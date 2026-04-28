<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BinotelApiCallCompleted;
use App\Services\BinotelCallAudioCacheService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CallCenterCallAudioFileController extends Controller
{
    public function __invoke(
        Request $request,
        BinotelApiCallCompleted $call,
        BinotelCallAudioCacheService $audioCacheService,
    ): BinaryFileResponse {
        $cachedAudio = $audioCacheService->cachedAudio($call);

        abort_if($cachedAudio === null, 404, 'Локальний аудіофайл для цього дзвінка ще не завантажено.');

        $absolutePath = (string) ($cachedAudio['absolute_path'] ?? '');
        $downloadName = (string) ($cachedAudio['file_name'] ?? basename($absolutePath));
        $mimeType = trim((string) ($cachedAudio['mime_type'] ?? ''));

        if ($request->boolean('download')) {
            return response()->download(
                $absolutePath,
                $downloadName,
                $mimeType !== '' ? ['Content-Type' => $mimeType] : [],
            );
        }

        return response()->file(
            $absolutePath,
            $mimeType !== '' ? ['Content-Type' => $mimeType] : [],
        );
    }
}
