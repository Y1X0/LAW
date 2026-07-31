<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * case_assignments — إسناد المحامين للقضايا (Legal / LC-2).
 * lead = المحامي الرئيسي، support = مساعد. أساس عزل الرؤية (view_own).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('role', 20)->default('support')->comment('lead/support');
            $table->timestampsTz();

            $table->unique(['case_id', 'employee_id']);
            $table->index(['employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_assignments');
    }
};
