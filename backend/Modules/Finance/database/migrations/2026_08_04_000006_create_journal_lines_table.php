<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * journal_lines — سطور القيود اليومية (Finance / Phase 6 · PR-3).
 *
 * كل سطر إمّا مدين أو دائن (أحدهما > 0 والآخر صفر). مجموع المدين = مجموع الدائن لكل
 * قيد (يُفرَض في JournalService). السطور غير قابلة للتعديل بعد ترحيل القيد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestampsTz();

            $table->index(['journal_entry_id']);
            $table->index(['account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
