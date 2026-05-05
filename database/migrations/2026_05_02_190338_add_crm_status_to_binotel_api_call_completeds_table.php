<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table) {
            $table->string('crm_normalized_phone', 20)->nullable()->index();
            $table->boolean('crm_phone_exists')->nullable()->index();
            $table->boolean('crm_missing')->nullable()->index();
            $table->string('crm_manager')->nullable();
            $table->string('crm_case')->nullable()->index();
            $table->timestamp('crm_checked_at')->nullable()->index();
            $table->text('crm_lookup_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table) {
            $table->dropIndex(['crm_normalized_phone']);
            $table->dropIndex(['crm_phone_exists']);
            $table->dropIndex(['crm_missing']);
            $table->dropIndex(['crm_case']);
            $table->dropIndex(['crm_checked_at']);
            $table->dropColumn([
                'crm_normalized_phone',
                'crm_phone_exists',
                'crm_missing',
                'crm_manager',
                'crm_case',
                'crm_checked_at',
                'crm_lookup_error',
            ]);
        });
    }
};
