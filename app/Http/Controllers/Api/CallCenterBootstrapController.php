<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CallCenterChecklistStore;
use App\Support\CallCenterEvaluationJobStore;
use App\Support\CallCenterLlmPrompts;
use App\Support\CallCenterPageData;
use App\Support\CallCenterTranscriptionSettings;
use Illuminate\Http\JsonResponse;

class CallCenterBootstrapController extends Controller
{
    public function __invoke(
        CallCenterPageData $pageData,
        CallCenterChecklistStore $checklistStore,
        CallCenterTranscriptionSettings $transcriptionSettings,
        CallCenterEvaluationJobStore $jobStore,
    ): JsonResponse {
        $activeJob = $jobStore->latestActiveJob();

        return response()
            ->json($pageData->build(
                $checklistStore,
                $transcriptionSettings,
                [
                    'checklistsEndpoint' => url('/api/call-center/checklists'),
                    'transcriptionEndpoint' => url('/api/call-center/transcriptions'),
                    'transcriptionTaskEndpoint' => url('/api/call-center/transcriptions/tasks'),
                    'transcriptionAiRewriteEndpoint' => url('/api/call-center/transcriptions/ai-rewrite'),
                    'transcriptionEvaluationEndpoint' => url('/api/call-center/evaluations'),
                    'transcriptionSettingsEndpoint' => url('/api/call-center/settings'),
                    'pageBootstrapEndpoint' => url('/api/call-center/bootstrap'),
                    'callAudioEndpoint' => url('/api/call-center/calls/__CALL_ID__/audio-url'),
                    'transcriptionLlmSystemPrompt' => CallCenterLlmPrompts::structuredEvaluationSystemPrompt(),
                    'activeEvaluationJob' => $activeJob !== null ? $jobStore->publicPayload($activeJob) : null,
                ]
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
