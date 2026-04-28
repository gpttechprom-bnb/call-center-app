<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CallCenterTranscriptionSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CallCenterSettingsController extends Controller
{
    public function show(CallCenterTranscriptionSettings $settings): JsonResponse
    {
        return response()->json([
            'settings' => $settings->payload(),
        ]);
    }

    public function update(Request $request, CallCenterTranscriptionSettings $settings): JsonResponse
    {
        $validated = $request->validate([
            'transcription_model' => ['required', Rule::in($settings->availableModels())],
            'transcription_initial_prompt' => ['nullable', 'string', 'max:4000'],
            'speaker_diarization_enabled' => ['nullable', 'boolean'],
            'speaker_diarization_token' => ['nullable', 'string', 'max:4096'],
            'llm_provider' => ['required', Rule::in($settings->availableLlmProviders())],
            'llm_api_url' => ['required', 'string', 'max:2000'],
            'llm_api_key' => ['nullable', 'string', 'max:4096'],
            'llm_api_keys' => ['nullable', 'array'],
            'llm_api_keys.openai' => ['nullable', 'string', 'max:4096'],
            'llm_api_keys.anthropic' => ['nullable', 'string', 'max:4096'],
            'llm_api_keys.openrouter' => ['nullable', 'string', 'max:4096'],
            'llm_api_keys.gemini' => ['nullable', 'string', 'max:4096'],
            'llm_api_key_deletes' => ['nullable', 'array'],
            'llm_api_key_deletes.openai' => ['nullable', 'boolean'],
            'llm_api_key_deletes.anthropic' => ['nullable', 'boolean'],
            'llm_api_key_deletes.openrouter' => ['nullable', 'boolean'],
            'llm_api_key_deletes.gemini' => ['nullable', 'boolean'],
            'llm_model' => ['required', 'string', 'max:255'],
            'llm_temperature' => ['required', 'numeric', 'between:0,2'],
            'llm_num_ctx' => ['required', 'integer', 'min:256', 'max:131072'],
            'llm_top_k' => ['required', 'integer', 'min:1', 'max:500'],
            'llm_top_p' => ['required', 'numeric', 'between:0,1'],
            'llm_repeat_penalty' => ['required', 'numeric', 'between:0,5'],
            'llm_seed' => ['nullable', 'integer', 'between:-2147483648,2147483647'],
            'llm_num_predict' => ['required', 'integer', 'between:-1,32768'],
            'llm_timeout_seconds' => ['required', 'integer', 'min:15', 'max:3600'],
            'llm_thinking_enabled' => ['nullable', 'boolean'],
        ]);

        $diarizationEnabled = $request->boolean('speaker_diarization_enabled');
        $newToken = trim((string) ($validated['speaker_diarization_token'] ?? ''));

        if ($diarizationEnabled && $newToken === '' && ! $settings->hasSpeakerDiarizationToken()) {
            $request->validate([
                'speaker_diarization_token' => ['required'],
            ], [
                'speaker_diarization_token.required' => 'Щоб увімкнути коректне визначення автора репліки, додайте Hugging Face token для pyannote.',
            ]);
        }

        $updatedSettings = $settings->save([
            'transcription_model' => (string) $validated['transcription_model'],
            'transcription_initial_prompt' => (string) ($validated['transcription_initial_prompt'] ?? ''),
            'speaker_diarization_enabled' => $diarizationEnabled,
            'speaker_diarization_token' => $newToken,
            'llm_provider' => (string) $validated['llm_provider'],
            'llm_api_url' => (string) $validated['llm_api_url'],
            'llm_api_key' => trim((string) ($validated['llm_api_key'] ?? '')),
            'llm_api_keys' => $validated['llm_api_keys'] ?? [],
            'llm_api_key_deletes' => $validated['llm_api_key_deletes'] ?? [],
            'llm_model' => (string) $validated['llm_model'],
            'llm_temperature' => $validated['llm_temperature'],
            'llm_num_ctx' => $validated['llm_num_ctx'],
            'llm_top_k' => $validated['llm_top_k'],
            'llm_top_p' => $validated['llm_top_p'],
            'llm_repeat_penalty' => $validated['llm_repeat_penalty'],
            'llm_seed' => $validated['llm_seed'] ?? null,
            'llm_num_predict' => $validated['llm_num_predict'],
            'llm_timeout_seconds' => $validated['llm_timeout_seconds'],
            'llm_thinking_enabled' => false,
        ]);

        return response()->json([
            'message' => 'Налаштування LLM і транскрибації збережено.',
            'settings' => $updatedSettings,
        ]);
    }

    public function availableModels(Request $request, CallCenterTranscriptionSettings $settings): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::in($settings->availableLlmProviders())],
            'llm_api_url' => ['nullable', 'string', 'max:2000'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'delete_api_key' => ['nullable', 'boolean'],
        ]);

        $provider = (string) $validated['provider'];
        $apiKey = $request->boolean('delete_api_key')
            ? null
            : trim((string) ($validated['api_key'] ?? ''));

        if ($apiKey === '') {
            $apiKey = $provider === 'ollama'
                ? ($settings->llmProvider() === 'ollama' ? $settings->llmApiKey() : null)
                : $settings->llmProviderApiKey($provider);
        }

        return response()->json([
            'provider' => $provider,
            'models' => $settings->availableLlmModels(
                $validated['llm_api_url'] ?? null,
                $provider,
                $apiKey,
            ),
        ]);
    }
}
