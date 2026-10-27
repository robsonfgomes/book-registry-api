<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Books\Models\Book;
use App\Domain\Books\Policies\BookPolicy;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response as DocResponse;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Gate::policy(Book::class, BookPolicy::class);

        $this->configureRouteBindings();
        $this->configureRateLimiting();
        $this->configureApiDocumentation();
    }

    private function configureApiDocumentation(): void
    {
        Scramble::configure()
            // apiResource registers update as PUT|PATCH; fields are optional, so PATCH describes it best.
            ->preferPatchMethod()
            // Scramble does not infer the throttle middleware, so add its 429 response to every throttled route.
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo): void {
                $isThrottled = collect($routeInfo->route->gatherMiddleware())
                    ->contains(fn (string $middleware): bool => str_starts_with($middleware, 'throttle:'));

                if ($isThrottled) {
                    $operation->addResponse(
                        DocResponse::make(429)
                            ->setDescription('Too many requests')
                            ->setContent('application/json', Schema::fromType(
                                (new ObjectType)
                                    ->addProperty('message', (new StringType)->example('Too Many Attempts.'))
                                    ->setRequired(['message']),
                            )),
                    );
                }
            });
    }

    private function configureRouteBindings(): void
    {
        Route::bind('book', function (string $value): Book {
            return request()->user()->books()->findOrFail($value);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request): Limit {
            $limit = Limit::perMinute(Config::integer('api.rate_limits.auth'));
            $email = $request->input('email');

            if ($request->routeIs('v1.login') && is_string($email)) {
                return $limit->by(Str::lower($email).'|'.$request->ip());
            }

            return $limit->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(Config::integer('api.rate_limits.api'))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
