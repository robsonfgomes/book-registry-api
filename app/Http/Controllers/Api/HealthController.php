<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Response as DocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    #[DocResponse(200, 'All dependencies are available', type: "array{status: 'ok', checks: array{app: 'ok', database: 'ok'|'fail', redis: 'ok'|'fail'}}")]
    #[DocResponse(503, 'A dependency is unavailable', type: "array{status: 'fail', checks: array{app: 'ok', database: 'ok'|'fail', redis: 'ok'|'fail'}}")]
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => 'ok',
            'database' => $this->check('database', fn () => DB::select('SELECT 1')),
            'redis' => $this->check('redis', fn () => Redis::connection()->ping()),
        ];

        $healthy = ! in_array('fail', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'fail',
            'checks' => $checks,
        ], $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    private function check(string $name, callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable $exception) {
            Log::error("Health check failed: {$name}", ['exception' => $exception]);

            return 'fail';
        }
    }
}
