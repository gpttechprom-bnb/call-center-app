<?php

namespace App\Console\Commands;

use App\Services\AltCallCenterAutomationCalendarStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class WarmAltCallCenterCalendarCrmCache extends Command
{
    protected $signature = 'call-center:warm-alt-calendar-crm-cache
        {--date= : Day in d.m.Y format}
        {--month= : Month in Y-m format}
        {--from= : Start day in d.m.Y format}
        {--to= : End day in d.m.Y format}';

    protected $description = 'Warm CRM cache for the alt call-center automation calendar';

    public function handle(AltCallCenterAutomationCalendarStatsService $calendarStatsService): int
    {
        $timezone = (string) config('binotel.timezone', 'Europe/Kyiv');
        $rawDate = trim((string) $this->option('date'));
        $rawMonth = trim((string) $this->option('month'));
        $rawFrom = trim((string) $this->option('from'));
        $rawTo = trim((string) $this->option('to'));

        if (($rawFrom !== '' && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $rawFrom) !== 1)
            || ($rawTo !== '' && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $rawTo) !== 1)) {
            $this->error('The --from and --to options must be in d.m.Y format.');

            return self::INVALID;
        }

        if (($rawFrom !== '' || $rawTo !== '') && ($rawFrom === '' || $rawTo === '')) {
            $this->error('Use --from and --to together.');

            return self::INVALID;
        }

        if ($rawDate !== '' && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $rawDate) !== 1) {
            $this->error('The --date option must be in d.m.Y format.');

            return self::INVALID;
        }

        if ($rawMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $rawMonth) !== 1) {
            $this->error('The --month option must be in Y-m format.');

            return self::INVALID;
        }

        if ($rawFrom !== '' && $rawTo !== '') {
            $from = CarbonImmutable::createFromFormat('d.m.Y', $rawFrom, $timezone);
            $to = CarbonImmutable::createFromFormat('d.m.Y', $rawTo, $timezone);

            if ($from === false || $to === false) {
                $this->error('The selected range could not be parsed.');

                return self::FAILURE;
            }

            if ($to->lessThan($from)) {
                [$from, $to] = [$to, $from];
            }

            $cursor = $from;

            while ($cursor->lessThanOrEqualTo($to)) {
                $stats = $calendarStatsService->buildMonthStats(
                    (int) $cursor->format('Y'),
                    (int) $cursor->format('n'),
                    $cursor->format('d.m.Y'),
                );
                $dayStats = collect($stats['days'] ?? [])->firstWhere('date', $cursor->format('d.m.Y'));

                $this->info(sprintf(
                    'CRM cache warmed for %s: total=%s, scenario=%s, crm_skipped=%s, crm_status=%s',
                    $cursor->format('d.m.Y'),
                    $dayStats['totalCalls'] ?? 0,
                    $dayStats['required'] ?? 0,
                    $dayStats['crmSkipped'] ?? 0,
                    $dayStats['crmStatus'] ?? 'unknown',
                ));

                $cursor = $cursor->addDay();
            }

            return self::SUCCESS;
        }

        if ($rawDate !== '') {
            $date = CarbonImmutable::createFromFormat('d.m.Y', $rawDate, $timezone);
        } else {
            $date = CarbonImmutable::now($timezone);
        }

        if ($date === false) {
            $this->error('The selected date could not be parsed.');

            return self::FAILURE;
        }

        if ($rawMonth !== '') {
            [$year, $month] = array_map('intval', explode('-', $rawMonth, 2));
        } else {
            $year = (int) $date->format('Y');
            $month = (int) $date->format('n');
        }

        $stats = $calendarStatsService->buildMonthStats($year, $month, $date->format('d.m.Y'));
        $dayStats = collect($stats['days'] ?? [])->firstWhere('date', $date->format('d.m.Y'));

        $this->info(sprintf(
            'CRM cache warmed for %s: total=%s, scenario=%s, crm_skipped=%s, crm_status=%s',
            $date->format('d.m.Y'),
            $dayStats['totalCalls'] ?? 0,
            $dayStats['required'] ?? 0,
            $dayStats['crmSkipped'] ?? 0,
            $dayStats['crmStatus'] ?? 'unknown',
        ));

        return self::SUCCESS;
    }
}
