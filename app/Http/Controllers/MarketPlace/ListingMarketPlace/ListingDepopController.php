<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\DepopListingStatus;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\DepopSheetListingService;
use App\Support\Marketplace\ListingChannelCounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingDepopController extends Controller
{
    public function listingDepop(Request $request): View
    {
        return view('market-places.listing-market-places.listingDepop', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
            'counts' => ChannelListingRegistry::nrReqCountArray('depop'),
        ]);
    }

    public function getViewListingDepopData(): JsonResponse
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('depop'),
        ]);
    }

    public function getNrReqCount(): array
    {
        return ChannelListingRegistry::nrReqCountArray('depop');
    }

    public function saveStatus(Request $request): JsonResponse
    {
        if (! Schema::hasTable('depop_listing_statuses')) {
            return response()->json(['error' => 'Depop listing table is not ready. Run migrations.'], 503);
        }

        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|string',
            'seller_link' => 'nullable|string',
        ]);

        $sku = trim((string) $validated['sku']);
        $status = DepopListingStatus::where('sku', $sku)->first();
        $existing = $status && is_array($status->value) ? $status->value : [];

        foreach (['nr_req', 'listed', 'buyer_link', 'seller_link'] as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field] ?? '';
            }
        }

        DepopListingStatus::updateOrCreate(['sku' => $sku], ['value' => $existing]);

        return response()->json(['status' => 'success']);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $result = app(DepopSheetListingService::class)->importUploadedCatalog($request->file('file'));
            $counts = ListingChannelCounts::forChannel('depop', false);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'processed' => $result['processed'],
                'listed' => $result['listed'],
                'skipped' => $result['skipped'],
                'unmatched' => $result['unmatched'],
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['sku', 'listing_id', 'buyer_link']);
            fputcsv($file, ['EXAMPLE-SKU-1', '', '']);
            fputcsv($file, ['EXAMPLE-SKU-2', '', '']);
            fclose($file);
        }, 'depop-current-listings-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['sku', 'listed', 'listing_id', 'buyer_link', 'seller_link']);
            if (Schema::hasTable('depop_listing_statuses')) {
                foreach (DepopListingStatus::query()->orderBy('sku')->get() as $row) {
                    $value = is_array($row->value) ? $row->value : [];
                    fputcsv($file, [
                        $row->sku,
                        $value['listed'] ?? '',
                        $value['listing_id'] ?? '',
                        $value['buyer_link'] ?? '',
                        $value['seller_link'] ?? '',
                    ]);
                }
            }
            fclose($file);
        }, 'depop-current-listings.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
