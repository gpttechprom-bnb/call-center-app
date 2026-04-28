<?php

namespace Tests\Unit;

use App\Services\BinotelApi;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BinotelApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('binotel.api.key', 'test-key');
        config()->set('binotel.api.secret', 'test-secret');
        config()->set('binotel.api.host', 'https://api.binotel.com/api/');
        config()->set('binotel.api.version', '4.0');
        config()->set('binotel.api.format', 'json');
        config()->set('binotel.api.timeout', 20);
        config()->set('binotel.api.disable_ssl_checks', false);
        config()->set('binotel.api.debug', false);
        config()->set('binotel.log_channel', 'null');
    }

    public function test_send_request_posts_credentials_and_general_call_id(): void
    {
        Http::fake([
            'https://api.binotel.com/api/4.0/stats/call-record.json' => Http::response([
                'url' => 'https://records.example.com/call.mp3',
            ], 200),
        ]);

        $client = new BinotelApi();

        $response = $client->sendRequest('stats/call-record', [
            'generalCallID' => 'general-call-id-123',
        ]);

        $this->assertSame([
            'url' => 'https://records.example.com/call.mp3',
        ], $response);

        Http::assertSent(function ($request): bool {
            $payload = json_decode($request->body(), true);

            return $request->url() === 'https://api.binotel.com/api/4.0/stats/call-record.json'
                && ($payload['generalCallID'] ?? null) === 'general-call-id-123'
                && ($payload['key'] ?? null) === 'test-key'
                && ($payload['secret'] ?? null) === 'test-secret';
        });
    }

    public function test_get_call_record_url_extracts_url_from_api_response(): void
    {
        Http::fake([
            'https://api.binotel.com/api/4.0/stats/call-record.json' => Http::response([
                'status' => 'success',
                'result' => [
                    'record' => 'https://records.example.com/call.mp3',
                ],
            ], 200),
        ]);

        $client = new BinotelApi();

        $this->assertSame(
            'https://records.example.com/call.mp3',
            $client->getCallRecordUrl('general-call-id-456')
        );
    }

    public function test_get_call_record_url_prefers_recording_link_from_call_details(): void
    {
        Http::fake([
            'https://api.binotel.com/api/4.0/stats/call-record.json' => Http::response([
                'status' => 'success',
                'callDetails' => [
                    'callTrackingData' => [
                        'fullUrl' => 'https://example.com/source-page',
                    ],
                    'linkToCallRecordOverlayInMyBusiness' => 'https://my.binotel.ua/f/pbx/#/calls/by-number?subject=0971111111&sacte=ovl-link-pb-6536027126',
                    'linkToCallRecordInMyBusiness' => 'https://my.binotel.ua/?module=cdrs&action=generateFile&fileName=6536027126.mp3&callDate=2026-22-04_14:48&customerNumber=0971111111&callType=incoming',
                ],
            ], 200),
        ]);

        $client = new BinotelApi();

        $this->assertSame(
            'https://my.binotel.ua/?module=cdrs&action=generateFile&fileName=6536027126.mp3&callDate=2026-22-04_14:48&customerNumber=0971111111&callType=incoming',
            $client->getCallRecordUrl('6536027126')
        );
    }

    public function test_get_call_record_url_ignores_non_audio_urls(): void
    {
        Http::fake([
            'https://api.binotel.com/api/4.0/stats/call-record.json' => Http::response([
                'status' => 'success',
                'callDetails' => [
                    'callTrackingData' => [
                        'fullUrl' => 'https://example.com/source-page',
                    ],
                    'linkToCallRecordOverlayInMyBusiness' => 'https://my.binotel.ua/f/pbx/#/calls/by-number?subject=0971111111&sacte=ovl-link-pb-6536027126',
                ],
            ], 200),
        ]);

        $client = new BinotelApi();

        $this->assertNull($client->getCallRecordUrl('6536027126'));
    }
}
