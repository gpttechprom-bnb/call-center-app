<?php

namespace App\Services;

use App\Models\BinotelApiCallCompleted;
use App\Support\AltCallCenterAutomationStore;
use App\Support\CallCenterPageData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AltCallCenterCallsListingService
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    private const PAYLOAD_CACHE_TTL_SECONDS = 300;

    private ?bool $hasOptimizedInteractionLookupColumns = null;

    private ?bool $hasEmployeeDisplayNameColumn = null;

    private ?bool $hasDirectionKeyColumn = null;

    public function __construct(
        private readonly CallCenterPageData $pageData,
        private readonly AltCallCenterAutomationStore $automationStore,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function paginatedPayload(array $filters): array
    {
        $normalizedFilters = $this->normalizedFilters($filters);
        $callsVersion = $this->pageData->callsVersion();

        return Cache::remember(
            $this->listingCacheKey($normalizedFilters, $callsVersion),
            now()->addSeconds(self::PAYLOAD_CACHE_TTL_SECONDS),
            function () use ($normalizedFilters, $callsVersion): array {
                if ($this->usesCollectionFallback($normalizedFilters)) {
                    return $this->collectionPaginatedPayload($normalizedFilters, $callsVersion);
                }

                $page = $this->resolvePage($normalizedFilters['page'] ?? null);
                $perPage = $this->resolvePerPage($normalizedFilters['per_page'] ?? null);
                $query = $this->standardListingQuery($normalizedFilters);

                $this->applyOrdering($query, $normalizedFilters);

                $paginator = $query->paginate($perPage, ['binotel_api_call_completeds.*'], 'page', $page);
                $items = $paginator->getCollection()
                    ->map(fn (BinotelApiCallCompleted $call): array => $this->pageData->callPayload($call))
                    ->values()
                    ->all();

                return [
                    'items' => $items,
                    'pagination' => [
                        'page' => $paginator->currentPage(),
                        'perPage' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'totalPages' => $paginator->lastPage(),
                    ],
                    'employeeOptions' => $this->employeeOptions($normalizedFilters),
                    'historyContext' => $this->interactionHistoryContext($normalizedFilters),
                    'callsVersion' => $callsVersion,
                ];
            }
        );
    }

    /**
     * @return array{items: array<int, array{name:string,callsCount:int,scoredCallsCount:int,score:?int}>}
     */
    public function managersSummaryPayload(): array
    {
        $callsVersion = $this->pageData->callsVersion();

        return Cache::remember(
            $this->managersSummaryCacheKey($callsVersion),
            now()->addSeconds(self::PAYLOAD_CACHE_TTL_SECONDS),
            function (): array {
                $timezone = $this->appTimezone();
                $start = CarbonImmutable::now($timezone)->startOfMonth();
                $end = $start->endOfMonth();
                $employeeSql = $this->employeeDisplaySelectSql();
                $scoreSql = $this->scoreSortSql();

                $rows = BinotelApiCallCompleted::query()
                    ->selectRaw($employeeSql.' as employee_label')
                    ->selectRaw('COUNT(*) as calls_count')
                    ->selectRaw(sprintf('SUM(CASE WHEN %s IS NULL THEN 0 ELSE %s END) as score_total', $scoreSql, $scoreSql))
                    ->selectRaw(sprintf('SUM(CASE WHEN %s IS NULL THEN 0 ELSE 1 END) as scored_calls_count', $scoreSql))
                    ->leftJoin('binotel_call_feedbacks as feedback', 'feedback.binotel_api_call_completed_id', '=', 'binotel_api_call_completeds.id')
                    ->whereBetween('binotel_api_call_completeds.call_details_start_time', [
                        $start->startOfDay()->getTimestamp(),
                        $end->endOfDay()->getTimestamp(),
                    ])
                    ->groupByRaw($employeeSql)
                    ->get()
                    ->map(function ($row): array {
                        $callsCount = max(0, (int) ($row->calls_count ?? 0));
                        $scoredCallsCount = max(0, (int) ($row->scored_calls_count ?? 0));
                        $scoreTotal = (float) ($row->score_total ?? 0);
                        $score = $scoredCallsCount > 0
                            ? max(0, min(100, (int) round($scoreTotal / $scoredCallsCount)))
                            : null;

                        return [
                            'name' => $this->normalizeManagerDisplayName((string) ($row->employee_label ?? '')),
                            'callsCount' => $callsCount,
                            'scoredCallsCount' => $scoredCallsCount,
                            'score' => $score,
                        ];
                    })
                    ->sort(function (array $left, array $right): int {
                        $leftScore = $left['score'] ?? -1;
                        $rightScore = $right['score'] ?? -1;

                        if ($leftScore !== $rightScore) {
                            return $rightScore <=> $leftScore;
                        }

                        if (($left['callsCount'] ?? 0) !== ($right['callsCount'] ?? 0)) {
                            return ($right['callsCount'] ?? 0) <=> ($left['callsCount'] ?? 0);
                        }

                        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
                    })
                    ->values()
                    ->all();

                return [
                    'items' => $rows,
                ];
            }
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function collectionPaginatedPayload(array $filters, ?string $callsVersion = null): array
    {
        $page = $this->resolvePage($filters['page'] ?? null);
        $perPage = $this->resolvePerPage($filters['per_page'] ?? null);
        $query = $this->standardListingQuery($filters, applyEmployeeFilter: false);

        $items = $query
            ->orderBy('binotel_api_call_completeds.call_details_start_time')
            ->orderBy('binotel_api_call_completeds.id')
            ->get()
            ->map(fn (BinotelApiCallCompleted $call): array => $this->pageData->callPayload($call))
            ->values()
            ->all();

        $employeeOptions = $this->employeeOptionsFromPayload($items);
        $filteredItems = array_values(array_filter($items, fn (array $item): bool => $this->payloadMatchesFilters($item, $filters)));
        $sortedItems = $this->sortPayloadItems($filteredItems, $filters);
        $total = count($sortedItems);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $safePage = min(max($page, 1), $totalPages);
        $offset = ($safePage - 1) * $perPage;

        return [
            'items' => array_slice($sortedItems, $offset, $perPage),
            'pagination' => [
                'page' => $safePage,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
            'employeeOptions' => $employeeOptions,
            'historyContext' => $this->interactionHistoryContextFromPayload($filteredItems),
            'callsVersion' => $callsVersion ?? $this->pageData->callsVersion(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function standardListingQuery(array $filters, bool $applyEmployeeFilter = true): Builder
    {
        $query = BinotelApiCallCompleted::query()
            ->with('feedback')
            ->select('binotel_api_call_completeds.*')
            ->leftJoin('binotel_call_feedbacks as feedback', 'feedback.binotel_api_call_completed_id', '=', 'binotel_api_call_completeds.id');

        if ($this->hasOptimizedInteractionLookupColumns()) {
            $query->leftJoinSub(
                DB::table('binotel_api_call_completeds as interaction_calls')
                    ->selectRaw('interaction_calls.interaction_phone_key as interaction_phone_key')
                    ->selectRaw('interaction_calls.interaction_manager_key as interaction_manager_key')
                    ->selectRaw('COUNT(*) as interaction_count')
                    ->where('interaction_calls.interaction_number', '>', 0)
                    ->whereNotNull('interaction_calls.interaction_phone_key')
                    ->whereNotNull('interaction_calls.interaction_manager_key')
                    ->groupBy('interaction_calls.interaction_phone_key', 'interaction_calls.interaction_manager_key'),
                'interaction_aggregate',
                function ($join): void {
                    $join->on('binotel_api_call_completeds.interaction_phone_key', '=', 'interaction_aggregate.interaction_phone_key')
                        ->on('binotel_api_call_completeds.interaction_manager_key', '=', 'interaction_aggregate.interaction_manager_key');
                }
            );
        } else {
            $interactionPhoneSql = $this->interactionPhoneSql();
            $interactionManagerSql = $this->interactionManagerSql();

            $query->leftJoinSub(
                DB::table('binotel_api_call_completeds as interaction_calls')
                    ->selectRaw($this->interactionPhoneSql('interaction_calls').' as interaction_phone_key')
                    ->selectRaw($this->interactionManagerSql('interaction_calls').' as interaction_manager_key')
                    ->selectRaw('COUNT(*) as interaction_count')
                    ->where('interaction_calls.interaction_number', '>', 0)
                    ->groupByRaw(
                        $this->interactionPhoneSql('interaction_calls').', '.
                        $this->interactionManagerSql('interaction_calls')
                    ),
                'interaction_aggregate',
                function ($join) use ($interactionPhoneSql, $interactionManagerSql): void {
                    $join->on(DB::raw($interactionPhoneSql), '=', 'interaction_aggregate.interaction_phone_key')
                        ->on(DB::raw($interactionManagerSql), '=', 'interaction_aggregate.interaction_manager_key');
                }
            );
        }

        $query->addSelect(DB::raw(
            'COALESCE(interaction_aggregate.interaction_count, '.
            'CASE '.
            'WHEN binotel_api_call_completeds.interaction_number IS NULL THEN NULL '.
            'WHEN binotel_api_call_completeds.interaction_number <= 0 THEN 0 '.
            'ELSE 1 END'.
            ') as interaction_count'
        ));

        $this->applyBaseFilters($query, $filters, $applyEmployeeFilter);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyBaseFilters(Builder $query, array $filters, bool $applyEmployeeFilter): void
    {
        $normalizedPhone = $this->normalizeDigits((string) ($filters['phone'] ?? ''));
        if ($normalizedPhone !== '') {
            $query->where(function ($phoneQuery) use ($normalizedPhone): void {
                foreach ([
                    'binotel_api_call_completeds.call_details_external_number',
                    'binotel_api_call_completeds.call_details_customer_from_outside_external_number',
                    'binotel_api_call_completeds.call_details_internal_number',
                ] as $column) {
                    $phoneQuery->orWhereRaw($this->digitsSql($column).' LIKE ?', ['%'.$normalizedPhone.'%']);
                }
            });
        }

        [$startTimestamp, $endTimestamp] = $this->resolvedDateBounds($filters);
        if ($startTimestamp !== null && $endTimestamp !== null) {
            $query->whereBetween('binotel_api_call_completeds.call_details_start_time', [$startTimestamp, $endTimestamp]);
        }

        if ($applyEmployeeFilter) {
            $employee = trim((string) ($filters['employee'] ?? ''));
            if ($employee !== '' && $employee !== 'all') {
                if ($this->hasEmployeeDisplayNameColumn()) {
                    $query->where('binotel_api_call_completeds.employee_display_name', $employee);
                } else {
                    $query->whereRaw($this->employeeDisplaySql().' = ?', [$employee]);
                }
            }
        }

        $interactionPhone = $this->normalizeInteractionPhone((string) ($filters['interactionPhone'] ?? ''));
        $interactionManager = $this->normalizeInteractionManagerKey((string) ($filters['interactionManager'] ?? ''));
        if ($interactionPhone !== '' && $interactionManager !== '') {
            if ($this->hasOptimizedInteractionLookupColumns()) {
                $query->where('binotel_api_call_completeds.interaction_phone_key', $interactionPhone)
                    ->where('binotel_api_call_completeds.interaction_manager_key', $interactionManager);
            } else {
                $query->whereRaw($this->interactionPhoneSql().' = ?', [$interactionPhone])
                    ->whereRaw($this->interactionManagerSql().' = ?', [$interactionManager]);
            }
        }

        $rowInteraction = (int) ($filters['calendarRowInteraction'] ?? 0);
        $rowDirection = $this->normalizeDirection((string) ($filters['calendarRowDirection'] ?? ''));
        if ($rowInteraction > 0 && $rowDirection !== '') {
            $query->where('binotel_api_call_completeds.interaction_number', $rowInteraction);

            if ($this->hasDirectionKeyColumn()) {
                $query->where('binotel_api_call_completeds.direction_key', $rowDirection);
            } else {
                $query->whereRaw($this->directionSql().' = ?', [$rowDirection]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyOrdering(Builder $query, array $filters): void
    {
        $direction = $this->resolveSortDirection($filters['sort_direction'] ?? null);
        $sortField = $this->resolveSortField($filters['sort_field'] ?? null);

        if ($sortField === 'interactionCount') {
            $query->orderBy('interaction_count', $direction);
        } elseif ($sortField === 'interactionNumber') {
            $query->orderBy('binotel_api_call_completeds.interaction_number', $direction);
        } elseif ($sortField === 'duration') {
            $query->orderBy('binotel_api_call_completeds.call_details_billsec', $direction);
        } elseif ($sortField === 'processed') {
            $query->orderByRaw($this->processedSortSql().' '.strtoupper($direction));
        } elseif ($sortField === 'score') {
            $query->orderByRaw($this->scoreSortSql().' '.strtoupper($direction));
        } elseif ($sortField === 'model') {
            $query->orderByRaw('COALESCE(NULLIF(TRIM(feedback.evaluation_model), \'\'), \'\') '.strtoupper($direction));
        } else {
            $query->orderBy('binotel_api_call_completeds.call_details_start_time', $direction);
        }

        if ($sortField !== 'time') {
            $query->orderBy('binotel_api_call_completeds.call_details_start_time', 'desc');
        }

        $query->orderBy('binotel_api_call_completeds.id', $direction === 'asc' ? 'asc' : 'desc');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    private function employeeOptions(array $filters): array
    {
        if ($this->usesCollectionFallback($filters)) {
            return [];
        }

        $query = BinotelApiCallCompleted::query()
            ->selectRaw($this->employeeDisplaySelectSql().' as employee_label')
            ->leftJoin('binotel_call_feedbacks as feedback', 'feedback.binotel_api_call_completed_id', '=', 'binotel_api_call_completeds.id');

        $this->applyBaseFilters($query, $filters, false);

        return $query
            ->distinct()
            ->orderBy('employee_label')
            ->limit(500)
            ->pluck('employee_label')
            ->map(fn (mixed $value): string => (string) $value)
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function employeeOptionsFromPayload(array $items): array
    {
        $seen = [];

        foreach ($items as $item) {
            $employee = trim((string) ($item['employee'] ?? ''));
            if ($employee === '' || in_array($employee, $seen, true)) {
                continue;
            }

            $seen[] = $employee;
        }

        sort($seen, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($seen);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>|null
     */
    private function interactionHistoryContext(array $filters): ?array
    {
        $interactionPhone = $this->normalizeInteractionPhone((string) ($filters['interactionPhone'] ?? ''));
        $interactionManager = $this->normalizeInteractionManagerKey((string) ($filters['interactionManager'] ?? ''));

        if ($interactionPhone === '' || $interactionManager === '') {
            return null;
        }

        $query = $this->standardListingQuery($filters);
        $firstCall = (clone $query)
            ->orderBy('binotel_api_call_completeds.call_details_start_time')
            ->orderBy('binotel_api_call_completeds.id')
            ->first();
        $lastCall = (clone $query)
            ->orderByDesc('binotel_api_call_completeds.call_details_start_time')
            ->orderByDesc('binotel_api_call_completeds.id')
            ->first();

        if (! $firstCall instanceof BinotelApiCallCompleted || ! $lastCall instanceof BinotelApiCallCompleted) {
            return null;
        }

        $firstPayload = $this->pageData->callPayload($firstCall);
        $lastPayload = $this->pageData->callPayload($lastCall);

        return [
            'displayPhone' => trim((string) ($firstPayload['caller'] ?? '')) ?: $interactionPhone,
            'displayManager' => $this->normalizeManagerDisplayName((string) ($firstPayload['employee'] ?? $interactionManager)),
            'periodText' => trim(sprintf(
                '%s %s - %s %s',
                (string) ($firstPayload['date'] ?? ''),
                (string) ($firstPayload['time'] ?? ''),
                (string) ($lastPayload['date'] ?? ''),
                (string) ($lastPayload['time'] ?? '')
            )),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, string>|null
     */
    private function interactionHistoryContextFromPayload(array $items): ?array
    {
        if ($items === []) {
            return null;
        }

        $sorted = $items;
        usort($sorted, fn (array $left, array $right): int => $this->payloadTimestamp($left) <=> $this->payloadTimestamp($right));

        $first = $sorted[0] ?? null;
        $last = $sorted[count($sorted) - 1] ?? null;
        if (! is_array($first) || ! is_array($last)) {
            return null;
        }

        return [
            'displayPhone' => trim((string) ($first['caller'] ?? '')),
            'displayManager' => $this->normalizeManagerDisplayName((string) ($first['employee'] ?? '')),
            'periodText' => trim(sprintf(
                '%s %s - %s %s',
                (string) ($first['date'] ?? ''),
                (string) ($first['time'] ?? ''),
                (string) ($last['date'] ?? ''),
                (string) ($last['time'] ?? '')
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $filters
     */
    private function payloadMatchesFilters(array $item, array $filters): bool
    {
        $employee = trim((string) ($filters['employee'] ?? ''));
        if ($employee !== '' && $employee !== 'all' && (string) ($item['employee'] ?? '') !== $employee) {
            return false;
        }

        $metric = trim((string) ($filters['calendarMetric'] ?? ''));
        if ($metric !== '' && ! $this->matchesAutomationMetricPayload($item, $metric)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function matchesAutomationMetricPayload(array $item, string $metric): bool
    {
        $processed = $this->isProcessedPayload($item);
        $matchedRule = $this->matchedAutomationRule($item);
        $crmSkipped = $matchedRule !== null && $this->isCrmSkippedPayload($item);

        return match ($metric) {
            'totalCalls' => true,
            'processedTotal' => $processed,
            'required' => $matchedRule !== null && ! $crmSkipped,
            'processedScenario' => $matchedRule !== null && ! $crmSkipped && $processed,
            'processedCrmSkipped' => $matchedRule !== null && $crmSkipped && $processed,
            'processedOutsideBaseRules' => $processed && $matchedRule === null,
            'remaining' => $matchedRule !== null && ! $crmSkipped && ! $processed,
            'crmSkipped' => $matchedRule !== null && $crmSkipped,
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{checklist_id:string,interaction_number:int,direction:string}|null
     */
    private function matchedAutomationRule(array $item): ?array
    {
        $interactionNumber = max(1, (int) ($item['interactionNumber'] ?? 0));
        $durationSeconds = $this->durationSeconds((string) ($item['duration'] ?? ''));
        $minimumDurationSeconds = $this->minimumAutomationDurationMinutes() * 60;

        if ($interactionNumber <= 0 || ($minimumDurationSeconds > 0 && $durationSeconds < $minimumDurationSeconds)) {
            return null;
        }

        $direction = $this->normalizeDirection((string) ($item['direction'] ?? '')) ?: 'out';

        foreach ($this->configuredAutomationRules() as $rule) {
            if ((int) $rule['interaction_number'] !== $interactionNumber) {
                continue;
            }

            if ($rule['direction'] !== 'any' && $rule['direction'] !== $direction) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isProcessedPayload(array $item): bool
    {
        if (trim((string) ($item['altAutoStatus'] ?? '')) === 'completed') {
            return true;
        }

        return trim((string) ($item['processedAt'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isCrmSkippedPayload(array $item): bool
    {
        $missingInCrm = $item['missingInCrm'] ?? null;
        $crmPhoneExists = $item['crmPhoneExists'] ?? null;
        $crmCase = mb_strtolower(trim((string) ($item['crmCase'] ?? '')), 'UTF-8');
        $status = trim((string) ($item['altAutoStatus'] ?? ''));
        $error = mb_strtolower(trim((string) ($item['altAutoError'] ?? '')), 'UTF-8');

        if ($missingInCrm === true) {
            return true;
        }

        if ($missingInCrm === false) {
            return false;
        }

        if ($crmPhoneExists === false) {
            return true;
        }

        if ($crmPhoneExists === true) {
            return false;
        }

        if (in_array($crmCase, [
            'low-quality lead',
            'низькоякісний лід',
            'no matches found',
            'need add phone number',
        ], true)) {
            return true;
        }

        if ($status !== 'completed' || $error === '') {
            return false;
        }

        return str_contains($error, 'crm')
            && ! str_contains($error, 'уже знайдено в crm')
            && (
                str_contains($error, 'low-quality lead')
                || str_contains($error, 'низькоякісний лід')
                || str_contains($error, 'no matches found')
                || str_contains($error, 'need add phone number')
            );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function sortPayloadItems(array $items, array $filters): array
    {
        $sortField = $this->resolveSortField($filters['sort_field'] ?? null);
        $direction = $this->resolveSortDirection($filters['sort_direction'] ?? null);

        usort($items, function (array $left, array $right) use ($sortField, $direction): int {
            $result = 0;

            if ($sortField === 'interactionCount') {
                $result = ((int) ($left['interactionCount'] ?? 0)) <=> ((int) ($right['interactionCount'] ?? 0));
            } elseif ($sortField === 'interactionNumber') {
                $result = ((int) ($left['interactionNumber'] ?? 0)) <=> ((int) ($right['interactionNumber'] ?? 0));
            } elseif ($sortField === 'duration') {
                $result = $this->durationSeconds((string) ($left['duration'] ?? '')) <=> $this->durationSeconds((string) ($right['duration'] ?? ''));
            } elseif ($sortField === 'processed') {
                $result = $this->processedPayloadTimestamp($left) <=> $this->processedPayloadTimestamp($right);
            } elseif ($sortField === 'score') {
                $result = ((int) ($left['score'] ?? -1)) <=> ((int) ($right['score'] ?? -1));
            } elseif ($sortField === 'model') {
                $result = strnatcasecmp((string) ($left['model'] ?? ''), (string) ($right['model'] ?? ''));
            } else {
                $result = $this->payloadTimestamp($left) <=> $this->payloadTimestamp($right);
            }

            if ($result === 0) {
                $result = ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            }

            return $direction === 'asc' ? $result : -$result;
        });

        return $items;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadTimestamp(array $payload): int
    {
        $date = trim((string) ($payload['date'] ?? ''));
        $time = trim((string) ($payload['time'] ?? '00:00'));

        return $this->displayDateTimeTimestamp($date, $time);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processedPayloadTimestamp(array $payload): int
    {
        $date = trim((string) ($payload['processedDate'] ?? ''));
        $time = trim((string) ($payload['processedTime'] ?? '00:00'));

        return $this->displayDateTimeTimestamp($date, $time);
    }

    private function displayDateTimeTimestamp(string $date, string $time): int
    {
        $resolvedDate = $this->parseDisplayDate($date);

        if ($resolvedDate === null) {
            return 0;
        }

        [$hours, $minutes] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $resolvedDate->setTime($hours, $minutes)->getTimestamp();
    }

    private function parseDisplayDate(string $value): ?CarbonImmutable
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        foreach (['d.m.Y', 'Y-m-d'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $trimmed, $this->appTimezone())->startOfDay();
            } catch (\Throwable) {
                // Continue.
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0:?int,1:?int}
     */
    private function resolvedDateBounds(array $filters): array
    {
        $start = $this->parseDisplayDate((string) ($filters['date_start'] ?? ''));
        $end = $this->parseDisplayDate((string) ($filters['date_end'] ?? ''));

        if ($start === null && $end === null) {
            $calendarDate = $this->parseDisplayDate((string) ($filters['calendarDate'] ?? ''));
            $start = $calendarDate;
            $end = $calendarDate;
        }

        if ($start === null && $end !== null) {
            $start = $end;
        }

        if ($end === null && $start !== null) {
            $end = $start;
        }

        if ($start === null || $end === null) {
            return [null, null];
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->startOfDay()->getTimestamp(), $end->endOfDay()->getTimestamp()];
    }

    /**
     * @return array<int, array{checklist_id:string,interaction_number:int,direction:string}>
     */
    private function configuredAutomationRules(): array
    {
        $evaluationSettings = $this->automationStore->processingSettings()['evaluation'] ?? [];
        $rawRules = is_array($evaluationSettings['checklist_routing_rules'] ?? null)
            ? $evaluationSettings['checklist_routing_rules']
            : [];
        $normalized = [];
        $seen = [];

        foreach ($rawRules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $checklistId = trim((string) ($rule['checklist_id'] ?? ''));
            $interactionNumber = (int) ($rule['interaction_number'] ?? 0);
            $direction = $this->normalizeDirection((string) ($rule['direction'] ?? '')) ?: 'any';

            if ($checklistId === '' || $interactionNumber < 1 || $interactionNumber > 20) {
                continue;
            }

            $signature = $checklistId.'|'.$interactionNumber.'|'.$direction;
            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $normalized[] = [
                'checklist_id' => $checklistId,
                'interaction_number' => $interactionNumber,
                'direction' => $direction,
            ];
        }

        return $normalized;
    }

    private function minimumAutomationDurationMinutes(): int
    {
        $evaluationSettings = $this->automationStore->processingSettings()['evaluation'] ?? [];

        return max(0, min(10, (int) ($evaluationSettings['minimum_duration_minutes'] ?? 0)));
    }

    private function usesCollectionFallback(array $filters): bool
    {
        return trim((string) ($filters['calendarMetric'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizedFilters(array $filters): array
    {
        return [
            'page' => $this->resolvePage($filters['page'] ?? null),
            'per_page' => $this->resolvePerPage($filters['per_page'] ?? null),
            'sort_field' => $this->resolveSortField($filters['sort_field'] ?? null),
            'sort_direction' => $this->resolveSortDirection($filters['sort_direction'] ?? null),
            'phone' => $this->normalizeDigits((string) ($filters['phone'] ?? '')),
            'employee' => trim((string) ($filters['employee'] ?? '')),
            'date_start' => trim((string) ($filters['date_start'] ?? '')),
            'date_end' => trim((string) ($filters['date_end'] ?? '')),
            'interactionPhone' => $this->normalizeInteractionPhone((string) ($filters['interactionPhone'] ?? '')),
            'interactionManager' => $this->normalizeInteractionManagerKey((string) ($filters['interactionManager'] ?? '')),
            'calendarMetric' => trim((string) ($filters['calendarMetric'] ?? '')),
            'calendarDate' => trim((string) ($filters['calendarDate'] ?? '')),
            'calendarRowInteraction' => max(0, (int) ($filters['calendarRowInteraction'] ?? 0)),
            'calendarRowDirection' => $this->normalizeDirection((string) ($filters['calendarRowDirection'] ?? '')),
            'calendarRowScenario' => trim((string) ($filters['calendarRowScenario'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function listingCacheKey(array $filters, string $callsVersion): string
    {
        $payload = [
            'version' => $callsVersion,
            'filters' => $filters,
        ];

        if ($this->usesCollectionFallback($filters)) {
            $payload['automation'] = $this->automationSettingsFingerprint();
        }

        return 'alt_call_center:calls_listing:'.sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function managersSummaryCacheKey(string $callsVersion): string
    {
        return 'alt_call_center:managers_summary:'.$callsVersion;
    }

    private function automationSettingsFingerprint(): string
    {
        $evaluationSettings = $this->automationStore->processingSettings()['evaluation'] ?? [];

        try {
            return sha1(json_encode($evaluationSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            return 'automation:error';
        }
    }

    private function resolvePage(mixed $value): int
    {
        return max(1, (int) $value);
    }

    private function resolvePerPage(mixed $value): int
    {
        $perPage = (int) $value;

        if ($perPage <= 0) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        return min(self::MAX_PER_PAGE, $perPage);
    }

    private function resolveSortField(mixed $value): string
    {
        $allowed = ['time', 'duration', 'interactionCount', 'interactionNumber', 'model', 'processed', 'score'];
        $sortField = trim((string) $value);

        return in_array($sortField, $allowed, true) ? $sortField : 'time';
    }

    private function resolveSortDirection(mixed $value): string
    {
        return strtolower(trim((string) $value)) === 'asc' ? 'asc' : 'desc';
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function normalizeInteractionPhone(string $value): string
    {
        $digits = $this->normalizeDigits($value);

        return strlen($digits) === 12 && str_starts_with($digits, '380')
            ? substr($digits, 2)
            : $digits;
    }

    private function normalizeInteractionManagerKey(string $value): string
    {
        $normalized = trim($value);
        $normalized = preg_replace('/^Wire:\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+Sip$/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return mb_strtolower($normalized, 'UTF-8');
    }

    private function normalizeManagerDisplayName(string $value): string
    {
        $normalized = preg_replace('/^Wire:\s*/i', '', $value) ?? $value;
        $normalized = preg_replace('/\s+Sip$/i', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function normalizeDirection(string $value): string
    {
        $direction = strtolower(trim($value));

        return in_array($direction, ['in', 'out', 'any'], true) ? $direction : '';
    }

    private function durationSeconds(string $duration): int
    {
        if (! preg_match('/^\d{2,}:\d{2}$/', $duration)) {
            return 0;
        }

        [$minutes, $seconds] = array_map('intval', explode(':', $duration));

        return ($minutes * 60) + $seconds;
    }

    private function appTimezone(): string
    {
        return (string) config('binotel.timezone', 'Europe/Kyiv');
    }

    private function hasOptimizedInteractionLookupColumns(): bool
    {
        if ($this->hasOptimizedInteractionLookupColumns !== null) {
            return $this->hasOptimizedInteractionLookupColumns;
        }

        return $this->hasOptimizedInteractionLookupColumns =
            Schema::hasColumn('binotel_api_call_completeds', 'interaction_phone_key')
            && Schema::hasColumn('binotel_api_call_completeds', 'interaction_manager_key');
    }

    private function hasEmployeeDisplayNameColumn(): bool
    {
        if ($this->hasEmployeeDisplayNameColumn !== null) {
            return $this->hasEmployeeDisplayNameColumn;
        }

        return $this->hasEmployeeDisplayNameColumn = Schema::hasColumn('binotel_api_call_completeds', 'employee_display_name');
    }

    private function hasDirectionKeyColumn(): bool
    {
        if ($this->hasDirectionKeyColumn !== null) {
            return $this->hasDirectionKeyColumn;
        }

        return $this->hasDirectionKeyColumn = Schema::hasColumn('binotel_api_call_completeds', 'direction_key');
    }

    private function employeeDisplaySelectSql(): string
    {
        if ($this->hasEmployeeDisplayNameColumn()) {
            return "COALESCE(NULLIF(TRIM(binotel_api_call_completeds.employee_display_name), ''), 'Не визначено')";
        }

        return $this->employeeDisplaySql();
    }

    private function employeeDisplaySql(string $alias = 'binotel_api_call_completeds'): string
    {
        return "CASE ".
            "WHEN TRIM(COALESCE({$alias}.call_details_employee_name, '')) <> '' THEN TRIM({$alias}.call_details_employee_name) ".
            "WHEN TRIM(COALESCE({$alias}.call_details_internal_number, '')) <> '' THEN CONCAT('Внутрішній номер ', TRIM({$alias}.call_details_internal_number)) ".
            "ELSE 'Не визначено' END";
    }

    private function digitsSql(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";
    }

    private function interactionPhoneSql(string $alias = 'binotel_api_call_completeds'): string
    {
        $digits = $this->digitsSql("{$alias}.call_details_external_number");

        return "CASE WHEN CHAR_LENGTH({$digits}) = 12 AND LEFT({$digits}, 3) = '380' THEN SUBSTRING({$digits}, 3) ELSE {$digits} END";
    }

    private function interactionManagerSql(string $alias = 'binotel_api_call_completeds'): string
    {
        $choice = "LOWER(TRIM(CASE ".
            "WHEN TRIM(COALESCE({$alias}.call_details_employee_email, '')) <> '' THEN {$alias}.call_details_employee_email ".
            "WHEN TRIM(COALESCE({$alias}.call_details_internal_number, '')) <> '' THEN {$alias}.call_details_internal_number ".
            "ELSE COALESCE({$alias}.call_details_employee_name, '') END))";

        return "TRIM(REPLACE(REPLACE({$choice}, 'wire: ', ''), ' sip', ''))";
    }

    private function directionSql(string $alias = 'binotel_api_call_completeds'): string
    {
        return "CASE WHEN TRIM(COALESCE({$alias}.call_details_call_type, '')) IN ('0', 'in', 'incoming') THEN 'in' ELSE 'out' END";
    }

    private function scoreSortSql(): string
    {
        return "COALESCE(feedback.evaluation_score_percent, ".
            "CASE WHEN feedback.evaluation_total_points > 0 THEN ROUND((feedback.evaluation_score / feedback.evaluation_total_points) * 100) ELSE NULL END)";
    }

    private function processedSortSql(): string
    {
        return "COALESCE(feedback.evaluated_at, feedback.transcribed_at, feedback.updated_at, binotel_api_call_completeds.alt_auto_finished_at)";
    }
}
