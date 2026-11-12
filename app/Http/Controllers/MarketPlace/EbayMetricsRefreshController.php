<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Support\Marketplace\EbayMetricsRefresh;

/**
 * After S PRC pushes on /ebay-tabulator-view, /ebay2-tabulator-view and /ebay3-tabulator-view: truncate that
 * channel's metrics table and re-pull every listing (new prices) from eBay in a background process. The page polls
 * status() and reloads the table when it is done.
 */
class EbayMetricsRefreshController extends Controller
{
    public function start(string $channel)
    {
        if (! EbayMetricsRefresh::isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported eBay channel'], 404);
        }

        $result = EbayMetricsRefresh::start($channel);
        $ok = $result['started'] || $result['already_running'];

        return response()->json([
            'success' => $ok,
            'already_running' => $result['already_running'],
            'message' => $ok ? null : ($result['error'] ?? 'Could not start the refresh'),
        ], $ok ? 200 : 500);
    }

    public function status(string $channel)
    {
        if (! EbayMetricsRefresh::isSupported($channel)) {
            return response()->json(['state' => 'idle', 'running' => false, 'message' => 'Unsupported eBay channel'], 404);
        }

        return response()->json(EbayMetricsRefresh::status($channel) ?? ['state' => 'idle', 'running' => false, 'message' => '']);
    }
}
