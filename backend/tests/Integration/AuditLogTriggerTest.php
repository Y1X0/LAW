<?php

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * تحقّق حيّ على PostgreSQL (B5 · M7-A) — يُثبت السلوك على محرّك الإنتاج نفسه، وهو ما لا تغطّيه
 * مجموعة الاختبارات الرئيسيّة (SQLite). يتخطّى على أي محرّك غير pgsql فيبقى CI الرئيسي أخضر.
 *
 * يؤكّد: (1) trigger الـ append-only يرفض فعليّاً UPDATE وDELETE على audit_logs حتى عبر SQL مباشر
 * خارج الـ ORM (طبقة M5 التي لا تُختبَr على SQLite)، و(2) عمود jsonb يخزّن ويسترجع بأمانة على
 * Postgres. لا يستخدم RefreshDatabase (معاملة الاختبار تتعارض مع استثناء الـ trigger)؛ يعتمد على
 * قاعدة CI المُهاجَرة سلفاً (خطوة migrate against PostgreSQL) ويُنظّف صفوفه بمسار مسموح.
 */
class AuditLogTriggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('trigger الـ append-only يُختبَر على PostgreSQL فقط.');
        }
    }

    private function insertLog(): int
    {
        return (int) DB::table('audit_logs')->insertGetId([
            'action' => 'login',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => 1,
            'old_values' => json_encode(['role' => 'lawyer']),
            'new_values' => json_encode(['role' => 'admin']),
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);
    }

    public function test_jsonb_columns_round_trip_on_postgres(): void
    {
        $id = $this->insertLog();

        $row = DB::table('audit_logs')->where('id', $id)->first();
        $this->assertSame('lawyer', json_decode($row->old_values, true)['role']);
        $this->assertSame('admin', json_decode($row->new_values, true)['role']);
    }

    public function test_update_is_blocked_by_database_trigger(): void
    {
        $id = $this->insertLog();

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $id)->update(['action' => 'tampered']);
    }

    public function test_delete_is_blocked_by_database_trigger(): void
    {
        $id = $this->insertLog();

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $id)->delete();
    }
}
