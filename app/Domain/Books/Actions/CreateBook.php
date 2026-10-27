<?php

declare(strict_types=1);

namespace App\Domain\Books\Actions;

use App\Domain\Books\Exceptions\DuplicateIsbnException;
use App\Domain\Books\Models\Book;
use App\Domain\Books\ValueObjects\Isbn;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateBook
{
    public function handle(User $user, string $name, Isbn $isbn, string $value): Book
    {
        try {
            return $user->books()->create([
                'name' => $name,
                'isbn' => $isbn,
                'value' => $value,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateIsbnException($isbn, $exception);
        }
    }
}
