<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * expense_categories — تصنيفات المصروفات (Finance / Phase 6 · PR-1).
 *
 * بيانات مرجعية تُصنَّف بها سندات الصرف (رسوم محكمة، إيجار، رواتب، قرطاسية...).
 * تُعطَّل عبر is_active بلا حذف فعلي حفاظاً على ربط المصروفات السابقة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('is_active')->default(true)->comment('تعطيل بلا حذف فعلي');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
