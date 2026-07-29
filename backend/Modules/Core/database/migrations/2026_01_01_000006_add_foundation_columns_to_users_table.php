<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توسيع جدول users بحقول الأساس (مصادقة/أمان) وفق docs/02 §3 و docs/05.
 * ملاحظة نطاق: employee_id (FK → employees) يُضاف في وحدة HR (Issue #13)،
 * ومنطق المصادقة/MFA يُنفَّذ في Issue #11 — هنا نضع أساس المخطط فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->nullable()->unique()->after('name');
            $table->string('mfa_secret', 255)->nullable()->comment('سر TOTP مشفّر')->after('password');
            $table->boolean('mfa_enabled')->default(false)->after('mfa_secret');
            $table->string('status', 20)->default('active')->comment('active/suspended/locked')->after('mfa_enabled');
            $table->timestampTz('last_login_at')->nullable()->after('status');
            $table->unsignedSmallInteger('failed_attempts')->default(0)->after('last_login_at');
            $table->timestampTz('locked_until')->nullable()->after('failed_attempts');
            $table->timestampTz('password_changed_at')->nullable()->after('locked_until');
            $table->softDeletesTz();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
            $table->dropIndex(['status']);
            $table->dropColumn([
                'username', 'mfa_secret', 'mfa_enabled', 'status',
                'last_login_at', 'failed_attempts', 'locked_until', 'password_changed_at',
            ]);
        });
    }
};
