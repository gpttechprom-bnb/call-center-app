<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AltCallCenterCallsListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AltCallCenterCallsController extends Controller
{
    public function __invoke(Request $request, AltCallCenterCallsListingService $listingService): JsonResponse
    {
        return response()
            ->json($listingService->paginatedPayload($request->query()))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
