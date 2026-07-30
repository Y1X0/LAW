<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تحصين تكامل البصمة (متابعة Issue #16):
 *  - timezone لكل جهاز: يُفسَّر وقت النبضة بمنطقة الجهاز ثم يُحوَّل UTC قبل التخزين
 *    (يمنع انزياح التوقيت بين فروع/أجهزة بمناطق زمنية مختلفة).
 *  - حذف ناعم للأجهزة: تقاعد/إيقاف جهاز لا يمحو سجلات البصمة الخام (بيانات تدقيق).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->string('timezone', 40)->nullable()->after('serial_number')
                ->comment('منطقة الجهاز الزمنية (IANA)؛ null = منطقة التطبيق');
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
            $table->dropColumn('timezone');
        });
    }
};
