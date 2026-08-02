<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول إنهاء الخدمة (M1/PR-2 hardening) — تاريخ وسبب الإنهاء بجانب حالة الموظف
 * (status=terminated). مطلوبة لدورة حياة الموارد البشرية والرواتب لاحقاً. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('termination_date')->nullable()->after('contract_end');
            $table->string('termination_reason', 255)->nullable()->after('termination_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['termination_date', 'termination_reason']);
        });
    }
};
