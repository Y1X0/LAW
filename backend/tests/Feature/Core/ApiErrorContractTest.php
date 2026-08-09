<?php

namespace Tests\Feature\Core;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Core\Exceptions\AuthException;
use Modules\Finance\Exceptions\ImmutableJournalEntryException;
use Modules\Finance\Exceptions\InvalidJournalEntryException;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\LegalCase;
use PDOException;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * عقد الأخطاء الموحّد (PR-1): لا فشل صامت ولا خطأ عامّ. كل فشل يعود بسبب عربي واضح،
 * على مستوى الحقل عند اللزوم، برمز حالة ورمز خطأ مناسبين، ودون تسريب SQL/أثر/تفاصيل داخلية.
 *
 * يستخدم مسارات اختبار مؤقّتة تحت /api لتفجير استثناءات مُفبركة والتحقّق من مرورها عبر
 * معالِج الأخطاء المركزي (bootstrap/app.php) إلى الغلاف {data,meta,errors}.
 */
class ApiErrorContractTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** يبني QueryException مُفبركاً بـ SQLSTATE ورسالة سائق محدّدين. */
    private function queryException(string $sqlState, string $driverMessage, string $class = QueryException::class): QueryException
    {
        $pdo = new PDOException($driverMessage);
        $pdo->errorInfo = [$sqlState, 1, $driverMessage];

        return new $class('pgsql', 'insert into "cases" ("internal_number") values (?)', ['X'], $pdo);
    }

    // ── التحقّق بالعربية على مستوى الحقل ─────────────────────────────────────

    public function test_validation_errors_are_arabic_and_field_scoped(): void
    {
        $admin = $this->userWithPermissions(['cases.create']);

        $res = $this->actingAsToken($admin)->postJson('/api/cases', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['code', 'message', 'fields' => ['internal_number', 'title', 'client_id']]]);

        // اسم الحقل عربي (من attributes) والرسالة عربية واضحة على مستوى الحقل.
        $this->assertStringContainsString('مطلوب', $res->json('errors.fields.internal_number.0'));
        $this->assertStringContainsString('الرقم الداخلي', $res->json('errors.fields.internal_number.0'));
        $this->assertStringContainsString('العنوان', $res->json('errors.fields.title.0'));
    }

    public function test_invalid_email_shows_arabic_field_message(): void
    {
        // مسار المصادقة يتحقّق من صيغة البريد؛ رسالة الحقل يجب أن تكون عربية.
        $res = $this->postJson('/api/auth/login', ['email' => 'not-an-email', 'password' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');

        $this->assertStringContainsString('البريد الإلكتروني', $res->json('errors.fields.email.0'));
    }

    // ── أخطاء قاعدة البيانات: تفرّد/مفتاح أجنبي/حقل مطلوب ────────────────────

    public function test_unique_violation_maps_to_409_without_leaking_sql(): void
    {
        Route::get('/api/__test/unique', fn () => throw $this->queryException(
            '23505',
            'duplicate key value violates unique constraint "cases_internal_number_unique"',
            UniqueConstraintViolationException::class,
        ));

        $res = $this->getJson('/api/__test/unique')
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'CONFLICT_DUPLICATE');

        $body = $res->getContent();
        $this->assertStringContainsString('مستخدمة مسبقاً', $res->json('errors.message'));
        $this->assertStringNotContainsString('cases_internal_number_unique', $body);
        $this->assertStringNotContainsString('insert into', $body);
        $this->assertStringNotContainsString('23505', $body);
    }

    public function test_foreign_key_violation_maps_to_409(): void
    {
        Route::get('/api/__test/fk', fn () => throw $this->queryException(
            '23503',
            'update or delete on table "clients" violates foreign key constraint "cases_client_id_foreign"',
        ));

        $this->getJson('/api/__test/fk')
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'CONFLICT_REFERENCE')
            ->assertJsonPath('data', null);
    }

    public function test_not_null_violation_maps_to_422(): void
    {
        Route::get('/api/__test/notnull', fn () => throw $this->queryException(
            '23502',
            'null value in column "title" of relation "cases" violates not-null constraint',
        ));

        $this->getJson('/api/__test/notnull')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    // ── استثناءات المجال (المالية/المصادقة) ─────────────────────────────────

    public function test_invalid_journal_entry_maps_to_422_with_domain_message(): void
    {
        Route::get('/api/__test/journal-invalid', fn () => throw new InvalidJournalEntryException(
            'القيد غير متوازن: مجموع المدين لا يساوي مجموع الدائن.',
        ));

        $this->getJson('/api/__test/journal-invalid')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INVALID_JOURNAL_ENTRY')
            ->assertJsonPath('errors.message', 'القيد غير متوازن: مجموع المدين لا يساوي مجموع الدائن.');
    }

    public function test_immutable_journal_entry_maps_to_409(): void
    {
        Route::get('/api/__test/journal-immutable', fn () => throw new ImmutableJournalEntryException(
            'القيد مُرحَّل ولا يمكن تعديله.',
        ));

        $this->getJson('/api/__test/journal-immutable')
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'IMMUTABLE_JOURNAL_ENTRY');
    }

    public function test_auth_exception_renders_its_status_and_code(): void
    {
        Route::get('/api/__test/auth', fn () => throw AuthException::accountLocked());

        $this->getJson('/api/__test/auth')
            ->assertStatus(423)
            ->assertJsonPath('errors.code', 'ACCOUNT_LOCKED')
            ->assertJsonPath('errors.message', 'الحساب مقفل مؤقتاً بسبب محاولات دخول فاشلة متكررة.');
    }

    // ── توحيد 403 (Gate::authorize) على رسالة عربية ─────────────────────────

    public function test_authorization_exception_unifies_to_arabic_403(): void
    {
        Route::get('/api/__test/authz', fn () => throw new AuthorizationException);

        $this->getJson('/api/__test/authz')
            ->assertStatus(403)
            ->assertJsonPath('errors.message', 'لا تملك صلاحية تنفيذ هذا الإجراء.');
    }

    public function test_authorization_exception_keeps_custom_arabic_message(): void
    {
        Route::get('/api/__test/authz-custom', fn () => throw new AuthorizationException('لا يمكن حذف آخر مدير للنظام.'));

        $this->getJson('/api/__test/authz-custom')
            ->assertStatus(403)
            ->assertJsonPath('errors.message', 'لا يمكن حذف آخر مدير للنظام.');
    }

    // ── 404: لا تسريب لاسم النموذج ───────────────────────────────────────────

    public function test_model_not_found_does_not_leak_model_class(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);

        $res = $this->actingAsToken($admin)->getJson('/api/users/999999')
            ->assertStatus(404)
            ->assertJsonPath('errors.code', 'HTTP_404');

        $body = $res->getContent();
        $this->assertStringNotContainsString('No query results', $body);
        $this->assertStringNotContainsString('App\\Models', $body);
        $this->assertStringContainsString('غير موجود', $res->json('errors.message'));
    }

    // ── 500: لا تسريب SQL/أثر في الإنتاج (debug=false) ──────────────────────

    public function test_server_error_hides_sql_and_stack_in_production(): void
    {
        config(['app.debug' => false]);

        Route::get('/api/__test/server', fn () => throw $this->queryException(
            '42601',
            'syntax error at or near "SELCT" in query: SELECT * FROM secret_users',
        ));

        $res = $this->getJson('/api/__test/server')
            ->assertStatus(500)
            ->assertJsonPath('errors.code', 'SERVER_ERROR')
            ->assertJsonPath('errors.message', 'حدث خطأ غير متوقّع.');

        $body = $res->getContent();
        $this->assertStringNotContainsString('secret_users', $body);
        $this->assertStringNotContainsString('syntax error', $body);
        $this->assertStringNotContainsString('SELECT', $body);
        $this->assertArrayNotHasKey('debug', $res->json('errors'));
    }

    public function test_unknown_route_returns_arabic_json_envelope(): void
    {
        $res = $this->getJson('/api/no-such-endpoint-xyz')
            ->assertStatus(404)
            ->assertJsonPath('errors.code', 'HTTP_404');

        // غلاف JSON لا HTML، ورسالة عربية آمنة.
        $this->assertIsString($res->json('errors.message'));
        $this->assertStringNotContainsString('<!DOCTYPE', $res->getContent());
    }

    // ── منع القضية/المهمّة: رسالة عربية لا رفض صامت ─────────────────────────

    public function test_case_forbidden_now_includes_arabic_message(): void
    {
        $orphan = $this->userWithPermissions(['cases.view_own']); // بلا موظف مرتبط
        $case = LegalCase::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->actingAsToken($orphan)->getJson("/api/cases/{$case->id}")
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE')
            ->assertJsonPath('errors.message', 'هذا الحساب غير مرتبط بسجلّ موظف.');
    }
}
