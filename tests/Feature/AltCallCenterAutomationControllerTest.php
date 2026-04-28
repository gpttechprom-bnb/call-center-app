<?php

namespace Tests\Feature;

use App\Models\BinotelApiCallCompleted;
use App\Models\BinotelCallFeedback;
use App\Services\AltCallCenterAutoProcessor;
use App\Services\AltCallCenterAutomationDispatcher;
use App\Services\AltCallCenterAutomationStopper;
use App\Services\ProcessTreeTerminator;
use App\Services\AltCallCenterTranscriptionService;
use App\Services\BinotelCallRecordUrlResolver;
use App\Services\BinotelCallFeedbackStore;
use App\Services\CallCenterTranscriptionAiRewriteService;
use App\Support\AltCallCenterAutomationStore;
use App\Support\AltCallCenterEvaluationJobStore;
use App\Support\CallCenterChecklistStore;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class AltCallCenterAutomationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('binotel.log_channel', 'null');
        config()->set('binotel.timezone', 'Europe/Kyiv');
        config()->set('binotel.api.key', '');
        config()->set('binotel.api.secret', '');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_automation_settings_are_saved_for_background_worker_and_thinking_is_forced_off(): void
    {
        Storage::fake('local');

        $response = $this->putJson('/api/alt/call-center/automation/settings', [
            'ai_rewrite' => [
                'model' => 'qwen3.5:4b',
                'prompt' => 'Спільний AI prompt.',
                'prompt_by_model' => [
                    'qwen3.5:4b' => 'AI prompt для qwen-профілю.',
                    'llama3.2:3b' => 'AI prompt для llama-профілю.',
                ],
                'generation_settings' => [
                    'thinking_enabled' => true,
                    'temperature' => 0.3,
                    'num_ctx' => 8192,
                    'top_k' => 40,
                    'top_p' => 0.9,
                    'repeat_penalty' => 1.1,
                    'num_predict' => 512,
                    'seed' => 42,
                    'timeout_seconds' => 600,
                ],
                'generation_settings_by_model' => [
                    'qwen3.5:4b' => [
                        'temperature' => 0.35,
                        'num_ctx' => 12288,
                        'top_k' => 50,
                        'top_p' => 0.85,
                        'repeat_penalty' => 1.15,
                        'num_predict' => 768,
                        'timeout_seconds' => 420,
                    ],
                ],
            ],
            'evaluation' => [
                'model' => 'gemma-no-think:latest',
                'system_prompt' => 'Спільний system prompt.',
                'system_prompt_by_model' => [
                    'gemma-no-think:latest' => 'System prompt для gemma-профілю.',
                    'qwen3:8b' => 'System prompt для qwen-профілю.',
                ],
                'generation_settings' => [
                    'thinking_enabled' => true,
                    'temperature' => 0.1,
                    'num_ctx' => 4096,
                    'top_k' => 20,
                    'top_p' => 0.8,
                    'repeat_penalty' => 1.05,
                    'num_predict' => 256,
                    'timeout_seconds' => 300,
                ],
                'generation_settings_by_model' => [
                    'gemma-no-think:latest' => [
                        'temperature' => 0.18,
                        'num_ctx' => 6144,
                        'top_k' => 24,
                        'top_p' => 0.82,
                        'repeat_penalty' => 1.08,
                        'num_predict' => 384,
                        'timeout_seconds' => 240,
                    ],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('automation.processing_settings.ai_rewrite.model', 'qwen3.5:4b')
            ->assertJsonPath('automation.processing_settings.ai_rewrite.generation_settings.thinking_enabled', false)
            ->assertJsonPath('automation.processing_settings.ai_rewrite.generation_settings.num_ctx', 12288)
            ->assertJsonPath('automation.processing_settings.evaluation.model', 'gemma-no-think:latest')
            ->assertJsonPath('automation.processing_settings.evaluation.generation_settings.thinking_enabled', false)
            ->assertJsonPath('automation.processing_settings.evaluation.generation_settings.timeout_seconds', 240);

        $processingSettings = (array) $response->json('automation.processing_settings');

        $this->assertSame(
            'AI prompt для qwen-профілю.',
            $processingSettings['ai_rewrite']['prompt_by_model']['qwen3.5:4b'] ?? null,
        );
        $this->assertSame(
            'System prompt для gemma-профілю.',
            $processingSettings['evaluation']['system_prompt_by_model']['gemma-no-think:latest'] ?? null,
        );

        $processor = app(AltCallCenterAutoProcessor::class);
        $method = (new ReflectionClass($processor))->getMethod('llmEvaluationSettings');
        $evaluationSettings = $method->invoke($processor);

        $this->assertSame('gemma-no-think:latest', $evaluationSettings['model']);
        $this->assertSame('System prompt для gemma-профілю.', $evaluationSettings['system_prompt']);
        $this->assertFalse($evaluationSettings['thinking_enabled']);
        $this->assertSame(6144, $evaluationSettings['num_ctx']);
        $this->assertSame(240, $evaluationSettings['timeout_seconds']);
    }

    public function test_automation_settings_store_checklist_routing_rules_and_evaluation_toggle(): void
    {
        Storage::fake('local');

        $response = $this->putJson('/api/alt/call-center/automation/settings', [
            'evaluation' => [
                'enabled' => false,
                'checklist_routing_rules' => [
                    [
                        'checklist_id' => 'first-incoming-call',
                        'interaction_number' => 1,
                        'direction' => 'in',
                    ],
                ],
                'model' => 'gemma-no-think:latest',
                'system_prompt' => 'Оціни пункт чек-листа.',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('automation.processing_settings.evaluation.enabled', false)
            ->assertJsonPath('automation.processing_settings.evaluation.checklist_routing_rules.0.checklist_id', 'first-incoming-call')
            ->assertJsonPath('automation.processing_settings.evaluation.checklist_routing_rules.0.interaction_number', 1)
            ->assertJsonPath('automation.processing_settings.evaluation.checklist_routing_rules.0.direction', 'in');
    }

    public function test_automation_settings_store_ai_rewrite_toggle(): void
    {
        Storage::fake('local');

        $response = $this->putJson('/api/alt/call-center/automation/settings', [
            'ai_rewrite' => [
                'enabled' => false,
                'model' => 'qwen3.5:4b',
                'prompt' => 'Точково виправ орфографію.',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('automation.processing_settings.ai_rewrite.enabled', false)
            ->assertJsonPath('automation.processing_settings.ai_rewrite.model', 'qwen3.5:4b');
    }

    public function test_background_worker_uses_checklist_routing_rule_for_saved_feedback(): void
    {
        Storage::fake('local');

        $selectedChecklist = app(CallCenterChecklistStore::class)->save([
            'name' => 'Кастомний чек-лист automation',
            'type' => 'Вхідний сценарій',
            'prompt' => 'Оціни дзвінок за кастомним чек-листом.',
            'items' => [
                ['label' => 'Привітався', 'max_points' => 10],
                ['label' => 'Закрив наступний крок', 'max_points' => 10],
            ],
        ]);

        app(AltCallCenterAutomationStore::class)->saveProcessingSettings([], [
            'enabled' => true,
            'checklist_routing_rules' => [
                [
                    'checklist_id' => (string) $selectedChecklist['id'],
                    'interaction_number' => 1,
                    'direction' => 'in',
                ],
            ],
            'model' => 'gemma-no-think:latest',
            'system_prompt' => 'Спільний system prompt для evaluation.',
            'system_prompt_by_model' => [
                'gemma-no-think:latest' => 'System prompt із профілю gemma.',
                'qwen3:8b' => 'System prompt із профілю qwen.',
            ],
            'generation_settings' => [
                'temperature' => 0.9,
                'num_ctx' => 4096,
                'top_k' => 40,
                'top_p' => 0.9,
                'repeat_penalty' => 1.1,
                'num_predict' => 256,
                'timeout_seconds' => 300,
            ],
            'generation_settings_by_model' => [
                'gemma-no-think:latest' => [
                    'temperature' => 0.17,
                    'num_ctx' => 8192,
                    'top_k' => 18,
                    'top_p' => 0.72,
                    'repeat_penalty' => 1.04,
                    'num_predict' => 512,
                    'timeout_seconds' => 210,
                ],
            ],
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'selected-checklist-source',
            'call_details_call_id' => 'selected-checklist-source',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 23, 1, 0, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0985450576',
            'call_details_employee_name' => 'Manager One',
            'call_details_call_type' => 'incoming',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/selected-checklist-source.mp3',
        ]);

        $this->mock(BinotelCallRecordUrlResolver::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('resolve')
                ->once()
                ->andReturn('https://records.example.com/selected-checklist-source.mp3');
        });

        $this->mock(AltCallCenterTranscriptionService::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('transcribe')
                ->once()
                ->andReturn([
                    'source' => [
                        'type' => 'url',
                        'name' => 'selected-checklist-source.mp3',
                        'relativePath' => null,
                    ],
                    'transcription' => [
                        'text' => 'Менеджер: Доброго дня. Клієнт: Підкажіть деталі.',
                        'formattedText' => 'Менеджер: Доброго дня. Клієнт: Підкажіть деталі.',
                        'dialogueText' => 'Менеджер: Доброго дня. Клієнт: Підкажіть деталі.',
                        'language' => 'uk',
                        'model' => 'large-v3',
                    ],
                    'storageRunDirectory' => 'call-center/alt/transcriptions/test-run',
                ]);
        });

        $this->mock(CallCenterTranscriptionAiRewriteService::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('rewrite')
                ->once()
                ->andReturn([
                    'text' => 'Менеджер: Доброго дня. Клієнт: Підкажіть деталі замовлення.',
                    'model' => 'qwen3.5:4b',
                    'message' => 'AI-обробку завершено.',
                    'corrections' => [],
                    'raw_corrections' => '{"corrections":[]}',
                ]);
        });

        $this->mock(AltCallCenterEvaluationJobStore::class, function (MockInterface $mock) use ($selectedChecklist): void {
            $mock
                ->shouldReceive('latestActiveJob')
                ->once()
                ->andReturn(null);
            $mock
                ->shouldReceive('create')
                ->once()
                ->withArgs(function (array $transcription, array $checklist, array $settings, ?string $generalCallId) use ($selectedChecklist): bool {
                    return ($checklist['id'] ?? null) === $selectedChecklist['id']
                        && ($checklist['name'] ?? null) === $selectedChecklist['name']
                        && $generalCallId === 'selected-checklist-source'
                        && trim((string) ($transcription['dialogueText'] ?? '')) !== ''
                        && ($settings['model'] ?? null) === 'gemma-no-think:latest'
                        && ($settings['system_prompt'] ?? null) === 'System prompt із профілю gemma.'
                        && (float) ($settings['temperature'] ?? 0) === 0.17
                        && ($settings['num_ctx'] ?? null) === 8192
                        && ($settings['timeout_seconds'] ?? null) === 210;
                })
                ->andReturn(['id' => 'job-selected-checklist']);
            $mock
                ->shouldReceive('find')
                ->once()
                ->with('job-selected-checklist')
                ->andReturn([
                    'id' => 'job-selected-checklist',
                    'status' => 'completed',
                ]);
        });

        $feedbackStore = app(BinotelCallFeedbackStore::class);

        Artisan::shouldReceive('call')
            ->once()
            ->with('call-center:alt-evaluate-job', ['jobId' => 'job-selected-checklist'])
            ->andReturnUsing(function () use ($feedbackStore, $selectedChecklist): int {
                $feedbackStore->storeEvaluationResult('selected-checklist-source', [
                    'checklistId' => $selectedChecklist['id'],
                    'checklistName' => $selectedChecklist['name'],
                    'score' => 18,
                    'totalPoints' => 20,
                    'scorePercent' => 90,
                    'summary' => 'Кастомний чек-лист відпрацював коректно.',
                    'strongSide' => 'Добре почав розмову.',
                    'focus' => 'Трохи чіткіше фіксувати наступний крок.',
                    'provider' => 'ollama',
                    'model' => 'qwen3.5:4b',
                ], 'job-selected-checklist');

                return 0;
            });

        $processed = app(AltCallCenterAutoProcessor::class)->processNext();

        $this->assertTrue($processed);
        $this->assertSame(
            'completed',
            app(AltCallCenterAutomationStore::class)->state()['current_stage'] ?? null,
        );
        $this->assertDatabaseHas('binotel_call_feedbacks', [
            'general_call_id' => 'selected-checklist-source',
            'evaluation_status' => 'completed',
            'evaluation_checklist_id' => (string) $selectedChecklist['id'],
            'evaluation_checklist_name' => (string) $selectedChecklist['name'],
            'evaluation_score' => 18,
        ]);
    }

    public function test_background_worker_skips_evaluation_when_no_checklist_routing_rule_matches_call_direction(): void
    {
        Storage::fake('local');

        $selectedChecklist = app(CallCenterChecklistStore::class)->save([
            'name' => 'Лише для вхідних',
            'type' => 'Вхідний сценарій',
            'prompt' => 'Оціни лише вхідний дзвінок.',
            'items' => [
                ['label' => 'Привітався', 'max_points' => 10],
            ],
        ]);

        app(AltCallCenterAutomationStore::class)->saveProcessingSettings([], [
            'enabled' => true,
            'checklist_routing_rules' => [
                [
                    'checklist_id' => (string) $selectedChecklist['id'],
                    'interaction_number' => 1,
                    'direction' => 'in',
                ],
            ],
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'routing-rule-no-match',
            'call_details_call_id' => 'routing-rule-no-match',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 23, 1, 0, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0985450576',
            'call_details_employee_name' => 'Manager One',
            'call_details_call_type' => 'outgoing',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/routing-rule-no-match.mp3',
        ]);

        $this->mock(BinotelCallRecordUrlResolver::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('resolve')
                ->once()
                ->andReturn('https://records.example.com/routing-rule-no-match.mp3');
        });

        $this->mock(AltCallCenterTranscriptionService::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('transcribe')
                ->once()
                ->andReturn([
                    'source' => [
                        'type' => 'url',
                        'name' => 'routing-rule-no-match.mp3',
                        'relativePath' => null,
                    ],
                    'transcription' => [
                        'text' => 'Менеджер: Вітаю. Клієнт: Слухаю.',
                        'formattedText' => 'Менеджер: Вітаю. Клієнт: Слухаю.',
                        'dialogueText' => 'Менеджер: Вітаю. Клієнт: Слухаю.',
                        'language' => 'uk',
                        'model' => 'large-v3',
                    ],
                    'storageRunDirectory' => 'call-center/alt/transcriptions/test-run-routing-no-match',
                ]);
        });

        $this->mock(CallCenterTranscriptionAiRewriteService::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('rewrite')
                ->once()
                ->andReturn([
                    'text' => 'Менеджер: Вітаю. Клієнт: Слухаю уважно.',
                    'model' => 'qwen3.5:4b',
                    'message' => 'AI-обробку завершено.',
                    'corrections' => [],
                    'raw_corrections' => '{"corrections":[]}',
                ]);
        });

        $this->mock(AltCallCenterEvaluationJobStore::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('latestActiveJob')
                ->once()
                ->andReturn(null);
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('find');
        });

        Artisan::shouldReceive('call')->never();

        $processed = app(AltCallCenterAutoProcessor::class)->processNext();

        $this->assertTrue($processed);

        $feedback = BinotelCallFeedback::query()
            ->where('general_call_id', 'routing-rule-no-match')
            ->first();

        $this->assertNotNull($feedback);
        $this->assertSame('completed', (string) $feedback->transcription_status);
        $this->assertNull($feedback->evaluation_status);
        $this->assertNull($feedback->evaluation_checklist_id);
        $this->assertStringContainsString(
            'чек-лист не підібрано',
            (string) (app(AltCallCenterAutomationStore::class)->state()['last_message'] ?? ''),
        );
    }

    public function test_background_worker_uses_selected_ai_model_profile_for_prompt_and_generation_settings(): void
    {
        Storage::fake('local');

        app(AltCallCenterAutomationStore::class)->saveProcessingSettings([
            'model' => 'qwen3.5:4b',
            'prompt' => 'Спільний AI prompt.',
            'prompt_by_model' => [
                'qwen3.5:4b' => 'AI prompt із профілю qwen.',
                'llama3.2:3b' => 'AI prompt із профілю llama.',
            ],
            'generation_settings' => [
                'temperature' => 0.95,
                'num_ctx' => 4096,
                'top_k' => 40,
                'top_p' => 0.9,
                'repeat_penalty' => 1.1,
                'num_predict' => 256,
                'timeout_seconds' => 300,
            ],
            'generation_settings_by_model' => [
                'qwen3.5:4b' => [
                    'temperature' => 0.27,
                    'num_ctx' => 16384,
                    'top_k' => 19,
                    'top_p' => 0.74,
                    'repeat_penalty' => 1.03,
                    'num_predict' => 640,
                    'timeout_seconds' => 225,
                ],
            ],
        ], [
            'enabled' => false,
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'selected-ai-profile',
            'call_details_call_id' => 'selected-ai-profile',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 23, 2, 0, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0501234567',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/selected-ai-profile.mp3',
        ]);

        $this->mock(BinotelCallRecordUrlResolver::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('resolve')
                ->once()
                ->andReturn('https://records.example.com/selected-ai-profile.mp3');
        });

        $this->mock(AltCallCenterTranscriptionService::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('transcribe')
                ->once()
                ->andReturn([
                    'source' => [
                        'type' => 'url',
                        'name' => 'selected-ai-profile.mp3',
                        'relativePath' => null,
                    ],
                    'transcription' => [
                        'text' => 'Менеджер: Доброго дня. Клієнт: Хочу деталі товару.',
                        'formattedText' => 'Менеджер: Доброго дня. Клієнт: Хочу деталі товару.',
                        'dialogueText' => 'Менеджер: Доброго дня. Клієнт: Хочу деталі товару.',
                        'language' => 'uk',
                        'model' => 'large-v3',
                    ],
                    'storageRunDirectory' => 'call-center/alt/transcriptions/test-run-ai-profile',
                ]);
        });

        $this->mock(CallCenterTranscriptionAiRewriteService::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('rewrite')
                ->once()
                ->withArgs(function (
                    string $text,
                    string $prompt,
                    string $model,
                    mixed $settings,
                    array $generationSettings,
                ): bool {
                    return str_contains($text, 'Клієнт: Хочу деталі товару.')
                        && $prompt === 'AI prompt із профілю qwen.'
                        && $model === 'qwen3.5:4b'
                        && (float) ($generationSettings['temperature'] ?? 0) === 0.27
                        && ($generationSettings['num_ctx'] ?? null) === 16384
                        && ($generationSettings['top_k'] ?? null) === 19
                        && ($generationSettings['num_predict'] ?? null) === 640
                        && ($generationSettings['timeout_seconds'] ?? null) === 225;
                })
                ->andReturn([
                    'text' => 'Менеджер: Доброго дня. Клієнт: Хочу деталі товару й доставки.',
                    'model' => 'qwen3.5:4b',
                    'message' => 'AI-обробку завершено.',
                    'corrections' => [],
                    'raw_corrections' => '{"corrections":[]}',
                ]);
        });

        $this->mock(AltCallCenterEvaluationJobStore::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('latestActiveJob')
                ->once()
                ->andReturn(null);
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('find');
        });

        Artisan::shouldReceive('call')->never();

        $processed = app(AltCallCenterAutoProcessor::class)->processNext();

        $this->assertTrue($processed);
        $this->assertSame(
            'completed',
            app(AltCallCenterAutomationStore::class)->state()['current_stage'] ?? null,
        );
        $this->assertDatabaseHas('binotel_call_feedbacks', [
            'general_call_id' => 'selected-ai-profile',
            'transcription_status' => 'completed',
        ]);
    }

    public function test_background_worker_skips_ai_rewrite_when_disabled_and_evaluates_whisper_text_directly(): void
    {
        Storage::fake('local');

        $selectedChecklist = app(CallCenterChecklistStore::class)->save([
            'name' => 'Перевірка без AI-правок',
            'type' => 'Вихідний сценарій',
            'prompt' => 'Оціни дзвінок без верхнього AI-блоку.',
            'items' => [
                ['label' => 'Привітався', 'max_points' => 10],
            ],
        ]);

        app(AltCallCenterAutomationStore::class)->saveProcessingSettings([
            'enabled' => false,
            'model' => 'qwen3.5:4b',
            'prompt' => 'Цей prompt не має викликатися.',
        ], [
            'enabled' => true,
            'checklist_routing_rules' => [
                [
                    'checklist_id' => (string) $selectedChecklist['id'],
                    'interaction_number' => 1,
                    'direction' => 'out',
                ],
            ],
            'model' => 'gemma-no-think:latest',
            'system_prompt' => 'Оціни дзвінок без AI-правок.',
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'skip-ai-rewrite-source',
            'call_details_call_id' => 'skip-ai-rewrite-source',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 24, 12, 0, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0670000001',
            'call_details_employee_name' => 'Manager Skip AI',
            'call_details_call_type' => 'outgoing',
            'call_details_disposition' => 'ANSWER',
            'call_details_billsec' => 75,
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/skip-ai-rewrite-source.mp3',
        ]);

        $this->mock(BinotelCallRecordUrlResolver::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('resolve')
                ->once()
                ->andReturn('https://records.example.com/skip-ai-rewrite-source.mp3');
        });

        $this->mock(AltCallCenterTranscriptionService::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('transcribe')
                ->once()
                ->andReturn([
                    'source' => [
                        'type' => 'url',
                        'name' => 'skip-ai-rewrite-source.mp3',
                        'relativePath' => null,
                    ],
                    'transcription' => [
                        'text' => 'Менеджер: доброго дня. Клієнт: хочу ціну.',
                        'formattedText' => 'Менеджер: доброго дня. Клієнт: хочу ціну.',
                        'dialogueText' => 'Менеджер: доброго дня. Клієнт: хочу ціну.',
                        'language' => 'uk',
                        'model' => 'large-v3',
                    ],
                    'storageRunDirectory' => 'call-center/alt/transcriptions/test-run-skip-ai',
                ]);
        });

        $this->mock(CallCenterTranscriptionAiRewriteService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('rewrite');
        });

        $this->mock(AltCallCenterEvaluationJobStore::class, function (MockInterface $mock) use ($selectedChecklist): void {
            $mock
                ->shouldReceive('latestActiveJob')
                ->once()
                ->andReturn(null);
            $mock
                ->shouldReceive('create')
                ->once()
                ->withArgs(function (array $transcription, array $checklist, array $settings, ?string $generalCallId) use ($selectedChecklist): bool {
                    return ($checklist['id'] ?? null) === $selectedChecklist['id']
                        && $generalCallId === 'skip-ai-rewrite-source'
                        && str_contains((string) ($transcription['dialogueText'] ?? ''), 'Цей дзвінок між клієнтом та менеджером:')
                        && str_contains((string) ($transcription['dialogueText'] ?? ''), 'Менеджер: доброго дня. Клієнт: хочу ціну.')
                        && empty($transcription['ai_rewrite']);
                })
                ->andReturn(['id' => 'job-skip-ai-rewrite']);
            $mock
                ->shouldReceive('find')
                ->once()
                ->with('job-skip-ai-rewrite')
                ->andReturn([
                    'id' => 'job-skip-ai-rewrite',
                    'status' => 'completed',
                ]);
        });

        $feedbackStore = app(BinotelCallFeedbackStore::class);

        Artisan::shouldReceive('call')
            ->once()
            ->with('call-center:alt-evaluate-job', ['jobId' => 'job-skip-ai-rewrite'])
            ->andReturnUsing(function () use ($feedbackStore, $selectedChecklist): int {
                $feedbackStore->storeEvaluationResult('skip-ai-rewrite-source', [
                    'checklistId' => $selectedChecklist['id'],
                    'checklistName' => $selectedChecklist['name'],
                    'score' => 8,
                    'totalPoints' => 10,
                    'scorePercent' => 80,
                    'summary' => 'Оцінка без верхнього AI-блоку завершена.',
                    'provider' => 'ollama',
                    'model' => 'gemma-no-think:latest',
                ], 'job-skip-ai-rewrite');

                return 0;
            });

        $processed = app(AltCallCenterAutoProcessor::class)->processNext();

        $this->assertTrue($processed);
        $this->assertSame(
            'completed',
            app(AltCallCenterAutomationStore::class)->state()['current_stage'] ?? null,
        );
        $this->assertStringContainsString(
            'транскрипт після Whisper',
            (string) (app(AltCallCenterAutomationStore::class)->state()['last_message'] ?? ''),
        );
        $this->assertDatabaseHas('binotel_call_feedbacks', [
            'general_call_id' => 'skip-ai-rewrite-source',
            'transcription_status' => 'completed',
            'evaluation_status' => 'completed',
            'evaluation_checklist_id' => (string) $selectedChecklist['id'],
        ]);
    }

    public function test_background_worker_skips_llm_evaluation_when_automation_evaluation_is_disabled(): void
    {
        Storage::fake('local');

        app(AltCallCenterAutomationStore::class)->saveProcessingSettings([], [
            'enabled' => false,
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'disabled-evaluation-source',
            'call_details_call_id' => 'disabled-evaluation-source',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 23, 1, 30, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0985450576',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/disabled-evaluation-source.mp3',
        ]);

        $this->mock(BinotelCallRecordUrlResolver::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('resolve')
                ->once()
                ->andReturn('https://records.example.com/disabled-evaluation-source.mp3');
        });

        $this->mock(AltCallCenterTranscriptionService::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('transcribe')
                ->once()
                ->andReturn([
                    'source' => [
                        'type' => 'url',
                        'name' => 'disabled-evaluation-source.mp3',
                        'relativePath' => null,
                    ],
                    'transcription' => [
                        'text' => 'Менеджер: Вітаю. Клієнт: Слухаю.',
                        'formattedText' => 'Менеджер: Вітаю. Клієнт: Слухаю.',
                        'dialogueText' => 'Менеджер: Вітаю. Клієнт: Слухаю.',
                        'language' => 'uk',
                        'model' => 'large-v3',
                    ],
                    'storageRunDirectory' => 'call-center/alt/transcriptions/test-run-disabled',
                ]);
        });

        $this->mock(CallCenterTranscriptionAiRewriteService::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('rewrite')
                ->once()
                ->andReturn([
                    'text' => 'Менеджер: Вітаю. Клієнт: Слухаю уважно.',
                    'model' => 'qwen3.5:4b',
                    'message' => 'AI-обробку завершено.',
                    'corrections' => [],
                    'raw_corrections' => '{"corrections":[]}',
                ]);
        });

        $this->mock(AltCallCenterEvaluationJobStore::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('latestActiveJob')
                ->once()
                ->andReturn(null);
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('find');
        });

        Artisan::shouldReceive('call')->never();

        $processed = app(AltCallCenterAutoProcessor::class)->processNext();

        $this->assertTrue($processed);

        $feedback = BinotelCallFeedback::query()
            ->where('general_call_id', 'disabled-evaluation-source')
            ->first();

        $this->assertNotNull($feedback);
        $this->assertSame('completed', (string) $feedback->transcription_status);
        $this->assertNull($feedback->evaluation_status);
        $this->assertNull($feedback->evaluation_checklist_id);
    }

    public function test_background_worker_retries_failed_first_interaction_before_newer_pending_call(): void
    {
        Storage::fake('local');

        app(AltCallCenterAutomationStore::class)->saveProcessingSettings([], [
            'enabled' => false,
        ]);

        $failedCall = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'failed-first-interaction',
            'call_details_call_id' => 'failed-first-interaction',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 23, 1, 0, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0501234567',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 1,
            'alt_auto_status' => 'failed',
            'alt_auto_error' => 'Попередня спроба завершилася помилкою.',
        ]);

        BinotelCallFeedback::query()->create([
            'binotel_api_call_completed_id' => $failedCall->id,
            'general_call_id' => 'failed-first-interaction',
            'call_id' => 'failed-first-interaction',
            'transcription_status' => 'completed',
            'transcription_text' => 'Менеджер: Доброго дня. Клієнт: Потрібна консультація.',
            'transcription_dialogue_text' => 'Менеджер: Доброго дня. Клієнт: Потрібна консультація.',
            'transcription_formatted_text' => 'Менеджер: Доброго дня. Клієнт: Потрібна консультація.',
            'evaluation_status' => 'failed',
            'error_message' => 'Попередня спроба завершилася помилкою.',
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'later-pending-interaction',
            'call_details_call_id' => 'later-pending-interaction',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 23, 2, 0, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0507654321',
            'call_details_employee_name' => 'Manager Two',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/later-pending-interaction.mp3',
            'alt_auto_status' => 'pending',
        ]);

        $this->mock(BinotelCallRecordUrlResolver::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('resolve');
        });

        $this->mock(AltCallCenterTranscriptionService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('transcribe');
        });

        $this->mock(CallCenterTranscriptionAiRewriteService::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('rewrite')
                ->once()
                ->withArgs(function (string $text): bool {
                    return str_contains($text, 'Потрібна консультація.');
                })
                ->andReturn([
                    'text' => 'Менеджер: Доброго дня. Клієнт: Потрібна детальна консультація.',
                    'model' => 'qwen3.5:4b',
                    'message' => 'AI-обробку завершено.',
                    'corrections' => [],
                    'raw_corrections' => '{"corrections":[]}',
                ]);
        });

        $this->mock(AltCallCenterEvaluationJobStore::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('latestActiveJob')
                ->once()
                ->andReturn(null);
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('find');
        });

        Artisan::shouldReceive('call')->never();

        $processed = app(AltCallCenterAutoProcessor::class)->processNext();

        $this->assertTrue($processed);
        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $failedCall->id,
            'alt_auto_status' => 'completed',
            'alt_auto_error' => null,
        ]);
        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'call_details_general_call_id' => 'later-pending-interaction',
            'alt_auto_status' => 'pending',
        ]);
        $this->assertDatabaseHas('binotel_call_feedbacks', [
            'general_call_id' => 'failed-first-interaction',
            'transcription_status' => 'completed',
        ]);
    }

    public function test_window_settings_stop_running_worker_immediately_when_current_time_is_outside_new_window(): void
    {
        Storage::fake('local');
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 23, 12, 0, 0, 'Europe/Kyiv'));

        $automationStore = app(AltCallCenterAutomationStore::class);
        $automationStore->play();
        $automationStore->markWorkerStarted(987654);

        $this->mock(ProcessTreeTerminator::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('terminate')
                ->once()
                ->with(987654)
                ->andReturn([987654]);
        });

        $response = $this->putJson('/api/alt/call-center/automation/settings', [
            'window' => [
                'start_time' => '13:15',
                'end_time' => '14:45',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('automation.window.start_time', '13:15')
            ->assertJsonPath('automation.window.end_time', '14:45')
            ->assertJsonPath('automation.window.is_open', false)
            ->assertJsonPath('automation.paused', false)
            ->assertJsonPath('automation.status', 'waiting')
            ->assertJsonPath('automation.process_id', null);

        $this->assertStringContainsString(
            'Поточний час поза дозволеним вікном',
            (string) $response->json('message'),
        );
    }

    public function test_window_settings_dispatch_worker_immediately_when_queue_is_playing_inside_new_window(): void
    {
        Storage::fake('local');
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 23, 12, 0, 0, 'Europe/Kyiv'));

        $automationStore = app(AltCallCenterAutomationStore::class);
        $automationStore->play();

        $this->mock(AltCallCenterAutomationDispatcher::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('dispatch')
                ->once()
                ->andReturn(123456);
        });

        $response = $this->putJson('/api/alt/call-center/automation/settings', [
            'window' => [
                'start_time' => '08:00',
                'end_time' => '13:00',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('automation.window.start_time', '08:00')
            ->assertJsonPath('automation.window.end_time', '13:00')
            ->assertJsonPath('automation.window.is_open', true)
            ->assertJsonPath('automation.paused', false);

        $this->assertStringContainsString(
            'Поточний час входить у дозволене вікно',
            (string) $response->json('message'),
        );
    }

    public function test_next_first_call_returns_earliest_unprocessed_first_interaction_for_day(): void
    {
        $day = CarbonImmutable::create(2026, 4, 22, 0, 0, 0, 'Europe/Kyiv');

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'second-interaction',
            'call_details_call_id' => 'second-interaction',
            'call_details_start_time' => $day->setTime(9, 0)->getTimestamp(),
            'call_details_external_number' => '0960000001',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 2,
            'call_record_url' => 'https://records.example.com/second.mp3',
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'first-interaction-a',
            'call_details_call_id' => 'first-interaction-a',
            'call_details_start_time' => $day->setTime(10, 0)->getTimestamp(),
            'call_details_external_number' => '0960000002',
            'call_details_employee_name' => 'Manager Two',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/first-a.mp3',
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'first-interaction-b',
            'call_details_call_id' => 'first-interaction-b',
            'call_details_start_time' => $day->setTime(11, 0)->getTimestamp(),
            'call_details_external_number' => '0960000003',
            'call_details_employee_name' => 'Manager Three',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/first-b.mp3',
        ]);

        $response = $this->getJson('/api/alt/call-center/automation/next-first-call?date=22.04.2026');

        $response
            ->assertOk()
            ->assertJsonPath('call.generalCallId', 'first-interaction-a')
            ->assertJsonPath('call.interactionNumber', 1)
            ->assertJsonPath('call.audioUrl', 'https://records.example.com/first-a.mp3');
    }

    public function test_background_worker_skips_call_without_fresh_binotel_url_then_returns_to_it_after_next_call(): void
    {
        Storage::fake('local');
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 24, 8, 0, 0, 'Europe/Kyiv'));

        app(AltCallCenterAutomationStore::class)->saveProcessingSettings([], [
            'enabled' => false,
        ]);

        $firstCall = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'binotel-missing-first',
            'call_details_call_id' => 'binotel-missing-first',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 24, 8, 0, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0960000001',
            'call_details_employee_name' => 'Manager One',
            'call_details_disposition' => 'ANSWER',
            'call_details_billsec' => 120,
            'interaction_number' => 1,
            'call_details_link_to_call_record_in_my_business' => 'https://my.binotel.ua/call/binotel-missing-first',
        ]);

        $secondCall = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'binotel-ready-second',
            'call_details_call_id' => 'binotel-ready-second',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 24, 8, 5, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0960000002',
            'call_details_employee_name' => 'Manager Two',
            'call_details_disposition' => 'ANSWER',
            'call_details_billsec' => 95,
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/binotel-ready-second.mp3',
        ]);

        $this->mock(BinotelCallRecordUrlResolver::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (BinotelApiCallCompleted $call): bool {
                    return (string) $call->call_details_general_call_id === 'binotel-missing-first';
                })
                ->andReturnUsing(function (BinotelApiCallCompleted $call): string {
                    $call->forceFill([
                        'call_record_url' => null,
                        'call_record_url_last_checked_at' => now(),
                        'call_record_url_check_attempts' => 1,
                    ])->save();

                    return '';
                });

            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (BinotelApiCallCompleted $call): bool {
                    return (string) $call->call_details_general_call_id === 'binotel-ready-second';
                })
                ->andReturn('https://records.example.com/binotel-ready-second.mp3');

            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (BinotelApiCallCompleted $call): bool {
                    return (string) $call->call_details_general_call_id === 'binotel-missing-first';
                })
                ->andReturnUsing(function (BinotelApiCallCompleted $call): string {
                    $freshUrl = 'https://records.example.com/binotel-missing-first.mp3';

                    $call->forceFill([
                        'call_record_url' => $freshUrl,
                        'call_record_url_last_checked_at' => now(),
                        'call_record_url_check_attempts' => 2,
                    ])->save();

                    return $freshUrl;
                });
        });

        $this->mock(AltCallCenterTranscriptionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('transcribe')
                ->once()
                ->with(
                    null,
                    'https://records.example.com/binotel-ready-second.mp3',
                    'auto',
                    null,
                    Mockery::type('callable'),
                )
                ->andReturn([
                    'source' => ['type' => 'url', 'name' => 'binotel-ready-second.mp3', 'relativePath' => null],
                    'transcription' => [
                        'text' => 'Текст другого дзвінка',
                        'formattedText' => 'Текст другого дзвінка',
                        'dialogueText' => 'Текст другого дзвінка',
                    ],
                ]);

            $mock->shouldReceive('transcribe')
                ->once()
                ->with(
                    null,
                    'https://records.example.com/binotel-missing-first.mp3',
                    'auto',
                    null,
                    Mockery::type('callable'),
                )
                ->andReturn([
                    'source' => ['type' => 'url', 'name' => 'binotel-missing-first.mp3', 'relativePath' => null],
                    'transcription' => [
                        'text' => 'Текст першого дзвінка',
                        'formattedText' => 'Текст першого дзвінка',
                        'dialogueText' => 'Текст першого дзвінка',
                    ],
                ]);
        });

        $this->mock(CallCenterTranscriptionAiRewriteService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('rewrite')
                ->once()
                ->andReturn([
                    'text' => 'AI текст другого дзвінка',
                    'corrections' => [],
                    'raw_corrections' => '{"corrections":[]}',
                ]);

            $mock->shouldReceive('rewrite')
                ->once()
                ->andReturn([
                    'text' => 'AI текст першого дзвінка',
                    'corrections' => [],
                    'raw_corrections' => '{"corrections":[]}',
                ]);
        });

        $this->mock(AltCallCenterEvaluationJobStore::class, function (MockInterface $mock): void {
            $mock->shouldReceive('latestActiveJob')->times(3)->andReturn(null);
        });

        $processor = app(AltCallCenterAutoProcessor::class);

        $this->assertTrue($processor->processNext());
        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $firstCall->id,
            'alt_auto_status' => 'failed',
            'alt_auto_error' => 'Binotel не повернув пряме посилання на запис навіть після запиту оновлення. Дзвінок пропущено до наступної спроби.',
            'call_record_url_check_attempts' => 1,
        ]);
        $this->assertDatabaseMissing('binotel_call_feedbacks', [
            'general_call_id' => 'binotel-missing-first',
        ]);

        $this->assertTrue($processor->processNext());
        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $secondCall->id,
            'alt_auto_status' => 'completed',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(46));

        $this->assertTrue($processor->processNext());
        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $firstCall->id,
            'alt_auto_status' => 'completed',
            'call_record_url' => 'https://records.example.com/binotel-missing-first.mp3',
            'call_record_url_check_attempts' => 2,
        ]);
    }

    public function test_background_worker_marks_binotel_call_final_failure_after_second_missing_url_attempt(): void
    {
        Storage::fake('local');
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 24, 10, 0, 0, 'Europe/Kyiv'));

        app(AltCallCenterAutomationStore::class)->saveProcessingSettings([], [
            'enabled' => false,
        ]);

        $firstCall = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'binotel-missing-final',
            'call_details_call_id' => 'binotel-missing-final',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 24, 10, 0, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0960000013',
            'call_details_employee_name' => 'Manager Three',
            'call_details_disposition' => 'ANSWER',
            'call_details_billsec' => 120,
            'interaction_number' => 1,
            'call_details_link_to_call_record_in_my_business' => 'https://my.binotel.ua/call/binotel-missing-final',
        ]);

        $secondCall = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'binotel-ready-after-gap',
            'call_details_call_id' => 'binotel-ready-after-gap',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 24, 10, 5, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0960000014',
            'call_details_employee_name' => 'Manager Four',
            'call_details_disposition' => 'ANSWER',
            'call_details_billsec' => 95,
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/binotel-ready-after-gap.mp3',
        ]);

        $this->mock(BinotelCallRecordUrlResolver::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (BinotelApiCallCompleted $call): bool {
                    return (string) $call->call_details_general_call_id === 'binotel-missing-final';
                })
                ->andReturnUsing(function (BinotelApiCallCompleted $call): string {
                    $call->forceFill([
                        'call_record_url' => null,
                        'call_record_url_last_checked_at' => now(),
                        'call_record_url_check_attempts' => 1,
                    ])->save();

                    return '';
                });

            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (BinotelApiCallCompleted $call): bool {
                    return (string) $call->call_details_general_call_id === 'binotel-ready-after-gap';
                })
                ->andReturn('https://records.example.com/binotel-ready-after-gap.mp3');

            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (BinotelApiCallCompleted $call): bool {
                    return (string) $call->call_details_general_call_id === 'binotel-missing-final';
                })
                ->andReturnUsing(function (BinotelApiCallCompleted $call): string {
                    $call->forceFill([
                        'call_record_url' => null,
                        'call_record_url_last_checked_at' => now(),
                        'call_record_url_check_attempts' => 2,
                    ])->save();

                    return '';
                });
        });

        $this->mock(AltCallCenterTranscriptionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('transcribe')
                ->once()
                ->with(
                    null,
                    'https://records.example.com/binotel-ready-after-gap.mp3',
                    'auto',
                    null,
                    Mockery::type('callable'),
                )
                ->andReturn([
                    'source' => ['type' => 'url', 'name' => 'binotel-ready-after-gap.mp3', 'relativePath' => null],
                    'transcription' => [
                        'text' => 'Текст соседнего дзвінка',
                        'formattedText' => 'Текст соседнего дзвінка',
                        'dialogueText' => 'Текст соседнего дзвінка',
                    ],
                ]);
        });

        $this->mock(CallCenterTranscriptionAiRewriteService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('rewrite')
                ->once()
                ->andReturn([
                    'text' => 'AI текст соседнего дзвінка',
                    'corrections' => [],
                    'raw_corrections' => '{"corrections":[]}',
                ]);
        });

        $this->mock(AltCallCenterEvaluationJobStore::class, function (MockInterface $mock): void {
            $mock->shouldReceive('latestActiveJob')->times(4)->andReturn(null);
        });

        $processor = app(AltCallCenterAutoProcessor::class);

        $this->assertTrue($processor->processNext());
        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $firstCall->id,
            'alt_auto_status' => 'failed',
            'alt_auto_error' => 'Binotel не повернув пряме посилання на запис навіть після запиту оновлення. Дзвінок пропущено до наступної спроби.',
            'call_record_url_check_attempts' => 1,
        ]);

        $this->assertTrue($processor->processNext());
        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $secondCall->id,
            'alt_auto_status' => 'completed',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(46));

        $this->assertTrue($processor->processNext());
        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $firstCall->id,
            'alt_auto_status' => 'failed',
            'alt_auto_error' => 'Binotel не повернув пряме посилання на запис навіть після повторної перевірки. Дзвінок позначено як помилку.',
            'call_record_url_check_attempts' => 2,
        ]);

        $this->assertFalse($processor->processNext());
        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $firstCall->id,
            'alt_auto_status' => 'failed',
            'call_record_url_check_attempts' => 2,
        ]);
    }

    public function test_background_worker_skips_download_failure_and_moves_to_next_call(): void
    {
        Storage::fake('local');

        app(AltCallCenterAutomationStore::class)->saveProcessingSettings([], [
            'enabled' => false,
        ]);

        $firstCall = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'audio-download-failed',
            'call_details_call_id' => 'audio-download-failed',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 24, 9, 0, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0960000011',
            'call_details_employee_name' => 'Manager One',
            'call_details_disposition' => 'ANSWER',
            'call_details_billsec' => 90,
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/audio-download-failed.mp3',
            'call_record_url_last_checked_at' => now()->subMinutes(10),
            'call_record_url_check_attempts' => 1,
        ]);

        $secondCall = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'audio-download-next',
            'call_details_call_id' => 'audio-download-next',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 24, 9, 5, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0960000012',
            'call_details_employee_name' => 'Manager Two',
            'call_details_disposition' => 'ANSWER',
            'call_details_billsec' => 120,
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/audio-download-next.mp3',
        ]);

        $this->mock(BinotelCallRecordUrlResolver::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (BinotelApiCallCompleted $call): bool {
                    return (string) $call->call_details_general_call_id === 'audio-download-failed';
                })
                ->andReturn('https://records.example.com/audio-download-failed.mp3');

            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (BinotelApiCallCompleted $call): bool {
                    return (string) $call->call_details_general_call_id === 'audio-download-next';
                })
                ->andReturn('https://records.example.com/audio-download-next.mp3');
        });

        $this->mock(AltCallCenterTranscriptionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('transcribe')
                ->once()
                ->with(
                    null,
                    'https://records.example.com/audio-download-failed.mp3',
                    'auto',
                    null,
                    Mockery::type('callable'),
                )
                ->andThrow(new RuntimeException('Не вдалося завантажити аудіо за посиланням. HTTP 403 Forbidden.'));

            $mock->shouldReceive('transcribe')
                ->once()
                ->with(
                    null,
                    'https://records.example.com/audio-download-next.mp3',
                    'auto',
                    null,
                    Mockery::type('callable'),
                )
                ->andReturn([
                    'source' => ['type' => 'url', 'name' => 'audio-download-next.mp3', 'relativePath' => null],
                    'transcription' => [
                        'text' => 'Текст наступного дзвінка',
                        'formattedText' => 'Текст наступного дзвінка',
                        'dialogueText' => 'Текст наступного дзвінка',
                    ],
                ]);
        });

        $this->mock(CallCenterTranscriptionAiRewriteService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('rewrite')
                ->once()
                ->andReturn([
                    'text' => 'AI текст наступного дзвінка',
                    'corrections' => [],
                    'raw_corrections' => '{"corrections":[]}',
                ]);
        });

        $this->mock(AltCallCenterEvaluationJobStore::class, function (MockInterface $mock): void {
            $mock->shouldReceive('latestActiveJob')->times(2)->andReturn(null);
        });

        $processor = app(AltCallCenterAutoProcessor::class);

        $this->assertTrue($processor->processNext());
        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $firstCall->id,
            'call_record_url' => null,
            'alt_auto_status' => 'failed',
            'alt_auto_error' => 'Не вдалося завантажити аудіо за посиланням. HTTP 403 Forbidden.',
        ]);

        $this->assertTrue($processor->processNext());
        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $secondCall->id,
            'alt_auto_status' => 'completed',
        ]);
    }

    public function test_next_first_call_skips_calls_that_already_have_evaluation(): void
    {
        $day = CarbonImmutable::create(2026, 4, 22, 0, 0, 0, 'Europe/Kyiv');

        $evaluated = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'already-evaluated',
            'call_details_call_id' => 'already-evaluated',
            'call_details_start_time' => $day->setTime(8, 0)->getTimestamp(),
            'call_details_external_number' => '0960000001',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/already.mp3',
        ]);

        BinotelCallFeedback::query()->create([
            'binotel_api_call_completed_id' => $evaluated->id,
            'general_call_id' => 'already-evaluated',
            'call_id' => 'already-evaluated',
            'transcription_status' => 'completed',
            'evaluation_status' => 'completed',
            'evaluation_score' => 91,
            'evaluated_at' => now(),
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'next-unprocessed',
            'call_details_call_id' => 'next-unprocessed',
            'call_details_start_time' => $day->setTime(9, 0)->getTimestamp(),
            'call_details_external_number' => '0960000002',
            'call_details_employee_name' => 'Manager Two',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/next.mp3',
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'late-unprocessed',
            'call_details_call_id' => 'late-unprocessed',
            'call_details_start_time' => $day->setTime(22, 0)->getTimestamp(),
            'call_details_external_number' => '0960000003',
            'call_details_employee_name' => 'Manager Three',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/late.mp3',
        ]);

        $response = $this->getJson('/api/alt/call-center/automation/next-first-call?date=22.04.2026');

        $response
            ->assertOk()
            ->assertJsonPath('call.generalCallId', 'next-unprocessed')
            ->assertJsonPath('call.audioUrl', 'https://records.example.com/next.mp3');
    }

    public function test_next_first_call_reserves_returned_call_so_next_request_moves_forward(): void
    {
        $day = CarbonImmutable::create(2026, 4, 22, 0, 0, 0, 'Europe/Kyiv');

        $first = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'first-unreserved',
            'call_details_call_id' => 'first-unreserved',
            'call_details_start_time' => $day->setTime(8, 0)->getTimestamp(),
            'call_details_external_number' => '0960000001',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/first.mp3',
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'second-unreserved',
            'call_details_call_id' => 'second-unreserved',
            'call_details_start_time' => $day->setTime(9, 0)->getTimestamp(),
            'call_details_external_number' => '0960000002',
            'call_details_employee_name' => 'Manager Two',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/second.mp3',
        ]);

        $this->getJson('/api/alt/call-center/automation/next-first-call?date=22.04.2026')
            ->assertOk()
            ->assertJsonPath('call.generalCallId', 'first-unreserved');

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $first->id,
            'alt_auto_status' => 'reserved',
        ]);

        $this->getJson('/api/alt/call-center/automation/next-first-call?date=22.04.2026')
            ->assertOk()
            ->assertJsonPath('call.generalCallId', 'second-unreserved');
    }

    public function test_next_first_call_returns_failed_first_interaction_before_newer_pending_call(): void
    {
        $day = CarbonImmutable::create(2026, 4, 22, 0, 0, 0, 'Europe/Kyiv');

        $failed = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'failed-first-call',
            'call_details_call_id' => 'failed-first-call',
            'call_details_start_time' => $day->setTime(8, 0)->getTimestamp(),
            'call_details_external_number' => '0960000001',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/failed-first-call.mp3',
            'alt_auto_status' => 'failed',
            'alt_auto_error' => 'Попередня спроба завершилася помилкою.',
        ]);

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'later-pending-call',
            'call_details_call_id' => 'later-pending-call',
            'call_details_start_time' => $day->setTime(9, 0)->getTimestamp(),
            'call_details_external_number' => '0960000002',
            'call_details_employee_name' => 'Manager Two',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/later-pending-call.mp3',
            'alt_auto_status' => 'pending',
        ]);

        $response = $this->getJson('/api/alt/call-center/automation/next-first-call?date=22.04.2026');

        $response
            ->assertOk()
            ->assertJsonPath('call.generalCallId', 'failed-first-call')
            ->assertJsonPath('call.audioUrl', 'https://records.example.com/failed-first-call.mp3');

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $failed->id,
            'alt_auto_status' => 'reserved',
            'alt_auto_error' => null,
        ]);
    }

    public function test_next_first_call_refreshes_expired_binotel_s3_url(): void
    {
        config()->set('binotel.api.key', 'test-key');
        config()->set('binotel.api.secret', 'test-secret');

        $day = CarbonImmutable::create(2026, 4, 22, 0, 0, 0, 'Europe/Kyiv');
        $expiredUrl = 'https://cdn0993.s3.eu-west-1.amazonaws.com/65/expired.mp3?X-Amz-Date=20200101T000000Z&X-Amz-Expires=3600&X-Amz-Signature=expired';
        $freshUrl = 'https://cdn0993.s3.eu-west-1.amazonaws.com/65/fresh.mp3?X-Amz-Date=20990101T000000Z&X-Amz-Expires=3600&X-Amz-Signature=fresh';

        BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'refresh-me',
            'call_details_call_id' => 'refresh-me',
            'call_details_start_time' => $day->setTime(10, 0)->getTimestamp(),
            'call_details_external_number' => '0960000002',
            'call_details_employee_name' => 'Manager Two',
            'call_details_disposition' => 'ANSWER',
            'call_details_billsec' => 60,
            'interaction_number' => 1,
            'call_record_url' => $expiredUrl,
        ]);

        Http::fake([
            'https://api.binotel.com/api/4.0/stats/call-record.json' => Http::response([
                'url' => $freshUrl,
            ], 200),
        ]);

        $response = $this->getJson('/api/alt/call-center/automation/next-first-call?date=22.04.2026');

        $response
            ->assertOk()
            ->assertJsonPath('call.generalCallId', 'refresh-me')
            ->assertJsonPath('call.audioUrl', $freshUrl)
            ->assertJsonPath('call.binotelStatus', 'Успіх');

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'call_details_general_call_id' => 'refresh-me',
            'call_record_url' => $freshUrl,
            'call_record_url_check_attempts' => 1,
        ]);
    }

    public function test_alt_transcription_rejects_audio_url_for_already_evaluated_call(): void
    {
        $call = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => '6536626178',
            'call_details_call_id' => '6536626178',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 22, 22, 2, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0985450576',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 1,
            'call_record_url' => 'https://cdn0993.s3.eu-west-1.amazonaws.com/65/6536626178.mp3',
            'alt_auto_status' => 'skipped',
        ]);

        BinotelCallFeedback::query()->create([
            'binotel_api_call_completed_id' => $call->id,
            'general_call_id' => '6536626178',
            'call_id' => '6536626178',
            'transcription_status' => 'completed',
            'evaluation_status' => 'completed',
            'evaluation_score' => 91,
            'evaluated_at' => now(),
        ]);

        $response = $this->postJson('/api/alt/call-center/transcriptions', [
            'audio_url' => 'https://cdn0993.s3.eu-west-1.amazonaws.com/65/6536626178.mp3?X-Amz-Date=20260422T153530Z&X-Amz-Expires=3600',
            'language' => 'auto',
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('already_processed', true)
            ->assertJsonPath('call.generalCallId', '6536626178');

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $call->id,
            'alt_auto_status' => 'completed',
        ]);
    }

    public function test_alt_transcription_marks_call_failed_when_whisper_cannot_load_audio(): void
    {
        $call = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => '6536000001',
            'call_details_call_id' => '6536000001',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 22, 12, 0, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0985450576',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 1,
            'call_record_url' => 'https://cdn0993.s3.eu-west-1.amazonaws.com/65/6536000001.mp3',
            'alt_auto_status' => 'running',
        ]);

        $this->mock(AltCallCenterTranscriptionService::class)
            ->shouldReceive('transcribe')
            ->once()
            ->andThrow(new RuntimeException('Не вдалося завантажити аудіо за посиланням.'));

        $this->postJson('/api/alt/call-center/transcriptions', [
            'audio_url' => 'https://cdn0993.s3.eu-west-1.amazonaws.com/65/6536000001.mp3',
            'language' => 'auto',
        ])->assertStatus(422);

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $call->id,
            'alt_auto_status' => 'failed',
            'alt_auto_error' => 'Не вдалося завантажити аудіо за посиланням.',
        ]);
    }

    public function test_alt_transcription_retries_with_fresh_audio_url_after_download_failure(): void
    {
        $call = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => '6536000002',
            'call_details_call_id' => '6536000002',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 22, 12, 30, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0985450576',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 1,
            'call_record_url' => 'https://cdn0993.s3.eu-west-1.amazonaws.com/65/6536000002.mp3?signature=stale',
            'alt_auto_status' => 'running',
        ]);

        $refreshedUrl = 'https://cdn0993.s3.eu-west-1.amazonaws.com/65/6536000002.mp3?signature=fresh';

        $this->mock(AltCallCenterTranscriptionService::class, function (MockInterface $mock) use ($refreshedUrl): void {
            $mock->shouldReceive('transcribe')
                ->once()
                ->with(null, 'https://cdn0993.s3.eu-west-1.amazonaws.com/65/6536000002.mp3?signature=stale', 'auto', Mockery::type('callable'))
                ->andThrow(new RuntimeException('Не вдалося завантажити аудіо за посиланням.'));

            $mock->shouldReceive('transcribe')
                ->once()
                ->with(null, $refreshedUrl, 'auto', Mockery::type('callable'))
                ->andReturn([
                    'source' => [
                        'type' => 'url',
                        'name' => $refreshedUrl,
                        'relativePath' => 'call-center/alt/transcriptions/test/remote-audio.mp3',
                    ],
                    'transcription' => [
                        'model' => 'large-v3',
                        'text' => 'Оновлений текст',
                        'formattedText' => 'Оновлений текст',
                        'dialogueText' => 'Оновлений текст',
                        'language' => 'uk',
                    ],
                ]);
        });

        $this->mock(BinotelCallRecordUrlResolver::class, function (MockInterface $mock) use ($call, $refreshedUrl): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (BinotelApiCallCompleted $resolvedCall) use ($call): bool {
                    return $resolvedCall->is($call);
                })
                ->andReturn('https://cdn0993.s3.eu-west-1.amazonaws.com/65/6536000002.mp3?signature=stale');

            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (BinotelApiCallCompleted $resolvedCall, bool $forceRefresh) use ($call): bool {
                    return $resolvedCall->is($call) && $forceRefresh === true;
                })
                ->andReturn($refreshedUrl);
        });

        $this->postJson('/api/alt/call-center/transcriptions', [
            'audio_url' => 'https://cdn0993.s3.eu-west-1.amazonaws.com/65/6536000002.mp3?signature=stale',
            'language' => 'auto',
        ])
            ->assertOk()
            ->assertJsonPath('transcription.text', 'Оновлений текст')
            ->assertJsonPath('task.source.name', $refreshedUrl);

        $this->assertDatabaseMissing('binotel_api_call_completeds', [
            'id' => $call->id,
            'alt_auto_status' => 'failed',
        ]);
    }

    public function test_feedback_store_marks_running_and_completed_calls_without_reverting_to_failed(): void
    {
        $call = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'feedback-completed',
            'call_details_call_id' => 'feedback-completed',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 22, 13, 0, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0985450576',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/feedback.mp3',
        ]);

        $store = app(BinotelCallFeedbackStore::class);

        $store->storeEvaluationRequested('feedback-completed', [
            'text' => 'Текст дзвінка',
            'dialogueText' => 'Текст дзвінка',
        ], [
            'id' => 'checklist',
            'name' => 'Чек-лист',
        ], 'job-1');

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $call->id,
            'alt_auto_status' => 'running',
        ]);

        $store->markEvaluationRunning('feedback-completed', 'job-1');

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $call->id,
            'alt_auto_status' => 'running',
        ]);

        $store->storeEvaluationResult('feedback-completed', [
            'checklistId' => 'checklist',
            'checklistName' => 'Чек-лист',
            'score' => 18,
            'totalPoints' => 20,
            'scorePercent' => 90,
        ], 'job-1');

        $this->assertDatabaseHas('binotel_call_feedbacks', [
            'general_call_id' => 'feedback-completed',
            'evaluation_status' => 'completed',
            'evaluation_score' => 18,
        ]);

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $call->id,
            'alt_auto_status' => 'completed',
            'alt_auto_error' => null,
        ]);
    }

    public function test_pause_stops_worker_and_releases_current_call_for_retry(): void
    {
        Storage::fake('local');

        $call = BinotelApiCallCompleted::query()->create([
            'request_type' => 'apiCallCompleted',
            'call_details_general_call_id' => 'stop-current-call',
            'call_details_call_id' => 'stop-current-call',
            'call_details_start_time' => CarbonImmutable::create(2026, 4, 23, 5, 50, 0, 'Europe/Kyiv')->getTimestamp(),
            'call_details_external_number' => '0985450576',
            'call_details_employee_name' => 'Manager One',
            'interaction_number' => 1,
            'call_record_url' => 'https://records.example.com/stop-current-call.mp3',
            'alt_auto_status' => 'running',
            'alt_auto_started_at' => now(),
        ]);

        $automationStore = app(AltCallCenterAutomationStore::class);
        $automationStore->play();
        $automationStore->markWorkerStarted(987654);
        $automationStore->markCurrentCall(
            'stop-current-call',
            'https://records.example.com/stop-current-call.mp3',
            'Транскрибуємо дзвінок stop-current-call через Whisper.',
        );

        $jobStore = app(AltCallCenterEvaluationJobStore::class);
        $job = $jobStore->create([
            'text' => 'Текст дзвінка',
            'dialogueText' => 'Текст дзвінка',
        ], [
            'id' => 'checklist',
            'name' => 'Чек-лист',
            'items' => [],
        ], [], 'stop-current-call');
        $jobId = (string) $job['id'];
        $jobStore->markRunning($jobId);
        $jobStore->updateProcessId($jobId, 987654);

        app(BinotelCallFeedbackStore::class)->storeEvaluationRequested('stop-current-call', [
            'text' => 'Текст дзвінка',
            'dialogueText' => 'Текст дзвінка',
        ], [
            'id' => 'checklist',
            'name' => 'Чек-лист',
        ], $jobId);

        $this->mock(ProcessTreeTerminator::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('terminate')
                ->once()
                ->with(987654)
                ->andReturn([987654]);
        });

        $response = $this->postJson('/api/alt/call-center/automation/pause');

        $response
            ->assertOk()
            ->assertJsonPath('automation.paused', true)
            ->assertJsonPath('automation.status', 'paused')
            ->assertJsonPath('automation.process_id', null)
            ->assertJsonPath('automation.current_general_call_id', null)
            ->assertJsonPath('automation.current_audio_url', null);

        $this->assertDatabaseHas('binotel_api_call_completeds', [
            'id' => $call->id,
            'alt_auto_status' => 'pending',
            'alt_auto_error' => 'Фонову обробку зупинено вручну. Дзвінок повернено в чергу.',
        ]);

        $this->assertDatabaseHas('binotel_call_feedbacks', [
            'general_call_id' => 'stop-current-call',
            'evaluation_status' => 'failed',
            'last_evaluation_job_id' => $jobId,
            'error_message' => 'Фонову обробку зупинено вручну. Дзвінок повернено в чергу.',
        ]);

        $finishedJob = $jobStore->find($jobId);

        $this->assertSame('failed', $finishedJob['status'] ?? null);
        $this->assertNull($finishedJob['process_id'] ?? null);
        $this->assertSame(
            'Фонову обробку зупинено вручну. Дзвінок повернено в чергу.',
            $finishedJob['error'] ?? null,
        );
    }

    public function test_closed_window_stop_preserves_manual_pause_flag(): void
    {
        Storage::fake('local');

        $automationStore = app(AltCallCenterAutomationStore::class);
        $automationStore->pause();

        $state = app(AltCallCenterAutomationStopper::class)
            ->stopForClosedWindow('Фонова транскрибація вимкнена у робочий час.');

        $this->assertTrue($state['paused']);
        $this->assertSame('paused', $state['status']);
        $this->assertSame('Фонова транскрибація вимкнена у робочий час.', $state['last_message']);
    }

    public function test_manual_pause_outside_window_is_marked_for_next_window_auto_resume(): void
    {
        Storage::fake('local');
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 27, 12, 0, 0, 'Europe/Kyiv'));

        $automationStore = app(AltCallCenterAutomationStore::class);
        $automationStore->play();
        $automationStore->saveWindowSettings('19:23', '06:00');

        $this->mock(ProcessTreeTerminator::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('terminate')
                ->once()
                ->with(null)
                ->andReturn([]);
        });

        $response = $this->postJson('/api/alt/call-center/automation/pause');

        $response
            ->assertOk()
            ->assertJsonPath('automation.paused', true)
            ->assertJsonPath('automation.resume_on_next_window_open', true)
            ->assertJsonPath('automation.paused_reason', 'manual')
            ->assertJsonPath('automation.window.is_open', false);
    }

    public function test_manual_pause_inside_open_window_waits_for_the_next_scheduled_window(): void
    {
        Storage::fake('local');
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 27, 20, 0, 0, 'Europe/Kyiv'));

        $automationStore = app(AltCallCenterAutomationStore::class);
        $automationStore->play();
        $automationStore->saveWindowSettings('19:23', '06:00');

        $this->mock(ProcessTreeTerminator::class, function (MockInterface $mock): void {
            $mock
                ->shouldReceive('terminate')
                ->once()
                ->with(null)
                ->andReturn([]);
        });

        $response = $this->postJson('/api/alt/call-center/automation/pause');

        $response
            ->assertOk()
            ->assertJsonPath('automation.paused', true)
            ->assertJsonPath('automation.resume_on_next_window_open', true)
            ->assertJsonPath('automation.wait_for_window_to_close_before_resume', true)
            ->assertJsonPath('automation.paused_reason', 'manual')
            ->assertJsonPath('automation.window.is_open', true);
    }
}
