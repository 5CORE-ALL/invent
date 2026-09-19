<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\SheetListingCatalog;
use App\Support\Marketplace\SheetListingCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingSheetCatalogController extends Controller
{
    public function show(Request $request, string $channel): View
    {
        $slug = $this->requireSlug($channel);

        return view('market-places.listing-market-places.listingSheetCatalog', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
            'channel' => $slug,
            'label' => SheetListingCatalog::label($slug),
            'counts' => ChannelListingRegistry::nrReqCountArray($slug),
        ]);
    }

    public function data(string $channel): JsonResponse
    {
        $slug = $this->requireSlug($channel);

        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows($slug),
        ]);
    }

    public function getNrReqCount(): array
    {
        return ChannelListingRegistry::nrReqCountArray('depop');
    }

    public function saveStatus(Request $request, string $channel): JsonResponse
    {
        $slug = $this->requireSlug($channel);
        $statusClass = SheetListingCatalog::statusClass($slug);
        if ($statusClass === null || ! Schema::hasTable((new $statusClass)->getTable())) {
            return response()->json(['error' => 'Listing table is not ready. Run migrations.'], 503);
        }

        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|string',
            'seller_link' => 'nullable|string',
        ]);

        $sku = trim((string) $validated['sku']);
        $status = $statusClass::where('sku', $sku)->first();
        $existing = $status && is_array($status->value) ? $status->value : [];

        foreach (['nr_req', 'listed', 'buyer_link', 'seller_link'] as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field] ?? '';
            }
        }

        $statusClass::updateOrCreate(['sku' => $sku], ['value' => $existing]);

        return response()->json(['status' => 'success']);
    }

    public function import(Request $request, string $channel): JsonResponse
    {
        $slug = $this->requireSlug($channel);
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $result = app(SheetListingCatalogService::class)->importUploadedCatalog($slug, $request->file('file'));
            $counts = ListingChannelCounts::forChannel($slug, false);

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

    public function template(string $channel): StreamedResponse
    {
        $slug = $this->requireSlug($channel);

        return response()->streamDownload(function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['sku', 'listing_id', 'buyer_link']);
            fputcsv($file, ['EXAMPLE-SKU-1', '', '']);
            fputcsv($file, ['EXAMPLE-SKU-2', '', '']);
            fclose($file);
        }, $slug.'-current-listings-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function export(string $channel): StreamedResponse
    {
        $slug = $this->requireSlug($channel);
        $statusClass = SheetListingCatalog::statusClass($slug);

        return response()->streamDownload(function () use ($statusClass) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['sku', 'listed', 'listing_id', 'buyer_link', 'seller_link']);
            if ($statusClass && Schema::hasTable((new $statusClass)->getTable())) {
                foreach ($statusClass::query()->orderBy('sku')->get() as $row) {
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
        }, $slug.'-current-listings.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function requireSlug(string $channel): string
    {
        $slug = ListingChannelCounts::normalize($channel);
        if (! SheetListingCatalog::has($slug)) {
            abort(404, 'Sheet listing channel not found');
        }

        return $slug;
    }
}
