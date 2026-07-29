<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * user_role — إسناد الأدوار للمستخدمين، مع تقييد اختياري بالفرع. راجع docs/02 §3.
 * نستخدم مفتاحاً بديلاً (id) + فهرس فريد على (user_id, role_id, branch_id)
 * لتفادي عمود nullable ضمن PK (branch_id قد يكون NULL لدور غير مقيّد بفرع).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestampTz('created_at')->nullable()->useCurrent();

            $table->unique(['user_id', 'role_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role');
    }
};
