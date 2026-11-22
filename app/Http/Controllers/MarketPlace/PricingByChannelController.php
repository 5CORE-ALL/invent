<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Services\PricingByChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PricingByChannelController extends Controller
{
    public function __construct(private readonly PricingByChannelService $service)
    {
    }

    public function index()
    {
        return view('market-places.pricing_by_channel_view', [
            'channels' => $this->service->channels(),
        ]);
    }

    public function dataJson(Request $request): JsonResponse
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');

        try {
            $data = $this->service->build($request->boolean('refresh'));

            return response()->json($data)->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        } catch (\Throwable $e) {
            Log::error('Pricing by channel data failed: '.$e->getMessage());

            return response()->json(['error' => 'Failed to build pricing by channel data'], 500);
        }
    }

    /** Drops the cached rows after a price was saved / pushed, so the next load shows the new numbers. */
    public function forgetCache(): JsonResponse
    {
        PricingByChannelService::forget();

        return response()->json(['success' => true]);
    }
}
