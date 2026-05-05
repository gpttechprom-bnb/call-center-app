<?php

namespace App\Support;

use App\Models\BinotelApiCallCompleted;
use App\Services\BinotelCallAudioCacheService;
use App\Services\CallCenterCrmCallStatusStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CallCenterPageData
{
    /**
     * @var array<string, array{checked_at:?string, phones:array<string, array{phone_exist:bool,manager:?string,case:?string}>}|null>
     */
    private array $crmDayCache = [];

    public function __construct(
        private readonly BinotelCallAudioCacheService $audioCacheService,
        private readonly CallCenterCrmCallStatusStore $crmCallStatusStore,
    ) {
    }

    /**
     * @param  array<string, mixed>  $endpoints
     * @return array<string, mixed>
     */
    public function build(
        CallCenterChecklistStore $checklistStore,
        CallCenterTranscriptionSettings $transcriptionSettings,
        array $endpoints = [],
        ?string $clientCallsVersion = null,
        bool $includeCalls = true,
    ): array {
        $transcriptionUploadLimitBytes = (int) config('call_center.transcription.max_upload_kb', 102400) * 1024;
        $callsVersion = $this->resolveCallsVersion();
        $callsIncluded = $includeCalls && $this->shouldIncludeCalls($callsVersion, $clientCallsVersion);

        return array_merge([
            'calls' => $callsIncluded ? $this->resolveCalls() : null,
            'callsIncluded' => $callsIncluded,
            'callsVersion' => $callsVersion,
            'checklists' => $checklistStore->all(),
            'defaultChecklistId' => $checklistStore->defaultId(),
            'transcriptionUploadLimitBytes' => $transcriptionUploadLimitBytes,
            'transcriptionSettings' => $transcriptionSettings->payload(),
        ], $endpoints);
    }

    public function callsVersion(): string
    {
        return $this->resolveCallsVersion();
    }

    /**
     * @return array<string, mixed>
     */
    public function callPayload(BinotelApiCallCompleted $call): array
    {
        return $this->mapBinotelCall($call);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCalls(): array
    {
        try {
            if (! Schema::hasTable('binotel_api_call_completeds')) {
                return [];
            }

            $calls = BinotelApiCallCompleted::query()
                ->with('feedback')
                ->orderByDesc('call_details_start_time')
                ->orderByDesc('id')
                ->get();

            if ($calls->isEmpty()) {
                return [];
            }

            return $calls
                ->map(fn (BinotelApiCallCompleted $call): array => $this->mapBinotelCall($call))
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function shouldIncludeCalls(string $serverCallsVersion, ?string $clientCallsVersion): bool
    {
        $normalizedClientVersion = trim((string) $clientCallsVersion);

        if ($normalizedClientVersion === '') {
            return true;
        }

        return ! hash_equals($serverCallsVersion, $normalizedClientVersion);
    }

    private function resolveCallsVersion(): string
    {
        try {
            $callAggregate = $this->aggregateTableSnapshot('binotel_api_call_completeds', [
                'id' => 'max_id',
                'updated_at' => 'max_updated_at',
                'call_record_url_last_checked_at' => 'max_record_url_checked_at',
                'local_audio_downloaded_at' => 'max_local_audio_downloaded_at',
                'local_audio_expires_at' => 'max_local_audio_expires_at',
                'alt_auto_started_at' => 'max_alt_auto_started_at',
                'alt_auto_finished_at' => 'max_alt_auto_finished_at',
                'crm_checked_at' => 'max_crm_checked_at',
            ]);

            if ($callAggregate === []) {
                return 'calls:none';
            }

            $feedbackAggregate = $this->aggregateTableSnapshot('binotel_call_feedbacks', [
                'updated_at' => 'max_updated_at',
                'transcribed_at' => 'max_transcribed_at',
                'evaluated_at' => 'max_evaluated_at',
            ]);

            $payload = [
                'calls' => $callAggregate,
                'feedback' => $feedbackAggregate,
            ];

            return sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            return 'calls:error';
        }
    }

    /**
     * @param  array<string, string>  $trackedColumns
     * @return array<string, mixed>
     */
    private function aggregateTableSnapshot(string $table, array $trackedColumns): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $availableColumns = array_flip(Schema::getColumnListing($table));

        $query = DB::table($table)->selectRaw('COUNT(*) as aggregate_count');

        foreach ($trackedColumns as $column => $alias) {
            if (! isset($availableColumns[$column])) {
                continue;
            }

            $query->selectRaw(sprintf('MAX(`%s`) as `%s`', $column, $alias));
        }

        $snapshot = $query->first();

        return $snapshot ? (array) $snapshot : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBinotelCall(BinotelApiCallCompleted $call): array
    {
        $startedAt = $this->resolveStartedAt($call);
        $durationSeconds = max(0, (int) ($call->call_details_billsec ?? 0));
        $employeeName = trim((string) ($call->call_details_employee_name ?? ''));
        $internalNumber = trim((string) ($call->call_details_internal_number ?? ''));
        $employeeDisplayName = trim((string) ($call->employee_display_name ?? ''));
        $employeeEmail = trim((string) ($call->call_details_employee_email ?? ''));
        $callerName = trim((string) ($call->call_details_customer_from_outside_name ?? ''));
        $trackingDomain = trim((string) ($call->call_details_call_tracking_domain ?? ''));
        $trackingSource = trim((string) ($call->call_details_call_tracking_utm_source ?? ''));
        $recordingUrl = trim((string) ($call->call_record_url ?? ''));
        $fallbackRecordingUrl = trim((string) ($call->call_details_link_to_call_record_in_my_business ?? ''));
        $overlayRecordingUrl = trim((string) ($call->call_details_link_to_call_record_overlay_in_my_business ?? ''));
        $recordingStatus = trim((string) ($call->call_details_recording_status ?? ''));
        $interactionNumber = (int) ($call->interaction_number ?? 0);
        $localAudio = $this->audioCacheService->cachedAudio($call);
        $localAudioUrl = $localAudio !== null
            ? route('api.alt.call-center.calls.audio-file', ['call' => $call->id])
            : null;
        $localAudioDownloadUrl = $localAudio !== null
            ? route('api.alt.call-center.calls.audio-file', ['call' => $call->id, 'download' => 1])
            : null;
        $feedback = $call->feedback;
        $score = $this->feedbackScore($feedback);
        $transcript = $this->feedbackTranscript($feedback);
        $scoreItems = $this->feedbackScoreItems($feedback);
        $evaluationMeta = $this->feedbackEvaluationMeta($feedback);
        $comparisonRuns = $this->feedbackComparisonRuns($feedback);
        $processedAt = $this->resolveProcessedAt($call, $feedback, $comparisonRuns);
        $feedbackSummary = trim((string) ($feedback->evaluation_summary ?? ''));
        $crmStatus = $this->resolveCrmStatus($call, $startedAt);
        $summaryBits = [];

        if ($call->call_details_disposition) {
            $summaryBits[] = 'Статус: '.(string) $call->call_details_disposition;
        }

        if ($call->call_details_billsec !== null) {
            $summaryBits[] = 'Тривалість: '.$this->formatDuration($durationSeconds);
        }

        if ($trackingDomain !== '') {
            $summaryBits[] = 'Домен: '.$trackingDomain;
        }

        if ($trackingSource !== '') {
            $summaryBits[] = 'Джерело: '.$trackingSource;
        }

        $noteBits = [];

        if ($call->call_details_general_call_id) {
            $noteBits[] = 'General Call ID: '.(string) $call->call_details_general_call_id;
        }

        if ($call->call_details_pbx_name) {
            $noteBits[] = 'PBX: '.(string) $call->call_details_pbx_name;
        }

        if ($call->call_details_call_tracking_utm_campaign) {
            $noteBits[] = 'Campaign: '.(string) $call->call_details_call_tracking_utm_campaign;
        }

        return [
            'id' => $call->id,
            'direction' => $this->resolveDirection($call),
            'caller' => trim((string) ($call->call_details_external_number ?? '')) ?: 'Невідомий номер',
            'callerMeta' => $callerName !== '' ? $callerName : $trackingDomain,
            'crmPhoneExists' => $crmStatus['crmPhoneExists'],
            'crmCase' => $crmStatus['crmCase'],
            'crmManager' => $crmStatus['crmManager'],
            'crmCheckedAt' => $crmStatus['crmCheckedAt'],
            'crmLookupError' => $crmStatus['crmLookupError'],
            'missingInCrm' => $crmStatus['missingInCrm'],
            'model' => $this->callModelLabel($evaluationMeta),
            'modelMeta' => $this->callModelMetaLabel($evaluationMeta),
            'modelSortValue' => $this->callModelSortValue($evaluationMeta),
            'employee' => $employeeDisplayName !== '' ? $employeeDisplayName : ($employeeName !== '' ? $employeeName : ($internalNumber !== '' ? 'Внутрішній номер '.$internalNumber : 'Не визначено')),
            'employeeMeta' => $employeeEmail !== '' ? $employeeEmail : $internalNumber,
            'duration' => $this->formatDuration($durationSeconds),
            'time' => $startedAt?->format('H:i') ?? '',
            'date' => $startedAt?->format('d.m.Y') ?? '',
            'processedTime' => $processedAt?->format('H:i') ?? '',
            'processedDate' => $processedAt?->format('d.m.Y') ?? '',
            'processedAt' => $processedAt?->toIso8601String(),
            'transcriptStatus' => $this->feedbackTranscriptStatus($feedback),
            'audioStatus' => $this->resolveAudioStatus($recordingUrl, $recordingStatus, $localAudio !== null),
            'binotelStatus' => $this->resolveBinotelStatus($call, $localAudio !== null),
            'score' => $score,
            'summary' => $feedbackSummary !== ''
                ? $feedbackSummary
                : ($summaryBits !== [] ? implode('. ', $summaryBits).'.' : 'Дзвінок отримано з Binotel webhook.'),
            'transcript' => $transcript !== '' ? $transcript : 'Транскрибація для цього дзвінка ще не виконувалася.',
            'note' => $noteBits !== [] ? implode('. ', $noteBits).'.' : 'Додаткові дані дзвінка доступні після отримання webhook.',
            'scoreItems' => $scoreItems,
            'evaluationMeta' => $evaluationMeta,
            'comparisonRuns' => $comparisonRuns,
            'audioUrl' => $localAudioUrl ?? ($recordingUrl !== '' ? $recordingUrl : null),
            'remoteAudioUrl' => $recordingUrl !== '' ? $recordingUrl : null,
            'audioFallbackUrl' => $fallbackRecordingUrl !== '' ? $fallbackRecordingUrl : null,
            'audioOverlayUrl' => $overlayRecordingUrl !== '' ? $overlayRecordingUrl : null,
            'generalCallId' => $call->call_details_general_call_id,
            'interactionCount' => is_numeric($call->interaction_count ?? null)
                ? max(0, (int) $call->interaction_count)
                : null,
            'interactionNumber' => $interactionNumber >= 0 ? $interactionNumber : null,
            'recordingStatus' => $recordingStatus,
            'localAudioUrl' => $localAudioUrl,
            'localAudioDownloadUrl' => $localAudioDownloadUrl,
            'localAudioFileName' => $localAudio['file_name'] ?? null,
            'localAudioDownloadedAt' => isset($localAudio['downloaded_at']) && $localAudio['downloaded_at'] !== null
                ? $localAudio['downloaded_at']->toIso8601String()
                : null,
            'localAudioExpiresAt' => isset($localAudio['expires_at']) && $localAudio['expires_at'] !== null
                ? $localAudio['expires_at']->toIso8601String()
                : null,
            'localAudioSize' => $this->audioCacheService->formatFileSize(
                isset($localAudio['size_bytes']) && is_int($localAudio['size_bytes']) ? $localAudio['size_bytes'] : null
            ),
            'localAudioStatus' => $localAudio !== null ? 'Локальний файл готовий' : 'Локальний файл ще не завантажено',
            'localAudioError' => trim((string) ($call->local_audio_last_error ?? '')) ?: null,
            'altAutoStatus' => trim((string) ($call->alt_auto_status ?? '')) ?: null,
            'altAutoError' => trim((string) ($call->alt_auto_error ?? '')) ?: null,
        ];
    }

    /**
     * @return array{crmPhoneExists:?bool,crmCase:?string,crmManager:?string,crmCheckedAt:?string,crmLookupError:?string,missingInCrm:?bool}
     */
    private function resolveCrmStatus(BinotelApiCallCompleted $call, ?CarbonImmutable $startedAt): array
    {
        $status = $this->crmCallStatusStore->statusPayload($call);

        if ($this->hasResolvedCrmStatus($status)) {
            return $status;
        }

        $phone = $this->crmCallStatusStore->phoneForCall($call);
        $dayKey = $startedAt?->format('Y-m-d') ?? '';

        if ($phone === '' || $dayKey === '') {
            return $status;
        }

        $cachedDay = $this->crmCacheForDay($dayKey);
        $cachedPhone = is_array($cachedDay['phones'] ?? null) ? ($cachedDay['phones'][$phone] ?? null) : null;

        if (! is_array($cachedPhone)) {
            return $status;
        }

        $phoneExists = (bool) ($cachedPhone['phone_exist'] ?? false);

        return [
            'crmPhoneExists' => $phoneExists,
            'crmCase' => isset($cachedPhone['case']) ? trim((string) $cachedPhone['case']) ?: null : null,
            'crmManager' => isset($cachedPhone['manager']) ? trim((string) $cachedPhone['manager']) ?: null : null,
            'crmCheckedAt' => isset($cachedDay['checked_at']) ? trim((string) $cachedDay['checked_at']) ?: null : null,
            'crmLookupError' => null,
            'missingInCrm' => ! $phoneExists,
        ];
    }

    /**
     * @param  array{crmPhoneExists:?bool,crmCase:?string,crmManager:?string,crmCheckedAt:?string,crmLookupError:?string,missingInCrm:?bool}  $status
     */
    private function hasResolvedCrmStatus(array $status): bool
    {
        return $status['crmPhoneExists'] !== null
            || $status['crmCase'] !== null
            || $status['crmManager'] !== null
            || $status['crmCheckedAt'] !== null
            || $status['crmLookupError'] !== null
            || $status['missingInCrm'] !== null;
    }

    /**
     * @return array{checked_at:?string, phones:array<string, array{phone_exist:bool,manager:?string,case:?string}>}|null
     */
    private function crmCacheForDay(string $dayKey): ?array
    {
        if (array_key_exists($dayKey, $this->crmDayCache)) {
            return $this->crmDayCache[$dayKey];
        }

        $path = 'call-center/alt/automation/calendar-crm/'.$dayKey.'.json';

        try {
            if (! Storage::disk('local')->exists($path)) {
                return $this->crmDayCache[$dayKey] = null;
            }

            $payload = json_decode((string) Storage::disk('local')->get($path), true);
            if (! is_array($payload)) {
                return $this->crmDayCache[$dayKey] = null;
            }

            $phones = $this->extractPhonesFromCrmCachePayload($payload);
            if ($phones === []) {
                return $this->crmDayCache[$dayKey] = null;
            }

            return $this->crmDayCache[$dayKey] = [
                'checked_at' => isset($payload['checked_at']) ? trim((string) $payload['checked_at']) ?: null : null,
                'phones' => $phones,
            ];
        } catch (Throwable) {
            return $this->crmDayCache[$dayKey] = null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{phone_exist:bool,manager:?string,case:?string}>
     */
    private function extractPhonesFromCrmCachePayload(array $payload): array
    {
        $candidates = [];

        if (is_array($payload['phones'] ?? null)) {
            $candidates[] = $payload['phones'];
        }

        if (is_array($payload['results'] ?? null)) {
            $candidates[] = $payload['results'];
        }

        $candidates[] = $payload;

        foreach ($candidates as $candidate) {
            $normalized = [];

            foreach ($candidate as $phone => $item) {
                if (! is_string($phone) || trim($phone) === '' || ! is_array($item)) {
                    continue;
                }

                if (
                    ! array_key_exists('phone_exist', $item)
                    && ! array_key_exists('manager', $item)
                    && ! array_key_exists('case', $item)
                ) {
                    continue;
                }

                $normalized[$phone] = [
                    'phone_exist' => (bool) ($item['phone_exist'] ?? false),
                    'manager' => isset($item['manager']) ? trim((string) $item['manager']) ?: null : null,
                    'case' => isset($item['case']) ? trim((string) $item['case']) ?: null : null,
                ];
            }

            if ($normalized !== []) {
                return $normalized;
            }
        }

        return [];
    }

    private function resolveBinotelStatus(BinotelApiCallCompleted $call, bool $hasLocalAudio = false): string
    {
        if ($hasLocalAudio) {
            return 'Локально';
        }

        $recordingUrl = trim((string) ($call->call_record_url ?? ''));

        if ($recordingUrl !== '') {
            return 'Успіх';
        }

        if ($call->call_record_url_last_checked_at !== null) {
            return 'Помилка';
        }

        return '—';
    }

    private function resolveStartedAt(BinotelApiCallCompleted $call): ?CarbonImmutable
    {
        $timestamp = $call->call_details_start_time;

        if (! is_int($timestamp) || $timestamp <= 0) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($timestamp, (string) config('binotel.timezone', 'Europe/Kyiv'));
    }

    private function resolveDirection(BinotelApiCallCompleted $call): string
    {
        $normalizedDirection = trim((string) ($call->direction_key ?? ''));
        if (in_array($normalizedDirection, ['in', 'out'], true)) {
            return $normalizedDirection;
        }

        $callType = trim((string) ($call->call_details_call_type ?? ''));

        return in_array($callType, ['0', 'in', 'incoming'], true) ? 'in' : 'out';
    }

    /**
     * @param  mixed  $feedback
     * @param  array<int, array<string, mixed>>  $comparisonRuns
     */
    private function resolveProcessedAt(BinotelApiCallCompleted $call, $feedback, array $comparisonRuns = []): ?CarbonImmutable
    {
        for ($index = count($comparisonRuns) - 1; $index >= 0; $index--) {
            $run = $comparisonRuns[$index] ?? [];

            foreach (['evaluatedAt', 'transcribedAt', 'createdAt'] as $field) {
                $resolved = $this->normalizeDateTimeValue($run[$field] ?? null);

                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        foreach ([
            $feedback?->evaluated_at ?? null,
            $feedback?->transcribed_at ?? null,
            $feedback?->updated_at ?? null,
            $call->alt_auto_finished_at ?? null,
        ] as $candidate) {
            $resolved = $this->normalizeDateTimeValue($candidate);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function normalizeDateTimeValue(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof CarbonImmutable) {
                return $value->setTimezone((string) config('binotel.timezone', 'Europe/Kyiv'));
            }

            return CarbonImmutable::parse((string) $value)
                ->setTimezone((string) config('binotel.timezone', 'Europe/Kyiv'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  mixed  $feedback
     */
    private function feedbackTranscriptStatus($feedback): string
    {
        $status = trim((string) ($feedback->transcription_status ?? ''));

        if ($status === 'completed') {
            return 'Транскрибація готова';
        }

        if ($status === 'failed') {
            return 'Транскрибація завершилася помилкою';
        }

        if ($status !== '') {
            return 'Транскрибація в роботі';
        }

        return 'Транскрибація ще не запускалась';
    }

    /**
     * @param  mixed  $feedback
     */
    private function feedbackTranscript($feedback): string
    {
        foreach (['transcription_dialogue_text', 'transcription_formatted_text', 'transcription_text'] as $field) {
            $value = trim((string) ($feedback->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  mixed  $feedback
     */
    private function feedbackScore($feedback): ?int
    {
        if ($feedback === null) {
            return null;
        }

        if (is_numeric($feedback->evaluation_score_percent)) {
            return max(0, min(100, (int) $feedback->evaluation_score_percent));
        }

        if (is_numeric($feedback->evaluation_score) && is_numeric($feedback->evaluation_total_points) && (int) $feedback->evaluation_total_points > 0) {
            return max(0, min(100, (int) round(((int) $feedback->evaluation_score / (int) $feedback->evaluation_total_points) * 100)));
        }

        return null;
    }

    /**
     * @param  mixed  $feedback
     * @return array<int, array<string, mixed>>
     */
    private function feedbackScoreItems($feedback): array
    {
        if ($feedback === null) {
            return [];
        }

        $payload = is_array($feedback->evaluation_payload ?? null)
            ? $feedback->evaluation_payload
            : [];
        $items = is_array($payload['items'] ?? null)
            ? $payload['items']
            : [];

        if ($items === []) {
            return [];
        }

        $mapped = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['label'] ?? $item['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $mapped[] = [
                'title' => $title,
                'text' => trim((string) ($item['comment'] ?? $item['answer'] ?? '')),
                'score' => $this->feedbackItemScore($item),
                'maxPoints' => $this->feedbackItemMaxPoints($item),
                'percentage' => $this->feedbackItemPercent($item),
            ];
        }

        return $mapped;
    }

    /**
     * @param  mixed  $feedback
     * @return array<string, mixed>
     */
    private function feedbackEvaluationMeta($feedback): array
    {
        if ($feedback === null) {
            return [
                'provider' => '',
                'providerLabel' => '',
                'model' => '',
                'title' => '',
                'summary' => '',
                'params' => [],
            ];
        }

        $payload = is_array($feedback->evaluation_payload ?? null)
            ? $feedback->evaluation_payload
            : [];
        $provider = trim((string) ($payload['provider'] ?? $feedback->evaluation_provider ?? ''));
        $providerLabel = $this->formatEvaluationProviderLabel($provider);
        $model = trim((string) ($payload['model'] ?? $feedback->evaluation_model ?? ''));
        $params = $this->normalizeEvaluationModelParams($payload);
        $title = $model !== ''
            ? ($providerLabel !== '' ? $model.' через '.$providerLabel : $model)
            : $providerLabel;

        return [
            'provider' => $provider,
            'providerLabel' => $providerLabel,
            'model' => $model,
            'title' => $title,
            'summary' => $this->formatEvaluationModelSummary($model, $providerLabel, $params),
            'params' => $params,
        ];
    }

    /**
     * @param  mixed  $feedback
     * @return array<int, array<string, mixed>>
     */
    private function feedbackComparisonRuns($feedback): array
    {
        if ($feedback === null) {
            return [];
        }

        $rawRuns = is_array($feedback->comparison_runs ?? null)
            ? array_values(array_filter($feedback->comparison_runs, 'is_array'))
            : [];

        if ($rawRuns === [] && $this->hasLegacyFeedbackData($feedback)) {
            $rawRuns[] = [
                'id' => 'legacy',
                'order' => 1,
                'created_at' => optional($feedback->created_at)->toIso8601String(),
                'updated_at' => optional($feedback->updated_at)->toIso8601String(),
                'source_context' => 'legacy',
                'transcription_status' => $feedback->transcription_status,
                'transcription_source_type' => $feedback->transcription_source_type,
                'transcription_source_name' => $feedback->transcription_source_name,
                'transcription_source_relative_path' => $feedback->transcription_source_relative_path,
                'transcription_storage_run_directory' => $feedback->transcription_storage_run_directory,
                'transcription_language' => $feedback->transcription_language,
                'transcription_model' => $feedback->transcription_model,
                'transcription_text' => $feedback->transcription_text,
                'transcription_dialogue_text' => $feedback->transcription_dialogue_text,
                'transcription_formatted_text' => $feedback->transcription_formatted_text,
                'transcription_payload' => $feedback->transcription_payload,
                'transcribed_at' => optional($feedback->transcribed_at)->toIso8601String(),
                'evaluation_status' => $feedback->evaluation_status,
                'last_evaluation_job_id' => $feedback->last_evaluation_job_id,
                'evaluation_checklist_id' => $feedback->evaluation_checklist_id,
                'evaluation_checklist_name' => $feedback->evaluation_checklist_name,
                'evaluation_score' => $feedback->evaluation_score,
                'evaluation_total_points' => $feedback->evaluation_total_points,
                'evaluation_score_percent' => $feedback->evaluation_score_percent,
                'evaluation_summary' => $feedback->evaluation_summary,
                'evaluation_strong_side' => $feedback->evaluation_strong_side,
                'evaluation_focus' => $feedback->evaluation_focus,
                'evaluation_provider' => $feedback->evaluation_provider,
                'evaluation_model' => $feedback->evaluation_model,
                'evaluation_payload' => $feedback->evaluation_payload,
                'evaluation_requested_at' => optional($feedback->evaluation_requested_at)->toIso8601String(),
                'evaluated_at' => optional($feedback->evaluated_at)->toIso8601String(),
                'error_message' => $feedback->error_message,
            ];
        }

        if ($rawRuns === []) {
            return [];
        }

        usort($rawRuns, static fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));
        $mapped = [];

        foreach ($rawRuns as $index => $run) {
            $evaluationMeta = $this->feedbackEvaluationMeta((object) [
                'evaluation_payload' => $run['evaluation_payload'] ?? null,
                'evaluation_provider' => $run['evaluation_provider'] ?? null,
                'evaluation_model' => $run['evaluation_model'] ?? null,
            ]);
            $score = $this->feedbackRunScore($run);
            $scoreItems = $this->feedbackRunScoreItems($run);
            $transcript = $this->feedbackRunTranscript($run);
            $transcriptStatus = $this->feedbackRunTranscriptStatus($run);

            $mapped[] = [
                'id' => trim((string) ($run['id'] ?? '')) ?: 'run-'.($index + 1),
                'order' => max(1, (int) ($run['order'] ?? ($index + 1))),
                'sourceContext' => trim((string) ($run['source_context'] ?? '')),
                'transcript' => $transcript,
                'transcriptStatus' => $transcriptStatus,
                'score' => $score,
                'scoreItems' => $scoreItems,
                'evaluationMeta' => $evaluationMeta,
                'model' => $this->callModelLabel($evaluationMeta),
                'modelMeta' => $this->callModelMetaLabel($evaluationMeta),
                'summary' => trim((string) ($run['evaluation_summary'] ?? '')),
                'createdAt' => trim((string) ($run['created_at'] ?? '')) ?: null,
                'transcribedAt' => trim((string) ($run['transcribed_at'] ?? '')) ?: null,
                'evaluatedAt' => trim((string) ($run['evaluated_at'] ?? '')) ?: null,
            ];
        }

        return $mapped;
    }

    private function hasLegacyFeedbackData($feedback): bool
    {
        foreach ([
            'transcription_text',
            'transcription_dialogue_text',
            'transcription_formatted_text',
            'transcription_model',
            'evaluation_model',
            'evaluation_score',
            'evaluation_score_percent',
            'evaluation_payload',
        ] as $field) {
            if (! blank($feedback->{$field} ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function feedbackRunTranscript(array $run): string
    {
        foreach (['transcription_dialogue_text', 'transcription_formatted_text', 'transcription_text'] as $field) {
            $value = trim((string) ($run[$field] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function feedbackRunTranscriptStatus(array $run): string
    {
        $status = trim((string) ($run['transcription_status'] ?? ''));

        if ($status === 'completed') {
            return 'Транскрибація готова';
        }

        if ($status === 'failed') {
            return 'Транскрибація завершилася помилкою';
        }

        if ($status !== '') {
            return 'Транскрибація в роботі';
        }

        return 'Транскрибація ще не запускалась';
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function feedbackRunScore(array $run): ?int
    {
        if (is_numeric($run['evaluation_score_percent'] ?? null)) {
            return max(0, min(100, (int) $run['evaluation_score_percent']));
        }

        if (is_numeric($run['evaluation_score'] ?? null) && is_numeric($run['evaluation_total_points'] ?? null) && (int) $run['evaluation_total_points'] > 0) {
            return max(0, min(100, (int) round(((int) $run['evaluation_score'] / (int) $run['evaluation_total_points']) * 100)));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $run
     * @return array<int, array<string, mixed>>
     */
    private function feedbackRunScoreItems(array $run): array
    {
        $payload = is_array($run['evaluation_payload'] ?? null)
            ? $run['evaluation_payload']
            : [];
        $items = is_array($payload['items'] ?? null)
            ? $payload['items']
            : [];

        if ($items === []) {
            return [];
        }

        $mapped = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['label'] ?? $item['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $mapped[] = [
                'title' => $title,
                'text' => trim((string) ($item['comment'] ?? $item['answer'] ?? '')),
                'score' => $this->feedbackItemScore($item),
                'maxPoints' => $this->feedbackItemMaxPoints($item),
                'percentage' => $this->feedbackItemPercent($item),
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, scalar>
     */
    private function normalizeEvaluationModelParams(array $payload): array
    {
        $rawParams = is_array($payload['modelParams'] ?? null)
            ? $payload['modelParams']
            : [];

        if ($rawParams === []) {
            return [];
        }

        $params = [];

        foreach ([
            'temperature',
            'num_ctx',
            'top_k',
            'top_p',
            'repeat_penalty',
            'num_predict',
            'seed',
            'timeout_seconds',
        ] as $key) {
            if (! array_key_exists($key, $rawParams)) {
                continue;
            }

            $value = $rawParams[$key];

            if (! is_scalar($value)) {
                continue;
            }

            $stringValue = trim((string) $value);

            if ($stringValue === '') {
                continue;
            }

            $params[$key] = $value;
        }

        return $params;
    }

    /**
     * @param  array<string, scalar>  $params
     */
    private function formatEvaluationModelSummary(string $model, string $providerLabel, array $params): string
    {
        $parts = [];

        if ($model !== '') {
            $parts[] = $providerLabel !== ''
                ? 'LLM: '.$model.' через '.$providerLabel
                : 'LLM: '.$model;
        } elseif ($providerLabel !== '') {
            $parts[] = 'LLM: '.$providerLabel;
        }

        $paramLabels = [
            'temperature' => 'temp',
            'num_ctx' => 'ctx',
            'top_k' => 'top_k',
            'top_p' => 'top_p',
            'repeat_penalty' => 'repeat_penalty',
            'num_predict' => 'num_predict',
            'seed' => 'seed',
            'timeout_seconds' => 'timeout',
        ];
        $paramParts = [];

        foreach ($paramLabels as $key => $label) {
            if (! array_key_exists($key, $params)) {
                continue;
            }

            $suffix = $key === 'timeout_seconds' ? ' c' : '';
            $paramParts[] = $label.' '.$this->formatEvaluationParamValue($params[$key]).$suffix;
        }

        if ($paramParts !== []) {
            $parts[] = 'Параметри: '.implode(', ', $paramParts);
        }

        if ($parts === []) {
            return '';
        }

        return implode('. ', $parts).'.';
    }

    private function formatEvaluationProviderLabel(string $provider): string
    {
        $normalized = mb_strtolower(trim($provider), 'UTF-8');

        return match ($normalized) {
            'ollama' => 'Ollama',
            default => $provider !== '' ? $provider : '',
        };
    }

    /**
     * @param  array<string, mixed>  $evaluationMeta
     */
    private function callModelLabel(array $evaluationMeta): string
    {
        return trim((string) ($evaluationMeta['model'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $evaluationMeta
     */
    private function callModelMetaLabel(array $evaluationMeta): string
    {
        $providerLabel = trim((string) ($evaluationMeta['providerLabel'] ?? ''));
        $model = $this->callModelLabel($evaluationMeta);

        if ($providerLabel === '' || $providerLabel === $model) {
            return '';
        }

        return $providerLabel;
    }

    /**
     * @param  array<string, mixed>  $evaluationMeta
     */
    private function callModelSortValue(array $evaluationMeta): float
    {
        $model = $this->callModelLabel($evaluationMeta);

        if ($model === '') {
            return -1;
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*b\b/i', $model, $matches) === 1) {
            return (float) $matches[1];
        }

        return 0;
    }

    private function formatEvaluationParamValue(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            $formatted = number_format($value, 4, '.', '');

            return rtrim(rtrim($formatted, '0'), '.');
        }

        if (is_numeric($value)) {
            $numeric = (string) $value;

            if (str_contains($numeric, '.')) {
                return rtrim(rtrim($numeric, '0'), '.');
            }

            return $numeric;
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function feedbackItemScore(array $item): ?int
    {
        return is_numeric($item['score'] ?? null) ? (int) $item['score'] : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function feedbackItemMaxPoints(array $item): ?int
    {
        return is_numeric($item['max_points'] ?? null) ? (int) $item['max_points'] : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function feedbackItemPercent(array $item): ?int
    {
        if (is_numeric($item['percentage'] ?? null)) {
            return max(0, min(100, (int) $item['percentage']));
        }

        if (is_numeric($item['score'] ?? null) && is_numeric($item['max_points'] ?? null) && (int) $item['max_points'] > 0) {
            return max(0, min(100, (int) round(((int) $item['score'] / (int) $item['max_points']) * 100)));
        }

        return null;
    }

    private function resolveAudioStatus(string $recordingUrl, string $recordingStatus, bool $hasLocalAudio = false): string
    {
        if ($hasLocalAudio) {
            return 'Локальний файл готовий';
        }

        if ($recordingUrl !== '') {
            return 'Запис доступний';
        }

        if ($recordingStatus === 'uploading') {
            return 'Запис завантажується';
        }

        return 'Запис недоступний';
    }

    private function formatDuration(int $durationSeconds): string
    {
        $minutes = intdiv($durationSeconds, 60);
        $seconds = $durationSeconds % 60;

        return str_pad((string) $minutes, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $seconds, 2, '0', STR_PAD_LEFT);
    }
}
