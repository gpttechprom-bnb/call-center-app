<?php

namespace App\Console\Commands;

use App\Services\BinotelApiCallCompletedStore;
use Illuminate\Console\Command;

class RecalculateBinotelInteractionNumbers extends Command
{
    protected $signature = 'call-center:recalculate-interactions';

    protected $description = 'Recalculate Binotel interaction_number values and set non-conversations to 0';

    public function handle(BinotelApiCallCompletedStore $store): int
    {
        $updated = $store->recalculateAllInteractionNumbers();

        $this->info(sprintf(
            'Interaction numbers recalculated. Updated %d call(s). Non-conversation calls now use interaction_number = 0.',
            $updated,
        ));

        return self::SUCCESS;
    }
}
