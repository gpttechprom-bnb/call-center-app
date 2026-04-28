<?php

namespace App\Services;

use App\Support\AltCallCenterAutomationStore;
use App\Support\AltCallCenterAutomationWindow;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;

class AltCallCenterAutomationDispatcher
{
    private const BACKGROUND_WORKER_MAX_SECONDS = 55;

    public function __construct(
        private readonly AltCallCenterAutomationStore $automationStore,
        private readonly AltCallCenterAutomationWindow $automationWindow,
        private readonly AltCallCenterAutomationStopper $automationStopper,
    ) {
    }

    public function dispatchIfPlaying(): ?int
    {
        if (! $this->automationWindow->isOpen()) {
            $this->automationStore->noteWindowClosedDuringManualPause();
        }

        if ($this->automationWindow->isOpen() && $this->automationStore->shouldResumeWhenWindowOpens()) {
            $this->automationStore->autoResumeWhenWindowOpens();
        }

        if ($this->automationStore->isPaused()) {
            return null;
        }

        if (! $this->automationWindow->isOpen()) {
            $this->automationStopper->stopForClosedWindow($this->automationWindow->closedMessage());

            return null;
        }

        return $this->dispatch();
    }

    public function dispatch(): ?int
    {
        if (! $this->automationWindow->isOpen()) {
            $this->automationStore->noteWindowClosedDuringManualPause();
            $this->automationStopper->stopForClosedWindow($this->automationWindow->closedMessage());

            return null;
        }

        if ($this->automationStore->shouldResumeWhenWindowOpens()) {
            $this->automationStore->autoResumeWhenWindowOpens();
        }

        if (! Schema::hasColumn('binotel_api_call_completeds', 'alt_auto_status')) {
            return null;
        }

        $state = $this->automationStore->state();
        $processId = is_numeric($state['process_id'] ?? null) ? (int) $state['process_id'] : null;

        if ($processId !== null && (bool) ($state['worker_alive'] ?? false)) {
            return $processId;
        }

        $phpBinary = $this->resolvePhpBinary();
        $basePath = base_path();
        $artisanPath = base_path('artisan');

        $command = sprintf(
            'cd %s && nohup %s %s call-center:alt-auto-worker --max-seconds=%d > /dev/null 2>&1 < /dev/null & echo $!',
            escapeshellarg($basePath),
            escapeshellarg($phpBinary),
            escapeshellarg($artisanPath),
            self::BACKGROUND_WORKER_MAX_SECONDS,
        );

        $process = Process::fromShellCommandline($command, $basePath);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Не вдалося запустити фонову чергу транскрибації.');
        }

        $output = trim($process->getOutput());
        $newProcessId = ctype_digit($output) ? (int) $output : null;

        $this->automationStore->markWorkerStarted($newProcessId);

        return $newProcessId;
    }

    public function dispatchSpecificCall(string $generalCallId): ?int
    {
        $normalized = trim($generalCallId);

        if ($normalized === '') {
            return null;
        }

        $phpBinary = $this->resolvePhpBinary();
        $basePath = base_path();
        $artisanPath = base_path('artisan');

        $command = sprintf(
            'cd %s && nohup %s %s call-center:alt-auto-worker --once --ignore-window --force-general-call-id=%s > /dev/null 2>&1 < /dev/null & echo $!',
            escapeshellarg($basePath),
            escapeshellarg($phpBinary),
            escapeshellarg($artisanPath),
            escapeshellarg($normalized),
        );

        $process = Process::fromShellCommandline($command, $basePath);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Не вдалося запустити примусову обробку вибраного дзвінка.');
        }

        $output = trim($process->getOutput());

        return ctype_digit($output) ? (int) $output : null;
    }

    private function resolvePhpBinary(): string
    {
        $binary = PHP_BINARY;

        if (str_contains(basename($binary), 'php-fpm')) {
            $cliBinary = rtrim(PHP_BINDIR, '/').'/php';

            return is_file($cliBinary) ? $cliBinary : 'php';
        }

        return $binary !== '' ? $binary : 'php';
    }
}
