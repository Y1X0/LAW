<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * clients — عملاء المكتب (Legal / LC-1).
 * أفراد أو شركات، بلا حذف فعلي: التعطيل عبر status حفاظاً على تاريخ القضايا لاحقاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('type', 20)->default('individual')->comment('individual/company');
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('national_id', 50)->nullable()->comment('هوية وطنية أو سجل تجاري');
            $table->string('address', 255)->nullable();
            $table->string('status', 20)->default('active')->comment('active/inactive — لا حذف فعلي');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['status']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
