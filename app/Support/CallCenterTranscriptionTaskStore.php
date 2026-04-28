<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class CallCenterTranscriptionTaskStore
{
    /**
     * @return array<string, mixed>
     */
    public function create(): array
    {
        $task = [
            'id' => (string) Str::uuid(),
            'status' => 'pending',
            'process_id' => null,
            'created_at' => now()->toIso8601String(),
            'started_at' => null,
        ];

        $this->write($task);

        return $task;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $taskId): ?array
    {
        $path = $this->path($taskId);

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk('local')->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function markRunning(string $taskId): void
    {
        $task = $this->find($taskId);
        if ($task === null) {
            return;
        }

        $task['status'] = 'running';
        $task['started_at'] = $task['started_at'] ?: now()->toIso8601String();

        $this->write($task);
    }

    public function updateProcessId(string $taskId, ?int $processId): void
    {
        $task = $this->find($taskId);
        if ($task === null) {
            return;
        }

        $task['process_id'] = $processId !== null && $processId > 0
            ? $processId
            : null;

        $this->write($task);
    }

    public function clear(string $taskId): void
    {
        $path = $this->path($taskId);

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    public function publicPayload(array $task): array
    {
        return [
            'id' => (string) ($task['id'] ?? ''),
            'status' => (string) ($task['status'] ?? 'pending'),
            'process_id' => is_numeric($task['process_id'] ?? null) ? (int) $task['process_id'] : null,
            'created_at' => $task['created_at'] ?? null,
            'started_at' => $task['started_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $task
     */
    private function write(array $task): void
    {
        $path = $this->path((string) $task['id']);
        $absolutePath = Storage::disk('local')->path($path);
        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Не вдалося створити директорію для службового завдання транскрибації.');
        }

        try {
            $payload = json_encode(
                $task,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Не вдалося підготувати службове завдання транскрибації для збереження.', 0, $exception);
        }

        $written = @file_put_contents($absolutePath, $payload, LOCK_EX);

        if ($written === false) {
            throw new RuntimeException('Не вдалося зберегти службове завдання транскрибації.');
        }
    }

    protected function path(string $taskId): string
    {
        return trim((string) config('call_center.transcription.tasks_dir', 'call-center/transcription-tasks'), '/')
            .'/'.trim($taskId).'.json';
    }
}
