<?php

namespace Tests\Unit;

use App\Services\CallCenterChecklistEvaluator;
use App\Support\CallCenterTranscriptionSettings;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CallCenterChecklistEvaluatorTest extends TestCase
{
    public function test_build_checklist_evaluation_messages_are_stateless_and_include_full_transcript_once(): void
    {
        $service = new CallCenterChecklistEvaluator($this->settingsStub());
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
        $this->assertStringContainsString('Не покладайся на пам’ять попередніх повідомлень.', $messages[0]['content']);
        $this->assertStringContainsString($fullTranscript, $messages[1]['content']);
        $this->assertStringContainsString('Пункт чек-листа:', $messages[1]['content']);
        $this->assertStringContainsString($item['label'], $messages[1]['content']);
        $this->assertStringNotContainsString('assistant', json_encode($messages, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('Попередня відповідь', $messages[1]['content']);
    }

    public function test_legacy_saved_sequential_system_prompt_is_replaced_with_stateless_prompt(): void
    {
        $service = new CallCenterChecklistEvaluator($this->settingsStub());

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

    public function test_salvages_payload_when_llm_breaks_item_ids_but_keeps_order(): void
    {
        $service = new CallCenterChecklistEvaluator($this->settingsStub());
        $checklistItems = [
            ['id' => 'item_1', 'label' => 'Привітався та встановив контакт', 'max_points' => 10],
            ['id' => 'item_2', 'label' => 'Виявив потребу', 'max_points' => 10],
        ];
        $payload = [
            'checklist_name' => 'Sales QA Checklist',
            'items' => [
                [
                    'item_id' => 'criterion_1',
                    'score' => 10,
                    'comment' => 'Manager greeted the client correctly.',
                ],
                [
                    'item_id' => 'criterion_2',
                    'score' => 4,
                    'comment' => 'Need to ask one more follow-up question.',
                ],
            ],
        ];

        $salvaged = $this->invokePrivateMethod($service, 'salvageEvaluationPayload', [
            $payload,
            $checklistItems,
            'Чек-лист продажів',
        ]);

        $this->assertIsArray($salvaged);
        $this->assertSame('Чек-лист продажів', $salvaged['checklist_name']);
        $this->assertCount(2, $salvaged['items']);
        $this->assertSame('item_1', $salvaged['items'][0]['item_id']);
        $this->assertSame(10, $salvaged['items'][0]['score']);
        $this->assertStringStartsWith('Виконано:', $salvaged['items'][0]['comment']);
        $this->assertSame('item_2', $salvaged['items'][1]['item_id']);
        $this->assertSame(4, $salvaged['items'][1]['score']);
        $this->assertStringStartsWith('Частково виконано:', $salvaged['items'][1]['comment']);
    }

    public function test_salvages_payload_when_only_some_items_match(): void
    {
        $service = new CallCenterChecklistEvaluator($this->settingsStub());
        $checklistItems = [
            ['id' => 'item_1', 'label' => 'Привітався та встановив контакт', 'max_points' => 10],
            ['id' => 'item_2', 'label' => 'Виявив потребу', 'max_points' => 10],
        ];
        $payload = [
            'checklist_name' => 'Checklist',
            'items' => [
                [
                    'item_id' => 'item_1',
                    'score' => 7,
                    'comment' => 'Manager covered this point.',
                ],
            ],
        ];

        $salvaged = $this->invokePrivateMethod($service, 'salvageEvaluationPayload', [
            $payload,
            $checklistItems,
            'Чек-лист продажів',
        ]);

        $this->assertIsArray($salvaged);
        $this->assertCount(2, $salvaged['items']);
        $this->assertSame('item_1', $salvaged['items'][0]['item_id']);
        $this->assertSame(7, $salvaged['items'][0]['score']);
        $this->assertStringStartsWith('Частково виконано:', $salvaged['items'][0]['comment']);
        $this->assertSame('item_2', $salvaged['items'][1]['item_id']);
        $this->assertSame(0, $salvaged['items'][1]['score']);
        $this->assertStringStartsWith('Не виконано:', $salvaged['items'][1]['comment']);
    }

    public function test_extract_generate_response_text_falls_back_to_message_content(): void
    {
        $service = new CallCenterChecklistEvaluator($this->settingsStub());

        $content = $this->invokePrivateMethod($service, 'extractGenerateResponseText', [[
            'response' => '',
            'message' => [
                'content' => '{"checklist_name":"QA","items":[]}',
            ],
        ]]);

        $this->assertSame('{"checklist_name":"QA","items":[]}', $content);
    }

    public function test_extract_generate_response_text_joins_array_chunks(): void
    {
        $service = new CallCenterChecklistEvaluator($this->settingsStub());

        $content = $this->invokePrivateMethod($service, 'extractGenerateResponseText', [[
            'response' => [
                ['text' => '{'],
                ['text' => '"checklist_name":"QA"'],
                ['text' => '}'],
            ],
        ]]);

        $this->assertSame("{\n\"checklist_name\":\"QA\"\n}", $content);
    }

    public function test_parse_checklist_answer_accepts_verbose_negative_explanation_without_explicit_verdict_line(): void
    {
        $service = new CallCenterChecklistEvaluator($this->settingsStub());

        $parsed = $this->invokePrivateMethod($service, 'parseChecklistAnswer', [
            "**Привітання клієнта:**\nУ даному діалозі **привітання клієнта відсутнє**.",
        ]);

        $this->assertSame('Ні', $parsed['verdict']);
        $this->assertStringContainsString('відсутнє', $parsed['reason']);
    }

    public function test_parse_checklist_answer_accepts_compact_nemaye_verdict(): void
    {
        $service = new CallCenterChecklistEvaluator($this->settingsStub());

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
        $service = new CallCenterChecklistEvaluator($this->settingsStub());

        $parsed = $this->invokePrivateMethod($service, 'parseChecklistAnswer', [
            'У наданому діалозі немає жодних згадок про програмування.',
        ]);

        $this->assertSame('Ні', $parsed['verdict']);
        $this->assertStringContainsString('немає жодних згадок', $parsed['reason']);
    }

    public function test_parse_checklist_answer_falls_back_to_positive_for_verbose_explanation_without_negative_markers(): void
    {
        $service = new CallCenterChecklistEvaluator($this->settingsStub());

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

    private function settingsStub(): CallCenterTranscriptionSettings
    {
        return new class extends CallCenterTranscriptionSettings
        {
        };
    }
}
