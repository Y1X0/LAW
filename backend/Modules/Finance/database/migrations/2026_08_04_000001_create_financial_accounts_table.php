<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * financial_accounts — الحسابات النقدية (صندوق/بنك) (Finance / Phase 6 · PR-1).
 *
 * كل حساب صندوق أو بنك يستقبل المدفوعات ويصرف منه المصروفات. `current_balance`
 * قيمة محسوبة تُحدَّث لاحقاً عبر حركات القبض/الصرف (تبدأ = opening_balance).
 * تُعطَّل عبر is_active بلا حذف فعلي حفاظاً على تاريخ الحركات.
 *
 * ملاحظة تصميم: لا عمود branch_id في هذا الطور (تعدّد الفروع مؤجَّل — انظر Backlog).
 * الأعمدة والفهارس مصمَّمة بحيث يمكن إضافة branch_id لاحقاً دون كسر الـAPI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('type', 20)->default('cash')->comment('cash/bank');
            $table->string('account_number', 50)->nullable()->comment('رقم الحساب/IBAN للبنك');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0)->comment('محسوب — يُحدَّث عبر الحركات');
            $table->char('currency', 3)->default('SAR');
            $table->boolean('is_active')->default(true)->comment('تعطيل بلا حذف فعلي');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['type']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};
