<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Books\Actions;

use App\Domain\Books\Actions\UpdateBook;
use App\Domain\Books\Exceptions\DuplicateIsbnException;
use App\Domain\Books\Models\Book;
use App\Domain\Books\ValueObjects\Isbn;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateBookTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function itUpdatesTheBook(): void
    {
        $book = Book::factory()->create();

        (new UpdateBook)->handle($book, 'Refactoring', new Isbn('9780134757599'), '49.50');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'name' => 'Refactoring',
            'isbn' => '9780134757599',
            'value' => '49.50',
        ]);
    }

    #[Test]
    public function itAllowsKeepingTheBooksOwnIsbn(): void
    {
        $book = Book::factory()->create(['isbn' => new Isbn('9780132350884')]);

        (new UpdateBook)->handle($book, 'Renamed', new Isbn('9780132350884'), '10.00');

        $this->assertDatabaseHas('books', ['id' => $book->id, 'name' => 'Renamed', 'isbn' => '9780132350884']);
    }

    #[Test]
    public function itThrowsDuplicateIsbnWhenAnotherBookOfTheUserHasTheIsbn(): void
    {
        // Created through the factory, skipping validation: simulates a concurrent
        // request that took the ISBN after this request was validated.
        $user = User::factory()->create();
        Book::factory()->for($user)->create(['isbn' => new Isbn('9780132350884')]);
        $book = Book::factory()->for($user)->create(['isbn' => new Isbn('9780134757599')]);

        try {
            (new UpdateBook)->handle($book, $book->name, new Isbn('9780132350884'), $book->value);
            $this->fail('Expected DuplicateIsbnException was not thrown.');
        } catch (DuplicateIsbnException $exception) {
            $this->assertSame('9780132350884', $exception->isbn->value);
        }

        $this->assertDatabaseHas('books', ['id' => $book->id, 'isbn' => '9780134757599']);
    }
}
