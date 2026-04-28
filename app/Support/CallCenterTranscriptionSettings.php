<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CallCenterTranscriptionSettings
{
    /**
     * @return array<int, string>
     */
    public function availableTranscriptionProviders(): array
    {
        return ['faster_whisper', 'ollama', 'openai'];
    }

    /**
     * @return array<int, string>
     */
    public function availableModels(): array
    {
        $models = config('call_center.transcription.available_models', []);

        return array_values(array_filter(
            is_array($models) ? $models : [],
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
    }

    /**
     * @return array<int, string>
     */
    public function availableLlmProviders(): array
    {
        return ['ollama', 'openai', 'anthropic', 'openrouter', 'gemini'];
    }

    public function currentModel(): string
    {
        $storedModel = $this->read()['transcription_model'] ?? null;
        $provider = $this->transcriptionProvider();
        $defaultModel = $this->defaultTranscriptionModel($provider);

        if (is_string($storedModel)) {
            $normalizedModel = trim($storedModel);
            if (
                $normalizedModel !== ''
                && (
                    $provider !== 'faster_whisper'
                    || in_array($normalizedModel, $this->availableModels(), true)
                )
            ) {
                return $normalizedModel;
            }
        }

        return $defaultModel;
    }

    public function transcriptionProvider(): string
    {
        $storedProvider = trim((string) ($this->read()['transcription_provider'] ?? ''));

        return in_array($storedProvider, $this->availableTranscriptionProviders(), true)
            ? $storedProvider
            : 'faster_whisper';
    }

    /**
     * @return array<int, string>
     */
    public function availableTranscriptionProviderModels(?string $provider = null): array
    {
        $resolvedProvider = in_array($provider, $this->availableTranscriptionProviders(), true)
            ? (string) $provider
            : $this->transcriptionProvider();

        return match ($resolvedProvider) {
            'openai' => $this->normalizeTranscriptionModelList([
                'gpt-4o-mini-transcribe',
                'gpt-4o-transcribe',
                'gpt-4o-transcribe-diarize',
                'whisper-1',
            ], $this->transcriptionProvider() === 'openai' ? $this->currentModel() : $this->defaultTranscriptionModel('openai')),
            'ollama' => $this->fetchOllamaModels(
                $this->transcriptionApiUrl('ollama'),
                $this->transcriptionApiKey('ollama'),
            ),
            default => $this->normalizeTranscriptionModelList(
                $this->availableModels(),
                $this->transcriptionProvider() === 'faster_whisper' ? $this->currentModel() : $this->defaultTranscriptionModel('faster_whisper'),
            ),
        };
    }

    public function transcriptionApiUrl(?string $provider = null): string
    {
        $resolvedProvider = in_array($provider, $this->availableTranscriptionProviders(), true)
            ? (string) $provider
            : $this->transcriptionProvider();

        if ($resolvedProvider === 'faster_whisper') {
            return '';
        }

        $storedUrl = trim((string) ($this->read()['transcription_api_url'] ?? ''));
        if ($storedUrl !== '') {
            return $storedUrl;
        }

        if ($resolvedProvider === 'ollama') {
            return $this->llmProvider() === 'ollama'
                ? $this->llmApiUrl()
                : trim((string) config('call_center.evaluation.ollama_url', 'http://llm_yaprofi_ollama:11434'));
        }

        return $this->defaultLlmApiUrl($resolvedProvider);
    }

    public function transcriptionApiKey(?string $provider = null): ?string
    {
        $resolvedProvider = in_array($provider, $this->availableTranscriptionProviders(), true)
            ? (string) $provider
            : $this->transcriptionProvider();

        if ($resolvedProvider === 'openai') {
            return $this->llmProviderApiKey('openai');
        }

        if ($resolvedProvider === 'ollama') {
            return $this->llmProvider() === 'ollama' ? $this->llmApiKey() : null;
        }

        return null;
    }

    public function llmProvider(): string
    {
        $storedProvider = trim((string) ($this->read()['llm_provider'] ?? ''));
        $defaultProvider = trim((string) config('call_center.evaluation.provider', 'ollama'));

        return in_array($storedProvider, $this->availableLlmProviders(), true)
            ? $storedProvider
            : $defaultProvider;
    }

    public function llmApiUrl(): string
    {
        $storedUrl = trim((string) ($this->read()['llm_api_url'] ?? ''));
        if ($storedUrl !== '') {
            return $storedUrl;
        }

        return $this->defaultLlmApiUrl($this->llmProvider());
    }

    public function llmApiKey(): ?string
    {
        $providerKey = $this->llmProviderApiKey($this->llmProvider());
        if ($providerKey !== null) {
            return $providerKey;
        }

        $storedKey = trim((string) ($this->read()['llm_api_key'] ?? ''));
        if ($storedKey !== '') {
            return $storedKey;
        }

        $configuredKey = trim((string) config('call_center.evaluation.api_key', ''));

        return $configuredKey !== '' ? $configuredKey : null;
    }

    public function hasLlmApiKey(): bool
    {
        return $this->llmApiKey() !== null;
    }

    public function llmProviderApiKey(string $provider): ?string
    {
        $provider = trim($provider);
        if ($provider === '') {
            return null;
        }

        $keys = $this->llmProviderApiKeys();
        $key = trim((string) ($keys[$provider] ?? ''));

        return $key !== '' ? $key : null;
    }

    public function hasLlmProviderApiKey(string $provider): bool
    {
        return $this->llmProviderApiKey($provider) !== null;
    }

    /**
     * @return array<string, string>
     */
    public function llmProviderApiKeys(): array
    {
        $keys = $this->read()['llm_api_keys'] ?? [];

        if (! is_array($keys)) {
            return [];
        }

        $normalized = [];
        foreach ($this->availableLlmProviders() as $provider) {
            $key = trim((string) ($keys[$provider] ?? ''));
            if ($key !== '') {
                $normalized[$provider] = $key;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, bool>
     */
    public function llmProviderApiKeyStatus(): array
    {
        $status = [];

        foreach (['openai', 'anthropic', 'openrouter', 'gemini'] as $provider) {
            $status[$provider] = $this->hasLlmProviderApiKey($provider);
        }

        return $status;
    }

    /**
     * @return array<string, string|null>
     */
    public function llmProviderApiKeyPreviews(): array
    {
        $previews = [];

        foreach (['openai', 'anthropic', 'openrouter', 'gemini'] as $provider) {
            $previews[$provider] = $this->maskApiKey($this->llmProviderApiKey($provider));
        }

        return $previews;
    }

    public function llmModel(): string
    {
        $storedModel = trim((string) ($this->read()['llm_model'] ?? ''));
        if ($storedModel !== '') {
            return $storedModel;
        }

        return trim((string) config('call_center.evaluation.ollama_model', 'qwen2.5:7b'));
    }

    private function defaultLlmApiUrl(string $provider): string
    {
        return match ($provider) {
            'openai' => 'https://api.openai.com/v1',
            'anthropic' => 'https://api.anthropic.com/v1',
            'openrouter' => 'https://openrouter.ai/api/v1',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
            default => trim((string) config('call_center.evaluation.ollama_url', 'http://llm_yaprofi_ollama:11434')),
        };
    }

    /**
     * @return array<int, string>
     */
    private function cloudLlmModels(string $provider): array
    {
        $models = match ($provider) {
            'openai' => ['gpt-4.1-mini', 'gpt-4.1', 'gpt-4o-mini', 'gpt-4o'],
            'anthropic' => ['claude-3-5-haiku-latest', 'claude-sonnet-4-5', 'claude-opus-4-1'],
            'openrouter' => ['openai/gpt-4.1-mini', 'anthropic/claude-sonnet-4.5', 'google/gemini-2.5-flash', 'qwen/qwen3-235b-a22b'],
            'gemini' => ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-1.5-flash'],
            default => [$this->llmModel()],
        };

        $models[] = $this->llmModel();

        return array_values(array_unique(array_filter($models)));
    }

    /**
     * @return array<int, string>
     */
    public function availableLlmModels(?string $apiUrl = null, ?string $provider = null, ?string $apiKey = null): array
    {
        $resolvedProvider = in_array($provider, $this->availableLlmProviders(), true)
            ? $provider
            : $this->llmProvider();
        $resolvedUrl = rtrim(trim((string) (
            $apiUrl
            ?? ($resolvedProvider === $this->llmProvider()
                ? $this->llmApiUrl()
                : $this->defaultLlmApiUrl($resolvedProvider))
        )), '/');
        $resolvedApiKey = trim((string) (
            $apiKey
            ?? ($resolvedProvider === 'ollama'
                ? ($resolvedProvider === $this->llmProvider() ? $this->llmApiKey() : null)
                : $this->llmProviderApiKey($resolvedProvider))
        ));

        if ($resolvedProvider === 'ollama') {
            return $this->fetchOllamaModels($resolvedUrl, $resolvedApiKey !== '' ? $resolvedApiKey : null);
        }

        return $this->fetchCloudModels(
            $resolvedProvider,
            $resolvedUrl,
            $resolvedApiKey !== '' ? $resolvedApiKey : null,
        );
    }

    /**
     * @return array<int, string>
     */
    private function fetchOllamaModels(string $apiUrl, ?string $apiKey = null): array
    {
        if ($apiUrl === '') {
            return [$this->llmModel()];
        }

        try {
            $request = Http::timeout(8)->acceptJson();

            if ($apiKey !== null && $apiKey !== '') {
                $request = $request->withToken($apiKey);
            }

            $response = $request->get($apiUrl.'/api/tags');
            if (! $response->successful()) {
                return [$this->llmModel()];
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return [$this->llmModel()];
            }

            $models = [];
            foreach (($payload['models'] ?? []) as $model) {
                if (! is_array($model)) {
                    continue;
                }

                $name = trim((string) ($model['name'] ?? $model['model'] ?? ''));
                if ($name !== '') {
                    $models[] = $name;
                }
            }

            return $this->normalizeLlmModelList($models, [$this->llmModel()]);
        } catch (Throwable) {
            return [$this->llmModel()];
        }
    }

    /**
     * @return array<int, string>
     */
    private function fetchCloudModels(string $provider, string $apiUrl, ?string $apiKey = null): array
    {
        $fallback = $this->cloudLlmModels($provider);

        if ($apiUrl === '' || $apiKey === null || $apiKey === '') {
            return $fallback;
        }

        try {
            $response = match ($provider) {
                'openai' => Http::timeout(12)
                    ->acceptJson()
                    ->withToken($apiKey)
                    ->get($apiUrl.'/models'),
                'anthropic' => Http::timeout(12)
                    ->acceptJson()
                    ->withHeaders([
                        'x-api-key' => $apiKey,
                        'anthropic-version' => '2023-06-01',
                    ])
                    ->get($apiUrl.'/models'),
                'openrouter' => Http::timeout(12)
                    ->acceptJson()
                    ->withToken($apiKey)
                    ->get($apiUrl.'/models'),
                'gemini' => Http::timeout(12)
                    ->acceptJson()
                    ->get($apiUrl.'/models?key='.rawurlencode($apiKey)),
                default => null,
            };

            if ($response === null || ! $response->successful()) {
                return $fallback;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return $fallback;
            }

            $models = match ($provider) {
                'openai', 'anthropic', 'openrouter' => $this->extractDataModels($payload),
                'gemini' => $this->extractGeminiModels($payload),
                default => [],
            };

            return $this->normalizeLlmModelList($models, $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function extractDataModels(array $payload): array
    {
        $models = [];

        foreach (($payload['data'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            if ($id !== '') {
                $models[] = $id;
            }
        }

        return $models;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function extractGeminiModels(array $payload): array
    {
        $models = [];

        foreach (($payload['models'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $supportedMethods = array_map(
                static fn (mixed $method): string => trim((string) $method),
                is_array($item['supportedGenerationMethods'] ?? null) ? $item['supportedGenerationMethods'] : [],
            );

            if ($supportedMethods !== [] && ! in_array('generateContent', $supportedMethods, true)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $name = preg_replace('#^models/#', '', $name) ?? $name;

            if ($name !== '') {
                $models[] = $name;
            }
        }

        return $models;
    }

    /**
     * @param array<int, string> $models
     * @param array<int, string> $fallback
     * @return array<int, string>
     */
    private function normalizeLlmModelList(array $models, array $fallback = []): array
    {
        $normalized = array_values(array_unique(array_filter([
            ...$models,
            ...$fallback,
            $this->llmModel(),
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')));

        return $normalized !== [] ? $normalized : [$this->llmModel()];
    }

    public function llmTemperature(): float
    {
        $profile = $this->llmProfile($this->llmProvider(), $this->llmModel());

        return $this->normalizeFloat(
            $this->read()['llm_temperature'] ?? null,
            $profile['temperature_default'],
            0.0,
            $profile['temperature_max'],
        );
    }

    public function llmNumCtx(): int
    {
        $profile = $this->llmProfile($this->llmProvider(), $this->llmModel());

        return $this->normalizeInt(
            $this->read()['llm_num_ctx'] ?? null,
            $profile['num_ctx_default'],
            256,
            $profile['num_ctx_max'],
        );
    }

    public function llmTopK(): int
    {
        return $this->normalizeInt(
            $this->read()['llm_top_k'] ?? null,
            (int) config('call_center.evaluation.options.top_k', 40),
            1,
            500,
        );
    }

    public function llmTopP(): float
    {
        return $this->normalizeFloat(
            $this->read()['llm_top_p'] ?? null,
            (float) config('call_center.evaluation.options.top_p', 0.9),
            0.0,
            1.0,
        );
    }

    public function llmRepeatPenalty(): float
    {
        return $this->normalizeFloat(
            $this->read()['llm_repeat_penalty'] ?? null,
            (float) config('call_center.evaluation.options.repeat_penalty', 1.1),
            0.0,
            5.0,
        );
    }

    public function llmSeed(): ?int
    {
        $storedSeed = $this->read()['llm_seed'] ?? config('call_center.evaluation.options.seed', '');

        if ($storedSeed === '' || $storedSeed === null) {
            return null;
        }

        return $this->normalizeInt($storedSeed, 0, -2147483648, 2147483647);
    }

    public function llmNumPredict(): int
    {
        $profile = $this->llmProfile($this->llmProvider(), $this->llmModel());

        return $this->normalizeInt(
            $this->read()['llm_num_predict'] ?? null,
            $profile['num_predict_default'],
            $profile['num_predict_min'],
            $profile['num_predict_max'],
        );
    }

    public function llmTimeoutSeconds(): int
    {
        $defaultTimeout = (int) config('call_center.evaluation.timeout_seconds', 600);
        $resolvedTimeout = $this->normalizeInt(
            $this->read()['llm_timeout_seconds'] ?? null,
            $defaultTimeout,
            15,
            3600,
        );

        return max($defaultTimeout, $resolvedTimeout);
    }

    public function llmBackgroundTimeoutSeconds(): int
    {
        $configuredTimeout = (int) config('call_center.evaluation.background_timeout_seconds', 7200);

        return max($this->llmTimeoutSeconds(), $configuredTimeout);
    }

    public function llmThinkingEnabled(): bool
    {
        return false;
    }

    /**
     * @return array{
     *     transcription_provider: string,
     *     transcription_available_providers: array<int, string>,
     *     transcription_model: string,
     *     available_models: array<int, string>,
     *     transcription_provider_available_models: array<int, string>,
     *     transcription_initial_prompt: string,
     *     speaker_diarization_enabled: bool,
     *     speaker_diarization_has_token: bool,
     *     speaker_diarization_provider_model: string,
     *     llm_provider: string,
     *     llm_available_providers: array<int, string>,
     *     llm_api_url: string,
     *     llm_has_api_key: bool,
     *     llm_api_key_status: array<string, bool>,
     *     llm_api_key_previews: array<string, string|null>,
     *     llm_model: string,
     *     llm_available_models: array<int, string>,
     *     llm_temperature: float,
     *     llm_num_ctx: int,
     *     llm_top_k: int,
     *     llm_top_p: float,
     *     llm_repeat_penalty: float,
     *     llm_seed: ?int,
     *     llm_num_predict: int,
     *     llm_timeout_seconds: int,
     *     llm_thinking_enabled: bool,
     *     llm_background_timeout_seconds: int
     * }
     */
    public function payload(): array
    {
        return [
            'transcription_provider' => $this->transcriptionProvider(),
            'transcription_available_providers' => $this->availableTranscriptionProviders(),
            'transcription_model' => $this->currentModel(),
            'available_models' => $this->availableModels(),
            'transcription_provider_available_models' => $this->availableTranscriptionProviderModels(),
            'transcription_initial_prompt' => $this->transcriptionInitialPrompt(),
            'speaker_diarization_enabled' => $this->speakerDiarizationEnabled(),
            'speaker_diarization_has_token' => $this->hasSpeakerDiarizationToken(),
            'speaker_diarization_provider_model' => $this->speakerDiarizationProviderModel(),
            'llm_provider' => $this->llmProvider(),
            'llm_available_providers' => $this->availableLlmProviders(),
            'llm_api_url' => $this->llmApiUrl(),
            'llm_has_api_key' => $this->hasLlmApiKey(),
            'llm_api_key_status' => $this->llmProviderApiKeyStatus(),
            'llm_api_key_previews' => $this->llmProviderApiKeyPreviews(),
            'llm_model' => $this->llmModel(),
            'llm_available_models' => $this->availableLlmModels(),
            'llm_temperature' => $this->llmTemperature(),
            'llm_num_ctx' => $this->llmNumCtx(),
            'llm_top_k' => $this->llmTopK(),
            'llm_top_p' => $this->llmTopP(),
            'llm_repeat_penalty' => $this->llmRepeatPenalty(),
            'llm_seed' => $this->llmSeed(),
            'llm_num_predict' => $this->llmNumPredict(),
            'llm_timeout_seconds' => $this->llmTimeoutSeconds(),
            'llm_background_timeout_seconds' => $this->llmBackgroundTimeoutSeconds(),
            'llm_thinking_enabled' => $this->llmThinkingEnabled(),
        ];
    }

    public function transcriptionInitialPrompt(): string
    {
        return $this->normalizeInitialPrompt(
            (string) ($this->read()['transcription_initial_prompt'] ?? config('call_center.transcription.initial_prompt', ''))
        );
    }

    public function speakerDiarizationEnabled(): bool
    {
        $storedValue = $this->read()['speaker_diarization_enabled'] ?? null;

        if (is_bool($storedValue)) {
            return $storedValue;
        }

        if (is_string($storedValue) || is_numeric($storedValue)) {
            return filter_var($storedValue, FILTER_VALIDATE_BOOL);
        }

        return (bool) config('call_center.transcription.diarization.enabled', false);
    }

    public function hasSpeakerDiarizationToken(): bool
    {
        return $this->speakerDiarizationToken() !== null;
    }

    public function speakerDiarizationToken(): ?string
    {
        $storedToken = trim((string) ($this->read()['speaker_diarization_token'] ?? ''));
        if ($storedToken !== '') {
            return $storedToken;
        }

        $configuredToken = trim((string) config('call_center.transcription.diarization.auth_token', ''));

        return $configuredToken !== '' ? $configuredToken : null;
    }

    public function speakerDiarizationProviderModel(): string
    {
        return (string) config(
            'call_center.transcription.diarization.provider_model',
            'pyannote/speaker-diarization-community-1',
        );
    }

    /**
     * @param array{
     *     transcription_provider?: string,
     *     transcription_model: string,
     *     transcription_initial_prompt?: string|null,
     *     speaker_diarization_enabled: bool,
     *     speaker_diarization_token?: string|null,
     *     llm_provider: string,
     *     llm_api_url: string,
     *     llm_api_key?: string|null,
     *     llm_api_keys?: array<string, string|null>,
     *     llm_api_key_deletes?: array<string, bool>,
     *     llm_model: string,
     *     llm_temperature: float|int|string,
     *     llm_num_ctx: int|string,
     *     llm_top_k: int|string,
     *     llm_top_p: float|int|string,
     *     llm_repeat_penalty: float|int|string,
     *     llm_seed?: int|string|null,
     *     llm_num_predict: int|string,
     *     llm_timeout_seconds: int|string,
     *     llm_thinking_enabled?: bool
     * } $attributes
     */
    public function save(array $attributes): array
    {
        $provider = trim((string) ($attributes['transcription_provider'] ?? $this->transcriptionProvider()));
        if (! in_array($provider, $this->availableTranscriptionProviders(), true)) {
            throw new RuntimeException('Оберіть коректний оператор транскрибації.');
        }

        $model = trim((string) $attributes['transcription_model']);
        if ($model === '') {
            throw new RuntimeException('Оберіть модель транскрибації.');
        }

        if ($provider === 'faster_whisper' && ! in_array($model, $this->availableModels(), true)) {
            throw new RuntimeException('Оберіть коректну модель faster-whisper.');
        }

        $provider = trim((string) $attributes['llm_provider']);
        if (! in_array($provider, $this->availableLlmProviders(), true)) {
            throw new RuntimeException('Оберіть коректний провайдер LLM.');
        }

        $llmModel = trim((string) $attributes['llm_model']);
        if ($llmModel === '') {
            throw new RuntimeException('Вкажіть назву LLM-моделі.');
        }

        $profile = $this->llmProfile($provider, $llmModel);

        $existing = $this->read();
        $tokenInput = array_key_exists('speaker_diarization_token', $attributes)
            ? trim((string) ($attributes['speaker_diarization_token'] ?? ''))
            : null;
        $storedToken = $tokenInput !== null && $tokenInput !== ''
            ? $tokenInput
            : trim((string) ($existing['speaker_diarization_token'] ?? ''));

        $llmApiKeyInput = array_key_exists('llm_api_key', $attributes)
            ? trim((string) ($attributes['llm_api_key'] ?? ''))
            : null;
        $storedLlmApiKey = $llmApiKeyInput !== null && $llmApiKeyInput !== ''
            ? $llmApiKeyInput
            : trim((string) ($existing['llm_api_key'] ?? ''));
        $storedProviderApiKeys = $this->mergeLlmProviderApiKeys(
            is_array($existing['llm_api_keys'] ?? null) ? $existing['llm_api_keys'] : [],
            is_array($attributes['llm_api_keys'] ?? null) ? $attributes['llm_api_keys'] : [],
            is_array($attributes['llm_api_key_deletes'] ?? null) ? $attributes['llm_api_key_deletes'] : [],
        );

        $defaultTimeout = (int) config('call_center.evaluation.timeout_seconds', 600);
        $resolvedTimeout = $this->normalizeInt($attributes['llm_timeout_seconds'], $defaultTimeout, 15, 3600);

        Storage::disk('local')->put(
            $this->settingsPath(),
            json_encode([
                'transcription_provider' => trim((string) ($attributes['transcription_provider'] ?? $this->transcriptionProvider())),
                'transcription_model' => $model,
                'transcription_initial_prompt' => $this->normalizeInitialPrompt($attributes['transcription_initial_prompt'] ?? ''),
                'speaker_diarization_enabled' => (bool) $attributes['speaker_diarization_enabled'],
                'speaker_diarization_token' => $storedToken,
                'llm_provider' => $provider,
                'llm_api_url' => trim((string) $attributes['llm_api_url']),
                'llm_api_key' => $storedLlmApiKey,
                'llm_api_keys' => $storedProviderApiKeys,
                'llm_model' => $llmModel,
                'llm_temperature' => $this->normalizeFloat($attributes['llm_temperature'], $profile['temperature_default'], 0.0, $profile['temperature_max']),
                'llm_num_ctx' => $this->normalizeInt($attributes['llm_num_ctx'], $profile['num_ctx_default'], 256, $profile['num_ctx_max']),
                'llm_top_k' => $this->normalizeInt($attributes['llm_top_k'], 40, 1, 500),
                'llm_top_p' => $this->normalizeFloat($attributes['llm_top_p'], 0.9, 0.0, 1.0),
                'llm_repeat_penalty' => $this->normalizeFloat($attributes['llm_repeat_penalty'], 1.1, 0.0, 5.0),
                'llm_seed' => $this->normalizeOptionalInt($attributes['llm_seed'] ?? null, -2147483648, 2147483647),
                'llm_num_predict' => $this->normalizeInt($attributes['llm_num_predict'], $profile['num_predict_default'], $profile['num_predict_min'], $profile['num_predict_max']),
                'llm_timeout_seconds' => max($defaultTimeout, $resolvedTimeout),
                'llm_thinking_enabled' => false,
                'updated_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $path = $this->settingsPath();

        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) Storage::disk('local')->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeInt(mixed $value, int $default, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) round((float) $value)));
    }

    private function normalizeOptionalInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return max($min, min($max, (int) round((float) $value)));
    }

    private function normalizeInitialPrompt(mixed $value): string
    {
        $normalized = preg_replace("/\r\n?/", "\n", trim((string) $value));
        $normalized = str_replace("\u{00A0}", ' ', $normalized ?? '');
        $normalized = preg_replace("/\t+/", "\n", $normalized) ?? $normalized;
        $normalized = preg_replace("/[ \f\v]{3,}/", "\n", $normalized) ?? $normalized;

        $lines = array_filter(array_map(
            static fn (string $line): string => trim(preg_replace('/[^\S\n]+/u', ' ', $line) ?? $line),
            explode("\n", $normalized),
        ), static fn (string $line): bool => $line !== '');

        return mb_substr(implode("\n", $lines), 0, 4000, 'UTF-8');
    }

    private function defaultTranscriptionModel(string $provider): string
    {
        return match ($provider) {
            'openai' => 'gpt-4o-mini-transcribe',
            'ollama' => 'whisper:latest',
            default => (string) config('call_center.transcription.model', 'large-v3'),
        };
    }

    /**
     * @param array<int, string> $models
     * @return array<int, string>
     */
    private function normalizeTranscriptionModelList(array $models, ?string $preferredModel = null): array
    {
        $normalized = array_values(array_unique(array_filter([
            ...$models,
            $preferredModel ?? $this->currentModel(),
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')));

        return $normalized !== [] ? $normalized : [$preferredModel ?? $this->currentModel()];
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $incoming
     * @param array<string, mixed> $deletes
     * @return array<string, string>
     */
    private function mergeLlmProviderApiKeys(array $existing, array $incoming, array $deletes = []): array
    {
        $merged = [];

        foreach ($this->availableLlmProviders() as $provider) {
            $existingKey = trim((string) ($existing[$provider] ?? ''));
            $incomingKey = trim((string) ($incoming[$provider] ?? ''));
            $deleteRequested = filter_var($deletes[$provider] ?? false, FILTER_VALIDATE_BOOL);
            $key = $incomingKey !== ''
                ? $incomingKey
                : ($deleteRequested ? '' : $existingKey);

            if ($key !== '') {
                $merged[$provider] = $key;
            }
        }

        return $merged;
    }

    private function maskApiKey(?string $key): ?string
    {
        $key = trim((string) $key);
        if ($key === '') {
            return null;
        }

        $length = mb_strlen($key, 'UTF-8');
        if ($length <= 10) {
            return mb_substr($key, 0, 3, 'UTF-8').'...'.mb_substr($key, -2, null, 'UTF-8');
        }

        return mb_substr($key, 0, 6, 'UTF-8').'...'.mb_substr($key, -4, null, 'UTF-8');
    }

    private function normalizeFloat(mixed $value, float $default, float $min, float $max): float
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (float) $value));
    }

    /**
     * @return array{
     *     temperature_default: float,
     *     temperature_max: float,
     *     num_ctx_default: int,
     *     num_ctx_max: int,
     *     num_predict_default: int,
     *     num_predict_min: int,
     *     num_predict_max: int,
     *     thinking_default: bool
     * }
     */
    private function llmProfile(string $provider, string $model): array
    {
        $defaultTemperature = (float) config('call_center.evaluation.temperature', 0.2);
        $defaultNumCtx = (int) config('call_center.evaluation.options.num_ctx', 4096);
        $defaultNumPredict = (int) config('call_center.evaluation.options.num_predict', 256);
        $profile = [
            'temperature_default' => $defaultTemperature,
            'temperature_max' => 2.0,
            'num_ctx_default' => $defaultNumCtx,
            'num_ctx_max' => 131072,
            'num_predict_default' => $defaultNumPredict,
            'num_predict_min' => -1,
            'num_predict_max' => 32768,
            'thinking_default' => false,
        ];

        return $profile;
    }

    protected function settingsPath(): string
    {
        return trim((string) config('call_center.transcription.settings_path', 'call-center/settings.json'), '/');
    }
}
