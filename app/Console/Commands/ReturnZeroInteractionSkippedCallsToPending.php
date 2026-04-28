<?php

namespace App\Console\Commands;

use App\Models\BinotelApiCallCompleted;
use Illuminate\Console\Command;

class ReturnZeroInteractionSkippedCallsToPending extends Command
{
    private const LEGACY_SKIPPED_ERROR = 'Запис існував до запуску автоматичної черги.';

    protected $signature = 'call-center:return-zero-interaction-skipped-calls
        {--dry-run : Show affected calls without updating them}
        {--limit=0 : Maximum number of calls to return to pending}';

    protected $description = 'Return to pending only calls that were skipped earlier and are now eligible after interaction_number recalculation';

    public function handle(): int
    {
        $calls = $this->eligibleCallsQuery()
            ->orderBy('call_details_start_time')
            ->orderBy('id')
            ->when(
                (int) $this->option('limit') > 0,
                fn ($query) => $query->limit((int) $this->option('limit'))
            )
            ->get([
                'id',
                'call_details_general_call_id',
                'call_details_start_time',
                'interaction_number',
                'alt_auto_status',
                'alt_auto_error',
            ]);

        if ($calls->isEmpty()) {
            $this->info('No skipped calls matched the zero-interaction recovery filter.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'general_call_id', 'start_time', 'interaction_number', 'alt_auto_status'],
            $calls->map(fn (BinotelApiCallCompleted $call): array => [
                'id' => $call->id,
                'general_call_id' => $call->call_details_general_call_id,
                'start_time' => $call->call_details_start_time,
                'interaction_number' => $call->interaction_number,
                'alt_auto_status' => $call->alt_auto_status,
            ])->all()
        );

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf(
                'Dry run: %d call(s) would be returned to pending.',
                $calls->count(),
            ));

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($calls as $call) {
            $call->forceFill([
                'alt_auto_status' => 'pending',
                'alt_auto_error' => null,
                'alt_auto_started_at' => null,
                'alt_auto_finished_at' => null,
            ])->save();

            $updated++;
        }

        $this->info(sprintf(
            'Returned %d call(s) to pending.',
            $updated,
        ));

        return self::SUCCESS;
    }

    private function eligibleCallsQuery()
    {
        return BinotelApiCallCompleted::query()
            ->where('request_type', 'apiCallCompleted')
            ->whereNotNull('call_details_general_call_id')
            ->where('call_details_general_call_id', '<>', '')
            ->where('call_details_disposition', 'ANSWER')
            ->where('call_details_billsec', '>', 0)
            ->whereBetween('interaction_number', [1, 20])
            ->where('alt_auto_status', 'skipped')
            ->where('alt_auto_error', self::LEGACY_SKIPPED_ERROR)
            ->whereDoesntHave('feedback');
    }
}
