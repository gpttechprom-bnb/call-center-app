<?php

namespace App\Support;

class AltCallCenterTranscriptionTaskStore extends CallCenterTranscriptionTaskStore
{
    protected function path(string $taskId): string
    {
        return trim((string) config('call_center.transcription.alt_tasks_dir', 'call-center/alt/transcription-tasks'), '/')
            .'/'.trim($taskId).'.json';
    }
}
