<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    // Port 1 is closed in the containers, so the connection is refused right away.
    private const int UNREACHABLE_PORT = 1;

    #[Test]
    public function itReportsOkWhenAllDependenciesAreAvailable(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'checks' => ['app' => 'ok', 'database' => 'ok', 'redis' => 'ok'],
            ]);
    }

    #[Test]
    public function itReturns503WhenTheDatabaseIsUnavailable(): void
    {
        Log::spy();
        config(['database.connections.mysql.port' => self::UNREACHABLE_PORT]);
        DB::purge('mysql');

        // Exact JSON also proves no error details (SQL state, host, credentials) leak into the response.
        $this->getJson('/api/health')
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'fail',
                'checks' => ['app' => 'ok', 'database' => 'fail', 'redis' => 'ok'],
            ]);

        Log::shouldHaveReceived('error')->once()->with('Health check failed: database', Mockery::type('array'));
    }

    #[Test]
    public function itReturns503WhenRedisIsUnavailable(): void
    {
        Log::spy();
        config(['database.redis.default.port' => self::UNREACHABLE_PORT]);
        Redis::purge('default');

        $this->getJson('/api/health')
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'fail',
                'checks' => ['app' => 'ok', 'database' => 'ok', 'redis' => 'fail'],
            ]);

        Log::shouldHaveReceived('error')->once()->with('Health check failed: redis', Mockery::type('array'));
    }
}
