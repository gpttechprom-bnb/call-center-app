<?php

namespace Database\Seeders;

use App\Models\CallCenterCall;
use App\Models\CallCenterCallScoreItem;
use App\Support\CallCenterDemoData;
use Illuminate\Database\Seeder;

class CallCenterSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        CallCenterCallScoreItem::query()->delete();
        CallCenterCall::query()->delete();

        foreach (CallCenterDemoData::storageCalls() as $callData) {
            $scoreItems = $callData['score_items'];
            unset($callData['score_items']);

            $call = CallCenterCall::query()->create($callData);

            $call->scoreItems()->createMany($scoreItems);
        }
    }
}
