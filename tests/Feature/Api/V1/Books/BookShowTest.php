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

class BookShowTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function itReturnsTheBook(): void
    {
        $this->travelTo('2026-01-15 10:30:00');
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create([
            'name' => 'Clean Code',
            'isbn' => new Isbn('9780132350884'),
            'value' => '89.90',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/books/{$book->id}");

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $book->id,
                    'name' => 'Clean Code',
                    'isbn' => '9780132350884',
                    'value' => '89.90',
                    'created_at' => '2026-01-15T10:30:00+00:00',
                    'updated_at' => '2026-01-15T10:30:00+00:00',
                ],
            ]);
    }

    #[Test]
    public function itReturns404ForAnotherUsersBook(): void
    {
        $book = Book::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/v1/books/{$book->id}");

        $response->assertNotFound()
            ->assertExactJson(['message' => 'Book not found.']);
    }

    #[Test]
    public function itReturns404ForABookThatDoesNotExist(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/books/999');

        $response->assertNotFound()
            ->assertExactJson(['message' => 'Book not found.']);
    }
}
