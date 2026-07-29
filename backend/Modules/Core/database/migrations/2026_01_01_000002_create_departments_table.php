<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * departments — الأقسام (هيكل شجري داخل الفرع). راجع docs/02 §3.
 * ملاحظة نطاق: manager_id يشير مستقبلاً إلى employees (وحدة HR، Issue #13)،
 * لذا يُضاف الآن كعمود nullable دون قيد FK؛ يُربط في ترحيل HR لتفادي تجاوز نطاق #10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 120);
            $table->unsignedBigInteger('manager_id')->nullable()->comment('FK → employees يُضاف في وحدة HR');
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
