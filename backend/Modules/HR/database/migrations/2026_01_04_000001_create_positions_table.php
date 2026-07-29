<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * positions — كتالوج المسميات الوظيفية (Basic HR Records — Issue #14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('title', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
