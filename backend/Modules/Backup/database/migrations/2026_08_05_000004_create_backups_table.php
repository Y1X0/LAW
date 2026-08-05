<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ النسخ الاحتياطية لقاعدة البيانات (Phase 13 · Go-Live). كل صفّ = نسخة pg_dump مرفوعة
 * إلى تخزين النسخ (دلو R2 منفصل). النوع (kind) يقود الاحتفاظ المتدرّج (GFS): يومي/أسبوعي/شهري
 * + يدوي. لا تُخزَّن القيمة نفسها هنا — فقط بياناتها الوصفية؛ الملف على التخزين الخارجي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->nullable();
            $table->string('disk', 40)->default('backups');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('kind', 20)->default('manual')->comment('manual|daily|weekly|monthly');
            $table->string('status', 20)->default('pending')->comment('pending|completed|failed');
            $table->string('trigger', 20)->default('manual')->comment('manual|scheduled');
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['kind', 'id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
