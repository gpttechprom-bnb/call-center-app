<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AltCallCenterCallsListingService;
use Illuminate\Http\JsonResponse;

class AltCallCenterManagersSummaryController extends Controller
{
    public function __invoke(AltCallCenterCallsListingService $listingService): JsonResponse
    {
        return response()
            ->json($listingService->managersSummaryPayload())
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
