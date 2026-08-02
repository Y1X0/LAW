<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * يتحقق من أن ترحيلات النواة التأسيسية (Issue #10) تُنشئ الجداول والأعمدة المتوقعة
 * وفق docs/02-database-design.md — ويثبت تحميلها عبر ModuleServiceProvider.
 */
class FoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_tables_exist(): void
    {
        foreach ([
            'branches', 'departments', 'roles', 'permissions',
            'role_permission', 'user_role', 'settings',
            'audit_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "الجدول {$table} غير موجود");
        }
    }

    public function test_users_table_has_foundation_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'username', 'mfa_secret', 'mfa_enabled', 'status',
            'last_login_at', 'failed_attempts', 'locked_until',
            'password_changed_at', 'deleted_at',
        ]));
    }

    public function test_key_columns_present_on_core_tables(): void
    {
        $this->assertTrue(Schema::hasColumns('branches', ['name', 'code', 'is_active']));
        $this->assertTrue(Schema::hasColumns('departments', ['branch_id', 'parent_id', 'manager_id']));
        $this->assertTrue(Schema::hasColumns('settings', ['branch_id', 'group', 'key', 'value']));
        $this->assertTrue(Schema::hasColumns('audit_logs', ['action', 'auditable_type', 'auditable_id', 'old_values', 'new_values']));
    }
}
