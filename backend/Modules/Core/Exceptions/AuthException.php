<?php

namespace Modules\Core\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * خطأ مصادقة يحمل رمز حالة HTTP ورمز خطأ برمجي (وفق مخطط الخطأ في docs/09).
 */
class AuthException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 401,
    ) {
        parent::__construct($message);
    }

    /**
     * يعرض نفسه على مخطّط الخطأ الموحّد {data,meta,errors} برمزه الحالة الصحيح
     * ورسالته العربية — كي لا يتحوّل إلى 500 عامّ إن رُمي خارج catch الصريح
     * في AuthController (مثل مسارات المصادقة الأخرى/الوسائط).
     */
    public function render(Request $request): ?JsonResponse
    {
        if (! ($request->is('api/*') || $request->expectsJson())) {
            return null;
        }

        return response()->json([
            'data' => null,
            'meta' => null,
            'errors' => ['code' => $this->errorCode, 'message' => $this->getMessage()],
        ], $this->status);
    }

    public static function invalidCredentials(): self
    {
        return new self('INVALID_CREDENTIALS', 'بيانات الدخول غير صحيحة.', 401);
    }

    public static function accountLocked(): self
    {
        return new self('ACCOUNT_LOCKED', 'الحساب مقفل مؤقتاً بسبب محاولات دخول فاشلة متكررة.', 423);
    }

    public static function accountInactive(): self
    {
        return new self('ACCOUNT_INACTIVE', 'الحساب غير مُفعّل.', 403);
    }

    public static function invalidToken(): self
    {
        return new self('INVALID_TOKEN', 'رمز المصادقة غير صالح أو منتهٍ.', 401);
    }
}
