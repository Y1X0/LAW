<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * audit_logs — منع التعديل/الحذف على مستوى قاعدة البيانات (B5 · M5).
 *
 * حارس الـ ORM (AuditLog model) يمنع UPDATE/DELETE عبر Eloquent فقط؛ كتابة SQL مباشرة تتجاوزه.
 * هذه الهجرة تضيف طبقة قاعدة بيانات في **PostgreSQL** (trigger يرفض UPDATE/DELETE) فيبقى السجل
 * append-only حتى أمام أي مسار خارج الـ ORM. **محروسة بـ pgsql فقط** فتكون no-op على SQLite
 * (بيئة الاختبار) — حارس الـ ORM يبقى الطبقة المشتركة في كل البيئات. INSERT غير متأثّر (فالنسخ/
 * الاستعادة عبر COPY/INSERT تعمل بلا مشكلة، والـ trigger يُستعاد ضمن الـ dump).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_prevent_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only (operation % is not allowed)', TG_OP;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_audit_logs_append_only ON audit_logs;
            CREATE TRIGGER trg_audit_logs_append_only
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_prevent_mutation();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(
            'DROP TRIGGER IF EXISTS trg_audit_logs_append_only ON audit_logs;'.
            'DROP FUNCTION IF EXISTS audit_logs_prevent_mutation();'
        );
    }
};
