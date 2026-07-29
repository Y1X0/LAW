<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * roles — الأدوار. راجع docs/02 §3 و docs/05 (RBAC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('display_name', 120);
            $table->boolean('is_system')->default(false)->comment('أدوار نظامية غير قابلة للحذف');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
