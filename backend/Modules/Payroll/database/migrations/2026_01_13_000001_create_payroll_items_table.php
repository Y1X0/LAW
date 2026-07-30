<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payroll_items — نتيجة حساب الراتب النهائية لموظف ضمن مسير (Epic 8 / Issue #36).
 *
 * لقطة نهائية غير قابلة للتغيير بعد اعتماد المسير: تحفظ الأساسي والبدلات والخصومات
 * والإجمالي والصافي + تفصيل البنود (breakdown) للتدقيق والكشف (#37).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->char('currency', 3)->default('SAR');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('allowances_total', 15, 2)->default(0);
            $table->decimal('deductions_total', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->jsonb('breakdown')->nullable()->comment('تفصيل البنود المحسوبة (بدلات/خصومات/حضور/إجازات)');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['payroll_run_id', 'employee_id'], 'payroll_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
