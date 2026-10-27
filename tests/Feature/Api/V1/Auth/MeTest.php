<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MeTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function itReturnsTheAuthenticatedUser(): void
    {
        $this->travelTo('2026-01-15 10:30:00');
        $user = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => 'Alice',
                    'email' => 'alice@example.com',
                    'created_at' => '2026-01-15T10:30:00+00:00',
                    'updated_at' => '2026-01-15T10:30:00+00:00',
                ],
            ]);
    }

    #[Test]
    public function itAcceptsATokenThatIsAlmost24HoursOld(): void
    {
        $this->freezeTime();
        $token = User::factory()->create()->createToken('api')->plainTextToken;

        $this->travel(24 * 60 - 1)->minutes();
        $response = $this->withToken($token)->getJson('/api/v1/me');

        $response->assertOk();
    }

    #[Test]
    public function itRejectsATokenOlderThan24HoursWith401(): void
    {
        $this->freezeTime();
        $token = User::factory()->create()->createToken('api')->plainTextToken;

        $this->travel(24)->hours();
        $response = $this->withToken($token)->getJson('/api/v1/me');

        $response->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    #[Test]
    public function itReturns429AfterTooManyRequestsFromTheSameUser(): void
    {
        config(['api.rate_limits.api' => 2]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/me')->assertOk();
        $this->getJson('/api/v1/me')->assertOk();

        $this->getJson('/api/v1/me')
            ->assertTooManyRequests()
            ->assertExactJson(['message' => 'Too Many Attempts.']);
    }
}
