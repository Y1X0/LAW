<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * leave_types — أنواع الإجازات وقواعد كل نوع (Issue #17, docs/03 أ-2).
 * سنوية/مرضية/طارئة/بدون راتب؛ لكل نوع رصيد سنوي، ودفع/عدمه، وإلزام مرفق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 30)->unique()->comment('annual/sick/emergency/unpaid');
            $table->boolean('is_paid')->default(true);
            $table->boolean('consumes_balance')->default(true)->comment('unpaid لا يخصم رصيداً');
            $table->boolean('requires_attachment')->default(false)->comment('مثل المرضية → تقرير طبي');
            $table->decimal('default_annual_days', 5, 1)->default(0)->comment('الاستحقاق السنوي الافتراضي');
            $table->unsignedSmallInteger('max_consecutive_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
