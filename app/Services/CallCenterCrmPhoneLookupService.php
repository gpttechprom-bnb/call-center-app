<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CallCenterCrmPhoneLookupService
{
    private const DEFAULT_ENDPOINT = 'https://yaprofi.ua/api/call_center_get_phone_data';

    /**
     * @return array{phone:string,phone_exist:bool,manager:?string,case:?string,response:array<string,mixed>}|null
     */
    public function lookup(?string $phone, ?string $startDate = null, ?string $endDate = null): ?array
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return null;
        }

        $query = $this->buildLookupQuery($normalizedPhone, $startDate, $endDate);
        $fallbackQuery = $this->historicalQueryForLookup($normalizedPhone, $startDate, $endDate);

        return $this->performLookup($normalizedPhone, $query, $fallbackQuery);
    }

    public function lookupForDay(?string $phone, CarbonInterface $day): ?array
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return null;
        }

        $query = $this->queryForDay($normalizedPhone, $day);
        $fallbackQuery = $this->historicalQueryForDay($normalizedPhone, $day);

        return $this->performLookup($normalizedPhone, $query, $fallbackQuery);
    }

    public function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($phone ?? '')) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            $digits = substr($digits, 2);
        }

        return strlen($digits) === 10 ? $digits : '';
    }

    /**
     * For "today" the CRM API uses its own default range, so we intentionally do not
     * send startDate/endDate. For any other day we pin the lookup to that exact date.
     *
     * @return array{phone:string,startDate?:string,endDate?:string}|null
     */
    public function queryForDay(?string $phone, CarbonInterface $day): ?array
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return null;
        }

        $query = ['phone' => $normalizedPhone];
        $timezone = $day->getTimezone();
        $targetDay = CarbonImmutable::instance($day)->setTimezone($timezone)->startOfDay();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if (! $targetDay->equalTo($today)) {
            $date = $targetDay->format('d.m.y');
            $query['startDate'] = $date;
            $query['endDate'] = $date;
        }

        return $query;
    }

    /**
     * When the CRM endpoint is queried only for a single day, it can miss leads that
     * already existed earlier in the year but were still active by the target date.
     * This broader range is used only as a fallback after a same-day/no-date miss.
     *
     * @return array{phone:string,startDate:string,endDate:string}|null
     */
    public function historicalQueryForDay(?string $phone, CarbonInterface $day): ?array
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return null;
        }

        $targetDay = CarbonImmutable::instance($day)->startOfDay();

        return [
            'phone' => $normalizedPhone,
            'startDate' => $targetDay->startOfYear()->format('d.m.y'),
            'endDate' => $targetDay->format('d.m.y'),
        ];
    }

    /**
     * @param  array{phone_exist:bool,manager:?string,case:?string,response:array<string,mixed>}  $lookup
     */
    public function shouldRetryWithHistoricalRange(array $lookup): bool
    {
        if ((bool) ($lookup['phone_exist'] ?? false)) {
            return false;
        }

        $case = mb_strtolower(trim((string) ($lookup['case'] ?? '')));

        return $case === ''
            || $case === 'no matches found'
            || $case === 'need add phone number';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{phone:string,phone_exist:bool,manager:?string,case:?string,response:array<string,mixed>}
     */
    public function mapLookupPayload(string $phone, array $payload): array
    {
        return [
            'phone' => $this->normalizePhone($phone),
            'phone_exist' => (bool) ($payload['phone_exist'] ?? false),
            'manager' => isset($payload['manager']) ? trim((string) ($payload['manager'])) ?: null : null,
            'case' => isset($payload['case']) ? trim((string) ($payload['case'])) ?: null : null,
            'response' => $payload,
        ];
    }

    private function endpoint(): string
    {
        return trim((string) config('call_center.crm.phone_lookup_url', self::DEFAULT_ENDPOINT));
    }

    /**
     * @return array{phone:string,startDate?:string,endDate?:string}
     */
    private function buildLookupQuery(string $normalizedPhone, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = ['phone' => $normalizedPhone];

        if ($this->isValidDate($startDate)) {
            $query['startDate'] = trim((string) $startDate);
        }

        if ($this->isValidDate($endDate)) {
            $query['endDate'] = trim((string) $endDate);
        }

        return $query;
    }

    /**
     * @param  array{phone:string,startDate?:string,endDate?:string}  $query
     * @param  array{phone:string,startDate:string,endDate:string}|null  $fallbackQuery
     * @return array{phone:string,phone_exist:bool,manager:?string,case:?string,response:array<string,mixed>}
     */
    private function performLookup(string $normalizedPhone, array $query, ?array $fallbackQuery = null): array
    {
        $lookup = $this->requestLookup($normalizedPhone, $query);

        if (
            $fallbackQuery === null
            || $fallbackQuery === $query
            || ! $this->shouldRetryWithHistoricalRange($lookup)
        ) {
            return $lookup;
        }

        $fallbackLookup = $this->requestLookup($normalizedPhone, $fallbackQuery);

        return $this->shouldRetryWithHistoricalRange($fallbackLookup) ? $lookup : $fallbackLookup;
    }

    /**
     * @param  array{phone:string,startDate?:string,endDate?:string}  $query
     * @return array{phone:string,phone_exist:bool,manager:?string,case:?string,response:array<string,mixed>}
     */
    private function requestLookup(string $normalizedPhone, array $query): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->get($this->endpoint(), $query);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Не вдалося перевірити номер телефону в CRM.'
                .(trim($exception->getMessage()) !== '' ? ' '.trim($exception->getMessage()) : '')
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'CRM-перевірка номера повернула помилку HTTP '.$response->status().'.'
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('CRM-перевірка номера повернула некоректний JSON.');
        }

        return $this->mapLookupPayload($normalizedPhone, $payload);
    }

    /**
     * @return array{phone:string,startDate:string,endDate:string}|null
     */
    private function historicalQueryForLookup(string $normalizedPhone, ?string $startDate, ?string $endDate): ?array
    {
        $targetDay = $this->lookupTargetDay($startDate, $endDate);

        if ($targetDay === null) {
            return null;
        }

        return $this->historicalQueryForDay($normalizedPhone, $targetDay);
    }

    private function lookupTargetDay(?string $startDate, ?string $endDate): ?CarbonImmutable
    {
        $timezone = (string) config('binotel.timezone', config('app.timezone', 'Europe/Kyiv'));

        foreach ([$endDate, $startDate] as $candidate) {
            if (! $this->isValidDate($candidate)) {
                continue;
            }

            $parsed = CarbonImmutable::createFromFormat('d.m.y', trim((string) $candidate), $timezone);

            if ($parsed !== false) {
                return $parsed->startOfDay();
            }
        }

        return CarbonImmutable::now($timezone)->startOfDay();
    }

    private function isValidDate(?string $value): bool
    {
        $date = trim((string) ($value ?? ''));

        return $date !== '' && preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $date) === 1;
    }
}
