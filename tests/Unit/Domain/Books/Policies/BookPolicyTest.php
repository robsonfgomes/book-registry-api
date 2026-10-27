<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Books\Policies;

use App\Domain\Books\Models\Book;
use App\Domain\Books\Policies\BookPolicy;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookPolicyTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function abilities(): array
    {
        return [
            'view' => ['view'],
            'update' => ['update'],
            'delete' => ['delete'],
        ];
    }

    #[Test]
    #[DataProvider('abilities')]
    public function itAllowsTheOwner(string $ability): void
    {
        $owner = User::factory()->make(['id' => 1]);
        $book = Book::factory()->make(['user_id' => 1]);

        $response = (new BookPolicy)->{$ability}($owner, $book);

        $this->assertTrue($response->allowed());
    }

    #[Test]
    #[DataProvider('abilities')]
    public function itDeniesOtherUsersAsNotFound(string $ability): void
    {
        $otherUser = User::factory()->make(['id' => 2]);
        $book = Book::factory()->make(['user_id' => 1]);

        $response = (new BookPolicy)->{$ability}($otherUser, $book);

        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }
}
