<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة أعمدة القفل لمسير الرواتب (Issue #37) — من قفل المسير ومتى.
 * بعد القفل تصبح أرقام المسير غير قابلة للتغيير (approved → locked).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('locked_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestampTz('locked_at')->nullable()->after('locked_by');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn('locked_at');
        });
    }
};
