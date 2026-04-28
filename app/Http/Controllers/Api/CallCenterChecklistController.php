<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CallCenterChecklistStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CallCenterChecklistController extends Controller
{
    public function index(CallCenterChecklistStore $store): JsonResponse
    {
        return response()->json([
            'checklists' => $store->all(),
            'defaultChecklistId' => $store->defaultId(),
        ]);
    }

    public function store(Request $request, CallCenterChecklistStore $store): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'max:120'],
            'prompt' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.label' => ['required', 'string', 'max:2000'],
            'items.*.max_points' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $checklist = $store->save($validated);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Чек-лист збережено.',
            'checklist' => $checklist,
            'checklists' => $store->all(),
            'defaultChecklistId' => $store->defaultId(),
        ]);
    }

    public function duplicate(string $checklistId, CallCenterChecklistStore $store): JsonResponse
    {
        try {
            $checklist = $store->duplicate($checklistId);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 404);
        }

        return response()->json([
            'message' => 'Копію чек-листа створено.',
            'checklist' => $checklist,
            'checklists' => $store->all(),
            'defaultChecklistId' => $store->defaultId(),
        ]);
    }

    public function destroy(string $checklistId, CallCenterChecklistStore $store): JsonResponse
    {
        try {
            $store->delete($checklistId);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 404);
        }

        return response()->json([
            'message' => 'Чек-лист видалено.',
            'deletedChecklistId' => $checklistId,
            'checklists' => $store->all(),
            'defaultChecklistId' => $store->defaultId(),
        ]);
    }
}
