<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function itRevokesTheCurrentTokenAndReturns204(): void
    {
        $token = User::factory()->create()->createToken('api');

        $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/logout');

        $response->assertNoContent();
        $this->assertModelMissing($token->accessToken);
    }

    #[Test]
    public function itRejectsTheRevokedTokenWith401(): void
    {
        $token = User::factory()->create()->createToken('api')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();

        // The guard keeps the resolved user between requests in the same test; reset it like a new request would.
        $this->app['auth']->forgetGuards();
        $response = $this->withToken($token)->getJson('/api/v1/me');

        $response->assertUnauthorized();
    }

    #[Test]
    public function itKeepsTheUsersOtherTokens(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('api');
        $other = $user->createToken('api');

        $this->withToken($current->plainTextToken)->postJson('/api/v1/logout');

        $this->assertModelMissing($current->accessToken);
        $this->assertModelExists($other->accessToken);
    }
}
