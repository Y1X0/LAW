<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * case_parties — أطراف القضية (Legal / LG-2).
 * الخصم/الشهود/أطراف أخرى مرتبطون بالقضية فقط (لا Client جديد). العميل الأساسي في clients.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('type', 20)->comment('plaintiff/defendant/witness/other');
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_parties');
    }
};
