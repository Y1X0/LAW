<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * employee_documents — مستندات الموظف (docs/02 §4) — Basic HR Records (Issue #14).
 * تخزين البيانات الوصفية والمسار؛ الأرشيف المركزي وحدة لاحقة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('doc_type', 40)->comment('contract/id_copy/certificate/other');
            $table->string('title', 200);
            $table->string('file_path', 255);
            $table->date('expiry_date')->nullable()->comment('لتنبيه انتهاء الوثيقة');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['employee_id', 'doc_type']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
