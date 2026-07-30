<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * salary_components — كتالوج مكوّنات الراتب (Epic 8 / Issue #33).
 * النوع: بدل (allowance) أو خصم (deduction). طريقة القيمة: مبلغ ثابت أو نسبة من الأساسي.
 * القيمة الفعلية تُسنَد لكل موظف في employee_salary_components. لا حساب هنا (#36).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 40)->unique();
            $table->string('type', 12)->comment('allowance/deduction');
            $table->string('value_type', 12)->default('fixed')->comment('fixed/percentage');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
