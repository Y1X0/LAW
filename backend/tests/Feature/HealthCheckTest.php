<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

/**
 * فحص الجاهزية وهويّة الإصدار (M3): /api/health فحص حقيقي (يشمل قاعدة البيانات) يفشل
 * بأمان عند تعذّرها، و/api/version هويّة إصدار خفيفة بلا قاعدة بيانات — بلا تسريب.
 */
class HealthCheckTest extends TestCase
{
    // ── /api/health ─────────────────────────────────────────────────────────

    public function test_health_is_ok_when_database_available(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'checks' => ['database' => 'ok']])
            ->assertJsonStructure(['status', 'app', 'environment', 'version', 'commit', 'checks' => ['database'], 'timestamp']);
    }

    public function test_health_fails_safely_with_503_when_database_unavailable(): void
    {
        // نُحاكي تعذّر قاعدة البيانات برسالة سائق تحمل تفاصيل حسّاسة (يجب ألا تُعرَض).
        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('getPdo')->andThrow(new PDOException(
            'SQLSTATE[08006] could not connect to server: Connection refused host=10.9.8.7 user=lawfirm password=s3cr3t',
        ));

        $res = $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJson(['status' => 'error', 'checks' => ['database' => 'unavailable']]);

        // استجابة آمنة: لا SQL/أثر/مسار/اعتماد/اسم استثناء.
        $body = $res->getContent();
        foreach (['SQLSTATE', 'could not connect', 'host=', 'password', 's3cr3t', '10.9.8.7', 'PDOException', '/var/www', 'vendor'] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }
    }

    public function test_health_response_contains_no_secrets(): void
    {
        $body = $this->getJson('/api/health')->getContent();

        foreach ([config('database.connections.pgsql.password'), config('app.key'), 'APP_KEY', 'DB_PASSWORD'] as $secret) {
            if (is_string($secret) && $secret !== '') {
                $this->assertStringNotContainsString($secret, $body);
            }
        }
    }

    // ── هويّة الإصدار: /api/health + /api/version ────────────────────────────

    public function test_release_identity_exposes_commit_from_config(): void
    {
        config(['app.commit' => 'abc123def456']);

        $this->getJson('/api/version')->assertOk()->assertJsonPath('commit', 'abc123def456');
        $this->getJson('/api/health')->assertOk()->assertJsonPath('commit', 'abc123def456');
    }

    public function test_release_commit_is_null_when_unset_no_fake_fallback(): void
    {
        config(['app.commit' => null]);

        $this->getJson('/api/version')->assertOk()->assertJsonPath('commit', null);
        $this->getJson('/api/health')->assertOk()->assertJsonPath('commit', null);
    }

    // ── /api/version ─────────────────────────────────────────────────────────

    public function test_version_endpoint_exposes_identity_without_database(): void
    {
        // لا يجب أن يلمس /api/version قاعدة البيانات إطلاقاً — نُفشِل أي اتصال ونتوقّع نجاحه.
        DB::shouldReceive('connection')->andThrow(new PDOException('should not be called'));

        $this->getJson('/api/version')
            ->assertOk()
            ->assertJsonStructure(['app', 'version', 'commit', 'environment', 'laravel']);
    }

    // ── /up يبقى كما هو (فحص إقلاع سطحي) ─────────────────────────────────────

    public function test_framework_health_probe_up_still_works(): void
    {
        $this->get('/up')->assertOk();
    }

    // ── ربط Render يشير إلى الفحص الحقيقي ────────────────────────────────────

    public function test_render_health_check_path_points_to_api_health(): void
    {
        $renderYaml = file_get_contents(base_path('../render.yaml'));

        $this->assertIsString($renderYaml);
        $this->assertStringContainsString('healthCheckPath: /api/health', $renderYaml);
    }
}
