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
     * تسجيل الدخول: يتحقق من البيانات والحالة والقفل، ثم يصدر زوج توكنات.
     *
     * @return array{user: User, tokens: array{access_token: string, refresh_token: string, access_expires_at: string, refresh_expires_at: string}}
     */
    public function login(string $email, string $password, Request $request): array
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->audit('login_failed', null, $request, ['email' => $email]);
            throw AuthException::invalidCredentials();
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            $this->audit('login_locked', $user, $request);
            throw AuthException::accountLocked();
        }

        if ($user->status !== 'active') {
            $this->audit('login_inactive', $user, $request);
            throw AuthException::accountInactive();
        }

        if (! Hash::check($password, $user->password)) {
            $this->registerFailedAttempt($user);
            $this->audit('login_failed', $user, $request, ['email' => $email]);
            throw AuthException::invalidCredentials();
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
