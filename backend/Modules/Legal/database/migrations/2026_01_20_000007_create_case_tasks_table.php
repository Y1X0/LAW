<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * case_tasks — مهام المحامي (Legal / LC-5).
 * مهمة مسندة لموظف واحد (MVP)، مرتبطة بقضية اختيارياً. العزل بالإسناد (view_own).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('normal')->comment('low/normal/high');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open')->comment('open/done');
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('assigned_to')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['assigned_to', 'status']);
            $table->index(['case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_tasks');
    }
};
