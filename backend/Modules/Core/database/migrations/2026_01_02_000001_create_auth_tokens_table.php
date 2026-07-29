<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * auth_tokens — جلسات/توكنات المصادقة (Access + Refresh مع تدوير).
 * يحقّق مفهوم "إدارة الجلسات" في docs/02 §3 و docs/05 §5.1 و §5.3
 * (التوكنات تُخزَّن مجزّأة SHA-256، لا نصاً صريحاً).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120)->nullable()->comment('اسم الجهاز/العميل');
            $table->char('access_token_hash', 64)->unique();
            $table->char('refresh_token_hash', 64)->unique();
            $table->timestampTz('access_expires_at');
            $table->timestampTz('refresh_expires_at');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_tokens');
    }
};
