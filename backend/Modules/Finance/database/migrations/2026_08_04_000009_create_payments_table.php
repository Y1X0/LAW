<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payments — سندات القبض (Finance / Phase 6 · PR-6).
 *
 * تحصيل دفعة مقابل فاتورة: تُرحِّل قيداً آليّاً (مدين صندوق/بنك = دائن ذمم العملاء)
 * وتحدّث رصيد الفاتورة وحالتها (sent→partial→paid). لا حذف — التصحيح بعكس (reversal)
 * ينشئ سند قبض عكسيّاً بمبلغ سالب مرتبطاً بالأصل، ويبقى الأصل تاريخيّاً.
 *
 * `idempotency_key` فريد يمنع ازدواج التحصيل: إعادة الطلب بنفس المفتاح تُعيد السند نفسه
 * بلا قيد جديد. `receipt_no` يُولَّد من المعرّف (RCP-000001). لا branch_id (مؤجَّل).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 40)->nullable()->unique()->comment('يُولَّد من المعرّف: RCP-000001');
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->decimal('amount', 15, 2)->comment('سالب لسند القبض العكسي');
            $table->string('method', 20)->comment('cash/bank_transfer/cheque/card');
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete()->comment('الصندوق/البنك المستلِم');
            $table->string('reference', 80)->nullable()->comment('رقم الشيك/التحويل');
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()->comment('قيد القبض المُرحَّل');
            $table->foreignId('reversal_of_id')->nullable()->constrained('payments')->nullOnDelete()->comment('السند الأصلي الذي يعكسه هذا السند');
            $table->string('idempotency_key', 80)->nullable()->unique()->comment('يمنع ازدواج التحصيل');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->index(['invoice_id']);
            $table->index(['client_id']);
            $table->index(['account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
