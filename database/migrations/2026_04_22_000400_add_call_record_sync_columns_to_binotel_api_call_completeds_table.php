<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table) {
            $table->unsignedInteger('call_record_url_check_attempts')->default(0);
            $table->timestamp('call_record_url_last_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table) {
            $table->dropColumn([
                'call_record_url_check_attempts',
                'call_record_url_last_checked_at',
            ]);
        });
    }
};
