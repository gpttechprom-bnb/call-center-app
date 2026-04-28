<?php

namespace App\Services;

use App\Support\AltCallCenterTranscriptionSettings;
use Illuminate\Http\UploadedFile;

class AltCallCenterTranscriptionService
{
    public function __construct(
        private readonly AltCallCenterTranscriptionSettings $transcriptionSettings,
        private readonly AltFasterWhisperTranscriptionService $fasterWhisper,
        private readonly AltOpenAiCompatibleTranscriptionService $remoteTranscription,
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
        if ($this->transcriptionSettings->transcriptionProvider() === 'faster_whisper') {
            return $this->fasterWhisper->transcribe(
                $audioFile,
                $audioUrl,
                $language,
                $processStarted,
                $progressUpdated,
            );
        }

        return $this->remoteTranscription->transcribe(
            $audioFile,
            $audioUrl,
            $language,
            $processStarted,
            $progressUpdated,
        );
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
        if ($this->transcriptionSettings->transcriptionProvider() === 'faster_whisper') {
            return $this->fasterWhisper->transcribeStoredFile(
                $absoluteAudioPath,
                $sourceName,
                $sourceRelativePath,
                $language,
                $processStarted,
                $progressUpdated,
            );
        }

        return $this->remoteTranscription->transcribeStoredFile(
            $absoluteAudioPath,
            $sourceName,
            $sourceRelativePath,
            $language,
            $processStarted,
            $progressUpdated,
        );
    }
}
