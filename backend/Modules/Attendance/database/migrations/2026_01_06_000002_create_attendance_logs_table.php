<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * attendance_logs — طبقة Staging الخام لسجلات البصمة (Issue #16, docs/04 §5).
 * كل نبضة تُحفَظ كاملةً (raw_payload) قبل معالجتها إلى attendance_records — لا فقدان.
 * الفهرس الفريد (device_id, biometric_user_id, punch_time) يضمن منع التكرار (Idempotency).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('biometric_user_id', 40);
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestampTz('punch_time');
            $table->string('punch_type', 10)->default('unknown')->comment('in/out/unknown');
            $table->string('verify_mode', 20)->nullable()->comment('fingerprint/face/card/password');
            $table->string('source', 10)->default('push')->comment('push/pull');
            $table->string('status', 12)->default('pending')->comment('pending/matched/unmatched/processed');
            $table->timestampTz('processed_at')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestampsTz();

            // منع التكرار: أي إعادة إرسال لنفس (الجهاز/المستخدم/الوقت) تُتجاهل.
            $table->unique(['device_id', 'biometric_user_id', 'punch_time'], 'attendance_logs_dedup_unique');
            $table->index('employee_id');
            $table->index('status');
            $table->index('biometric_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
