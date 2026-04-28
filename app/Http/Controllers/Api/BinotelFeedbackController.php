<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BinotelApiCallCompleted;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class BinotelFeedbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->hasValidKey($request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'general_call_id' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'string', 'max:120'],
            'date_to' => ['nullable', 'string', 'max:120'],
            'counter' => ['nullable', 'integer', 'between:1,500'],
            'limit' => ['nullable', 'integer', 'between:1,500'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $dateFrom = $this->parseTimestamp($validated['date_from'] ?? null, 'date_from');
        $dateTo = $this->parseTimestamp($validated['date_to'] ?? null, 'date_to');
        $counter = (int) ($validated['counter'] ?? $validated['limit'] ?? config('binotel.feedback.default_counter', 100));
        $counter = min($counter, (int) config('binotel.feedback.max_counter', 500));
        $offset = (int) ($validated['offset'] ?? 0);

        $query = BinotelApiCallCompleted::query()
            ->with(['historyItems', 'feedback'])
            ->orderByDesc('call_details_start_time')
            ->orderByDesc('id');

        $generalCallId = trim((string) ($validated['general_call_id'] ?? ''));

        if ($generalCallId !== '') {
            $query->where('call_details_general_call_id', $generalCallId);
        }

        if ($dateFrom !== null) {
            $query->where('call_details_start_time', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('call_details_start_time', '<=', $dateTo);
        }

        $total = (clone $query)->count();
        $records = $query
            ->offset($offset)
            ->limit($counter)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $records
                ->map(fn (BinotelApiCallCompleted $call): array => $this->mapCall($call))
                ->values()
                ->all(),
            'meta' => [
                'total' => $total,
                'returned' => $records->count(),
                'counter' => $counter,
                'offset' => $offset,
                'filters' => [
                    'general_call_id' => $generalCallId !== '' ? $generalCallId : null,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCall(BinotelApiCallCompleted $call): array
    {
        $payload = $call->toArray();

        return [
            'general_call_id' => $call->call_details_general_call_id,
            'call_id' => $call->call_details_call_id,
            'interaction_number' => $call->interaction_number,
            'started_at_unix' => $call->call_details_start_time,
            'started_at_iso' => $this->startedAtIso($call->call_details_start_time),
            'call' => Arr::except($payload, ['history_items', 'feedback']),
            'history_items' => $payload['history_items'] ?? [],
            'feedback' => $payload['feedback'] ?? null,
        ];
    }

    private function hasValidKey(Request $request): bool
    {
        $configuredKey = trim((string) config('binotel.feedback.api_key', ''));

        if ($configuredKey === '') {
            return false;
        }

        $providedKey = trim((string) (
            $request->query('key')
            ?? $request->header('X-Api-Key')
            ?? $request->header('X-Feedback-Key')
            ?? ''
        ));

        return hash_equals($configuredKey, $providedKey);
    }

    private function parseTimestamp(mixed $value, string $field): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $rawValue = trim((string) $value);

        if (ctype_digit($rawValue)) {
            return (int) $rawValue;
        }

        try {
            return CarbonImmutable::parse($rawValue)->getTimestamp();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $field => 'Некоректний формат дати. Використовуйте unix timestamp або дату, яку може розібрати Carbon.',
            ]);
        }
    }

    private function startedAtIso(?int $timestamp): ?string
    {
        if (! is_int($timestamp) || $timestamp <= 0) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC($timestamp)->toIso8601String();
    }
}
