<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BinotelApiCallCompleted;
use App\Services\AltCallCenterAutomationDispatcher;
use App\Services\AltCallCenterAutomationStopper;
use App\Services\BinotelCallAudioCacheService;
use App\Services\BinotelCallRecordUrlResolver;
use App\Support\AltCallCenterAutomationStore;
use App\Support\AltCallCenterAutomationWindow;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class AltCallCenterAutomationController extends Controller
{
    public function show(
        AltCallCenterAutomationStore $automationStore,
        AltCallCenterAutomationWindow $automationWindow,
    ): JsonResponse {
        return response()->json([
            'automation' => $automationStore->stateWithWindow($automationWindow),
        ]);
    }

    public function play(
        AltCallCenterAutomationStore $automationStore,
        AltCallCenterAutomationDispatcher $dispatcher,
        AltCallCenterAutomationWindow $automationWindow,
    ): JsonResponse {
        $state = $automationStore->play();

        try {
            $dispatcher->dispatch();
            $state = $automationStore->stateWithWindow($automationWindow);
        } catch (RuntimeException $exception) {
            $automationStore->markError($exception->getMessage());

            return response()->json([
                'message' => $exception->getMessage(),
                'automation' => $automationStore->stateWithWindow($automationWindow),
            ], 500);
        }

        return response()->json([
            'message' => (string) ($state['last_message'] ?? 'Фонову чергу увімкнено.'),
            'automation' => $state,
        ]);
    }

    public function pause(
        AltCallCenterAutomationStopper $automationStopper,
        AltCallCenterAutomationWindow $automationWindow,
    ): JsonResponse
    {
        $state = $automationStopper->pauseAndStop(true, $automationWindow->isOpen());

        return response()->json([
            'message' => 'Фонову чергу поставлено на паузу. Поточний фоновий процес зупинено та очищено.',
            'automation' => array_merge($state, [
                'window' => $automationWindow->state(),
            ]),
        ]);
    }

    public function forceCall(
        BinotelApiCallCompleted $call,
        AltCallCenterAutomationStore $automationStore,
        AltCallCenterAutomationDispatcher $dispatcher,
    ): JsonResponse {
        $call->loadMissing('feedback');

        $generalCallId = trim((string) $call->call_details_general_call_id);
        if ($generalCallId === '') {
            return response()->json([
                'message' => 'У цього дзвінка немає General Call ID, тому примусовий запуск недоступний.',
            ], 422);
        }

        $call->forceFill([
            'alt_auto_status' => 'pending',
            'alt_auto_error' => null,
            'alt_auto_started_at' => null,
            'alt_auto_finished_at' => null,
            'call_record_url_check_attempts' => 0,
            'call_record_url_last_checked_at' => null,
        ])->save();

        $state = $automationStore->state();
        $workerAlive = (bool) ($state['worker_alive'] ?? false);
        $manualMessage = 'Примусовий запуск для дзвінка '.$generalCallId.' поставлено в ручний пріоритет.';

        $automationStore->scheduleRetry(
            $generalCallId,
            1,
            999,
            now()->toIso8601String(),
            $manualMessage,
            'transcription',
        );

        if ($workerAlive && ! $automationStore->isPaused()) {
            $automationStore->markMessage(
                $manualMessage.' Активний воркер візьме його наступним після поточного дзвінка.',
                'running',
                'waiting',
            );

            return response()->json([
                'message' => 'Дзвінок поставлено в пріоритет активному воркеру. Він піде наступним у черзі.',
                'call' => [
                    'id' => $call->id,
                    'generalCallId' => $generalCallId,
                    'altAutoStatus' => 'pending',
                ],
            ], 202);
        }

        try {
            $dispatcher->dispatchSpecificCall($generalCallId);
        } catch (RuntimeException $exception) {
            $call->forceFill([
                'alt_auto_status' => 'failed',
                'alt_auto_error' => $exception->getMessage(),
                'alt_auto_finished_at' => now(),
            ])->save();

            return response()->json([
                'message' => $exception->getMessage(),
            ], 500);
        }

        $automationStore->markMessage(
            $manualMessage.' Запущено окремий одноразовий воркер поза графіком роботи.',
            'running',
            'transcription',
        );

        return response()->json([
            'message' => 'Запущено примусову обробку вибраного дзвінка поза графіком роботи.',
            'call' => [
                'id' => $call->id,
                'generalCallId' => $generalCallId,
                'altAutoStatus' => 'pending',
            ],
        ], 202);
    }

    public function updateSettings(
        Request $request,
        AltCallCenterAutomationStore $automationStore,
        AltCallCenterAutomationWindow $automationWindow,
        AltCallCenterAutomationDispatcher $dispatcher,
        AltCallCenterAutomationStopper $automationStopper,
    ): JsonResponse
    {
        $validated = $request->validate([
            'ai_rewrite' => ['nullable', 'array'],
            'ai_rewrite.enabled' => ['nullable', 'boolean'],
            'ai_rewrite.model' => ['nullable', 'string', 'max:255'],
            'ai_rewrite.prompt' => ['nullable', 'string', 'max:10000'],
            'ai_rewrite.prompt_by_model' => ['nullable', 'array'],
            'ai_rewrite.prompt_by_model.*' => ['nullable', 'string', 'max:10000'],
            'ai_rewrite.generation_settings' => ['nullable', 'array'],
            'ai_rewrite.generation_settings_by_model' => ['nullable', 'array'],
            'evaluation' => ['nullable', 'array'],
            'evaluation.enabled' => ['nullable', 'boolean'],
            'evaluation.checklist_routing_rules' => ['nullable', 'array'],
            'evaluation.checklist_routing_rules.*.checklist_id' => ['required_with:evaluation.checklist_routing_rules', 'string', 'max:120'],
            'evaluation.checklist_routing_rules.*.interaction_number' => ['required_with:evaluation.checklist_routing_rules', 'integer', 'between:1,20'],
            'evaluation.checklist_routing_rules.*.direction' => ['nullable', 'string', 'in:in,out,any'],
            'evaluation.model' => ['nullable', 'string', 'max:255'],
            'evaluation.evaluation_scenario' => ['nullable', 'string', 'in:stateless_single_item,sequential_chat,batch_single_prompt'],
            'evaluation.system_prompt' => ['nullable', 'string', 'max:20000'],
            'evaluation.system_prompt_by_model' => ['nullable', 'array'],
            'evaluation.system_prompt_by_model.*' => ['nullable', 'string', 'max:20000'],
            'evaluation.generation_settings' => ['nullable', 'array'],
            'evaluation.generation_settings_by_model' => ['nullable', 'array'],
            'window' => ['nullable', 'array'],
            'window.start_time' => ['nullable', 'date_format:H:i'],
            'window.end_time' => ['nullable', 'date_format:H:i'],
            'window.weekly_schedule' => ['nullable', 'array'],
            'window.weekly_schedule.*.day' => ['required_with:window.weekly_schedule', 'integer', 'between:1,7'],
            'window.weekly_schedule.*.start_time' => ['nullable', 'date_format:H:i'],
            'window.weekly_schedule.*.end_time' => ['nullable', 'date_format:H:i'],
            'window.weekly_schedule.*.is_day_off' => ['nullable', 'boolean'],
        ]);

        $automationStore->saveProcessingSettings(
            $this->normalizeProcessingBlock($validated['ai_rewrite'] ?? [], 'prompt'),
            $this->normalizeEvaluationBlock($validated['evaluation'] ?? []),
        );

        if (is_array($validated['window'] ?? null)) {
            $windowSettings = $this->normalizeWindowSettings(
                $validated['window'],
                $automationStore,
            );
            $automationStore->saveWindowSettings(
                $windowSettings['start_time'],
                $windowSettings['end_time'],
                $windowSettings['weekly_schedule'],
            );
        }

        try {
            $state = $this->syncAutomationRuntime(
                $automationStore,
                $automationWindow,
                $dispatcher,
                $automationStopper,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'automation' => $automationStore->stateWithWindow($automationWindow),
            ], 500);
        }

        return response()->json([
            'message' => is_array($validated['window'] ?? null)
                ? $this->windowSavedMessage($state)
                : 'Налаштування фонової обробки збережено.',
            'automation' => $state,
        ]);
    }

    public function nextFirstCall(
        Request $request,
        BinotelCallRecordUrlResolver $recordUrlResolver,
        BinotelCallAudioCacheService $audioCacheService,
    ): JsonResponse
    {
        $timezone = (string) config('binotel.timezone', 'Europe/Kyiv');
        $day = $this->resolveRequestedDay(trim((string) $request->query('date', '')), $timezone);
        $startTimestamp = $day->startOfDay()->getTimestamp();
        $endTimestamp = $day->endOfDay()->getTimestamp();
        $supportsLocalAudioCache = Schema::hasColumn('binotel_api_call_completeds', 'local_audio_relative_path');

        $calls = BinotelApiCallCompleted::query()
            ->with('feedback')
            ->where('request_type', 'apiCallCompleted')
            ->where('interaction_number', 1)
            ->whereBetween('call_details_start_time', [$startTimestamp, $endTimestamp])
            ->whereNotNull('call_details_general_call_id')
            ->where('call_details_general_call_id', '<>', '')
            ->where('call_details_disposition', 'ANSWER')
            ->where('call_details_billsec', '>', 0)
            ->where(function ($query) use ($supportsLocalAudioCache): void {
                if ($supportsLocalAudioCache) {
                    $query
                        ->whereNotNull('local_audio_relative_path')
                        ->orWhereNotNull('call_record_url')
                        ->orWhereNotNull('call_details_link_to_call_record_in_my_business')
                        ->orWhereNotNull('call_details_link_to_call_record_overlay_in_my_business');

                    return;
                }

                $query
                    ->whereNotNull('call_record_url')
                    ->orWhereNotNull('call_details_link_to_call_record_in_my_business')
                    ->orWhereNotNull('call_details_link_to_call_record_overlay_in_my_business');
            })
            ->whereDoesntHave('feedback', function ($query): void {
                $query
                    ->where('transcription_status', 'completed')
                    ->orWhereIn('evaluation_status', ['pending', 'running', 'completed'])
                    ->orWhereNotNull('evaluation_score')
                    ->orWhereNotNull('evaluated_at');
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('alt_auto_status')
                    ->orWhereIn('alt_auto_status', ['pending', 'failed']);
            })
            ->orderBy('call_details_start_time')
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($calls as $call) {
            $cachedAudio = null;

            try {
                $cachedAudio = $audioCacheService->ensureLocalCopy($call);
            } catch (RuntimeException) {
                $cachedAudio = null;
            }

            $audioUrl = $cachedAudio !== null
                ? route('api.alt.call-center.calls.audio-file', ['call' => $call->id])
                : $recordUrlResolver->resolve($call);
            $call->refresh();

            if ($audioUrl === '') {
                continue;
            }

            $this->markCallReserved($call);
            $call->refresh();

            return response()->json([
                'message' => 'Знайдено першу взаємодію за день. Посилання додано в поле транскрибації.',
                'call' => $this->mapCall($call, $audioUrl, $timezone, $audioCacheService->cachedAudio($call)),
            ]);
        }

        return response()->json([
            'message' => 'Для цього дня немає необробленої першої взаємодії з готовим прямим посиланням на запис.',
            'date' => $day->format('d.m.Y'),
        ], 404);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function normalizeProcessingBlock(array $settings, string $promptKey): array
    {
        $model = trim((string) ($settings['model'] ?? ''));
        $promptByModelKey = $promptKey.'_by_model';
        $promptByModel = $this->normalizePromptByModel(
            is_array($settings[$promptByModelKey] ?? null) ? $settings[$promptByModelKey] : [],
        );
        $prompt = trim((string) ($settings[$promptKey] ?? ''));
        $generationSettings = $this->normalizeGenerationSettings(
            is_array($settings['generation_settings'] ?? null) ? $settings['generation_settings'] : $settings,
        );
        $generationSettingsByModel = $this->normalizeGenerationSettingsByModel(
            is_array($settings['generation_settings_by_model'] ?? null) ? $settings['generation_settings_by_model'] : [],
        );

        if ($model !== '') {
            $prompt = trim((string) ($promptByModel[$model] ?? $prompt));
            $generationSettings = $generationSettingsByModel[$model] ?? $generationSettings;

            if ($prompt !== '') {
                $promptByModel[$model] = $prompt;
            }

            $generationSettingsByModel[$model] = $generationSettings;
        }

        $normalized = [
            'model' => $model,
            $promptKey => $prompt,
            $promptByModelKey => $promptByModel,
            'generation_settings' => $generationSettings,
            'generation_settings_by_model' => $generationSettingsByModel,
        ];

        if (array_key_exists('enabled', $settings)) {
            $normalized['enabled'] = (bool) $settings['enabled'];
        }

        return array_filter($normalized, static fn (mixed $value, string $key): bool => $key === 'enabled' || ($value !== '' && $value !== []), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function normalizeEvaluationBlock(array $settings): array
    {
        $normalized = $this->normalizeProcessingBlock($settings, 'system_prompt');

        if (array_key_exists('enabled', $settings)) {
            $normalized['enabled'] = (bool) $settings['enabled'];
        }

        $scenario = trim((string) ($settings['evaluation_scenario'] ?? ''));
        if ($scenario !== '') {
            $normalized['evaluation_scenario'] = $scenario;
        }

        $routingRules = $this->normalizeChecklistRoutingRules(
            is_array($settings['checklist_routing_rules'] ?? null) ? $settings['checklist_routing_rules'] : [],
        );
        if ($routingRules !== []) {
            $normalized['checklist_routing_rules'] = $routingRules;
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $rules
     * @return array<int, array{checklist_id:string,interaction_number:int,direction:string}>
     */
    private function normalizeChecklistRoutingRules(array $rules): array
    {
        $normalized = [];

        foreach (array_values($rules) as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $checklistId = trim((string) ($rule['checklist_id'] ?? ''));
            $interactionNumber = max(1, min(20, (int) ($rule['interaction_number'] ?? 1)));
            $direction = trim((string) ($rule['direction'] ?? 'any'));
            $direction = in_array($direction, ['in', 'out', 'any'], true) ? $direction : 'any';

            if ($checklistId === '') {
                continue;
            }

            $normalized[] = [
                'checklist_id' => $checklistId,
                'interaction_number' => $interactionNumber,
                'direction' => $direction,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $settingsByModel
     * @return array<string, array<string, mixed>>
     */
    private function normalizeGenerationSettingsByModel(array $settingsByModel): array
    {
        $normalized = [];

        foreach ($settingsByModel as $model => $settings) {
            $modelName = trim((string) $model);

            if ($modelName === '' || ! is_array($settings)) {
                continue;
            }

            $normalized[$modelName] = $this->normalizeGenerationSettings($settings);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $promptsByModel
     * @return array<string, string>
     */
    private function normalizePromptByModel(array $promptsByModel): array
    {
        $normalized = [];

        foreach ($promptsByModel as $model => $prompt) {
            $modelName = trim((string) $model);
            $promptText = trim((string) $prompt);

            if ($modelName === '' || $promptText === '') {
                continue;
            }

            $normalized[$modelName] = $promptText;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function normalizeGenerationSettings(array $settings): array
    {
        return array_filter([
            'thinking_enabled' => false,
            'temperature' => $this->nullableFloat($settings['temperature'] ?? null, 0.0, 2.0),
            'num_ctx' => $this->nullableInt($settings['num_ctx'] ?? null, 256, 131072),
            'top_k' => $this->nullableInt($settings['top_k'] ?? null, 1, 500),
            'top_p' => $this->nullableFloat($settings['top_p'] ?? null, 0.0, 1.0),
            'repeat_penalty' => $this->nullableFloat($settings['repeat_penalty'] ?? $settings['repetition_penalty'] ?? null, 0.0, 5.0),
            'num_predict' => $this->nullableInt($settings['num_predict'] ?? $settings['max_new_tokens'] ?? null, -1, 32768),
            'seed' => $this->nullableInt($settings['seed'] ?? null, -2147483648, 2147483647),
            'timeout_seconds' => $this->nullableInt($settings['timeout_seconds'] ?? null, 15, 3600),
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function nullableInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === '' || $value === null || ! is_numeric($value)) {
            return null;
        }

        return max($min, min($max, (int) round((float) $value)));
    }

    /**
     * @param array<string, mixed> $window
     * @return array{start_time: string, end_time: string, weekly_schedule: array<int, array{day:int,start_time:string,end_time:string,is_day_off:bool}>}
     */
    private function normalizeWindowSettings(array $window, AltCallCenterAutomationStore $automationStore): array
    {
        $currentSettings = $automationStore->windowSettings();
        $startTime = trim((string) ($window['start_time'] ?? $currentSettings['start_time']));
        $endTime = trim((string) ($window['end_time'] ?? $currentSettings['end_time']));
        $weeklySchedule = $this->normalizeWeeklySchedule(
            is_array($window['weekly_schedule'] ?? null) ? $window['weekly_schedule'] : [],
            $startTime !== '' ? $startTime : $currentSettings['start_time'],
            $endTime !== '' ? $endTime : $currentSettings['end_time'],
        );

        return [
            'start_time' => $startTime !== '' ? $startTime : $currentSettings['start_time'],
            'end_time' => $endTime !== '' ? $endTime : $currentSettings['end_time'],
            'weekly_schedule' => $weeklySchedule,
        ];
    }

    /**
     * @param array<int, mixed> $schedule
     * @return array<int, array{day:int,start_time:string,end_time:string,is_day_off:bool}>
     */
    private function normalizeWeeklySchedule(array $schedule, string $defaultStartTime, string $defaultEndTime): array
    {
        $sourceByDay = [];
        foreach (array_values($schedule) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $day = (int) ($item['day'] ?? ($index + 1));
            if ($day < 1 || $day > 7) {
                continue;
            }

            $sourceByDay[$day] = $item;
        }

        $normalized = [];
        for ($day = 1; $day <= 7; $day++) {
            $item = $sourceByDay[$day] ?? [];

            $normalized[] = [
                'day' => $day,
                'start_time' => $this->normalizeScheduleTime($item['start_time'] ?? null, $defaultStartTime),
                'end_time' => $this->normalizeScheduleTime($item['end_time'] ?? null, $defaultEndTime),
                'is_day_off' => filter_var($item['is_day_off'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        return $normalized;
    }

    private function normalizeScheduleTime(mixed $value, string $fallback): string
    {
        $time = trim((string) $value);

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1) {
            return $time;
        }

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $fallback) === 1 ? $fallback : '00:00';
    }

    /**
     * @return array<string, mixed>
     */
    private function syncAutomationRuntime(
        AltCallCenterAutomationStore $automationStore,
        AltCallCenterAutomationWindow $automationWindow,
        AltCallCenterAutomationDispatcher $dispatcher,
        AltCallCenterAutomationStopper $automationStopper,
    ): array {
        if ($automationStore->isPaused()) {
            if (! $automationWindow->isOpen()) {
                $automationStore->noteWindowClosedDuringManualPause();
            }

            if ($automationWindow->isOpen() && $automationStore->shouldResumeWhenWindowOpens()) {
                $automationStore->autoResumeWhenWindowOpens();
            }

            return $automationStore->stateWithWindow($automationWindow);
        }

        if (! $automationWindow->isOpen()) {
            $state = $automationStopper->stopForClosedWindow($automationWindow->closedMessage());

            return array_merge($state, [
                'window' => $automationWindow->state(),
            ]);
        }

        $dispatcher->dispatch();

        return $automationStore->stateWithWindow($automationWindow);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function windowSavedMessage(array $state): string
    {
        $window = is_array($state['window'] ?? null) ? $state['window'] : [];
        $startTime = trim((string) ($window['start_time'] ?? ''));
        $endTime = trim((string) ($window['end_time'] ?? ''));

        if ($startTime !== '' && $startTime === $endTime) {
            return 'Час автозапуску збережено. Фоновий worker тепер може працювати цілодобово.';
        }

        if ((bool) ($window['is_day_off'] ?? false)) {
            return 'Графік автозапуску збережено. Сьогодні позначено як вихідний, тому worker зупинено.';
        }

        if ((bool) ($window['is_open'] ?? false)) {
            return 'Час автозапуску збережено. Поточний час входить у дозволене вікно.';
        }

        return 'Час автозапуску збережено. Поточний час поза дозволеним вікном, тому worker зупинено.';
    }

    private function nullableFloat(mixed $value, float $min, float $max): ?float
    {
        if ($value === '' || $value === null || ! is_numeric($value)) {
            return null;
        }

        return max($min, min($max, (float) $value));
    }

    private function resolveRequestedDay(string $value, string $timezone): CarbonImmutable
    {
        if ($value === '') {
            return CarbonImmutable::now($timezone);
        }

        foreach (['d.m.Y', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value, $timezone);
            } catch (Throwable) {
                $date = null;
            }

            if ($date instanceof CarbonImmutable) {
                return $date;
            }
        }

        return CarbonImmutable::now($timezone);
    }

    private function markCallReserved(BinotelApiCallCompleted $call): void
    {
        if (! array_key_exists('alt_auto_status', $call->getAttributes())) {
            return;
        }

        $call->forceFill([
            'alt_auto_status' => 'reserved',
            'alt_auto_error' => null,
            'alt_auto_started_at' => now(),
            'alt_auto_finished_at' => null,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCall(
        BinotelApiCallCompleted $call,
        string $audioUrl,
        string $timezone,
        ?array $localAudio = null,
    ): array
    {
        $startedAt = is_int($call->call_details_start_time) && $call->call_details_start_time > 0
            ? CarbonImmutable::createFromTimestamp($call->call_details_start_time, $timezone)
            : null;
        $fallbackUrl = trim((string) ($call->call_details_link_to_call_record_in_my_business ?? ''));
        $overlayUrl = trim((string) ($call->call_details_link_to_call_record_overlay_in_my_business ?? ''));
        $recordingStatus = trim((string) ($call->call_details_recording_status ?? ''));
        $remoteAudioUrl = trim((string) ($call->call_record_url ?? ''));

        return [
            'id' => $call->id,
            'generalCallId' => trim((string) $call->call_details_general_call_id) ?: null,
            'interactionNumber' => max(0, (int) ($call->interaction_number ?? 0)),
            'audioUrl' => $localAudio !== null
                ? route('api.alt.call-center.calls.audio-file', ['call' => $call->id])
                : $audioUrl,
            'remoteAudioUrl' => $remoteAudioUrl !== '' ? $remoteAudioUrl : $audioUrl,
            'audioFallbackUrl' => $fallbackUrl !== '' ? $fallbackUrl : null,
            'audioOverlayUrl' => $overlayUrl !== '' ? $overlayUrl : null,
            'audioStatus' => $localAudio !== null ? 'Локальний файл готовий' : ($audioUrl !== '' ? 'Запис доступний' : 'Запис недоступний'),
            'binotelStatus' => $this->binotelStatus($call, $audioUrl, $localAudio !== null),
            'recordingStatus' => $recordingStatus,
            'date' => $startedAt?->format('d.m.Y') ?? '',
            'time' => $startedAt?->format('H:i') ?? '',
            'caller' => trim((string) ($call->call_details_external_number ?? '')) ?: 'Невідомий номер',
            'employee' => trim((string) ($call->call_details_employee_name ?? '')) ?: 'Не визначено',
            'localAudioUrl' => $localAudio !== null ? route('api.alt.call-center.calls.audio-file', ['call' => $call->id]) : null,
            'localAudioDownloadUrl' => $localAudio !== null ? route('api.alt.call-center.calls.audio-file', ['call' => $call->id, 'download' => 1]) : null,
        ];
    }

    private function callAlreadyEvaluated(BinotelApiCallCompleted $call): bool
    {
        $feedback = $call->feedback;

        if ($feedback === null) {
            return false;
        }

        return in_array((string) ($feedback->evaluation_status ?? ''), ['pending', 'running', 'completed'], true)
            || $feedback->evaluation_score !== null
            || $feedback->evaluated_at !== null;
    }

    private function binotelStatus(BinotelApiCallCompleted $call, string $audioUrl, bool $hasLocalAudio = false): string
    {
        if ($hasLocalAudio) {
            return 'Локально';
        }

        if (trim($audioUrl) !== '') {
            return 'Успіх';
        }

        if ($call->call_record_url_last_checked_at !== null) {
            return 'Помилка';
        }

        return '—';
    }
}
