<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table): void {
            $table->string('local_audio_relative_path')->nullable()->after('call_record_url');
            $table->string('local_audio_original_name')->nullable()->after('local_audio_relative_path');
            $table->string('local_audio_mime_type')->nullable()->after('local_audio_original_name');
            $table->unsignedBigInteger('local_audio_size_bytes')->nullable()->after('local_audio_mime_type');
            $table->timestamp('local_audio_downloaded_at')->nullable()->after('local_audio_size_bytes');
            $table->timestamp('local_audio_expires_at')->nullable()->after('local_audio_downloaded_at');
            $table->text('local_audio_last_error')->nullable()->after('local_audio_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table): void {
            $table->dropColumn([
                'local_audio_relative_path',
                'local_audio_original_name',
                'local_audio_mime_type',
                'local_audio_size_bytes',
                'local_audio_downloaded_at',
                'local_audio_expires_at',
                'local_audio_last_error',
            ]);
        });
    }
};
