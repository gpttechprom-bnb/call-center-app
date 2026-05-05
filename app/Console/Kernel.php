<?php

namespace App\Console;

use App\Support\AltCallCenterAutomationWindow;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule
            ->command('binotel:sync-call-record-urls --limit=100 --retry-minutes=15')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule
            ->command('call-center:cache-audio --limit=200')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule
            ->command('call-center:alt-auto-worker --max-seconds=55')
            ->everyMinute()
            ->when(fn () => app(AltCallCenterAutomationWindow::class)->isOpen())
            ->withoutOverlapping();

        $schedule
            ->command('call-center:alt-stop-worker')
            ->everyMinute()
            ->when(fn () => ! app(AltCallCenterAutomationWindow::class)->isOpen())
            ->withoutOverlapping();

        $schedule
            ->command('call-center:warm-alt-calendar-crm-cache')
            ->dailyAt('17:15')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
