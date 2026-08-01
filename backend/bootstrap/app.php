<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        // توحيد بقية الأخطاء (غير التحقّق) على نفس مخطّط الاستجابة {data,meta,errors}
        // لطلبات الـ API فقط، مع عدم تسريب أي تفاصيل داخلية في الإنتاج (يعتمد app.debug).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null; // غير API → السلوك الافتراضي
            }
            if ($e instanceof ValidationException) {
                return null; // يعالجه المعالج أعلاه
            }

            [$status, $code] = match (true) {
                $e instanceof AuthenticationException => [401, 'UNAUTHENTICATED'],
                $e instanceof ModelNotFoundException => [404, 'NOT_FOUND'],
                $e instanceof AuthorizationException => [403, 'FORBIDDEN'],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'HTTP_'.$e->getStatusCode()],
                default => [500, 'SERVER_ERROR'],
            };

            if ($status >= 500) {
                // لا تُسرّب رسالة/أثر الاستثناء في الإنتاج.
                $message = config('app.debug') ? $e->getMessage() : 'حدث خطأ غير متوقّع.';
            } else {
                $message = $e->getMessage() ?: 'تعذّرت العملية.';
            }

            $errors = ['code' => $code, 'message' => $message];
            if (config('app.debug') && $status >= 500) {
                $errors['debug'] = ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()];
            }

            return response()->json(['data' => null, 'meta' => null, 'errors' => $errors], $status);
        });
    })->create();
