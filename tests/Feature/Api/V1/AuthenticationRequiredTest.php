<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationRequiredTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'logout' => ['POST', '/api/v1/logout'],
            'me' => ['GET', '/api/v1/me'],
            'list books' => ['GET', '/api/v1/books'],
            'create book' => ['POST', '/api/v1/books'],
            'show book' => ['GET', '/api/v1/books/1'],
            'replace book' => ['PUT', '/api/v1/books/1'],
            'update book' => ['PATCH', '/api/v1/books/1'],
            'delete book' => ['DELETE', '/api/v1/books/1'],
        ];
    }

    #[Test]
    #[DataProvider('protectedRoutes')]
    public function itReturns401WithoutAToken(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);

        $response->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    #[Test]
    #[DataProvider('protectedRoutes')]
    public function itReturns401WithAnInvalidToken(string $method, string $uri): void
    {
        $response = $this->withToken('1|invalid-token')->json($method, $uri);

        $response->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    #[Test]
    public function itReturns401AsJsonEvenWithoutAnAcceptHeader(): void
    {
        $response = $this->get('/api/v1/me');

        $response->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
