<?php

namespace Modules\Core\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\AuthToken;

/**
 * إعادة تعيين كلمة المرور (نسيت كلمة المرور) — ضمن نطاق المصادقة (Issue #11).
 */
class PasswordController
{
    /** POST /api/auth/forgot-password */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // لا نكشف وجود البريد من عدمه (منع تعداد الحسابات).
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'data' => ['message' => 'إن كان البريد مسجّلاً فسيصلك رابط إعادة التعيين.'],
            'meta' => null,
            'errors' => null,
        ]);
    }

    /** POST /api/auth/reset-password */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                    'failed_attempts' => 0,
                    'locked_until' => null,
                    'remember_token' => Str::random(60),
                ])->save();

                // إبطال كل الجلسات النشطة عند تغيير كلمة المرور (docs/05 §5.3).
                AuthToken::where('user_id', $user->id)->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'password_reset',
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => ['code' => 'RESET_FAILED', 'message' => __($status)],
            ], 422);
        }

        return response()->json([
            'data' => ['message' => 'تم تحديث كلمة المرور بنجاح.'],
            'meta' => null,
            'errors' => null,
        ]);
    }
}
