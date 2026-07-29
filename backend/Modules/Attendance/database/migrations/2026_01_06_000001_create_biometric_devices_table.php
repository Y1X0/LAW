<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * biometric_devices — أجهزة البصمة المسجّلة (docs/04 §3, ADR-003) — Biometric Integration (Issue #16).
 * كل جهاز له مورّد (vendor) ونمط تكامل (push/pull) ومفتاح سري للتحقق من حمولات Push.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 100);
            $table->string('vendor', 20)->comment('zkteco/hikvision/suprema/anviz');
            $table->string('api_mode', 10)->default('push')->comment('push/pull');
            $table->string('serial_number', 80)->nullable()->unique();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('secret', 128)->comment('مفتاح سري للتحقق من Push/Webhook');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_sync_at')->nullable();
            $table->string('status', 20)->default('unknown')->comment('online/offline/unknown');
            $table->timestampsTz();

            $table->index('vendor');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
