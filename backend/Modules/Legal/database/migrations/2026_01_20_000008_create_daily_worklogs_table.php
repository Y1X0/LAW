<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * daily_worklogs — الإنجاز اليومي الذاتي (Legal / LC-5).
 * سجل واحد لكل (موظف، يوم)؛ يُكتب/يُحدَّث لليوم فقط. المحامي يرى سجله فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_worklogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->text('done_today');
            $table->text('plan_tomorrow')->nullable();
            $table->timestampsTz();

            $table->unique(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_worklogs');
    }
};
