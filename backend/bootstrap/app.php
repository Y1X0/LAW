<?php

use App\Support\DbConstraintError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            // استثناء يحمل استجابة مُعدّة مسبقاً (throwResponse) — نُبقيها كما هي.
            if ($e instanceof HttpResponseException) {
                return null;
            }

            // تصنيف آمن لأخطاء قاعدة البيانات (تفرّد/مفتاح أجنبي/حقل مطلوب) إلى رسالة
            // عربية واضحة ورمز مناسب — دون تسريب أي SQL أو أسماء جداول في الإنتاج.
            if ($db = DbConstraintError::classify($e)) {
                [$status, $code, $message] = $db;

                return response()->json(
                    ['data' => null, 'meta' => null, 'errors' => ['code' => $code, 'message' => $message]],
                    $status,
                );
            }

            // ملاحظة: يحوّل معالج Laravel ModelNotFoundException/AuthorizationException إلى
            // HttpException قبل بلوغ هذا الردّ، لذا نكشف أصلها عبر getPrevious لتنقية الرسالة.
            $previous = $e instanceof HttpExceptionInterface ? $e->getPrevious() : null;

            [$status, $code] = match (true) {
                $e instanceof AuthenticationException => [401, 'UNAUTHENTICATED'],
                $e instanceof ModelNotFoundException => [404, 'HTTP_404'],
                $e instanceof AuthorizationException => [403, 'HTTP_403'],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'HTTP_'.$e->getStatusCode()],
                default => [500, 'SERVER_ERROR'],
            };

            if ($status >= 500) {
                // لا تُسرّب رسالة/أثر الاستثناء في الإنتاج.
                $message = config('app.debug') ? $e->getMessage() : 'حدث خطأ غير متوقّع.';
            } elseif ($e instanceof AuthenticationException) {
                $message = 'يلزم تسجيل الدخول للمتابعة.';
            } elseif ($previous instanceof ModelNotFoundException || $e instanceof ModelNotFoundException) {
                // لا نُظهر «No query results for model [App\Models\User] 5» — تسريب اسم النموذج.
                $message = 'العنصر المطلوب غير موجود.';
            } elseif ($previous instanceof AuthorizationException || $e instanceof AuthorizationException) {
                // توحيد كل رفض صلاحيات (بما فيه Gate::authorize) على رسالة عربية واضحة،
                // بدل رسالة Laravel الإنجليزية «This action is unauthorized.» — مع احترام أي
                // رسالة عربية مخصّصة إن وُجدت (لا نطمسها).
                $orig = $previous instanceof AuthorizationException ? $previous->getMessage() : $e->getMessage();
                $default = in_array($orig, ['', 'This action is unauthorized.', 'This action is unauthorized'], true);
                $message = $default ? 'لا تملك صلاحية تنفيذ هذا الإجراء.' : $orig;
            } else {
                // HttpException مقصود (abort(4xx,'رسالة عربية')) — نُبقي رسالته إن كانت ذات معنى،
                // وإلا رسالة عربية افتراضية حسب الحالة (نتجنّب رسائل Symfony الإنجليزية الافتراضية).
                $raw = $e instanceof HttpExceptionInterface ? $e->getMessage() : '';
                $symfonyDefaults = ['', 'Not Found', 'Forbidden', 'Unauthorized', 'Bad Request', 'Method Not Allowed', 'Conflict', 'Unprocessable Entity', 'Too Many Requests', 'Internal Server Error'];
                $message = in_array($raw, $symfonyDefaults, true) ? match ($status) {
                    400 => 'طلب غير صالح.',
                    401 => 'يلزم تسجيل الدخول للمتابعة.',
                    403 => 'لا تملك صلاحية تنفيذ هذا الإجراء.',
                    404 => 'العنصر المطلوب غير موجود.',
                    405 => 'الإجراء غير مسموح به.',
                    409 => 'تعارض مع الحالة الحالية للسجلّ.',
                    419 => 'انتهت صلاحية الجلسة. يرجى تحديث الصفحة والمحاولة مجدداً.',
                    422 => 'بيانات غير صحيحة.',
                    429 => 'محاولات كثيرة جداً. يرجى المحاولة بعد قليل.',
                    default => 'تعذّرت العملية.',
                } : $raw;
            }

            $errors = ['code' => $code, 'message' => $message];
            if (config('app.debug') && $status >= 500) {
                $errors['debug'] = ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()];
            }

            return response()->json(['data' => null, 'meta' => null, 'errors' => $errors], $status);
        });
    })->create();
