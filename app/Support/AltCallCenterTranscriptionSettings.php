<?php

namespace App\Support;

class AltCallCenterTranscriptionSettings extends CallCenterTranscriptionSettings
{
    protected function settingsPath(): string
    {
        return trim((string) config('call_center.transcription.alt_settings_path', 'call-center/alt/settings.json'), '/');
    }
}
