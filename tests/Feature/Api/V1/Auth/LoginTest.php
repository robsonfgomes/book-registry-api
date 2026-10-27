<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function itReturnsTheUserAndATokenForValidCredentials(): void
    {
        $user = User::factory()->create(['email' => 'alice@example.com', 'password' => 'secret123']);

        $response = $this->postJson('/api/v1/login', ['email' => 'alice@example.com', 'password' => 'secret123']);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'alice@example.com')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'created_at', 'updated_at'], 'token']);
        $this->assertCount(1, $user->tokens);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidCredentials(): array
    {
        return [
            'wrong password' => ['alice@example.com', 'wrong-password'],
            'unknown email' => ['nobody@example.com', 'secret123'],
        ];
    }

    #[Test]
    #[DataProvider('invalidCredentials')]
    public function itReturns422WithTheSameGenericMessageForInvalidCredentials(string $email, string $password): void
    {
        User::factory()->create(['email' => 'alice@example.com', 'password' => 'secret123']);

        $response = $this->postJson('/api/v1/login', ['email' => $email, 'password' => $password]);

        $response->assertUnprocessable()
            ->assertExactJson([
                'message' => 'These credentials do not match our records.',
                'errors' => ['email' => ['These credentials do not match our records.']],
            ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function itReturns422WhenRequiredFieldsAreMissing(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([
                'email' => 'The email field is required.',
                'password' => 'The password field is required.',
            ]);
    }

    #[Test]
    public function itReturns429AfterTooManyAttemptsForTheSameEmail(): void
    {
        config(['api.rate_limits.auth' => 3]);
        User::factory()->create(['email' => 'alice@example.com', 'password' => 'secret123']);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/v1/login', ['email' => 'alice@example.com', 'password' => 'wrong-password'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/login', ['email' => 'alice@example.com', 'password' => 'secret123'])
            ->assertTooManyRequests()
            ->assertExactJson(['message' => 'Too Many Attempts.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function itLimitsEachEmailSeparately(): void
    {
        config(['api.rate_limits.auth' => 3]);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/v1/login', ['email' => 'alice@example.com', 'password' => 'wrong-password']);
        }

        $this->postJson('/api/v1/login', ['email' => 'bob@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable();
    }
}
