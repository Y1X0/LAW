<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * يثبت أن التطبيق يُقلع وأن نواة الوحدات (Core) محمّلة عبر ModuleServiceProvider،
 * وأن مسار الـ API الخاص بالوحدة يعمل تحت البادئة /api.
 */
class HealthCheckTest extends TestCase
{
    public function test_module_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'database' => 'ok',
            ])
            ->assertJsonStructure(['status', 'app', 'environment', 'database', 'timestamp']);
    }

    public function test_core_api_version_endpoint_works(): void
    {
        $this->getJson('/api/version')
            ->assertOk()
            ->assertJsonStructure(['app', 'version', 'laravel']);
    }

    public function test_framework_health_probe_is_up(): void
    {
        $this->get('/up')->assertOk();
    }
}
