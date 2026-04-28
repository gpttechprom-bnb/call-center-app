<?php

namespace Tests\Feature;

use App\Models\BinotelApiCallCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CallCenterCallAudioUrlControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('binotel.log_channel', 'null');
        config()->set('binotel.api.key', 'test-key');
        config()->set('binotel.api.secret', 'test-secret');
    }

    public function test_fetches_audio_url_using_general_call_id_from_database(): void
    {
        $call = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'attempts_counter' => 1,
            'call_details_general_call_id' => '6536027126',
            'call_details_call_id' => 'different-leg-id',
            'call_details_start_time' => 1713186021,
            'call_details_recording_status' => 'uploading',
            'call_details_link_to_call_record_in_my_business' => 'https://my.binotel.ua/?module=cdrs&action=generateFile&fileName=different-leg-id.mp3',
        ]);

        Http::fake([
            'https://api.binotel.com/api/4.0/stats/call-record.json' => Http::response([
                'url' => 'https://cdn.example.com/65/6536027126.mp3?signature=fresh',
            ], 200),
        ]);

        $response = $this->getJson("/api/call-center/calls/{$call->id}/audio-url");

        $response
            ->assertOk()
            ->assertJsonPath('id', $call->id)
            ->assertJsonPath('generalCallId', '6536027126')
            ->assertJsonPath('audioUrl', 'https://cdn.example.com/65/6536027126.mp3?signature=fresh')
            ->assertJsonPath('audioStatus', 'Запис доступний')
            ->assertJsonPath('binotelStatus', 'Успіх');

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $call->id,
            'call_details_general_call_id' => '6536027126',
            'call_record_url' => 'https://cdn.example.com/65/6536027126.mp3?signature=fresh',
            'call_record_url_check_attempts' => 1,
        ]);

        Http::assertSent(function ($request): bool {
            $payload = json_decode($request->body(), true);

            return ($payload['generalCallID'] ?? null) === '6536027126'
                && ($payload['key'] ?? null) === 'test-key'
                && ($payload['secret'] ?? null) === 'test-secret';
        });
    }
}
