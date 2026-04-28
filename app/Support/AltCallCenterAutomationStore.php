<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use JsonException;

class AltCallCenterAutomationStore
{
    private const STATE_PATH = 'call-center/alt/automation/state.json';

    private const WEEKLY_SCHEDULE_DAYS = [
        1 => 'Понеділок',
        2 => 'Вівторок',
        3 => 'Середа',
        4 => 'Четвер',
        5 => 'Пʼятниця',
        6 => 'Субота',
        7 => 'Неділя',
    ];

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $state = $this->read();
        $processId = $this->normalizeProcessId($state['process_id'] ?? null);
        $workerAlive = $this->isProcessAlive($processId);
        $windowSettings = $this->normalizeWindowSettings(
            is_array($state['window_settings'] ?? null) ? $state['window_settings'] : [],
        );
        $retry = $this->normalizeRetryState(
            is_array($state['retry'] ?? null) ? $state['retry'] : [],
        );

        if ($processId !== null && ! $workerAlive) {
            $state['process_id'] = null;

            if (trim((string) ($state['worker_stopped_at'] ?? '')) === '') {
                $state['worker_stopped_at'] = now()->toIso8601String();
            }
        }

        return array_merge($this->defaultState(), $state, [
            'paused' => (bool) ($state['paused'] ?? true),
            'process_id' => $workerAlive ? $processId : null,
            'worker_alive' => $workerAlive,
            'window_settings' => $windowSettings,
            'retry' => $retry,
        ]);
    }

    public function isPaused(): bool
    {
        return (bool) ($this->state()['paused'] ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    public function play(): array
    {
        return $this->update([
            'paused' => false,
            'paused_reason' => null,
            'resume_on_next_window_open' => false,
            'wait_for_window_to_close_before_resume' => false,
            'status' => 'waiting',
            'current_stage' => 'waiting',
            'last_message' => 'Фонова черга увімкнена. Нові дзвінки будуть оброблятися по черзі.',
            'last_error' => null,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array{ai_rewrite: array<string, mixed>, evaluation: array<string, mixed>}
     */
    public function processingSettings(): array
    {
        $settings = $this->state()['processing_settings'] ?? [];

        return [
            'ai_rewrite' => is_array($settings['ai_rewrite'] ?? null) ? $settings['ai_rewrite'] : [],
            'evaluation' => is_array($settings['evaluation'] ?? null) ? $settings['evaluation'] : [],
        ];
    }

    /**
     * @return array{start_time: string, end_time: string, weekly_schedule: array<int, array{day:int,label:string,start_time:string,end_time:string,is_day_off:bool}>}
     */
    public function windowSettings(): array
    {
        $settings = $this->state()['window_settings'] ?? [];

        return $this->normalizeWindowSettings(is_array($settings) ? $settings : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function stateWithWindow(AltCallCenterAutomationWindow $automationWindow): array
    {
        return array_merge($this->state(), [
            'window' => $automationWindow->state(),
        ]);
    }

    /**
     * @param array<string, mixed> $aiRewriteSettings
     * @param array<string, mixed> $evaluationSettings
     * @return array<string, mixed>
     */
    public function saveProcessingSettings(array $aiRewriteSettings, array $evaluationSettings): array
    {
        return $this->update([
            'processing_settings' => [
                'ai_rewrite' => $aiRewriteSettings,
                'evaluation' => $evaluationSettings,
            ],
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function saveWindowSettings(string $startTime, string $endTime, ?array $weeklySchedule = null): array
    {
        return $this->update([
            'window_settings' => $this->normalizeWindowSettings([
                'start_time' => $startTime,
                'end_time' => $endTime,
                'weekly_schedule' => $weeklySchedule,
            ]),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function markCurrentCall(string $generalCallId, ?string $audioUrl, string $message, string $stage = 'transcription'): void
    {
        $this->update([
            'status' => 'running',
            'current_stage' => trim($stage) !== '' ? trim($stage) : 'transcription',
            'current_general_call_id' => trim($generalCallId) !== '' ? trim($generalCallId) : null,
            'current_audio_url' => $audioUrl !== null && trim($audioUrl) !== '' ? trim($audioUrl) : null,
            'current_whisper_text' => null,
            'current_transcript_text' => null,
            'current_ai_corrections' => [],
            'current_ai_raw_corrections' => null,
            'last_message' => $message,
            'last_error' => null,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function updateCurrentWhisperText(string $text, string $message, ?string $stage = null): void
    {
        $this->update([
            'status' => 'running',
            'current_stage' => $stage !== null && trim($stage) !== '' ? trim($stage) : ($this->read()['current_stage'] ?? null),
            'current_whisper_text' => trim($text) !== '' ? $text : null,
            'last_message' => $message,
            'last_error' => null,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function updateCurrentTranscript(string $text, string $message, ?string $stage = null): void
    {
        $this->update([
            'status' => 'running',
            'current_stage' => $stage !== null && trim($stage) !== '' ? trim($stage) : ($this->read()['current_stage'] ?? null),
            'current_transcript_text' => trim($text) !== '' ? $text : null,
            'last_message' => $message,
            'last_error' => null,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param array<int, array{original:string,replacement:string,count:int}> $corrections
     */
    public function updateCurrentAiCorrections(array $corrections, ?string $rawCorrections, string $message, ?string $stage = null): void
    {
        $normalizedCorrections = array_values(array_filter($corrections, static function (mixed $item): bool {
            return is_array($item)
                && trim((string) ($item['original'] ?? '')) !== ''
                && trim((string) ($item['replacement'] ?? '')) !== '';
        }));

        $this->update([
            'status' => 'running',
            'current_stage' => $stage !== null && trim($stage) !== '' ? trim($stage) : ($this->read()['current_stage'] ?? null),
            'current_ai_corrections' => $normalizedCorrections,
            'current_ai_raw_corrections' => trim((string) $rawCorrections) !== '' ? trim((string) $rawCorrections) : null,
            'last_message' => $message,
            'last_error' => null,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function clearCurrentCall(): void
    {
        $this->update([
            'current_stage' => null,
            'current_general_call_id' => null,
            'current_audio_url' => null,
            'current_whisper_text' => null,
            'current_transcript_text' => null,
            'current_ai_corrections' => [],
            'current_ai_raw_corrections' => null,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array{general_call_id:?string,attempt:int,max_attempts:int,available_at:?string,last_error:?string}
     */
    public function retryState(): array
    {
        return $this->state()['retry'] ?? $this->normalizeRetryState([]);
    }

    public function scheduleRetry(
        string $generalCallId,
        int $attempt,
        int $maxAttempts,
        string $availableAt,
        string $message,
        ?string $stage = null,
    ): void {
        $this->update([
            'status' => 'waiting',
            'current_stage' => $stage !== null && trim($stage) !== '' ? trim($stage) : ($this->read()['current_stage'] ?? null),
            'last_message' => $message,
            'last_error' => null,
            'retry' => $this->normalizeRetryState([
                'general_call_id' => $generalCallId,
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
                'available_at' => $availableAt,
            ]),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function clearRetry(?string $generalCallId = null): void
    {
        $retry = $this->retryState();
        $storedCallId = trim((string) ($retry['general_call_id'] ?? ''));
        $targetCallId = trim((string) $generalCallId);

        if ($targetCallId !== '' && $storedCallId !== '' && $storedCallId !== $targetCallId) {
            return;
        }

        $this->update([
            'retry' => $this->normalizeRetryState([]),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function pause(
        bool $resumeOnNextWindowOpen = false,
        bool $waitForWindowToCloseBeforeResume = false,
    ): array
    {
        return $this->update([
            'paused' => true,
            'paused_reason' => 'manual',
            'resume_on_next_window_open' => $resumeOnNextWindowOpen,
            'wait_for_window_to_close_before_resume' => $resumeOnNextWindowOpen && $waitForWindowToCloseBeforeResume,
            'status' => 'paused',
            'current_stage' => 'paused',
            'last_message' => 'Фонова черга поставлена на паузу.',
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function pauseAfterFailure(string $message, ?string $stage = null, ?string $displayMessage = null): array
    {
        $resolvedStage = $stage !== null && trim($stage) !== ''
            ? trim($stage)
            : trim((string) ($this->read()['current_stage'] ?? ''));

        return $this->update([
            'paused' => true,
            'paused_reason' => 'failure',
            'resume_on_next_window_open' => false,
            'wait_for_window_to_close_before_resume' => false,
            'status' => 'failed',
            'current_stage' => $resolvedStage !== '' ? $resolvedStage : 'failed',
            'last_message' => trim((string) $displayMessage) !== ''
                ? trim((string) $displayMessage)
                : 'Фонова черга зупинена після помилки. Усуньте причину та натисніть Play, щоб повторити цей дзвінок.',
            'last_error' => $message,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function markStopped(
        bool $paused,
        string $status,
        string $message,
        ?string $pausedReason = null,
        bool $resumeOnNextWindowOpen = false,
        bool $waitForWindowToCloseBeforeResume = false,
    ): array
    {
        return $this->update([
            'paused' => $paused,
            'paused_reason' => $paused ? $pausedReason : null,
            'resume_on_next_window_open' => $paused ? $resumeOnNextWindowOpen : false,
            'wait_for_window_to_close_before_resume' => $paused ? $waitForWindowToCloseBeforeResume : false,
            'status' => $status,
            'current_stage' => $paused ? 'paused' : 'waiting',
            'process_id' => null,
            'current_general_call_id' => null,
            'current_audio_url' => null,
            'current_whisper_text' => null,
            'current_transcript_text' => null,
            'current_ai_corrections' => [],
            'current_ai_raw_corrections' => null,
            'last_message' => $message,
            'last_error' => null,
            'worker_stopped_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function shouldResumeWhenWindowOpens(): bool
    {
        $state = $this->state();

        return (bool) ($state['paused'] ?? true)
            && (string) ($state['paused_reason'] ?? '') === 'manual'
            && (bool) ($state['resume_on_next_window_open'] ?? false)
            && ! (bool) ($state['wait_for_window_to_close_before_resume'] ?? false);
    }

    public function noteWindowClosedDuringManualPause(): array
    {
        $state = $this->state();

        if (
            ! (bool) ($state['paused'] ?? true)
            || (string) ($state['paused_reason'] ?? '') !== 'manual'
            || ! (bool) ($state['resume_on_next_window_open'] ?? false)
            || ! (bool) ($state['wait_for_window_to_close_before_resume'] ?? false)
        ) {
            return $state;
        }

        return $this->update([
            'wait_for_window_to_close_before_resume' => false,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function autoResumeWhenWindowOpens(): array
    {
        if (! $this->shouldResumeWhenWindowOpens()) {
            return $this->state();
        }

        return $this->update([
            'paused' => false,
            'paused_reason' => null,
            'resume_on_next_window_open' => false,
            'wait_for_window_to_close_before_resume' => false,
            'status' => 'waiting',
            'current_stage' => 'waiting',
            'last_message' => 'Графік знову дозволяє автозапуск. Тимчасову ручну паузу знято автоматично.',
            'last_error' => null,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function markWorkerStarted(?int $processId): void
    {
        $this->update([
            'process_id' => $processId !== null && $processId > 0 ? $processId : null,
            'status' => 'running',
            'worker_started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function markWorkerStopped(?int $processId = null): void
    {
        $state = $this->read();
        $storedProcessId = $this->normalizeProcessId($state['process_id'] ?? null);

        if ($processId !== null && $storedProcessId !== null && $storedProcessId !== $processId) {
            return;
        }

        $paused = (bool) ($state['paused'] ?? true);
        $hasError = trim((string) ($state['last_error'] ?? '')) !== '';
        $currentStage = trim((string) ($state['current_stage'] ?? ''));

        $this->update([
            'process_id' => null,
            'status' => $paused
                ? ($hasError ? 'failed' : 'paused')
                : 'waiting',
            'current_stage' => $paused
                ? ($hasError ? ($currentStage !== '' ? $currentStage : 'failed') : 'paused')
                : 'waiting',
            'worker_stopped_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function markMessage(string $message, string $status = 'running', ?string $stage = null): void
    {
        $this->update([
            'status' => $status,
            'current_stage' => $stage !== null && trim($stage) !== '' ? trim($stage) : ($this->read()['current_stage'] ?? null),
            'last_message' => $message,
            'last_error' => null,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function markError(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'current_stage' => 'failed',
            'last_error' => $message,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function workerIsAlive(): bool
    {
        return (bool) ($this->state()['worker_alive'] ?? false);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function update(array $attributes): array
    {
        $state = array_merge($this->defaultState(), $this->read(), $attributes);
        $this->write($state);

        return $this->state();
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::STATE_PATH)) {
            return $this->defaultState();
        }

        try {
            $decoded = json_decode((string) $disk->get(self::STATE_PATH), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->defaultState();
        }

        return is_array($decoded) ? $decoded : $this->defaultState();
    }

    /**
     * @param array<string, mixed> $state
     */
    private function write(array $state): void
    {
        Storage::disk('local')->put(
            self::STATE_PATH,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultState(): array
    {
        return [
            'paused' => true,
            'paused_reason' => null,
            'resume_on_next_window_open' => false,
            'wait_for_window_to_close_before_resume' => false,
            'status' => 'paused',
            'process_id' => null,
            'current_stage' => 'paused',
            'last_message' => 'Фонова черга на паузі.',
            'last_error' => null,
            'current_general_call_id' => null,
            'current_audio_url' => null,
            'current_whisper_text' => null,
            'current_transcript_text' => null,
            'current_ai_corrections' => [],
            'current_ai_raw_corrections' => null,
            'processing_settings' => [
                'ai_rewrite' => [],
                'evaluation' => [],
            ],
            'retry' => $this->normalizeRetryState([]),
            'window_settings' => $this->defaultWindowSettings(),
            'worker_started_at' => null,
            'worker_stopped_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{start_time: string, end_time: string, weekly_schedule: array<int, array{day:int,label:string,start_time:string,end_time:string,is_day_off:bool}>}
     */
    private function normalizeWindowSettings(array $settings): array
    {
        $startTime = $this->normalizeTimeValue(
            $settings['start_time'] ?? null,
            $this->defaultWindowTime('call_center.automation.night_start_time', 'call_center.automation.night_start_hour', 20),
        );
        $endTime = $this->normalizeTimeValue(
            $settings['end_time'] ?? null,
            $this->defaultWindowTime('call_center.automation.night_end_time', 'call_center.automation.night_end_hour', 6),
        );

        return [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'weekly_schedule' => $this->normalizeWeeklySchedule(
                $settings['weekly_schedule'] ?? null,
                $startTime,
                $endTime,
            ),
        ];
    }

    /**
     * @return array{start_time: string, end_time: string, weekly_schedule: array<int, array{day:int,label:string,start_time:string,end_time:string,is_day_off:bool}>}
     */
    private function defaultWindowSettings(): array
    {
        $startTime = $this->defaultWindowTime('call_center.automation.night_start_time', 'call_center.automation.night_start_hour', 20);
        $endTime = $this->defaultWindowTime('call_center.automation.night_end_time', 'call_center.automation.night_end_hour', 6);

        return [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'weekly_schedule' => $this->normalizeWeeklySchedule(null, $startTime, $endTime),
        ];
    }

    /**
     * @return array<int, array{day:int,label:string,start_time:string,end_time:string,is_day_off:bool}>
     */
    private function normalizeWeeklySchedule(mixed $schedule, string $defaultStartTime, string $defaultEndTime): array
    {
        $sourceByDay = [];

        if (is_array($schedule)) {
            foreach (array_values($schedule) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $day = (int) ($item['day'] ?? $item['iso_day'] ?? ($index + 1));
                if (! array_key_exists($day, self::WEEKLY_SCHEDULE_DAYS)) {
                    continue;
                }

                $sourceByDay[$day] = $item;
            }
        }

        $normalized = [];
        foreach (self::WEEKLY_SCHEDULE_DAYS as $day => $label) {
            $item = $sourceByDay[$day] ?? [];
            $isDayOff = $this->normalizeBoolean(
                $item['is_day_off'] ?? $item['day_off'] ?? null,
                false,
            );

            $normalized[] = [
                'day' => $day,
                'label' => $label,
                'start_time' => $this->normalizeTimeValue($item['start_time'] ?? null, $defaultStartTime),
                'end_time' => $this->normalizeTimeValue($item['end_time'] ?? null, $defaultEndTime),
                'is_day_off' => $isDayOff,
            ];
        }

        return $normalized;
    }

    private function normalizeBoolean(mixed $value, bool $fallback): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $fallback;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }

    /**
     * @param array<string, mixed> $retry
     * @return array{general_call_id:?string,attempt:int,max_attempts:int,available_at:?string,last_error:?string}
     */
    private function normalizeRetryState(array $retry): array
    {
        $generalCallId = trim((string) ($retry['general_call_id'] ?? ''));
        $availableAt = trim((string) ($retry['available_at'] ?? ''));
        $lastError = trim((string) ($retry['last_error'] ?? ''));

        return [
            'general_call_id' => $generalCallId !== '' ? $generalCallId : null,
            'attempt' => max(0, (int) ($retry['attempt'] ?? 0)),
            'max_attempts' => max(0, (int) ($retry['max_attempts'] ?? 0)),
            'available_at' => $availableAt !== '' ? $availableAt : null,
            'last_error' => $lastError !== '' ? $lastError : null,
        ];
    }

    private function normalizeTimeValue(mixed $value, string $fallback): string
    {
        $time = trim((string) $value);

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1) {
            return $time;
        }

        if (is_numeric($value)) {
            return sprintf('%02d:00', $this->normalizeHour((int) $value));
        }

        return $fallback;
    }

    private function defaultWindowTime(string $timeConfigKey, string $hourConfigKey, int $defaultHour): string
    {
        $configuredTime = trim((string) config($timeConfigKey, ''));

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $configuredTime) === 1) {
            return $configuredTime;
        }

        return sprintf('%02d:00', $this->normalizeHour(config($hourConfigKey, $defaultHour)));
    }

    private function normalizeHour(mixed $hour): int
    {
        return max(0, min(23, (int) $hour));
    }

    private function normalizeProcessId(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $processId = (int) $value;

        return $processId > 0 ? $processId : null;
    }

    private function isProcessAlive(?int $processId): bool
    {
        if ($processId === null || $processId <= 0) {
            return false;
        }

        if (! function_exists('posix_kill')) {
            return true;
        }

        if (posix_kill($processId, 0)) {
            return $this->isAutomationWorkerProcess($processId);
        }

        return function_exists('posix_get_last_error')
            && posix_get_last_error() === 1
            && $this->isAutomationWorkerProcess($processId);
    }

    private function isAutomationWorkerProcess(int $processId): bool
    {
        $cmdlinePath = '/proc/'.$processId.'/cmdline';

        if (! is_readable($cmdlinePath)) {
            return false;
        }

        $cmdline = str_replace("\0", ' ', trim((string) @file_get_contents($cmdlinePath)));

        if ($cmdline === '') {
            return false;
        }

        return str_contains($cmdline, 'artisan')
            && str_contains($cmdline, 'call-center:alt-auto-worker');
    }
}
