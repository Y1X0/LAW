<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * chart_of_accounts — دليل الحسابات (Finance / Phase 6 · PR-2).
 *
 * أساس المحاسبة بالقيد المزدوج: كل حساب له `code` مرجعي و`type` محاسبي وتسلسل هرمي
 * عبر parent_id. الحسابات النظامية التي يرحّل إليها المحرّك آلياً (ذمم العملاء،
 * ضريبة مستحقة، إيرادات أتعاب، صندوق/بنك، مصروف عام) تُوسَم بـ `system_role` ثابت.
 *
 * مبدأ حاكم: منطق المحاسبة يحصل على الحساب عبر `system_role` (دور دلالي ثابت) لا عبر
 * الرقم — فيمكن إعادة ترقيم دليل الحسابات لاحقاً دون تعديل أي منطق. لا branch_id (مؤجَّل).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('رمز الحساب المرجعي');
            $table->string('name', 120);
            $table->string('type', 20)->comment('asset/liability/equity/revenue/expense');
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('system_role', 40)->nullable()->unique()->comment('دور دلالي ثابت للحسابات النظامية (لا يتغيّر بتغيّر الترقيم)');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['type']);
            $table->index(['parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
