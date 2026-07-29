<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * attendance_records — سجل الحضور اليومي المعالَج (docs/02 §4) — Attendance Core (Issue #15).
 * سجل واحد لكل موظف في اليوم. لا علاقة بأجهزة البصمة (source=manual هنا؛ biometric في #16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('work_date');
            $table->timestampTz('check_in')->nullable();
            $table->timestampTz('check_out')->nullable();
            $table->integer('worked_minutes')->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->string('status', 20)->default('present')->comment('present/absent/late/early_leave/leave/holiday/weekend');
            $table->string('source', 20)->default('manual')->comment('manual/biometric');
            $table->foreignId('shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['employee_id', 'work_date']);
            $table->index('work_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
