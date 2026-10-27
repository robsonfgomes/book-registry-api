<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Books;

use App\Domain\Books\Models\Book;
use App\Domain\Books\ValueObjects\Isbn;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function itReplacesAllFieldsWithPut(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'name' => 'Refactoring',
            'isbn' => '9780134757599',
            'value' => '49.50',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Refactoring')
            ->assertJsonPath('data.isbn', '9780134757599')
            ->assertJsonPath('data.value', '49.50');
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'name' => 'Refactoring',
            'isbn' => '9780134757599',
            'value' => '49.50',
        ]);
    }

    #[Test]
    public function itChangesOnlyTheSentFieldsWithPatch(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create([
            'name' => 'Clean Code',
            'isbn' => new Isbn('9780132350884'),
            'value' => '89.90',
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/books/{$book->id}", ['name' => 'Clean Code (2nd edition)']);

        $response->assertOk();
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'name' => 'Clean Code (2nd edition)',
            'isbn' => '9780132350884',
            'value' => '89.90',
        ]);
    }

    #[Test]
    public function itAllowsKeepingTheBooksOwnIsbn(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['isbn' => new Isbn('9780132350884')]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'name' => 'Clean Code',
            'isbn' => '9780132350884',
            'value' => '79.00',
        ]);

        $response->assertOk();
    }

    #[Test]
    public function itReturns422WhenAnotherBookOfTheUserHasTheIsbn(): void
    {
        $user = User::factory()->create();
        Book::factory()->for($user)->create(['isbn' => new Isbn('9780132350884')]);
        $book = Book::factory()->for($user)->create(['isbn' => new Isbn('9780134757599')]);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/books/{$book->id}", ['isbn' => '9780132350884']);

        $response->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['isbn' => 'You already have a book with this ISBN.']);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'isbn' => '9780134757599']);
    }

    #[Test]
    public function itReturns422ForAnInvalidField(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['isbn' => new Isbn('9780132350884')]);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/books/{$book->id}", ['isbn' => '97801323508AB']);

        $response->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['isbn' => 'The ISBN field must be 13 digits.']);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'isbn' => '9780132350884']);
    }

    #[Test]
    public function itReturns404ForAnotherUsersBookAndLeavesItUnchanged(): void
    {
        $book = Book::factory()->create(['name' => 'Clean Code']);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->patchJson("/api/v1/books/{$book->id}", ['name' => 'Hijacked']);

        $response->assertNotFound()
            ->assertExactJson(['message' => 'Book not found.']);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'name' => 'Clean Code']);
    }
}
