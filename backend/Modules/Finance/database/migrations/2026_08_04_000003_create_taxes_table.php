<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * taxes — إعدادات الضرائب (Finance / Phase 6 · PR-1).
 *
 * كل صف نسبة ضريبية مُسمّاة (VAT = 15٪ في السعودية). النسبة الفعّالة الافتراضية
 * = الصف النشط (is_active). تُستخدم لاحقاً كقيمة افتراضية لـ tax_rate في بنود
 * الفواتير، مع إمكانية 0٪ للبنود المعفاة. لا حذف فعلي — تعطيل عبر is_active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->decimal('rate', 5, 2)->comment('نسبة مئوية، مثال 15.00');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
