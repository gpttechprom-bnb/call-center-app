<?php

namespace App\Services;

use App\Models\BinotelApiCallCompleted;
use App\Models\BinotelApiCallCompletedHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Psr\Log\LoggerInterface;

class BinotelApiCallCompletedStore
{
    public const ZERO_INTERACTION_NUMBER = 0;

    private ?bool $hasInteractionNumberColumnCache = null;

    private ?bool $hasCrmStatusColumnsCache = null;

    private ?bool $hasOptimizedLookupColumnsCache = null;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(array $payload): ?BinotelApiCallCompleted
    {
        $callDetails = is_array($payload['callDetails'] ?? null)
            ? $payload['callDetails']
            : [];

        $generalCallId = $this->nullableString($callDetails['generalCallID'] ?? null);
        $callId = $this->nullableString($callDetails['callID'] ?? null);

        if ($generalCallId === null && $callId === null) {
            $this->logger()->warning('Binotel apiCallCompleted payload skipped because both generalCallID and callID are missing.', [
                'payload' => $payload,
            ]);

            return null;
        }

        /** @var BinotelApiCallCompleted $record */
        $record = DB::transaction(function () use ($payload, $callDetails, $generalCallId, $callId): BinotelApiCallCompleted {
            $record = $this->findExistingRecord($generalCallId, $callId) ?? new BinotelApiCallCompleted();
            $previousInteractionGroup = $this->interactionGroup($record);

            $record->fill($this->mapMainAttributes($payload, $callDetails));
            $record->save();

            $record->historyItems()->delete();
            $record->historyItems()->createMany($this->mapHistoryItems($callDetails));

            $currentInteractionGroup = $this->interactionGroup($record);
            $this->recalculateInteractionNumbersForGroup($currentInteractionGroup);

            if (! $this->sameInteractionGroup($previousInteractionGroup, $currentInteractionGroup)) {
                $this->recalculateInteractionNumbersForGroup($previousInteractionGroup);
            }

            if ($currentInteractionGroup === null && $this->hasInteractionNumberColumn()) {
                BinotelApiCallCompleted::query()
                    ->whereKey($record->id)
                    ->update(['interaction_number' => null]);
            }

            return $record->fresh(['historyItems']);
        });

        return $record;
    }

