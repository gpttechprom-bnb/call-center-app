<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table) {
            $table->string('alt_auto_status')->nullable()->index();
            $table->timestamp('alt_auto_started_at')->nullable();
            $table->timestamp('alt_auto_finished_at')->nullable();
            $table->text('alt_auto_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table) {
            $table->dropIndex(['alt_auto_status']);
            $table->dropColumn([
                'alt_auto_status',
                'alt_auto_started_at',
                'alt_auto_finished_at',
                'alt_auto_error',
            ]);
        });
    }
};
