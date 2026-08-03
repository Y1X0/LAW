<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * case_documents — أعمدة الملف الفعلي (Legal / Phase 5 · PR-1 Storage Foundation).
 * تُضاف تمهيداً للرفع الحقيقي إلى R2 في PR-2. الاسم الأصلي يُحفظ في قاعدة البيانات
 * فقط؛ المسار على التخزين يُشتقّ خادميّاً (cases/{case_id}/{uuid}.{ext}) ولا يُقبل
 * من العميل. الأعمدة nullable ليتعايش السجلّ الوصفي القديم مع الملفات الجديدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_documents', function (Blueprint $table) {
            $table->string('original_name', 255)->nullable()->after('description')->comment('اسم الملف الأصلي كما رفعه المستخدم — عرض فقط');
            $table->string('mime_type', 100)->nullable()->after('original_name');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->string('checksum', 64)->nullable()->after('size_bytes')->comment('sha256 لمنع التكرار/التحقّق');
        });
    }

    public function down(): void
    {
        Schema::table('case_documents', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'mime_type', 'size_bytes', 'checksum']);
        });
    }
};
