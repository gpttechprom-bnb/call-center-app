<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('binotel_api_call_completeds')
            ->whereNull('alt_auto_status')
            ->update([
                'alt_auto_status' => 'skipped',
                'alt_auto_finished_at' => now(),
                'alt_auto_error' => 'Запис існував до запуску автоматичної черги.',
            ]);
    }

    public function down(): void
    {
        DB::table('binotel_api_call_completeds')
            ->where('alt_auto_status', 'skipped')
            ->where('alt_auto_error', 'Запис існував до запуску автоматичної черги.')
            ->update([
                'alt_auto_status' => null,
                'alt_auto_finished_at' => null,
                'alt_auto_error' => null,
            ]);
    }
};
