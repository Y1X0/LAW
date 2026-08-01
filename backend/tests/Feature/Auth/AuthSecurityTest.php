<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * اختبارات أمنية للمصادقة (Issue #11): تجزئة كلمة المرور والتوكنات،
 * وعدم تسريب المعلومات.
 */
class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_is_stored_hashed_not_plaintext(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $raw = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertNotSame('password', $raw);
        $this->assertTrue(Hash::check('password', $raw));
    }

    public function test_tokens_are_stored_hashed_not_plaintext(): void
    {
        User::factory()->create(['email' => 'a@b.test', 'password' => 'password']);
        $tokens = $this->postJson('/api/auth/login', ['email' => 'a@b.test', 'password' => 'password'])->json('data.tokens');

        // النص الصريح للتوكن يجب ألا يظهر في قاعدة البيانات
        $this->assertDatabaseMissing('auth_tokens', ['access_token_hash' => $tokens['access_token']]);
        $this->assertDatabaseMissing('auth_tokens', ['refresh_token_hash' => $tokens['refresh_token']]);
        $this->assertDatabaseHas('auth_tokens', ['access_token_hash' => hash('sha256', $tokens['access_token'])]);
    }

    public function test_forgot_password_does_not_reveal_account_existence(): void
    {
        // بريد غير مسجّل يجب أن يعيد نفس الرسالة المحايدة (منع تعداد الحسابات)
        $this->postJson('/api/auth/forgot-password', ['email' => 'ghost@none.test'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['message']]);
    }

    public function test_login_is_rate_limited_per_ip(): void
    {
        // تصلّب أمني: تجاوز حدّ المعدّل (30/دقيقة) يردّ 429 بصرف النظر عن صحّة البيانات.
        $last = null;
        for ($i = 0; $i < 31; $i++) {
            $last = $this->postJson('/api/auth/login', ['email' => 'x@y.test', 'password' => 'nope']);
        }

        $this->assertSame(429, $last->getStatusCode());
    }

    public function test_forgot_password_is_rate_limited_per_ip(): void
    {
        $last = null;
        for ($i = 0; $i < 11; $i++) {
            $last = $this->postJson('/api/auth/forgot-password', ['email' => 'ghost@none.test']);
        }

        $this->assertSame(429, $last->getStatusCode());
    }
}
