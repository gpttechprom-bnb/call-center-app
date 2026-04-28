<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\TerminatesTrackedProcess;
use App\Http\Controllers\Controller;
use App\Services\BinotelCallFeedbackStore;
use App\Support\AltCallCenterEvaluationJobStore;
use App\Support\CallCenterChecklistStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;

class AltCallCenterEvaluationController extends Controller
{
    use TerminatesTrackedProcess;

    public function store(
        Request $request,
        CallCenterChecklistStore $checklistStore,
        AltCallCenterEvaluationJobStore $jobStore,
        BinotelCallFeedbackStore $feedbackStore,
    ): JsonResponse {
        $validated = $request->validate([
            'transcription' => ['required', 'array'],
            'transcription.text' => ['nullable', 'string', 'max:400000'],
            'transcription.dialogueText' => ['nullable', 'string', 'max:400000'],
            'general_call_id' => ['nullable', 'string', 'max:120'],
            'checklist_id' => ['nullable', 'string', 'max:120'],
            'checklist_name' => ['nullable', 'string', 'max:150'],
            'checklist_type' => ['nullable', 'string', 'max:120'],
            'checklist_prompt' => ['nullable', 'string', 'max:10000'],
            'checklist_items' => ['nullable', 'array'],
            'checklist_items.*.label' => ['nullable', 'string', 'max:2000'],
            'checklist_items.*.max_points' => ['nullable', 'numeric', 'between:1,100'],
            'llm_settings' => ['nullable', 'array'],
            'llm_settings.provider' => ['nullable', 'string', 'max:120'],
            'llm_settings.model' => ['nullable', 'string', 'max:255'],
            'llm_settings.run_id' => ['nullable', 'string', 'max:120'],
            'llm_settings.evaluation_scenario' => ['nullable', 'string', 'in:stateless_single_item,sequential_chat,batch_single_prompt'],
            'llm_settings.system_prompt' => ['nullable', 'string', 'max:20000'],
            'llm_settings.thinking_enabled' => ['nullable', 'boolean'],
            'llm_settings.temperature' => ['nullable', 'numeric', 'between:0,2'],
            'llm_settings.num_ctx' => ['nullable', 'integer', 'between:256,131072'],
            'llm_settings.top_k' => ['nullable', 'integer', 'between:1,500'],
            'llm_settings.top_p' => ['nullable', 'numeric', 'between:0,1'],
            'llm_settings.repeat_penalty' => ['nullable', 'numeric', 'between:0,5'],
            'llm_settings.repetition_penalty' => ['nullable', 'numeric', 'between:0,5'],
            'llm_settings.num_predict' => ['nullable', 'integer', 'between:-1,32768'],
            'llm_settings.max_new_tokens' => ['nullable', 'integer', 'between:-1,32768'],
            'llm_settings.seed' => ['nullable', 'integer', 'between:-2147483648,2147483647'],
            'llm_settings.timeout_seconds' => ['nullable', 'integer', 'between:15,3600'],
        ]);

        $transcription = is_array($validated['transcription'] ?? null)
            ? $validated['transcription']
            : [];
        $generalCallId = trim((string) ($validated['general_call_id'] ?? ''));

        $dialogueText = trim((string) ($transcription['dialogueText'] ?? ''));
        $rawText = trim((string) ($transcription['text'] ?? ''));

        if ($dialogueText === '' && $rawText === '') {
            throw ValidationException::withMessages([
                'transcription' => 'Спочатку виконайте транскрибацію або передайте готовий текст дзвінка для оцінювання.',
            ]);
        }

        try {
            $checklist = $this->resolveChecklist($validated, $checklistStore);
            $activeJob = $jobStore->latestActiveJob();

            if ($activeJob !== null) {
                return response()->json([
                    'message' => 'Оцінювання вже виконується у фоновому режимі. Підключаємося до поточного завдання замість запуску нового.',
                    'job' => $jobStore->publicPayload($activeJob),
                    'reused_existing_job' => true,
                ], 202);
            }

            $job = $jobStore->create($transcription, $checklist, $validated['llm_settings'] ?? [], $generalCallId !== '' ? $generalCallId : null);
            $runId = trim((string) (($validated['llm_settings']['run_id'] ?? '')));

            if ($generalCallId !== '') {
                $feedbackStore->storeEvaluationRequested($generalCallId, $transcription, $checklist, (string) ($job['id'] ?? ''), true, $runId !== '' ? $runId : null);
            }

            $processId = $this->dispatchBackgroundJob((string) $job['id']);

            if ($processId !== null) {
                $jobStore->updateProcessId((string) $job['id'], $processId);
                $job = $jobStore->find((string) $job['id']) ?? $job;
            }
        } catch (RuntimeException $exception) {
            report($exception);

            if (isset($job) && is_array($job) && trim((string) ($job['id'] ?? '')) !== '') {
                $jobStore->markFailed((string) $job['id'], $exception->getMessage());
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'job' => isset($job) && is_array($job) && trim((string) ($job['id'] ?? '')) !== ''
                    ? $jobStore->publicPayload($jobStore->find((string) $job['id']) ?? $job)
                    : null,
            ], 500);
        }

