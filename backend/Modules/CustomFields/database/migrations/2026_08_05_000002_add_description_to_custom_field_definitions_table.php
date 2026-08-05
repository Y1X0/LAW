<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وصف اختياري لتعريف الحقل (Phase 12 · PR-2). مع تراكم عشرات الحقول لكل شركة يصبح الوصف
 * ضروريّاً لتوثيق غرض الحقل (مثال: «رقم العقد الموقّع مع العميل»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->text('description')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
