<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قيم الحقول المخصّصة (Custom Fields / Phase 12 · PR-3). تخزين EAV مُنمَّط: عمود قيمة لكل نوع
 * (نصّي/رقمي/تاريخ/منطقي) — قابل للبحث والتقارير والفهرسة (لا JSON مبهم). القيمة تُربط بالتعريف
 * وبصفّ الكيان (entity + entity_id على نمط auditable في audit_logs، بلا FK عابر للوحدات على الكيان).
 *
 * حذف التعريف مقيّد (restrict) — بيانات قانونية لا تُمحى بـ cascade؛ الخدمة تمنع حذف تعريف له قيم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('custom_field_definitions')->restrictOnDelete();
            $table->string('entity', 40)->comment('الكيان المضيف: case | client | employee');
            $table->unsignedBigInteger('entity_id')->comment('مُعرّف صفّ الكيان (بلا FK عابر للوحدات)');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 20, 4)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            // قيمة واحدة لكل (حقل، صفّ كيان).
            $table->unique(['definition_id', 'entity', 'entity_id'], 'custom_field_values_unique');
            // تحميل كل قيم صفّ كيان (أو صفحة كيانات) دفعةً — بلا N+1.
            $table->index(['entity', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
