<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class ProcessTreeTerminator
{
    /**
     * @return array<int, int>
     */
    public function terminate(?int $processId, int $graceSeconds = 2): array
    {
        if ($processId === null || $processId <= 0) {
            return [];
        }

        $processIds = $this->processTree($processId);
        if ($processIds === []) {
            $processIds = [$processId];
        }

        $this->signal(array_reverse($processIds), 15);
        $this->waitUntilStopped($processIds, $graceSeconds);

        $aliveProcessIds = array_values(array_filter(
            $processIds,
            fn (int $pid): bool => $this->isAlive($pid),
        ));

        if ($aliveProcessIds !== []) {
            $this->signal(array_reverse($aliveProcessIds), 9);
        }

        return $processIds;
    }

    public function isAlive(?int $processId): bool
    {
        if ($processId === null || $processId <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            if (@posix_kill($processId, 0)) {
                return true;
            }

            return function_exists('posix_get_last_error') && posix_get_last_error() === 1;
        }

        $process = Process::fromShellCommandline(
            sprintf('kill -0 %d 2>/dev/null', $processId),
            base_path(),
        );
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @return array<int, int>
     */
    private function processTree(int $rootProcessId): array
    {
        $childrenByParent = $this->childrenByParent();
        $processIds = [];
        $stack = [$rootProcessId];

        while ($stack !== []) {
            $processId = array_shift($stack);
            if (! is_int($processId) || in_array($processId, $processIds, true)) {
                continue;
            }

            $processIds[] = $processId;

            foreach ($childrenByParent[$processId] ?? [] as $childProcessId) {
                $stack[] = $childProcessId;
            }
        }

        return $processIds;
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function childrenByParent(): array
    {
        $process = Process::fromShellCommandline('ps -eo pid=,ppid=', base_path());
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $childrenByParent = [];

        foreach (preg_split('/\R/', trim($process->getOutput())) ?: [] as $line) {
            if (! preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $line, $matches)) {
                continue;
            }

            $processId = (int) $matches[1];
            $parentProcessId = (int) $matches[2];
            $childrenByParent[$parentProcessId] ??= [];
            $childrenByParent[$parentProcessId][] = $processId;
        }

        return $childrenByParent;
    }

    /**
     * @param array<int, int> $processIds
     */
    private function signal(array $processIds, int $signal): void
    {
        foreach ($processIds as $processId) {
            if ($processId <= 0) {
                continue;
            }

            if (function_exists('posix_kill')) {
                @posix_kill($processId, $signal);
                continue;
            }

            Process::fromShellCommandline(
                sprintf('kill -%d %d 2>/dev/null || true', $signal, $processId),
                base_path(),
            )->run();
        }
    }

    /**
     * @param array<int, int> $processIds
     */
    private function waitUntilStopped(array $processIds, int $graceSeconds): void
    {
        $deadline = microtime(true) + max(0, $graceSeconds);

        while (microtime(true) < $deadline) {
            foreach ($processIds as $processId) {
                if ($this->isAlive($processId)) {
                    usleep(100_000);
                    continue 2;
                }
            }

            return;
        }
    }
}
