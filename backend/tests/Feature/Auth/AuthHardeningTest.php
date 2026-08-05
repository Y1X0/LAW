<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Modules\Core\Models\AuthToken;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * تقوية المصادقة (B5 · PR-3): فحص الحالة عند التدوير (M8)، حدّ المعدّل بالبريد (M9)،
 * سياسة كلمة المرور القويّة المركزيّة (M10)، ونافذة رمز الاستعادة الأقصر (L6).
 */
class AuthHardeningTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function loginRefreshToken(string $email): string
    {
        User::factory()->create(['email' => $email, 'password' => 'password', 'status' => 'active']);

        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password'])
            ->assertOk()->json('data.tokens.refresh_token');
    }

    // ---------- M8: refresh يفحص حالة الحساب ----------

    public function test_refresh_denied_when_account_suspended_and_token_revoked(): void
    {
        $refresh = $this->loginRefreshToken('susp@firm.test');
        User::where('email', 'susp@firm.test')->first()->forceFill(['status' => 'suspended'])->save();

        $this->postJson('/api/auth/refresh', ['refresh_token' => $refresh])
            ->assertStatus(401)->assertJsonPath('errors.code', 'INVALID_TOKEN');

        // التوكن المقدَّم أُبطِل — لا تبقى أي جلسة حيّة للحساب الموقوف.
        $user = User::where('email', 'susp@firm.test')->first();
        $this->assertSame(0, AuthToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
    }

    public function test_refresh_denied_when_account_locked(): void
    {
        $refresh = $this->loginRefreshToken('lock@firm.test');
        User::where('email', 'lock@firm.test')->first()
            ->forceFill(['locked_until' => now()->addMinutes(15)])->save();

        $this->postJson('/api/auth/refresh', ['refresh_token' => $refresh])
            ->assertStatus(401)->assertJsonPath('errors.code', 'INVALID_TOKEN');
    }

    public function test_refresh_succeeds_for_active_account(): void
    {
        $refresh = $this->loginRefreshToken('ok@firm.test');

        $this->postJson('/api/auth/refresh', ['refresh_token' => $refresh])
            ->assertOk()->assertJsonPath('data.tokens.access_token', fn ($t) => is_string($t) && $t !== '');
    }

    // ---------- M9: حدّ المعدّل بالبريد ----------

    public function test_login_is_rate_limited_per_email(): void
    {
        // 11 محاولة بنفس البريد (غير موجود) — حدّ البريد 10/دقيقة يردّ 429 عند الـ11،
        // قبل حدّ الـ IP (20)، فيثبت أن المفتاح بالبريد فعّال ضدّ التخمين الموزّع.
        $last = null;
        for ($i = 0; $i < 11; $i++) {
            $last = $this->postJson('/api/auth/login', ['email' => 'brute@none.test', 'password' => 'x']);
        }

        $this->assertSame(429, $last->getStatusCode());
    }

    // ---------- M10: سياسة كلمة المرور القويّة ----------

    public function test_reset_password_rejects_weak_password(): void
    {
        $user = User::factory()->create(['email' => 'weakreset@firm.test']);
        $token = Password::broker()->createToken($user);

        // 'short' يخالف min(12) + التعقيد → 422.
        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'weakreset@firm.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422);
    }

    public function test_admin_create_user_rejects_weak_password(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);

        $this->actingAsToken($admin)->postJson('/api/users', [
            'name' => 'ضعيف', 'email' => 'weakcreate@firm.test', 'password' => 'weakpass',
        ])->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_strong_password_is_accepted_on_reset(): void
    {
        $user = User::factory()->create(['email' => 'strongreset@firm.test']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'strongreset@firm.test',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ])->assertOk();
    }

    // ---------- L6: نافذة رمز الاستعادة ----------

    public function test_reset_token_expiry_is_shortened(): void
    {
        $this->assertSame(15, config('auth.passwords.users.expire'));
    }
}
