<?php

namespace App\Services;

use App\Models\BinotelApiCallCompleted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BinotelCallAudioCacheService
{
    private static ?bool $supportsLocalAudioCache = null;

    public function __construct(
        private readonly BinotelCallRecordUrlResolver $recordUrlResolver,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function ensureLocalCopy(BinotelApiCallCompleted $call, bool $forceRefreshUrl = false): ?array
    {
        if (! $this->supportsLocalAudioCache()) {
            return null;
        }

        if ($cached = $this->cachedAudio($call)) {
            return $cached;
        }

        $this->removeBrokenOrExpiredCopy($call);

        $audioUrl = $this->recordUrlResolver->resolve($call, $forceRefreshUrl);
        if ($audioUrl === '') {
            return null;
        }

        $directory = trim((string) config('call_center.audio_cache.alt_dir', 'call-center/alt/audio-cache'), '/');
        $extension = $this->resolveExtension($audioUrl);
        $generalCallId = trim((string) ($call->call_details_general_call_id ?? ''));
        $baseName = $generalCallId !== '' ? $generalCallId : 'call-'.$call->getKey();
        $fileName = $baseName.'-'.Str::uuid().'.'.$extension;
        $relativePath = $directory.'/'.date('Y/m/d').'/'.$fileName;
        $absolutePath = Storage::disk('local')->path($relativePath);
        $relativeDirectory = dirname($relativePath);
        $absoluteDirectory = dirname($absolutePath);

        Storage::disk('local')->makeDirectory($relativeDirectory);
        $this->ensureWritableDirectory($call, $absoluteDirectory);

        try {
            Storage::disk('local')->delete($relativePath);

            $response = Http::timeout(180)
                ->connectTimeout(20)
                ->withHeaders([
                    'User-Agent' => 'llm_yaprofi-call-center/1.0',
                ])
                ->withOptions(['sink' => $absolutePath])
                ->get($audioUrl);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($relativePath);
            $this->markDownloadFailure($call, $exception->getMessage());

            throw new RuntimeException(
                'Не вдалося завантажити аудіо у локальний кеш.'
                .(trim($exception->getMessage()) !== '' ? ' '.trim($exception->getMessage()) : '')
            );
        }

        if (! $response->successful()) {
            Storage::disk('local')->delete($relativePath);

            $message = 'Не вдалося завантажити аудіо у локальний кеш.'
                .' HTTP '.$response->status()
                .' '.trim((string) $response->reason());

            $this->markDownloadFailure($call, $message);

            throw new RuntimeException(trim($message));
        }

        if (! is_file($absolutePath) || filesize($absolutePath) === 0) {
            Storage::disk('local')->delete($relativePath);
            $message = 'Binotel повернув порожній аудіофайл для локального кешу.';
            $this->markDownloadFailure($call, $message);

            throw new RuntimeException($message);
        }

        $downloadedAt = now();
        $expiresAt = $downloadedAt->copy()->addDays(max(1, (int) config('call_center.audio_cache.retention_days', 10)));
        $mimeType = trim((string) $response->header('Content-Type'));
        $sizeBytes = filesize($absolutePath);

        $call->forceFill([
            'local_audio_relative_path' => $relativePath,
            'local_audio_original_name' => $fileName,
            'local_audio_mime_type' => $mimeType !== '' ? $mimeType : null,
            'local_audio_size_bytes' => is_int($sizeBytes) && $sizeBytes >= 0 ? $sizeBytes : null,
            'local_audio_downloaded_at' => $downloadedAt,
            'local_audio_expires_at' => $expiresAt,
            'local_audio_last_error' => null,
        ])->save();

        return $this->cachedAudio($call->fresh());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cachedAudio(BinotelApiCallCompleted $call): ?array
    {
        if (! $this->supportsLocalAudioCache()) {
            return null;
        }

        $relativePath = trim((string) ($call->local_audio_relative_path ?? ''));

        if ($relativePath === '') {
            return null;
        }

        if ($call->local_audio_expires_at !== null && now()->greaterThanOrEqualTo($call->local_audio_expires_at)) {
            return null;
        }

        if (! Storage::disk('local')->exists($relativePath)) {
            return null;
        }

        return [
            'relative_path' => $relativePath,
            'absolute_path' => Storage::disk('local')->path($relativePath),
            'file_name' => trim((string) ($call->local_audio_original_name ?? '')) !== ''
                ? trim((string) $call->local_audio_original_name)
                : basename($relativePath),
            'mime_type' => trim((string) ($call->local_audio_mime_type ?? '')) ?: null,
            'size_bytes' => $call->local_audio_size_bytes !== null ? (int) $call->local_audio_size_bytes : null,
            'downloaded_at' => $call->local_audio_downloaded_at,
            'expires_at' => $call->local_audio_expires_at,
        ];
    }

    public function deleteExpiredCopies(int $limit = 200): int
    {
        if (! $this->supportsLocalAudioCache()) {
            return 0;
        }

        $deleted = 0;

        BinotelApiCallCompleted::query()
            ->whereNotNull('local_audio_relative_path')
            ->where(function ($query): void {
                $query
                    ->whereNull('local_audio_expires_at')
                    ->orWhere('local_audio_expires_at', '<=', now());
            })
            ->orderBy('local_audio_expires_at')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (BinotelApiCallCompleted $call) use (&$deleted): void {
                $this->deleteLocalCopy($call);
                $deleted++;
            });

        return $deleted;
    }

    public function deleteLocalCopy(BinotelApiCallCompleted $call): void
    {
        if (! $this->supportsLocalAudioCache()) {
            return;
        }

        $relativePath = trim((string) ($call->local_audio_relative_path ?? ''));

        if ($relativePath !== '') {
            Storage::disk('local')->delete($relativePath);
        }

        $call->forceFill([
            'local_audio_relative_path' => null,
            'local_audio_original_name' => null,
            'local_audio_mime_type' => null,
            'local_audio_size_bytes' => null,
            'local_audio_downloaded_at' => null,
            'local_audio_expires_at' => null,
            'local_audio_last_error' => null,
        ])->save();
    }

    public function formatFileSize(?int $sizeBytes): ?string
    {
        if (! is_int($sizeBytes) || $sizeBytes < 0) {
            return null;
        }

        if ($sizeBytes < 1024) {
            return $sizeBytes.' B';
        }

        if ($sizeBytes < 1024 * 1024) {
            return number_format($sizeBytes / 1024, 1, '.', '').' KB';
        }

        return number_format($sizeBytes / (1024 * 1024), 1, '.', '').' MB';
    }

    private function removeBrokenOrExpiredCopy(BinotelApiCallCompleted $call): void
    {
        if (! $this->supportsLocalAudioCache()) {
            return;
        }

        $relativePath = trim((string) ($call->local_audio_relative_path ?? ''));

        if ($relativePath === '') {
            return;
        }

        $expired = $call->local_audio_expires_at !== null
            && now()->greaterThanOrEqualTo($call->local_audio_expires_at);
        $missing = ! Storage::disk('local')->exists($relativePath);

        if (! $expired && ! $missing) {
            return;
        }

        $this->deleteLocalCopy($call);
    }

    private function resolveExtension(string $audioUrl): string
    {
        $extension = strtolower((string) pathinfo((string) parse_url($audioUrl, PHP_URL_PATH), PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{2,8}$/', $extension) === 1 ? $extension : 'mp3';
    }

    private function markDownloadFailure(BinotelApiCallCompleted $call, string $message): void
    {
        if (! $this->supportsLocalAudioCache()) {
            return;
        }

        $call->forceFill([
            'local_audio_last_error' => trim($message) !== '' ? trim($message) : null,
        ])->save();
    }

    private function supportsLocalAudioCache(): bool
    {
        if (self::$supportsLocalAudioCache !== null) {
            return self::$supportsLocalAudioCache;
        }

        foreach ([
            'local_audio_relative_path',
            'local_audio_original_name',
            'local_audio_mime_type',
            'local_audio_size_bytes',
            'local_audio_downloaded_at',
            'local_audio_expires_at',
            'local_audio_last_error',
        ] as $column) {
            if (! Schema::hasColumn('binotel_api_call_completeds', $column)) {
                self::$supportsLocalAudioCache = false;

                return self::$supportsLocalAudioCache;
            }
        }

        self::$supportsLocalAudioCache = true;

        return self::$supportsLocalAudioCache;
    }

    private function ensureWritableDirectory(BinotelApiCallCompleted $call, string $absoluteDirectory): void
    {
        $parentDirectory = dirname($absoluteDirectory);

        if (! is_dir($absoluteDirectory) && ! @mkdir($absoluteDirectory, 0775, true) && ! is_dir($absoluteDirectory)) {
            $message = 'Не вдалося створити директорію для локального аудіокешу: '.$absoluteDirectory;
            $this->markDownloadFailure($call, $message);

            throw new RuntimeException($message);
        }

        $this->syncDirectoryMetadata($absoluteDirectory, $parentDirectory);

        if (! is_writable($absoluteDirectory)) {
            $message = 'Директорія локального аудіокешу недоступна для запису: '.$absoluteDirectory;
            $this->markDownloadFailure($call, $message);

            throw new RuntimeException($message);
        }
    }

    private function syncDirectoryMetadata(string $directory, string $referenceDirectory): void
    {
        $referenceOwner = @fileowner($referenceDirectory);
        $referenceGroup = @filegroup($referenceDirectory);

        @chmod($directory, 0775);

        if ($referenceOwner !== false) {
            @chown($directory, $referenceOwner);
        }

        if ($referenceGroup !== false) {
            @chgrp($directory, $referenceGroup);
        }
    }
}
