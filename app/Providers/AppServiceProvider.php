<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Books\Models\Book;
use App\Domain\Books\Policies\BookPolicy;
use Illuminate\Cache\RateLimiting\Limit;
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
        Gate::policy(Book::class, BookPolicy::class);

        $this->configureRouteBindings();
        $this->configureRateLimiting();
    }

    private function configureRouteBindings(): void
    {
        // {book} is resolved only among the authenticated user's books, so another user's book is a 404.
        Route::bind('book', function (string $value): Book {
            return request()->user()->books()->findOrFail($value);
        });
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
