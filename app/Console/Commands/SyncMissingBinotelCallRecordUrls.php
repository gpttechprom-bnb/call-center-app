<?php

namespace App\Console\Commands;

use App\Models\BinotelApiCallCompleted;
use App\Services\AltCallCenterAutomationDispatcher;
use App\Services\BinotelApi;
use App\Services\BinotelCallRecordUrlResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

class SyncMissingBinotelCallRecordUrls extends Command
{
    protected $signature = 'binotel:sync-call-record-urls
        {--limit=100 : Maximum calls to process in one run}
        {--retry-minutes=15 : Minimum minutes before retrying a call that did not return a URL}
        {--refresh-existing : Refresh rows that already have call_record_url}';

    protected $description = 'Fetch direct Binotel recording URLs by call_details_general_call_id and store them in call_record_url';

    public function handle(BinotelApi $binotelApi, AltCallCenterAutomationDispatcher $automationDispatcher): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $retryMinutes = max(1, (int) $this->option('retry-minutes'));
        $refreshExisting = (bool) $this->option('refresh-existing');

        if (blank(config('binotel.api.key')) || blank(config('binotel.api.secret'))) {
            $this->warn('Binotel API credentials are missing, skipping sync.');

            return self::SUCCESS;
        }

        $calls = BinotelApiCallCompleted::query()
            ->where('request_type', 'apiCallCompleted')
            ->whereNotNull('call_details_general_call_id')
            ->where(function ($query): void {
                $query
                    ->whereNull('call_record_url_check_attempts')
                    ->orWhere('call_record_url_check_attempts', '<', BinotelCallRecordUrlResolver::MAX_MISSING_URL_ATTEMPTS);
            })
            ->where(function ($query) use ($retryMinutes): void {
                $query
                    ->whereNull('call_record_url_last_checked_at')
                    ->orWhere('call_record_url_last_checked_at', '<=', now()->subMinutes($retryMinutes));
            });

        if (! $refreshExisting) {
            $calls->whereNull('call_record_url');
        }

        $calls = $calls
            ->orderBy('call_record_url_last_checked_at')
            ->orderByDesc('call_details_start_time')
            ->limit($limit)
            ->get();

        $processed = 0;
        $updated = 0;

        foreach ($calls as $call) {
            $generalCallId = trim((string) $call->call_details_general_call_id);

            if ($generalCallId === '') {
                continue;
            }

            $processed++;

            $call->call_record_url_last_checked_at = now();
            $call->call_record_url_check_attempts = ((int) $call->call_record_url_check_attempts) + 1;

            try {
                $url = $binotelApi->getCallRecordUrl($generalCallId);

                if (filled($url)) {
                    $call->call_record_url = $url;
                    $updated++;
                }
            } catch (Throwable $exception) {
                $this->logger()->warning('Failed to sync Binotel call_record_url.', [
                    'general_call_id' => $generalCallId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }

            $call->save();
        }

        $this->info(sprintf(
            'Processed %d call(s), updated %d call_record_url value(s).',
            $processed,
            $updated,
        ));

        if ($updated > 0) {
            try {
                $automationDispatcher->dispatchIfPlaying();
            } catch (Throwable $exception) {
                $this->logger()->warning('Failed to dispatch alt call-center automation worker after call_record_url sync.', [
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        }

        return self::SUCCESS;
    }

    private function logger(): LoggerInterface
    {
        return Log::channel((string) config('binotel.log_channel', 'stack'));
    }
}
