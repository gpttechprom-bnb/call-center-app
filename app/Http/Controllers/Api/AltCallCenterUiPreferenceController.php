<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CallCenterUiPreferenceStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AltCallCenterUiPreferenceController extends Controller
{
    private const CALLS_TABLE_COLUMNS_SCOPE = 'alt.call-center.calls-table-columns';
    private const CALLS_TABLE_COLUMNS = [
        'force',
        'direction',
        'interactionCount',
        'interactionNumber',
        'caller',
        'model',
        'employee',
        'score',
        'duration',
        'time',
        'processed',
        'binotel',
        'text',
        'audio',
    ];

    public function show(Request $request, CallCenterUiPreferenceStore $store): JsonResponse
    {
        $validated = $request->validate([
            'profile_key' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json([
            'scope' => self::CALLS_TABLE_COLUMNS_SCOPE,
            'preferences' => $store->get($request, self::CALLS_TABLE_COLUMNS_SCOPE),
            'profile_key' => (string) ($validated['profile_key'] ?? ''),
        ]);
    }

    public function update(Request $request, CallCenterUiPreferenceStore $store): JsonResponse
    {
        $validated = $request->validate([
            'profile_key' => ['nullable', 'string', 'max:64'],
            'widths' => ['required', 'array'],
            'widths.force' => ['nullable', 'integer', 'between:58,1200'],
            'widths.direction' => ['nullable', 'integer', 'between:42,1200'],
            'widths.interactionCount' => ['nullable', 'integer', 'between:86,1200'],
            'widths.interactionNumber' => ['nullable', 'integer', 'between:104,1200'],
            'widths.caller' => ['nullable', 'integer', 'between:150,1200'],
            'widths.model' => ['nullable', 'integer', 'between:168,1200'],
            'widths.employee' => ['nullable', 'integer', 'between:180,1400'],
            'widths.score' => ['nullable', 'integer', 'between:76,1200'],
            'widths.duration' => ['nullable', 'integer', 'between:108,1200'],
            'widths.time' => ['nullable', 'integer', 'between:132,1200'],
            'widths.processed' => ['nullable', 'integer', 'between:160,1200'],
            'widths.binotel' => ['nullable', 'integer', 'between:160,1200'],
            'widths.text' => ['nullable', 'integer', 'between:92,1200'],
            'widths.audio' => ['nullable', 'integer', 'between:92,1200'],
            'order' => ['required', 'array', 'size:14'],
            'order.*' => ['required', 'string', Rule::in(self::CALLS_TABLE_COLUMNS)],
        ]);

        $order = array_values($validated['order']);
        if (count(array_unique($order)) !== count(self::CALLS_TABLE_COLUMNS)) {
            return response()->json([
                'message' => 'Порядок колонок містить дублікати або пропуски.',
                'errors' => [
                    'order' => ['Порядок колонок має містити кожну колонку рівно один раз.'],
                ],
            ], 422);
        }

        $stored = $store->put(
            $request,
            self::CALLS_TABLE_COLUMNS_SCOPE,
            [
                'widths' => $validated['widths'],
                'order' => $order,
            ]
        );

        return response()->json([
            'message' => 'Ширини колонок збережено.',
            'scope' => self::CALLS_TABLE_COLUMNS_SCOPE,
            'preferences' => $stored,
        ]);
    }
}
