<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Books;

use App\Domain\Books\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookDestroyTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function itDeletesTheBookAndReturns204(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertNoContent();
        $this->assertModelMissing($book);
    }

    #[Test]
    public function itReturns404ForAnotherUsersBookAndKeepsIt(): void
    {
        $book = Book::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertNotFound()
            ->assertExactJson(['message' => 'Book not found.']);
        $this->assertModelExists($book);
    }
}
