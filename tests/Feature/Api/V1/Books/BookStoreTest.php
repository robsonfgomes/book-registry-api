<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Books;

use App\Domain\Books\Actions\CreateBook;
use App\Domain\Books\Exceptions\DuplicateIsbnException;
use App\Domain\Books\Models\Book;
use App\Domain\Books\ValueObjects\Isbn;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BookStoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array VALID_PAYLOAD = [
        'name' => 'Clean Code',
        'isbn' => '9780132350884',
        'value' => '89.90',
    ];

    #[Test]
    public function itCreatesTheBookForTheAuthenticatedUserAndReturns201(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/books', self::VALID_PAYLOAD);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Clean Code')
            ->assertJsonPath('data.isbn', '9780132350884')
            ->assertJsonPath('data.value', '89.90');
        $this->assertDatabaseHas('books', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'name' => 'Clean Code',
            'isbn' => '9780132350884',
            'value' => '89.90',
        ]);
    }

    #[Test]
    public function itIgnoresAUserIdSentInThePayload(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/books', [...self::VALID_PAYLOAD, 'user_id' => $otherUser->id])->assertCreated();

        $this->assertDatabaseHas('books', ['isbn' => '9780132350884', 'user_id' => $user->id]);
        $this->assertDatabaseMissing('books', ['user_id' => $otherUser->id]);
    }

    #[Test]
    public function itAcceptsTheValueAsAJsonNumberAndReturnsItAsAString(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', [...self::VALID_PAYLOAD, 'value' => 49.5]);

        $response->assertCreated()
            ->assertJsonPath('data.value', '49.50');
    }

    #[Test]
    #[TestWith(['0'], 'zero')]
    #[TestWith(['99999999.99'], 'maximum')]
    public function itAcceptsTheValueBoundaries(string $value): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', [...self::VALID_PAYLOAD, 'value' => $value]);

        $response->assertCreated();
    }

    #[Test]
    public function itReturns422WhenRequiredFieldsAreMissing(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', []);

        $response->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([
                'name' => 'The name field is required.',
                'isbn' => 'The ISBN field is required.',
                'value' => 'The value field is required.',
            ]);
        $this->assertDatabaseCount('books', 0);
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidFields(): array
    {
        return [
            'name too long' => [
                'name',
                str_repeat('a', 256),
                'The name field must not be greater than 255 characters.',
            ],
            'isbn with letters' => ['isbn', '97801323508AB', 'The ISBN field must be 13 digits.'],
            'isbn with 12 digits' => ['isbn', '978013235088', 'The ISBN field must be 13 digits.'],
            'isbn with 14 digits' => ['isbn', '97801323508841', 'The ISBN field must be 13 digits.'],
            'isbn as a number' => ['isbn', 9780132350884, 'The ISBN field must be a string.'],
            'negative value' => ['value', '-0.01', 'The value field must be at least 0.'],
            'non-numeric value' => ['value', 'abc', 'The value field must be a number.'],
            'value above the maximum' => [
                'value',
                '100000000',
                'The value field must not be greater than 99999999.99.',
            ],
            'value with 3 decimals' => ['value', '10.999', 'The value field must have 0-2 decimal places.'],
        ];
    }

    #[Test]
    #[DataProvider('invalidFields')]
    public function itReturns422ForAnInvalidField(string $field, mixed $value, string $message): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', [...self::VALID_PAYLOAD, $field => $value]);

        $response->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([$field => $message]);
        $this->assertDatabaseCount('books', 0);
    }

    #[Test]
    public function itReturns422WhenTheUserAlreadyHasABookWithTheIsbn(): void
    {
        $user = User::factory()->create();
        Book::factory()->for($user)->create(['isbn' => new Isbn('9780132350884')]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/books', self::VALID_PAYLOAD);

        $response->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['isbn' => 'You already have a book with this ISBN.']);
        $this->assertDatabaseCount('books', 1);
    }

    #[Test]
    public function itAllowsAnIsbnAlreadyUsedByAnotherUser(): void
    {
        Book::factory()->create(['isbn' => new Isbn('9780132350884')]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', self::VALID_PAYLOAD);

        $response->assertCreated();
        $this->assertDatabaseCount('books', 2);
    }

    #[Test]
    public function itReturns422WhenADuplicateIsbnIsOnlyDetectedByTheDatabase(): void
    {
        // Simulates the race: validation passed, but a concurrent request inserted the ISBN first.
        $this->mock(CreateBook::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andThrow(new DuplicateIsbnException(new Isbn('9780132350884')));
        });
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', self::VALID_PAYLOAD);

        $response->assertUnprocessable()
            ->assertExactJson([
                'message' => 'You already have a book with this ISBN.',
                'errors' => ['isbn' => ['You already have a book with this ISBN.']],
            ]);
    }
}
