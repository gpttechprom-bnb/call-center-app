<?php

namespace App\Console\Commands;

use App\Services\AltCallCenterAutomationStopper;
use App\Support\AltCallCenterAutomationWindow;
use Illuminate\Console\Command;

class StopAltCallCenterAutomationWorker extends Command
{
    protected $signature = 'call-center:alt-stop-worker
        {--pause : Mark the queue as paused after stopping the current process}
        {--message= : Message to write into automation state}';

    protected $description = 'Stop the current alt call-center automation worker and clean its runtime state';

    public function handle(
        AltCallCenterAutomationStopper $stopper,
        AltCallCenterAutomationWindow $automationWindow,
    ): int {
        $message = trim((string) $this->option('message'));

        $state = (bool) $this->option('pause')
            ? $stopper->pauseAndStop()
            : $stopper->stopForClosedWindow($message !== '' ? $message : $automationWindow->closedMessage());

        $this->info((string) ($state['last_message'] ?? 'Фоновий процес зупинено.'));

        return self::SUCCESS;
    }
}
