<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط جدول activity_logs — لم يُكتب إليه أبداً (لا نموذج ولا خدمة تستخدمه بعد
 * حذف ActivityLog). كان يُنشأ فارغاً في كل بيئة دون فائدة. dropIfExists تجعلها
 * آمنة على القواعد الجديدة (لا وجود للجدول) والإنتاج (تُسقطه فعلياً).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('activity_logs');
    }

    public function down(): void
    {
        // لا رجعة — الجدول كان ميتاً؛ إعادة إنشائه لا معنى لها.
    }
};
