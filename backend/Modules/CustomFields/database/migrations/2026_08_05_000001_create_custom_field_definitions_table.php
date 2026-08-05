<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تعريفات الحقول المخصّصة (Custom Fields / Phase 12) — محرّك تخصيص يجعل النظام قابلاً
 * للتوسّع لكل شركة دون تعديل كود: يُعرّف المدير حقولاً إضافية لكل كيان (قضية/عميل/موظف).
 *
 * التخزين EAV مُنمَّط (جدول القيم يأتي لاحقاً بأعمدة value_*). صلاحيات كل حقل تُخزَّن هنا
 * كقوائم أدوار (بيانات لا كود) لتفادي تفجير كتالوج RBAC مع كل حقل جديد تضيفه الشركة.
 * display_in يحدّد أين يظهر الحقل (إنشاء/تعديل/تفصيل/جدول) فتُخفى الحقول الحسّاسة من الجدول.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 40)->comment('الكيان المضيف: case | client | employee');
            $table->string('key', 60)->comment('مُعرّف تقني (slug) فريد لكل كيان');
            $table->string('label', 150)->comment('التسمية المعروضة');
            $table->string('type', 20)->comment('نوع الحقل — أحد CustomFieldDefinition::TYPES');
            $table->boolean('required')->default(false);
            $table->jsonb('options')->nullable()->comment('خيارات القائمة المنسدلة');
            $table->text('default_value')->nullable();
            $table->jsonb('validation')->nullable()->comment('قيود إضافية: min/max/regex');
            $table->jsonb('display_in')->nullable()->comment('أين يظهر: create/edit/details/list');
            // صلاحيات كل حقل (بيانات): فارغ/null ⇒ يرث صلاحية الكيان المضيف.
            $table->jsonb('view_roles')->nullable()->comment('أدوار العرض');
            $table->jsonb('edit_roles')->nullable()->comment('أدوار التعديل');
            $table->jsonb('search_roles')->nullable()->comment('أدوار البحث');
            $table->jsonb('export_roles')->nullable()->comment('أدوار التصدير');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['entity', 'key'], 'custom_field_definitions_entity_key_unique');
            $table->index(['entity', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
    }
};
