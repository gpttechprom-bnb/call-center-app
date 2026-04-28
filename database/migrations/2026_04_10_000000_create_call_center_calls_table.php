<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('call_center_calls', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 8);
            $table->string('caller');
            $table->string('caller_meta')->nullable();
            $table->string('employee');
            $table->string('employee_meta')->nullable();
            $table->unsignedInteger('duration_seconds');
            $table->dateTime('started_at');
            $table->string('transcript_status');
            $table->string('audio_status');
            $table->unsignedTinyInteger('score');
            $table->text('summary');
            $table->longText('transcript');
            $table->text('note');
            $table->timestamps();

            $table->index('started_at');
            $table->index('employee');
            $table->index('score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_center_calls');
    }
};
