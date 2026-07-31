<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * case_archive_locations — فهرسة الأرشيف الورقي (Legal / LG-3).
 * عدّة مواقع لكل قضية (الملف موزّع أحياناً على غرف/خزائن مختلفة). فهرسة فقط، بلا رفع ملفات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_archive_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('file_title', 200);
            $table->string('archive_room', 100)->nullable();
            $table->string('cabinet', 50)->nullable();
            $table->string('shelf', 50)->nullable();
            $table->string('drawer', 50)->nullable();
            $table->string('file_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_archive_locations');
    }
};
