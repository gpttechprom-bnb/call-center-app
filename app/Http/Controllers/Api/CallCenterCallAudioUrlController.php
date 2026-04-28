<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BinotelApiCallCompleted;
use App\Services\BinotelCallAudioCacheService;
use App\Services\BinotelCallRecordUrlResolver;
use Illuminate\Http\JsonResponse;

class CallCenterCallAudioUrlController extends Controller
{
    public function __invoke(
        BinotelApiCallCompleted $call,
        BinotelCallRecordUrlResolver $recordUrlResolver,
        BinotelCallAudioCacheService $audioCacheService,
    ): JsonResponse {
        $generalCallId = trim((string) $call->call_details_general_call_id);
        $localAudio = null;

        try {
            $localAudio = $audioCacheService->ensureLocalCopy($call, true);
        } catch (\RuntimeException) {
            $localAudio = null;
        }

        $audioUrl = $recordUrlResolver->resolve($call, true);
        $call->refresh();
        $localAudio ??= $audioCacheService->cachedAudio($call);

        $fallbackUrl = trim((string) ($call->call_details_link_to_call_record_in_my_business ?? ''));
        $overlayUrl = trim((string) ($call->call_details_link_to_call_record_overlay_in_my_business ?? ''));
        $recordingStatus = trim((string) ($call->call_details_recording_status ?? ''));
        $localAudioUrl = $localAudio !== null
            ? route('api.alt.call-center.calls.audio-file', ['call' => $call->id])
            : null;
        $localAudioDownloadUrl = $localAudio !== null
            ? route('api.alt.call-center.calls.audio-file', ['call' => $call->id, 'download' => 1])
            : null;

        return response()->json([
            'id' => $call->id,
            'generalCallId' => $generalCallId !== '' ? $generalCallId : null,
            'audioUrl' => $localAudioUrl ?? ($audioUrl !== '' ? $audioUrl : null),
            'remoteAudioUrl' => $audioUrl !== '' ? $audioUrl : null,
            'audioFallbackUrl' => $fallbackUrl !== '' ? $fallbackUrl : null,
            'audioOverlayUrl' => $overlayUrl !== '' ? $overlayUrl : null,
            'audioStatus' => $this->audioStatus($audioUrl, $recordingStatus, $localAudio !== null),
            'binotelStatus' => $this->binotelStatus($call, $audioUrl, $localAudio !== null),
            'recordingStatus' => $recordingStatus,
            'localAudioUrl' => $localAudioUrl,
            'localAudioDownloadUrl' => $localAudioDownloadUrl,
            'localAudioFileName' => $localAudio['file_name'] ?? null,
            'localAudioDownloadedAt' => isset($localAudio['downloaded_at']) && $localAudio['downloaded_at'] !== null
                ? $localAudio['downloaded_at']->toIso8601String()
                : null,
            'localAudioExpiresAt' => isset($localAudio['expires_at']) && $localAudio['expires_at'] !== null
                ? $localAudio['expires_at']->toIso8601String()
                : null,
            'localAudioSize' => $audioCacheService->formatFileSize(
                isset($localAudio['size_bytes']) && is_int($localAudio['size_bytes']) ? $localAudio['size_bytes'] : null
            ),
            'localAudioStatus' => $localAudio !== null ? 'Локальний файл готовий' : 'Локальний файл ще не завантажено',
            'localAudioError' => trim((string) ($call->local_audio_last_error ?? '')) ?: null,
        ]);
    }

    private function audioStatus(string $audioUrl, string $recordingStatus, bool $hasLocalAudio): string
    {
        if ($hasLocalAudio) {
            return 'Локальний файл готовий';
        }

        if ($audioUrl !== '') {
            return 'Запис доступний';
        }

        if ($recordingStatus === 'uploading') {
            return 'Запис завантажується';
        }

        return 'Запис недоступний';
    }

    private function binotelStatus(BinotelApiCallCompleted $call, string $audioUrl, bool $hasLocalAudio): string
    {
        if ($hasLocalAudio) {
            return 'Локально';
        }

        if (trim($audioUrl) !== '') {
            return 'Успіх';
        }

        if ($call->call_record_url_last_checked_at !== null) {
            return 'Помилка';
        }

        return '—';
    }
}
