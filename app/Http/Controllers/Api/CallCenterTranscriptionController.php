<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BinotelApiCallCompleted;
use App\Services\BinotelCallFeedbackStore;
use App\Services\BinotelCallRecordUrlResolver;
use App\Services\FasterWhisperTranscriptionService;
use App\Support\CallCenterTranscriptionTaskStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CallCenterTranscriptionController extends Controller
{
    public function __invoke(
        Request $request,
        FasterWhisperTranscriptionService $transcriptionService,
        CallCenterTranscriptionTaskStore $taskStore,
        BinotelCallFeedbackStore $feedbackStore,
        BinotelCallRecordUrlResolver $recordUrlResolver,
    ): JsonResponse {
        $maxUploadKb = (int) config('call_center.transcription.max_upload_kb', 102400);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'task_id' => ['nullable', 'string', 'max:120'],
            'general_call_id' => ['nullable', 'string', 'max:120'],
            'run_id' => ['nullable', 'string', 'max:120'],
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
        $audioUrl = $validated['audio_url'] ?? null;

        if (! $request->hasFile('audio_file') && $generalCallId !== '') {
            $call = BinotelApiCallCompleted::query()
                ->where('call_details_general_call_id', $generalCallId)
                ->first();

            if ($call !== null) {
                $resolvedAudioUrl = $recordUrlResolver->resolve($call);

                if ($resolvedAudioUrl !== '') {
                    $audioUrl = $resolvedAudioUrl;
                }
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

        try {
            $result = $transcriptionService->transcribe(
                $request->file('audio_file'),
                $audioUrl,
                $language,
                static function (?int $processId) use ($taskId, $taskStore): void {
                    if ($taskId === '') {
                        return;
                    }

                    $taskStore->updateProcessId($taskId, $processId);
                },
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Не вдалося обробити аудіо. Перевірте налаштування faster-whisper та спробуйте ще раз.',
            ], 500);
        } finally {
            if ($taskId !== '') {
                $taskStore->clear($taskId);
            }
        }

        $runId = trim((string) ($validated['run_id'] ?? ''));

        if ($generalCallId !== '') {
            if ($runId === '') {
                $runId = $feedbackStore->startRun($generalCallId, ['source_context' => 'manual'])['run_id'];
            }

            $feedbackStore->storeTranscription($generalCallId, $result, $runId);
        }

        return response()->json([
            'message' => 'Транскрибацію виконано успішно.',
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
}
