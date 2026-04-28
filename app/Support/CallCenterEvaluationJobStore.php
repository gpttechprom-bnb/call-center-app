<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class CallCenterEvaluationJobStore
{
    /**
     * @param array<string, mixed> $transcription
     * @param array<string, mixed> $checklist
     * @return array<string, mixed>
     */
    public function create(array $transcription, array $checklist, array $llmSettings = [], ?string $generalCallId = null): array
    {
        $job = [
            'id' => (string) Str::uuid(),
            'general_call_id' => $generalCallId !== null && trim($generalCallId) !== ''
                ? trim($generalCallId)
                : null,
            'status' => 'pending',
            'process_id' => null,
            'transcription' => $transcription,
            'checklist' => $checklist,
            'llm_settings' => $llmSettings,
            'evaluation' => null,
            'error' => null,
            'logs' => [],
            'llm' => [
                'phase' => 'pending',
                'thinking' => '',
                'response' => '',
                'system_prompt' => '',
                'prompt' => '',
            ],
            'created_at' => now()->toIso8601String(),
            'started_at' => null,
            'finished_at' => null,
        ];

        $this->appendLogEntry($job, 'Завдання оцінювання створено. Очікуємо запуск фонового процесу.');
        $this->write($job);

        return $job;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $jobId): ?array
    {
        $path = $this->path($jobId);
        $absolutePath = Storage::disk('local')->path($path);

        if (! is_file($absolutePath)) {
            return null;
        }

        return $this->readJobFile($absolutePath);
    }

    public function markRunning(string $jobId): void
    {
        $job = $this->find($jobId);
        if ($job === null) {
            return;
        }

        $job['status'] = 'running';
        $job['started_at'] = $job['started_at'] ?: now()->toIso8601String();
        $job['error'] = null;
        $job['llm'] = $this->normalizeLlmTrace($job);
        $job['llm']['phase'] = 'running';
        $this->appendLogEntry($job, 'Фонове оцінювання запущено.');

        $this->write($job);
    }

    public function updateProcessId(string $jobId, ?int $processId): void
    {
        $job = $this->find($jobId);
        if ($job === null) {
            return;
        }

        $job['process_id'] = $processId !== null && $processId > 0
            ? $processId
            : null;

        $this->write($job);
    }

    public function clear(string $jobId): void
    {
        $path = $this->path($jobId);

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestActiveJob(): ?array
    {
        $disk = Storage::disk('local');
        $directory = $this->jobsDirectory();

        if (! $disk->exists($directory)) {
            return null;
        }

        $files = $disk->files($directory);
        usort($files, static fn (string $left, string $right): int => $disk->lastModified($right) <=> $disk->lastModified($left));

        foreach ($files as $path) {
            if (! str_ends_with($path, '.json')) {
                continue;
            }

            $job = $this->readJobFile($disk->path($path), 1);

            if (is_array($job) && $this->isActiveJob($job)) {
                return $job;
            }
        }

        return null;
    }

    /**
     * Return the most recent unfinished job even if its process is no longer alive.
     *
     * @return array<string, mixed>|null
     */
    public function latestOpenJob(?string $generalCallId = null): ?array
    {
        $disk = Storage::disk('local');
        $directory = $this->jobsDirectory();

        if (! $disk->exists($directory)) {
            return null;
        }

        $normalizedCallId = trim((string) $generalCallId);
        $files = $disk->files($directory);
        usort($files, static fn (string $left, string $right): int => $disk->lastModified($right) <=> $disk->lastModified($left));

        foreach ($files as $path) {
            if (! str_ends_with($path, '.json')) {
                continue;
            }

            $job = $this->readJobFile($disk->path($path), 1);
            if (! is_array($job) || ! $this->isOpenJob($job)) {
                continue;
            }

            if ($normalizedCallId !== '') {
                $jobCallId = trim((string) ($job['general_call_id'] ?? ''));

                if ($jobCallId !== $normalizedCallId) {
                    continue;
                }
            }

            return $job;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $evaluation
     */
    public function markCompleted(string $jobId, array $evaluation): void
    {
        $job = $this->find($jobId);
        if ($job === null) {
            return;
        }

        $job['status'] = 'completed';
        $job['process_id'] = null;
        $job['evaluation'] = $evaluation;
        $job['error'] = null;
        $job['started_at'] = $job['started_at'] ?: now()->toIso8601String();
        $job['finished_at'] = now()->toIso8601String();
        $job['llm'] = $this->normalizeLlmTrace($job);
        $job['llm']['phase'] = 'completed';
        $this->appendLogEntry($job, 'Фонове оцінювання завершено.');

        $this->write($job);
    }

    public function markFailed(string $jobId, string $message): void
    {
        $job = $this->find($jobId);
        if ($job === null) {
            return;
        }

        $job['status'] = 'failed';
        $job['process_id'] = null;
        $job['error'] = $message;
        $job['started_at'] = $job['started_at'] ?: now()->toIso8601String();
        $job['finished_at'] = now()->toIso8601String();
        $job['llm'] = $this->normalizeLlmTrace($job);
        $job['llm']['phase'] = 'failed';
        $this->appendLogEntry($job, $message, 'error');

        $this->write($job);
    }

    public function appendLog(string $jobId, string $message, string $channel = 'status'): void
    {
        $job = $this->find($jobId);
        if ($job === null) {
            return;
        }

        $this->appendLogEntry($job, $message, $channel);
        $this->write($job);
    }

    public function updateLlmTrace(
        string $jobId,
        ?string $thinking = null,
        ?string $response = null,
        ?string $phase = null,
        ?string $systemPrompt = null,
        ?string $prompt = null,
    ): void {
        $job = $this->find($jobId);
        if ($job === null) {
            return;
        }

        $job['llm'] = $this->normalizeLlmTrace($job);

        if ($thinking !== null) {
            $job['llm']['thinking'] = $thinking;
        }

        if ($response !== null) {
            $job['llm']['response'] = $response;
        }

        if ($phase !== null && trim($phase) !== '') {
            $job['llm']['phase'] = trim($phase);
        }

        if ($systemPrompt !== null) {
            $job['llm']['system_prompt'] = $systemPrompt;
        }

        if ($prompt !== null) {
            $job['llm']['prompt'] = $prompt;
        }

        $this->write($job);
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    public function publicPayload(array $job): array
    {
        return [
            'id' => (string) ($job['id'] ?? ''),
            'general_call_id' => trim((string) ($job['general_call_id'] ?? '')) ?: null,
            'status' => (string) ($job['status'] ?? 'pending'),
            'evaluation_scenario' => trim((string) (($job['llm_settings']['evaluation_scenario'] ?? ''))) ?: null,
            'process_id' => is_numeric($job['process_id'] ?? null) ? (int) $job['process_id'] : null,
            'evaluation' => is_array($job['evaluation'] ?? null) ? $job['evaluation'] : null,
            'message' => (string) ($job['error'] ?? ''),
            'logs' => $this->publicLogs($job['logs'] ?? []),
            'llm' => $this->normalizeLlmTrace($job),
            'created_at' => $job['created_at'] ?? null,
            'started_at' => $job['started_at'] ?? null,
            'finished_at' => $job['finished_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $job
     */
    private function write(array $job): void
    {
        $path = $this->path((string) $job['id']);
        $absolutePath = Storage::disk('local')->path($path);
        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Не вдалося створити директорію для фонового оцінювання дзвінка.');
        }

        try {
            $payload = json_encode(
                $job,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Не вдалося підготувати дані фонового оцінювання для збереження.', 0, $exception);
        }

        $temporaryPath = $absolutePath.'.tmp.'.getmypid().'.'.Str::random(8);
        $written = @file_put_contents($temporaryPath, $payload, LOCK_EX);

        if ($written === false) {
            throw new RuntimeException('Не вдалося зберегти файл фонового оцінювання дзвінка.');
        }

        @chmod($temporaryPath, 0664);

        if (! @rename($temporaryPath, $absolutePath)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Не вдалося замінити файл фонового оцінювання дзвінка.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJobFile(string $absolutePath, int $attempts = 3): ?array
    {
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if (! is_file($absolutePath)) {
                return null;
            }

            $handle = @fopen($absolutePath, 'rb');
            if ($handle === false) {
                usleep(20_000);
                continue;
            }

            $contents = false;

            try {
                if (@flock($handle, LOCK_SH)) {
                    $contents = stream_get_contents($handle);
                    @flock($handle, LOCK_UN);
                }
            } finally {
                @fclose($handle);
            }

            if (is_string($contents) && trim($contents) !== '') {
                $decoded = json_decode($contents, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            usleep(20_000);
        }

        return null;
    }

    protected function path(string $jobId): string
    {
        return $this->jobsDirectory()
            .'/'.trim($jobId).'.json';
    }

    protected function jobsDirectory(): string
    {
        return trim((string) config('call_center.evaluation.jobs_dir', 'call-center/evaluation-jobs'), '/');
    }

    /**
     * @param array<string, mixed> $job
     */
    private function appendLogEntry(array &$job, string $message, string $channel = 'status'): void
    {
        $normalizedMessage = trim($message);
        if ($normalizedMessage === '') {
            return;
        }

        $logs = $this->publicLogs($job['logs'] ?? []);
        $logs[] = [
            'channel' => trim((string) $channel) !== '' ? trim((string) $channel) : 'status',
            'message' => $normalizedMessage,
            'created_at' => now()->toIso8601String(),
        ];

        $job['logs'] = array_slice($logs, -250);
    }

    /**
     * @param mixed $value
     * @return array<int, array{channel:string,message:string,created_at:mixed}>
     */
    private function publicLogs(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $logs = [];

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $message = trim((string) ($entry['message'] ?? ''));
            if ($message === '') {
                continue;
            }

            $logs[] = [
                'channel' => trim((string) ($entry['channel'] ?? 'status')) ?: 'status',
                'message' => $message,
                'created_at' => $entry['created_at'] ?? null,
            ];
        }

        return $logs;
    }

    /**
     * @param array<string, mixed> $job
     * @return array{phase:string,thinking:string,response:string,system_prompt:string,prompt:string}
     */
    private function normalizeLlmTrace(array $job): array
    {
        $llm = is_array($job['llm'] ?? null) ? $job['llm'] : [];

        return [
            'phase' => trim((string) ($llm['phase'] ?? 'pending')) ?: 'pending',
            'thinking' => (string) ($llm['thinking'] ?? ''),
            'response' => (string) ($llm['response'] ?? ''),
            'system_prompt' => (string) ($llm['system_prompt'] ?? ''),
            'prompt' => (string) ($llm['prompt'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $job
     */
    private function isActiveJob(array $job): bool
    {
        if (! $this->isOpenJob($job)) {
            return false;
        }

        $status = trim((string) ($job['status'] ?? 'pending'));

        $createdAt = strtotime((string) ($job['created_at'] ?? $job['started_at'] ?? ''));

        if ($createdAt !== false && $createdAt < now()->subHours(6)->getTimestamp()) {
            return false;
        }

        $processId = is_numeric($job['process_id'] ?? null) ? (int) $job['process_id'] : null;
        if ($status === 'running' && $processId !== null && ! $this->isProcessAlive($processId)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function isOpenJob(array $job): bool
    {
        $status = trim((string) ($job['status'] ?? 'pending'));

        if (! in_array($status, ['pending', 'running'], true)) {
            return false;
        }

        return trim((string) ($job['finished_at'] ?? '')) === '';
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
            return true;
        }

        return function_exists('posix_get_last_error') && posix_get_last_error() === 1;
    }
}
