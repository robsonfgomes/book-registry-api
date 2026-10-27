<?php

use App\Domain\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // There is no login page to redirect to: unauthenticated requests get a 401 JSON response instead.
        $middleware->redirectGuestsTo(fn (): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->map(
            ModelNotFoundException::class,
            fn (ModelNotFoundException $exception) => new NotFoundHttpException(
                class_basename($exception->getModel()).' not found.',
                $exception,
            ),
        );

        $exceptions->map(
            BusinessRuleException::class,
            fn (BusinessRuleException $exception) => ValidationException::withMessages([
                $exception->field() => $exception->getMessage(),
            ]),
        );
    })->create();
