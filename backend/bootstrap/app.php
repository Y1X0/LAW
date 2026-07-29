<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // توحيد أخطاء التحقق على مخطط الاستجابة الموحّد {data, meta, errors} (docs/09).
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'data' => null,
                    'meta' => null,
                    'errors' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'بيانات غير صحيحة.',
                        'fields' => $e->errors(),
                    ],
                ], 422);
            }

            return null;
        });
    })->create();
