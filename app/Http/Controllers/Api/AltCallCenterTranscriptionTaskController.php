<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\TerminatesTrackedProcess;
use App\Http\Controllers\Controller;
use App\Support\AltCallCenterTranscriptionTaskStore;
use Illuminate\Http\JsonResponse;

class AltCallCenterTranscriptionTaskController extends Controller
{
    use TerminatesTrackedProcess;

    public function store(AltCallCenterTranscriptionTaskStore $taskStore): JsonResponse
    {
        $task = $taskStore->create();

        return response()->json([
            'message' => 'Службове завдання транскрибації створено.',
            'task' => $taskStore->publicPayload($task),
        ], 201);
    }

    public function destroy(string $taskId, AltCallCenterTranscriptionTaskStore $taskStore): JsonResponse
    {
        $task = $taskStore->find($taskId);

        if ($task !== null) {
            $this->terminateTrackedProcess(
                is_numeric($task['process_id'] ?? null)
                    ? (int) $task['process_id']
                    : null,
            );

            $taskStore->clear($taskId);
        }

        return response()->json([
            'message' => 'Транскрибацію зупинено та очищено.',
            'taskId' => $taskId,
            'cleared' => true,
        ]);
    }
}
