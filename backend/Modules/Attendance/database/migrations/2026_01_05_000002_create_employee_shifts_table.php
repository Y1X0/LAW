<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * employee_shifts — إسناد الموظف لنوبة عمل (تاريخي) — Attendance Core (Issue #15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('work_shifts')->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->timestampsTz();

            $table->index(['employee_id', 'from_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shifts');
    }
};
