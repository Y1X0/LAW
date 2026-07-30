<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payroll_leave_summaries — لقطة (Snapshot) ملخّص الإجازات الشهري داخل Payroll (Issue #35).
 *
 * تُحفَظ أيام الإجازة المدفوعة/بدون راتب المشتقّة من الإجازات المعتمدة وقت المزامنة،
 * فلا تتغيّر الرواتب التاريخية إن تغيّرت الإجازات لاحقاً. Payroll يقرأ الإجازات فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_leave_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('paid_leave_days', 6, 1)->default(0);
            $table->decimal('unpaid_leave_days', 6, 1)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['payroll_run_id', 'employee_id'], 'payroll_leave_summaries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_leave_summaries');
    }
};
