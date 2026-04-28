<?php

namespace App\Services;

use App\Models\BinotelApiCallCompleted;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class BinotelCallRecordUrlResolver
{
    public const RETRY_MINUTES = 15;
    public const MAX_MISSING_URL_ATTEMPTS = 3;

    public function __construct(
        private readonly BinotelApi $binotelApi,
    ) {
    }

    public function resolve(BinotelApiCallCompleted $call, bool $forceRefresh = false): string
    {
        $audioUrl = trim((string) ($call->call_record_url ?? ''));
        $generalCallId = trim((string) $call->call_details_general_call_id);
        $hasUrl = $audioUrl !== '';
        $isExpired = $this->temporaryUrlIsExpired($audioUrl);
        $shouldRefresh = ($forceRefresh && $hasUrl) || ! $hasUrl || $isExpired;

        if (! $shouldRefresh) {
            return $audioUrl;
        }

        if ($generalCallId === '') {
            return $isExpired ? '' : $audioUrl;
        }

        if (! $hasUrl && ! $this->canRetryMissingUrl($call)) {
            return '';
        }

        $call->call_record_url_last_checked_at = now();
        $call->call_record_url_check_attempts = ((int) $call->call_record_url_check_attempts) + 1;

        try {
            $freshAudioUrl = $this->binotelApi->getCallRecordUrl($generalCallId);

            if (filled($freshAudioUrl)) {
                $audioUrl = trim((string) $freshAudioUrl);
                $call->call_record_url = $audioUrl;
            }
        } catch (Throwable $exception) {
            Log::channel((string) config('binotel.log_channel', 'stack'))
                ->warning('Failed to refresh Binotel call audio URL.', [
                    'call_id' => $call->id,
                    'general_call_id' => $generalCallId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
        }

        $call->save();

        return $this->temporaryUrlIsExpired($audioUrl) ? '' : $audioUrl;
    }

    public function canRetryMissingUrl(BinotelApiCallCompleted $call): bool
    {
        if (trim((string) ($call->call_record_url ?? '')) !== '') {
            return true;
        }

        $attempts = max(0, (int) ($call->call_record_url_check_attempts ?? 0));
        if ($attempts >= self::MAX_MISSING_URL_ATTEMPTS) {
            return false;
        }

        $lastCheckedAt = $call->call_record_url_last_checked_at;
        if ($lastCheckedAt === null) {
            return true;
        }

        return $lastCheckedAt->lessThanOrEqualTo(now()->subMinutes(self::RETRY_MINUTES));
    }

    public function temporaryUrlIsExpired(string $audioUrl): bool
    {
        $queryString = (string) (parse_url($audioUrl, PHP_URL_QUERY) ?: '');

        if ($queryString === '') {
            return false;
        }

        parse_str($queryString, $query);

        $signedAt = trim((string) ($query['X-Amz-Date'] ?? $query['x-amz-date'] ?? ''));
        $expiresIn = $query['X-Amz-Expires'] ?? $query['x-amz-expires'] ?? null;

        if ($signedAt === '' || ! is_numeric($expiresIn)) {
            return false;
        }

        try {
            $signedAtDate = CarbonImmutable::createFromFormat('Ymd\THis\Z', $signedAt, 'UTC');
        } catch (Throwable) {
            return false;
        }

        if (! $signedAtDate instanceof CarbonImmutable) {
            return false;
        }

        $expiresAt = $signedAtDate->addSeconds(max(0, (int) $expiresIn));

        return CarbonImmutable::now('UTC')->greaterThanOrEqualTo($expiresAt->subMinutes(5));
    }
}
