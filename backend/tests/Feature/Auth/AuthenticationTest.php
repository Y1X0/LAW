<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\AuthToken;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'lawyer@firm.test',
            'password' => 'password', // يُجزّأ عبر cast 'hashed'
        ], $overrides));
    }

    public function test_login_succeeds_and_returns_token_pair(): void
    {
        $this->user();

        $res = $this->postJson('/api/auth/login', [
            'email' => 'lawyer@firm.test',
            'password' => 'password',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['data' => ['user' => ['id', 'email'], 'tokens' => ['access_token', 'refresh_token', 'access_expires_at', 'refresh_expires_at']]]);
        $this->assertDatabaseCount('auth_tokens', 1);
    }

    public function test_login_writes_audit_event(): void
    {
        $this->user();
        $this->postJson('/api/auth/login', ['email' => 'lawyer@firm.test', 'password' => 'password'])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'login']);
    }

    public function test_login_fails_with_wrong_password_and_increments_attempts(): void
    {
        $user = $this->user();

        $this->postJson('/api/auth/login', ['email' => 'lawyer@firm.test', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('errors.code', 'INVALID_CREDENTIALS');

        $this->assertSame(1, $user->fresh()->failed_attempts);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_failed']);
    }

    public function test_account_locks_after_max_failed_attempts(): void
    {
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'lawyer@firm.test', 'password' => 'wrong']);
        }

        // بعد بلوغ الحد، المحاولة التالية تُرفض بحالة قفل
        $this->postJson('/api/auth/login', ['email' => 'lawyer@firm.test', 'password' => 'password'])
            ->assertStatus(423)
            ->assertJsonPath('errors.code', 'ACCOUNT_LOCKED');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->user(['status' => 'suspended']);

        $this->postJson('/api/auth/login', ['email' => 'lawyer@firm.test', 'password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'ACCOUNT_INACTIVE');
    }

    /**
     * SEC-4: بكلمة مرور خاطئة، حساب مُعطَّل لا يكشف حالته — يعيد INVALID_CREDENTIALS
     * الموحّدة مثل أي فشل، فلا يستطيع مهاجم تعداد الحسابات المُعطَّلة.
     */
    public function test_inactive_user_with_wrong_password_does_not_reveal_status(): void
    {
        $this->user(['status' => 'suspended']);

        $this->postJson('/api/auth/login', ['email' => 'lawyer@firm.test', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('errors.code', 'INVALID_CREDENTIALS');
    }

    /** SEC-4: حساب مقفول بكلمة مرور خاطئة يعيد INVALID_CREDENTIALS الموحّدة (لا يكشف القفل). */
    public function test_locked_user_with_wrong_password_does_not_reveal_status(): void
    {
        $this->user(['locked_until' => now()->addMinutes(15)]);

        $this->postJson('/api/auth/login', ['email' => 'lawyer@firm.test', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('errors.code', 'INVALID_CREDENTIALS');
    }

    /** SEC-4: بريد غير موجود وبريد موجود بكلمة خاطئة يعيدان نفس الرمز (لا تمييز). */
    public function test_unknown_email_returns_same_code_as_wrong_password(): void
    {
        $this->user();

        $unknown = $this->postJson('/api/auth/login', ['email' => 'ghost@firm.test', 'password' => 'whatever'])
            ->assertStatus(401)->json('errors.code');
        $wrong = $this->postJson('/api/auth/login', ['email' => 'lawyer@firm.test', 'password' => 'wrong'])
            ->assertStatus(401)->json('errors.code');

        $this->assertSame($wrong, $unknown);
        $this->assertSame('INVALID_CREDENTIALS', $unknown);
    }

    public function test_authenticated_user_can_access_me(): void
    {
        $this->user();
        $token = $this->postJson('/api/auth/login', ['email' => 'lawyer@firm.test', 'password' => 'password'])
            ->json('data.tokens.access_token');

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'lawyer@firm.test');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('errors.code', 'UNAUTHENTICATED');
    }

    public function test_logout_revokes_the_token(): void
    {
        $this->user();
        $token = $this->postJson('/api/auth/login', ['email' => 'lawyer@firm.test', 'password' => 'password'])
            ->json('data.tokens.access_token');

        $this->withHeaders(['Authorization' => "Bearer {$token}"])->postJson('/api/auth/logout')->assertOk();

        // التوكن أصبح غير صالح بعد الخروج
        $this->withHeaders(['Authorization' => "Bearer {$token}"])->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_refresh_rotates_tokens_and_invalidates_old_refresh(): void
    {
        $this->user();
        $login = $this->postJson('/api/auth/login', ['email' => 'lawyer@firm.test', 'password' => 'password'])->json('data.tokens');

        $new = $this->postJson('/api/auth/refresh', ['refresh_token' => $login['refresh_token']])
            ->assertOk()
            ->json('data.tokens');

        $this->assertNotSame($login['access_token'], $new['access_token']);

        // إعادة استخدام refresh القديم مرفوضة (تدوير)
        $this->postJson('/api/auth/refresh', ['refresh_token' => $login['refresh_token']])
            ->assertStatus(401)
            ->assertJsonPath('errors.code', 'INVALID_TOKEN');
    }

    public function test_login_requires_valid_input_with_unified_error_schema(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['data', 'meta', 'errors' => ['code', 'message', 'fields']]);
    }

    public function test_expired_access_token_is_rejected(): void
    {
        $user = $this->user();
        $plain = Str::random(64);
        AuthToken::create([
            'user_id' => $user->id,
            'access_token_hash' => hash('sha256', $plain),
            'refresh_token_hash' => hash('sha256', Str::random(64)),
            'access_expires_at' => now()->subMinute(),   // منتهٍ
            'refresh_expires_at' => now()->addDays(14),
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('errors.code', 'UNAUTHENTICATED');
    }

    public function test_expired_refresh_token_is_rejected(): void
    {
        $user = $this->user();
        $plain = Str::random(64);
        AuthToken::create([
            'user_id' => $user->id,
            'access_token_hash' => hash('sha256', Str::random(64)),
            'refresh_token_hash' => hash('sha256', $plain),
            'access_expires_at' => now()->addMinutes(15),
            'refresh_expires_at' => now()->subDay(),      // منتهٍ
        ]);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])
            ->assertStatus(401)
            ->assertJsonPath('errors.code', 'INVALID_TOKEN');
    }
}
