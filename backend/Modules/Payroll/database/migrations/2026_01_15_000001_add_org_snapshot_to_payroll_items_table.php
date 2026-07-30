<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لقطة الموقع التنظيمي على payroll_items (Epic 8 / Issue #38).
 *
 * تُجمّد فرع الموظف وقسمه لحظة الحساب حتى تبقى تقارير التكلفة حسب الفرع/القسم
 * **تاريخية**: انتقال الموظف لفرع/قسم آخر لاحقاً لا يغيّر تقارير مسير سابق.
 * أعمدة بلا مفتاح خارجي (بلا nullOnDelete) لحفظ القيمة المجمّدة حتى لو حُذف الكيان.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('employee_id');
            $table->unsignedBigInteger('department_id')->nullable()->after('branch_id');

            $table->index('branch_id', 'payroll_items_branch_idx');
            $table->index('department_id', 'payroll_items_department_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropIndex('payroll_items_branch_idx');
            $table->dropIndex('payroll_items_department_idx');
            $table->dropColumn(['branch_id', 'department_id']);
        });
    }
};
