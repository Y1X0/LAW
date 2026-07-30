<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * employee_salary_components — إسناد مكوّن راتب لموظف بقيمة وفترة فعالية (Issue #33).
 * تاريخي: إعادة إسناد نفس المكوّن تؤرشف السابق (is_active=false) — لا حذف، لسلامة الـ snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->restrictOnDelete();
            $table->decimal('value', 15, 2)->comment('مبلغ ثابت أو نسبة مئوية حسب value_type للمكوّن');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['employee_id', 'is_active']);
            $table->index(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
