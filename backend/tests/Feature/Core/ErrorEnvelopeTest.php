<?php

namespace Tests\Feature\Core;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * توحيد الاستجابة للأخطاء غير التحقّق على مخطّط {data,meta,errors} (تصلّب ما قبل النشر).
 * قبل الإصلاح كانت 404/403/abort ترجع {message} فقط — يكسر قراءة الواجهة لـ errors.code.
 */
class ErrorEnvelopeTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_model_not_found_returns_unified_envelope(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);

        // ربط النموذج المفقود يُحوَّل إلى 404؛ المهم أنه يعود بالغلاف الموحّد لا بـ {message}.
        $this->actingAsToken($admin)->getJson('/api/users/999999')
            ->assertStatus(404)
            ->assertJsonPath('errors.code', 'HTTP_404')
            ->assertJsonPath('data', null);
    }

    public function test_abort_business_error_keeps_envelope_and_status(): void
    {
        // إعادة تعيين كلمة مرور بدون تأكيد → 422 عبر التحقّق (يبقى VALIDATION_ERROR).
        $admin = $this->userWithPermissions(['users.manage']);
        $target = User::factory()->create();

        $this->actingAsToken($admin)->postJson("/api/users/{$target->id}/reset-password", [
            'password' => 'NewPass123', 'password_confirmation' => 'mismatch',
        ])->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_unknown_api_route_returns_json_envelope_not_html(): void
    {
        $this->getJson('/api/no-such-endpoint')
            ->assertStatus(404)
            ->assertJsonPath('errors.code', 'HTTP_404');
    }
}
