<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request): Limit {
            $limit = Limit::perMinute(Config::integer('api.rate_limits.auth'));
            $email = $request->input('email');

            // Login is limited per IP + email (slows down password guessing on one account);
            // register only per IP, so one client cannot create accounts in bulk.
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
