<?php

namespace App\Support;

class AltCallCenterChecklistStore extends CallCenterChecklistStore
{
    protected function storagePath(): string
    {
        return trim((string) config('call_center.checklists.alt_storage_path', 'call-center/alt/checklists.json'), '/');
    }
}
