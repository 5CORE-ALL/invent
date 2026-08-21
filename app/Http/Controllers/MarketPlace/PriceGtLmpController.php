<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Support\Marketplace\PriceGtLmpChannelCounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * price >lmp — Tabulator of analytics channels and their red-triangle counts.
 */
class PriceGtLmpController extends Controller
{
    public function index()
    {
        return view('market-places.Price_gt_lmp');
    }

    public function getData()
    {
        try {
            $data = collect(PriceGtLmpChannelCounts::masterRows(false))->values();
            $total = (int) $data->sum('price_gt_lmp');

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'total_price_gt_lmp' => $total,
            ]);
        } catch (\Throwable $e) {
            Log::error('price >lmp getData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function report(Request $request)
    {
        $channel = (string) $request->input('channel', '');
        $count = (int) $request->input('count', 0);
        if (PriceGtLmpChannelCounts::resolveKey($channel) === null) {
            return response()->json(['success' => false, 'message' => 'Unknown channel'], 422);
        }

        PriceGtLmpChannelCounts::storeReported($channel, $count);

        return response()->json(['success' => true]);
    }
}
