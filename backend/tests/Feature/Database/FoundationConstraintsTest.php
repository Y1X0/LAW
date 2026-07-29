<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Models\Role;
use RuntimeException;
use Tests\TestCase;

/**
 * يتحقق أن القيود تعمل والبيانات غير الصحيحة تُرفض فعلياً (Issue #10)،
 * وليس مجرد وجود الجداول/العلاقات.
 */
class FoundationConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_branch_code_is_rejected(): void
    {
        Branch::create(['name' => 'الرئيسي', 'code' => 'HQ']);

        $this->expectException(QueryException::class);
        Branch::create(['name' => 'آخر', 'code' => 'HQ']); // انتهاك قيد unique
    }

    public function test_duplicate_role_name_is_rejected(): void
    {
        Role::create(['name' => 'lawyer', 'display_name' => 'محامٍ']);

        $this->expectException(QueryException::class);
        Role::create(['name' => 'lawyer', 'display_name' => 'مكرر']);
    }

    public function test_department_requires_existing_branch_fk(): void
    {
        $this->expectException(QueryException::class);
        Department::create(['branch_id' => 999999, 'name' => 'قسم يتيم']); // FK غير موجود
    }

    public function test_branch_deletion_is_restricted_when_it_has_departments(): void
    {
        $branch = Branch::create(['name' => 'فرع', 'code' => 'BR1']);
        Department::create(['branch_id' => $branch->id, 'name' => 'قسم']);

        $this->expectException(QueryException::class);
        $branch->delete(); // restrictOnDelete يمنع الحذف
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $log = AuditLog::create([
            'action' => 'create', 'auditable_type' => 'Case', 'auditable_id' => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $log = AuditLog::create([
            'action' => 'create', 'auditable_type' => 'Case', 'auditable_id' => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $log->delete();
    }

    public function test_mfa_secret_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create(['mfa_secret' => 'TOTPSECRET123']);

        // القيمة المخزّنة خام في قاعدة البيانات يجب ألا تساوي النص الصريح
        $rawStored = \DB::table('users')->where('id', $user->id)->value('mfa_secret');
        $this->assertNotSame('TOTPSECRET123', $rawStored);
        // لكن القراءة عبر النموذج تفكّ التشفير
        $this->assertSame('TOTPSECRET123', $user->fresh()->mfa_secret);
    }
}
