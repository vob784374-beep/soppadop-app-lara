<?php

use App\Providers\AuthServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
        $middleware->alias([
            'jwt.authenticate' => \App\Http\Middleware\JwtAuthenticate::class,
            'permission'       => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request): ?\Illuminate\Http\JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                $e instanceof \Illuminate\Validation\ValidationException => response()->json([
                    'data'    => null,
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                    'meta'    => null,
                ], 422),

                $e instanceof \Illuminate\Auth\AuthenticationException => response()->json([
                    'data'    => null,
                    'message' => 'Unauthenticated.',
                    'errors'  => null,
                    'meta'    => null,
                ], 401),

                $e instanceof \Illuminate\Auth\Access\AuthorizationException => response()->json([
                    'data'    => null,
                    'message' => 'This action is unauthorized.',
                    'errors'  => null,
                    'meta'    => null,
                ], 403),

                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => response()->json([
                    'data'    => null,
                    'message' => 'Resource not found.',
                    'errors'  => null,
                    'meta'    => null,
                ], 404),

                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface => response()->json([
                    'data'    => null,
                    'message' => $e->getMessage() ?: 'HTTP error.',
                    'errors'  => null,
                    'meta'    => null,
                ], $e->getStatusCode()),

                default => response()->json([
                    'data'    => null,
                    'message' => 'An unexpected error occurred.',
                    'errors'  => null,
                    'meta'    => null,
                ], 500),
            };
        });
    })->create();
