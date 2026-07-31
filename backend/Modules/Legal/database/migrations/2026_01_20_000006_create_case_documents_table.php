<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * case_documents — مستندات القضية (Legal / LC-4).
 * بيانات وصفية فقط (metadata + مسار تخزين) — بلا رفع فعلي حتى مرحلة Production Hardening.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('document_type', 60)->nullable()->comment('نص MVP: مذكرة/لائحة/عقد/حكم/وكالة/إثبات');
            $table->text('description')->nullable();
            $table->string('storage_disk', 50)->nullable()->comment('metadata فقط — لا رفع فعلي');
            $table->string('storage_path', 500)->nullable()->comment('metadata فقط — لا رفع فعلي');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_documents');
    }
};
