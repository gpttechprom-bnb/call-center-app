<?php

namespace App\Services;

use App\Models\BinotelApiCallCompleted;
use App\Support\AltCallCenterAutomationStore;
use App\Support\AltCallCenterEvaluationJobStore;
use Illuminate\Support\Facades\Schema;

class AltCallCenterAutomationStopper
{
    public function __construct(
        private readonly AltCallCenterAutomationStore $automationStore,
        private readonly AltCallCenterEvaluationJobStore $jobStore,
        private readonly BinotelCallFeedbackStore $feedbackStore,
        private readonly ProcessTreeTerminator $terminator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function pauseAndStop(
        bool $resumeOnNextWindowOpen = false,
        bool $waitForWindowToCloseBeforeResume = false,
    ): array
    {
        return $this->stop(
            true,
            'Фонову чергу поставлено на паузу. Поточний процес зупинено, службовий стан очищено.',
            'Фонову обробку зупинено вручну. Дзвінок повернено в чергу.',
            'manual',
            $resumeOnNextWindowOpen,
            $waitForWindowToCloseBeforeResume,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function stopForClosedWindow(string $message): array
    {
        return $this->stop(
            null,
            $message,
            'Фонову обробку зупинено поза дозволеним часом. Дзвінок повернено в чергу.',
            null,
            false,
            false,
            true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function stop(
        ?bool $paused,
        string $stateMessage,
        string $cleanupMessage,
        ?string $pausedReason = null,
        bool $resumeOnNextWindowOpen = false,
        bool $waitForWindowToCloseBeforeResume = false,
        bool $windowHasClosed = false,
    ): array
    {
        $state = $this->automationStore->state();
        $finalPaused = $paused ?? (bool) ($state['paused'] ?? true);
        $finalPausedReason = $finalPaused
            ? ($pausedReason ?? ((string) ($state['paused_reason'] ?? '') !== '' ? (string) ($state['paused_reason'] ?? '') : null))
            : null;
        $finalResumeOnNextWindowOpen = $finalPaused
            ? ($pausedReason !== null ? $resumeOnNextWindowOpen : (bool) ($state['resume_on_next_window_open'] ?? false))
            : false;
        $finalWaitForWindowToCloseBeforeResume = $finalPaused
            ? ($pausedReason !== null
                ? ($finalResumeOnNextWindowOpen && $waitForWindowToCloseBeforeResume)
                : ((bool) ($state['wait_for_window_to_close_before_resume'] ?? false)
                    && ! ($windowHasClosed
                        && $finalResumeOnNextWindowOpen
                        && $finalPausedReason === 'manual')))
            : false;
        $workerProcessId = $this->processId($state['process_id'] ?? null);

        if ($workerProcessId !== null && $this->isDefinitelyNotAutomationWorker($workerProcessId)) {
            $workerProcessId = null;
        }

        $terminatedProcessIds = $this->terminator->terminate($workerProcessId);
        $generalCallId = trim((string) ($state['current_general_call_id'] ?? ''));
        $activeJob = $this->activeAutomationJob($generalCallId, $workerProcessId, $terminatedProcessIds);

        if ($generalCallId === '' && $activeJob !== null) {
            $generalCallId = trim((string) ($activeJob['general_call_id'] ?? ''));
        }

        if ($activeJob !== null) {
            $jobId = trim((string) ($activeJob['id'] ?? ''));
            if ($jobId !== '') {
                $this->jobStore->markFailed($jobId, $cleanupMessage);
            }

            $jobCallId = trim((string) ($activeJob['general_call_id'] ?? ''));
            if ($jobCallId !== '') {
                $this->feedbackStore->markEvaluationFailed($jobCallId, $cleanupMessage, $jobId !== '' ? $jobId : null);
            }
        }

        $this->releaseCurrentCall($generalCallId, $cleanupMessage);

        return $this->automationStore->markStopped(
            $finalPaused,
            $finalPaused ? 'paused' : 'waiting',
            $stateMessage,
            $finalPausedReason,
            $finalResumeOnNextWindowOpen,
            $finalWaitForWindowToCloseBeforeResume,
        );
    }

    /**
     * @param array<int, int> $terminatedProcessIds
     * @return array<string, mixed>|null
     */
    private function activeAutomationJob(string $generalCallId, ?int $workerProcessId, array $terminatedProcessIds): ?array
    {
        $job = $this->jobStore->latestActiveJob();

        if ($job === null && $generalCallId !== '') {
            $job = $this->jobStore->latestOpenJob($generalCallId);
        }

        if ($job === null && ($workerProcessId !== null || $terminatedProcessIds !== [])) {
            $job = $this->jobStore->latestOpenJob();
        }

        if ($job === null) {
            return null;
        }

        $jobProcessId = $this->processId($job['process_id'] ?? null);
        $jobCallId = trim((string) ($job['general_call_id'] ?? ''));

        if ($generalCallId !== '' && $jobCallId === $generalCallId) {
            return $job;
        }

        if ($jobProcessId !== null && $workerProcessId !== null && $jobProcessId === $workerProcessId) {
            return $job;
        }

        if ($jobProcessId !== null && in_array($jobProcessId, $terminatedProcessIds, true)) {
            return $job;
        }

        return null;
    }

    private function releaseCurrentCall(string $generalCallId, string $message): void
    {
        if ($generalCallId === '' || ! Schema::hasColumn('binotel_api_call_completeds', 'alt_auto_status')) {
            return;
        }

        $attributes = [
            'alt_auto_status' => 'pending',
            'alt_auto_error' => $message,
        ];

        if (Schema::hasColumn('binotel_api_call_completeds', 'alt_auto_started_at')) {
            $attributes['alt_auto_started_at'] = null;
        }

        if (Schema::hasColumn('binotel_api_call_completeds', 'alt_auto_finished_at')) {
            $attributes['alt_auto_finished_at'] = null;
        }

        BinotelApiCallCompleted::query()
            ->where('call_details_general_call_id', $generalCallId)
            ->whereIn('alt_auto_status', ['running', 'reserved', 'failed'])
            ->update($attributes);
    }

    private function processId(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $processId = (int) $value;

        return $processId > 0 ? $processId : null;
    }

    private function isDefinitelyNotAutomationWorker(int $processId): bool
    {
        $cmdlinePath = '/proc/'.$processId.'/cmdline';

        if (! is_readable($cmdlinePath)) {
            return false;
        }

        $cmdline = str_replace("\0", ' ', trim((string) @file_get_contents($cmdlinePath)));

        if ($cmdline === '') {
            return false;
        }

        return ! str_contains($cmdline, 'artisan')
            || ! str_contains($cmdline, 'call-center:alt-auto-worker');
    }
}
