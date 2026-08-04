<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * invoice_items — بنود الفاتورة (Finance / Phase 6 · PR-4).
 *
 * لكل بند نسبة ضريبة مستقلّة (tax_rate ٪، 0 للمعفى). `line_total` محسوب = الكمية ×
 * سعر الوحدة قبل الضريبة. البنود تُحذف وتُعاد إنشاؤها عند تعديل المسودّة فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 15, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('نسبة الضريبة لهذا البند ٪ (0 للمعفى)');
            $table->decimal('line_total', 15, 2)->default(0)->comment('محسوب: quantity × unit_price قبل الضريبة');
            $table->timestampsTz();

            $table->index(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
