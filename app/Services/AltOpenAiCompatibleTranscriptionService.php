<?php

namespace App\Services;

use App\Support\AltCallCenterTranscriptionSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AltOpenAiCompatibleTranscriptionService
{
    public function __construct(
        protected readonly CallCenterSpeakerFormatter $speakerFormatter,
        protected readonly AltCallCenterTranscriptionSettings $transcriptionSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function transcribe(
        ?UploadedFile $audioFile,
        ?string $audioUrl,
        string $language,
        ?callable $processStarted = null,
        ?callable $progressUpdated = null,
    ): array {
        [$relativeRunDirectory, $absoluteRunDirectory] = $this->makeRunDirectory();

        $source = $audioFile instanceof UploadedFile
            ? $this->storeUploadedFile($audioFile, $relativeRunDirectory)
            : $this->downloadAudioFile((string) $audioUrl, $relativeRunDirectory);

        if ($processStarted !== null) {
            $processStarted(null);
        }

        if ($progressUpdated !== null) {
            $progressUpdated([
                'text' => '',
                'formatted_text' => '',
                'segments_count' => 0,
            ]);
        }

        $transcription = $this->requestTranscription(
            $source['absolute_path'],
            $language,
        );

        $dialogue = $this->speakerFormatter->format(
            is_array($transcription['segments'] ?? null) ? $transcription['segments'] : [],
        );

        $transcription['dialogueText'] = $dialogue['dialogueText'];
        $transcription['dialogueSegments'] = $dialogue['dialogueSegments'];

        if ($progressUpdated !== null) {
            $progressUpdated([
                'text' => (string) ($transcription['text'] ?? ''),
                'formatted_text' => (string) ($transcription['dialogueText'] ?? ''),
                'segments_count' => count($transcription['segments'] ?? []),
            ]);
        }

        Storage::disk('local')->put(
            $relativeRunDirectory.'/transcription-result.json',
            json_encode($transcription, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        return [
            'storageRunDirectory' => $relativeRunDirectory,
            'source' => [
                'type' => $source['type'],
                'name' => $source['name'],
                'relativePath' => $source['relative_path'],
            ],
            'transcription' => $transcription,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transcribeStoredFile(
        string $absoluteAudioPath,
        string $sourceName,
        string $sourceRelativePath,
        string $language,
        ?callable $processStarted = null,
        ?callable $progressUpdated = null,
    ): array {
        if (! is_file($absoluteAudioPath)) {
            throw new RuntimeException('Локальний аудіофайл для транскрибації не знайдено.');
        }

        [$relativeRunDirectory] = $this->makeRunDirectory();

        if ($processStarted !== null) {
            $processStarted(null);
        }

        if ($progressUpdated !== null) {
            $progressUpdated([
                'text' => '',
                'formatted_text' => '',
                'segments_count' => 0,
            ]);
        }

        $transcription = $this->requestTranscription(
            $absoluteAudioPath,
            $language,
        );

        $dialogue = $this->speakerFormatter->format(
            is_array($transcription['segments'] ?? null) ? $transcription['segments'] : [],
        );

        $transcription['dialogueText'] = $dialogue['dialogueText'];
        $transcription['dialogueSegments'] = $dialogue['dialogueSegments'];

        if ($progressUpdated !== null) {
            $progressUpdated([
                'text' => (string) ($transcription['text'] ?? ''),
                'formatted_text' => (string) ($transcription['dialogueText'] ?? ''),
                'segments_count' => count($transcription['segments'] ?? []),
            ]);
        }

        Storage::disk('local')->put(
            $relativeRunDirectory.'/transcription-result.json',
            json_encode($transcription, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        return [
            'storageRunDirectory' => $relativeRunDirectory,
            'source' => [
                'type' => 'cached_url',
                'name' => $sourceName,
                'relativePath' => $sourceRelativePath,
            ],
            'transcription' => $transcription,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function makeRunDirectory(): array
    {
        $baseDirectory = trim((string) config('call_center.transcription.alt_storage_dir', 'call-center/alt/transcriptions'), '/');
        $relativeRunDirectory = $baseDirectory.'/'.date('Y/m/d').'/'.Str::uuid();

        Storage::disk('local')->makeDirectory($relativeRunDirectory);

        return [
            $relativeRunDirectory,
            Storage::disk('local')->path($relativeRunDirectory),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function storeUploadedFile(UploadedFile $audioFile, string $relativeRunDirectory): array
    {
        $extension = $audioFile->getClientOriginalExtension()
            ?: $audioFile->guessExtension()
            ?: 'bin';

        $baseName = Str::slug(pathinfo($audioFile->getClientOriginalName(), PATHINFO_FILENAME));
        $fileName = ($baseName !== '' ? $baseName : 'audio').'.'.$extension;

        $relativePath = $audioFile->storeAs($relativeRunDirectory, $fileName, 'local');
        if (! is_string($relativePath)) {
            throw new RuntimeException('Не вдалося зберегти завантажений аудіофайл.');
        }

        return [
            'type' => 'upload',
            'name' => $audioFile->getClientOriginalName(),
            'relative_path' => $relativePath,
            'absolute_path' => Storage::disk('local')->path($relativePath),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function downloadAudioFile(string $audioUrl, string $relativeRunDirectory): array
    {
        $extension = pathinfo(parse_url($audioUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'bin';
        $fileName = 'remote-audio.'.$extension;
        $relativePath = $relativeRunDirectory.'/'.$fileName;
        Storage::disk('local')->makeDirectory($relativeRunDirectory);
        $absolutePath = Storage::disk('local')->path($relativePath);

        $response = null;
        $lastException = null;

        foreach ([1, 2, 3] as $attempt) {
            try {
                Storage::disk('local')->makeDirectory($relativeRunDirectory);
                Storage::disk('local')->delete($relativePath);

                $response = Http::timeout(180)
                    ->connectTimeout(20)
                    ->withHeaders([
                        'User-Agent' => 'llm_yaprofi-call-center/1.0',
                    ])
                    ->withOptions(['sink' => $absolutePath])
                    ->get($audioUrl);

                if ($response->successful()) {
                    break;
                }
            } catch (Throwable $exception) {
                $lastException = $exception;
            }

            if ($attempt < 3) {
                usleep(500000);
            }
        }

        if (! $response?->successful()) {
            Storage::disk('local')->delete($relativePath);

            if ($lastException instanceof Throwable) {
                $message = trim($lastException->getMessage());

                throw new RuntimeException(
                    'Не вдалося завантажити аудіо за посиланням.'
                    .($message !== '' ? ' '.$message : '')
                );
            }

            $status = $response?->status();
            $reason = trim((string) $response?->reason());
            $bodySnippet = trim(mb_substr((string) $response?->body(), 0, 300));

            throw new RuntimeException(
                'Не вдалося завантажити аудіо за посиланням.'
                .($status ? ' HTTP '.$status : '')
                .($reason !== '' ? ' '.$reason.'.' : '')
                .($bodySnippet !== '' ? ' '.$bodySnippet : '')
            );
        }

        if (is_file($absolutePath) && filesize($absolutePath) === 0) {
            Storage::disk('local')->delete($relativePath);
            throw new RuntimeException('За вказаним посиланням отримано порожній файл.');
        }

        return [
            'type' => 'url',
            'name' => $audioUrl,
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestTranscription(string $audioPath, string $language): array
    {
        $provider = $this->transcriptionSettings->transcriptionProvider();
        $apiUrl = rtrim($this->transcriptionSettings->transcriptionApiUrl($provider), '/');
        $apiKey = $this->transcriptionSettings->transcriptionApiKey($provider);
        $model = $this->transcriptionSettings->currentModel();
        $initialPrompt = $this->transcriptionSettings->transcriptionInitialPrompt();

        if ($apiUrl === '') {
            throw new RuntimeException('Для вибраного оператора транскрибації не задано API URL.');
        }

        if ($provider === 'openai' && ($apiKey === null || trim($apiKey) === '')) {
            throw new RuntimeException('Для OpenAI-транскрибації збережіть API ключ у налаштуваннях.');
        }

        try {
            $request = Http::timeout((int) config('call_center.transcription.timeout_seconds', 1800))
                ->acceptJson();

            if (is_string($apiKey) && trim($apiKey) !== '') {
                $request = $request->withToken(trim($apiKey));
            }

            $response = $request
                ->attach('file', fopen($audioPath, 'r'), basename($audioPath))
                ->post($apiUrl.'/audio/transcriptions', array_filter([
                    'model' => $model,
                    'language' => $language !== 'auto' ? $language : null,
                    'prompt' => $initialPrompt !== '' ? $initialPrompt : null,
                    'response_format' => 'verbose_json',
                ], static fn (mixed $value): bool => $value !== null && $value !== ''));
        } catch (Throwable) {
            throw new RuntimeException('Не вдалося звернутися до вибраного оператора транскрибації.');
        }

        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload)) {
            $message = is_array($payload)
                ? trim((string) data_get($payload, 'error.message', data_get($payload, 'message', '')))
                : '';

            throw new RuntimeException(
                $message !== ''
                    ? $message
                    : 'Оператор транскрибації повернув помилку. Перевірте модель, API URL і ключ доступу.'
            );
        }

        $segments = $this->normalizeSegments(
            is_array($payload['segments'] ?? null) ? $payload['segments'] : [],
            trim((string) ($payload['text'] ?? '')),
        );

        return [
            'provider' => $provider,
            'model' => $model,
            'language' => (string) ($payload['language'] ?? ($language === 'auto' ? 'auto' : $language)),
            'duration' => isset($payload['duration']) ? (float) $payload['duration'] : null,
            'text' => trim((string) ($payload['text'] ?? '')),
            'segments' => $segments,
            'raw_response' => $payload,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $segments
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSegments(array $segments, string $fallbackText = ''): array
    {
        $normalized = [];

        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $text = trim((string) ($segment['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $start = (float) ($segment['start'] ?? 0);
            $end = (float) ($segment['end'] ?? 0);
            if ($end <= 0) {
                $end = max($start, $start + 0.01);
            }

            $normalized[] = [
                'start' => $start,
                'end' => $end,
                'text' => $text,
                'start_label' => $this->formatTimestamp($start),
                'end_label' => $this->formatTimestamp($end),
            ];
        }

        if ($normalized === [] && $fallbackText !== '') {
            $normalized[] = [
                'start' => 0.0,
                'end' => 0.0,
                'text' => $fallbackText,
                'start_label' => $this->formatTimestamp(0),
                'end_label' => $this->formatTimestamp(0),
            ];
        }

        return $normalized;
    }

    private function formatTimestamp(float $seconds): string
    {
        $safeSeconds = max(0, (int) floor($seconds));
        $hours = intdiv($safeSeconds, 3600);
        $minutes = intdiv($safeSeconds % 3600, 60);
        $remainingSeconds = $safeSeconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
        }

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }
}
