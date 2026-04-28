<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('binotel_call_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('binotel_api_call_completed_id')->nullable();
            $table->string('general_call_id')->unique();
            $table->string('call_id')->nullable()->index();
            $table->string('transcription_status')->nullable();
            $table->string('transcription_source_type')->nullable();
            $table->string('transcription_source_name')->nullable();
            $table->string('transcription_source_relative_path')->nullable();
            $table->string('transcription_storage_run_directory')->nullable();
            $table->string('transcription_language')->nullable();
            $table->string('transcription_model')->nullable();
            $table->longText('transcription_text')->nullable();
            $table->longText('transcription_dialogue_text')->nullable();
            $table->longText('transcription_formatted_text')->nullable();
            $table->json('transcription_payload')->nullable();
            $table->timestamp('transcribed_at')->nullable();
            $table->string('evaluation_status')->nullable();
            $table->string('last_evaluation_job_id')->nullable();
            $table->string('evaluation_checklist_id')->nullable();
            $table->string('evaluation_checklist_name')->nullable();
            $table->integer('evaluation_score')->nullable();
            $table->integer('evaluation_total_points')->nullable();
            $table->integer('evaluation_score_percent')->nullable();
            $table->text('evaluation_summary')->nullable();
            $table->text('evaluation_strong_side')->nullable();
            $table->text('evaluation_focus')->nullable();
            $table->string('evaluation_provider')->nullable();
            $table->string('evaluation_model')->nullable();
            $table->json('evaluation_payload')->nullable();
            $table->timestamp('evaluation_requested_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('binotel_api_call_completed_id', 'binotel_call_feedbacks_call_fk')
                ->references('id')
                ->on('binotel_api_call_completeds')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('binotel_call_feedbacks', function (Blueprint $table) {
            $table->dropForeign('binotel_call_feedbacks_call_fk');
        });

        Schema::dropIfExists('binotel_call_feedbacks');
    }
};
