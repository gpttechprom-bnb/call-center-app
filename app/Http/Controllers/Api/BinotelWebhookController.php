<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BinotelWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

class BinotelWebhookController extends Controller
{
    public function __invoke(Request $request, BinotelWebhookService $webhookService): JsonResponse
    {
        $clientIp = (string) $request->ip();
        $allowedServers = config('binotel.allowed_servers', []);
        $sourceHost = is_array($allowedServers) ? ($allowedServers[$clientIp] ?? null) : null;

        if ($sourceHost === null) {
            $this->logger()->warning('Unauthorized Binotel webhook request.', [
                'clientIp' => $clientIp,
            ]);

            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $payload = $request->all();

            if ($payload !== []) {
                $webhookService->handle($payload, $clientIp, (string) $sourceHost);
            }

            return response()->json(['status' => 'success']);
        } catch (Throwable $e) {
            $this->logger()->error('Failed to process Binotel webhook.', [
                'clientIp' => $clientIp,
                'sourceHost' => $sourceHost,
                'requestType' => (string) $request->input('requestType', ''),
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    private function logger(): LoggerInterface
    {
        return Log::channel((string) config('binotel.log_channel', 'stack'));
    }
}
