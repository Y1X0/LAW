<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * branches — الفروع (جذر العزل التنظيمي). راجع docs/02-database-design.md §3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 30)->unique();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('city', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
