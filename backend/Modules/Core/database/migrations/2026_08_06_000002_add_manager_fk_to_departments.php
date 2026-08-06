<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * departments.manager_id — إضافة قيد المفتاح الأجنبي المؤجَّل (B5 · M6).
 *
 * أُنشئ العمود بلا FK (تعليق «يُضاف في وحدة HR») لكنه لم يُضَف قط. هذه الهجرة تُغلق الفجوة:
 * FK إلى employees(id) مع nullOnDelete (حذف الموظف يُفرّغ إشارة المدير لا يكسرها). متوافقة مع
 * المحرّكين (SQLite يعيد بناء الجدول، Postgres عبر ALTER)، وآمنة على البيانات (manager_id كلّها
 * NULL افتراضياً — لا قيم يتيمة تمنع القيد).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
    }
};
