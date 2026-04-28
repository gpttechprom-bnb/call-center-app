<?php

namespace App\Http\Controllers;

use App\Support\AltCallCenterTranscriptionSettings;
use App\Support\AltCallCenterAutomationStore;
use App\Support\AltCallCenterAutomationWindow;
use App\Support\AltCallCenterEvaluationJobStore;
use App\Support\CallCenterChecklistStore;
use App\Support\CallCenterLlmPrompts;
use App\Support\CallCenterPageData;
use Illuminate\Http\Response;

class AltCallCenterController extends Controller
{
    public function __invoke(
        CallCenterPageData $pageData,
        CallCenterChecklistStore $checklistStore,
        AltCallCenterTranscriptionSettings $transcriptionSettings,
        AltCallCenterEvaluationJobStore $jobStore,
        AltCallCenterAutomationStore $automationStore,
        AltCallCenterAutomationWindow $automationWindow,
    ): Response
    {
        $activeJob = $jobStore->latestActiveJob();

        return response()->view('alt.call-center', $pageData->build(
            $checklistStore,
            $transcriptionSettings,
            [
                'checklistsEndpoint' => url('/api/alt/call-center/checklists'),
                'transcriptionEndpoint' => url('/api/alt/call-center/transcriptions'),
                'transcriptionTaskEndpoint' => url('/api/alt/call-center/transcriptions/tasks'),
                'transcriptionAiRewriteEndpoint' => url('/api/alt/call-center/transcriptions/ai-rewrite'),
                'transcriptionEvaluationEndpoint' => url('/api/alt/call-center/evaluations'),
                'transcriptionSettingsEndpoint' => url('/api/alt/call-center/settings'),
                'pageBootstrapEndpoint' => url('/api/alt/call-center/bootstrap'),
                'callAudioEndpoint' => url('/api/alt/call-center/calls/__CALL_ID__/audio-url'),
                'callForceProcessEndpoint' => url('/api/alt/call-center/calls/__CALL_ID__/force-process'),
                'automationEndpoint' => url('/api/alt/call-center/automation'),
                'automationPlayEndpoint' => url('/api/alt/call-center/automation/play'),
                'automationPauseEndpoint' => url('/api/alt/call-center/automation/pause'),
                'automationSettingsEndpoint' => url('/api/alt/call-center/automation/settings'),
                'automationNextFirstCallEndpoint' => url('/api/alt/call-center/automation/next-first-call'),
                'automationState' => $automationStore->stateWithWindow($automationWindow),
                'transcriptionLlmSystemPrompt' => CallCenterLlmPrompts::sequentialEvaluationSystemPrompt(),
                'activeEvaluationJob' => $activeJob !== null ? $jobStore->publicPayload($activeJob) : null,
                'binotelFeedbackApiKeyPreview' => $this->maskApiKey(
                    trim((string) config('binotel.feedback.api_key', ''))
                ),
                'binotelFeedbackApiKeyConfigured' => trim((string) config('binotel.feedback.api_key', '')) !== '',
            ]
        ), 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function maskApiKey(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $length = mb_strlen($trimmed, 'UTF-8');
        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        $start = mb_substr($trimmed, 0, min(4, $length), 'UTF-8');
        $end = mb_substr($trimmed, max(0, $length - 3), null, 'UTF-8');

        return $start.str_repeat('•', max(4, $length - 7)).$end;
    }
}
