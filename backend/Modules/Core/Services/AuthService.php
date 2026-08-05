<?php

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Exceptions\AuthException;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\AuthToken;

/**
 * منطق المصادقة (Issue #11): تسجيل الدخول/الخروج، إصدار وتدوير التوكنات،
 * قفل الحساب بعد المحاولات الفاشلة، وتسجيل أحداث Audit.
 * لا يتضمّن RBAC (Issue #12).
 */
class AuthService
{
    public const ACCESS_TTL_MINUTES = 15;

    public const REFRESH_TTL_DAYS = 14;

    public const MAX_FAILED_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    /**
     * SEC-4: تجزئة وهمية صالحة (bcrypt) للتحقّق بزمن ثابت تقريباً حين لا يوجد مستخدم —
     * يمنع تمييز البريد الموجود عن غير الموجود عبر فرق التوقيت.
     */
    private const DUMMY_HASH = '$2y$12$D7XlJWG07rTWZ./8kQqxbOB5wJOQ787JLgNBVVpRx/5/s/brOzHIC';

    /**
     * تسجيل الدخول: يتحقق من البيانات والحالة والقفل، ثم يصدر زوج توكنات.
     *
     * @return array{user: User, tokens: array{access_token: string, refresh_token: string, access_expires_at: string, refresh_expires_at: string}}
     */
    public function login(string $email, string $password, Request $request): array
    {
        $user = User::where('email', $email)->first();

        // SEC-4: تحقّق من كلمة المرور دائماً (حتى لمستخدم غير موجود، عبر تجزئة وهمية)
        // بزمن ثابت تقريباً — يمنع تعداد الحسابات بفارق التوقيت.
        $passwordValid = $user
            ? Hash::check($password, $user->password)
            : $this->dummyCheck($password);

        // SEC-4: لا تُكشَف حالة الحساب (وجود/قفل/تعطيل) إلا لمن أثبت كلمة المرور الصحيحة.
        // بكلمة مرور خاطئة، كل الحالات تعيد INVALID_CREDENTIALS موحّدة (لا تمييز).
        if (! $passwordValid) {
            $isLocked = $user && $user->locked_until && $user->locked_until->isFuture();
            if ($user && $user->status === 'active' && ! $isLocked) {
                $this->registerFailedAttempt($user);
            }
            $this->audit('login_failed', $user, $request, ['email' => $email]);
            throw AuthException::invalidCredentials();
        }

        // كلمة المرور صحيحة: الآن فقط نُبلّغ المستخدم الشرعي بحالة حسابه.
        if ($user->locked_until && $user->locked_until->isFuture()) {
            $this->audit('login_locked', $user, $request);
            throw AuthException::accountLocked();
        }

        if ($user->status !== 'active') {
            $this->audit('login_inactive', $user, $request);
            throw AuthException::accountInactive();
        }

        // نجاح: تصفير العدّاد وتحديث آخر دخول
        $user->forceFill([
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();

        $tokens = $this->issueTokens($user, $request);
        $this->audit('login', $user, $request);

        return ['user' => $user, 'tokens' => $tokens];
    }

    /**
     * إصدار زوج توكنات جديد وتخزينه مجزّأً.
     */
    public function issueTokens(User $user, Request $request): array
    {
        $access = Str::random(64);
        $refresh = Str::random(64);
        $accessExpires = now()->addMinutes(self::ACCESS_TTL_MINUTES);
        $refreshExpires = now()->addDays(self::REFRESH_TTL_DAYS);

        AuthToken::create([
            'user_id' => $user->id,
            'name' => $request->userAgent() ? Str::limit($request->userAgent(), 115, '') : null,
            'access_token_hash' => $this->hash($access),
            'refresh_token_hash' => $this->hash($refresh),
            'access_expires_at' => $accessExpires,
            'refresh_expires_at' => $refreshExpires,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_used_at' => now(),
        ]);

        return [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'access_expires_at' => $accessExpires->toIso8601String(),
            'refresh_expires_at' => $refreshExpires->toIso8601String(),
        ];
    }

    /**
     * تدوير التوكنات: يُبطل التوكن الحالي ويُصدر زوجاً جديداً.
     */
    public function refresh(string $refreshToken, Request $request): array
    {
        $token = AuthToken::where('refresh_token_hash', $this->hash($refreshToken))
            ->whereNull('revoked_at')
            ->first();

        if (! $token || $token->refresh_expires_at->isPast()) {
            throw AuthException::invalidToken();
        }

        $user = $token->user;

        // M8: أعِد فحص حالة الحساب عند التدوير. حساب موقوف/مقفول (أو محذوف) يجب ألا يُبقي جلسته
        // حيّة حتى 14 يوماً عبر refresh — يُبطَل التوكن المقدَّم ويُرفَض الطلب برمز عام (لا كشف حالة،
        // ويُجبَر إعادة الدخول). يوازي فحوص login (status/locked_until).
        $isLocked = $user && $user->locked_until && $user->locked_until->isFuture();
        if ($user === null || $user->status !== 'active' || $isLocked) {
            $token->forceFill(['revoked_at' => now()])->save();
            $this->audit('token_refresh_denied', $user, $request);
            throw AuthException::invalidToken();
        }

        $token->forceFill(['revoked_at' => now()])->save(); // تدوير: إبطال القديم

        $tokens = $this->issueTokens($user, $request);
        $this->audit('token_refresh', $user, $request);

        return ['user' => $user, 'tokens' => $tokens];
    }

    /**
     * إبطال توكن (تسجيل خروج).
     */
    public function logout(AuthToken $token, Request $request): void
    {
        $token->forceFill(['revoked_at' => now()])->save();
        $this->audit('logout', $token->user, $request);
    }

    /**
     * حلّ توكن الوصول (Bearer) إلى سجل توكن صالح، أو null.
     */
    public function resolveAccessToken(string $accessToken): ?AuthToken
    {
        $token = AuthToken::where('access_token_hash', $this->hash($accessToken))
            ->whereNull('revoked_at')
            ->first();

        if (! $token || $token->access_expires_at->isPast()) {
            return null;
        }

        return $token;
    }

    /** SEC-4: يشغّل تجزئة bcrypt على تجزئة وهمية لمعادلة التوقيت، ويعيد false دائماً. */
    private function dummyCheck(string $password): bool
    {
        Hash::check($password, self::DUMMY_HASH);

        return false;
    }

    private function registerFailedAttempt(User $user): void
    {
        $attempts = $user->failed_attempts + 1;
        $user->failed_attempts = $attempts;

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $user->locked_until = Carbon::now()->addMinutes(self::LOCK_MINUTES);
            $user->failed_attempts = 0;
        }

        $user->save();
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * تسجيل حدث مصادقة في سجل التدقيق (Append-Only).
     */
    private function audit(string $action, ?User $user, Request $request, array $context = []): void
    {
        AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $user?->id ?? 0,
            'new_values' => $context ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
