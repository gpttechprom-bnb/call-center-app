<?php

namespace App\Services;

use App\Models\BinotelApiCallCompleted;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

class BinotelWebhookService
{
    public function __construct(
        private readonly BinotelApiCallCompletedStore $apiCallCompletedStore,
        private readonly BinotelApi $binotelApi,
        private readonly AltCallCenterAutomationDispatcher $automationDispatcher,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, string $sourceIp, string $sourceHost): void
    {
        $requestType = trim((string) ($payload['requestType'] ?? ''));

        match ($requestType) {
            'answeredTheCall' => $this->logStructuredWebhook('answeredTheCall', $payload, $sourceIp, $sourceHost),
            'apiCallCompleted' => $this->handleApiCallCompleted($payload, $sourceIp, $sourceHost),
            'apiCallSettings' => $this->logStructuredWebhook('apiCallSettings', $payload, $sourceIp, $sourceHost),
            default => $this->logStructuredWebhook($requestType !== '' ? $requestType : 'unknown', $payload, $sourceIp, $sourceHost),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleApiCallCompleted(array $payload, string $sourceIp, string $sourceHost): void
    {
        $record = $this->apiCallCompletedStore->store($payload);
        $this->enrichCallRecordUrl($record);
        $this->dispatchAutomationWorker();

        $this->logStructuredWebhook('apiCallCompleted', $payload, $sourceIp, $sourceHost);
    }

    private function dispatchAutomationWorker(): void
    {
        try {
            $this->automationDispatcher->dispatchIfPlaying();
        } catch (Throwable $exception) {
            $this->logger()->warning('Failed to dispatch alt call-center automation worker.', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
    }

    private function enrichCallRecordUrl(?BinotelApiCallCompleted $record): void
    {
        if (! $record instanceof BinotelApiCallCompleted) {
            return;
        }

        if (filled($record->call_record_url)) {
            return;
        }

        $generalCallId = trim((string) $record->call_details_general_call_id);

        if ($generalCallId === '') {
            return;
        }

        $callRecordUrl = $this->binotelApi->getCallRecordUrl($generalCallId);

        if ($callRecordUrl === null) {
            return;
        }

        $record->forceFill([
            'call_record_url' => $callRecordUrl,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logStructuredWebhook(
        string $requestType,
        array $payload,
        string $sourceIp,
        string $sourceHost
    ): void {
        $this->logger()->info('Binotel webhook received.', $this->context(
            $payload,
            $sourceIp,
            $sourceHost,
            $requestType
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function context(
        array $payload,
        string $sourceIp,
        string $sourceHost,
        ?string $requestType = null
    ): array {
        $resolvedRequestType = $requestType ?? trim((string) ($payload['requestType'] ?? ''));

        return [
            'requestType' => $resolvedRequestType,
            'sourceIp' => $sourceIp,
            'sourceHost' => $sourceHost,
            'callId' => $this->extractCallId($payload),
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractCallId(array $payload): ?string
    {
        $candidates = [
            $payload['generalCallID'] ?? null,
            $payload['callID'] ?? null,
            data_get($payload, 'callDetails.generalCallID'),
            data_get($payload, 'callDetails.callID'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function logger(): LoggerInterface
    {
        return Log::channel((string) config('binotel.log_channel', 'stack'));
    }
}
