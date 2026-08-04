<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * expenses — سندات الصرف (Finance / Phase 6 · PR-8).
 *
 * صرف مصروف مصنَّف من حساب صندوق/بنك، بربط اختياري بقضية (رسوم محكمة...). يُرحِّل قيداً
 * آليّاً (مدين حساب المصروف = دائن الصندوق/البنك) ويُنقص رصيد الحساب الدافع. لا حذف —
 * التصحيح بعكس ينشئ سند صرف عكسيّاً بمبلغ سالب مرتبطاً بالأصل، ويبقى الأصل تاريخيّاً.
 *
 * `category_id` و`case_id` يُبقيان البيانات جاهزة لتقارير الأرباح/الخسائر والمصروفات حسب
 * التصنيف/القضية. `voucher_no` يُولَّد من المعرّف (EXP-000001). لا branch_id (مؤجَّل).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 40)->nullable()->unique()->comment('يُولَّد من المعرّف: EXP-000001');
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete()->comment('مصروف مرتبط بقضية (رسوم محكمة...)');
            $table->decimal('amount', 15, 2)->comment('سالب لسند الصرف العكسي');
            $table->string('method', 20)->comment('cash/bank_transfer/cheque');
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete()->comment('الصندوق/البنك الدافع');
            $table->string('beneficiary', 150)->nullable()->comment('المستفيد');
            $table->date('expense_date');
            $table->text('description')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()->comment('قيد الصرف المُرحَّل');
            $table->foreignId('reversal_of_id')->nullable()->constrained('expenses')->nullOnDelete()->comment('السند الأصلي الذي يعكسه هذا السند');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->index(['category_id']);
            $table->index(['case_id']);
            $table->index(['account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
