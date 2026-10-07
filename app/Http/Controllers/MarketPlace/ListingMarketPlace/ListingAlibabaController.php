<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Alibaba Missing L page. Listed comes from alibaba_metrics.product_id.
 * Publish adds the checked SKUs through Alibaba's product schema API.
 */
class ListingAlibabaController extends Controller
{
    use HandlesListingPublishActions;

    protected function listingPublishChannel(): string
    {
        return 'alibaba';
    }

    public function listingAlibaba(Request $request)
    {
        return view('market-places.listing-market-places.listingAlibaba', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
            'publishChannel' => 'alibaba',
        ]);
    }

    public function getViewListingAlibabaData(Request $request)
    {
        $rows = AutomatedListingPage::rows('alibaba')->each(function ($row) {
            $id = trim((string) ($row->product_id ?? $row->listing_id ?? ''));
            if ($id !== '' && ! str_starts_with($id, 'http')) {
                $row->buyer_link = 'https://www.alibaba.com/product-detail/_'.$id.'.html';
            }
        });

        return response()->json([
            'status' => 200,
            'data' => $rows,
        ]);
    }

    public function saveStatus(Request $request)
    {
        if ($response = $this->listingPublishResponse($request)) {
            return $response;
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Alibaba listing status is automatic. Use Publish to add a Missing L SKU.',
        ], 422);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('alibaba');
    }

    public function import(Request $request)
    {
        return response()->json([
            'error' => 'Import is not available for Alibaba. Missing L is CP Master minus live Alibaba product ids.',
        ], 422);
    }

    public function export(): StreamedResponse
    {
        $rows = AutomatedListingPage::rows('alibaba');

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['sku', 'parent', 'inv', 'std_price', 'nr_req', 'listed', 'product_id']);
            foreach ($rows as $row) {
                $row = (array) (is_object($row) && method_exists($row, 'toArray') ? $row->toArray() : $row);
                $id = trim((string) ($row['product_id'] ?? $row['eBay_item_id'] ?? ''));
                fputcsv($out, [
                    $row['sku'] ?? '',
                    $row['parent'] ?? $row['Parent'] ?? '',
                    $row['INV'] ?? 0,
                    $row['std_price'] ?? '',
                    $row['nr_req'] ?? 'REQ',
                    $id !== '' ? 'Listed' : 'Pending',
                    $id,
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_alibaba_'.date('Y-m-d').'.csv"',
        ]);
    }
}
