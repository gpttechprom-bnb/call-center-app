<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BinotelApiCallCompleted;
use App\Services\AltCallCenterTranscriptionService;
use App\Services\BinotelCallAudioCacheService;
use App\Services\BinotelCallFeedbackStore;
use App\Services\BinotelCallRecordUrlResolver;
use App\Support\AltCallCenterTranscriptionTaskStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AltCallCenterTranscriptionController extends Controller
{
    public function __invoke(
        Request $request,
        AltCallCenterTranscriptionService $transcriptionService,
        AltCallCenterTranscriptionTaskStore $taskStore,
        BinotelCallFeedbackStore $feedbackStore,
        BinotelCallRecordUrlResolver $recordUrlResolver,
        BinotelCallAudioCacheService $audioCacheService,
    ): JsonResponse {
        ignore_user_abort(true);

        $maxUploadKb = (int) config('call_center.transcription.max_upload_kb', 102400);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'task_id' => ['nullable', 'string', 'max:120'],
            'general_call_id' => ['nullable', 'string', 'max:120'],
            'run_id' => ['nullable', 'string', 'max:120'],
            'force_reprocess' => ['nullable', 'boolean'],
            'audio_file' => ['nullable', 'file', 'max:'.$maxUploadKb],
            'audio_url' => ['nullable', 'url', 'max:2000'],
            'language' => ['nullable', Rule::in(['auto', 'uk', 'ru', 'en'])],
        ]);

        if (! $request->hasFile('audio_file') && blank($validated['audio_url'] ?? null)) {
            throw ValidationException::withMessages([
                'audio_file' => 'Додайте аудіофайл або вкажіть посилання на аудіо.',
            ]);
        }

        $generalCallId = trim((string) ($validated['general_call_id'] ?? ''));
        $runId = trim((string) ($validated['run_id'] ?? ''));
        $forceReprocess = (bool) ($validated['force_reprocess'] ?? false);
        $audioUrl = $validated['audio_url'] ?? null;
        $call = null;

        if ($generalCallId === '' && ! $request->hasFile('audio_file')) {
            $generalCallId = $this->generalCallIdFromAudioUrl((string) $audioUrl);
        }

        if (! $request->hasFile('audio_file') && $generalCallId !== '') {
            $call = BinotelApiCallCompleted::query()
                ->with('feedback')
                ->where('call_details_general_call_id', $generalCallId)
                ->first();

            if ($call !== null) {
                if (! $forceReprocess && $this->callAlreadyEvaluated($call)) {
                    $this->markCallCompleted($call);

                    return response()->json([
                        'message' => 'Цей дзвінок уже має оцінку. Whisper не запускався повторно, переходимо до наступного дзвінка.',
                        'already_processed' => true,
                        'call' => [
                            'id' => $call->id,
                            'generalCallId' => $call->call_details_general_call_id,
                        ],
                    ], 409);
                }

                $resolvedAudioUrl = $recordUrlResolver->resolve($call);
                if ($resolvedAudioUrl !== '') {
                    $audioUrl = $resolvedAudioUrl;
                }

                $this->markCallRunning($call);
            }
        }

        $language = (string) ($validated['language'] ?? 'auto');
        $taskId = trim((string) ($validated['task_id'] ?? ''));

        if ($taskId !== '' && $taskStore->find($taskId) === null) {
            throw ValidationException::withMessages([
                'task_id' => 'Службове завдання транскрибації не знайдено. Спробуйте запустити ще раз.',
            ]);
        }

        if ($taskId !== '') {
            $taskStore->markRunning($taskId);
        }

        $processStarted = static function (?int $processId) use ($taskId, $taskStore): void {
            if ($taskId === '') {
                return;
            }

            $taskStore->updateProcessId($taskId, $processId);
        };

        try {
            if ($call !== null && ! $request->hasFile('audio_file')) {
                $cachedAudio = $audioCacheService->ensureLocalCopy($call);

                if ($cachedAudio === null) {
                    throw new RuntimeException('Не вдалося підготувати локальний аудіофайл для транскрибації цього дзвінка.');
                }

                $result = $transcriptionService->transcribeStoredFile(
                    (string) $cachedAudio['absolute_path'],
                    (string) $cachedAudio['file_name'],
                    (string) $cachedAudio['relative_path'],
                    $language,
                    $processStarted,
                );
            } else {
                $result = $transcriptionService->transcribe(
                    $request->file('audio_file'),
                    $audioUrl,
                    $language,
                    $processStarted,
                );
            }
        } catch (RuntimeException $exception) {
            if (! $request->hasFile('audio_file') && $generalCallId !== '' && $this->shouldRetryWithFreshAudioUrl($exception)) {
                $call ??= BinotelApiCallCompleted::query()
                    ->with('feedback')
                    ->where('call_details_general_call_id', $generalCallId)
                    ->first();

                if ($call !== null) {
                    $freshAudioUrl = $recordUrlResolver->resolve($call, true);

                    if ($freshAudioUrl !== '' && $freshAudioUrl !== $audioUrl) {
                        try {
                            $cachedAudio = $audioCacheService->ensureLocalCopy($call, true);

                            if ($cachedAudio === null) {
                                throw new RuntimeException('Не вдалося повторно підготувати локальний аудіофайл для транскрибації.');
                            }

                            $result = $transcriptionService->transcribeStoredFile(
                                (string) $cachedAudio['absolute_path'],
                                (string) $cachedAudio['file_name'],
                                (string) $cachedAudio['relative_path'],
                                $language,
                                $processStarted,
                            );

                            if ($generalCallId !== '') {
                                if ($runId === '') {
                                    $runId = $feedbackStore->startRun($generalCallId, ['source_context' => 'alt_manual'])['run_id'];
                                }

                                $feedbackStore->storeTranscription($generalCallId, $result, $runId);
                            }

                            return response()->json([
                                'message' => 'Транскрибацію в alt-контурі виконано успішно.',
                                'task' => [
                                    'title' => $validated['title'] ?? 'Без назви',
                                    'source' => $result['source'],
                                ],
                                'run_id' => $runId !== '' ? $runId : null,
                                'transcription' => $result['transcription'],
                                'evaluation' => null,
                                'evaluation_error' => null,
                            ]);
                        } catch (RuntimeException $retryException) {
                            $exception = $retryException;
                        }
                    }
                }
            }

            if ($call !== null) {
                $this->markCallFailed($call, $exception->getMessage());
            }

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            if ($call !== null) {
                $this->markCallFailed($call, 'Не вдалося обробити аудіо. Перевірте налаштування оператора транскрибації та спробуйте ще раз.');
            }

            return response()->json([
                'message' => 'Не вдалося обробити аудіо. Перевірте налаштування оператора транскрибації та спробуйте ще раз.',
            ], 500);
        } finally {
            if ($taskId !== '') {
                $taskStore->clear($taskId);
            }
        }

        if ($generalCallId !== '') {
            if ($runId === '') {
                $runId = $feedbackStore->startRun($generalCallId, ['source_context' => 'alt_manual'])['run_id'];
            }

            $feedbackStore->storeTranscription($generalCallId, $result, $runId);
        }

        return response()->json([
            'message' => 'Транскрибацію в alt-контурі виконано успішно.',
            'task' => [
                'title' => $validated['title'] ?? 'Без назви',
                'source' => $result['source'],
            ],
            'run_id' => $runId !== '' ? $runId : null,
            'transcription' => $result['transcription'],
            'evaluation' => null,
            'evaluation_error' => null,
        ]);
    }

    private function generalCallIdFromAudioUrl(string $audioUrl): string
    {
        $path = (string) (parse_url($audioUrl, PHP_URL_PATH) ?: '');

        if ($path === '') {
            return '';
        }

        $filename = basename($path);

        return preg_match('/\A(\d{6,})\.(?:mp3|wav|m4a|ogg|webm|mp4|aac|flac|opus)\z/iu', $filename, $matches)
            ? (string) $matches[1]
            : '';
    }

    private function callAlreadyEvaluated(BinotelApiCallCompleted $call): bool
    {
        $feedback = $call->feedback;

        if ($feedback === null) {
            return false;
        }

        return in_array((string) ($feedback->evaluation_status ?? ''), ['pending', 'running', 'completed'], true)
            || $feedback->evaluation_score !== null
            || $feedback->evaluated_at !== null;
    }

    private function markCallCompleted(BinotelApiCallCompleted $call): void
    {
        if (! array_key_exists('alt_auto_status', $call->getAttributes())) {
            return;
        }

        $call->forceFill([
            'alt_auto_status' => 'completed',
            'alt_auto_error' => null,
            'alt_auto_finished_at' => now(),
        ])->save();
    }

    private function markCallRunning(BinotelApiCallCompleted $call): void
    {
        if (! array_key_exists('alt_auto_status', $call->getAttributes())) {
            return;
        }

        $call->forceFill([
            'alt_auto_status' => 'running',
            'alt_auto_error' => null,
            'alt_auto_started_at' => now(),
            'alt_auto_finished_at' => null,
        ])->save();
    }

    private function markCallFailed(BinotelApiCallCompleted $call, string $message): void
    {
        if (! array_key_exists('alt_auto_status', $call->getAttributes())) {
            return;
        }

        $call->forceFill([
            'alt_auto_status' => 'failed',
            'alt_auto_error' => $message,
            'alt_auto_finished_at' => now(),
        ])->save();
    }

    private function shouldRetryWithFreshAudioUrl(RuntimeException $exception): bool
    {
        return str_contains(
            mb_strtolower($exception->getMessage()),
            'не вдалося завантажити аудіо за посиланням'
        );
    }
}
