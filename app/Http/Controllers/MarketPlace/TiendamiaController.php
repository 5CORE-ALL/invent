<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TiendamiaController extends Controller
{
    /**
     * Legacy CVR route (loads Walmart pricing CVR view — unchanged behavior).
     */
    public function tiendamiaPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $percentage = Cache::remember('Walmart', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();

            return $marketplaceData ? $marketplaceData->percentage : 100;
        });

        return view('market-places.walmartPricingCvr', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage,
        ]);
    }
}
