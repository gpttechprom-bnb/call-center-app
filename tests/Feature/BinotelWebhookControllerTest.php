<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BinotelWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('binotel.log_channel', 'null');
        config()->set('binotel.api.key', '');
        config()->set('binotel.api.secret', '');
    }

    public function test_rejects_request_from_unknown_ip(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/binotel-webhook', [
                'requestType' => 'apiCallCompleted',
            ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'error' => 'Unauthorized',
            ]);
    }

    #[DataProvider('supportedRequestTypesProvider')]
    public function test_accepts_request_from_allowed_ip(string $requestType): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '45.91.130.36'])
            ->postJson('/api/binotel-webhook', [
                'requestType' => $requestType,
                'generalCallID' => 'test-call-id',
                'callDetails' => [
                    'callID' => 'nested-call-id',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function supportedRequestTypesProvider(): array
    {
        return [
            'answeredTheCall' => ['answeredTheCall'],
            'apiCallCompleted' => ['apiCallCompleted'],
            'apiCallSettings' => ['apiCallSettings'],
        ];
    }

    public function test_persists_api_call_completed_payload_and_history_items(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '45.91.130.36'])
            ->postJson('/api/binotel-webhook', [
                'requestType' => 'apiCallCompleted',
                'attemptsCounter' => '1',
                'language' => 'ru',
                'myBinotelDomain' => 'my.binotel.ua',
                'callDetails' => [
                    'companyID' => '67875',
                    'generalCallID' => '4937638623',
                    'callID' => '4937638623',
                    'startTime' => '1712320979',
                    'callType' => '0',
                    'internalNumber' => '901',
                    'internalAdditionalData' => null,
                    'externalNumber' => '0671654363',
                    'waitsec' => '4',
                    'billsec' => '38',
                    'disposition' => 'ANSWER',
                    'recordingStatus' => 'uploading',
                    'isNewCall' => '0',
                    'whoHungUp' => null,
                    'customerData' => null,
                    'employeeData' => [
                        'name' => 'тест',
                        'email' => '779@ya.ua',
                    ],
                    'pbxNumberData' => [
                        'number' => '0674984496',
                        'name' => 'CallTracking tehpromproect.com.ua',
                    ],
                    'historyData' => [
                        [
                            'waitsec' => '4',
                            'billsec' => '38',
                            'disposition' => 'ANSWER',
                            'internalNumber' => '901',
                            'internalAdditionalData' => null,
                            'employeeData' => [
                                'name' => 'тест',
                                'email' => '779@ya.ua',
                            ],
                        ],
                    ],
                    'customerDataFromOutside' => [
                        'id' => '102150716',
                        'externalNumber' => '0671654363',
                        'name' => 'Милаенко',
                        'linkToCrmUrl' => null,
                    ],
                    'callTrackingData' => [
                        'id' => '16833664',
                        'type' => 'dynamic',
                        'gaClientId' => '463855936.1712320079',
                        'firstVisitAt' => '1712320077',
                        'fullUrl' => 'https://tehpromproect.com.ua/ua/catalog/dvuhsektsionnye-lestnitsy',
                        'utm_source' => 'google',
                        'utm_medium' => 'cpc',
                        'utm_campaign' => 'Lestnisa-Ukr_Performance_Max',
                        'utm_content' => '(not set)',
                        'utm_term' => '(not set)',
                        'ipAddress' => '46.200.201.34',
                        'geoipCountry' => 'Ukraine',
                        'geoipRegion' => 'Zhytomyr',
                        'geoipCity' => 'Zhytomyr',
                        'geoipOrg' => null,
                        'domain' => 'tehpromproect.com.ua',
                        'gaTrackingId' => 'G-JJXJ8BECR4',
                        'timeSpentOnSiteBeforeMakeCall' => '553',
                    ],
                    'linkToCallRecordOverlayInMyBusiness' => 'https://my.binotel.ua/?module=history&subject=0671654363&sacte=ovl-link-pb-4937638623',
                    'linkToCallRecordInMyBusiness' => 'https://my.binotel.ua/?module=cdrs&action=generateFile&fileName=4937638623.mp3&callDate=2024-05-04_15:42&customerNumber=0671654363&callType=incoming',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'request_type' => 'apiCallCompleted',
            'attempts_counter' => 1,
            'language' => 'ru',
            'my_binotel_domain' => 'my.binotel.ua',
            'call_details_company_id' => '67875',
            'call_details_general_call_id' => '4937638623',
            'call_details_call_id' => '4937638623',
            'call_details_internal_number' => '901',
            'call_details_external_number' => '0671654363',
            'call_details_recording_status' => 'uploading',
            'call_details_employee_email' => '779@ya.ua',
            'call_details_customer_from_outside_id' => '102150716',
            'call_details_call_tracking_id' => '16833664',
        ]);

        $completedId = \DB::table('binotel_api_call_completeds')
            ->where('call_details_general_call_id', '4937638623')
            ->value('id');

        $this->assertNotNull($completedId);

        $this->assertDatabaseHas('binotel_api_call_completed_histories', [
            'binotel_api_call_completed_id' => $completedId,
            'sort_order' => 0,
            'waitsec' => 4,
            'billsec' => 38,
            'disposition' => 'ANSWER',
            'internal_number' => '901',
            'employee_email' => '779@ya.ua',
        ]);
    }

    public function test_assigns_interaction_numbers_for_repeated_manager_client_calls(): void
    {
        foreach ([
            ['generalCallId' => 'newer-call', 'startTime' => '1712321000'],
            ['generalCallId' => 'older-call', 'startTime' => '1712320000'],
        ] as $call) {
            $response = $this
                ->withServerVariables(['REMOTE_ADDR' => '45.91.130.36'])
                ->postJson('/api/binotel-webhook', [
                    'requestType' => 'apiCallCompleted',
                    'attemptsCounter' => '1',
                    'callDetails' => [
                        'generalCallID' => $call['generalCallId'],
                        'callID' => $call['generalCallId'],
                        'startTime' => $call['startTime'],
                        'externalNumber' => '0671654363',
                        'internalNumber' => '901',
                        'employeeData' => [
                            'name' => 'тест',
                            'email' => '779@ya.ua',
                        ],
                    ],
                ]);

            $response->assertOk();
        }

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'call_details_general_call_id' => 'older-call',
            'interaction_number' => 1,
        ]);

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'call_details_general_call_id' => 'newer-call',
            'interaction_number' => 2,
        ]);
    }

    public function test_fetches_and_persists_direct_call_record_url_by_general_call_id(): void
    {
        config()->set('binotel.api.key', 'test-key');
        config()->set('binotel.api.secret', 'test-secret');

        Http::fake([
            'https://api.binotel.com/api/4.0/stats/call-record.json' => Http::response([
                'url' => 'https://records.example.com/4937638623.mp3',
            ], 200),
        ]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '45.91.130.36'])
            ->postJson('/api/binotel-webhook', [
                'requestType' => 'apiCallCompleted',
                'attemptsCounter' => '1',
                'callDetails' => [
                    'generalCallID' => '4937638623',
                    'callID' => '4937638623',
                    'externalNumber' => '0671654363',
                ],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'call_details_general_call_id' => '4937638623',
            'call_record_url' => 'https://records.example.com/4937638623.mp3',
        ]);
    }
}
