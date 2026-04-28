<?php

namespace App\Console\Commands;

use App\Models\BinotelApiCallCompleted;
use App\Services\BinotelCallAudioCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CacheBinotelCallAudio extends Command
{
    protected $signature = 'call-center:cache-audio
        {--limit=100 : Maximum number of calls to inspect in one run}';

    protected $description = 'Download and cache Binotel call audio locally for all answered calls';

    public function handle(BinotelCallAudioCacheService $audioCacheService): int
    {
        if (! $this->supportsLocalAudioCache()) {
            $this->info('Audio cache scan skipped: local audio cache columns are not available yet.');

            return self::SUCCESS;
        }

        $deletedExpired = $audioCacheService->deleteExpiredCopies();
        $processed = 0;
        $downloaded = 0;

        $calls = BinotelApiCallCompleted::query()
            ->where('request_type', 'apiCallCompleted')
            ->whereNotNull('call_details_general_call_id')
            ->where('call_details_general_call_id', '<>', '')
            ->where('call_details_disposition', 'ANSWER')
            ->where('call_details_billsec', '>', 0)
            ->where(function ($query): void {
                $query
                    ->whereNull('local_audio_relative_path')
                    ->orWhereNull('local_audio_expires_at')
                    ->orWhere('local_audio_expires_at', '<=', now())
                    ->orWhereNotNull('local_audio_last_error');
            })
            ->orderByDesc('call_details_start_time')
            ->orderByDesc('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($calls as $call) {
            $processed++;

            try {
                if ($audioCacheService->ensureLocalCopy($call) !== null) {
                    $downloaded++;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $this->info(sprintf(
            'Audio cache scan finished: processed %d call(s), cached %d item(s), pruned %d expired file(s).',
            $processed,
            $downloaded,
            $deletedExpired,
        ));

        return self::SUCCESS;
    }

    private function supportsLocalAudioCache(): bool
    {
        foreach ([
            'local_audio_relative_path',
            'local_audio_original_name',
            'local_audio_mime_type',
            'local_audio_size_bytes',
            'local_audio_downloaded_at',
            'local_audio_expires_at',
            'local_audio_last_error',
        ] as $column) {
            if (! Schema::hasColumn('binotel_api_call_completeds', $column)) {
                return false;
            }
        }

        return true;
    }
}