        return response()->json([
            'message' => 'Оцінювання alt-контуру запущено у фоновому режимі.',
            'job' => $jobStore->publicPayload($job),
            'reused_existing_job' => false,
        ], 202);
    }

    public function show(string $jobId, AltCallCenterEvaluationJobStore $jobStore): JsonResponse
    {
        $job = $jobStore->find($jobId);

        if ($job === null) {
            return response()->json([
                'message' => 'Завдання оцінювання не знайдено.',
            ], 404);
        }

        return response()->json([
            'job' => $jobStore->publicPayload($job),
        ]);
    }

    public function destroy(string $jobId, AltCallCenterEvaluationJobStore $jobStore): JsonResponse
    {
        $job = $jobStore->find($jobId);

        if ($job !== null) {
            $this->terminateTrackedProcess(
                is_numeric($job['process_id'] ?? null)
                    ? (int) $job['process_id']
                    : null,
            );
            $jobStore->clear($jobId);
        }

        return response()->json([
            'message' => 'Фонове оцінювання зупинено та очищено.',
            'jobId' => $jobId,
            'cleared' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function resolveChecklist(array $validated, CallCenterChecklistStore $checklistStore): array
    {
        $checklistId = trim((string) ($validated['checklist_id'] ?? $checklistStore->defaultId()));
        $rawChecklistName = trim((string) ($validated['checklist_name'] ?? ''));
        $rawChecklistType = trim((string) ($validated['checklist_type'] ?? ''));
        $rawChecklistPrompt = trim((string) ($validated['checklist_prompt'] ?? ''));
        $rawChecklistItems = is_array($validated['checklist_items'] ?? null)
            ? array_values(array_filter($validated['checklist_items'], 'is_array'))
            : [];

        if ($rawChecklistName !== '' || $rawChecklistPrompt !== '' || $rawChecklistItems !== []) {
            try {
                return $checklistStore->normalizeChecklist([
                    'id' => $checklistId,
                    'name' => $rawChecklistName !== '' ? $rawChecklistName : 'Чек-лист',
                    'type' => $rawChecklistType !== '' ? $rawChecklistType : 'Загальний сценарій',
                    'prompt' => $rawChecklistPrompt,
                    'items' => $rawChecklistItems,
                ], $checklistId, []);
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages([
                    'checklist_items' => $exception->getMessage(),
                ]);
            }
        }

        $checklist = $checklistStore->find($checklistId);
        if ($checklist !== null) {
            return $checklist;
        }

        throw ValidationException::withMessages([
            'checklist_id' => 'Оберіть коректний чек-лист для оцінювання.',
        ]);
    }

    private function dispatchBackgroundJob(string $jobId): ?int
    {
        $phpBinary = $this->resolvePhpBinary();
        $basePath = base_path();
        $artisanPath = base_path('artisan');

        $command = sprintf(
            'cd %s && nohup %s %s call-center:alt-evaluate-job %s > /dev/null 2>&1 < /dev/null & echo $!',
            escapeshellarg($basePath),
            escapeshellarg($phpBinary),
            escapeshellarg($artisanPath),
            escapeshellarg($jobId),
        );

        $process = Process::fromShellCommandline($command, $basePath);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Не вдалося запустити фонове оцінювання дзвінка.');
        }

        $output = trim($process->getOutput());

        return ctype_digit($output) ? (int) $output : null;
    }

    private function resolvePhpBinary(): string
    {
        $binary = PHP_BINARY;

        if (str_contains(basename($binary), 'php-fpm')) {
            $cliBinary = rtrim(PHP_BINDIR, '/').'/php';

            return is_file($cliBinary) ? $cliBinary : 'php';
        }

        return $binary !== '' ? $binary : 'php';
    }
}
