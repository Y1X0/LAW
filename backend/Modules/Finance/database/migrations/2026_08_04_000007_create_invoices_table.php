<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * invoices — الفواتير (Finance / Phase 6 · PR-4).
 *
 * تُنشأ مسودّة (draft) قابلة للتعديل، ثم تُعتمد (sent) فتُرحَّل قيد الإيراد آلياً وتصير
 * نهائية. الإجماليات تُحسب في InvoiceService (ضريبة لكل بند + خصم على مستوى الفاتورة).
 * `invoice_no` يُولَّد من المعرّف عند الإنشاء (INV-000001). لا branch_id / لا contract_id
 * (مؤجَّلان — Backlog). المدفوعات (partial/paid) تأتي في PR لاحق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 40)->nullable()->unique()->comment('يُولَّد من المعرّف: INV-000001');
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0)->comment('مجموع البنود قبل الضريبة');
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0)->comment('خصم على مستوى الفاتورة');
            $table->decimal('total', 15, 2)->default(0)->comment('subtotal - discount + tax_amount');
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0)->comment('المتبقّي = total - paid_amount');
            $table->string('status', 20)->default('draft')->comment('draft/sent/partial/paid/overdue/cancelled');
            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()->comment('قيد الإيراد المُرحَّل عند الاعتماد');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->index(['status']);
            $table->index(['client_id']);
            $table->index(['case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
