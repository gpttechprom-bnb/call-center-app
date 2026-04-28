<?php

namespace Tests\Unit;

use App\Services\AltCallCenterChecklistEvaluator;
use App\Support\AltCallCenterTranscriptionSettings;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AltCallCenterChecklistEvaluatorTest extends TestCase
{
    public function test_build_system_prompt_uses_stateless_single_item_rules(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $prompt = $this->invokePrivateMethod($service, 'buildSystemPrompt', [[
            'prompt' => '',
        ]]);

        $this->assertStringContainsString('Оціни один пункт чек-листа тільки за наданим транскриптом.', $prompt);
        $this->assertStringContainsString('Не покладайся на пам’ять попередніх повідомлень.', $prompt);
        $this->assertStringContainsString('з нового рядка тільки одне слово: Так або Ні', $prompt);
    }

    public function test_build_checklist_evaluation_messages_are_stateless_and_include_full_transcript_once(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());
        $fullTranscript = "[00:00] Менеджер: Добрий день.\n[00:02] Клієнт: Мені потрібен рахунок.";
        $item = [
            'id' => 'item_1',
            'label' => 'Чи привітався менеджер?',
            'max_points' => 2,
        ];

        $messages = $this->invokePrivateMethod($service, 'buildChecklistEvaluationMessages', [
            $fullTranscript,
            $item,
        ]);

        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('user', $messages[1]['role']);
        $this->assertStringContainsString($fullTranscript, $messages[1]['content']);
        $this->assertStringContainsString('Пункт чек-листа:', $messages[1]['content']);
        $this->assertStringContainsString($item['label'], $messages[1]['content']);
        $this->assertStringNotContainsString('assistant', json_encode($messages, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('Попередня відповідь', $messages[1]['content']);
    }

    public function test_build_system_prompt_uses_sequential_chat_rules_when_scenario_selected(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $prompt = $this->invokePrivateMethod($service, 'buildSystemPrompt', [[
            'prompt' => '',
        ], [
            'evaluation_scenario' => 'sequential_chat',
            'system_prompt' => 'Ти QA-асистент відділу продажів. Оціни один пункт чек-листа тільки за наданим транскриптом. Не покладайся на пам’ять попередніх повідомлень.',
        ]]);

        $this->assertStringContainsString('Спочатку уважно прочитай транскрипт дзвінка.', $prompt);
        $this->assertStringContainsString('Пам’ятай контекст діалогу в межах поточного чату', $prompt);
        $this->assertStringContainsString('відповідай тільки: ГОТОВО', $prompt);
    }

    public function test_build_sequential_bootstrap_messages_include_transcript_once_and_ready_instruction(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());
        $fullTranscript = "[00:00] Менеджер: Добрий день.\n[00:02] Клієнт: Мені потрібен рахунок.";

        $messages = $this->invokePrivateMethod($service, 'buildSequentialConversationBootstrapMessages', [
            $fullTranscript,
            [
                'name' => 'Перший вхідний дзвінок',
            ],
            [
                'evaluation_scenario' => 'sequential_chat',
            ],
        ]);

        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('user', $messages[1]['role']);
        $this->assertStringContainsString($fullTranscript, $messages[1]['content']);
        $this->assertStringContainsString('Запамʼятай цей транскрипт у межах поточного чату.', $messages[1]['content']);
        $this->assertStringContainsString('відповідай тільки словом: ГОТОВО', $messages[1]['content']);
    }

    public function test_legacy_saved_sequential_system_prompt_is_replaced_with_stateless_prompt(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $messages = $this->invokePrivateMethod($service, 'buildChecklistEvaluationMessages', [
            'Повний транскрипт',
            [
                'id' => 'item_1',
                'label' => 'Чи було привітання?',
                'max_points' => 2,
            ],
            [],
            [
                'system_prompt' => 'Ти QA-асистент відділу продажів і працюєш тільки в режимі послідовного чату.',
            ],
        ]);

        $this->assertStringContainsString('Оціни один пункт чек-листа тільки за наданим транскриптом.', $messages[0]['content']);
        $this->assertStringNotContainsString('послідовного чату', $messages[0]['content']);
    }

    public function test_evaluation_scenario_normalizes_supported_aliases(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $this->assertSame(
            AltCallCenterChecklistEvaluator::SCENARIO_SEQUENTIAL_CHAT,
            $service->evaluationScenario(['evaluation_scenario' => 'chat'])
        );
        $this->assertSame(
            AltCallCenterChecklistEvaluator::SCENARIO_STATELESS_SINGLE_ITEM,
            $service->evaluationScenario(['evaluation_scenario' => 'anything-else'])
        );
    }

    public function test_extract_chat_message_content_falls_back_to_top_level_response(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $content = $this->invokePrivateMethod($service, 'extractChatMessageContent', [[
            'message' => [
                'content' => '',
            ],
            'response' => 'Ок',
        ]]);

        $this->assertSame('Ок', $content);
    }

    public function test_extract_chat_message_content_joins_array_chunks(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $content = $this->invokePrivateMethod($service, 'extractChatMessageContent', [[
            'message' => [
                'content' => [
                    ['text' => 'Та'],
                    ['text' => 'к'],
                ],
            ],
        ]]);

        $this->assertSame("Та\nк", $content);
    }

    public function test_parse_checklist_answer_extracts_reason_and_yes_verdict(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $parsed = $this->invokePrivateMethod($service, 'parseChecklistAnswer', [
            "У транскрипті менеджер прямо назвав причину дзвінка.\n\nТак",
        ]);

        $this->assertSame([
            'verdict' => 'Так',
            'reason' => 'У транскрипті менеджер прямо назвав причину дзвінка.',
        ], $parsed);
    }

    public function test_parse_checklist_answer_accepts_compact_unknown_verdict(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $parsed = $this->invokePrivateMethod($service, 'parseChecklistAnswer', [
            "У транскрипті немає достатнього підтвердження цього пункту.\n\nя незнаю",
        ]);

        $this->assertSame([
            'verdict' => 'Я не знаю',
            'reason' => 'У транскрипті немає достатнього підтвердження цього пункту.',
        ], $parsed);
    }

    public function test_build_item_comment_prefers_model_reason_for_unknown_answer(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $comment = $this->invokePrivateMethod($service, 'buildItemComment', [
            'Я не знаю',
            'У транскрипті мало даних для впевненої оцінки.',
        ]);

        $this->assertSame('У транскрипті мало даних для впевненої оцінки.', $comment);
    }

    public function test_parse_checklist_answer_accepts_verbose_negative_explanation_without_explicit_verdict_line(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $parsed = $this->invokePrivateMethod($service, 'parseChecklistAnswer', [
            "**Привітання клієнта:**\nУ даному діалозі **привітання клієнта відсутнє**.",
        ]);

        $this->assertSame('Ні', $parsed['verdict']);
        $this->assertStringContainsString('відсутнє', $parsed['reason']);
    }

    public function test_parse_checklist_answer_accepts_compact_nemaye_verdict(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $parsed = $this->invokePrivateMethod($service, 'parseChecklistAnswer', [
            'Немає.',
        ]);

        $this->assertSame([
            'verdict' => 'Ні',
            'reason' => '',
        ], $parsed);
    }

    public function test_parse_checklist_answer_accepts_verbose_negative_nemaye_phrase(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $parsed = $this->invokePrivateMethod($service, 'parseChecklistAnswer', [
            'У наданому діалозі немає жодних згадок про програмування.',
        ]);

        $this->assertSame('Ні', $parsed['verdict']);
        $this->assertStringContainsString('немає жодних згадок', $parsed['reason']);
    }

    public function test_parse_checklist_answer_falls_back_to_positive_for_verbose_explanation_without_negative_markers(): void
    {
        $service = new AltCallCenterChecklistEvaluator($this->settingsStub());

        $parsed = $this->invokePrivateMethod($service, 'parseChecklistAnswer', [
            'У цьому діалозі ключовим моментом є перехід до загальної пропозиції послуг.',
        ]);

        $this->assertSame('Так', $parsed['verdict']);
        $this->assertStringContainsString('ключовим моментом', $parsed['reason']);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function invokePrivateMethod(object $target, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($target, $method);

        return $reflection->invokeArgs($target, $arguments);
    }

    private function settingsStub(): AltCallCenterTranscriptionSettings
    {
        return new class extends AltCallCenterTranscriptionSettings
        {
        };
    }
}
