<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Modules\Core\Models\AuthToken;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_ok(): void
    {
        User::factory()->create(['email' => 'user@firm.test']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'user@firm.test'])->assertOk();
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'user@firm.test']);
    }

    public function test_reset_password_updates_password_and_revokes_sessions(): void
    {
        $user = User::factory()->create(['email' => 'user@firm.test', 'password' => 'old-password']);
        // جلسة نشطة قائمة يجب أن تُبطَل عند إعادة التعيين
        AuthToken::create([
            'user_id' => $user->id,
            'access_token_hash' => hash('sha256', 'a'),
            'refresh_token_hash' => hash('sha256', 'r'),
            'access_expires_at' => now()->addMinutes(15),
            'refresh_expires_at' => now()->addDays(14),
        ]);

        $token = Password::broker()->createToken($user);

        $res = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'user@firm.test',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $res->assertOk();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->password_changed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_reset']);
        // كل التوكنات النشطة أُبطلت
        $this->assertSame(0, AuthToken::whereNull('revoked_at')->count());
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'user@firm.test']);

        $this->postJson('/api/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => 'user@firm.test',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(422)->assertJsonPath('errors.code', 'RESET_FAILED');
    }

    public function test_new_password_can_be_used_to_login(): void
    {
        $user = User::factory()->create(['email' => 'user@firm.test', 'password' => 'old-password']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'user@firm.test',
            'password' => 'brand-new-pass-9',
            'password_confirmation' => 'brand-new-pass-9',
        ])->assertOk();

        $this->postJson('/api/auth/login', ['email' => 'user@firm.test', 'password' => 'brand-new-pass-9'])->assertOk();
    }
}
