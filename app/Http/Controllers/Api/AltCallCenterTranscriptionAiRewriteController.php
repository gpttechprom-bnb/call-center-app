<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CallCenterTranscriptionAiRewriteService;
use App\Support\AltCallCenterTranscriptionSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AltCallCenterTranscriptionAiRewriteController extends Controller
{
    public function __invoke(
        Request $request,
        CallCenterTranscriptionAiRewriteService $aiRewriteService,
        AltCallCenterTranscriptionSettings $settings,
    ): JsonResponse|StreamedResponse {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:500000'],
            'prompt' => ['required', 'string', 'max:4000'],
            'model' => ['nullable', 'string', 'max:255'],
            'generation_settings' => ['nullable', 'array'],
            'generation_settings.provider' => ['nullable', 'string', 'max:120'],
            'generation_settings.thinking_enabled' => ['nullable', 'boolean'],
            'generation_settings.temperature' => ['nullable', 'numeric', 'between:0,2'],
            'generation_settings.num_ctx' => ['nullable', 'integer', 'between:256,131072'],
            'generation_settings.top_k' => ['nullable', 'integer', 'between:1,500'],
            'generation_settings.top_p' => ['nullable', 'numeric', 'between:0,1'],
            'generation_settings.repeat_penalty' => ['nullable', 'numeric', 'between:0,5'],
            'generation_settings.repetition_penalty' => ['nullable', 'numeric', 'between:0,5'],
            'generation_settings.num_predict' => ['nullable', 'integer', 'between:-1,32768'],
            'generation_settings.max_new_tokens' => ['nullable', 'integer', 'between:-1,32768'],
            'generation_settings.seed' => ['nullable', 'integer', 'between:-2147483648,2147483647'],
            'generation_settings.timeout_seconds' => ['nullable', 'integer', 'between:15,3600'],
            'stream' => ['nullable', 'boolean'],
        ]);

        if ((bool) ($validated['stream'] ?? false)) {
            return response()->stream(function () use ($validated, $aiRewriteService, $settings): void {
                ignore_user_abort(true);

                $emit = function (string $type, array $payload = []): void {
                    if (connection_aborted()) {
                        return;
                    }

                    echo json_encode(
                        array_merge(['type' => $type], $payload),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    )."\n";

                    @ob_flush();
                    flush();
                };

                try {
                    $result = $aiRewriteService->streamRewrite(
                        (string) $validated['text'],
                        (string) $validated['prompt'],
                        (string) ($validated['model'] ?? ''),
                        $settings,
                        $validated['generation_settings'] ?? [],
                        static function (string $type, array $payload = []) use ($emit): void {
                            $emit($type, $payload);
                        },
                    );

                    $emit('completed', [
                        'message' => $result['message'] ?? 'AI-обробку тексту в alt-контурі завершено.',
                        'text' => $result['text'],
                        'model' => $result['model'],
                        'corrections' => $result['corrections'] ?? [],
                        'raw_corrections' => $result['raw_corrections'] ?? '',
                    ]);
                } catch (RuntimeException $exception) {
                    $emit('error', [
                        'message' => $exception->getMessage(),
                    ]);
                } catch (Throwable) {
                    $emit('error', [
                        'message' => 'Не вдалося виконати AI-обробку тексту в alt-контурі. Перевірте Ollama та спробуйте ще раз.',
                    ]);
                }
            }, 200, [
                'Content-Type' => 'application/x-ndjson; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        try {
            $result = $aiRewriteService->rewrite(
                (string) $validated['text'],
                (string) $validated['prompt'],
                (string) ($validated['model'] ?? ''),
                $settings,
                $validated['generation_settings'] ?? [],
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Не вдалося виконати AI-обробку тексту в alt-контурі. Перевірте Ollama та спробуйте ще раз.',
            ], 500);
        }

        return response()->json([
            'message' => $result['message'] ?? 'AI-обробку тексту в alt-контурі завершено.',
            'text' => $result['text'],
            'model' => $result['model'],
            'corrections' => $result['corrections'] ?? [],
            'raw_corrections' => $result['raw_corrections'] ?? '',
        ]);
    }
}
