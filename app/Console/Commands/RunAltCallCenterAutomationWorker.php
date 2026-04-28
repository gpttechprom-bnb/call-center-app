<?php

namespace App\Console\Commands;

use App\Services\AltCallCenterAutoProcessor;
use App\Services\AltCallCenterAutomationDispatcher;
use App\Support\AltCallCenterAutomationStore;
use App\Support\AltCallCenterAutomationWindow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RunAltCallCenterAutomationWorker extends Command
{
    protected $signature = 'call-center:alt-auto-worker
        {--once : Process at most one queued call}
        {--max-seconds=0 : Maximum worker lifetime in seconds; 0 keeps running until paused}
        {--force-general-call-id= : Process only the specified General Call ID}
        {--ignore-window : Allow a manual one-off run outside the automation schedule}';

    protected $description = 'Run the background alt call-center transcription and evaluation queue';

    public function handle(
        AltCallCenterAutomationStore $automationStore,
        AltCallCenterAutomationWindow $automationWindow,
        AltCallCenterAutoProcessor $processor,
        AltCallCenterAutomationDispatcher $dispatcher,
    ): int {
        $forcedGeneralCallId = trim((string) $this->option('force-general-call-id'));
        $ignoreWindow = (bool) $this->option('ignore-window');
        $manualSpecificRun = $forcedGeneralCallId !== '';

        if (! $ignoreWindow && ! $automationWindow->isOpen()) {
            $automationStore->noteWindowClosedDuringManualPause();
        }

        if (! $ignoreWindow && $automationWindow->isOpen() && $automationStore->shouldResumeWhenWindowOpens()) {
            $automationStore->autoResumeWhenWindowOpens();
        }

        if ($automationStore->isPaused() && ! $manualSpecificRun) {
            $automationStore->markMessage('Фонова черга на паузі.', 'paused');

            return self::SUCCESS;
        }

        if (! $ignoreWindow && ! $automationWindow->isOpen()) {
            $automationStore->markMessage($automationWindow->closedMessage(), 'waiting');

            return self::SUCCESS;
        }

        $lockHandle = $this->acquireLock();
        if ($lockHandle === null) {
            $automationStore->markMessage('Фоновий воркер вже запущений.', 'running');

            return self::SUCCESS;
        }

        $processId = getmypid() ?: null;
        $automationStore->markWorkerStarted($processId);

        $startedAt = time();
        $maxSeconds = max(0, (int) $this->option('max-seconds'));
        $once = (bool) $this->option('once') || $manualSpecificRun;
        $exitCode = self::SUCCESS;
        $shouldRespawn = false;

        try {
            do {
                if ($automationStore->isPaused() && ! $manualSpecificRun) {
                    $automationStore->markMessage('Фонова черга поставлена на паузу. Воркер завершує цикл.', 'paused');
                    break;
                }

                if (! $ignoreWindow && ! $automationWindow->isOpen()) {
                    $automationStore->markMessage($automationWindow->closedMessage(), 'waiting');
                    break;
                }

                $processed = $processor->processNext($manualSpecificRun ? $forcedGeneralCallId : null);

                if ($once) {
                    break;
                }

                if (! $processed) {
                    if ($automationStore->isPaused()) {
                        break;
                    }

                    sleep(10);
                }
            } while ($maxSeconds === 0 || (time() - $startedAt) < $maxSeconds);
        } catch (Throwable $exception) {
            $automationStore->markError($exception->getMessage());
            report($exception);
            $exitCode = self::FAILURE;
        } finally {
            $automationStore->markWorkerStopped($processId);
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);

            $shouldRespawn = ! $once
                && ! $automationStore->isPaused()
                && $automationWindow->isOpen()
                && ! $manualSpecificRun
                && ! $ignoreWindow;

            if ($shouldRespawn) {
                try {
                    $dispatcher->dispatchIfPlaying();
                } catch (Throwable $dispatchException) {
                    $automationStore->markError($dispatchException->getMessage());
                    report($dispatchException);
                }
            }
        }

        return $exitCode;
    }

    /**
     * @return resource|null
     */
    private function acquireLock()
    {
        $path = Storage::disk('local')->path('call-center/alt/automation/worker.lock');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return null;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        ftruncate($handle, 0);
        fwrite($handle, (string) (getmypid() ?: ''));

        return $handle;
    }
}
