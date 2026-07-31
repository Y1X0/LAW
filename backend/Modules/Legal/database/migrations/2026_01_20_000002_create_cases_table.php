<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cases — القضايا (Legal / LC-2).
 * لكل قضية رقم داخلي للمكتب (internal_number) ورقم لدى المحكمة (court_case_number).
 * الرؤية مُنطَّقة: المحامي المسند فقط (view_own) مقابل الإدارة (view_all).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('internal_number', 50)->unique()->comment('رقم الملف الداخلي للمكتب');
            $table->string('court_case_number', 50)->nullable()->comment('رقم القضية لدى المحكمة');
            $table->string('title', 200);
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('responsible_lawyer_id')->nullable()->constrained('employees')->nullOnDelete()->comment('المحامي الرئيسي');
            $table->string('court_name', 150)->nullable();
            $table->string('case_type', 50)->nullable()->comment('نص MVP — لاحقاً جدول case_types');
            $table->decimal('value', 15, 2)->nullable()->comment('قيمة القضية');
            $table->string('status', 20)->default('open')->comment('open/pending/closed');
            $table->unsignedTinyInteger('progress')->default(0)->comment('نسبة التقدّم 0-100 (يدوي)');
            $table->date('opened_date')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['status']);
            $table->index(['client_id']);
            $table->index(['responsible_lawyer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
