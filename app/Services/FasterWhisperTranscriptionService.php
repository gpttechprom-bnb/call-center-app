<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\CallCenterTranscriptionSettings;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class FasterWhisperTranscriptionService
{
    private const STDERR_PROGRESS_PREFIX = '__WHISPER_PROGRESS__';

    public function __construct(
        protected readonly CallCenterSpeakerFormatter $speakerFormatter,
        protected readonly CallCenterTranscriptionSettings $transcriptionSettings,
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

        $transcription = $this->runPythonTranscriber(
            $source['absolute_path'],
            $absoluteRunDirectory,
            $language,
            $processStarted,
            $progressUpdated,
        );
        $dialogueSourceSegments = is_array($transcription['speakerTurns'] ?? null) && $transcription['speakerTurns'] !== []
            ? $transcription['speakerTurns']
            : (is_array($transcription['segments'] ?? null) ? $transcription['segments'] : []);
        $dialogue = $this->speakerFormatter->format(
            $dialogueSourceSegments,
        );

        $transcription['dialogueText'] = $dialogue['dialogueText'];
        $transcription['dialogueSegments'] = $dialogue['dialogueSegments'];

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

        [$relativeRunDirectory, $absoluteRunDirectory] = $this->makeRunDirectory();

        $transcription = $this->runPythonTranscriber(
            $absoluteAudioPath,
            $absoluteRunDirectory,
            $language,
            $processStarted,
            $progressUpdated,
        );
        $dialogueSourceSegments = is_array($transcription['speakerTurns'] ?? null) && $transcription['speakerTurns'] !== []
            ? $transcription['speakerTurns']
            : (is_array($transcription['segments'] ?? null) ? $transcription['segments'] : []);
        $dialogue = $this->speakerFormatter->format($dialogueSourceSegments);

        $transcription['dialogueText'] = $dialogue['dialogueText'];
        $transcription['dialogueSegments'] = $dialogue['dialogueSegments'];

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
        $baseDirectory = $this->transcriptionStorageDirectory();
        $relativeRunDirectory = $baseDirectory.'/'.date('Y/m/d').'/'.Str::uuid();

        Storage::disk('local')->makeDirectory($relativeRunDirectory);
        $absoluteBaseDirectory = Storage::disk('local')->path($baseDirectory);
        $absoluteRunDirectory = Storage::disk('local')->path($relativeRunDirectory);
        $this->ensureWritableRunDirectories($absoluteBaseDirectory, $absoluteRunDirectory);

        return [
            $relativeRunDirectory,
            $absoluteRunDirectory,
        ];
    }

    private function ensureWritableRunDirectories(string $absoluteBaseDirectory, string $absoluteRunDirectory): void
    {
        $directories = array_values(array_unique([
            $absoluteBaseDirectory,
            dirname(dirname(dirname($absoluteRunDirectory))),
            dirname(dirname($absoluteRunDirectory)),
            dirname($absoluteRunDirectory),
            $absoluteRunDirectory,
        ]));

        foreach ($directories as $index => $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $referenceDirectory = $index === 0
                ? dirname($directory)
                : ($directories[$index - 1] ?? dirname($directory));

            $this->syncDirectoryMetadata($directory, $referenceDirectory);
        }
    }

    private function syncDirectoryMetadata(string $directory, string $referenceDirectory): void
    {
        $referenceOwner = @fileowner($referenceDirectory);
        $referenceGroup = @filegroup($referenceDirectory);

        @chmod($directory, 02775);

        if ($referenceOwner !== false) {
            @chown($directory, $referenceOwner);
        }

        if ($referenceGroup !== false) {
            @chgrp($directory, $referenceGroup);
        }
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

        $contentType = $this->normalizeContentType((string) $response->header('Content-Type'));
        if ($contentType !== null && ! $this->isSupportedRemoteMediaContentType($contentType)) {
            Storage::disk('local')->delete($relativePath);

            if ($this->looksLikeHtmlContentType($contentType)) {
                throw new RuntimeException(
                    $this->isBinotelCabinetUrl($audioUrl)
                        ? 'Посилання з кабінету Binotel веде на HTML-сторінку, а не на запис дзвінка. Вставте пряме посилання на аудіофайл або завантажте файл вручну.'
                        : 'Посилання веде на веб-сторінку, а не на аудіофайл. Вставте пряме посилання на запис або завантажте файл вручну.'
                );
            }

            throw new RuntimeException(
                'Посилання не повертає аудіофайл. Потрібен прямий URL на mp3, wav, m4a, ogg, webm, mp4, aac, flac або opus.'
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

    private function normalizeContentType(string $contentType): ?string
    {
        $normalized = strtolower(trim(explode(';', $contentType)[0] ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function isSupportedRemoteMediaContentType(string $contentType): bool
    {
        if (str_starts_with($contentType, 'audio/') || str_starts_with($contentType, 'video/')) {
            return true;
        }

        return in_array($contentType, [
            'application/octet-stream',
            'binary/octet-stream',
            'application/mp4',
            'application/ogg',
        ], true);
    }

    private function looksLikeHtmlContentType(string $contentType): bool
    {
        return in_array($contentType, [
            'text/html',
            'application/xhtml+xml',
        ], true);
    }

    private function isBinotelCabinetUrl(string $audioUrl): bool
    {
        $host = strtolower((string) parse_url($audioUrl, PHP_URL_HOST));

        return str_contains($host, 'binotel.ua');
    }

    /**
     * @return array<string, mixed>
     */
    private function runPythonTranscriber(
        string $audioPath,
        string $absoluteRunDirectory,
        string $language,
        ?callable $processStarted = null,
        ?callable $progressUpdated = null,
    ): array {
        $selectedModel = $this->transcriptionSettings->currentModel();
        $initialPrompt = $this->transcriptionSettings->transcriptionInitialPrompt();
        $diarizationEnabled = $this->transcriptionSettings->speakerDiarizationEnabled();
        $diarizationToken = $this->resolveDiarizationToken($diarizationEnabled);
        $pythonBinary = $this->resolvePythonBinary();
        $cacheDirectory = Storage::disk('local')->path(
            trim((string) config('call_center.transcription.cache_dir'), '/')
        );
        $matplotlibCacheDirectory = rtrim($cacheDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'matplotlib';

        if (! is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0775, true);
        }

        if (! is_dir($matplotlibCacheDirectory)) {
            mkdir($matplotlibCacheDirectory, 0775, true);
        }

        $command = [
            $pythonBinary,
            (string) config('call_center.transcription.script_path'),
            '--audio-path',
            $audioPath,
            '--language',
            $language,
            '--model',
            $selectedModel,
            '--device',
            (string) config('call_center.transcription.device', 'cpu'),
            '--compute-type',
            (string) config('call_center.transcription.compute_type', 'int8'),
            '--beam-size',
            (string) config('call_center.transcription.beam_size', 5),
        ];

        if ($initialPrompt !== '') {
            $command[] = '--initial-prompt';
            $command[] = $initialPrompt;
        }

        if ((bool) config('call_center.transcription.word_timestamps', true)) {
            $command[] = '--word-timestamps';
        }

        if ((bool) config('call_center.transcription.vad_filter', true)) {
            $command[] = '--vad-filter';
        }

        if ($diarizationEnabled) {
            $command[] = '--diarization-enabled';
            $command[] = '--diarization-model';
            $command[] = (string) config(
                'call_center.transcription.diarization.provider_model',
                'pyannote/speaker-diarization-community-1',
            );

            $numSpeakers = (int) config('call_center.transcription.diarization.num_speakers', 2);
            if ($numSpeakers > 0) {
                $command[] = '--diarization-num-speakers';
                $command[] = (string) $numSpeakers;
            }

            $command[] = '--turn-merge-gap';
            $command[] = (string) config('call_center.transcription.diarization.merge_gap_seconds', 0.8);
        }

        $process = new Process($command, base_path(), [
            'HF_HOME' => $cacheDirectory,
            'TRANSFORMERS_CACHE' => $cacheDirectory,
            'XDG_CACHE_HOME' => $cacheDirectory,
            'MPLCONFIGDIR' => $matplotlibCacheDirectory,
            'HF_TOKEN' => $diarizationToken ?? '',
            'HUGGING_FACE_HUB_TOKEN' => $diarizationToken ?? '',
            'PYANNOTE_AUTH_TOKEN' => $diarizationToken ?? '',
        ]);

        $process->setTimeout((int) config('call_center.transcription.timeout_seconds', 1800));
        $process->start();

        if ($processStarted !== null) {
            $processStarted($process->getPid());
        }

        $stdout = '';
        $stderr = '';
        $stderrLineBuffer = '';

        $process->wait(function ($type, $buffer) use (&$stdout, &$stderr, &$stderrLineBuffer, $progressUpdated): void {
            if ($type === Process::OUT) {
                $stdout .= $buffer;

                return;
            }

            $stderrLineBuffer .= $buffer;

            while (($newlinePosition = strpos($stderrLineBuffer, "\n")) !== false) {
                $line = substr($stderrLineBuffer, 0, $newlinePosition);
                $stderrLineBuffer = substr($stderrLineBuffer, $newlinePosition + 1);
                $stderr .= $this->consumePythonStderrLine($line, $progressUpdated);
            }
        });

        if ($stderrLineBuffer !== '') {
            $stderr .= $this->consumePythonStderrLine($stderrLineBuffer, $progressUpdated);
        }

        $output = trim($stdout);
        $errorOutput = trim($stderr);
        $payload = json_decode($output, true);

        if (! is_array($payload)) {
            throw new RuntimeException(
                $errorOutput !== ''
                    ? $errorOutput
                    : 'Скрипт транскрибації повернув невалідний JSON.'
            );
        }

        if (! $process->isSuccessful() || ! ($payload['ok'] ?? false)) {
            throw new RuntimeException(
                (string) ($payload['error'] ?? $errorOutput ?: 'Не вдалося виконати faster-whisper.')
            );
        }

        return [
            'model' => $selectedModel,
            'text' => (string) ($payload['text'] ?? ''),
            'formattedText' => (string) ($payload['formatted_text'] ?? $payload['text'] ?? ''),
            'language' => (string) ($payload['language'] ?? 'auto'),
            'languageProbability' => $payload['language_probability'] ?? null,
            'duration' => $payload['duration'] ?? null,
            'segments' => is_array($payload['segments'] ?? null) ? $payload['segments'] : [],
            'speakerTurns' => is_array($payload['speaker_turns'] ?? null) ? $payload['speaker_turns'] : [],
            'speakerDiarization' => is_array($payload['speaker_diarization'] ?? null) ? $payload['speaker_diarization'] : null,
            'rawResultPath' => $absoluteRunDirectory.'/transcription-result.json',
        ];
    }

    private function consumePythonStderrLine(string $line, ?callable $progressUpdated = null): string
    {
        $trimmedLine = trim($line);

        if ($trimmedLine === '') {
            return '';
        }

        if (str_starts_with($trimmedLine, self::STDERR_PROGRESS_PREFIX)) {
            $payload = json_decode(substr($trimmedLine, strlen(self::STDERR_PROGRESS_PREFIX)), true);

            if (is_array($payload) && $progressUpdated !== null) {
                $progressUpdated($payload);
            }

            return '';
        }

        return $line.PHP_EOL;
    }

    private function resolvePythonBinary(): string
    {
        $configuredBinary = trim((string) config('call_center.transcription.python_binary', 'python3'));
        $venvBinary = '/opt/whisper-venv/bin/python3';

        if ($configuredBinary !== '' && $configuredBinary !== 'python3') {
            return $configuredBinary;
        }

        if (is_file($venvBinary) && is_executable($venvBinary)) {
            return $venvBinary;
        }

        return $configuredBinary !== '' ? $configuredBinary : 'python3';
    }

    private function resolveDiarizationToken(bool $diarizationEnabled): ?string
    {
        $token = $this->transcriptionSettings->speakerDiarizationToken();

        if ($diarizationEnabled && blank($token)) {
            throw new RuntimeException(
                'Увімкнене визначення автора реплік, але не задано Hugging Face token для pyannote. Додайте його в Налаштуваннях.'
            );
        }

        return $token;
    }

    protected function transcriptionStorageDirectory(): string
    {
        return trim((string) config('call_center.transcription.storage_dir', 'call-center/transcriptions'), '/');
    }
}
