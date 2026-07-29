<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * role_permission — ربط M:N بين الأدوار والصلاحيات (PK مركّب). راجع docs/02 §3.
 * جدول ربط بحت: created_at فقط حسب اصطلاحات قاعدة البيانات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestampTz('created_at')->nullable()->useCurrent();

            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
    }
};
