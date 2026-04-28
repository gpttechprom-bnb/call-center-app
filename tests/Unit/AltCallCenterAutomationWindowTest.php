<?php

namespace Tests\Unit;

use App\Support\AltCallCenterAutomationStore;
use App\Support\AltCallCenterAutomationWindow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AltCallCenterAutomationWindowTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_window_supports_minute_precision_for_overnight_ranges(): void
    {
        Storage::fake('local');

        $store = app(AltCallCenterAutomationStore::class);
        $store->saveWindowSettings('22:30', '05:15');

        $window = app(AltCallCenterAutomationWindow::class);

        $this->assertFalse($window->isOpen(CarbonImmutable::create(2026, 4, 23, 22, 29, 0, 'Europe/Kyiv')));
        $this->assertTrue($window->isOpen(CarbonImmutable::create(2026, 4, 23, 22, 30, 0, 'Europe/Kyiv')));
        $this->assertTrue($window->isOpen(CarbonImmutable::create(2026, 4, 24, 5, 14, 0, 'Europe/Kyiv')));
        $this->assertFalse($window->isOpen(CarbonImmutable::create(2026, 4, 24, 5, 15, 0, 'Europe/Kyiv')));
    }

    public function test_equal_start_and_end_times_keep_window_open_all_day(): void
    {
        Storage::fake('local');

        $store = app(AltCallCenterAutomationStore::class);
        $store->saveWindowSettings('00:00', '00:00');

        $window = app(AltCallCenterAutomationWindow::class);

        $this->assertTrue($window->isOpen(CarbonImmutable::create(2026, 4, 23, 0, 0, 0, 'Europe/Kyiv')));
        $this->assertTrue($window->isOpen(CarbonImmutable::create(2026, 4, 23, 12, 0, 0, 'Europe/Kyiv')));
        $this->assertTrue($window->isOpen(CarbonImmutable::create(2026, 4, 23, 23, 59, 0, 'Europe/Kyiv')));
    }

    public function test_weekly_schedule_can_disable_a_day(): void
    {
        Storage::fake('local');

        $store = app(AltCallCenterAutomationStore::class);
        $store->saveWindowSettings('20:00', '06:00', [
            ['day' => 5, 'start_time' => '00:00', 'end_time' => '00:00', 'is_day_off' => true],
        ]);

        $window = app(AltCallCenterAutomationWindow::class);

        $this->assertFalse($window->isOpen(CarbonImmutable::create(2026, 4, 24, 12, 0, 0, 'Europe/Kyiv')));
        $this->assertFalse($window->isOpen(CarbonImmutable::create(2026, 4, 24, 23, 0, 0, 'Europe/Kyiv')));
    }

    public function test_weekly_schedule_uses_the_current_day_time_window(): void
    {
        Storage::fake('local');

        $store = app(AltCallCenterAutomationStore::class);
        $store->saveWindowSettings('20:00', '06:00', [
            ['day' => 4, 'start_time' => '10:00', 'end_time' => '11:00', 'is_day_off' => false],
            ['day' => 5, 'start_time' => '22:00', 'end_time' => '23:00', 'is_day_off' => false],
        ]);

        $window = app(AltCallCenterAutomationWindow::class);

        $this->assertTrue($window->isOpen(CarbonImmutable::create(2026, 4, 23, 10, 30, 0, 'Europe/Kyiv')));
        $this->assertFalse($window->isOpen(CarbonImmutable::create(2026, 4, 23, 11, 0, 0, 'Europe/Kyiv')));
        $this->assertFalse($window->isOpen(CarbonImmutable::create(2026, 4, 24, 10, 30, 0, 'Europe/Kyiv')));
        $this->assertTrue($window->isOpen(CarbonImmutable::create(2026, 4, 24, 22, 30, 0, 'Europe/Kyiv')));
    }

    public function test_overnight_weekly_schedule_continues_into_the_next_morning(): void
    {
        Storage::fake('local');

        $store = app(AltCallCenterAutomationStore::class);
        $store->saveWindowSettings('19:23', '06:00', [
            ['day' => 1, 'start_time' => '19:23', 'end_time' => '06:00', 'is_day_off' => false],
            ['day' => 2, 'start_time' => '22:00', 'end_time' => '23:00', 'is_day_off' => false],
        ]);

        $window = app(AltCallCenterAutomationWindow::class);

        $this->assertTrue($window->isOpen(CarbonImmutable::create(2026, 4, 20, 23, 30, 0, 'Europe/Kyiv')));
        $this->assertTrue($window->isOpen(CarbonImmutable::create(2026, 4, 21, 5, 59, 0, 'Europe/Kyiv')));
        $this->assertFalse($window->isOpen(CarbonImmutable::create(2026, 4, 21, 6, 0, 0, 'Europe/Kyiv')));
        $this->assertFalse($window->isOpen(CarbonImmutable::create(2026, 4, 21, 21, 0, 0, 'Europe/Kyiv')));
        $this->assertTrue($window->isOpen(CarbonImmutable::create(2026, 4, 21, 22, 30, 0, 'Europe/Kyiv')));
    }
}
