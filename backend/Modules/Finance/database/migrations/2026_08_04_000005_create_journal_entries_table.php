<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * journal_entries — رؤوس القيود اليومية (Finance / Phase 6 · PR-3).
 *
 * أساس القيد المزدوج. القيد المُرحَّل (posted) غير قابل للتعديل أو الحذف — أي تصحيح
 * يكون بقيد عكس (reversal) يشير إلى الأصل عبر reversal_of_id. `entry_no` يُولَّد من
 * المعرّف عند الترحيل (JE-000001) فيبقى فريداً بلا سباق. الترحيل ذرّي مع سطوره.
 * لا branch_id (تعدّد الفروع مؤجَّل — Backlog).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_no', 40)->nullable()->unique()->comment('يُولَّد من المعرّف عند الترحيل: JE-000001');
            $table->date('entry_date');
            $table->string('description', 255)->nullable();
            $table->string('reference_type', 60)->nullable()->comment('نوع المستند المصدر: invoice/payment/expense');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('posted')->default(false);
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete()->comment('القيد الأصلي الذي يعكسه هذا القيد');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('posted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->comment('اعتماد صريح (اختياري) — منفصل عن الترحيل');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->index(['reference_type', 'reference_id']);
            $table->index(['posted']);
            $table->index(['entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
