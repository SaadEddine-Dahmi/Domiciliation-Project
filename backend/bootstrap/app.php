<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use App\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__ . '/../routes/web.php',
        api:      __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);
        // statefulApi() intentionally removed — Bearer token only, no CSRF
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // FIX: Return 401 JSON for unauthenticated API requests.
        // Without this, Laravel tries to redirect to route('login')
        // which does not exist in API-only apps → RouteNotFoundException 500.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Non authentifié.',
                ], 401);
            }
        });

    })->create();