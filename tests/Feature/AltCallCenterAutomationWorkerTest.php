<?php

namespace Tests\Feature;

use App\Services\AltCallCenterAutoProcessor;
use App\Support\AltCallCenterAutomationStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class AltCallCenterAutomationWorkerTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_worker_keeps_processing_next_calls_until_queue_waits_or_pauses(): void
    {
        Storage::fake('local');
        config()->set('call_center.automation.night_start_hour', 0);
        config()->set('call_center.automation.night_end_hour', 0);

        $store = app(AltCallCenterAutomationStore::class);
        $store->play();

        $iterations = 0;

        $this->mock(AltCallCenterAutoProcessor::class, function (MockInterface $mock) use (&$iterations, $store): void {
            $mock
                ->shouldReceive('processNext')
                ->times(3)
                ->andReturnUsing(function () use (&$iterations, $store): bool {
                    $iterations++;

                    if ($iterations === 3) {
                        $store->pause();

                        return false;
                    }

                    return true;
                });
        });

        $exitCode = Artisan::call('call-center:alt-auto-worker');

        $this->assertSame(0, $exitCode);
        $this->assertSame(3, $iterations);
        $this->assertTrue($store->isPaused());
    }

    public function test_worker_auto_resumes_temporary_manual_pause_when_window_opens_again(): void
    {
        Storage::fake('local');
        config()->set('call_center.automation.timezone', 'Europe/Kyiv');

        $store = app(AltCallCenterAutomationStore::class);
        $store->saveWindowSettings('19:23', '06:00');
        $store->pause(true);

        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 27, 19, 30, 0, 'Europe/Kyiv'));

        $this->mock(AltCallCenterAutoProcessor::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('processNext')
                ->once()
                ->andReturn(false);
        });

        $exitCode = Artisan::call('call-center:alt-auto-worker --once');

        $this->assertSame(0, $exitCode);
        $this->assertFalse($store->isPaused());
        $this->assertFalse((bool) ($store->state()['resume_on_next_window_open'] ?? true));
    }

    public function test_worker_keeps_manual_pause_for_current_window_and_resumes_on_next_scheduled_window(): void
    {
        Storage::fake('local');
        config()->set('call_center.automation.timezone', 'Europe/Kyiv');

        $store = app(AltCallCenterAutomationStore::class);
        $store->saveWindowSettings('19:23', '06:00', [
            ['day' => 1, 'start_time' => '19:23', 'end_time' => '06:00', 'is_day_off' => false],
            ['day' => 2, 'start_time' => '19:23', 'end_time' => '06:00', 'is_day_off' => false],
        ]);
        $store->pause(true, true);

        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 27, 20, 0, 0, 'Europe/Kyiv'));
        $this->assertFalse($store->shouldResumeWhenWindowOpens());
        $this->assertTrue($store->isPaused());
        $this->assertTrue((bool) ($store->state()['wait_for_window_to_close_before_resume'] ?? false));

        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 28, 7, 0, 0, 'Europe/Kyiv'));
        Artisan::call('call-center:alt-stop-worker');

        $this->assertFalse((bool) ($store->state()['wait_for_window_to_close_before_resume'] ?? true));
        $this->assertTrue($store->isPaused());

        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 28, 19, 30, 0, 'Europe/Kyiv'));

        $this->mock(AltCallCenterAutoProcessor::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('processNext')
                ->once()
                ->andReturn(false);
        });

        $nextWindowExitCode = Artisan::call('call-center:alt-auto-worker --once');

        $this->assertSame(0, $nextWindowExitCode);
        $this->assertFalse($store->isPaused());
    }
}
