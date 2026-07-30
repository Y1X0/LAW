<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payroll_periods — فترات الرواتب الشهرية (Epic 8 / Issue #32).
 * دورة الحالة: draft → processing → approved → paid. سجل واحد لكل (سنة، شهر، فرع).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->comment('1..12');
            $table->string('status', 12)->default('draft')->comment('draft/processing/approved/paid');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['year', 'month', 'branch_id'], 'payroll_periods_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
