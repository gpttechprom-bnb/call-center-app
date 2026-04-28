<?php

namespace Tests\Feature;

use App\Models\BinotelApiCallCompleted;
use App\Models\BinotelCallFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BinotelFeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('binotel.feedback.api_key', 'uk6Wd0F1ISo7');
        config()->set('binotel.feedback.default_counter', 100);
        config()->set('binotel.feedback.max_counter', 500);
    }

    public function test_rejects_feedback_request_without_valid_key(): void
    {
        $response = $this->getJson('/api/binotel-feedback');

        $response
            ->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ]);
    }

    public function test_returns_feedback_payload_for_specific_general_call_id(): void
    {
        $call = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'attempts_counter' => 1,
            'call_details_general_call_id' => '6536027126',
            'call_details_call_id' => '6536027126',
            'call_details_start_time' => 1713186021,
            'call_details_external_number' => '0962724007',
            'call_details_internal_number' => '904',
            'call_details_billsec' => 239,
            'call_record_url' => 'https://records.example.com/6536027126.mp3',
            'interaction_number' => 2,
        ]);

        $call->historyItems()->create([
            'sort_order' => 0,
            'waitsec' => 16,
            'billsec' => 239,
            'disposition' => 'ANSWER',
            'internal_number' => '904',
            'employee_name' => 'Даниил',
            'employee_email' => '904@ya.ua',
        ]);

        BinotelCallFeedback::query()->create([
            'binotel_api_call_completed_id' => $call->id,
            'general_call_id' => '6536027126',
            'call_id' => '6536027126',
            'transcription_status' => 'completed',
            'transcription_language' => 'uk',
            'transcription_model' => 'large-v3',
            'transcription_text' => 'Повний текст транскрипції',
            'transcription_dialogue_text' => 'Менеджер: ...',
            'evaluation_status' => 'completed',
            'evaluation_score' => 88,
            'evaluation_total_points' => 100,
            'evaluation_score_percent' => 88,
            'evaluation_checklist_id' => 'sales',
            'evaluation_checklist_name' => 'Sales QA',
            'evaluation_summary' => 'Менеджер добре відпрацював дзвінок.',
            'evaluation_provider' => 'ollama',
            'evaluation_model' => 'qwen3.5:4b',
        ]);

        $response = $this->getJson('/api/binotel-feedback?key=uk6Wd0F1ISo7&general_call_id=6536027126');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.general_call_id', '6536027126')
            ->assertJsonPath('data.0.interaction_number', 2)
            ->assertJsonPath('data.0.call.interaction_number', 2)
            ->assertJsonPath('data.0.call.call_record_url', 'https://records.example.com/6536027126.mp3')
            ->assertJsonPath('data.0.feedback.transcription_text', 'Повний текст транскрипції')
            ->assertJsonPath('data.0.feedback.evaluation_score', 88)
            ->assertJsonPath('data.0.history_items.0.internal_number', '904');
    }

    public function test_applies_date_range_counter_and_offset_filters(): void
    {
        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'attempts_counter' => 1,
            'call_details_general_call_id' => 'old-call',
            'call_details_call_id' => 'old-call',
            'call_details_start_time' => 1710000000,
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'attempts_counter' => 1,
            'call_details_general_call_id' => 'mid-call',
            'call_details_call_id' => 'mid-call',
            'call_details_start_time' => 1711000000,
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'attempts_counter' => 1,
            'call_details_general_call_id' => 'new-call',
            'call_details_call_id' => 'new-call',
            'call_details_start_time' => 1712000000,
        ]);

        $response = $this->getJson('/api/binotel-feedback?key=uk6Wd0F1ISo7&date_from=1710500000&counter=1&offset=1');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('meta.counter', 1)
            ->assertJsonPath('meta.offset', 1)
            ->assertJsonPath('data.0.general_call_id', 'mid-call');
    }
}
