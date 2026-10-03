<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Business 5 Core (B2B) store — Missing L page with direct publish (POST /api/listings upsert).
 * Listed comes from b5c_b2b_products; there is no NRL/link status table for this channel.
 */
class ListingB5cB2bController extends Controller
{
    use HandlesListingPublishActions;

    protected function listingPublishChannel(): string
    {
        return 'b5cb2b';
    }

    public function listingB5cB2b(Request $request)
    {
        return view('market-places.listing-market-places.listingB5cB2b', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
        ]);
    }

    public function getViewListingB5cB2bData(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('b5cb2b'),
        ]);
    }

    public function saveStatus(Request $request)
    {
        if ($response = $this->listingPublishResponse($request)) {
            return $response;
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Business 5 Core (B2B) has no editable listing status; NRL/REQ and Listed are automatic.',
        ], 422);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('b5cb2b');
    }

    public function import(Request $request)
    {
        return response()->json([
            'error' => 'Import is not available for Business 5 Core (B2B); listing status is automatic from the B2B store.',
        ], 422);
    }

    public function export(): StreamedResponse
    {
        $rows = AutomatedListingPage::rows('b5cb2b');

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['sku', 'parent', 'inv', 'nr_req', 'listed', 'listing_id']);
            foreach ($rows as $row) {
                $row = (array) (is_object($row) && method_exists($row, 'toArray') ? $row->toArray() : $row);
                $id = trim((string) ($row['eBay_item_id'] ?? ''));
                fputcsv($out, [
                    $row['sku'] ?? '',
                    $row['parent'] ?? $row['Parent'] ?? '',
                    $row['INV'] ?? 0,
                    $row['nr_req'] ?? 'REQ',
                    $id !== '' ? 'Listed' : 'Pending',
                    $id,
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_b5cb2b_'.date('Y-m-d').'.csv"',
        ]);
    }
}
