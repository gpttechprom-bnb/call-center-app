<?php

namespace App\Services;

use App\Support\AltCallCenterTranscriptionSettings;

class AltFasterWhisperTranscriptionService extends FasterWhisperTranscriptionService
{
    public function __construct(
        CallCenterSpeakerFormatter $speakerFormatter,
        AltCallCenterTranscriptionSettings $transcriptionSettings,
    ) {
        parent::__construct($speakerFormatter, $transcriptionSettings);
    }

    protected function transcriptionStorageDirectory(): string
    {
        return trim((string) config('call_center.transcription.alt_storage_dir', 'call-center/alt/transcriptions'), '/');
    }
}
