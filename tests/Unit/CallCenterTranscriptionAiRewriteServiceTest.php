<?php

namespace Tests\Unit;

use App\Services\CallCenterTranscriptionAiRewriteService;
use App\Support\CallCenterTranscriptionSettings;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CallCenterTranscriptionAiRewriteServiceTest extends TestCase
{
    public function test_rewrite_request_payload_keeps_ollama_model_warm_briefly_after_response(): void
    {
        $service = new CallCenterTranscriptionAiRewriteService();
        $method = new ReflectionMethod($service, 'prepareRewriteContext');

        $context = $method->invokeArgs($service, [
            'Менеджер: Добрий день.',
            'Виправ помилки.',
            'qwen3.5:9b',
            $this->settingsStub(),
            [],
        ]);

        $this->assertSame('15s', $context['request_payload']['keep_alive']);
    }

    public function test_streamed_generate_chunks_preserve_spaces_newlines_and_speaker_text(): void
    {
        $service = new CallCenterTranscriptionAiRewriteService();
        $state = [
            'thinking' => '',
            'response' => '',
            'done' => null,
            'thinking_started' => false,
            'response_started' => false,
        ];
        $streamMethod = new ReflectionMethod($service, 'consumeOllamaStreamLine');

        foreach ([
            ['response' => '[00:00.720 - 00:03.040] Менеджер: Добрий', 'done' => false],
            ['response' => ' день', 'done' => false],
            ['response' => "\n[00:03.960 - 00:13.760] Клієнт: мені", 'done' => false],
            ['response' => ' потрібно', 'done' => false],
            ['response' => "\n[00:14.660 - 00:20.260] Менеджер: ", 'done' => false],
            ['response' => 'Одну секундочку.', 'done' => false],
            ['response' => '', 'done' => true],
        ] as $chunk) {
            $line = json_encode($chunk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $streamMethod->invokeArgs($service, [$line, &$state, null]);
        }

        $expected = "[00:00.720 - 00:03.040] Менеджер: Добрий день\n"
            ."[00:03.960 - 00:13.760] Клієнт: мені потрібно\n"
            .'[00:14.660 - 00:20.260] Менеджер: Одну секундочку.';

        $this->assertSame($expected, $state['response']);
    }

    public function test_streamed_and_non_stream_text_extraction_match_for_same_text(): void
    {
        $service = new CallCenterTranscriptionAiRewriteService();
        $state = [
            'thinking' => '',
            'response' => '',
            'done' => null,
            'thinking_started' => false,
            'response_started' => false,
        ];
        $streamMethod = new ReflectionMethod($service, 'consumeOllamaStreamLine');
        $extractMethod = new ReflectionMethod($service, 'extractResponseText');

        $fullText = "[00:00] Менеджер: Добрий день\n[00:03] Клієнт: мені потрібно рахунок.";

        foreach ([
            ['response' => '[00:00] Менеджер: Добрий', 'done' => false],
            ['response' => " день\n[00:03] Клієнт: мені", 'done' => false],
            ['response' => ' потрібно рахунок.', 'done' => false],
            ['response' => '', 'done' => true],
        ] as $chunk) {
            $line = json_encode($chunk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $streamMethod->invokeArgs($service, [$line, &$state, null]);
        }

        $nonStreamText = $extractMethod->invokeArgs($service, [[
            'response' => $fullText,
        ]]);

        $this->assertSame($fullText, $nonStreamText);
        $this->assertSame($nonStreamText, $state['response']);
    }

    public function test_decode_corrections_accepts_raw_object_sequence_without_outer_array(): void
    {
        $service = new CallCenterTranscriptionAiRewriteService();
        $method = new ReflectionMethod($service, 'decodeCorrectionsFromResponse');

        $corrections = $method->invokeArgs($service, [
            '{"original":"зрозумила","replacement":"зрозуміла"},'
            .'{"original":"вы","replacement":"ви"},'
            .'{"original":"клино-хомутове","replacement":"кліно-хомутове"}',
        ]);

        $this->assertSame([
            [
                'original' => 'клино-хомутове',
                'replacement' => 'кліно-хомутове',
            ],
            [
                'original' => 'зрозумила',
                'replacement' => 'зрозуміла',
            ],
            [
                'original' => 'вы',
                'replacement' => 'ви',
            ],
        ], $corrections);
    }

    public function test_decode_corrections_salvages_objects_from_truncated_response(): void
    {
        $service = new CallCenterTranscriptionAiRewriteService();
        $method = new ReflectionMethod($service, 'decodeCorrectionsFromResponse');

        $corrections = $method->invokeArgs($service, [
            'вас","replacement":"я вас"},'
            .'{"original":"зрозумила","replacement":"зрозуміла"},'
            .'{"original":"орештування","replacement":"орештування"}',
        ]);

        $this->assertSame([
            [
                'original' => 'зрозумила',
                'replacement' => 'зрозуміла',
            ],
        ], $corrections);
    }

    private function settingsStub(): CallCenterTranscriptionSettings
    {
        return new class extends CallCenterTranscriptionSettings
        {
            public function llmProvider(): string
            {
                return 'ollama';
            }

            public function llmApiUrl(): string
            {
                return 'http://ollama.test';
            }

            public function llmModel(): string
            {
                return 'qwen3.5:9b';
            }

            public function llmThinkingEnabled(): bool
            {
                return false;
            }

            public function llmTemperature(): float
            {
                return 0.2;
            }

            public function llmNumCtx(): int
            {
                return 4096;
            }

            public function llmTopK(): int
            {
                return 40;
            }

            public function llmTopP(): float
            {
                return 0.9;
            }

            public function llmRepeatPenalty(): float
            {
                return 1.1;
            }

            public function llmSeed(): ?int
            {
                return null;
            }

            public function llmNumPredict(): int
            {
                return 1500;
            }

            public function llmTimeoutSeconds(): int
            {
                return 600;
            }
        };
    }
}
