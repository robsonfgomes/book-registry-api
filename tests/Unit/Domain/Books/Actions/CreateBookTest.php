<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Books\Actions;

use App\Domain\Books\Actions\CreateBook;
use App\Domain\Books\Exceptions\DuplicateIsbnException;
use App\Domain\Books\Models\Book;
use App\Domain\Books\ValueObjects\Isbn;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateBookTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function itCreatesABookForTheUser(): void
    {
        $user = User::factory()->create();

        $book = (new CreateBook)->handle($user, 'Clean Code', new Isbn('9780132350884'), '89.90');

        $this->assertModelExists($book);
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'name' => 'Clean Code',
            'isbn' => '9780132350884',
            'value' => '89.90',
        ]);
    }

    #[Test]
    public function itThrowsDuplicateIsbnWhenTheUserAlreadyHasTheIsbn(): void
    {
        // Created through the factory, skipping validation: simulates a concurrent
        // request that inserted the same ISBN after this request was validated.
        $user = User::factory()->create();
        Book::factory()->for($user)->create(['isbn' => new Isbn('9780132350884')]);

        try {
            (new CreateBook)->handle($user, 'Clean Code', new Isbn('9780132350884'), '89.90');
            $this->fail('Expected DuplicateIsbnException was not thrown.');
        } catch (DuplicateIsbnException $exception) {
            $this->assertSame('9780132350884', $exception->isbn->value);
            $this->assertInstanceOf(UniqueConstraintViolationException::class, $exception->getPrevious());
        }

        $this->assertDatabaseCount('books', 1);
    }

    #[Test]
    public function itAllowsTheSameIsbnForADifferentUser(): void
    {
        Book::factory()->create(['isbn' => new Isbn('9780132350884')]);
        $user = User::factory()->create();

        $book = (new CreateBook)->handle($user, 'Clean Code', new Isbn('9780132350884'), '89.90');

        $this->assertModelExists($book);
        $this->assertDatabaseCount('books', 2);
    }
}
