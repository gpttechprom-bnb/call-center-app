<?php

namespace Tests\Feature;

use App\Models\BinotelApiCallCompleted;
use App\Models\BinotelCallFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallCenterBootstrapControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('binotel.log_channel', 'null');
        config()->set('binotel.api.key', '');
        config()->set('binotel.api.secret', '');
    }

    public function test_bootstrap_returns_calls_from_binotel_table_instead_of_demo_data(): void
    {
        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'attempts_counter' => 1,
            'language' => 'ru',
            'my_binotel_domain' => 'my.binotel.ua',
            'call_details_company_id' => '67875',
            'call_details_general_call_id' => '4957414849',
            'call_details_call_id' => '4957414849',
            'call_details_start_time' => 1713186021,
            'call_details_call_type' => '0',
            'call_details_internal_number' => '904',
            'call_details_internal_additional_data' => '802 >',
            'call_details_external_number' => '0962724007',
            'call_details_waitsec' => 16,
            'call_details_billsec' => 239,
            'call_details_disposition' => 'ANSWER',
            'call_details_recording_status' => 'uploading',
            'call_details_is_new_call' => true,
            'call_details_employee_name' => 'Даниил Романов Sip',
            'call_details_employee_email' => '904@ya.ua',
            'call_details_pbx_number' => '0676188422',
            'call_details_pbx_name' => 'CallTracking tehpromproect.com.ua',
            'call_details_customer_from_outside_name' => 'Милаенко',
            'call_details_call_tracking_domain' => 'tehpromproect.com.ua',
            'call_details_call_tracking_utm_source' => 'google',
            'call_details_call_tracking_utm_campaign' => 'Lesa_Vyshki_Performance_Max',
            'call_details_link_to_call_record_in_my_business' => 'https://my.binotel.ua/example-fallback',
            'call_record_url' => 'https://records.example.com/4957414849.mp3',
            'interaction_number' => 1,
        ]);

        $response = $this->getJson('/api/call-center/bootstrap');

        $response
            ->assertOk()
            ->assertJsonPath('calls.0.caller', '0962724007')
            ->assertJsonPath('calls.0.employee', 'Даниил Романов Sip')
            ->assertJsonPath('calls.0.audioUrl', 'https://records.example.com/4957414849.mp3')
            ->assertJsonPath('calls.0.binotelStatus', 'Успіх')
            ->assertJsonPath('calls.0.audioFallbackUrl', 'https://my.binotel.ua/example-fallback')
            ->assertJsonPath('calls.0.generalCallId', '4957414849')
            ->assertJsonPath('calls.0.interactionNumber', 1)
            ->assertJsonPath('calls.0.score', null);
    }

    public function test_bootstrap_returns_saved_evaluation_score_and_checklist_items(): void
    {
        $call = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'score-call',
            'call_details_call_id' => 'score-call',
            'call_details_start_time' => 1713186021,
            'call_details_call_type' => '0',
            'call_details_internal_number' => '904',
            'call_details_external_number' => '0962724007',
            'call_record_url' => 'https://records.example.com/score-call.mp3',
        ]);

        BinotelCallFeedback::query()->create([
            'binotel_api_call_completed_id' => $call->id,
            'general_call_id' => 'score-call',
            'call_id' => 'score-call',
            'transcription_status' => 'completed',
            'transcription_dialogue_text' => 'Цей дзвінок між клієнтом та менеджером: перший.',
            'evaluation_status' => 'completed',
            'evaluation_score' => 8,
            'evaluation_total_points' => 10,
            'evaluation_score_percent' => 80,
            'evaluation_summary' => 'Менеджер виконав частину чек-листа.',
            'evaluation_payload' => [
                'items' => [
                    [
                        'label' => 'Привітання',
                        'score' => 10,
                        'max_points' => 10,
                        'percentage' => 100,
                        'answer' => 'Так',
                        'comment' => 'Менеджер привітався.',
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/call-center/bootstrap');

        $response
            ->assertOk()
            ->assertJsonPath('calls.0.score', 80)
            ->assertJsonPath('calls.0.summary', 'Менеджер виконав частину чек-листа.')
            ->assertJsonPath('calls.0.transcriptStatus', 'Транскрибація готова')
            ->assertJsonPath('calls.0.transcript', 'Цей дзвінок між клієнтом та менеджером: перший.')
            ->assertJsonPath('calls.0.scoreItems.0.title', 'Привітання')
            ->assertJsonPath('calls.0.scoreItems.0.text', 'Менеджер привітався.')
            ->assertJsonPath('calls.0.scoreItems.0.score', 10)
            ->assertJsonPath('calls.0.scoreItems.0.maxPoints', 10)
            ->assertJsonPath('calls.0.scoreItems.0.percentage', 100);
    }
}
