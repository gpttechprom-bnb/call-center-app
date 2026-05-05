<?php

namespace App\Services;

use App\Models\BinotelApiCallCompleted;
use App\Support\AltCallCenterAutomationStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AltCallCenterAutomationCalendarStatsService
{
    private const CRM_CACHE_DIRECTORY = 'call-center/alt/automation/calendar-crm';

    private const CRM_LOOKUP_READY_AT = '17:15';

    private const INCOMING_CALL_TYPES = ['0', 'in', 'incoming'];

    public function __construct(
        private readonly AltCallCenterAutomationStore $automationStore,
        private readonly CallCenterCrmPhoneLookupService $crmPhoneLookupService,
        private readonly CallCenterCrmCallStatusStore $crmCallStatusStore,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildMonthStats(int $year, int $month, ?string $focusDate = null): array
    {
        $timezone = (string) config('binotel.timezone', 'Europe/Kyiv');
        $monthStart = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone);
        $monthEnd = $monthStart->endOfMonth();
        $rules = $this->configuredChecklistRoutingRules();
        $calls = $this->callsForPeriod($monthStart, $monthEnd);
        $callsByDay = [];

        foreach ($calls as $call) {
            $dateKey = $this->formatDayKey($call, $timezone);
            if ($dateKey === '') {
                continue;
            }

            $callsByDay[$dateKey] ??= [];
            $callsByDay[$dateKey][] = $call;
        }

        ksort($callsByDay);

        $days = [];
        $totalCandidates = 0;
        $totalRequired = 0;
        $totalProcessed = 0;
        $totalRemaining = 0;
        $totalCrmSkipped = 0;

        foreach ($callsByDay as $dateKey => $dayCalls) {
            $dayStats = $this->buildDayStats($dateKey, $dayCalls, $rules, $timezone, $focusDate);
            $days[] = $dayStats;
            $totalCandidates += (int) ($dayStats['candidateCount'] ?? 0);
            $totalRequired += (int) ($dayStats['required'] ?? 0);
            $totalProcessed += (int) ($dayStats['processed'] ?? 0);
            $totalRemaining += (int) ($dayStats['remaining'] ?? 0);
            $totalCrmSkipped += (int) ($dayStats['crmSkipped'] ?? 0);
        }

        return [
            'month' => $monthStart->format('Y-m'),
            'year' => (int) $monthStart->format('Y'),
            'monthIndex' => (int) $monthStart->format('n') - 1,
            'generatedAt' => now()->toIso8601String(),
            'crmLookupReadyAt' => self::CRM_LOOKUP_READY_AT,
            'rules' => array_map(function (array $rule): array {
                $interactionNumber = (int) ($rule['interaction_number'] ?? 1);
                $direction = (string) ($rule['direction'] ?? 'any');

                return [
                    'signature' => $this->ruleSignature($interactionNumber, $direction),
                    'interaction_number' => $interactionNumber,
                    'direction' => $direction,
                    'label' => $this->ruleLabel($interactionNumber, $direction),
                ];
            }, $rules),
            'days' => $days,
            'totalCandidates' => $totalCandidates,
            'totalRequired' => $totalRequired,
            'totalProcessed' => $totalProcessed,
            'totalRemaining' => $totalRemaining,
            'totalCrmSkipped' => $totalCrmSkipped,
            'completedDays' => count(array_filter($days, static fn (array $day): bool => (bool) ($day['isComplete'] ?? false))),
        ];
    }

    /**
     * @param  array<int, BinotelApiCallCompleted>  $calls
     * @param  array<int, array{checklist_id:string,interaction_number:int,direction:string}>  $rules
     * @return array<string, mixed>
     */
    private function buildDayStats(string $dateKey, array $calls, array $rules, string $timezone, ?string $focusDate = null): array
    {
        $allRows = [];
        $rows = [];
        $totalCalls = 0;
        $totalProcessedCalls = 0;
        $candidateCount = 0;
        $required = 0;
        $processed = 0;
        $crmSkipped = 0;
        $processedCrmSkipped = 0;
        $dayDate = CarbonImmutable::createFromFormat('d.m.Y', $dateKey, $timezone);
        $crmContext = $this->crmContextForDay($dayDate, $calls, $rules, $focusDate === $dateKey);

        foreach ($calls as $call) {
            $interactionNumber = max(1, (int) ($call->interaction_number ?? 0));
            $direction = $this->direction($call);
            $rowSignature = $interactionNumber > 0 ? $interactionNumber.':'.$direction : '';
            $isProcessed = $this->isProcessedCall($call);
            $phone = $this->crmPhoneLookupService->normalizePhone(
                (string) ($call->call_details_external_number ?? $call->call_details_customer_from_outside_external_number ?? '')
            );

            if ($rowSignature !== '') {
                $allRows[$rowSignature] ??= [
                    'signature' => $rowSignature,
                    'label' => $this->ruleLabel($interactionNumber, $direction),
                    'interactionNumber' => $interactionNumber,
                    'direction' => $direction,
                    'total' => 0,
                    'processed' => 0,
                    'crmSkipped' => 0,
                    'isInScenario' => false,
                ];

                $allRows[$rowSignature]['total'] += 1;
                $totalCalls += 1;

                if ($isProcessed) {
                    $allRows[$rowSignature]['processed'] += 1;
                    $totalProcessedCalls += 1;
                }
            }

            if (! $this->meetsMinimumDuration($call)) {
                continue;
            }

            $matchedRule = $this->resolveMatchedRule($call, $rules);
            if ($matchedRule === null) {
                continue;
            }

            $candidateCount += 1;

            if ($rowSignature !== '' && isset($allRows[$rowSignature])) {
                $allRows[$rowSignature]['isInScenario'] = true;
            }

            $ruleSignature = $this->ruleSignature(
                (int) $matchedRule['interaction_number'],
                (string) $matchedRule['direction']
            );

            $rows[$ruleSignature] ??= [
                'signature' => $ruleSignature,
                'label' => $this->ruleLabel(
                    (int) $matchedRule['interaction_number'],
                    (string) $matchedRule['direction']
                ),
                'interactionNumber' => (int) $matchedRule['interaction_number'],
                'direction' => (string) $matchedRule['direction'],
                'required' => 0,
                'processed' => 0,
                'crmSkipped' => 0,
                'candidateCount' => 0,
            ];

            $rows[$ruleSignature]['candidateCount'] += 1;

            $crmHit = $phone !== ''
                && isset($crmContext['phones'][$phone])
                && $this->shouldTreatCrmResultAsSkipped($crmContext['phones'][$phone]);

            if ($crmHit) {
                $crmSkipped += 1;
                $rows[$ruleSignature]['crmSkipped'] += 1;

                if ($isProcessed) {
                    $processedCrmSkipped += 1;
                }

                if ($rowSignature !== '' && isset($allRows[$rowSignature])) {
                    $allRows[$rowSignature]['crmSkipped'] += 1;
                }

                continue;
            }

            $required += 1;
            $rows[$ruleSignature]['required'] += 1;

            if ($isProcessed) {
                $processed += 1;
                $rows[$ruleSignature]['processed'] += 1;
            }
        }

        $orderedRows = array_values($rows);
        usort($orderedRows, fn (array $left, array $right): int => $this->compareRows($left, $right));

        $orderedAllRows = array_values($allRows);
        usort($orderedAllRows, fn (array $left, array $right): int => $this->compareRows($left, $right));

        $remaining = max(0, $required - $processed);
        $processedWithinBaseRules = $processed + $processedCrmSkipped;
        $processedOutsideBaseRules = max(0, $totalProcessedCalls - $processedWithinBaseRules);

        return [
            'date' => $dateKey,
            'totalCalls' => $totalCalls,
            'totalProcessedCalls' => $totalProcessedCalls,
            'candidateCount' => $candidateCount,
            'required' => $required,
            'processed' => $processed,
            'processedCrmSkipped' => $processedCrmSkipped,
            'processedWithinBaseRules' => $processedWithinBaseRules,
            'processedOutsideBaseRules' => $processedOutsideBaseRules,
            'remaining' => $remaining,
            'crmSkipped' => $crmSkipped,
            'crmStatus' => $crmContext['status'],
            'crmStatusLabel' => $crmContext['label'],
            'isComplete' => $required > 0 && $remaining === 0,
            'rows' => $orderedRows,
            'allRows' => $orderedAllRows,
        ];
    }

    /**
     * @param  array{phone_exist:bool,manager:?string,case:?string}  $crmRow
     */
    private function shouldTreatCrmResultAsSkipped(array $crmRow): bool
    {
        return ! (bool) ($crmRow['phone_exist'] ?? false);
    }

    /**
     * @param  array<int, BinotelApiCallCompleted>  $calls
     * @param  array<int, array{checklist_id:string,interaction_number:int,direction:string}>  $rules
     * @return array{status:string,label:string,phones:array<string, array{phone_exist:bool,manager:?string,case:?string}>}
     */
    private function crmContextForDay(CarbonImmutable $day, array $calls, array $rules, bool $allowLiveLookup): array
    {
        $candidatePhones = [];

        foreach ($calls as $call) {
            if ($this->resolveMatchedRule($call, $rules) === null) {
                continue;
            }

            $phone = $this->crmPhoneLookupService->normalizePhone(
                (string) ($call->call_details_external_number ?? $call->call_details_customer_from_outside_external_number ?? '')
            );

            if ($phone !== '') {
                $candidatePhones[$phone] = true;
            }
        }

        if ($candidatePhones === []) {
            return [
                'status' => 'ready',
                'label' => 'Немає номерів для CRM-перевірки.',
                'phones' => [],
            ];
        }

        $phones = array_keys($candidatePhones);
        $cachePayload = $this->readCrmCache($day);
        $cache = $cachePayload['phones'];

        if ($cachePayload['is_complete'] && $allowLiveLookup) {
            $lookupResult = $this->lookupPhonesForDay($phones, $day);
            $cache = array_replace($cache, $lookupResult['phones']);
            $checkedPhones = array_values(array_unique(array_merge($cachePayload['checked_phones'], $phones)));
            $hadErrors = $cachePayload['had_errors'] || $lookupResult['errors'] > 0;

            $this->writeCrmCache($day, $cache, $checkedPhones, true, $hadErrors);
            $this->persistCrmCacheToCalls($cache);

            return [
                'status' => $hadErrors ? 'partial' : 'ready',
                'label' => $hadErrors
                    ? 'CRM-результат для цієї дати оновлено, але частину номерів не вдалося перевірити повторно.'
                    : 'CRM-результат для цієї дати оновлено.',
                'phones' => $cache,
            ];
        }

        if ($cachePayload['is_complete']) {
            $this->persistCrmCacheToCalls($cache);

            return [
                'status' => $cachePayload['had_errors'] ? 'partial' : 'ready',
                'label' => $cachePayload['had_errors']
                    ? 'CRM-результат для цієї дати вже зафіксовано, але частину номерів раніше не вдалося перевірити.'
                    : 'CRM-результат для цієї дати взято з кешу.',
                'phones' => $cache,
            ];
        }

        if (! $allowLiveLookup) {
            $this->persistCrmCacheToCalls($cache);

            return [
                'status' => $cache !== [] ? 'ready' : 'pending',
                'label' => $cache !== []
                    ? 'CRM-дані взято з кешу. Для точної перевірки відкрийте потрібну дату.'
                    : 'CRM-перевірка підтягується для вибраної дати окремо.',
                'phones' => $cache,
            ];
        }

        if (! $this->shouldCollectCrmForDay($day)) {
            return [
                'status' => 'pending',
                'label' => 'CRM-перевірка для цього дня виконується після 17:15.',
                'phones' => [],
            ];
        }

        $checkedPhones = $cachePayload['checked_phones'];
        $missingPhones = array_values(array_filter($phones, static fn (string $phone): bool => ! in_array($phone, $checkedPhones, true)));

        if ($missingPhones !== []) {
            $lookupResult = $this->lookupPhonesForDay($missingPhones, $day);
            $cache = array_replace($cache, $lookupResult['phones']);
            $checkedPhones = array_values(array_unique(array_merge($checkedPhones, $missingPhones)));
            $hadErrors = $cachePayload['had_errors'] || $lookupResult['errors'] > 0;
            $this->writeCrmCache($day, $cache, $checkedPhones, true, $hadErrors);

            if ($lookupResult['errors'] > 0) {
                return [
                    'status' => 'partial',
                    'label' => 'CRM-результат для цієї дати зафіксовано, але частину номерів не вдалося перевірити.',
                    'phones' => $cache,
                ];
            }
        }

        return [
            'status' => 'ready',
            'label' => 'CRM-результат для цієї дати зафіксовано.',
            'phones' => $cache,
        ];
    }

    private function shouldCollectCrmForDay(CarbonImmutable $day): bool
    {
        $timezone = $day->getTimezone();
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $targetDay = $day->startOfDay();

        if ($targetDay->lessThan($today)) {
            return true;
        }

        if (! $targetDay->equalTo($today)) {
            return false;
        }

        [$hours, $minutes] = array_map('intval', explode(':', self::CRM_LOOKUP_READY_AT));
        $readyAt = $today->setTime($hours, $minutes);

        return CarbonImmutable::now($timezone)->greaterThanOrEqualTo($readyAt);
    }

    /**
     * @param  array<int, string>  $phones
     * @return array{phones:array<string, array{phone_exist:bool,manager:?string,case:?string}>, errors:int}
     */
    private function lookupPhonesForDay(array $phones, CarbonImmutable $day): array
    {
        $phones = array_values(array_unique(array_filter($phones)));
        if ($phones === []) {
            return ['phones' => [], 'errors' => 0];
        }

        $endpoint = trim((string) config('call_center.crm.phone_lookup_url', 'https://yaprofi.ua/api/call_center_get_phone_data'));
        $primaryLookup = $this->runLookupPool(
            $phones,
            $endpoint,
            fn (string $phone): ?array => $this->crmPhoneLookupService->queryForDay($phone, $day),
        );

        $results = $primaryLookup['phones'];
        $errors = $primaryLookup['errors'];
        $fallbackPhones = [];

        foreach ($results as $phone => $lookup) {
            if ($this->crmPhoneLookupService->shouldRetryWithHistoricalRange($lookup)) {
                $fallbackPhones[] = $phone;
            }
        }

        if ($fallbackPhones !== []) {
            $fallbackLookup = $this->runLookupPool(
                $fallbackPhones,
                $endpoint,
                fn (string $phone): ?array => $this->crmPhoneLookupService->historicalQueryForDay($phone, $day),
            );

            $results = array_replace($results, $fallbackLookup['phones']);
            $errors += $fallbackLookup['errors'];
        }

        foreach ($results as $phone => $lookup) {
            $this->crmCallStatusStore->storeLookupForPhone($phone, $lookup);
        }

        return [
            'phones' => $results,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, string>  $phones
     * @param  callable(string): (?array{phone:string,startDate?:string,endDate?:string})  $queryBuilder
     * @return array{phones:array<string, array{phone_exist:bool,manager:?string,case:?string}>, errors:int}
     */
    private function runLookupPool(array $phones, string $endpoint, callable $queryBuilder): array
    {
        $results = [];
        $errors = 0;

        foreach (array_chunk($phones, 12) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $queryBuilder, $endpoint) {
                $requests = [];

                foreach ($chunk as $phone) {
                    $query = $queryBuilder($phone);
                    if ($query === null) {
                        continue;
                    }

                    $requests[$phone] = $pool
                        ->as($phone)
                        ->acceptJson()
                        ->connectTimeout(5)
                        ->timeout(10)
                        ->get($endpoint, $query);
                }

                return $requests;
            });

            foreach ($chunk as $phone) {
                $response = $responses[$phone] ?? null;
                if ($response instanceof ConnectionException) {
                    $errors += 1;
                    continue;
                }

                if (! $response instanceof Response || ! $response->successful()) {
                    $errors += 1;
                    continue;
                }

                $payload = $response->json();
                if (! is_array($payload)) {
                    $errors += 1;
                    continue;
                }

                $results[$phone] = $this->crmPhoneLookupService->mapLookupPayload($phone, $payload);
            }
        }

        return [
            'phones' => $results,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{
     *     phones: array<string, array{phone_exist:bool,manager:?string,case:?string}>,
     *     checked_phones: array<int, string>,
     *     is_complete: bool,
     *     had_errors: bool
     * }
     */
    private function readCrmCache(CarbonImmutable $day): array
    {
        $path = $this->crmCachePath($day);
        if (! Storage::disk('local')->exists($path)) {
            return [
                'phones' => [],
                'checked_phones' => [],
                'is_complete' => false,
                'had_errors' => false,
            ];
        }

        $payload = json_decode((string) Storage::disk('local')->get($path), true);
        if (! is_array($payload)) {
            return [
                'phones' => [],
                'checked_phones' => [],
                'is_complete' => false,
                'had_errors' => false,
            ];
        }

        $isLegacyCachePayload = array_key_exists('checked_at', $payload) && ! array_key_exists('is_complete', $payload);
        $phones = $this->extractPhonesFromCrmCachePayload($payload);

        if ($phones === []) {
            return [
                'phones' => [],
                'checked_phones' => [],
                'is_complete' => false,
                'had_errors' => false,
            ];
        }

        $checkedPhones = array_values(array_filter(
            is_array($payload['checked_phones'] ?? null) ? $payload['checked_phones'] : array_keys($phones),
            static fn (mixed $phone): bool => is_string($phone) && trim($phone) !== ''
        ));

        return [
            'phones' => $phones,
            'checked_phones' => $checkedPhones,
            'is_complete' => $isLegacyCachePayload ? true : (bool) ($payload['is_complete'] ?? false),
            'had_errors' => (bool) ($payload['had_errors'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{phone_exist:bool,manager:?string,case:?string}>
     */
    private function extractPhonesFromCrmCachePayload(array $payload): array
    {
        $candidates = [];

        if (is_array($payload['phones'] ?? null)) {
            $candidates[] = $payload['phones'];
        }

        if (is_array($payload['results'] ?? null)) {
            $candidates[] = $payload['results'];
        }

        // Backward compatibility: older cache snapshots could store phone results at the root level.
        $candidates[] = $payload;

        foreach ($candidates as $candidate) {
            $normalized = [];

            foreach ($candidate as $phone => $item) {
                if (! is_string($phone) || trim($phone) === '' || ! is_array($item)) {
                    continue;
                }

                if (
                    ! array_key_exists('phone_exist', $item)
                    && ! array_key_exists('manager', $item)
                    && ! array_key_exists('case', $item)
                ) {
                    continue;
                }

                $normalized[$phone] = [
                    'phone_exist' => (bool) ($item['phone_exist'] ?? false),
                    'manager' => isset($item['manager']) ? trim((string) $item['manager']) ?: null : null,
                    'case' => isset($item['case']) ? trim((string) $item['case']) ?: null : null,
                ];
            }

            if ($normalized !== []) {
                return $normalized;
            }
        }

        return [];
    }

    /**
     * @param  array<string, array{phone_exist:bool,manager:?string,case:?string}>  $phones
     */
    private function writeCrmCache(
        CarbonImmutable $day,
        array $phones,
        array $checkedPhones,
        bool $isComplete,
        bool $hadErrors,
    ): void
    {
        Storage::disk('local')->makeDirectory(self::CRM_CACHE_DIRECTORY);

        $path = $this->crmCachePath($day);
        Storage::disk('local')->put($path, json_encode([
            'date' => $day->format('Y-m-d'),
            'checked_at' => now()->toIso8601String(),
            'checked_phones' => array_values(array_unique(array_filter($checkedPhones, static fn (mixed $phone): bool => is_string($phone) && trim($phone) !== ''))),
            'is_complete' => $isComplete,
            'had_errors' => $hadErrors,
            'phones' => $phones,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->normalizeCrmCachePermissions($path);
    }

    /**
     * @param  array<string, array{phone_exist:bool,manager:?string,case:?string}>  $cache
     */
    private function persistCrmCacheToCalls(array $cache): void
    {
        foreach ($cache as $phone => $lookup) {
            $this->crmCallStatusStore->storeLookupForPhone((string) $phone, array_merge($lookup, [
                'phone' => (string) $phone,
            ]));
        }
    }

    private function crmCachePath(CarbonImmutable $day): string
    {
        return self::CRM_CACHE_DIRECTORY.'/'.$day->format('Y-m-d').'.json';
    }

    private function normalizeCrmCachePermissions(string $relativePath): void
    {
        $directory = storage_path('app/'.self::CRM_CACHE_DIRECTORY);
        $file = storage_path('app/'.$relativePath);

        foreach ([$directory, $file] as $path) {
            if (! is_string($path) || $path === '' || ! file_exists($path)) {
                continue;
            }

            @chgrp($path, 'www-data');
        }

        if (is_dir($directory)) {
            @chmod($directory, 0775);
        }

        if (is_file($file)) {
            @chmod($file, 0664);
        }
    }

    /**
     * @return array<int, BinotelApiCallCompleted>
     */
    private function callsForPeriod(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return BinotelApiCallCompleted::query()
            ->with('feedback')
            ->where('request_type', 'apiCallCompleted')
            ->whereNotNull('call_details_general_call_id')
            ->where('call_details_general_call_id', '<>', '')
            ->where('call_details_disposition', 'ANSWER')
            ->where('call_details_billsec', '>', 0)
            ->whereBetween('interaction_number', [1, 20])
            ->whereBetween('call_details_start_time', [$start->startOfDay()->getTimestamp(), $end->endOfDay()->getTimestamp()])
            ->orderBy('call_details_start_time')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @return array<int, array{checklist_id:string,interaction_number:int,direction:string}>
     */
    private function configuredChecklistRoutingRules(): array
    {
        $evaluationSettings = $this->automationStore->processingSettings()['evaluation'];
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
            $direction = trim((string) ($rule['direction'] ?? 'any'));
            $direction = in_array($direction, ['in', 'out', 'any'], true) ? $direction : 'any';

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

    private function minimumDurationMinutes(): int
    {
        $evaluationSettings = $this->automationStore->processingSettings()['evaluation'];

        return max(0, min(10, (int) ($evaluationSettings['minimum_duration_minutes'] ?? 0)));
    }

    private function meetsMinimumDuration(BinotelApiCallCompleted $call): bool
    {
        $minimumDurationMinutes = $this->minimumDurationMinutes();

        if ($minimumDurationMinutes <= 0) {
            return true;
        }

        return (int) ($call->call_details_billsec ?? 0) >= ($minimumDurationMinutes * 60);
    }

    /**
     * @param  array<int, array{checklist_id:string,interaction_number:int,direction:string}>  $rules
     * @return array{checklist_id:string,interaction_number:int,direction:string}|null
     */
    private function resolveMatchedRule(BinotelApiCallCompleted $call, array $rules): ?array
    {
        $interactionNumber = max(1, (int) ($call->interaction_number ?? 0));
        if ($interactionNumber <= 0) {
            return null;
        }

        $direction = $this->direction($call);

        foreach ($rules as $rule) {
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

    private function direction(BinotelApiCallCompleted $call): string
    {
        $callType = trim((string) ($call->call_details_call_type ?? ''));

        return in_array($callType, self::INCOMING_CALL_TYPES, true) ? 'in' : 'out';
    }

    private function ruleSignature(int $interactionNumber, string $direction): string
    {
        return $interactionNumber.'|'.$direction;
    }

    private function ruleLabel(int $interactionNumber, string $direction): string
    {
        return $this->interactionLabel($interactionNumber).' · '.$this->directionLabel($direction);
    }

    private function interactionLabel(int $interactionNumber): string
    {
        $suffix = $interactionNumber === 1 ? 'й' : 'й';

        return $interactionNumber.'-'.$suffix.' дзвінок';
    }

    private function directionLabel(string $direction): string
    {
        return match ($direction) {
            'in' => 'вхідний',
            'out' => 'вихідний',
            default => 'будь-який',
        };
    }

    private function compareRows(array $left, array $right): int
    {
        $interactionDiff = ((int) ($left['interactionNumber'] ?? 0)) <=> ((int) ($right['interactionNumber'] ?? 0));
        if ($interactionDiff !== 0) {
            return $interactionDiff;
        }

        return strcmp((string) ($left['direction'] ?? ''), (string) ($right['direction'] ?? ''));
    }

    private function formatDayKey(BinotelApiCallCompleted $call, string $timezone): string
    {
        $timestamp = (int) ($call->call_details_start_time ?? 0);
        if ($timestamp <= 0) {
            return '';
        }

        return CarbonImmutable::createFromTimestamp($timestamp, $timezone)->format('d.m.Y');
    }

    private function isProcessedCall(BinotelApiCallCompleted $call): bool
    {
        if (trim((string) ($call->alt_auto_status ?? '')) === 'completed') {
            return true;
        }

        $feedback = $call->feedback;
        if ($feedback === null) {
            return false;
        }

        return trim((string) ($feedback->transcription_status ?? '')) === 'completed'
            || in_array(trim((string) ($feedback->evaluation_status ?? '')), ['pending', 'running', 'completed'], true)
            || $feedback->evaluation_score !== null
            || $feedback->evaluated_at !== null
            || $feedback->transcribed_at !== null;
    }
}
