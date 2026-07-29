<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * employees — الكيان المحوري في HR (Issue #13). راجع docs/02 §4.
 * يرتبط بالفرع والقسم (جداول Core) وبمدير مباشر (علاقة ذاتية).
 * البدلات/الخصومات والمستندات المتقدمة في وحدات لاحقة (#14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('employee_no', 30)->unique();
            $table->string('full_name_ar', 150);
            $table->string('full_name_en', 150)->nullable();
            $table->string('national_id', 20)->unique();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable()->comment('male/female');
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('hire_date')->nullable();
            $table->string('contract_type', 30)->nullable()->comment('permanent/temporary/part_time');
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('biometric_user_id', 40)->nullable()->comment('معرّف الموظف في جهاز البصمة (#16)');
            $table->string('status', 20)->default('active')->comment('active/on_leave/suspended/terminated');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('department_id');
            $table->index('branch_id');
            $table->index('status');
            $table->index('full_name_ar');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
