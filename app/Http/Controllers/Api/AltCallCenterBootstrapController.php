<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AltCallCenterAutomationStore;
use App\Support\AltCallCenterAutomationWindow;
use App\Support\AltCallCenterEvaluationJobStore;
use App\Support\AltCallCenterTranscriptionSettings;
use App\Support\CallCenterChecklistStore;
use App\Support\CallCenterLlmPrompts;
use App\Support\CallCenterPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AltCallCenterBootstrapController extends Controller
{
    public function __invoke(
        Request $request,
        CallCenterPageData $pageData,
        CallCenterChecklistStore $checklistStore,
        AltCallCenterTranscriptionSettings $transcriptionSettings,
        AltCallCenterEvaluationJobStore $jobStore,
        AltCallCenterAutomationStore $automationStore,
        AltCallCenterAutomationWindow $automationWindow,
    ): JsonResponse {
        $activeJob = $jobStore->latestActiveJob();

        return response()
            ->json($pageData->build(
                $checklistStore,
                $transcriptionSettings,
                [
                    'checklistsEndpoint' => url('/api/alt/call-center/checklists'),
                    'transcriptionEndpoint' => url('/api/alt/call-center/transcriptions'),
                    'transcriptionTaskEndpoint' => url('/api/alt/call-center/transcriptions/tasks'),
                    'transcriptionAiRewriteEndpoint' => url('/api/alt/call-center/transcriptions/ai-rewrite'),
                    'transcriptionEvaluationEndpoint' => url('/api/alt/call-center/evaluations'),
                    'transcriptionSettingsEndpoint' => url('/api/alt/call-center/settings'),
                    'callsTableColumnsPreferenceEndpoint' => url('/api/alt/call-center/ui-preferences/calls-table-columns'),
                    'callsListingEndpoint' => url('/api/alt/call-center/calls'),
                    'managersSummaryEndpoint' => url('/api/alt/call-center/managers/summary'),
                    'pageBootstrapEndpoint' => url('/api/alt/call-center/bootstrap'),
                    'callAudioEndpoint' => url('/api/alt/call-center/calls/__CALL_ID__/audio-url'),
                    'callForceProcessEndpoint' => url('/api/alt/call-center/calls/__CALL_ID__/force-process'),
                    'automationEndpoint' => url('/api/alt/call-center/automation'),
                    'automationCalendarStatsEndpoint' => url('/api/alt/call-center/automation/calendar-stats'),
                    'automationPlayEndpoint' => url('/api/alt/call-center/automation/play'),
                    'automationPauseEndpoint' => url('/api/alt/call-center/automation/pause'),
                    'automationSettingsEndpoint' => url('/api/alt/call-center/automation/settings'),
                    'automationNextFirstCallEndpoint' => url('/api/alt/call-center/automation/next-first-call'),
                    'automationState' => $automationStore->stateWithWindow($automationWindow),
                    'transcriptionLlmSystemPrompt' => CallCenterLlmPrompts::sequentialEvaluationSystemPrompt(),
                    'activeEvaluationJob' => $activeJob !== null ? $jobStore->publicPayload($activeJob) : null,
                ],
                $request->query('calls_version'),
                false
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
