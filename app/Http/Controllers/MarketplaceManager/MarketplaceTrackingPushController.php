<?php

namespace App\Http\Controllers\MarketplaceManager;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceManager\MarketplaceTrackingBatchPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceTrackingPushController extends Controller
{
    public function batch(Request $request, string $slug, MarketplaceTrackingBatchPush $push): JsonResponse
    {
        @set_time_limit(120);
        $limit = max(1, min(5, (int) $request->input('limit', 2)));

        return response()->json($push->pushBatch($slug, $limit));
    }
}
