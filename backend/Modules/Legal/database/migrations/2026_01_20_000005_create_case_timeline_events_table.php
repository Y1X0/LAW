<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * case_timeline_events — الخط الزمني للقضية (Legal / LC-4).
 * سجلّ Append-Only: كل حدث يُنشأ مرة واحدة ولا يُعدَّل ولا يُحذف؛ التصحيح = حدث جديد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('event_type', 60)->nullable()->comment('نص MVP: filing/hearing/memo/judgment/note/correction…');
            $table->date('event_date');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['case_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_timeline_events');
    }
};
