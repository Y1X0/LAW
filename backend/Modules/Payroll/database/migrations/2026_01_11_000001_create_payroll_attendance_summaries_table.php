<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payroll_attendance_summaries — لقطة (Snapshot) ملخّص الحضور الشهري داخل Payroll (Issue #34).
 *
 * تُحفَظ الأرقام المشتقّة من attendance_records وقت المزامنة، فلا تتغيّر الرواتب التاريخية
 * إن تغيّر الحضور لاحقاً. Payroll يقرأ الحضور فقط ولا يكتب فيه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('total_work_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['payroll_run_id', 'employee_id'], 'payroll_attendance_summaries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_attendance_summaries');
    }
};
