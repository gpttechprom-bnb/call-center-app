<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class AltCallCenterAutomationWindow
{
    public function __construct(
        private readonly AltCallCenterAutomationStore $automationStore,
    ) {
    }

    public function isOpen(?CarbonImmutable $now = null): bool
    {
        return (bool) $this->activeScheduleState($now)['is_open'];
    }

    /**
     * @return array{start_time: string, end_time: string, timezone: string, is_open: bool, is_day_off: bool, active_day: int, active_day_label: string, active_from_previous_day: bool, ends_next_day: bool, current_day_schedule: array<string, mixed>, weekly_schedule: array<int, array<string, mixed>>, closed_message: string}
     */
    public function state(?CarbonImmutable $now = null): array
    {
        $timezone = $this->timezone();
        $now = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $activeState = $this->activeScheduleState($now);
        $schedule = $activeState['schedule'];

        return [
            'start_time' => $schedule['start_time'],
            'end_time' => $schedule['end_time'],
            'timezone' => $timezone,
            'is_open' => (bool) $activeState['is_open'],
            'is_day_off' => (bool) ($schedule['is_day_off'] ?? false),
            'active_day' => (int) ($schedule['day'] ?? (int) $now->format('N')),
            'active_day_label' => (string) ($schedule['label'] ?? ''),
            'active_from_previous_day' => (bool) $activeState['active_from_previous_day'],
            'ends_next_day' => $this->endsNextDay($schedule),
            'current_day_schedule' => $schedule,
            'weekly_schedule' => $this->weeklySchedule(),
            'closed_message' => $this->closedMessage($now),
        ];
    }

    public function closedMessage(?CarbonImmutable $now = null): string
    {
        $activeState = $this->activeScheduleState($now);
        $schedule = $activeState['schedule'];

        if ((bool) ($schedule['is_day_off'] ?? false)) {
            return sprintf(
                'Сьогодні %s у графіку позначено як вихідний. Автоматичний запуск вимкнено (%s).',
                $schedule['label'] ?? 'цей день',
                $this->timezone(),
            );
        }

        return sprintf(
            'Фонова транскрибація вимкнена у робочий час. Автоматичний запуск дозволений тільки з %s до %s%s (%s).',
            $schedule['start_time'],
            $schedule['end_time'],
            $this->endsNextDay($schedule) ? ' наступного дня' : '',
            $this->timezone(),
        );
    }

    public function timezone(): string
    {
        $timezone = trim((string) config('call_center.automation.timezone', 'Europe/Kyiv'));

        return $timezone !== '' ? $timezone : 'Europe/Kyiv';
    }

    private function startTime(): string
    {
        return $this->automationStore->windowSettings()['start_time'];
    }

    private function endTime(): string
    {
        return $this->automationStore->windowSettings()['end_time'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function weeklySchedule(): array
    {
        return $this->automationStore->windowSettings()['weekly_schedule'];
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleFor(?CarbonImmutable $now = null): array
    {
        $timezone = $this->timezone();
        $now = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $day = (int) $now->format('N');

        return $this->scheduleForDay($day);
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleForDay(int $day): array
    {
        $day = $this->normalizeIsoDay($day);

        foreach ($this->weeklySchedule() as $schedule) {
            if ((int) ($schedule['day'] ?? 0) === $day) {
                return $schedule;
            }
        }

        return [
            'day' => $day,
            'label' => '',
            'start_time' => $this->startTime(),
            'end_time' => $this->endTime(),
            'is_day_off' => false,
        ];
    }

    /**
     * @return array{schedule: array<string, mixed>, is_open: bool, active_from_previous_day: bool}
     */
    private function activeScheduleState(?CarbonImmutable $now = null): array
    {
        $timezone = $this->timezone();
        $now = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $currentMinute = ((int) $now->format('G') * 60) + (int) $now->format('i');
        $today = (int) $now->format('N');
        $previousSchedule = $this->scheduleForDay($this->previousIsoDay($today));

        if (
            ! (bool) ($previousSchedule['is_day_off'] ?? false)
            && $this->endsNextDay($previousSchedule)
            && $currentMinute < $this->timeToMinutes((string) $previousSchedule['end_time'])
        ) {
            return [
                'schedule' => $previousSchedule,
                'is_open' => true,
                'active_from_previous_day' => true,
            ];
        }

        $schedule = $this->scheduleForDay($today);

        if ((bool) ($schedule['is_day_off'] ?? false)) {
            return [
                'schedule' => $schedule,
                'is_open' => false,
                'active_from_previous_day' => false,
            ];
        }

        $startMinute = $this->timeToMinutes((string) $schedule['start_time']);
        $endMinute = $this->timeToMinutes((string) $schedule['end_time']);
        $isOpen = match (true) {
            $startMinute === $endMinute => true,
            $startMinute < $endMinute => $currentMinute >= $startMinute && $currentMinute < $endMinute,
            default => $currentMinute >= $startMinute,
        };

        return [
            'schedule' => $schedule,
            'is_open' => $isOpen,
            'active_from_previous_day' => false,
        ];
    }

    /**
     * @param array<string, mixed> $schedule
     */
    private function endsNextDay(array $schedule): bool
    {
        return $this->timeToMinutes((string) ($schedule['start_time'] ?? '00:00'))
            > $this->timeToMinutes((string) ($schedule['end_time'] ?? '00:00'));
    }

    private function previousIsoDay(int $day): int
    {
        return $day <= 1 ? 7 : $day - 1;
    }

    private function normalizeIsoDay(int $day): int
    {
        return max(1, min(7, $day));
    }

    private function startMinute(): int
    {
        return $this->timeToMinutes($this->startTime());
    }

    private function endMinute(): int
    {
        return $this->timeToMinutes($this->endTime());
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time, 2)), 2, 0);

        return ($hour * 60) + $minute;
    }
}
