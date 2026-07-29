<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * employee_contracts — عقود التوظيف (سجل/تاريخ) — Basic HR Records (Issue #14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('contract_type', 30)->comment('permanent/temporary/part_time');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('status', 20)->default('active')->comment('active/expired/terminated');
            $table->string('file_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
