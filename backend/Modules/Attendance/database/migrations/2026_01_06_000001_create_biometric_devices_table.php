<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * biometric_devices — تسجيل أجهزة البصمة وتجريد المورّد ونمط التكامل (Issue #16).
 * كل جهاز له مورّد (zkteco…) ونمط api (push/pull) وتوكن سري مشفّر (docs/04 §2, §8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('vendor', 20)->default('zkteco')->comment('zkteco/hikvision/suprema/anviz');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('api_mode', 10)->default('push')->comment('push/pull');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('serial_number', 80)->nullable()->unique();
            $table->text('auth_token')->nullable()->comment('توكن الجهاز — مشفّر at-rest (cast encrypted)');
            $table->string('status', 12)->default('offline')->comment('online/offline/disabled');
            $table->timestampTz('last_sync_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('vendor');
            $table->index('status');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
