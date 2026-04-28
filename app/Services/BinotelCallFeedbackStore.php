<?php

namespace App\Services;

use App\Models\BinotelApiCallCompleted;
use App\Models\BinotelCallFeedback;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BinotelCallFeedbackStore
{
    private static ?bool $supportsComparisonRuns = null;

    /**
     * @param  array<string, mixed>  $context
     * @return array{feedback:BinotelCallFeedback,run_id:string}
     */
    public function startRun(string $generalCallId, array $context = []): array
    {
        $generalCallId = trim($generalCallId);

        if ($generalCallId === '') {
            throw new \InvalidArgumentException('General Call ID is required to start a comparison run.');
        }

        $feedback = $this->firstOrNew($generalCallId);
        $this->syncCallReference($feedback);
        $feedback->fill($this->clearedEvaluationAttributes());

        if (! $this->supportsComparisonRuns()) {
            $feedback->save();

            return [
                'feedback' => $feedback->fresh(['call']),
                'run_id' => '',
            ];
        }

        $runs = $this->ensureComparisonRunsInitialized($feedback);
        $runId = (string) Str::uuid();
        $nextOrder = $this->nextRunOrder($runs);
        $now = now()->toIso8601String();

        $runs[] = [
            'id' => $runId,
            'order' => $nextOrder,
            'created_at' => $now,
            'updated_at' => $now,
            'source_context' => trim((string) ($context['source_context'] ?? 'manual')) ?: 'manual',
            'transcription_status' => null,
            'transcription_source_type' => null,
            'transcription_source_name' => null,
            'transcription_source_relative_path' => null,
            'transcription_storage_run_directory' => null,
            'transcription_language' => null,
            'transcription_model' => null,
            'transcription_text' => null,
            'transcription_dialogue_text' => null,
            'transcription_formatted_text' => null,
            'transcription_payload' => null,
            'transcribed_at' => null,
            'evaluation_status' => null,
            'last_evaluation_job_id' => null,
            'evaluation_checklist_id' => null,
            'evaluation_checklist_name' => null,
            'evaluation_score' => null,
            'evaluation_total_points' => null,
            'evaluation_score_percent' => null,
            'evaluation_summary' => null,
            'evaluation_strong_side' => null,
            'evaluation_focus' => null,
            'evaluation_provider' => null,
            'evaluation_model' => null,
            'evaluation_payload' => null,
            'evaluation_requested_at' => null,
            'evaluated_at' => null,
            'error_message' => null,
        ];

        $feedback->forceFill([
            'comparison_runs' => $runs,
            'active_comparison_run_id' => $runId,
        ])->save();

        return [
            'feedback' => $feedback->fresh(['call']),
            'run_id' => $runId,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function storeTranscription(string $generalCallId, array $result, ?string $runId = null): ?BinotelCallFeedback
    {
        $generalCallId = trim($generalCallId);

        if ($generalCallId === '') {
            return null;
        }

        $feedback = $this->firstOrNew($generalCallId);
        $transcription = is_array($result['transcription'] ?? null)
            ? $result['transcription']
            : [];
        $source = is_array($result['source'] ?? null)
            ? $result['source']
            : [];

        $this->syncCallReference($feedback);
        $runId = $this->resolveRunId($feedback, $runId);

        $attributes = [
            'transcription_status' => 'completed',
            'transcription_source_type' => $this->nullableString($source['type'] ?? null, 255),
            'transcription_source_name' => $this->nullableString($source['name'] ?? null, 255),
            'transcription_source_relative_path' => $this->nullableString($source['relativePath'] ?? null, 255),
            'transcription_storage_run_directory' => $this->nullableString($result['storageRunDirectory'] ?? null, 255),
            'transcription_language' => $this->nullableString($transcription['language'] ?? null, 255),
            'transcription_model' => $this->nullableString($transcription['model'] ?? null, 255),
            'transcription_text' => $this->nullableString($transcription['text'] ?? null),
            'transcription_dialogue_text' => $this->nullableString($transcription['dialogueText'] ?? null),
            'transcription_formatted_text' => $this->nullableString($transcription['formattedText'] ?? null),
            'transcription_payload' => $transcription !== [] ? $transcription : null,
            'transcribed_at' => now(),
            'error_message' => null,
        ];

        $feedback->fill($attributes);
        $this->updateRun($feedback, $runId, function (array $run) use ($attributes): array {
            foreach ($attributes as $key => $value) {
                $run[$key] = $value instanceof \DateTimeInterface ? $value->toIso8601String() : $value;
            }

            return $run;
        });

        $feedback->save();
        $this->syncCallAutoStatus($feedback, 'running');

        return $feedback->fresh(['call']);
    }

    public function markEvaluationSkipped(string $generalCallId, ?string $runId = null): ?BinotelCallFeedback
    {
        $feedback = $this->findByGeneralCallId($generalCallId);

        if ($feedback === null) {
            return null;
        }

        $this->syncCallReference($feedback);
        $runId = $this->resolveRunId($feedback, $runId);
        $attributes = $this->clearedEvaluationAttributes();

        $feedback->fill($attributes);
        $this->updateRun($feedback, $runId, function (array $run) use ($attributes): array {
            foreach ($attributes as $key => $value) {
                $run[$key] = $value;
            }

            return $run;
        });

        if ($this->supportsComparisonRuns()) {
            $feedback->active_comparison_run_id = null;
        }

        $feedback->save();

        return $feedback->fresh(['call']);
    }

    /**
     * @param  array<string, mixed>  $transcription
     * @param  array<string, mixed>  $checklist
     */
    public function storeEvaluationRequested(
        string $generalCallId,
        array $transcription,
        array $checklist,
        string $jobId,
        bool $persistTranscription = true,
        ?string $runId = null,
    ): ?BinotelCallFeedback {
        $generalCallId = trim($generalCallId);

        if ($generalCallId === '') {
            return null;
        }

        $feedback = $this->firstOrNew($generalCallId);
        $this->syncCallReference($feedback);
        $runId = $this->resolveRunId($feedback, $runId);

        if ($persistTranscription) {
            $this->fillTranscriptionFieldsFromPayload($feedback, $transcription, $runId);
        }

        $attributes = [
            'evaluation_status' => 'pending',
            'last_evaluation_job_id' => $this->nullableString($jobId, 255),
            'evaluation_checklist_id' => $this->nullableString($checklist['id'] ?? null, 255),
            'evaluation_checklist_name' => $this->nullableString($checklist['name'] ?? null, 255),
            'evaluation_requested_at' => now(),
            'error_message' => null,
        ];

        $feedback->fill($attributes);
        $this->updateRun($feedback, $runId, function (array $run) use ($attributes): array {
            foreach ($attributes as $key => $value) {
                $run[$key] = $value instanceof \DateTimeInterface ? $value->toIso8601String() : $value;
            }

            return $run;
        });

        $feedback->save();
        $this->syncCallAutoStatus($feedback, 'running');

        return $feedback->fresh(['call']);
    }

    public function markEvaluationRunning(string $generalCallId, ?string $jobId = null, ?string $runId = null): ?BinotelCallFeedback
    {
        $feedback = $this->findByGeneralCallId($generalCallId);

        if ($feedback === null) {
            return null;
        }

        $this->syncCallReference($feedback);
        $runId = $this->resolveRunId($feedback, $runId);

        $attributes = [
            'evaluation_status' => 'running',
            'last_evaluation_job_id' => $this->nullableString($jobId, 255) ?? $feedback->last_evaluation_job_id,
            'error_message' => null,
        ];

        $feedback->fill($attributes);
        $this->updateRun($feedback, $runId, function (array $run) use ($attributes): array {
            foreach ($attributes as $key => $value) {
                $run[$key] = $value;
            }

            return $run;
        });

        $feedback->save();
        $this->syncCallAutoStatus($feedback, 'running');

        return $feedback->fresh(['call']);
    }

    /**
     * @param  array<string, mixed>  $evaluation
     */
    public function storeEvaluationResult(string $generalCallId, array $evaluation, ?string $jobId = null, ?string $runId = null): ?BinotelCallFeedback
    {
        $feedback = $this->findByGeneralCallId($generalCallId);

        if ($feedback === null) {
            return null;
        }

        $this->syncCallReference($feedback);
        $runId = $this->resolveRunId($feedback, $runId);

        $attributes = [
            'evaluation_status' => 'completed',
            'last_evaluation_job_id' => $this->nullableString($jobId, 255) ?? $feedback->last_evaluation_job_id,
            'evaluation_checklist_id' => $this->nullableString($evaluation['checklistId'] ?? null, 255) ?? $feedback->evaluation_checklist_id,
            'evaluation_checklist_name' => $this->nullableString($evaluation['checklistName'] ?? null, 255) ?? $feedback->evaluation_checklist_name,
            'evaluation_score' => $this->nullableInt($evaluation['score'] ?? null),
            'evaluation_total_points' => $this->nullableInt($evaluation['totalPoints'] ?? null),
            'evaluation_score_percent' => $this->nullableInt($evaluation['scorePercent'] ?? null),
            'evaluation_summary' => $this->nullableString($evaluation['summary'] ?? null),
            'evaluation_strong_side' => $this->nullableString($evaluation['strongSide'] ?? null),
            'evaluation_focus' => $this->nullableString($evaluation['focus'] ?? null),
            'evaluation_provider' => $this->nullableString($evaluation['provider'] ?? null, 255),
            'evaluation_model' => $this->nullableString($evaluation['model'] ?? null, 255),
            'evaluation_payload' => $evaluation !== [] ? $evaluation : null,
            'evaluated_at' => now(),
            'error_message' => null,
        ];

        $feedback->fill($attributes);
        $this->updateRun($feedback, $runId, function (array $run) use ($attributes): array {
            foreach ($attributes as $key => $value) {
                $run[$key] = $value instanceof \DateTimeInterface ? $value->toIso8601String() : $value;
            }

            return $run;
        });

        if ($this->supportsComparisonRuns()) {
            $feedback->active_comparison_run_id = null;
        }

        $feedback->save();
        $this->syncCallAutoStatus($feedback, 'completed');

        return $feedback->fresh(['call']);
    }

    public function markEvaluationFailed(string $generalCallId, string $message, ?string $jobId = null, ?string $runId = null): ?BinotelCallFeedback
    {
        $feedback = $this->findByGeneralCallId($generalCallId);

        if ($feedback === null) {
            return null;
        }

        $this->syncCallReference($feedback);
        $runId = $this->resolveRunId($feedback, $runId);

        $attributes = [
            'evaluation_status' => 'failed',
            'last_evaluation_job_id' => $this->nullableString($jobId, 255) ?? $feedback->last_evaluation_job_id,
            'error_message' => $this->nullableString($message),
        ];

        $feedback->fill($attributes);
        $this->updateRun($feedback, $runId, function (array $run) use ($attributes): array {
            foreach ($attributes as $key => $value) {
                $run[$key] = $value;
            }

            return $run;
        });

        if ($this->supportsComparisonRuns()) {
            $feedback->active_comparison_run_id = null;
        }

        $feedback->save();
        $this->syncCallAutoStatus($feedback, 'failed', $message);

        return $feedback->fresh(['call']);
    }

    private function findByGeneralCallId(string $generalCallId): ?BinotelCallFeedback
    {
        $normalized = trim($generalCallId);

        if ($normalized === '') {
            return null;
        }

        return BinotelCallFeedback::query()
            ->where('general_call_id', $normalized)
            ->first();
    }

    private function firstOrNew(string $generalCallId): BinotelCallFeedback
    {
        return $this->findByGeneralCallId($generalCallId)
            ?? new BinotelCallFeedback([
                'general_call_id' => trim($generalCallId),
            ]);
    }

    private function syncCallReference(BinotelCallFeedback $feedback): void
    {
        $call = BinotelApiCallCompleted::query()
            ->where('call_details_general_call_id', $feedback->general_call_id)
            ->first();

        if ($call === null) {
            return;
        }

        $feedback->binotel_api_call_completed_id = $call->id;
        $feedback->call_id = $call->call_details_call_id;
    }

    private function syncCallAutoStatus(BinotelCallFeedback $feedback, string $status, ?string $error = null): void
    {
        if (
            $feedback->binotel_api_call_completed_id === null
            || ! Schema::hasColumn('binotel_api_call_completeds', 'alt_auto_status')
        ) {
            return;
        }

        $attributes = [
            'alt_auto_status' => $status,
            'alt_auto_error' => $error,
        ];

        if ($status === 'running' && Schema::hasColumn('binotel_api_call_completeds', 'alt_auto_started_at')) {
            $attributes['alt_auto_started_at'] = now();
            $attributes['alt_auto_finished_at'] = null;
        }

        if (
            in_array($status, ['completed', 'failed'], true)
            && Schema::hasColumn('binotel_api_call_completeds', 'alt_auto_finished_at')
        ) {
            $attributes['alt_auto_finished_at'] = now();
        }

        BinotelApiCallCompleted::query()
            ->whereKey($feedback->binotel_api_call_completed_id)
            ->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $transcription
     */
    private function fillTranscriptionFieldsFromPayload(BinotelCallFeedback $feedback, array $transcription, ?string $runId = null): void
    {
        $attributes = [
            'transcription_status' => ($this->nullableString($feedback->transcription_status) ?? '') !== ''
                ? $feedback->transcription_status
                : 'completed',
            'transcription_language' => $this->nullableString($transcription['language'] ?? null, 255) ?? $feedback->transcription_language,
            'transcription_model' => $this->nullableString($transcription['model'] ?? null, 255) ?? $feedback->transcription_model,
            'transcription_text' => $this->nullableString($transcription['text'] ?? null) ?? $feedback->transcription_text,
            'transcription_dialogue_text' => $this->nullableString($transcription['dialogueText'] ?? null) ?? $feedback->transcription_dialogue_text,
            'transcription_formatted_text' => $this->nullableString($transcription['formattedText'] ?? null) ?? $feedback->transcription_formatted_text,
            'transcription_payload' => $transcription !== [] ? $transcription : $feedback->transcription_payload,
            'transcribed_at' => $feedback->transcribed_at ?? now(),
        ];

        $feedback->fill($attributes);

        if ($runId !== null && $runId !== '') {
            $this->updateRun($feedback, $runId, function (array $run) use ($attributes): array {
                foreach ($attributes as $key => $value) {
                    $run[$key] = $value instanceof \DateTimeInterface ? $value->toIso8601String() : $value;
                }

                return $run;
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function clearedEvaluationAttributes(): array
    {
        return [
            'evaluation_status' => null,
            'last_evaluation_job_id' => null,
            'evaluation_checklist_id' => null,
            'evaluation_checklist_name' => null,
            'evaluation_score' => null,
            'evaluation_total_points' => null,
            'evaluation_score_percent' => null,
            'evaluation_summary' => null,
            'evaluation_strong_side' => null,
            'evaluation_focus' => null,
            'evaluation_provider' => null,
            'evaluation_model' => null,
            'evaluation_payload' => null,
            'evaluation_requested_at' => null,
            'evaluated_at' => null,
            'error_message' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ensureComparisonRunsInitialized(BinotelCallFeedback $feedback): array
    {
        if (! $this->supportsComparisonRuns()) {
            return [];
        }

        $runs = is_array($feedback->comparison_runs ?? null)
            ? array_values(array_filter($feedback->comparison_runs, 'is_array'))
            : [];

        if ($runs !== []) {
            return $runs;
        }

        if (! $this->hasAnyLegacyResult($feedback)) {
            return [];
        }

        $runs[] = [
            'id' => 'legacy-'.(string) Str::uuid(),
            'order' => 1,
            'created_at' => optional($feedback->created_at)->toIso8601String(),
            'updated_at' => optional($feedback->updated_at)->toIso8601String(),
            'source_context' => 'legacy',
            'transcription_status' => $feedback->transcription_status,
            'transcription_source_type' => $feedback->transcription_source_type,
            'transcription_source_name' => $feedback->transcription_source_name,
            'transcription_source_relative_path' => $feedback->transcription_source_relative_path,
            'transcription_storage_run_directory' => $feedback->transcription_storage_run_directory,
            'transcription_language' => $feedback->transcription_language,
            'transcription_model' => $feedback->transcription_model,
            'transcription_text' => $feedback->transcription_text,
            'transcription_dialogue_text' => $feedback->transcription_dialogue_text,
            'transcription_formatted_text' => $feedback->transcription_formatted_text,
            'transcription_payload' => $feedback->transcription_payload,
            'transcribed_at' => optional($feedback->transcribed_at)->toIso8601String(),
            'evaluation_status' => $feedback->evaluation_status,
            'last_evaluation_job_id' => $feedback->last_evaluation_job_id,
            'evaluation_checklist_id' => $feedback->evaluation_checklist_id,
            'evaluation_checklist_name' => $feedback->evaluation_checklist_name,
            'evaluation_score' => $feedback->evaluation_score,
            'evaluation_total_points' => $feedback->evaluation_total_points,
            'evaluation_score_percent' => $feedback->evaluation_score_percent,
            'evaluation_summary' => $feedback->evaluation_summary,
            'evaluation_strong_side' => $feedback->evaluation_strong_side,
            'evaluation_focus' => $feedback->evaluation_focus,
            'evaluation_provider' => $feedback->evaluation_provider,
            'evaluation_model' => $feedback->evaluation_model,
            'evaluation_payload' => $feedback->evaluation_payload,
            'evaluation_requested_at' => optional($feedback->evaluation_requested_at)->toIso8601String(),
            'evaluated_at' => optional($feedback->evaluated_at)->toIso8601String(),
            'error_message' => $feedback->error_message,
        ];

        return $runs;
    }

    private function hasAnyLegacyResult(BinotelCallFeedback $feedback): bool
    {
        foreach ([
            'transcription_text',
            'transcription_dialogue_text',
            'transcription_formatted_text',
            'transcription_model',
            'evaluation_model',
            'evaluation_score',
            'evaluation_score_percent',
            'evaluation_payload',
        ] as $field) {
            if (! blank($feedback->{$field} ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function nextRunOrder(array $runs): int
    {
        $maxOrder = 0;

        foreach ($runs as $run) {
            $maxOrder = max($maxOrder, (int) ($run['order'] ?? 0));
        }

        return $maxOrder + 1;
    }

    private function resolveRunId(BinotelCallFeedback $feedback, ?string $runId = null): string
    {
        if (! $this->supportsComparisonRuns()) {
            return '';
        }

        $normalizedRunId = trim((string) $runId);

        if ($normalizedRunId !== '') {
            return $normalizedRunId;
        }

        $activeRunId = trim((string) ($feedback->active_comparison_run_id ?? ''));

        if ($activeRunId !== '') {
            return $activeRunId;
        }

        return $this->startRun($feedback->general_call_id, ['source_context' => 'manual'])['run_id'];
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutator
     */
    private function updateRun(BinotelCallFeedback $feedback, string $runId, callable $mutator): void
    {
        if (! $this->supportsComparisonRuns() || trim($runId) === '') {
            return;
        }

        $runs = $this->ensureComparisonRunsInitialized($feedback);
        $updated = false;

        foreach ($runs as $index => $run) {
            if (trim((string) ($run['id'] ?? '')) !== $runId) {
                continue;
            }

            $runs[$index] = $mutator($run);
            $runs[$index]['updated_at'] = now()->toIso8601String();
            $updated = true;
            break;
        }

        if (! $updated) {
            $runs[] = $mutator([
                'id' => $runId,
                'order' => $this->nextRunOrder($runs),
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'source_context' => 'manual',
            ]);
        }

        usort($runs, static fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));

        $feedback->comparison_runs = $runs;
        $feedback->active_comparison_run_id = $runId;
    }

    private function supportsComparisonRuns(): bool
    {
        if (self::$supportsComparisonRuns !== null) {
            return self::$supportsComparisonRuns;
        }

        self::$supportsComparisonRuns = Schema::hasColumn('binotel_call_feedbacks', 'comparison_runs')
            && Schema::hasColumn('binotel_call_feedbacks', 'active_comparison_run_id');

        return self::$supportsComparisonRuns;
    }

    /**
     * @param  mixed  $value
     */
    private function nullableString($value, ?int $maxLength = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($maxLength !== null && $maxLength > 0 && mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value === '' ? null : $value;
    }

    /**
     * @param  mixed  $value
     */
    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
