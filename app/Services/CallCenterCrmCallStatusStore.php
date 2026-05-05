<?php

namespace App\Services;

use App\Models\BinotelApiCallCompleted;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CallCenterCrmCallStatusStore
{
    public function __construct(
        private readonly CallCenterCrmPhoneLookupService $crmPhoneLookupService,
    ) {
    }

    public function phoneForCall(BinotelApiCallCompleted $call): string
    {
        return $this->crmPhoneLookupService->normalizePhone(
            (string) ($call->call_details_external_number ?? $call->call_details_customer_from_outside_external_number ?? '')
        );
    }

    /**
     * @param  array<string, mixed>  $lookup
     */
    public function storeLookupForCall(BinotelApiCallCompleted $call, array $lookup, ?CarbonInterface $checkedAt = null): void
    {
        if (! $this->hasCrmColumns()) {
            return;
        }

        $phone = $this->crmPhoneLookupService->normalizePhone((string) ($lookup['phone'] ?? ''))
            ?: $this->phoneForCall($call);

        if ($phone === '') {
            return;
        }

        $attributes = $this->lookupAttributes($phone, $lookup, $checkedAt);
        $this->updateMatchingCalls($phone, $attributes);
    }

    /**
     * @param  array<string, mixed>  $lookup
     */
    public function storeLookupForPhone(string $phone, array $lookup, ?CarbonInterface $checkedAt = null): void
    {
        if (! $this->hasCrmColumns()) {
            return;
        }

        $normalizedPhone = $this->crmPhoneLookupService->normalizePhone($phone);
        if ($normalizedPhone === '') {
            return;
        }

        $attributes = $this->lookupAttributes($normalizedPhone, $lookup, $checkedAt);
        $this->updateMatchingCalls($normalizedPhone, $attributes);
    }

    public function storeLookupErrorForCall(BinotelApiCallCompleted $call, string $message): void
    {
        if (! $this->hasCrmColumns()) {
            return;
        }

        $phone = $this->phoneForCall($call);
        if ($phone === '') {
            return;
        }

        $call->forceFill([
            'crm_normalized_phone' => $phone,
            'crm_lookup_error' => trim($message) ?: 'CRM lookup failed.',
            'crm_checked_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(BinotelApiCallCompleted $call): array
    {
        $phoneExists = $this->nullableBool($call->crm_phone_exists ?? null);
        $missing = $this->nullableBool($call->crm_missing ?? null);

        return [
            'crmPhoneExists' => $phoneExists,
            'crmCase' => trim((string) ($call->crm_case ?? '')) ?: null,
            'crmManager' => trim((string) ($call->crm_manager ?? '')) ?: null,
            'crmCheckedAt' => $call->crm_checked_at instanceof CarbonInterface
                ? $call->crm_checked_at->toIso8601String()
                : null,
            'crmLookupError' => trim((string) ($call->crm_lookup_error ?? '')) ?: null,
            'missingInCrm' => $missing,
        ];
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @return array<string, mixed>
     */
    private function lookupAttributes(string $phone, array $lookup, ?CarbonInterface $checkedAt): array
    {
        $phoneExists = (bool) ($lookup['phone_exist'] ?? false);

        return [
            'crm_normalized_phone' => $phone,
            'crm_phone_exists' => $phoneExists,
            'crm_missing' => ! $phoneExists,
            'crm_manager' => isset($lookup['manager']) ? trim((string) $lookup['manager']) ?: null : null,
            'crm_case' => isset($lookup['case']) ? trim((string) $lookup['case']) ?: null : null,
            'crm_checked_at' => $checkedAt ?? now(),
            'crm_lookup_error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateMatchingCalls(string $phone, array $attributes): void
    {
        $ids = [];

        BinotelApiCallCompleted::query()
            ->select(['id', 'crm_normalized_phone', 'call_details_external_number', 'call_details_customer_from_outside_external_number'])
            ->where(function ($query) use ($phone): void {
                $query
                    ->where('crm_normalized_phone', $phone)
                    ->orWhereNotNull('call_details_external_number')
                    ->orWhereNotNull('call_details_customer_from_outside_external_number');
            })
            ->orderBy('id')
            ->chunkById(500, function ($calls) use ($phone, &$ids): void {
                foreach ($calls as $call) {
                    $storedPhone = trim((string) ($call->crm_normalized_phone ?? ''));
                    $externalPhone = $this->crmPhoneLookupService->normalizePhone((string) ($call->call_details_external_number ?? ''));
                    $customerPhone = $this->crmPhoneLookupService->normalizePhone((string) ($call->call_details_customer_from_outside_external_number ?? ''));

                    if ($storedPhone === $phone || $externalPhone === $phone || $customerPhone === $phone) {
                        $ids[] = (int) $call->id;
                    }
                }
            });

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return;
        }

        foreach (array_chunk($ids, 500) as $chunk) {
            BinotelApiCallCompleted::query()
                ->whereKey($chunk)
                ->update($attributes);
        }
    }

    private function hasCrmColumns(): bool
    {
        try {
            return Schema::hasColumn('binotel_api_call_completeds', 'crm_missing')
                && Schema::hasColumn('binotel_api_call_completeds', 'crm_normalized_phone');
        } catch (Throwable) {
            return false;
        }
    }

    private function nullableBool(mixed $value): ?bool
    {
        return $value === null ? null : (bool) $value;
    }
}
