<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * leave_requests — دورة طلب الإجازة (Issue #17, docs/03 أ-2, docs/10 §2.2).
 * pending → approved/rejected/cancelled. days = أيام العمل الفعلية (استبعاد الويكند).
 * is_escalated: يُصعَّد الاعتماد عندما يكون مقدّم الطلب مديراً (Manager-of-Manager).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 4, 1)->comment('أيام العمل الفعلية المحتسبة');
            $table->text('reason')->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('status', 12)->default('pending')->comment('pending/approved/rejected/cancelled');
            $table->boolean('is_escalated')->default(false);
            $table->foreignId('approver_id')->nullable()->constrained('employees')->nullOnDelete()->comment('المدير المعتمِد المتوقّع');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
