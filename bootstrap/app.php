<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__ . '/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        ['middleware' => ['auth:api']], // adjust guard once case-api exists in Phase 7
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 401 Errors, require auth
        $exceptions->render(function (AuthenticationException $e, $request) {
            if (!$request->is('api/*')) return null;
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Unauthenticated',
                'status' => 401,
                'detail' => 'Authentication is required to access this resource.',
                'instance' => $request->path(),
            ], 401);
        });

        // 404 Errors, not found
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if (!$request->is('api/*')) return null;
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Resource not found',
                'status' => 404,
                'detail' => 'The requested resource does not exist.',
                'instance' => $request->path(),
            ], 404);
        });

        // 422 Errors, Validation errors
        $exceptions->render(function (ValidationException $e, $request) {
            if (!$request->is('api/*')) return null;
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Validation failed',
                'status' => 422,
                'detail' => 'One or more fields failed validation.',
                'instance' => $request->path(),
                'errors' => $e->errors(),
            ], 422);
        });

        // 500 Internal server errors
        $exceptions->render(function (Throwable $e, $request) {
            if (!$request->is('api/*')) return null; // let web routes use default Laravel error pages

            return response()->json([
                'type' => 'about:blank',
                'title' => 'An unexpected error occurred',
                'status' => 500,
                'detail' => config('app.debug') ? $e->getMessage() : 'Something went wrong.',
                'instance' => $request->path(),
            ], 500);
        });
    })->create();
