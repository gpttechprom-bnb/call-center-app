<?php

namespace App\Console\Commands;

use App\Services\AltCallCenterChecklistEvaluator;
use App\Services\BinotelCallFeedbackStore;
use App\Support\AltCallCenterEvaluationJobStore;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class RunAltCallCenterEvaluationJob extends Command
{
    protected $signature = 'call-center:alt-evaluate-job {jobId}';

    protected $description = 'Run a background alt call-center checklist evaluation job';

    public function handle(
        AltCallCenterEvaluationJobStore $jobStore,
        AltCallCenterChecklistEvaluator $evaluator,
        BinotelCallFeedbackStore $feedbackStore,
    ): int {
        $jobId = trim((string) $this->argument('jobId'));
        $job = $jobStore->find($jobId);

        if ($job === null) {
            $this->error('Alt evaluation job not found.');

            return self::FAILURE;
        }

        if (($job['status'] ?? null) === 'completed') {
            return self::SUCCESS;
        }

        $jobStore->markRunning($jobId);
        $jobStore->updateProcessId($jobId, getmypid());
        $generalCallId = trim((string) ($job['general_call_id'] ?? ''));
        $llmSettings = is_array($job['llm_settings'] ?? null) ? $job['llm_settings'] : [];
        $runId = trim((string) ($llmSettings['run_id'] ?? ''));

        if ($generalCallId !== '') {
            $feedbackStore->markEvaluationRunning($generalCallId, $jobId, $runId !== '' ? $runId : null);
        }

        try {
            $transcription = is_array($job['transcription'] ?? null) ? $job['transcription'] : [];
            $checklist = is_array($job['checklist'] ?? null) ? $job['checklist'] : [];

            if ($transcription === [] || $checklist === []) {
                throw new RuntimeException('Фонове оцінювання не отримало повних даних транскрипту або чек-листа.');
            }

            $jobStore->appendLog($jobId, 'Отримано транскрипт і чек-лист. Передаємо дані в сервіс оцінювання.');

            $latestThinking = '';
            $latestResponse = '';
            $latestSystemPrompt = '';
            $latestPrompt = '';
            $latestPhase = 'running';
            $lastTraceFlushAt = microtime(true);
            $lastThinkingLength = 0;
            $lastResponseLength = 0;

            $flushTrace = static function (bool $force = false) use (
                $jobStore,
                $jobId,
                &$latestThinking,
                &$latestResponse,
                &$latestSystemPrompt,
                &$latestPrompt,
                &$latestPhase,
                &$lastTraceFlushAt,
                &$lastThinkingLength,
                &$lastResponseLength,
            ): void {
                $thinkingLength = mb_strlen($latestThinking, 'UTF-8');
                $responseLength = mb_strlen($latestResponse, 'UTF-8');
                $hasMeaningfulChange = abs($thinkingLength - $lastThinkingLength) >= 60
                    || abs($responseLength - $lastResponseLength) >= 60;
                $cooldownPassed = (microtime(true) - $lastTraceFlushAt) >= 0.8;

                if (! $force && ! ($hasMeaningfulChange && $cooldownPassed)) {
                    return;
                }

                $jobStore->updateLlmTrace(
                    $jobId,
                    $latestThinking,
                    $latestResponse,
                    $latestPhase !== '' ? $latestPhase : 'streaming',
                    $latestSystemPrompt,
                    $latestPrompt,
                );
                $lastTraceFlushAt = microtime(true);
                $lastThinkingLength = $thinkingLength;
                $lastResponseLength = $responseLength;
            };

            $reporter = static function (array $event) use (
                $jobStore,
                $jobId,
                &$latestThinking,
                &$latestResponse,
                &$latestSystemPrompt,
                &$latestPrompt,
                &$latestPhase,
                $flushTrace,
            ): void {
                $type = (string) ($event['type'] ?? 'log');

                if ($type === 'prompt') {
                    $latestSystemPrompt = (string) ($event['system_prompt'] ?? '');
                    $latestPrompt = (string) ($event['prompt'] ?? '');
                    $jobStore->updateLlmTrace(
                        $jobId,
                        $latestThinking,
                        $latestResponse,
                        $latestPhase !== '' ? $latestPhase : 'prompt_prepared',
                        $latestSystemPrompt,
                        $latestPrompt,
                    );

                    return;
                }

                if ($type === 'thinking') {
                    $latestThinking = (string) ($event['text'] ?? '');
                    $jobStore->updateLlmTrace(
                        $jobId,
                        $latestThinking,
                        $latestResponse,
                        $latestPhase !== '' ? $latestPhase : 'running',
                        $latestSystemPrompt,
                        $latestPrompt,
                    );

                    return;
                }

                if ($type === 'response') {
                    $latestResponse = (string) ($event['text'] ?? '');
                    $jobStore->updateLlmTrace(
                        $jobId,
                        $latestThinking,
                        $latestResponse,
                        $latestPhase !== '' ? $latestPhase : 'running',
                        $latestSystemPrompt,
                        $latestPrompt,
                    );

                    return;
                }

                if ($type === 'phase') {
                    $phase = trim((string) ($event['phase'] ?? ''));

                    if ($phase !== '') {
                        $latestPhase = $phase;
                        $jobStore->updateLlmTrace(
                            $jobId,
                            $latestThinking,
                            $latestResponse,
                            $phase,
                            $latestSystemPrompt,
                            $latestPrompt,
                        );
                    }

                    $message = trim((string) ($event['message'] ?? ''));
                    if ($message !== '') {
                        $jobStore->appendLog($jobId, $message, trim((string) ($event['channel'] ?? 'status')) ?: 'status');
                    }

                    return;
                }

                $message = trim((string) ($event['message'] ?? ''));
                if ($message !== '') {
                    $jobStore->appendLog(
                        $jobId,
                        $message,
                        trim((string) ($event['channel'] ?? 'status')) ?: 'status',
                    );
                }
            };

            $evaluation = $evaluator->evaluateInBackground($transcription, $checklist, $reporter, $llmSettings);
            $flushTrace(true);
            $jobStore->updateLlmTrace(
                $jobId,
                $latestThinking,
                $latestResponse,
                'completed',
                $latestSystemPrompt,
                $latestPrompt,
            );
            $jobStore->markCompleted($jobId, $evaluation);

            if ($generalCallId !== '') {
                $feedbackStore->storeEvaluationResult($generalCallId, $evaluation, $jobId, $runId !== '' ? $runId : null);
            }
        } catch (Throwable $exception) {
            $jobStore->updateLlmTrace($jobId, null, null, 'failed');
            $errorMessage = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Не вдалося виконати фонове оцінювання дзвінка через LLM.';
            $jobStore->markFailed($jobId, $errorMessage);
            $this->error($errorMessage);

            if ($generalCallId !== '') {
                $feedbackStore->markEvaluationFailed($generalCallId, $errorMessage, $jobId, $runId !== '' ? $runId : null);
            }

            report($exception);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
