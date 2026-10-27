<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array VALID_PAYLOAD = [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    #[Test]
    public function itCreatesTheUserAndReturns201WithAToken(): void
    {
        $response = $this->postJson('/api/v1/register', self::VALID_PAYLOAD);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Alice')
            ->assertJsonPath('data.email', 'alice@example.com')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'created_at', 'updated_at'], 'token']);

        $user = User::where('email', 'alice@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertCount(1, $user->tokens);
    }

    #[Test]
    public function itReturns422WhenTheEmailIsAlreadyTaken(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        $response = $this->postJson('/api/v1/register', self::VALID_PAYLOAD);

        $response->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['email' => 'The email has already been taken.']);
        $this->assertDatabaseCount('users', 1);
    }

    #[Test]
    public function itReturns422WhenRequiredFieldsAreMissing(): void
    {
        $response = $this->postJson('/api/v1/register', []);

        $response->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([
                'name' => 'The name field is required.',
                'email' => 'The email field is required.',
                'password' => 'The password field is required.',
            ]);
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidFields(): array
    {
        return [
            'short password' => [
                ['password' => 'short', 'password_confirmation' => 'short'],
                'password',
                'The password field must be at least 8 characters.',
            ],
            'missing confirmation' => [
                ['password_confirmation' => ''],
                'password',
                'The password field confirmation does not match.',
            ],
            'mismatched confirmation' => [
                ['password_confirmation' => 'different123'],
                'password',
                'The password field confirmation does not match.',
            ],
            'invalid email' => [
                ['email' => 'not-an-email'],
                'email',
                'The email field must be a valid email address.',
            ],
            'name too long' => [
                ['name' => str_repeat('a', 256)],
                'name',
                'The name field must not be greater than 255 characters.',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $override
     */
    #[Test]
    #[DataProvider('invalidFields')]
    public function itReturns422ForAnInvalidField(array $override, string $field, string $message): void
    {
        $response = $this->postJson('/api/v1/register', [...self::VALID_PAYLOAD, ...$override]);

        $response->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([$field => $message]);
        $this->assertDatabaseCount('users', 0);
    }

    #[Test]
    public function itReturns429AfterTooManyAttemptsFromTheSameIp(): void
    {
        config(['api.rate_limits.auth' => 3]);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/v1/register', [])->assertUnprocessable();
        }

        $this->postJson('/api/v1/register', self::VALID_PAYLOAD)
            ->assertTooManyRequests()
            ->assertExactJson(['message' => 'Too Many Attempts.']);
        $this->assertDatabaseCount('users', 0);
    }
}
