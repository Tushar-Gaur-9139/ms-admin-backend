<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Security Shield: Prevent raw SQL queries, table names, and database internals from leaking to API clients
        $exceptions->render(function (QueryException $e, Request $request) {
            Log::error('Database QueryException masked for security: '.$e->getMessage(), [
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'url' => $request->fullUrl(),
            ]);

            if ($e->getCode() == 23000 || str_contains($e->getMessage(), '1062 Duplicate entry')) {
                return response()->json([
                    'message' => 'A record with matching unique details (such as phone or email) already exists.',
                ], 422);
            }

            return response()->json([
                'message' => 'A database error occurred while processing your request. Please try again or contact support.',
            ], 500);
        });

        $exceptions->render(function (\PDOException $e, Request $request) {
            Log::error('Database PDOException masked for security: '.$e->getMessage(), [
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'message' => 'Unable to connect to the database service. Please try again shortly.',
            ], 500);
        });
    })->create();
