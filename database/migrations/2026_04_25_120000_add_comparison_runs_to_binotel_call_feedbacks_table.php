<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binotel_call_feedbacks', function (Blueprint $table) {
            $table->json('comparison_runs')->nullable()->after('evaluation_payload');
            $table->string('active_comparison_run_id')->nullable()->after('comparison_runs')->index();
        });
    }

    public function down(): void
    {
        Schema::table('binotel_call_feedbacks', function (Blueprint $table) {
            $table->dropIndex(['active_comparison_run_id']);
            $table->dropColumn([
                'comparison_runs',
                'active_comparison_run_id',
            ]);
        });
    }
};
