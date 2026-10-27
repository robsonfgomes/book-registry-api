<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Books;

use App\Domain\Books\Models\Book;
use App\Domain\Books\ValueObjects\Isbn;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function itListsOnlyTheAuthenticatedUsersBooksNewestFirst(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->for($user)->count(3)->sequence(
            ['created_at' => '2026-01-01 10:00:00'],
            ['created_at' => '2026-01-03 10:00:00'],
            ['created_at' => '2026-01-02 10:00:00'],
        )->create();
        Book::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/books');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
        $this->assertSame([$books[1]->id, $books[2]->id, $books[0]->id], $response->json('data.*.id'));
    }

    #[Test]
    public function itOrdersBooksCreatedAtTheSameTimeByNewestId(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $first = Book::factory()->for($user)->create();
        $second = Book::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/books');

        $this->assertSame([$second->id, $first->id], $response->json('data.*.id'));
    }

    #[Test]
    public function itReturnsEachBookInTheApiFormat(): void
    {
        $this->travelTo('2026-01-15 10:30:00');
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create([
            'name' => 'Clean Code',
            'isbn' => new Isbn('0780132350884'),
            'value' => '89.9',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/books');

        $response->assertJsonPath('data.0', [
            'id' => $book->id,
            'name' => 'Clean Code',
            'isbn' => '0780132350884',
            'value' => '89.90',
            'created_at' => '2026-01-15T10:30:00+00:00',
            'updated_at' => '2026-01-15T10:30:00+00:00',
        ]);
    }

    #[Test]
    public function itPaginates15BooksPerPageByDefault(): void
    {
        $user = User::factory()->create();
        Book::factory()->for($user)->count(16)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/books');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.last_page', 2);
    }

    #[Test]
    public function itUsesTheRequestedPageAndPageSize(): void
    {
        $user = User::factory()->create();
        Book::factory()->for($user)->count(5)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/books?page=2&per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2);
        $this->assertStringEndsWith('/api/v1/books?per_page=2&page=3', $response->json('links.next'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidPagination(): array
    {
        return [
            'per_page zero' => ['per_page', '0', 'The per page field must be at least 1.'],
            'per_page above max' => ['per_page', '101', 'The per page field must not be greater than 100.'],
            'per_page not numeric' => ['per_page', 'abc', 'The per page field must be an integer.'],
            'page zero' => ['page', '0', 'The page field must be at least 1.'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPagination')]
    public function itReturns422ForInvalidPagination(string $parameter, string $value, string $message): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/books?'.http_build_query([$parameter => $value]));

        $response->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([$parameter => $message]);
    }
}
