<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
     $middleware->redirectGuestsTo(
        fn (Request $request) => $request->is('api/*')
            ? null
            : route('login')
    );
  })
    ->withExceptions(function (Exceptions $exceptions): void {

    // Always return JSON for API requests.
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request, Throwable $exception) =>
            $request->is('api/*') || $request->expectsJson()
    );

    // Validation errors: 422
   $exceptions->render(function (
    ValidationException $exception,
    Request $request
   ) {
    if (! $request->is('api/*')) {
        return null;
    }

    return response()->json([
        'success' => false,
        'message' => $exception->getMessage(),
        'errors' => $exception->errors(),
    ], 422);
  });

    // Authentication errors: 401
    $exceptions->render(function (
        AuthenticationException $exception,
        Request $request
    ) {
        if (! $request->is('api/*')) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    });

    // Authorization errors: 403
    $exceptions->render(function (
        AuthorizationException $exception,
        Request $request
    ) {
        if (! $request->is('api/*')) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'This action is unauthorized.',
        ], 403);
    });

    // Missing database models: 404
    $exceptions->render(function (
        ModelNotFoundException $exception,
        Request $request
    ) {
        if (! $request->is('api/*')) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Resource not found.',
        ], 404);
    });

    // Other HTTP exceptions: 4xx / 5xx
    $exceptions->render(function (
        HttpExceptionInterface $exception,
        Request $request
    ) {
        if (! $request->is('api/*')) {
            return null;
        }

        $status = $exception->getStatusCode();

        $message = match ($status) {
             401 => 'Unauthenticated.',
             403 => 'This action is unauthorized.',
             404 => 'Resource not found.',
             409 => $exception->getMessage() ?: 'Conflict.',
              default => $status >= 500
                ? 'Server error.'
                : 'Request failed.',
        };

        return response()->json([
            'success' => false,
            'message' => $message,
           ], $status);
        });

    // Unexpected errors: 500
    $exceptions->render(function (
        Throwable $exception,
        Request $request
    ) {
        if (! $request->is('api/*')) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'An unexpected error occurred.',
        ], 500);
    });

})->create();
