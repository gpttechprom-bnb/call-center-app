<?php

namespace App\Support;

class AltCallCenterEvaluationJobStore extends CallCenterEvaluationJobStore
{
    protected function jobsDirectory(): string
    {
        return trim((string) config('call_center.evaluation.alt_jobs_dir', 'call-center/alt/evaluation-jobs'), '/');
    }
}
