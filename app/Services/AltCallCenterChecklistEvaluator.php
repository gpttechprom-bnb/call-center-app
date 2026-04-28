<?php

namespace App\Services;

use App\Support\AltCallCenterTranscriptionSettings;
use App\Support\CallCenterLlmPrompts;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AltCallCenterChecklistEvaluator
{
    public const SCENARIO_STATELESS_SINGLE_ITEM = 'stateless_single_item';
    public const SCENARIO_SEQUENTIAL_CHAT = 'sequential_chat';

    private const CHECKLIST_REQUEST_PAUSE_MS = 2000;
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_RETRY_BASE_MS = 3000;
    private const RATE_LIMIT_RETRY_MAX_MS = 30000;
    private const TRANSIENT_CONNECTION_MAX_ATTEMPTS = 3;
    private const TRANSIENT_CONNECTION_RETRY_BASE_MS = 2000;
    private const TRANSIENT_CONNECTION_RETRY_MAX_MS = 10000;

    public function __construct(
        protected readonly AltCallCenterTranscriptionSettings $settings,
    ) {
    }

    /**
     * @param array<string, mixed> $transcription
     * @param array<string, mixed> $checklist
     * @param callable|null $reporter
     * @return array<string, mixed>
     */
    public function evaluate(
        array $transcription,
        array $checklist,
        ?callable $reporter = null,
        array $llmSettings = [],
    ): array
    {
        return $this->evaluateWithTimeout(
            $transcription,
            $checklist,
            $this->resolveTimeoutSeconds($llmSettings, $this->settings->llmTimeoutSeconds()),
            $reporter,
            $llmSettings,
        );
    }

    /**
     * @param array<string, mixed> $transcription
     * @param array<string, mixed> $checklist
     * @param callable|null $reporter
     * @return array<string, mixed>
     */
    public function evaluateInBackground(
        array $transcription,
        array $checklist,
        ?callable $reporter = null,
        array $llmSettings = [],
    ): array
    {
        return $this->evaluateWithTimeout(
            $transcription,
            $checklist,
            $this->resolveTimeoutSeconds($llmSettings, $this->settings->llmBackgroundTimeoutSeconds()),
            $reporter,
            $llmSettings,
        );
    }

    /**
     * @param array<string, mixed> $transcription
     * @param array<string, mixed> $checklist
     * @param callable|null $reporter
     * @return array<string, mixed>
     */
    private function evaluateWithTimeout(
        array $transcription,
        array $checklist,
        int $timeout,
        ?callable $reporter = null,
        array $llmSettings = [],
    ): array {
        if (! (bool) config('call_center.evaluation.enabled', true)) {
            throw new RuntimeException('Оцінювання дзвінків через LLM зараз вимкнено в конфігурації.');
        }

        $items = $this->normalizeChecklistItems($checklist['items'] ?? []);
        if ($items === []) {
            throw new RuntimeException('У чек-листі немає жодного пункту для оцінювання.');
        }

        $transcriptForEvaluation = $this->resolveTranscriptForEvaluation($transcription);
        if ($transcriptForEvaluation === '') {
            throw new RuntimeException('Транскрибація порожня, тому оцінити дзвінок за чек-листом не вдалося.');
        }

        $checklistName = trim((string) ($checklist['name'] ?? '')) ?: 'Чек-лист';
        $trace = [
            'thinking_sections' => [],
            'response_sections' => [],
        ];
        $scenario = $this->evaluationScenario($llmSettings);
        $normalizedItems = match ($scenario) {
            self::SCENARIO_SEQUENTIAL_CHAT => $this->evaluateChecklistItemsSequentialChat(
                $transcriptForEvaluation,
                $items,
                $checklist,
                $timeout,
                $reporter,
                $llmSettings,
                $trace,
            ),
            default => $this->evaluateChecklistItemsStateless(
                $transcriptForEvaluation,
                $items,
                $checklist,
                $timeout,
                $reporter,
                $llmSettings,
                $trace,
            ),
        };

        $earnedScore = $this->calculateEarnedScore($normalizedItems);
        $totalPoints = $this->calculateTotalPoints($normalizedItems);
        $overallPercent = $this->calculateOverallPercent($normalizedItems);

        return [
            'score' => $earnedScore,
            'totalPoints' => $totalPoints,
            'scorePercent' => $overallPercent,
            'checklistId' => (string) ($checklist['id'] ?? ''),
            'checklistName' => $checklistName,
            'strongSide' => $this->buildStrongSide($normalizedItems),
            'focus' => $this->buildFocus($normalizedItems),
            'summary' => $this->buildSummary($normalizedItems),
            'provider' => 'ollama',
            'model' => $this->model($llmSettings),
            'modelParams' => $this->resolvedModelParams($llmSettings, $timeout),
            'scenario' => $scenario,
            'items' => $normalizedItems,
        ];
    }

    /**
     * @param array<int, array{id:string,label:string,max_points:int}> $items
     * @param array<string, array<int, string>> $trace
     * @return array<int, array<string, mixed>>
     */
    private function evaluateChecklistItemsStateless(
        string $transcriptForEvaluation,
        array $items,
        array $checklist,
        int $timeout,
        ?callable $reporter,
        array $llmSettings,
        array &$trace,
    ): array {
        $this->emitReporter($reporter, 'log', [
            'channel' => 'status',
            'message' => 'Підготовлено '.count($items).' пункт(ів) чек-листа. Запускаємо незалежні stateless-запити: повний транскрипт + один пункт у кожному запиті.',
        ]);
        $this->emitReporter($reporter, 'log', [
            'channel' => 'status',
            'message' => 'Між пунктами додаємо коротку технічну паузу, а при 429 / Too Many Attempts backend автоматично зачекає і повторить запит.',
        ]);

        $normalizedItems = [];
        $itemsCount = count($items);

        foreach ($items as $index => $item) {
            $position = $index + 1;
            if ($index > 0) {
                $this->pauseBetweenChecklistRequests();
            }

            $normalizedItems[] = $this->evaluateChecklistItemStateless(
                $transcriptForEvaluation,
                $item,
                $checklist,
                $position,
                $itemsCount,
                $timeout,
                $reporter,
                $llmSettings,
                $trace,
            );
        }

        $this->emitReporter($reporter, 'phase', [
            'phase' => 'stateless_completed',
        ]);
        $this->emitReporter($reporter, 'log', [
            'channel' => 'success',
            'message' => 'Усі пункти чек-листа оцінено незалежними запитами. Формуємо підсумкові бали.',
        ]);

        return $normalizedItems;
    }

    /**
     * @param array<int, array{id:string,label:string,max_points:int}> $items
     * @param array<string, array<int, string>> $trace
     * @return array<int, array<string, mixed>>
     */
    private function evaluateChecklistItemsSequentialChat(
        string $transcriptForEvaluation,
        array $items,
        array $checklist,
        int $timeout,
        ?callable $reporter,
        array $llmSettings,
        array &$trace,
    ): array {
        $this->emitReporter($reporter, 'log', [
            'channel' => 'status',
            'message' => 'Підготовлено '.count($items).' пункт(ів) чек-листа. Запускаємо послідовний чат: спочатку один раз передаємо транскрипт, далі ставимо пункти по черзі.',
        ]);
        $this->emitReporter($reporter, 'log', [
            'channel' => 'status',
            'message' => 'У цьому сценарії модель працює в межах одного діалогу та памʼятає попередні відповіді в межах поточного завдання.',
        ]);

        $messages = $this->buildSequentialConversationBootstrapMessages($transcriptForEvaluation, $checklist, $llmSettings);

        $this->emitReporter($reporter, 'phase', [
            'phase' => 'sequential_bootstrap',
        ]);
        $this->emitReporter($reporter, 'prompt', [
            'system_prompt' => $this->extractSystemPrompt($messages),
            'prompt' => $this->buildChatPromptPreview($messages),
        ]);

        $bootstrapReply = $this->requestChat($messages, $timeout, $reporter, $llmSettings);
        $bootstrapAnswer = trim($this->extractChatMessageContent($bootstrapReply));
        $this->appendChecklistTrace($trace, 'CONTEXT', $bootstrapAnswer, $this->extractChatThinking($bootstrapReply), $reporter);
        if ($bootstrapAnswer !== '') {
            $messages[] = [
                'role' => 'assistant',
                'content' => $bootstrapAnswer,
            ];
        }

        $normalizedItems = [];
        $itemsCount = count($items);

        foreach ($items as $index => $item) {
            $position = $index + 1;
            if ($index > 0) {
                $this->pauseBetweenChecklistRequests();
            }

            $normalizedItems[] = $this->evaluateChecklistItemSequentialChat(
                $messages,
                $item,
                $position,
                $itemsCount,
                $timeout,
                $reporter,
                $llmSettings,
                $trace,
            );
        }

        $this->emitReporter($reporter, 'phase', [
            'phase' => 'sequential_completed',
        ]);
        $this->emitReporter($reporter, 'log', [
            'channel' => 'success',
            'message' => 'Усі пункти чек-листа оцінено в межах одного послідовного чату. Формуємо підсумкові бали.',
        ]);

        return $normalizedItems;
    }

    /**
     * @param array{id:string,label:string,max_points:int} $item
     * @param array<string, array<int, string>> $trace
     * @return array<string, mixed>
     */
    private function evaluateChecklistItemStateless(
        string $fullTranscript,
        array $item,
        array $checklist,
        int $position,
        int $total,
        int $timeout,
        ?callable $reporter,
        array $llmSettings,
        array &$trace,
    ): array {
        $label = $item['label'];
        $maxPoints = $item['max_points'];
        $phase = "stateless_question_{$position}_of_{$total}";

        $this->emitReporter($reporter, 'phase', [
            'phase' => $phase,
        ]);

        $messages = $this->buildChecklistEvaluationMessages($fullTranscript, $item, $checklist, $llmSettings);
        $this->emitReporter($reporter, 'prompt', [
            'system_prompt' => $this->extractSystemPrompt($messages),
            'prompt' => $this->buildChatPromptPreview($messages),
        ]);
        $this->emitReporter($reporter, 'log', [
            'channel' => 'status',
            'message' => "Пункт {$position}/{$total}: надсилаємо незалежний запит із повним транскриптом і тільки цим пунктом «{$label}».",
        ]);

        $answerReply = $this->requestChat($messages, $timeout, $reporter, $llmSettings);
        $rawAnswer = trim($this->extractChatMessageContent($answerReply));
        $this->appendChecklistTrace($trace, "Q{$position}", $rawAnswer, $this->extractChatThinking($answerReply), $reporter);
        $parsedAnswer = $this->parseChecklistAnswer($rawAnswer);

        if ($parsedAnswer === null) {
            $this->emitReporter($reporter, 'phase', [
                'phase' => "stateless_retry_{$position}_of_{$total}",
            ]);
            $this->emitReporter($reporter, 'log', [
                'channel' => 'warning',
                'message' => "Qwen повернула нестандартну відповідь на пункт {$position}: «{$rawAnswer}». Повторюємо незалежний запит з повним транскриптом і цим самим пунктом.",
            ]);

            $retryMessages = $this->buildChecklistEvaluationMessages(
                $fullTranscript,
                $item,
                $checklist,
                $llmSettings,
                $rawAnswer,
            );
            $this->emitReporter($reporter, 'prompt', [
                'system_prompt' => $this->extractSystemPrompt($retryMessages),
                'prompt' => $this->buildChatPromptPreview($retryMessages),
            ]);

            $retryReply = $this->requestChat($retryMessages, $timeout, $reporter, $llmSettings);
            $rawAnswer = trim($this->extractChatMessageContent($retryReply));
            $this->appendChecklistTrace($trace, "Q{$position}-RETRY", $rawAnswer, $this->extractChatThinking($retryReply), $reporter);
            $parsedAnswer = $this->parseChecklistAnswer($rawAnswer);
        }

        if ($parsedAnswer === null) {
            throw new RuntimeException(
                "Qwen не змогла повернути валідний формат відповіді для пункту {$position}: {$label}."
            );
        }

        $verdict = $parsedAnswer['verdict'];
        $reason = $parsedAnswer['reason'];
        $score = $verdict === 'Так' ? $maxPoints : 0;

        $this->emitReporter($reporter, 'log', [
            'channel' => $verdict === 'Так' ? 'success' : 'warning',
            'message' => $verdict === 'Так'
                ? "Пункт {$position}/{$total}: модель відповіла «Так». Нараховано {$score}/{$maxPoints}."
                : ($verdict === 'Я не знаю'
                    ? "Пункт {$position}/{$total}: модель відповіла «я незнаю». Нараховано 0/{$maxPoints}."
                    : "Пункт {$position}/{$total}: модель відповіла «Ні». Нараховано 0/{$maxPoints}."),
        ]);

        return [
            'id' => $item['id'],
            'label' => $label,
            'max_points' => $maxPoints,
            'score' => $score,
            'percentage' => $score > 0 ? 100 : 0,
            'answer' => $verdict,
            'comment' => $this->buildItemComment($verdict, $reason),
        ];
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     * @param array{id:string,label:string,max_points:int} $item
     * @param array<string, array<int, string>> $trace
     * @return array<string, mixed>
     */
    private function evaluateChecklistItemSequentialChat(
        array &$messages,
        array $item,
        int $position,
        int $total,
        int $timeout,
        ?callable $reporter,
        array $llmSettings,
        array &$trace,
    ): array {
        $label = $item['label'];
        $maxPoints = $item['max_points'];
        $phase = "sequential_question_{$position}_of_{$total}";

        $this->emitReporter($reporter, 'phase', [
            'phase' => $phase,
        ]);

        $questionMessage = [
            'role' => 'user',
            'content' => $this->buildSequentialChecklistQuestionPrompt($item),
        ];
        $messages[] = $questionMessage;
        $this->emitReporter($reporter, 'prompt', [
            'system_prompt' => $this->extractSystemPrompt($messages),
            'prompt' => $this->buildChatPromptPreview($messages),
        ]);
        $this->emitReporter($reporter, 'log', [
            'channel' => 'status',
            'message' => "Пункт {$position}/{$total}: ставимо в поточному чаті питання «{$label}».",
        ]);

        $answerReply = $this->requestChat($messages, $timeout, $reporter, $llmSettings);
        $rawAnswer = trim($this->extractChatMessageContent($answerReply));
        $messages[] = [
            'role' => 'assistant',
            'content' => $rawAnswer,
        ];
        $this->appendChecklistTrace($trace, "Q{$position}", $rawAnswer, $this->extractChatThinking($answerReply), $reporter);
        $parsedAnswer = $this->parseChecklistAnswer($rawAnswer);

        if ($parsedAnswer === null) {
            $this->emitReporter($reporter, 'phase', [
                'phase' => "sequential_retry_{$position}_of_{$total}",
            ]);
            $this->emitReporter($reporter, 'log', [
                'channel' => 'warning',
                'message' => "Модель повернула нестандартну відповідь на пункт {$position}: «{$rawAnswer}». Повторюємо питання в тому ж чаті з уточненням формату.",
            ]);

            $retryMessage = [
                'role' => 'user',
                'content' => $this->buildSequentialChecklistQuestionPrompt($item, $rawAnswer),
            ];
            $messages[] = $retryMessage;
            $this->emitReporter($reporter, 'prompt', [
                'system_prompt' => $this->extractSystemPrompt($messages),
                'prompt' => $this->buildChatPromptPreview($messages),
            ]);

            $retryReply = $this->requestChat($messages, $timeout, $reporter, $llmSettings);
            $rawAnswer = trim($this->extractChatMessageContent($retryReply));
            $messages[] = [
                'role' => 'assistant',
                'content' => $rawAnswer,
            ];
            $this->appendChecklistTrace($trace, "Q{$position}-RETRY", $rawAnswer, $this->extractChatThinking($retryReply), $reporter);
            $parsedAnswer = $this->parseChecklistAnswer($rawAnswer);
        }

        if ($parsedAnswer === null) {
            throw new RuntimeException(
                "Qwen не змогла повернути валідний формат відповіді для пункту {$position}: {$label}."
            );
        }

        $verdict = $parsedAnswer['verdict'];
        $reason = $parsedAnswer['reason'];
        $score = $verdict === 'Так' ? $maxPoints : 0;

        $this->emitReporter($reporter, 'log', [
            'channel' => $verdict === 'Так' ? 'success' : 'warning',
            'message' => $verdict === 'Так'
                ? "Пункт {$position}/{$total}: модель відповіла «Так». Нараховано {$score}/{$maxPoints}."
                : ($verdict === 'Я не знаю'
                    ? "Пункт {$position}/{$total}: модель відповіла «я незнаю». Нараховано 0/{$maxPoints}."
                    : "Пункт {$position}/{$total}: модель відповіла «Ні». Нараховано 0/{$maxPoints}."),
        ]);

        return [
            'id' => $item['id'],
            'label' => $label,
            'max_points' => $maxPoints,
            'score' => $score,
            'percentage' => $score > 0 ? 100 : 0,
            'answer' => $verdict,
            'comment' => $this->buildItemComment($verdict, $reason),
        ];
    }

    /**
     * @param array{id:string,label:string,max_points:int} $checklistItem
     * @return array<int, array{role:string,content:string}>
     */
    private function buildChecklistEvaluationMessages(
        string $fullTranscript,
        array $checklistItem,
        array $checklist = [],
        array $llmSettings = [],
        string $previousInvalidReply = '',
    ): array {
        return [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($checklist, $llmSettings),
            ],
            [
                'role' => 'user',
                'content' => $this->buildChecklistEvaluationUserPrompt($fullTranscript, $checklistItem, $previousInvalidReply),
            ],
        ];
    }

    /**
     * @return array<int, array{role:string,content:string}>
     */
    private function buildSequentialConversationBootstrapMessages(
        string $fullTranscript,
        array $checklist = [],
        array $llmSettings = [],
    ): array {
        return [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($checklist, $llmSettings),
            ],
            [
                'role' => 'user',
                'content' => $this->buildSequentialConversationBootstrapPrompt($fullTranscript, $checklist),
            ],
        ];
    }

    /**
     * @param array{id:string,label:string,max_points:int} $checklistItem
     */
    private function buildChecklistEvaluationUserPrompt(
        string $fullTranscript,
        array $checklistItem,
        string $previousInvalidReply = '',
    ): string {
        $retryBlock = trim($previousInvalidReply) !== ''
            ? "\n\nПопередня відповідь була невалідною:\n{$previousInvalidReply}\n\nПовтори відповідь строго у потрібному форматі, але знову аналізуй тільки транскрипт і пункт нижче."
            : '';

        return <<<PROMPT
Ось транскрипт розмови менеджера з клієнтом:

{$fullTranscript}

Пункт чек-листа:
{$checklistItem['label']}{$retryBlock}
PROMPT;
    }

    private function buildSequentialConversationBootstrapPrompt(string $fullTranscript, array $checklist = []): string
    {
        $checklistName = trim((string) ($checklist['name'] ?? ''));
        $checklistLabel = $checklistName !== '' ? $checklistName : 'цей чек-лист';

        return <<<PROMPT
Ось повний транскрипт розмови менеджера з клієнтом для оцінювання за чек-листом «{$checklistLabel}»:

{$fullTranscript}

Запамʼятай цей транскрипт у межах поточного чату.
Коли будеш готовий оцінювати окремі пункти по черзі, відповідай тільки словом: ГОТОВО
PROMPT;
    }

    /**
     * @param array{id:string,label:string,max_points:int} $checklistItem
     */
    private function buildSequentialChecklistQuestionPrompt(
        array $checklistItem,
        string $previousInvalidReply = '',
    ): string {
        $retryBlock = trim($previousInvalidReply) !== ''
            ? "\n\nПопередня відповідь була невалідною:\n{$previousInvalidReply}\n\nПовтори відповідь строго у потрібному форматі для цього ж пункту."
            : '';

        return <<<PROMPT
Оціни наступний пункт чек-листа тільки за транскриптом, який уже є в цьому чаті.

Пункт чек-листа:
{$checklistItem['label']}{$retryBlock}
PROMPT;
    }

    /**
     * @param array<string, array<int, string>> $trace
     */
    private function appendChecklistTrace(
        array &$trace,
        string $traceLabel,
        string $replyContent,
        string $replyThinking,
        ?callable $reporter,
    ): void {
        if ($replyThinking !== '') {
            $trace['thinking_sections'][] = "=== {$traceLabel} ===\n".$replyThinking;
            $this->emitReporter($reporter, 'thinking', [
                'text' => implode("\n\n", $trace['thinking_sections']),
            ]);
        }

        $trace['response_sections'][] = "=== {$traceLabel} ===\n".$replyContent;
        $this->emitReporter($reporter, 'response', [
            'text' => implode("\n\n", $trace['response_sections']),
        ]);
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     * @return array<string, mixed>
     */
    protected function requestChat(
        array $messages,
        int $timeout,
        ?callable $reporter = null,
        array $llmSettings = [],
    ): array
    {
        return $this->executeChatRequest(
            $messages,
            $timeout,
            $reporter,
            $llmSettings,
        );
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     * @return array<string, mixed>
     */
    private function executeChatRequest(
        array $messages,
        int $timeout,
        ?callable $reporter = null,
        array $llmSettings = [],
    ): array
    {
        if ($this->provider($llmSettings) !== 'ollama') {
            return $this->executeCloudChatRequest($messages, $timeout, $reporter, $llmSettings);
        }

        $url = rtrim($this->settings->llmApiUrl(), '/');

        $attempt = 0;

        while (true) {
            $attempt += 1;

            try {
                $response = $this->makeOllamaRequest($timeout)
                    ->post($url.'/api/chat', [
                        'model' => $this->model($llmSettings),
                        'messages' => $messages,
                        'stream' => false,
                        'think' => false,
                        'options' => $this->ollamaOptions($llmSettings),
                        'keep_alive' => '15s',
                    ]);
            } catch (ConnectionException $exception) {
                if ($this->shouldRetryTransientConnection($exception, $attempt)) {
                    $this->waitBeforeRetryingTransientConnection($attempt, $reporter);

                    continue;
                }

                throw $this->mapOllamaConnectionException($exception, $timeout);
            } catch (Throwable) {
                throw new RuntimeException('Сталася внутрішня помилка під час звернення до Ollama в stateless chat-режимі.');
            }

            if (! $response->successful()) {
                $error = $this->extractOllamaErrorMessage($response);

                if ($this->shouldRetryRateLimitedResponse($response, $error, $attempt)) {
                    $this->waitBeforeRetryingRateLimitedRequest($attempt, $response, $reporter);

                    continue;
                }

                throw new RuntimeException(
                    $error !== ''
                        ? $error
                        : 'Ollama повернув помилку під час stateless-оцінювання пункту чек-листа.'
                );
            }

            $decoded = $response->json();
            if (! is_array($decoded)) {
                throw new RuntimeException('Ollama повернув невалідну chat-відповідь.');
            }

            return $decoded;
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractChatMessageContent(array $response): string
    {
        foreach ([
            $response['message']['content'] ?? null,
            $response['choices'][0]['message']['content'] ?? null,
            $response['content'][0]['text'] ?? null,
            $response['candidates'][0]['content']['parts'][0]['text'] ?? null,
            $response['response'] ?? null,
            $response['content'] ?? null,
        ] as $candidate) {
            $normalized = $this->normalizeOllamaTextField($candidate);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractChatThinking(array $response): string
    {
        foreach ([
            $response['message']['thinking'] ?? null,
            $response['thinking'] ?? null,
        ] as $candidate) {
            $normalized = $this->normalizeOllamaTextField($candidate);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function normalizeOllamaTextField(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (! is_array($value)) {
            return '';
        }

        $chunks = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $chunk = trim($item);

                if ($chunk !== '') {
                    $chunks[] = $chunk;
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $chunk = trim((string) ($item['text'] ?? $item['content'] ?? ''));

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
        }

        return trim(implode("\n", $chunks));
    }

    private function extractOllamaErrorMessage(Response $response): string
    {
        $payload = $response->json();

        if (is_array($payload)) {
            $error = trim((string) ($payload['error'] ?? $payload['message'] ?? ''));

            if ($error !== '') {
                return $error;
            }
        }

        return trim($response->body());
    }

    private function pauseBetweenChecklistRequests(): void
    {
        if (self::CHECKLIST_REQUEST_PAUSE_MS <= 0) {
            return;
        }

        usleep(self::CHECKLIST_REQUEST_PAUSE_MS * 1000);
    }

    private function shouldRetryRateLimitedResponse(Response $response, string $error, int $attempt): bool
    {
        if ($attempt >= self::RATE_LIMIT_MAX_ATTEMPTS) {
            return false;
        }

        if ($response->status() === 429) {
            return true;
        }

        return $this->isRateLimitMessage($error);
    }

    private function waitBeforeRetryingRateLimitedRequest(
        int $attempt,
        ?Response $response = null,
        ?callable $reporter = null,
    ): void {
        $delayMs = $this->rateLimitRetryDelayMilliseconds($attempt, $response);
        $seconds = number_format($delayMs / 1000, 1, '.', '');

        $this->emitReporter($reporter, 'log', [
            'channel' => 'warning',
            'message' => 'LLM API тимчасово обмежив частоту запитів (429 / Too Many Attempts). '
                ."Чекаємо {$seconds} с і повторюємо спробу ".min($attempt + 1, self::RATE_LIMIT_MAX_ATTEMPTS).'/'.self::RATE_LIMIT_MAX_ATTEMPTS.'.',
        ]);

        usleep($delayMs * 1000);
    }

    private function rateLimitRetryDelayMilliseconds(int $attempt, ?Response $response = null): int
    {
        $retryAfterMs = $response !== null ? $this->extractRetryAfterMilliseconds($response) : null;
        if ($retryAfterMs !== null) {
            return $this->normalizeRetryDelayMilliseconds(
                $retryAfterMs,
                self::RATE_LIMIT_RETRY_BASE_MS,
                self::RATE_LIMIT_RETRY_MAX_MS,
            );
        }

        $delayMs = (self::RATE_LIMIT_RETRY_BASE_MS * (2 ** max(0, $attempt - 1))) + random_int(0, 750);

        return $this->normalizeRetryDelayMilliseconds(
            $delayMs,
            self::RATE_LIMIT_RETRY_BASE_MS,
            self::RATE_LIMIT_RETRY_MAX_MS,
        );
    }

    private function shouldRetryTransientConnection(ConnectionException $exception, int $attempt): bool
    {
        if ($attempt >= self::TRANSIENT_CONNECTION_MAX_ATTEMPTS) {
            return false;
        }

        $message = mb_strtolower($exception->getMessage(), 'UTF-8');
        if (
            str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'curl error 28')
            || str_contains($message, 'could not resolve host')
            || str_contains($message, 'name or service not known')
        ) {
            return false;
        }

        return true;
    }

    private function waitBeforeRetryingTransientConnection(int $attempt, ?callable $reporter = null): void
    {
        $delayMs = $this->transientConnectionRetryDelayMilliseconds($attempt);
        $seconds = number_format($delayMs / 1000, 1, '.', '');

        $this->emitReporter($reporter, 'log', [
            'channel' => 'warning',
            'message' => 'Підключення до LLM API коротко обірвалося або сервіс був перевантажений. '
                ."Чекаємо {$seconds} с і повторюємо спробу ".min($attempt + 1, self::TRANSIENT_CONNECTION_MAX_ATTEMPTS).'/'.self::TRANSIENT_CONNECTION_MAX_ATTEMPTS.'.',
        ]);

        usleep($delayMs * 1000);
    }

    private function transientConnectionRetryDelayMilliseconds(int $attempt): int
    {
        $delayMs = (self::TRANSIENT_CONNECTION_RETRY_BASE_MS * (2 ** max(0, $attempt - 1))) + random_int(0, 500);

        return $this->normalizeRetryDelayMilliseconds(
            $delayMs,
            self::TRANSIENT_CONNECTION_RETRY_BASE_MS,
            self::TRANSIENT_CONNECTION_RETRY_MAX_MS,
        );
    }

    private function extractRetryAfterMilliseconds(Response $response): ?int
    {
        $retryAfter = trim((string) $response->header('Retry-After'));
        if ($retryAfter === '') {
            return null;
        }

        if (is_numeric($retryAfter)) {
            return (int) round((float) $retryAfter * 1000);
        }

        $timestamp = strtotime($retryAfter);
        if ($timestamp === false) {
            return null;
        }

        return max(0, ($timestamp - time()) * 1000);
    }

    private function normalizeRetryDelayMilliseconds(int $delayMs, int $min, int $max): int
    {
        return max($min, min($max, $delayMs));
    }

    private function isRateLimitMessage(string $message): bool
    {
        $normalized = mb_strtolower(trim($message), 'UTF-8');
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'too many attempts')
            || str_contains($normalized, 'too many requests')
            || str_contains($normalized, 'rate limit')
            || str_contains($normalized, 'retry later')
            || str_contains($normalized, '429');
    }

    private function makeOllamaRequest(int $timeout)
    {
        $request = Http::connectTimeout(5)
            ->timeout($timeout)
            ->acceptJson();

        if ($this->settings->hasLlmApiKey()) {
            $request = $request->withToken($this->settings->llmApiKey());
        }

        return $request;
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     * @return array<string, mixed>
     */
    private function executeCloudChatRequest(
        array $messages,
        int $timeout,
        ?callable $reporter = null,
        array $llmSettings = [],
    ): array {
        $provider = $this->provider($llmSettings);
        $apiKey = $this->settings->llmProviderApiKey($provider);

        if ($apiKey === null) {
            throw new RuntimeException('Додайте API key для провайдера '.$provider.' у налаштуваннях.');
        }

        $attempt = 0;
        while (true) {
            $attempt += 1;

            try {
                $response = match ($provider) {
                    'anthropic' => $this->makeCloudRequest($timeout, $apiKey)
                        ->withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01'])
                        ->post(rtrim($this->settings->llmApiUrl(), '/').'/messages', $this->anthropicChatPayload($messages, $llmSettings)),
                    'gemini' => $this->makeCloudRequest($timeout, $apiKey)
                        ->post(rtrim($this->settings->llmApiUrl(), '/').'/models/'.$this->model($llmSettings).':generateContent?key='.$apiKey, $this->geminiChatPayload($messages, $llmSettings)),
                    default => $this->makeCloudRequest($timeout, $apiKey)
                        ->withToken($apiKey)
                        ->post(rtrim($this->settings->llmApiUrl(), '/').'/chat/completions', $this->openAiChatPayload($messages, $llmSettings)),
                };
            } catch (ConnectionException $exception) {
                if ($this->shouldRetryTransientConnection($exception, $attempt)) {
                    $this->waitBeforeRetryingTransientConnection($attempt, $reporter);

                    continue;
                }

                throw new RuntimeException('Не вдалося підключитися до платного LLM-провайдера.');
            } catch (Throwable) {
                throw new RuntimeException('Сталася внутрішня помилка під час звернення до платного LLM-провайдера.');
            }

            if (! $response->successful()) {
                $error = $this->extractOllamaErrorMessage($response);

                if ($this->shouldRetryRateLimitedResponse($response, $error, $attempt)) {
                    $this->waitBeforeRetryingRateLimitedRequest($attempt, $response, $reporter);

                    continue;
                }

                throw new RuntimeException($error !== '' ? $error : 'Платний LLM-провайдер повернув помилку.');
            }

            $decoded = $response->json();
            if (! is_array($decoded)) {
                throw new RuntimeException('Платний LLM-провайдер повернув невалідну відповідь.');
            }

            return $decoded;
        }
    }

    private function makeCloudRequest(int $timeout, string $apiKey)
    {
        return Http::connectTimeout(5)
            ->timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
                'HTTP-Referer' => config('app.url', 'http://localhost'),
                'X-Title' => config('app.name', 'Call Center'),
            ]);
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     * @return array<string, mixed>
     */
    private function openAiChatPayload(array $messages, array $llmSettings): array
    {
        return [
            'model' => $this->model($llmSettings),
            'messages' => $messages,
            'temperature' => $this->ollamaOptions($llmSettings)['temperature'] ?? 0.2,
        ];
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     * @return array<string, mixed>
     */
    private function anthropicChatPayload(array $messages, array $llmSettings): array
    {
        $system = '';
        $chatMessages = [];

        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');
            $content = (string) ($message['content'] ?? '');
            if ($role === 'system') {
                $system = trim($system."\n\n".$content);
                continue;
            }

            $chatMessages[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        return [
            'model' => $this->model($llmSettings),
            'system' => $system,
            'messages' => $chatMessages,
            'max_tokens' => max(256, (int) ($this->ollamaOptions($llmSettings)['num_predict'] ?? 1500)),
            'temperature' => $this->ollamaOptions($llmSettings)['temperature'] ?? 0.2,
        ];
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     * @return array<string, mixed>
     */
    private function geminiChatPayload(array $messages, array $llmSettings): array
    {
        $text = implode("\n\n", array_map(
            static fn (array $message): string => strtoupper((string) ($message['role'] ?? 'user')).":\n".(string) ($message['content'] ?? ''),
            $messages,
        ));

        return [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $text]],
                ],
            ],
            'generationConfig' => [
                'temperature' => $this->ollamaOptions($llmSettings)['temperature'] ?? 0.2,
                'maxOutputTokens' => max(256, (int) ($this->ollamaOptions($llmSettings)['num_predict'] ?? 1500)),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $checklist
     */
    private function buildSystemPrompt(array $checklist, array $llmSettings = []): string
    {
        $checklistPrompt = trim((string) ($checklist['prompt'] ?? ''));
        $additionalInstructions = $checklistPrompt !== ''
            ? "\n\nДодаткові інструкції з поля «Промпт для оцінювання»:\n".$checklistPrompt
            : '';
        $customSystemPrompt = trim((string) ($llmSettings['system_prompt'] ?? ''));
        $scenario = $this->evaluationScenario($llmSettings);

        $defaultPrompt = $scenario === self::SCENARIO_SEQUENTIAL_CHAT
            ? CallCenterLlmPrompts::sequentialChatChecklistSystemPrompt()
            : CallCenterLlmPrompts::statelessChecklistItemSystemPrompt();

        if ($customSystemPrompt !== '') {
            if (
                $scenario === self::SCENARIO_STATELESS_SINGLE_ITEM
                && $this->isLegacySequentialSystemPrompt($customSystemPrompt)
            ) {
                return $defaultPrompt.$additionalInstructions;
            }

            if (
                $scenario === self::SCENARIO_SEQUENTIAL_CHAT
                && $this->isLegacyStatelessSystemPrompt($customSystemPrompt)
            ) {
                return $defaultPrompt.$additionalInstructions;
            }

            return $customSystemPrompt.$additionalInstructions;
        }

        return $defaultPrompt.$additionalInstructions;
    }

    private function isLegacySequentialSystemPrompt(string $prompt): bool
    {
        $normalized = mb_strtolower($prompt, 'UTF-8');

        return str_contains($normalized, 'послідовного чату')
            || str_contains($normalized, 'поточного чату')
            || str_contains($normalized, 'на перше повідомлення з транскриптом')
            || str_contains($normalized, 'запам\'ятай цей транскрипт')
            || str_contains($normalized, 'відповідай тільки: готово');
    }

    private function isLegacyStatelessSystemPrompt(string $prompt): bool
    {
        $normalized = mb_strtolower($prompt, 'UTF-8');

        return str_contains($normalized, 'оціни один пункт чек-листа')
            || str_contains($normalized, 'не покладайся на пам’ять попередніх повідомлень')
            || str_contains($normalized, 'не покладайся на пам\'ять попередніх повідомлень');
    }

    public function evaluationScenario(array $llmSettings = []): string
    {
        $rawScenario = trim((string) ($llmSettings['evaluation_scenario'] ?? ''));
        $normalized = mb_strtolower($rawScenario, 'UTF-8');

        return match ($normalized) {
            'sequential', 'sequential_chat', 'chat', 'dialog', 'dialogue' => self::SCENARIO_SEQUENTIAL_CHAT,
            default => self::SCENARIO_STATELESS_SINGLE_ITEM,
        };
    }

    private function resolveTranscriptForEvaluation(array $transcription): string
    {
        $dialogueText = trim((string) ($transcription['dialogueText'] ?? ''));
        $rawText = trim((string) ($transcription['text'] ?? ''));

        return $dialogueText !== '' ? $dialogueText : $rawText;
    }

    /**
     * @return array{verdict:string,reason:string}|null
     */
    private function parseChecklistAnswer(string $value): ?array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $lines = preg_split('/\R/u', $trimmed) ?: [];
        $nonEmptyLines = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines,
        ), static fn (string $line): bool => $line !== ''));

        if ($nonEmptyLines === []) {
            return null;
        }

        for ($index = count($nonEmptyLines) - 1; $index >= 0; $index--) {
            $verdict = $this->normalizeChecklistVerdict($nonEmptyLines[$index]);

            if ($verdict === null) {
                continue;
            }

            $reason = $this->normalizeAnswerReason(implode(' ', array_slice($nonEmptyLines, 0, $index)));

            return [
                'verdict' => $verdict,
                'reason' => $reason,
            ];
        }

        $heuristicVerdict = $this->inferChecklistVerdictFromExplanation($trimmed);
        if ($heuristicVerdict !== null) {
            return [
                'verdict' => $heuristicVerdict,
                'reason' => $this->normalizeAnswerReason($trimmed),
            ];
        }

        $reason = $this->normalizeAnswerReason($trimmed);
        if ($reason !== '') {
            return [
                'verdict' => 'Так',
                'reason' => $reason,
            ];
        }

        $verdict = $this->normalizeChecklistVerdict($trimmed);
        if ($verdict === null) {
            return null;
        }

        return [
            'verdict' => $verdict,
            'reason' => '',
        ];
    }

    private function inferChecklistVerdictFromExplanation(string $value): ?string
    {
        $normalized = trim(mb_strtolower($value, 'UTF-8'));
        $normalized = trim(preg_replace('/[\*\`_#>\[\]\(\)]+/u', ' ', $normalized) ?? $normalized);
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/\b(я не знаю|я незнаю|не знаю|недостатньо (даних|інформації)|даних недостатньо|інформації недостатньо|неможливо визначити|неможливо сказати|не можу визначити)\b/u', $normalized) === 1) {
            return 'Я не знаю';
        }

        $hasNegative = preg_match('/\b(відсутн\w*|не було|не виявлено|не зафіксовано|не прозвучало|не згадано|не озвучив\w*|не назвав\w*|не уточнив\w*|немає\b|нема\b|немає підтвердження|не привітав\w*)\b/u', $normalized) === 1;
        $hasPositive = preg_match('/\b(присутн\w*|наявн\w*|є підтвердження|підтверджено|є в транскрипті|згадан\w*|озвучив\w*|назвав\w*|уточнив\w*|привітав\w*|виявив\w*|виконан\w*)\b/u', $normalized) === 1;

        if ($hasNegative && ! $hasPositive) {
            return 'Ні';
        }

        if ($hasPositive && ! $hasNegative) {
            return 'Так';
        }

        return null;
    }

    private function normalizeChecklistVerdict(string $value): ?string
    {
        $normalized = trim(mb_strtolower($value, 'UTF-8'));
        $normalized = trim(preg_replace('/[\s\.\,\!\?\"\':;\(\)\[\]\{\}]+/u', ' ', $normalized) ?? $normalized);

        if (in_array($normalized, ['я незнаю', 'я не знаю', 'не знаю', 'незнаю'], true)) {
            return 'Я не знаю';
        }

        $hasUnknown = preg_match('/\bя\s*не\s*знаю\b/u', $normalized) === 1
            || preg_match('/\bя\s*незнаю\b/u', $normalized) === 1
            || preg_match('/\bнедостатньо (інформації|даних)\b/u', $normalized) === 1;

        if ($normalized === 'так') {
            return 'Так';
        }

        if ($normalized === 'ні' || $normalized === 'ни' || $normalized === 'немає' || $normalized === 'нема') {
            return 'Ні';
        }

        if ($normalized === 'є') {
            return 'Так';
        }

        $hasTak = preg_match('/\bтак\b/ui', $normalized) === 1;
        $hasNi = preg_match('/\bні\b/ui', $normalized) === 1 || preg_match('/\bни\b/ui', $normalized) === 1;

        if ($hasUnknown && ! $hasTak && ! $hasNi) {
            return 'Я не знаю';
        }

        if ($hasTak && ! $hasNi) {
            return 'Так';
        }

        if ($hasNi && ! $hasTak) {
            return 'Ні';
        }

        $hasYes = preg_match('/\byes\b/i', $normalized) === 1;
        $hasNo = preg_match('/\bno\b/i', $normalized) === 1;

        if ($hasYes && ! $hasNo) {
            return 'Так';
        }

        if ($hasNo && ! $hasYes) {
            return 'Ні';
        }

        return null;
    }

    private function normalizeAnswerReason(string $value): string
    {
        $reason = trim(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
        $reason = preg_replace('/^(?:\d+[\.\)]\s*|[-*]\s*)/u', '', $reason) ?? $reason;
        $reason = trim($reason);

        if ($reason === '') {
            return '';
        }

        if ($this->normalizeChecklistVerdict($reason) !== null) {
            return '';
        }

        return mb_substr($reason, 0, 220);
    }

    private function buildItemComment(string $answer, string $reason): string
    {
        $normalizedReason = $this->normalizeAnswerReason($reason);
        if ($normalizedReason !== '') {
            return $normalizedReason;
        }

        if ($answer === 'Так') {
            return 'У транскрипті є підтвердження виконання цього пункту.';
        }

        if ($answer === 'Я не знаю') {
            return 'У транскрипті недостатньо підтвердження для впевненої оцінки цього пункту.';
        }

        return 'У транскрипті немає підтвердження виконання цього пункту.';
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, array{id:string,label:string,max_points:int}>
     */
    private function normalizeChecklistItems(array $items): array
    {
        $normalized = [];

        foreach (array_values($items) as $index => $item) {
            $label = trim(is_array($item)
                ? (string) ($item['label'] ?? $item['text'] ?? $item['name'] ?? '')
                : (string) $item);
            $maxPoints = $this->normalizeMaxPoints(is_array($item) ? ($item['max_points'] ?? $item['maxPoints'] ?? null) : null);

            if ($label === '') {
                continue;
            }

            $normalized[] = [
                'id' => 'item_'.($index + 1),
                'label' => $label,
                'max_points' => $maxPoints,
            ];
        }

        return $normalized;
    }

    private function normalizeMaxPoints(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 10;
        }

        return max(1, min(100, (int) round((float) $value)));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function calculateEarnedScore(array $items): int
    {
        return array_sum(array_map(
            static fn (array $item): int => (int) ($item['score'] ?? 0),
            $items,
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function calculateTotalPoints(array $items): int
    {
        return array_sum(array_map(
            static fn (array $item): int => (int) ($item['max_points'] ?? 0),
            $items,
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function calculateOverallPercent(array $items): int
    {
        if ($items === []) {
            return 0;
        }

        $earned = $this->calculateEarnedScore($items);
        $maximum = $this->calculateTotalPoints($items);

        if ($maximum <= 0) {
            return 0;
        }

        return max(0, min(100, (int) round(($earned / $maximum) * 100)));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function buildStrongSide(array $items): string
    {
        if ($items === []) {
            return 'Сильна сторона не визначена.';
        }

        $bestItem = $this->selectBestItem($items);
        if ($bestItem === null) {
            return 'Сильна сторона не визначена.';
        }

        $score = (int) ($bestItem['score'] ?? 0);
        $maxPoints = max(1, (int) ($bestItem['max_points'] ?? 1));
        $label = trim((string) ($bestItem['label'] ?? ''));

        if ($score <= 0) {
            return 'Модель не підтвердила жодного пункту чек-листа відповіддю «Так».';
        }

        return sprintf(
            'Найсильніше відпрацьовано пункт «%s» (%d/%d). Модель підтвердила його незалежною відповіддю «Так».',
            $label !== '' ? $label : 'без назви',
            $score,
            $maxPoints,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function buildFocus(array $items): string
    {
        if ($items === []) {
            return 'Немає рекомендації для наступного кроку.';
        }

        $weakestItem = $this->selectWeakestItem($items);
        if ($weakestItem === null) {
            return 'Немає рекомендації для наступного кроку.';
        }

        $score = (int) ($weakestItem['score'] ?? 0);
        $maxPoints = max(1, (int) ($weakestItem['max_points'] ?? 1));

        if ($score >= $maxPoints) {
            return 'Усі пункти чек-листа підтверджено відповідями «Так». Критичних зон росту не виявлено.';
        }

        $label = trim((string) ($weakestItem['label'] ?? ''));
        $answer = trim((string) ($weakestItem['answer'] ?? 'Ні'));

        if ($answer === 'Я не знаю') {
            return sprintf(
                'Основна зона уваги: «%s» (%d/%d). Для цього пункту модель у незалежному запиті повернула «я незнаю», бо в транскрипті замало явних підтверджень.',
                $label !== '' ? $label : 'без назви',
                $score,
                $maxPoints,
            );
        }

        return sprintf(
            'Основна зона росту: «%s» (%d/%d). Для цього пункту модель у незалежному запиті повернула відповідь «Ні».',
            $label !== '' ? $label : 'без назви',
            $score,
            $maxPoints,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function buildSummary(array $items): string
    {
        if ($items === []) {
            return 'Підсумок оцінки не сформовано.';
        }

        $earned = $this->calculateEarnedScore($items);
        $maximum = $this->calculateTotalPoints($items);
        $positiveAnswers = count(array_filter($items, static fn (array $item): bool => (int) ($item['score'] ?? 0) > 0));
        $unknownAnswers = count(array_filter($items, static fn (array $item): bool => (string) ($item['answer'] ?? '') === 'Я не знаю'));

        $summary = sprintf(
            'Stateless-оцінювання завершено: позитивних відповідей %d із %d, підсумковий бал %d з %d.',
            $positiveAnswers,
            count($items),
            $earned,
            $maximum,
        );

        if ($unknownAnswers > 0) {
            $summary .= sprintf(' Для %d пункт(ів) модель повернула «я незнаю».', $unknownAnswers);
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    private function selectBestItem(array $items): ?array
    {
        $bestItem = null;
        $bestPercent = -1;
        $bestScore = -1;

        foreach ($items as $item) {
            $percent = (int) ($item['percentage'] ?? 0);
            $score = (int) ($item['score'] ?? 0);

            if ($percent > $bestPercent || ($percent === $bestPercent && $score > $bestScore)) {
                $bestItem = $item;
                $bestPercent = $percent;
                $bestScore = $score;
            }
        }

        return $bestItem;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    private function selectWeakestItem(array $items): ?array
    {
        $weakestItem = null;
        $weakestPercent = 101;
        $weakestGap = -1;

        foreach ($items as $item) {
            $percent = (int) ($item['percentage'] ?? 0);
            $score = (int) ($item['score'] ?? 0);
            $maxPoints = max(1, (int) ($item['max_points'] ?? 1));
            $gap = $maxPoints - $score;

            if ($percent < $weakestPercent || ($percent === $weakestPercent && $gap > $weakestGap)) {
                $weakestItem = $item;
                $weakestPercent = $percent;
                $weakestGap = $gap;
            }
        }

        return $weakestItem;
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     */
    private function extractSystemPrompt(array $messages): string
    {
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                return (string) ($message['content'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     */
    private function buildChatPromptPreview(array $messages): string
    {
        $lines = [];

        foreach ($messages as $message) {
            $role = strtoupper((string) ($message['role'] ?? 'user'));
            $content = trim((string) ($message['content'] ?? ''));

            if ($role === 'SYSTEM' || $content === '') {
                continue;
            }

            if ($lines !== []) {
                $lines[] = '';
            }

            $lines[] = "=== {$role} ===";
            $lines[] = $content;
        }

        return $lines !== []
            ? implode("\n", $lines)
            : 'Після запуску тут з\'явиться stateless-запит, який backend передає в Qwen / Ollama.';
    }

    private function model(array $llmSettings = []): string
    {
        $localModel = trim((string) ($llmSettings['model'] ?? ''));
        if ($localModel !== '') {
            return $localModel;
        }

        return $this->settings->llmModel();
    }

    private function provider(array $llmSettings = []): string
    {
        $localProvider = trim((string) ($llmSettings['provider'] ?? ''));
        if ($localProvider !== '') {
            return $localProvider;
        }

        return $this->settings->llmProvider();
    }

    /**
     * @return array<string, int|float>
     */
    private function resolvedModelParams(array $llmSettings, int $timeout): array
    {
        $options = $this->ollamaOptions($llmSettings);
        $params = [
            'temperature' => $options['temperature'] ?? null,
            'num_ctx' => $options['num_ctx'] ?? null,
            'top_k' => $options['top_k'] ?? null,
            'top_p' => $options['top_p'] ?? null,
            'repeat_penalty' => $options['repeat_penalty'] ?? null,
            'num_predict' => $options['num_predict'] ?? null,
            'seed' => $options['seed'] ?? null,
            'timeout_seconds' => $timeout,
        ];

        return array_filter(
            $params,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );
    }

    /**
     * @return array<string, int|float>
     */
    private function ollamaOptions(array $llmSettings = []): array
    {
        $rawRepeatPenalty = $llmSettings['repeat_penalty'] ?? $llmSettings['repetition_penalty'] ?? null;

        $options = [
            'temperature' => $this->normalizeFloatOption($llmSettings['temperature'] ?? null, $this->settings->llmTemperature(), 0.0, 2.0),
            'num_ctx' => $this->normalizeIntOption($llmSettings['num_ctx'] ?? null, $this->settings->llmNumCtx(), 256, 131072),
            'top_k' => $this->normalizeIntOption($llmSettings['top_k'] ?? null, $this->settings->llmTopK(), 1, 500),
            'top_p' => $this->normalizeFloatOption($llmSettings['top_p'] ?? null, $this->settings->llmTopP(), 0.0, 1.0),
            'repeat_penalty' => $this->normalizeFloatOption($rawRepeatPenalty, $this->settings->llmRepeatPenalty(), 0.0, 5.0),
            'num_predict' => $this->normalizeIntOption(
                $llmSettings['num_predict'] ?? $llmSettings['max_new_tokens'] ?? null,
                $this->settings->llmNumPredict(),
                -1,
                32768,
            ),
        ];

        $seed = $this->normalizeOptionalIntOption(
            $llmSettings['seed'] ?? $this->settings->llmSeed(),
            -2147483648,
            2147483647,
        );
        if ($seed !== null) {
            $options['seed'] = $seed;
        }

        return $options;
    }
    private function resolveTimeoutSeconds(array $llmSettings, int $fallback): int
    {
        return max(
            $fallback,
            $this->normalizeIntOption($llmSettings['timeout_seconds'] ?? null, $fallback, 15, 3600),
        );
    }

    private function normalizeIntOption(mixed $value, int $default, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            return max($min, min($max, $default));
        }

        return max($min, min($max, (int) round((float) $value)));
    }

    private function normalizeOptionalIntOption(mixed $value, int $min, int $max): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return max($min, min($max, (int) round((float) $value)));
    }

    private function normalizeFloatOption(mixed $value, float $default, float $min, float $max): float
    {
        if (! is_numeric($value)) {
            return max($min, min($max, $default));
        }

        return max($min, min($max, (float) $value));
    }

    /**
     * @param callable|null $reporter
     * @param array<string, mixed> $payload
     */
    private function emitReporter(?callable $reporter, string $type, array $payload = []): void
    {
        if ($reporter === null) {
            return;
        }

        $reporter(array_merge($payload, [
            'type' => $type,
        ]));
    }

    private function mapOllamaConnectionException(ConnectionException $exception, int $timeout): RuntimeException
    {
        $message = mb_strtolower($exception->getMessage(), 'UTF-8');

        if (
            str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'curl error 28')
        ) {
            return new RuntimeException(
                "Ollama не встиг відповісти за {$timeout} сек. Зменште чек-лист/контекст, використайте швидшу модель або дочекайтеся завершення у фоновому режимі."
            );
        }

        return new RuntimeException('Не вдалося підключитися до Ollama для stateless-оцінювання чек-листа.');
    }
}
