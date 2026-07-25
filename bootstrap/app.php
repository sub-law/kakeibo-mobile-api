<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: [
            'prefix' => 'api',
            'path' => __DIR__ . '/../routes/api.php',
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {

        // ★ Laravel 11 では auth と auth.basic の両方を上書きする必要がある
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.basic' => \App\Http\Middleware\Authenticate::class,
        ]);

        // /api/login を CSRF チェックから除外
        $middleware->validateCsrfTokens(except: [
            'api/login',
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        // ★ 未認証時に login へリダイレクトせず JSON を返す
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        });
    })->create();
