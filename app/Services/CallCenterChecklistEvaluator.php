<?php

namespace App\Services;

use App\Support\CallCenterTranscriptionSettings;
use App\Support\CallCenterLlmPrompts;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CallCenterChecklistEvaluator
{
    private const CHECKLIST_REQUEST_PAUSE_MS = 2000;
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_RETRY_BASE_MS = 3000;
    private const RATE_LIMIT_RETRY_MAX_MS = 30000;
    private const TRANSIENT_CONNECTION_MAX_ATTEMPTS = 3;
    private const TRANSIENT_CONNECTION_RETRY_BASE_MS = 2000;
    private const TRANSIENT_CONNECTION_RETRY_MAX_MS = 10000;

    public function __construct(
        protected readonly CallCenterTranscriptionSettings $settings,
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
            false,
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
            true,
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
        bool $backgroundMode,
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

        $this->emitReporter($reporter, 'log', [
            'channel' => 'status',
            'message' => 'Підготовлено '.count($items).' пункт(ів) чек-листа для оцінювання.',
        ]);

        $dialogueText = trim((string) ($transcription['dialogueText'] ?? ''));
        $rawText = trim((string) ($transcription['text'] ?? ''));
        $transcriptForEvaluation = $dialogueText !== '' ? $dialogueText : $rawText;

        if ($transcriptForEvaluation === '') {
            throw new RuntimeException('Транскрибація порожня, тому оцінити дзвінок за чек-листом не вдалося.');
        }

        $checklistName = trim((string) ($checklist['name'] ?? '')) ?: 'Чек-лист';
        $trace = [
            'thinking_sections' => [],
            'response_sections' => [],
        ];

        $this->emitReporter($reporter, 'log', [
            'channel' => 'status',
            'message' => 'Підготовлено текст дзвінка для LLM. Запускаємо незалежні stateless-запити: повний транскрипт + один пункт чек-листа у кожному запиті.',
        ]);
        $this->emitReporter($reporter, 'log', [
            'channel' => 'status',
            'message' => 'Між пунктами додаємо коротку технічну паузу, а при 429 / Too Many Attempts backend автоматично зачекає і повторить запит.',
        ]);

        $normalizedItems = [];
        $itemsCount = count($items);

        foreach ($items as $index => $item) {
            if ($index > 0) {
                $this->pauseBetweenChecklistRequests();
            }

            $normalizedItems[] = $this->evaluateChecklistItemStateless(
                $transcriptForEvaluation,
                $item,
                $checklist,
                $index + 1,
                $itemsCount,
                $timeout,
                $reporter,
                $llmSettings,
                $trace,
            );
        }

        if ($normalizedItems === []) {
            throw new RuntimeException('Qwen не повернув коректну оцінку за чек-листом. Спробуйте ще раз або скоротіть чек-лист.');
        }

        $earnedScore = $this->calculateEarnedScore($normalizedItems);
        $totalPoints = $this->calculateTotalPoints($normalizedItems);
        $overallPercent = $this->calculateOverallPercent($normalizedItems);

        $this->emitReporter($reporter, 'log', [
            'channel' => 'success',
            'message' => 'LLM завершила незалежне оцінювання пунктів. Нормалізуємо підсумкові бали та коментарі.',
        ]);

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
            'items' => $normalizedItems,
        ];
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

        $this->emitReporter($reporter, 'phase', [
            'phase' => "stateless_question_{$position}_of_{$total}",
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
                'content' => $this->systemPrompt($llmSettings, $checklist),
            ],
            [
                'role' => 'user',
                'content' => $this->buildChecklistEvaluationUserPrompt($fullTranscript, $checklistItem, $previousInvalidReply),
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
    private function requestChat(
        array $messages,
        int $timeout,
        ?callable $reporter = null,
        array $llmSettings = [],
    ): array {
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
    ): array {
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

    /**
     * @param array<int, array{id:string,label:string,max_points:int}> $items
     * @return array<string, mixed>
     */
    private function requestOllama(
        string $dialogueText,
        string $rawText,
        array $checklist,
        array $items,
        int $timeout,
        bool $backgroundMode,
        ?callable $reporter = null,
        array $llmSettings = [],
    ): array
    {
        $url = rtrim($this->settings->llmApiUrl(), '/');
        $systemPrompt = $this->systemPrompt($llmSettings);
        $promptText = $this->buildPrompt($dialogueText, $rawText, $checklist, $items);
        $requestPayload = [
            'model' => $this->model($llmSettings),
            'think' => false,
            'system' => $systemPrompt,
            'format' => $this->responseFormatSchema(),
            'options' => $this->ollamaOptions($llmSettings),
            'prompt' => $promptText,
            'keep_alive' => '15s',
        ];
        $useStreaming = $backgroundMode || $reporter !== null;

        try {
            $this->emitReporter($reporter, 'prompt', [
                'system_prompt' => $systemPrompt,
                'prompt' => $promptText,
            ]);
            $this->emitReporter($reporter, 'log', [
                'channel' => 'status',
                'message' => $useStreaming
                    ? 'Надсилаємо потоковий запит до Ollama, щоб показувати хід роботи в реальному часі.'
                    : 'Надсилаємо запит до Ollama.',
            ]);

            $decoded = $this->executeGenerateRequest(
                $url,
                $requestPayload,
                $timeout,
                $useStreaming,
                $reporter,
            );
        } catch (ConnectionException $exception) {
            throw $this->mapOllamaConnectionException($exception, $timeout);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('Сталася внутрішня помилка під час звернення до Ollama для оцінки чек-листа.');
        }

        $decoded = $this->retryGenerateWithoutThinkingWhenResponseEmpty(
            $decoded,
            $url,
            $requestPayload,
            $timeout,
            $useStreaming,
            $reporter,
        );

        $content = $this->extractGenerateResponseText($decoded);
        $payload = $this->decodeJsonObject($content);
        $fallbackChecklistName = trim((string) ($checklist['name'] ?? '')) ?: 'Чек-лист';
        $salvagedPayload = $this->salvageEvaluationPayload($payload, $items, $fallbackChecklistName);
        $invalidReason = $this->invalidEvaluationPayloadReason($payload, $items);

        if ($invalidReason !== '') {
            $this->emitReporter($reporter, 'log', [
                'channel' => 'warning',
                'message' => 'Перша відповідь LLM не пройшла валідацію: '.$invalidReason,
            ]);
            $payload = $this->retryStructuredEvaluation(
                $dialogueText,
                $rawText,
                $checklist,
                $items,
                $invalidReason,
                $timeout,
                $reporter,
                $llmSettings,
            );
            $salvagedPayload = $this->salvageEvaluationPayload($payload, $items, $fallbackChecklistName)
                ?? $salvagedPayload;
        }

        $finalInvalidReason = $this->invalidEvaluationPayloadReason($payload, $items);

        if ($finalInvalidReason !== '') {
            if ($salvagedPayload !== null) {
                $this->emitReporter($reporter, 'log', [
                    'channel' => 'warning',
                    'message' => 'LLM повернула неідеальний JSON, але Laravel змогла відновити оцінювання за чек-листом із резервною нормалізацією.',
                ]);

                return $salvagedPayload;
            }

            $this->emitReporter($reporter, 'log', [
                'channel' => 'warning',
                'message' => 'Повторна відповідь LLM теж не пройшла валідацію: '.$finalInvalidReason,
            ]);
            throw new RuntimeException(
                $backgroundMode
                    ? 'Qwen в Ollama повернув невалідне оцінювання навіть у фоновому режимі. Спробуйте швидшу модель або скоротіть чек-лист.'
                    : 'Qwen в Ollama повернув оцінювання не українською або не у потрібному форматі. Спробуйте ще раз або зменште контекстне вікно.'
            );
        }

        return $payload ?? [];
    }

    private function makeOllamaRequest(int $timeout, bool $stream = false)
    {
        $request = Http::connectTimeout(5)
            ->timeout($timeout)
            ->acceptJson();

        if ($stream) {
            $request = $request->withOptions(['stream' => true]);
        }

        if ($this->settings->hasLlmApiKey()) {
            $request = $request->withToken($this->settings->llmApiKey());
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $requestPayload
     * @return array<string, mixed>
     */
    private function executeGenerateRequest(
        string $url,
        array $requestPayload,
        int $timeout,
        bool $useStreaming,
        ?callable $reporter = null,
    ): array {
        $response = $this->makeOllamaRequest($timeout, $useStreaming)
            ->post($url.'/api/generate', array_merge($requestPayload, [
                'stream' => $useStreaming,
            ]));

        if (! $useStreaming && ! $response->successful()) {
            $error = $this->extractOllamaErrorMessage($response);

            throw new RuntimeException(
                $error !== ''
                    ? $error
                    : 'Ollama повернув помилку під час оцінювання дзвінка.'
            );
        }

        $decoded = $useStreaming
            ? $this->consumeOllamaStreamedResponse($response, $reporter)
            : $response->json();

        if (! is_array($decoded)) {
            throw new RuntimeException('Ollama повернув невалідну відповідь.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $decoded
     * @param array<string, mixed> $requestPayload
     * @return array<string, mixed>
     */
    private function retryGenerateWithoutThinkingWhenResponseEmpty(
        array $decoded,
        string $url,
        array $requestPayload,
        int $timeout,
        bool $useStreaming,
        ?callable $reporter = null,
    ): array {
        if ($this->extractGenerateResponseText($decoded) !== '' || ! (bool) ($requestPayload['think'] ?? false)) {
            return $decoded;
        }

        $capturedThinking = $this->extractGenerateThinkingText($decoded);
        $this->emitReporter($reporter, 'log', [
            'channel' => 'warning',
            'message' => $capturedThinking !== ''
                ? 'Ollama повернула only-thinking без фінального тексту. Повторюємо той самий запит із think=false, щоб отримати відповідь.'
                : 'Ollama повернула порожню відповідь у thinking-режимі. Повторюємо той самий запит із think=false.',
        ]);

        try {
            $fallbackPayload = $requestPayload;
            $fallbackPayload['think'] = false;
            $fallbackDecoded = $this->executeGenerateRequest(
                $url,
                $fallbackPayload,
                $timeout,
                $useStreaming,
                $reporter,
            );
        } catch (Throwable) {
            return $decoded;
        }

        if ($capturedThinking !== '' && $this->extractGenerateThinkingText($fallbackDecoded) === '') {
            $fallbackDecoded['thinking'] = $capturedThinking;
        }

        return $fallbackDecoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function consumeOllamaStreamedResponse(Response $response, ?callable $reporter = null): array
    {
        if (! $response->successful()) {
            $error = $this->extractOllamaErrorMessage($response);

            throw new RuntimeException(
                $error !== ''
                    ? $error
                    : 'Ollama повернув помилку під час оцінювання дзвінка.'
            );
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        $state = [
            'thinking' => '',
            'response' => '',
            'done' => null,
            'thinking_started' => false,
            'response_started' => false,
        ];

        while (! $stream->eof()) {
            $buffer .= $stream->read(8192);

            while (($newlinePosition = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePosition);
                $buffer = (string) substr($buffer, $newlinePosition + 1);
                $this->consumeOllamaStreamLine($line, $state, $reporter);
            }
        }

        if (trim($buffer) !== '') {
            $this->consumeOllamaStreamLine($buffer, $state, $reporter);
        }

        $donePayload = is_array($state['done']) ? $state['done'] : [];
        $doneResponse = $this->extractGenerateResponseText($donePayload);
        if ($donePayload !== [] && $doneResponse !== '' && trim($state['response']) === '') {
            $state['response'] = $doneResponse;
        }

        $doneThinking = $this->extractGenerateThinkingText($donePayload);
        if ($donePayload !== [] && $doneThinking !== '' && trim($state['thinking']) === '') {
            $state['thinking'] = $doneThinking;
        }

        if (trim($state['response']) === '' && $donePayload === []) {
            throw new RuntimeException('Ollama повернув порожню потокову відповідь.');
        }

        $this->emitReporter($reporter, 'log', [
            'channel' => 'success',
            'message' => 'Відповідь Ollama отримано. Перевіряємо JSON та мову результату.',
        ]);

        return array_merge($donePayload, [
            'thinking' => $state['thinking'],
            'response' => $state['response'],
        ]);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function consumeOllamaStreamLine(string $line, array &$state, ?callable $reporter = null): void
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return;
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            $state['response'] .= $trimmed;
            $this->emitReporter($reporter, 'response', [
                'text' => $state['response'],
            ]);
            return;
        }

        $error = trim((string) ($decoded['error'] ?? ''));
        if ($error !== '') {
            throw new RuntimeException($error);
        }

        $thinkingChunk = $this->extractGenerateThinkingText($decoded);
        if ($thinkingChunk !== '') {
            $state['thinking'] .= $thinkingChunk;

            if (! $state['thinking_started']) {
                $state['thinking_started'] = true;
                $this->emitReporter($reporter, 'log', [
                    'channel' => 'status',
                    'message' => 'Ollama почала reasoning / thinking. Потік міркувань уже видно нижче.',
                ]);
            }

            $this->emitReporter($reporter, 'thinking', [
                'text' => $state['thinking'],
            ]);
        }

        $responseChunk = $this->extractGenerateResponseText($decoded);
        if ($responseChunk !== '') {
            $state['response'] .= $responseChunk;

            if (! $state['response_started']) {
                $state['response_started'] = true;
                $this->emitReporter($reporter, 'log', [
                    'channel' => 'status',
                    'message' => 'Ollama почала формувати фінальну відповідь.',
                ]);
            }

            $this->emitReporter($reporter, 'response', [
                'text' => $state['response'],
            ]);
        }

        if (($decoded['done'] ?? false) === true) {
            $state['done'] = $decoded;
        }
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractGenerateResponseText(array $decoded): string
    {
        foreach ([
            $decoded['response'] ?? null,
            $decoded['message']['content'] ?? null,
            $decoded['content'] ?? null,
        ] as $candidate) {
            $normalized = $this->normalizeOllamaTextField($candidate);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractGenerateThinkingText(array $decoded): string
    {
        foreach ([
            $decoded['thinking'] ?? null,
            $decoded['message']['thinking'] ?? null,
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

    /**
     * @param array<int, array{id:string,label:string,max_points:int}> $items
     */
    private function buildPrompt(string $dialogueText, string $rawText, array $checklist, array $items): string
    {
        $itemsList = implode("\n", array_map(
            static fn (array $item, int $index): string => ($index + 1).'. item_id: '.$item['id'].' | label: '.$item['label'].' | max_points: '.$item['max_points'],
            $items,
            array_keys($items),
        ));
        $allowedItemIds = implode(', ', array_map(
            static fn (array $item): string => $item['id'],
            $items,
        ));
        $expectedItemsCount = count($items);
        $nextForbiddenItemId = 'item_'.($expectedItemsCount + 1);

        $dialogueBlock = $dialogueText !== '' ? $dialogueText : $rawText;
        $checklistName = (string) ($checklist['name'] ?? 'Чек-лист');
        $checklistType = (string) ($checklist['type'] ?? 'Загальний сценарій');
        $checklistPrompt = trim((string) ($checklist['prompt'] ?? ''));
        $additionalInstructionsBlock = $checklistPrompt !== ''
            ? "\n\nДодаткові інструкції користувача до оцінювання:\n".$checklistPrompt
            : '';

        return <<<PROMPT
Ти керівник відділу контролю якості продажів. Оціни розмову менеджера з клієнтом тільки за наведеним чек-листом.

Правила:
1. Відповідай лише валідним JSON без markdown, коментарів і пояснень поза JSON.
2. УСІ текстові поля відповіді мають бути тільки українською мовою.
3. Використовуй часові мітки та ролі "Менеджер"/"Клієнт" з транскрипту, якщо вони є.
4. Не вигадуй факти, яких немає в транскрипті.
5. Для кожного пункту чек-листа дай окрему оцінку від 0 до max_points цього пункту і короткий коментар.
6. Поле items має містити рівно стільки елементів, скільки пунктів у чек-листі, і в тому ж порядку.
7. Кожен елемент items має містити саме поля item_id, score, comment.
8. Не повертай жодних полів summary, strong_side, focus, total_score, score_percent або будь-яких інших узагальнень.
9. Не пиши загальний підсумок розмови. Оцінюй тільки окремі пункти чек-листа.
10. Не об'єднуй сусідні пункти чек-листа в один і не переставляй їх місцями.
11. Значення item_id потрібно копіювати точно як у списку чек-листа нижче. Не вигадуй нові item_id і не змінюй їх.
12. Коментар для кожного item_id має стосуватися тільки цього одного пункту чек-листа.
13. Заборонено використовувати китайську, англійську або будь-яку іншу мову в полях checklist_name та items.comment.
14. Поле checklist_name поверни точно без перекладу: "{$this->escapeJsonString($checklistName)}".
15. Загальний бал буде рахувати бекенд, тому ти не повинен повертати загальний score дзвінка.
16. У відповіді має бути рівно {$expectedItemsCount} елементів у масиві items.
17. Дозволені тільки такі item_id і тільки в такому порядку: {$allowedItemIds}.
18. item_id поза цим списком заборонені. Не можна створювати {$nextForbiddenItemId} або будь-які інші нові item_id.
19. Якщо для пункту немає достатньо доказів у транскрипті, все одно поверни цей item_id зі score = 0 і коментарем українською.
20. Коментар має містити конкретну причину саме для цього пункту, а не копіювати один і той самий текст для різних item_id без потреби.
21. Коментар має ПОЯСНЮВАТИ оцінку за пунктом чек-листа, а не бути рекомендацією, наказом, нагадуванням або наступною дією.
22. Добрий формат коментаря: "Виконано: менеджер ...", "Частково виконано: менеджер ...", "Не виконано: у транскрипті немає підтвердження, що менеджер ...".
23. Якщо є доказ у транскрипті, коротко назви його словами або через часову мітку. Якщо доказу немає, прямо так і напиши.
24. Не пиши коментарі у стилі "Вказати ціну", "Запитати адресу", "Уточнити ...", "Перевірити ...". Це заборонений формат.
25. Коментар має бути максимум 1-2 короткі речення і стосуватися лише поточного пункту.
26. Кожен comment має починатися тільки з одного з таких шаблонів: "Виконано:", "Частково виконано:", "Не виконано:", "Менеджер", "У транскрипті", "У розмові".

Поверни JSON строго такого вигляду:
{
  "checklist_name": "{$this->escapeJsonString($checklistName)}",
  "items": [
    {
      "item_id": "item_1",
      "score": 0,
      "comment": "Не виконано: у транскрипті немає підтвердження, що менеджер назвав причину дзвінка."
    }
  ]
}

Чек-лист:
Назва: {$checklistName}
Тип сценарію: {$checklistType}
Пункти:
{$itemsList}
{$additionalInstructionsBlock}

Транскрипт дзвінка:
{$dialogueBlock}
PROMPT;
    }

    private function model(array $llmSettings = []): string
    {
        $localModel = trim((string) ($llmSettings['model'] ?? ''));
        if ($localModel !== '') {
            return $localModel;
        }

        return $this->settings->llmModel();
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

    /**
     * @param array<int, mixed> $rawItems
     * @param array<int, array{id:string,label:string,max_points:int}> $checklistItems
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEvaluationItems(array $rawItems, array $checklistItems, ?int $fallbackPercentScore): array
    {
        if ($rawItems === [] && $fallbackPercentScore === null) {
            return [];
        }

        $normalized = [];
        $rawItemsById = [];
        $rawItemsByLabel = [];

        foreach ($rawItems as $index => $rawItem) {
            if (! is_array($rawItem)) {
                continue;
            }

            $rawItem['__index'] = $index;
            $itemId = $this->normalizeComparableKey((string) ($rawItem['item_id'] ?? $rawItem['id'] ?? ''));
            if ($itemId !== '' && ! array_key_exists($itemId, $rawItemsById)) {
                $rawItemsById[$itemId] = $rawItem;
            }

            $labelKey = $this->normalizeComparableKey((string) ($rawItem['label'] ?? ''));
            if ($labelKey !== '' && ! array_key_exists($labelKey, $rawItemsByLabel)) {
                $rawItemsByLabel[$labelKey] = $rawItem;
            }
        }

        foreach ($checklistItems as $index => $item) {
            $itemId = $this->normalizeComparableKey($item['id']);
            $labelKey = $this->normalizeComparableKey($item['label']);
            $maxPoints = $item['max_points'];
            $positionSource = is_array($rawItems[$index] ?? null)
                ? $rawItems[$index]
                : [];

            $source = $rawItemsById[$itemId]
                ?? $rawItemsByLabel[$labelKey]
                ?? $positionSource
                ?? [];
            $score = $this->normalizeItemScore($source['score'] ?? null, $maxPoints);

            if ($score === null && $fallbackPercentScore !== null) {
                $score = (int) round(($maxPoints * $fallbackPercentScore) / 100);
            }

            $normalizedScore = $score ?? 0;

            $normalized[] = [
                'id' => $item['id'],
                'label' => $item['label'],
                'max_points' => $maxPoints,
                'score' => $normalizedScore,
                'percentage' => $maxPoints > 0
                    ? max(0, min(100, (int) round(($normalizedScore / $maxPoints) * 100)))
                    : 0,
                'comment' => $this->normalizeEvaluationComment(
                    $source['comment'] ?? null,
                    $item['label'],
                    $normalizedScore,
                    $maxPoints,
                ),
            ];
        }

        return $normalized;
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

    private function normalizePercentScore(mixed $score): ?int
    {
        if (! is_numeric($score)) {
            return null;
        }

        return max(0, min(100, (int) round((float) $score)));
    }

    private function normalizeItemScore(mixed $score, int $maxPoints): ?int
    {
        if (! is_numeric($score)) {
            return null;
        }

        return max(0, min($maxPoints, (int) round((float) $score)));
    }

    private function normalizeMaxPoints(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 10;
        }

        return max(1, min(100, (int) round((float) $value)));
    }

    private function normalizeSentence(mixed $value, string $fallback): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function normalizeEvaluationComment(mixed $value, string $label, int $score, int $maxPoints): string
    {
        $comment = trim((string) $value);
        if (
            $comment !== ''
            && ! $this->isRecommendationStyleComment($comment)
            && $this->startsWithAllowedCommentPrefix($comment)
        ) {
            return $comment;
        }

        $safeLabel = trim($label) !== '' ? '«'.trim($label).'»' : 'цього пункту чек-листа';

        if ($score <= 0) {
            return "Не виконано: у транскрипті немає достатнього підтвердження для пункту {$safeLabel}.";
        }

        if ($score >= $maxPoints) {
            return "Виконано: у транскрипті є достатнє підтвердження для пункту {$safeLabel}.";
        }

        return "Частково виконано: пункт {$safeLabel} підтверджено в розмові не повністю.";
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

        $label = trim((string) ($bestItem['label'] ?? ''));
        $score = (int) ($bestItem['score'] ?? 0);
        $maxPoints = max(1, (int) ($bestItem['max_points'] ?? 1));
        $comment = trim((string) ($bestItem['comment'] ?? ''));

        return $this->normalizeSentence(
            sprintf(
                'Найкраще відпрацьовано пункт «%s» (%d/%d). %s',
                $label !== '' ? $label : 'без назви',
                $score,
                $maxPoints,
                $this->startsWithAllowedCommentPrefix($comment)
                    ? $comment
                    : 'Пункт відпрацьовано найсильніше серед усього чек-листа.'
            ),
            'Сильна сторона не визначена.'
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
            return 'Усі пункти чек-листа закрито на максимум. Критичних зон росту не виявлено.';
        }

        $label = trim((string) ($weakestItem['label'] ?? ''));
        $comment = trim((string) ($weakestItem['comment'] ?? ''));

        return $this->normalizeSentence(
            sprintf(
                'Основна зона росту: «%s» (%d/%d). %s',
                $label !== '' ? $label : 'без назви',
                $score,
                $maxPoints,
                $this->startsWithAllowedCommentPrefix($comment)
                    ? $comment
                    : 'Цей пункт потребує найуважнішого доопрацювання.'
            ),
            'Немає рекомендації для наступного кроку.'
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
        $percent = $this->calculateOverallPercent($items);

        return sprintf(
            'Менеджер набрав %d з %d балів за чек-листом (%d%%).',
            $earned,
            $maximum,
            $percent
        );
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
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $value): ?array
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $trimmed = trim($value);
        $trimmed = preg_replace('/^```(?:json)?\s*/u', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/u', '', $trimmed) ?? $trimmed;

        if (preg_match('/\{.*\}/su', $trimmed, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseFormatSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'checklist_name' => ['type' => 'string'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'item_id' => ['type' => 'string'],
                            'label' => ['type' => 'string'],
                            'score' => ['type' => 'integer'],
                            'comment' => ['type' => 'string'],
                        ],
                        'required' => ['item_id', 'score', 'comment'],
                    ],
                ],
            ],
            'required' => ['checklist_name', 'items'],
        ];
    }

    private function isStructuredEvaluationPayload(?array $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $hasItems = is_array($payload['items'] ?? null) && ($payload['items'] ?? []) !== [];

        return $hasItems;
    }

    /**
     * @param array<int, array{id:string,label:string,max_points:int}> $checklistItems
     */
    private function isAcceptableEvaluationPayload(?array $payload, array $checklistItems = []): bool
    {
        return $this->isStructuredEvaluationPayload($payload)
            && $this->isEvaluationLanguageValid($payload)
            && $this->hasValidChecklistItemMapping($payload, $checklistItems);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<int, array{id:string,label:string,max_points:int}> $checklistItems
     */
    private function invalidEvaluationPayloadReason(?array $payload, array $checklistItems = []): string
    {
        if (! $this->isStructuredEvaluationPayload($payload)) {
            return 'LLM не повернула коректний JSON з масивом items.';
        }

        if (! $this->isEvaluationLanguageValid($payload)) {
            return 'LLM повернула текст не українською або з неприйнятною писемністю.';
        }

        if (! $this->hasValidChecklistItemMapping($payload, $checklistItems, $reason)) {
            return $reason ?? 'LLM порушила структуру item_id у відповіді.';
        }

        return '';
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<int, array{id:string,label:string,max_points:int}> $checklistItems
     * @return array<string, mixed>|null
     */
    private function salvageEvaluationPayload(?array $payload, array $checklistItems, string $fallbackChecklistName): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $rawItems = $payload['items'] ?? null;
        if (! is_array($rawItems) || $rawItems === []) {
            return null;
        }

        if (! $this->canRecoverEvaluationItems($rawItems, $checklistItems)) {
            return null;
        }

        $normalizedItems = $this->normalizeEvaluationItems($rawItems, $checklistItems, null);
        if ($normalizedItems === [] || count($normalizedItems) !== count($checklistItems)) {
            return null;
        }

        return [
            'checklist_name' => $fallbackChecklistName !== ''
                ? $fallbackChecklistName
                : trim((string) ($payload['checklist_name'] ?? '')),
            'items' => array_map(
                static fn (array $item): array => [
                    'item_id' => (string) ($item['id'] ?? ''),
                    'score' => (int) ($item['score'] ?? 0),
                    'comment' => (string) ($item['comment'] ?? ''),
                ],
                $normalizedItems,
            ),
        ];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<int, array{id:string,label:string,max_points:int}> $checklistItems
     */
    private function hasValidChecklistItemMapping(?array $payload, array $checklistItems = [], ?string &$reason = null): bool
    {
        if ($checklistItems === []) {
            return true;
        }

        $items = $payload['items'] ?? null;
        if (! is_array($items)) {
            $reason = 'Відповідь LLM не містить масив items.';

            return false;
        }

        if (count($items) !== count($checklistItems)) {
            $reason = sprintf(
                'LLM повернула %d пункт(ів) замість очікуваних %d.',
                count($items),
                count($checklistItems)
            );

            return false;
        }

        $seenIds = [];

        foreach ($checklistItems as $index => $checklistItem) {
            $rawItem = $items[$index] ?? null;
            if (! is_array($rawItem)) {
                $reason = sprintf('Елемент items[%d] відсутній або має неправильний формат.', $index);

                return false;
            }

            $actualItemId = trim((string) ($rawItem['item_id'] ?? $rawItem['id'] ?? ''));
            $expectedItemId = $checklistItem['id'];

            if ($actualItemId === '') {
                $reason = sprintf('Елемент items[%d] не містить item_id.', $index);

                return false;
            }

            if ($actualItemId !== $expectedItemId) {
                $reason = sprintf(
                    'LLM порушила порядок або назву item_id: на позиції %d очікувався %s, а отримано %s.',
                    $index + 1,
                    $expectedItemId,
                    $actualItemId
                );

                return false;
            }

            if (isset($seenIds[$actualItemId])) {
                $reason = sprintf('LLM продублювала item_id %s.', $actualItemId);

                return false;
            }

            $seenIds[$actualItemId] = true;
        }

        return true;
    }

    /**
     * @param array<int, mixed> $rawItems
     * @param array<int, array{id:string,label:string,max_points:int}> $checklistItems
     */
    private function canRecoverEvaluationItems(array $rawItems, array $checklistItems): bool
    {
        if ($rawItems === []) {
            return false;
        }

        if ($checklistItems === []) {
            return true;
        }

        if (count($rawItems) === count($checklistItems)) {
            return true;
        }

        return $this->countMatchingEvaluationItems($rawItems, $checklistItems) > 0;
    }

    /**
     * @param array<int, mixed> $rawItems
     * @param array<int, array{id:string,label:string,max_points:int}> $checklistItems
     */
    private function countMatchingEvaluationItems(array $rawItems, array $checklistItems): int
    {
        $expectedIds = [];
        $expectedLabels = [];

        foreach ($checklistItems as $item) {
            $itemId = $this->normalizeComparableKey((string) ($item['id'] ?? ''));
            if ($itemId !== '') {
                $expectedIds[$itemId] = true;
            }

            $label = $this->normalizeComparableKey((string) ($item['label'] ?? ''));
            if ($label !== '') {
                $expectedLabels[$label] = true;
            }
        }

        $matched = 0;

        foreach ($rawItems as $rawItem) {
            if (! is_array($rawItem)) {
                continue;
            }

            $itemId = $this->normalizeComparableKey((string) ($rawItem['item_id'] ?? $rawItem['id'] ?? ''));
            $label = $this->normalizeComparableKey((string) ($rawItem['label'] ?? ''));

            if (($itemId !== '' && isset($expectedIds[$itemId])) || ($label !== '' && isset($expectedLabels[$label]))) {
                $matched += 1;
            }
        }

        return $matched;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function hasValidCommentStyle(?array $payload, ?string &$reason = null): bool
    {
        if (! is_array($payload)) {
            $reason = 'Відповідь LLM не є JSON-об\'єктом.';

            return false;
        }

        $items = $payload['items'] ?? null;
        if (! is_array($items)) {
            $reason = 'Відповідь LLM не містить масив items.';

            return false;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $comment = trim((string) ($item['comment'] ?? ''));
            if ($comment === '') {
                $reason = sprintf('Елемент items[%d] не містить comment.', $index);

                return false;
            }

            if ($this->isRecommendationStyleComment($comment)) {
                $reason = sprintf(
                    'Коментар для items[%d] виглядає як рекомендація або команда, а не як пояснення оцінки: %s',
                    $index,
                    $comment
                );

                return false;
            }

            if (! $this->startsWithAllowedCommentPrefix($comment)) {
                $reason = sprintf(
                    'Коментар для items[%d] має неправильний формат. Потрібне пояснення оцінки, а не довільний текст: %s',
                    $index,
                    $comment
                );

                return false;
            }
        }

        return true;
    }

    private function isRecommendationStyleComment(string $comment): bool
    {
        return preg_match(
            '/^\s*(вказати|запитати|записати|повідомити|перевірити|підтвердити|уточнити|надіслати|сказати|озвучити|назвати|з[’\'`]?ясувати|відповісти|спитати|написати|розрахувати|запропонувати|перетелефонувати|узгодити|нагадати|скинути|відправити)\b/ui',
            $comment
        ) === 1;
    }

    private function startsWithAllowedCommentPrefix(string $comment): bool
    {
        return preg_match(
            '/^\s*(виконано:|частково виконано:|не виконано:|менеджер\b|у транскрипті\b|у розмові\b)/ui',
            $comment
        ) === 1;
    }

    private function isEvaluationLanguageValid(?array $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        foreach ($this->extractEvaluationTexts($payload) as $text) {
            if ($text === '') {
                continue;
            }

            if ($this->containsDisallowedScript($text)) {
                return false;
            }

            if (! $this->containsCyrillicNarrative($text)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function extractEvaluationTexts(array $payload): array
    {
        $texts = [
            trim((string) ($payload['checklist_name'] ?? '')),
        ];

        foreach (($payload['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $texts[] = trim((string) ($item['comment'] ?? ''));
        }

        return $texts;
    }

    private function containsDisallowedScript(string $text): bool
    {
        return preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text) === 1;
    }

    private function containsCyrillicNarrative(string $text): bool
    {
        $lettersOnly = preg_replace('/[^\p{L}]+/u', '', $text) ?? '';
        if ($lettersOnly === '') {
            return true;
        }

        return preg_match('/\p{Cyrillic}/u', $lettersOnly) === 1;
    }

    /**
     * @param array<int, array{id:string,label:string,max_points:int}> $items
     * @return array<string, mixed>|null
     */
    private function retryStructuredEvaluation(
        string $dialogueText,
        string $rawText,
        array $checklist,
        array $items,
        string $invalidReason,
        int $timeout,
        ?callable $reporter = null,
        array $llmSettings = [],
    ): ?array {
        $url = rtrim($this->settings->llmApiUrl(), '/');
        $expectedItemsCount = count($items);
        $allowedItemIds = implode(', ', array_map(
            static fn (array $item): string => $item['id'],
            $items,
        ));

        $retryPrompt = $this->buildPrompt($dialogueText, $rawText, $checklist, $items)."\n\n".
            "Попередня твоя відповідь не може бути прийнята інтерфейсом.\n".
            "Причина помилки: ".$invalidReason."\n".
            "Зроби нову відповідь з нуля, не продовжуй попередній JSON і не копіюй його структуру.\n".
            "Має бути рівно {$expectedItemsCount} елементів items.\n".
            "Дозволені тільки такі item_id і тільки в цьому порядку: {$allowedItemIds}.\n".
            "Поверни тільки новий валідний JSON у потрібній схемі. Усі текстові поля мають бути виключно українською мовою.";

        try {
            $this->emitReporter($reporter, 'prompt', [
                'system_prompt' => $this->systemPrompt($llmSettings),
                'prompt' => $retryPrompt,
            ]);
            $this->emitReporter($reporter, 'log', [
                'channel' => 'warning',
                'message' => 'Перша відповідь LLM була невалідною або неукраїнською. Запускаємо повторну спробу з жорсткішими інструкціями.',
            ]);

            $requestPayload = [
                'model' => $this->model($llmSettings),
                'think' => false,
                'system' => $this->systemPrompt($llmSettings),
                'format' => $this->responseFormatSchema(),
                'options' => array_merge($this->ollamaOptions($llmSettings), [
                    'temperature' => 0.0,
                ]),
                'prompt' => $retryPrompt,
                'keep_alive' => '15s',
            ];

            $decoded = $this->executeGenerateRequest(
                $url,
                $requestPayload,
                $timeout,
                true,
                $reporter,
            );
        } catch (Throwable) {
            return null;
        }

        $decoded = $this->retryGenerateWithoutThinkingWhenResponseEmpty(
            $decoded,
            $url,
            $requestPayload,
            $timeout,
            true,
            $reporter,
        );

        return $this->decodeJsonObject($this->extractGenerateResponseText($decoded));
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

    private function systemPrompt(array $llmSettings = [], array $checklist = []): string
    {
        $checklistPrompt = trim((string) ($checklist['prompt'] ?? ''));
        $additionalInstructions = $checklistPrompt !== ''
            ? "\n\nДодаткові інструкції з поля «Промпт для оцінювання»:\n".$checklistPrompt
            : '';
        $customSystemPrompt = trim((string) ($llmSettings['system_prompt'] ?? ''));
        if ($customSystemPrompt !== '' && ! $this->isLegacySequentialSystemPrompt($customSystemPrompt)) {
            return $customSystemPrompt.$additionalInstructions;
        }

        return CallCenterLlmPrompts::statelessChecklistItemSystemPrompt().$additionalInstructions;
    }

    private function isLegacySequentialSystemPrompt(string $prompt): bool
    {
        $normalized = mb_strtolower($prompt, 'UTF-8');

        return str_contains($normalized, 'послідовного чату')
            || str_contains($normalized, 'на перше повідомлення з транскриптом')
            || str_contains($normalized, 'запам\'ятай цей транскрипт');
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

        return new RuntimeException('Не вдалося підключитися до Ollama для оцінки чек-листа.');
    }

    private function escapeJsonString(string $value): string
    {
        return Str::of($value)
            ->replace('\\', '\\\\')
            ->replace('"', '\"')
            ->toString();
    }

    private function normalizeComparableKey(string $value): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', mb_strtolower(trim($value), 'UTF-8')) ?? '');

        return $normalized;
    }

    /**
     * @return array<string, int|float>
     */
    private function ollamaOptions(array $llmSettings = []): array
    {
        $rawRepeatPenalty = $llmSettings['repeat_penalty'] ?? $llmSettings['repetition_penalty'] ?? null;
        $rawNumPredict = $llmSettings['num_predict'] ?? $llmSettings['max_new_tokens'] ?? null;

        $options = [
            'temperature' => $this->normalizeFloatOption($llmSettings['temperature'] ?? null, $this->settings->llmTemperature(), 0.0, 2.0),
            'num_ctx' => $this->normalizeIntOption($llmSettings['num_ctx'] ?? null, $this->settings->llmNumCtx(), 256, 131072),
            'top_k' => $this->normalizeIntOption($llmSettings['top_k'] ?? null, $this->settings->llmTopK(), 1, 500),
            'top_p' => $this->normalizeFloatOption($llmSettings['top_p'] ?? null, $this->settings->llmTopP(), 0.0, 1.0),
            'repeat_penalty' => $this->normalizeFloatOption($rawRepeatPenalty, $this->settings->llmRepeatPenalty(), 0.0, 5.0),
            'num_predict' => $this->normalizeIntOption($rawNumPredict, $this->settings->llmNumPredict(), -1, 32768),
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
}
