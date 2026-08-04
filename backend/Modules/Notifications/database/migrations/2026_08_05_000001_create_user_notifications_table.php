<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * user_notifications — إشعارات داخل النظام لكل مستخدم (Notifications / Phase 8 · PR-1).
 *
 * جدول مخصّص بمفتاح صحيح و`user_id` صريح (نمط البيت) — لا نستخدم جدول Laravel القياسي
 * متعدّد الأشكال (`notifications`) تفادياً للتعارض مع علاقة `Notifiable::notifications()`
 * على نموذج المستخدم. `read_at = null` يعني «غير مقروء». `related_type/related_id`
 * ربط اختياري بالكيان المصدر (فاتورة/جلسة/مهمة...) دون FK صلب (الكيان قد يُحذف/يُعكَس).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('المستلِم');
            $table->string('type', 60)->comment('نوع الإشعار: payment_recorded/leave_approved/...');
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('related_type', 60)->nullable()->comment('اسم الكيان المرتبط (Invoice/Hearing/...)');
            $table->unsignedBigInteger('related_id')->nullable()->comment('معرّف الكيان المرتبط');
            $table->timestampTz('read_at')->nullable()->comment('null = غير مقروء');
            $table->timestampsTz();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
