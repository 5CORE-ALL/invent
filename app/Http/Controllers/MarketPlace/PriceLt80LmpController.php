<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Support\Marketplace\PriceLt80LmpChannelCounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * price < 80% of LMP — Tabulator of analytics channels and purple-triangle counts.
 */
class PriceLt80LmpController extends Controller
{
    public function index()
    {
        return view('market-places.Price_lt_80_lmp');
    }

    public function getData()
    {
        try {
            $data = collect(PriceLt80LmpChannelCounts::masterRows(false))->values();
            $total = (int) $data->sum('price_lt80_lmp');

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'total_price_lt80_lmp' => $total,
            ]);
        } catch (\Throwable $e) {
            Log::error('price < 80% of LMP getData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function report(Request $request)
    {
        $channel = (string) $request->input('channel', '');
        $count = (int) $request->input('count', 0);
        if (PriceLt80LmpChannelCounts::resolveKey($channel) === null) {
            return response()->json(['success' => false, 'message' => 'Unknown channel'], 422);
        }

        PriceLt80LmpChannelCounts::storeReported($channel, $count);

        return response()->json(['success' => true]);
    }
}