    private function findExistingRecord(?string $generalCallId, ?string $callId): ?BinotelApiCallCompleted
    {
        $query = BinotelApiCallCompleted::query();

        if ($generalCallId !== null) {
            $query->where('call_details_general_call_id', $generalCallId);
        }

        if ($callId !== null) {
            if ($generalCallId !== null) {
                $query->orWhere('call_details_call_id', $callId);
            } else {
                $query->where('call_details_call_id', $callId);
            }
        }

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $callDetails
     * @return array<string, mixed>
     */
    private function mapMainAttributes(array $payload, array $callDetails): array
    {
        $attributes = [
            'request_type' => $this->nullableString($payload['requestType'] ?? null),
            'attempts_counter' => $this->nullableInt($payload['attemptsCounter'] ?? null),
            'language' => $this->nullableString($payload['language'] ?? null),
            'my_binotel_domain' => $this->nullableString($payload['myBinotelDomain'] ?? null),
            'call_details_company_id' => $this->nullableString($callDetails['companyID'] ?? null),
            'call_details_general_call_id' => $this->nullableString($callDetails['generalCallID'] ?? null),
            'call_details_call_id' => $this->nullableString($callDetails['callID'] ?? null),
            'call_details_start_time' => $this->nullableInt($callDetails['startTime'] ?? null),
            'call_details_call_type' => $this->nullableString($callDetails['callType'] ?? null),
            'call_details_internal_number' => $this->nullableString($callDetails['internalNumber'] ?? null),
            'call_details_internal_additional_data' => $this->nullableString($callDetails['internalAdditionalData'] ?? null),
            'call_details_external_number' => $this->nullableString($callDetails['externalNumber'] ?? null),
            'call_details_waitsec' => $this->nullableInt($callDetails['waitsec'] ?? null),
            'call_details_billsec' => $this->nullableInt($callDetails['billsec'] ?? null),
            'call_details_disposition' => $this->nullableString($callDetails['disposition'] ?? null),
            'call_details_recording_status' => $this->nullableString($callDetails['recordingStatus'] ?? null),
            'call_details_is_new_call' => $this->nullableBool($callDetails['isNewCall'] ?? null),
            'call_details_who_hung_up' => $this->nullableString($callDetails['whoHungUp'] ?? null),
            'call_details_customer_data' => $this->nullableArray($callDetails['customerData'] ?? null),
            'call_details_employee_name' => $this->nullableString(data_get($callDetails, 'employeeData.name')),
            'call_details_employee_email' => $this->nullableString(data_get($callDetails, 'employeeData.email')),
            'call_details_pbx_number' => $this->nullableString(data_get($callDetails, 'pbxNumberData.number')),
            'call_details_pbx_name' => $this->nullableString(data_get($callDetails, 'pbxNumberData.name')),
            'call_details_customer_from_outside_id' => $this->nullableString(data_get($callDetails, 'customerDataFromOutside.id')),
            'call_details_customer_from_outside_external_number' => $this->nullableString(data_get($callDetails, 'customerDataFromOutside.externalNumber')),
            'call_details_customer_from_outside_name' => $this->nullableString(data_get($callDetails, 'customerDataFromOutside.name')),
            'call_details_customer_from_outside_link_to_crm_url' => $this->nullableString(data_get($callDetails, 'customerDataFromOutside.linkToCrmUrl')),
            'call_details_call_tracking_id' => $this->nullableString(data_get($callDetails, 'callTrackingData.id')),
            'call_details_call_tracking_type' => $this->nullableString(data_get($callDetails, 'callTrackingData.type')),
            'call_details_call_tracking_ga_client_id' => $this->nullableString(data_get($callDetails, 'callTrackingData.gaClientId')),
            'call_details_call_tracking_first_visit_at' => $this->nullableInt(data_get($callDetails, 'callTrackingData.firstVisitAt')),
            'call_details_call_tracking_full_url' => $this->nullableString(data_get($callDetails, 'callTrackingData.fullUrl')),
            'call_details_call_tracking_utm_source' => $this->nullableString(data_get($callDetails, 'callTrackingData.utm_source')),
            'call_details_call_tracking_utm_medium' => $this->nullableString(data_get($callDetails, 'callTrackingData.utm_medium')),
            'call_details_call_tracking_utm_campaign' => $this->nullableString(data_get($callDetails, 'callTrackingData.utm_campaign')),
            'call_details_call_tracking_utm_content' => $this->nullableString(data_get($callDetails, 'callTrackingData.utm_content')),
            'call_details_call_tracking_utm_term' => $this->nullableString(data_get($callDetails, 'callTrackingData.utm_term')),
            'call_details_call_tracking_ip_address' => $this->nullableString(data_get($callDetails, 'callTrackingData.ipAddress')),
            'call_details_call_tracking_geoip_country' => $this->nullableString(data_get($callDetails, 'callTrackingData.geoipCountry')),
            'call_details_call_tracking_geoip_region' => $this->nullableString(data_get($callDetails, 'callTrackingData.geoipRegion')),
            'call_details_call_tracking_geoip_city' => $this->nullableString(data_get($callDetails, 'callTrackingData.geoipCity')),
            'call_details_call_tracking_geoip_org' => $this->nullableString(data_get($callDetails, 'callTrackingData.geoipOrg')),
            'call_details_call_tracking_domain' => $this->nullableString(data_get($callDetails, 'callTrackingData.domain')),
            'call_details_call_tracking_ga_tracking_id' => $this->nullableString(data_get($callDetails, 'callTrackingData.gaTrackingId')),
            'call_details_call_tracking_time_spent_on_site_before_make_call' => $this->nullableInt(data_get($callDetails, 'callTrackingData.timeSpentOnSiteBeforeMakeCall')),
            'call_details_link_to_call_record_overlay_in_my_business' => $this->nullableString($callDetails['linkToCallRecordOverlayInMyBusiness'] ?? null),
            'call_details_link_to_call_record_in_my_business' => $this->nullableString($callDetails['linkToCallRecordInMyBusiness'] ?? null),
        ];

        if ($this->hasCrmStatusColumns()) {
            $attributes['crm_normalized_phone'] = $this->normalizeCrmPhone(
                data_get($callDetails, 'externalNumber') ?: data_get($callDetails, 'customerDataFromOutside.externalNumber')
            ) ?: null;
        }

        if ($this->hasOptimizedLookupColumns()) {
            $attributes['interaction_phone_key'] = $this->normalizeInteractionPhone($callDetails['externalNumber'] ?? null) ?: null;
            $attributes['interaction_manager_key'] = $this->interactionManagerKey(
                data_get($callDetails, 'employeeData.email'),
                $callDetails['internalNumber'] ?? null,
                data_get($callDetails, 'employeeData.name')
            ) ?: null;
            $attributes['employee_display_name'] = $this->employeeDisplayName(
                data_get($callDetails, 'employeeData.name'),
                $callDetails['internalNumber'] ?? null
            );
            $attributes['direction_key'] = $this->directionKey($callDetails['callType'] ?? null);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $callDetails
     * @return array<int, array<string, mixed>>
     */
    private function mapHistoryItems(array $callDetails): array
    {
        $historyItems = is_array($callDetails['historyData'] ?? null)
            ? $callDetails['historyData']
            : [];

        $mapped = [];

        foreach ($historyItems as $index => $historyItem) {
            if (! is_array($historyItem)) {
                continue;
            }

            $mapped[] = [
                'sort_order' => $index,
                'waitsec' => $this->nullableInt($historyItem['waitsec'] ?? null),
                'billsec' => $this->nullableInt($historyItem['billsec'] ?? null),
                'disposition' => $this->nullableString($historyItem['disposition'] ?? null),
                'internal_number' => $this->nullableString($historyItem['internalNumber'] ?? null),
                'internal_additional_data' => $this->nullableString($historyItem['internalAdditionalData'] ?? null),
                'employee_name' => $this->nullableString(data_get($historyItem, 'employeeData.name')),
                'employee_email' => $this->nullableString(data_get($historyItem, 'employeeData.email')),
            ];
        }

        return $mapped;
    }

    /**
     * @return array{phone:string, manager:string}|null
     */
    private function interactionGroup(BinotelApiCallCompleted $record): ?array
    {
        $phone = $this->hasOptimizedLookupColumns()
            ? trim((string) ($record->interaction_phone_key ?? ''))
            : $this->normalizeInteractionPhone($record->call_details_external_number ?? null);
        $manager = $this->hasOptimizedLookupColumns()
            ? trim((string) ($record->interaction_manager_key ?? ''))
            : $this->interactionManagerKey(
                $record->call_details_employee_email ?? null,
                $record->call_details_internal_number ?? null,
                $record->call_details_employee_name ?? null
            );

        if ($phone === '' || $manager === '') {
            return null;
        }

        return [
            'phone' => $phone,
            'manager' => $manager,
        ];
    }

    /**
     * @param  array{phone:string, manager:string}|null  $group
     */
    private function recalculateInteractionNumbersForGroup(?array $group): void
    {
        if ($group === null || ! $this->hasInteractionNumberColumn()) {
            return;
        }

        $query = BinotelApiCallCompleted::query()
            ->with('historyItems');

        if ($this->hasOptimizedLookupColumns()) {
            $query
                ->where('interaction_phone_key', $group['phone'])
                ->where('interaction_manager_key', $group['manager']);
        } else {
            $query->whereNotNull('call_details_external_number');
        }

        $records = $query->get([
                'id',
                'call_details_start_time',
                'call_details_external_number',
                'call_details_employee_email',
                'call_details_internal_number',
                'call_details_employee_name',
                'call_details_disposition',
                'call_details_billsec',
                'interaction_number',
                'interaction_phone_key',
                'interaction_manager_key',
            ])
            ->filter(fn (BinotelApiCallCompleted $call): bool => (
                $this->interactionGroup($call) === $group
            ))
            ->sort(function (BinotelApiCallCompleted $left, BinotelApiCallCompleted $right): int {
                $leftTime = (int) ($left->call_details_start_time ?? 0);
                $rightTime = (int) ($right->call_details_start_time ?? 0);

                if ($leftTime !== $rightTime) {
                    return $leftTime <=> $rightTime;
                }

                return (int) $left->id <=> (int) $right->id;
            })
            ->values();

        $meaningfulInteractionNumber = 0;

        foreach ($records as $call) {
            $interactionNumber = self::ZERO_INTERACTION_NUMBER;

            if ($this->isMeaningfulInteraction($call)) {
                $meaningfulInteractionNumber++;
                $interactionNumber = $meaningfulInteractionNumber;
            }

            if ((int) ($call->interaction_number ?? 0) === $interactionNumber) {
                continue;
            }

            BinotelApiCallCompleted::query()
                ->whereKey($call->id)
                ->update(['interaction_number' => $interactionNumber]);
        }
    }

    public function recalculateAllInteractionNumbers(): int
    {
        if (! $this->hasInteractionNumberColumn()) {
            return 0;
        }

        $records = BinotelApiCallCompleted::query()
            ->with('historyItems')
            ->get([
                'id',
                'call_details_start_time',
                'call_details_external_number',
                'call_details_employee_email',
                'call_details_internal_number',
                'call_details_employee_name',
                'call_details_disposition',
                'call_details_billsec',
                'interaction_number',
                'interaction_phone_key',
                'interaction_manager_key',
            ]);

        $groups = [];
        $updated = 0;

        foreach ($records as $record) {
            $group = $this->interactionGroup($record);

            if ($group === null) {
                if ((int) ($record->interaction_number ?? 0) !== 0) {
                    BinotelApiCallCompleted::query()
                        ->whereKey($record->id)
                        ->update(['interaction_number' => self::ZERO_INTERACTION_NUMBER]);
                    $updated++;
                }

                continue;
            }

            $key = $group['phone'].'::'.$group['manager'];
            $groups[$key]['group'] = $group;
            $groups[$key]['records'][] = $record;
        }

        foreach ($groups as $entry) {
            $groupRecords = collect($entry['records'] ?? [])
                ->sort(function (BinotelApiCallCompleted $left, BinotelApiCallCompleted $right): int {
                    $leftTime = (int) ($left->call_details_start_time ?? 0);
                    $rightTime = (int) ($right->call_details_start_time ?? 0);

                    if ($leftTime !== $rightTime) {
                        return $leftTime <=> $rightTime;
                    }

                    return (int) $left->id <=> (int) $right->id;
                })
                ->values();

            $meaningfulInteractionNumber = 0;

            foreach ($groupRecords as $record) {
                $interactionNumber = self::ZERO_INTERACTION_NUMBER;

                if ($this->isMeaningfulInteraction($record)) {
                    $meaningfulInteractionNumber++;
                    $interactionNumber = $meaningfulInteractionNumber;
                }

                if ((int) ($record->interaction_number ?? 0) === $interactionNumber) {
                    continue;
                }

                BinotelApiCallCompleted::query()
                    ->whereKey($record->id)
                    ->update(['interaction_number' => $interactionNumber]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  array{phone:string, manager:string}|null  $left
     * @param  array{phone:string, manager:string}|null  $right
     */
    private function sameInteractionGroup(?array $left, ?array $right): bool
    {
        return $left === $right;
    }

    private function hasInteractionNumberColumn(): bool
    {
        if ($this->hasInteractionNumberColumnCache !== null) {
            return $this->hasInteractionNumberColumnCache;
        }

        return $this->hasInteractionNumberColumnCache = Schema::hasColumn('binotel_api_call_completeds', 'interaction_number');
    }

    private function hasCrmStatusColumns(): bool
    {
        if ($this->hasCrmStatusColumnsCache !== null) {
            return $this->hasCrmStatusColumnsCache;
        }

        return $this->hasCrmStatusColumnsCache = Schema::hasColumn('binotel_api_call_completeds', 'crm_normalized_phone');
    }

    private function hasOptimizedLookupColumns(): bool
    {
        if ($this->hasOptimizedLookupColumnsCache !== null) {
            return $this->hasOptimizedLookupColumnsCache;
        }

        return $this->hasOptimizedLookupColumnsCache =
            Schema::hasColumn('binotel_api_call_completeds', 'interaction_phone_key')
            && Schema::hasColumn('binotel_api_call_completeds', 'interaction_manager_key')
            && Schema::hasColumn('binotel_api_call_completeds', 'employee_display_name')
            && Schema::hasColumn('binotel_api_call_completeds', 'direction_key');
    }

    private function isMeaningfulInteraction(BinotelApiCallCompleted $record): bool
    {
        if (
            trim((string) ($record->call_details_disposition ?? '')) === 'ANSWER'
            && max(0, (int) ($record->call_details_billsec ?? 0)) > 0
        ) {
            return true;
        }

        if (! $record->relationLoaded('historyItems')) {
            $record->loadMissing('historyItems');
        }

        return $record->historyItems->contains(function (BinotelApiCallCompletedHistory $historyItem): bool {
            return trim((string) ($historyItem->disposition ?? '')) === 'ANSWER'
                && max(0, (int) ($historyItem->billsec ?? 0)) > 0;
        });
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeInteractionPhone($value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? '')) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            return substr($digits, 2);
        }

        return $digits;
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeCrmPhone($value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? '')) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            $digits = substr($digits, 2);
        }

        return strlen($digits) === 10 ? $digits : '';
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeInteractionToken($value): string
    {
        $normalized = trim((string) ($value ?? ''));
        $normalized = preg_replace('/^Wire:\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+Sip$/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return function_exists('mb_strtolower')
            ? mb_strtolower($normalized, 'UTF-8')
            : strtolower($normalized);
    }

    private function interactionManagerKey($email, $internalNumber, $employeeName): string
    {
        foreach ([$email, $internalNumber, $employeeName] as $candidate) {
            $normalized = $this->normalizeInteractionToken($candidate);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function employeeDisplayName($employeeName, $internalNumber): string
    {
        $name = trim((string) ($employeeName ?? ''));
        if ($name !== '') {
            return $name;
        }

        $internal = trim((string) ($internalNumber ?? ''));
        if ($internal !== '') {
            return 'Внутрішній номер '.$internal;
        }

        return 'Не визначено';
    }

    private function directionKey($callType): string
    {
        $normalized = trim((string) ($callType ?? ''));

        return in_array($normalized, ['0', 'in', 'incoming'], true) ? 'in' : 'out';
    }

    /**
     * @param  mixed  $value
     */
    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

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

    /**
     * @param  mixed  $value
     */
    private function nullableBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>|null
     */
    private function nullableArray($value): ?array
    {
        return is_array($value) ? $value : null;
    }

    private function logger(): LoggerInterface
    {
        return Log::channel((string) config('binotel.log_channel', 'stack'));
    }
}
