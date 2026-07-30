<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الموظف بحساب مستخدم (Epic 9 / Issue #47) — أساس الخدمة الذاتية.
 *
 * علاقة 1:1 **اختيارية للطرفين**:
 *  - موظف قد لا يملك حساب دخول (user_id = null).
 *  - مستخدم قد لا يكون موظفاً (Super Admin/Auditor) — لا يظهر هنا.
 * `unique` يمنع ربط مستخدم واحد بأكثر من موظف. `nullOnDelete` يفكّ الربط عند
 * حذف المستخدم **دون إفساد سجل الموظف** (السجل يبقى، user_id يصبح null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
