<?php

namespace Tests\Feature\Core;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AuditLog;
use Tests\TestCase;

/**
 * سجل التدقيق append-only (B5 · M5).
 *
 * تُثبِت هذه الاختبارات طبقة حارس الـ ORM (المشتركة في كل البيئات). الطبقة الأعمق — trigger في
 * PostgreSQL يرفض UPDATE/DELETE حتى خارج الـ ORM — تُطبَّق عبر هجرة محروسة بـ pgsql (no-op على
 * SQLite) فلا تُختبَر في مجموعة الاختبار الحاليّة (SQLite)؛ تُثبَت عند إضافة مسار Postgres CI.
 */
class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(): AuditLog
    {
        return AuditLog::create([
            'user_id' => User::factory()->create()->id,
            'action' => 'login',
            'auditable_type' => User::class,
            'auditable_id' => 1,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_audit_log_cannot_be_updated_via_orm(): void
    {
        $log = $this->makeLog();

        $this->expectException(\RuntimeException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_log_cannot_be_deleted_via_orm(): void
    {
        $log = $this->makeLog();

        $this->expectException(\RuntimeException::class);
        $log->delete();
    }
}
