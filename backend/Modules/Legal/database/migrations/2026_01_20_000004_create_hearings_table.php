<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * hearings — جلسات القضايا (Legal / LC-3).
 * التأجيل يحافظ على السجل: الجلسة القديمة تصبح postponed (+ postponed_to/reason)
 * وتُنشأ جلسة جديدة scheduled. لا يُعدَّل سجل جلسة held.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hearings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->timestampTz('scheduled_at');
            $table->string('type', 60)->nullable()->comment('تمهيدية/مرافعة/نطق بالحكم… — نص MVP');
            $table->string('status', 20)->default('scheduled')->comment('scheduled/held/postponed/cancelled');
            $table->string('location', 150)->nullable();
            $table->text('outcome')->nullable()->comment('نتيجة الجلسة عند held');
            $table->timestampTz('postponed_to')->nullable()->comment('الموعد الجديد عند التأجيل');
            $table->string('postponed_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['case_id', 'scheduled_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hearings');
    }
};
