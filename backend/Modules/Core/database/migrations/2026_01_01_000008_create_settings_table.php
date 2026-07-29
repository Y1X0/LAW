<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * settings — إعدادات النظام (Key/Value بـ JSONB)، عامة أو خاصة بفرع. راجع docs/02 §3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('group', 40)->comment('general/email/sms/notifications/payroll');
            $table->string('key', 80);
            $table->jsonb('value')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'group', 'key']);
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
