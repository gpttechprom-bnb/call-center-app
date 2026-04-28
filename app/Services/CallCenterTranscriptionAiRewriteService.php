<?php

namespace App\Services;

use App\Support\CallCenterTranscriptionSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CallCenterTranscriptionAiRewriteService
{
    /**
     * @return array{text:string,model:string,message:string,corrections:array<int,array{original:string,replacement:string,count:int}>,raw_corrections:string}
     */
    public function rewrite(
        string $text,
        string $prompt,
        string $model,
        CallCenterTranscriptionSettings $settings,
        array $generationSettings = [],
    ): array {
        return $this->rewriteWithMode($text, $prompt, $model, $settings, $generationSettings, false, null);
    }

    /**
     * @param callable|null $reporter
     * @return array{text:string,model:string,message:string,corrections:array<int,array{original:string,replacement:string,count:int}>,raw_corrections:string}
     */
    public function streamRewrite(
        string $text,
        string $prompt,
        string $model,
        CallCenterTranscriptionSettings $settings,
        array $generationSettings = [],
        ?callable $reporter = null,
    ): array {
        return $this->rewriteWithMode($text, $prompt, $model, $settings, $generationSettings, true, $reporter);
    }

    /**
     * @param callable|null $reporter
     * @return array{text:string,model:string,message:string,corrections:array<int,array{original:string,replacement:string,count:int}>,raw_corrections:string}
     */
    private function rewriteWithMode(
        string $text,
        string $prompt,
        string $model,
        CallCenterTranscriptionSettings $settings,
        array $generationSettings,
        bool $useStreaming,
        ?callable $reporter = null,
    ): array {
        $context = $this->prepareRewriteContext($text, $prompt, $model, $settings, $generationSettings);
        $timeout = $context['timeout'];

        $this->logAiRewriteDiagnostic('request_prepared', [
            'stream' => $useStreaming,
            'url' => $context['url'].'/api/generate',
            'model' => $context['model'],
            'system' => (string) ($context['request_payload']['system'] ?? ''),
            'prompt' => (string) ($context['request_payload']['prompt'] ?? ''),
            'options' => $context['request_payload']['options'] ?? [],
            'think' => $context['request_payload']['think'] ?? null,
            'keep_alive' => $context['request_payload']['keep_alive'] ?? null,
        ]);

        $this->emitReporter($reporter, 'status', [
            'phase' => 'preparing',
            'message' => 'Підготовлено пошук орфографічних виправлень. Надсилаємо запит до Ollama.',
        ]);

        try {
            $decoded = $this->executeGenerateRequest(
                $context['url'],
                $context['request_payload'],
                $timeout,
                $settings,
                $useStreaming,
                $reporter,
            );
        } catch (ConnectionException $exception) {
            throw $this->mapOllamaConnectionException($exception, $timeout);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('Сталася внутрішня помилка під час AI-обробки тексту.');
        }

        $decoded = $this->retryGenerateWithoutThinkingWhenResponseEmpty(
            $decoded,
            $context['url'],
            $context['request_payload'],
            $timeout,
            $settings,
            $useStreaming,
            $reporter,
        );

        $rawCorrectionsText = $this->extractResponseText($decoded);
        if ($rawCorrectionsText === '') {
            throw new RuntimeException('Ollama не повернула список виправлень після AI-обробки. Спробуйте ще раз або перевірте модель і ліміти відповіді.');
        }

        $correctionResult = $this->buildCorrectedTextFromModelResponse($context['source_text'], $rawCorrectionsText);
        $rewrittenText = $correctionResult['text'];
        $appliedCorrections = $correctionResult['corrections'];
        $appliedReplacementCount = array_sum(array_map(
            static fn (array $correction): int => (int) ($correction['count'] ?? 0),
            $appliedCorrections,
        ));

        $this->logAiRewriteDiagnostic('correction_map', [
            'stream' => $useStreaming,
            'model' => $context['model'],
            'raw_corrections' => $rawCorrectionsText,
            'corrections' => $appliedCorrections,
            'applied_replacement_count' => $appliedReplacementCount,
            'text' => $rewrittenText,
            'length' => mb_strlen($rewrittenText, 'UTF-8'),
        ]);

        $completionMessage = $appliedReplacementCount > 0
            ? "AI-обробку завершено. Скрипт застосував {$appliedReplacementCount} точних автозамін."
            : 'AI-обробку завершено. Надійних точних автозамін у тексті не знайдено.';

        $this->emitReporter($reporter, 'status', [
            'phase' => 'completed',
            'message' => $completionMessage,
        ]);

        return [
            'text' => $rewrittenText,
            'model' => $context['model'],
            'message' => $completionMessage,
            'corrections' => $appliedCorrections,
            'raw_corrections' => $rawCorrectionsText,
        ];
    }

    private function systemPrompt(): string
    {
        return 'Ти коректор українських транскриптів дзвінків. Твоє завдання - знайти тільки точкові виправлення: очевидні орфографічні помилки, російські слова, які треба замінити українськими відповідниками, і неіснуючі або неправильно розпізнані слова, які можна впевнено виправити за контекстом. Не переписуй текст повністю, не змінюй сенс, не додавай нові фрази і не виправляй стиль. Відповідай тільки валідним JSON без markdown і пояснень.';
    }

    /**
     * @return array{
     *     source_text:string,
     *     user_prompt:string,
     *     model:string,
     *     url:string,
     *     timeout:int,
     *     request_payload:array<string,mixed>
     * }
     */
    private function prepareRewriteContext(
        string $text,
        string $prompt,
        string $model,
        CallCenterTranscriptionSettings $settings,
        array $generationSettings = [],
    ): array {
        $sourceText = trim($text);
        if ($sourceText === '') {
            throw new RuntimeException('Немає тексту для AI-обробки. Спочатку вставте або отримайте транскрибацію.');
        }

        $userPrompt = trim($prompt);
        if ($userPrompt === '') {
            throw new RuntimeException('Вкажіть промт для AI-обробки тексту.');
        }

        $resolvedModel = trim($model) !== '' ? trim($model) : $settings->llmModel();
        if ($resolvedModel === '') {
            throw new RuntimeException('Не вдалося визначити LLM-модель для AI-обробки тексту.');
        }

        $resolvedProvider = trim((string) ($generationSettings['provider'] ?? $settings->llmProvider())) ?: 'ollama';
        $url = rtrim($settings->llmApiUrl(), '/');
        if ($url === '') {
            throw new RuntimeException('Не вказано URL для Ollama. Перевірте налаштування LLM.');
        }

        $timeout = $this->resolveTimeoutSeconds($settings, $generationSettings);

        return [
            'source_text' => $sourceText,
            'user_prompt' => $userPrompt,
            'model' => $resolvedModel,
            'url' => $url,
            'timeout' => $timeout,
            'request_payload' => [
                'model' => $resolvedModel,
                '_provider' => $resolvedProvider,
                'think' => false,
                'system' => $this->systemPrompt(),
                'prompt' => $this->buildPrompt($userPrompt, $sourceText),
                'options' => $this->ollamaOptions($settings, $sourceText, $generationSettings),
                'keep_alive' => '15s',
            ],
        ];
    }

    private function buildPrompt(string $userPrompt, string $sourceText): string
    {
        return <<<PROMPT
Користувацький промт:
{$userPrompt}

Текст для обробки:
<<<TEXT
{$sourceText}
TEXT

Поверни тільки JSON у такому форматі:
{"corrections":[{"original":"слово з помилкою","replacement":"виправлене слово"}]}

Правила:
1. Не переписуй увесь текст і не повертай фінальну версію тексту.
2. У "original" пиши точний фрагмент з тексту, який треба замінити.
3. У "replacement" пиши тільки виправлений фрагмент.
4. Додавай тільки впевнені точкові виправлення: орфографію, заміну російських слів на українські відповідники, а також неіснуючі або неправильно розпізнані слова, якщо правильний варіант очевидний з контексту.
5. Не змінюй структуру реплік, імена, телефони, артикули, цифри, адреси, бренди або сенс.
6. Якщо виправлень немає, поверни {"corrections":[]}.
7. Не додавай markdown, коментарі, пояснення або стару/нову версію тексту.
PROMPT;
    }

    /**
     * @return array<string, int|float>
     */
    private function ollamaOptions(
        CallCenterTranscriptionSettings $settings,
        string $sourceText,
        array $generationSettings = [],
    ): array
    {
        $rawNumPredict = $generationSettings['num_predict'] ?? $generationSettings['max_new_tokens'] ?? null;
        $rawRepeatPenalty = $generationSettings['repeat_penalty'] ?? $generationSettings['repetition_penalty'] ?? null;
        $hasLocalNumPredict = is_numeric($rawNumPredict);
        $estimatedNumPredict = max(
            $settings->llmNumPredict(),
            min(
                4096,
                max(512, (int) ceil(mb_strlen($sourceText, 'UTF-8') / 12))
            ),
        );
        $numPredict = $hasLocalNumPredict
            ? $this->normalizeIntOption($rawNumPredict, $settings->llmNumPredict(), -1, 32768)
            : $estimatedNumPredict;

        $options = [
            'temperature' => $this->normalizeFloatOption($generationSettings['temperature'] ?? null, $settings->llmTemperature(), 0.0, 2.0),
            'num_ctx' => $this->normalizeIntOption($generationSettings['num_ctx'] ?? null, $settings->llmNumCtx(), 256, 131072),
            'top_k' => $this->normalizeIntOption($generationSettings['top_k'] ?? null, $settings->llmTopK(), 1, 500),
            'top_p' => $this->normalizeFloatOption($generationSettings['top_p'] ?? null, $settings->llmTopP(), 0.0, 1.0),
            'repeat_penalty' => $this->normalizeFloatOption($rawRepeatPenalty, $settings->llmRepeatPenalty(), 0.0, 5.0),
            'num_predict' => $numPredict,
        ];

        $seed = $this->normalizeOptionalIntOption(
            $generationSettings['seed'] ?? $settings->llmSeed(),
            -2147483648,
            2147483647,
        );
        if ($seed !== null) {
            $options['seed'] = $seed;
        }

        return $options;
    }

    private function resolveTimeoutSeconds(CallCenterTranscriptionSettings $settings, array $generationSettings = []): int
    {
        $fallback = $settings->llmTimeoutSeconds();

        return max(
            $fallback,
            $this->normalizeIntOption(
                $generationSettings['timeout_seconds'] ?? null,
                $fallback,
                15,
                3600,
            ),
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
     * @param array<string, mixed> $requestPayload
     * @param callable|null $reporter
     * @return array<string, mixed>
     */
    private function executeGenerateRequest(
        string $url,
        array $requestPayload,
        int $timeout,
        CallCenterTranscriptionSettings $settings,
        bool $useStreaming = false,
        ?callable $reporter = null,
    ): array {
        $provider = trim((string) ($requestPayload['_provider'] ?? 'ollama'));
        unset($requestPayload['_provider']);

        if ($provider !== 'ollama') {
            return $this->executeCloudRewriteRequest($provider, $requestPayload, $timeout, $settings);
        }

        $response = $this->makeOllamaRequest($timeout, $settings, $useStreaming)
            ->post($url.'/api/generate', array_merge($requestPayload, [
                'stream' => $useStreaming,
            ]));

        if (! $useStreaming && ! $response->successful()) {
            $error = $this->extractOllamaErrorMessage($response);

            throw new RuntimeException(
                $error !== ''
                    ? $error
                    : 'Ollama повернув помилку під час AI-обробки тексту.'
            );
        }

        $decoded = $useStreaming
            ? $this->consumeOllamaStreamedResponse($response, $reporter)
            : $response->json();

        if (! $useStreaming) {
            $this->logAiRewriteDiagnostic('non_stream_response', [
                'raw_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Ollama повернула невалідну відповідь для AI-обробки тексту.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $requestPayload
     * @return array<string, mixed>
     */
    private function executeCloudRewriteRequest(
        string $provider,
        array $requestPayload,
        int $timeout,
        CallCenterTranscriptionSettings $settings,
    ): array {
        $apiKey = $settings->llmProviderApiKey($provider);
        if ($apiKey === null) {
            throw new RuntimeException('Додайте API key для провайдера '.$provider.' у налаштуваннях.');
        }

        $model = (string) ($requestPayload['model'] ?? $settings->llmModel());
        $system = (string) ($requestPayload['system'] ?? '');
        $prompt = (string) ($requestPayload['prompt'] ?? '');
        $temperature = (float) ($requestPayload['options']['temperature'] ?? $settings->llmTemperature());
        $maxTokens = max(256, (int) ($requestPayload['options']['num_predict'] ?? $settings->llmNumPredict()));
        $baseUrl = rtrim($settings->llmApiUrl(), '/');

        $request = Http::connectTimeout(5)->timeout($timeout)->acceptJson();
        $response = match ($provider) {
            'anthropic' => $request
                ->withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01'])
                ->post($baseUrl.'/messages', [
                    'model' => $model,
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]),
            'gemini' => $request
                ->post($baseUrl.'/models/'.$model.':generateContent?key='.$apiKey, [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $system."\n\n".$prompt]]]],
                    'generationConfig' => ['temperature' => $temperature, 'maxOutputTokens' => $maxTokens],
                ]),
            default => $request
                ->withToken($apiKey)
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => $temperature,
                ]),
        };

        if (! $response->successful()) {
            throw new RuntimeException($this->extractOllamaErrorMessage($response) ?: 'Платний LLM-провайдер повернув помилку.');
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new RuntimeException('Платний LLM-провайдер повернув невалідну відповідь.');
        }

        return $decoded;
    }

    private function makeOllamaRequest(int $timeout, CallCenterTranscriptionSettings $settings, bool $stream = false)
    {
        $request = Http::connectTimeout(5)
            ->timeout($timeout)
            ->acceptJson();

        if ($stream) {
            $request = $request->withOptions(['stream' => true]);
        }

        if ($settings->hasLlmApiKey()) {
            $request = $request->withToken($settings->llmApiKey());
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $decoded
     * @param array<string, mixed> $requestPayload
     * @param callable|null $reporter
     * @return array<string, mixed>
     */
    private function retryGenerateWithoutThinkingWhenResponseEmpty(
        array $decoded,
        string $url,
        array $requestPayload,
        int $timeout,
        CallCenterTranscriptionSettings $settings,
        bool $useStreaming = false,
        ?callable $reporter = null,
    ): array {
        if ($this->extractResponseText($decoded) !== '' || ! (bool) ($requestPayload['think'] ?? false)) {
            return $decoded;
        }

        $capturedThinking = $this->extractThinkingText($decoded);
        $this->emitReporter($reporter, 'status', [
            'phase' => 'retrying_without_thinking',
            'message' => $capturedThinking !== ''
                ? 'Ollama повернула only-thinking без списку виправлень. Повторюємо запит із think=false.'
                : 'Ollama повернула порожню відповідь у thinking-режимі. Повторюємо запит із think=false.',
        ]);

        try {
            $fallbackPayload = $requestPayload;
            $fallbackPayload['think'] = false;

            $fallbackDecoded = $this->executeGenerateRequest(
                $url,
                $fallbackPayload,
                $timeout,
                $settings,
                $useStreaming,
                $reporter,
            );
        } catch (Throwable) {
            return $decoded;
        }

        if ($capturedThinking !== '' && $this->extractThinkingText($fallbackDecoded) === '') {
            $fallbackDecoded['thinking'] = $capturedThinking;
        }

        return $fallbackDecoded;
    }

    /**
     * @param callable|null $reporter
     * @return array<string, mixed>
     */
    private function consumeOllamaStreamedResponse(Response $response, ?callable $reporter = null): array
    {
        if (! $response->successful()) {
            $error = $this->extractOllamaErrorMessage($response);

            throw new RuntimeException(
                $error !== ''
                    ? $error
                    : 'Ollama повернула помилку під час AI-обробки тексту.'
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
        $doneResponse = $this->extractResponseText($donePayload);
        if ($donePayload !== [] && $doneResponse !== '' && trim($state['response']) === '') {
            $state['response'] = $doneResponse;
        }

        $doneThinking = $this->extractThinkingText($donePayload);
        if ($donePayload !== [] && $doneThinking !== '' && trim($state['thinking']) === '') {
            $state['thinking'] = $doneThinking;
        }

        if (trim($state['response']) === '' && $donePayload === []) {
            throw new RuntimeException('Ollama повернула порожню потокову відповідь.');
        }

        $this->logAiRewriteDiagnostic('stream_assembled', [
            'thinking' => $state['thinking'],
            'response' => $state['response'],
            'response_length' => mb_strlen($state['response'], 'UTF-8'),
            'done_payload' => $donePayload,
        ]);

        return array_merge($donePayload, [
            'thinking' => $state['thinking'],
            'response' => $state['response'],
        ]);
    }

    /**
     * @param array<string, mixed> $state
     * @param callable|null $reporter
     */
    private function consumeOllamaStreamLine(string $line, array &$state, ?callable $reporter = null): void
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return;
        }

        $decoded = json_decode($trimmed, true);
        $this->logAiRewriteDiagnostic('stream_raw_chunk', [
            'raw_line' => $line,
            'decoded_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

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

        $thinkingChunk = $this->extractStreamThinkingChunk($decoded);
        if ($thinkingChunk !== null && $thinkingChunk !== '') {
            $state['thinking'] .= $thinkingChunk;

            if (! $state['thinking_started']) {
                $state['thinking_started'] = true;
                $this->emitReporter($reporter, 'status', [
                    'phase' => 'thinking',
                    'message' => 'Ollama почала reasoning / thinking. Потік видно в живому блоці під текстом.',
                ]);
            }

            $this->emitReporter($reporter, 'thinking', [
                'text' => $state['thinking'],
            ]);
        }

        $responseChunk = $this->extractStreamResponseChunk($decoded);
        if ($responseChunk !== null && $responseChunk !== '') {
            $state['response'] .= $responseChunk;

            if (! $state['response_started']) {
                $state['response_started'] = true;
                $this->emitReporter($reporter, 'status', [
                    'phase' => 'generating',
                    'message' => 'Ollama формує список виправлень. Живий результат оновлюється нижче.',
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
     * @param array<string, mixed> $response
     */
    private function extractStreamResponseChunk(array $response): ?string
    {
        foreach ([
            $response['choices'][0]['message']['content'] ?? null,
            $response['content'][0]['text'] ?? null,
            $response['candidates'][0]['content']['parts'][0]['text'] ?? null,
            $response['response'] ?? null,
            $response['message']['content'] ?? null,
            $response['content'] ?? null,
        ] as $candidate) {
            $normalized = $this->normalizeOllamaStreamTextField($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractStreamThinkingChunk(array $response): ?string
    {
        foreach ([
            $response['thinking'] ?? null,
            $response['message']['thinking'] ?? null,
        ] as $candidate) {
            $normalized = $this->normalizeOllamaStreamTextField($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractResponseText(array $response): string
    {
        foreach ([
            $response['response'] ?? null,
            $response['message']['content'] ?? null,
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
     * @return array{text:string,corrections:array<int,array{original:string,replacement:string,count:int}>}
     */
    private function buildCorrectedTextFromModelResponse(string $sourceText, string $responseText): array
    {
        $corrections = $this->decodeCorrectionsFromResponse($responseText);

        return $this->applyCorrectionsToText($sourceText, $corrections);
    }

    /**
     * @return array<int,array{original:string,replacement:string}>
     */
    private function decodeCorrectionsFromResponse(string $responseText): array
    {
        $jsonCandidate = $this->extractCorrectionJsonCandidate($responseText);
        $decoded = json_decode($jsonCandidate, true);

        if (! is_array($decoded)) {
            $decoded = $this->decodeLooseCorrectionJson($jsonCandidate)
                ?? $this->decodeLooseCorrectionJson($responseText);
        }

        if (! is_array($decoded)) {
            $lineCorrections = $this->decodeLineCorrectionsFromResponse($responseText);
            if ($lineCorrections !== []) {
                return $lineCorrections;
            }

            throw new RuntimeException('Ollama не повернула валідний JSON зі списком виправлень.');
        }

        $items = $decoded['corrections']
            ?? $decoded['replacements']
            ?? $decoded['fixes']
            ?? null;

        if ($items === null && array_key_exists('original', $decoded)) {
            $items = [$decoded];
        }

        if ($items === null && array_key_exists(0, $decoded)) {
            $items = $decoded;
        }

        if (! is_array($items)) {
            throw new RuntimeException('Ollama повернула JSON без масиву corrections.');
        }

        return $this->normalizeCorrectionItems($items);
    }

    /**
     * @return array<int|string,mixed>|null
     */
    private function decodeLooseCorrectionJson(string $responseText): ?array
    {
        $candidate = trim(str_replace("\r\n", "\n", $responseText));
        $candidate = preg_replace('/\A```(?:json)?\s*/iu', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s*```\z/u', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B,");

        if ($candidate === '') {
            return null;
        }

        if (str_starts_with($candidate, '{') && str_ends_with($candidate, '}')) {
            $wrapped = json_decode('['.$candidate.']', true);
            if (is_array($wrapped)) {
                return $wrapped;
            }
        }

        preg_match_all('/\{[^{}]*\}/u', $candidate, $matches);
        $objects = [];

        foreach ($matches[0] ?? [] as $objectJson) {
            $object = json_decode($objectJson, true);

            if (! is_array($object)) {
                continue;
            }

            if (
                ! array_key_exists('original', $object)
                && ! array_key_exists('wrong', $object)
                && ! array_key_exists('source', $object)
                && ! array_key_exists('from', $object)
                && ! array_key_exists('old', $object)
            ) {
                continue;
            }

            $objects[] = $object;
        }

        return $objects !== [] ? $objects : null;
    }

    private function extractCorrectionJsonCandidate(string $responseText): string
    {
        $candidate = trim(str_replace("\r\n", "\n", $responseText));
        $candidate = preg_replace('/\A```(?:json)?\s*/iu', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s*```\z/u', '', $candidate) ?? $candidate;
        $candidate = trim($candidate);

        if (json_decode($candidate, true) !== null || json_last_error() === JSON_ERROR_NONE) {
            return $candidate;
        }

        $objectStart = strpos($candidate, '{');
        $objectEnd = strrpos($candidate, '}');
        if ($objectStart !== false && $objectEnd !== false && $objectEnd > $objectStart) {
            return substr($candidate, $objectStart, $objectEnd - $objectStart + 1);
        }

        $arrayStart = strpos($candidate, '[');
        $arrayEnd = strrpos($candidate, ']');
        if ($arrayStart !== false && $arrayEnd !== false && $arrayEnd > $arrayStart) {
            return substr($candidate, $arrayStart, $arrayEnd - $arrayStart + 1);
        }

        return $candidate;
    }

    /**
     * @return array<int,array{original:string,replacement:string}>
     */
    private function decodeLineCorrectionsFromResponse(string $responseText): array
    {
        $lines = preg_split('/\R/u', trim($responseText)) ?: [];
        $items = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            $line = preg_replace('/\A[-*]\s*/u', '', $line) ?? $line;

            if (! preg_match('/\A["“”]?(.{1,120}?)["“”]?\s*(?:=>|->|→)\s*["“”]?(.{1,120}?)["“”]?\z/u', $line, $matches)) {
                continue;
            }

            $items[] = [
                'original' => $matches[1],
                'replacement' => $matches[2],
            ];
        }

        return $this->normalizeCorrectionItems($items);
    }

    /**
     * @param array<int|string,mixed> $items
     * @return array<int,array{original:string,replacement:string}>
     */
    private function normalizeCorrectionItems(array $items): array
    {
        $corrections = [];
        $seen = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $original = $this->normalizeCorrectionText(
                $item['original']
                ?? $item['wrong']
                ?? $item['source']
                ?? $item['from']
                ?? $item['old']
                ?? '',
            );
            $replacement = $this->normalizeCorrectionText(
                $item['replacement']
                ?? $item['corrected']
                ?? $item['correction']
                ?? $item['to']
                ?? $item['new']
                ?? '',
            );

            if (
                $original === ''
                || $replacement === ''
                || $original === $replacement
                || mb_strlen($original, 'UTF-8') < 2
                || mb_strlen($original, 'UTF-8') > 160
                || mb_strlen($replacement, 'UTF-8') > 160
            ) {
                continue;
            }

            $key = mb_strtolower($original.'=>'.$replacement, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $corrections[] = [
                'original' => $original,
                'replacement' => $replacement,
            ];

            if (count($corrections) >= 500) {
                break;
            }
        }

        usort(
            $corrections,
            static fn (array $left, array $right): int => mb_strlen($right['original'], 'UTF-8') <=> mb_strlen($left['original'], 'UTF-8'),
        );

        return $corrections;
    }

    private function normalizeCorrectionText(mixed $value): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<int,array{original:string,replacement:string}> $corrections
     * @return array{text:string,corrections:array<int,array{original:string,replacement:string,count:int}>}
     */
    private function applyCorrectionsToText(string $sourceText, array $corrections): array
    {
        $correctedText = $sourceText;
        $appliedCorrections = [];

        foreach ($corrections as $correction) {
            $original = $correction['original'];
            $replacement = $correction['replacement'];
            $count = 0;

            if ($this->shouldUseWordBoundaries($original)) {
                $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote($original, '/').'(?![\p{L}\p{N}_])/u';
                $nextText = preg_replace_callback(
                    $pattern,
                    static fn (): string => $replacement,
                    $correctedText,
                    -1,
                    $count,
                );

                if ($nextText !== null) {
                    $correctedText = $nextText;
                } else {
                    $correctedText = str_replace($original, $replacement, $correctedText, $count);
                }
            } else {
                $correctedText = str_replace($original, $replacement, $correctedText, $count);
            }

            if ($count <= 0) {
                continue;
            }

            $appliedCorrections[] = [
                'original' => $original,
                'replacement' => $replacement,
                'count' => $count,
            ];
        }

        return [
            'text' => $correctedText,
            'corrections' => $appliedCorrections,
        ];
    }

    private function shouldUseWordBoundaries(string $value): bool
    {
        return preg_match('/\A[\p{L}\p{N}_]/u', $value) === 1
            && preg_match('/[\p{L}\p{N}_]\z/u', $value) === 1;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractThinkingText(array $response): string
    {
        foreach ([
            $response['thinking'] ?? null,
            $response['message']['thinking'] ?? null,
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
            return trim(str_replace("\r\n", "\n", $value));
        }

        if (! is_array($value)) {
            return '';
        }

        $chunks = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $chunk = trim(str_replace("\r\n", "\n", $item));

                if ($chunk !== '') {
                    $chunks[] = $chunk;
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $chunk = trim(str_replace("\r\n", "\n", (string) ($item['text'] ?? $item['content'] ?? '')));

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
        }

        return trim(implode("\n", $chunks));
    }

    private function normalizeOllamaStreamTextField(mixed $value): ?string
    {
        if (is_string($value)) {
            return str_replace("\r\n", "\n", $value);
        }

        if (! is_array($value)) {
            return null;
        }

        $chunks = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $chunks[] = str_replace("\r\n", "\n", $item);
                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            if (array_key_exists('text', $item)) {
                $chunks[] = str_replace("\r\n", "\n", (string) $item['text']);
                continue;
            }

            if (array_key_exists('content', $item)) {
                $chunks[] = str_replace("\r\n", "\n", (string) $item['content']);
            }
        }

        return implode('', $chunks);
    }

    private function extractOllamaErrorMessage(Response $response): string
    {
        $payload = $response->json();
        if (is_array($payload)) {
            $candidate = trim((string) ($payload['error'] ?? $payload['message'] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return trim((string) $response->body());
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
                "Ollama не встигла обробити текст за {$timeout} сек. Спробуйте коротший текст, швидшу модель або менший промт."
            );
        }

        return new RuntimeException('Не вдалося підключитися до Ollama для AI-обробки тексту.');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logAiRewriteDiagnostic(string $event, array $context = []): void
    {
        try {
            Log::info('call_center_ai_rewrite.'.$event, $context);
        } catch (Throwable) {
            // Logging must never interrupt the user-facing AI rewrite flow.
        }
    }

    /**
     * @param callable|null $reporter
     * @param array<string, mixed> $payload
     */
    private function emitReporter(?callable $reporter, string $type, array $payload = []): void
    {
        if (! $reporter) {
            return;
        }

        $reporter($type, $payload);
    }
}
