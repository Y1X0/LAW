<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * attendance_logs — طبقة Staging الخام لبصمات الأجهزة (docs/04 §4, ADR-003) — Issue #16.
 * كل نبضة تُحفظ كاملةً (raw_payload) قبل معالجتها إلى attendance_records — لا فقدان.
 * منع التكرار (Idempotency): فهرس فريد (device_id, biometric_user_id, punch_time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('biometric_user_id', 40)->comment('معرّف المستخدم في الجهاز');
            $table->timestampTz('punch_time');
            $table->string('punch_type', 12)->default('unknown')->comment('check_in/check_out/unknown');
            $table->string('verify_mode', 20)->nullable()->comment('fingerprint/face/card/password');
            $table->jsonb('raw_payload')->nullable();
            $table->string('status', 12)->default('pending')->comment('pending/processed/unmatched');
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['device_id', 'biometric_user_id', 'punch_time'], 'attendance_logs_idempotency');
            $table->index('status');
            $table->index('biometric_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
