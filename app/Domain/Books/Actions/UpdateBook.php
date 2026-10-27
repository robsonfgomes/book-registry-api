<?php

declare(strict_types=1);

namespace App\Domain\Books\Actions;

use App\Domain\Books\Exceptions\DuplicateIsbnException;
use App\Domain\Books\Models\Book;
use App\Domain\Books\ValueObjects\Isbn;
use Illuminate\Database\UniqueConstraintViolationException;

class UpdateBook
{
    public function handle(Book $book, string $name, Isbn $isbn, string $value): Book
    {
        try {
            $book->update([
                'name' => $name,
                'isbn' => $isbn,
                'value' => $value,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateIsbnException($isbn, $exception);
        }

        return $book;
    }
}
