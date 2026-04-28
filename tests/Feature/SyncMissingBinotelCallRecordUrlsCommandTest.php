<?php

namespace Tests\Feature;

use App\Models\BinotelApiCallCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMissingBinotelCallRecordUrlsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('binotel.log_channel', 'null');
        config()->set('binotel.api.key', 'test-key');
        config()->set('binotel.api.secret', 'test-secret');
    }

    public function test_command_fetches_direct_url_for_missing_records(): void
    {
        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'attempts_counter' => 1,
            'call_details_general_call_id' => '6536027126',
            'call_details_call_id' => '6536027126',
            'call_details_start_time' => 1713186021,
        ]);

        Http::fake([
            'https://api.binotel.com/api/4.0/stats/call-record.json' => Http::response([
                'url' => 'https://records.example.com/6536027126.mp3',
            ], 200),
        ]);

        $this->artisan('binotel:sync-call-record-urls --limit=10')
            ->expectsOutput('Processed 1 call(s), updated 1 call_record_url value(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'call_details_general_call_id' => '6536027126',
            'call_record_url' => 'https://records.example.com/6536027126.mp3',
            'call_record_url_check_attempts' => 1,
        ]);

        $record = BinotelApiCallCompleted::query()
            ->where('call_details_general_call_id', '6536027126')
            ->first();

        $this->assertNotNull($record?->call_record_url_last_checked_at);
    }

    public function test_command_skips_calls_that_already_have_direct_url(): void
    {
        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'attempts_counter' => 1,
            'call_details_general_call_id' => 'already-synced',
            'call_details_call_id' => 'already-synced',
            'call_details_start_time' => 1713186021,
            'call_record_url' => 'https://records.example.com/already-synced.mp3',
        ]);

        Http::fake();

        $this->artisan('binotel:sync-call-record-urls --limit=10')
            ->expectsOutput('Processed 0 call(s), updated 0 call_record_url value(s).')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_command_respects_five_minute_retry_window(): void
    {
        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'attempts_counter' => 1,
            'call_details_general_call_id' => 'retry-later',
            'call_details_call_id' => 'retry-later',
            'call_details_start_time' => 1713186021,
            'call_record_url_last_checked_at' => now(),
        ]);

        Http::fake();

        $this->artisan('binotel:sync-call-record-urls --limit=10')
            ->expectsOutput('Processed 0 call(s), updated 0 call_record_url value(s).')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }
}
