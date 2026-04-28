<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Services\ProcessTreeTerminator;

trait TerminatesTrackedProcess
{
    protected function terminateTrackedProcess(?int $processId): void
    {
        app(ProcessTreeTerminator::class)->terminate($processId);
    }
}
