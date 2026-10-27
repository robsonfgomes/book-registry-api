<?php

declare(strict_types=1);

namespace App\Domain\Books\Policies;

use App\Domain\Books\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookPolicy
{
    public function view(User $user, Book $book): Response
    {
        return $this->owns($user, $book);
    }

    public function update(User $user, Book $book): Response
    {
        return $this->owns($user, $book);
    }

    public function delete(User $user, Book $book): Response
    {
        return $this->owns($user, $book);
    }

    private function owns(User $user, Book $book): Response
    {
        return $user->id === $book->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
