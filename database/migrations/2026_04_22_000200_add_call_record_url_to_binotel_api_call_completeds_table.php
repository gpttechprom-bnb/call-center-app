<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table) {
            $table->text('call_record_url')->nullable()->after('call_details_link_to_call_record_in_my_business');
        });
    }

    public function down(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table) {
            $table->dropColumn('call_record_url');
        });
    }
};
