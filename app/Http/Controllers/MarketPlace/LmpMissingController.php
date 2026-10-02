<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Support\Marketplace\LmpMissingChannelCounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * LMP Missing data — Tabulator of analytics channels and their LMP M. counts.
 */
class LmpMissingController extends Controller
{
    public function index()
    {
        return view('market-places.Lmp_missing_data');
    }

    public function getData()
    {
        try {
            $data = collect(LmpMissingChannelCounts::masterRows(false))->values();
            $total = LmpMissingChannelCounts::sumCounted($data->all());

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'total_lmp_missing' => $total,
            ]);
        } catch (\Throwable $e) {
            Log::error('LMP Missing getData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Analytics pages POST the live LMP M. badge count so this master stays in sync.
     */
    public function report(Request $request)
    {
        $channel = (string) $request->input('channel', '');
        $count = (int) $request->input('count', 0);
        if (LmpMissingChannelCounts::resolveKey($channel) === null) {
            return response()->json(['success' => false, 'message' => 'Unknown channel'], 422);
        }

        LmpMissingChannelCounts::storeReported($channel, $count);

        return response()->json(['success' => true]);
    }

    /**
     * Red NR leaves that channel out of the LMP M. total.
     * Only president@5core.com can change it.
     */
    public function updateNr(Request $request)
    {
        if (! $this->canToggleNr()) {
            return response()->json([
                'success' => false,
                'message' => 'Only president@5core.com can change NR.',
            ], 403);
        }

        $channel = (string) $request->input('channel', '');
        if (LmpMissingChannelCounts::resolveKey($channel) === null) {
            return response()->json(['success' => false, 'message' => 'Unknown channel'], 422);
        }

        $nr = $request->boolean('nr');
        LmpMissingChannelCounts::setNr($channel, $nr);

        return response()->json([
            'success' => true,
            'nr' => $nr,
            'total_lmp_missing' => LmpMissingChannelCounts::totalMissing(true),
        ]);
    }

    private function canToggleNr(): bool
    {
        return strtolower(trim((string) (auth()->user()->email ?? ''))) === 'president@5core.com';
    }
}
