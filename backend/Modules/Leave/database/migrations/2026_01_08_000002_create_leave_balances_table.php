<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * leave_balances — رصيد الإجازة لكل موظف/نوع/سنة (Issue #17, docs/03 أ-2).
 * المتبقي = المستحق (entitled) − المستهلك (consumed). سجل واحد لكل (موظف، نوع، سنة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled_days', 5, 1)->default(0);
            $table->decimal('consumed_days', 5, 1)->default(0);
            $table->timestampsTz();

            $table->unique(['employee_id', 'leave_type_id', 'year'], 'leave_balances_unique');
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
