<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\Temu2ListingStatus;
use App\Services\MarketplaceManager\Temu2ListingPublishService;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingTemu2Controller extends Controller
{
    public function listingTemu2(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('temu2_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingTemu2', [
            'temuPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingTemu2Data(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('temu2'),
        ]);
    }

    public function saveStatus(Request $request, Temu2ListingPublishService $publisher)
    {
        if ($request->boolean('publish') || $request->input('action') === 'publish') {
            return $this->publish($request, $publisher);
        }
        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|url',
            'seller_link' => 'nullable|url',
        ]);

        $sku = $validated['sku'];
        $status = Temu2ListingStatus::where('sku', $sku)->first();
        $existing = $status ? $status->value : [];

        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        Temu2ListingStatus::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('temu2');
    }

    public function publish(Request $request, Temu2ListingPublishService $publisher)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $skus = $request->input('skus');
        if (! is_array($skus) || $skus === []) {
            $single = trim((string) $request->input('sku', ''));
            $skus = $single !== '' ? [$single] : [];
        }

        $validatedSkus = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $validatedSkus[] = $sku;
            }
        }
        if ($validatedSkus === []) {
            return response()->json([
                'success' => false,
                'message' => 'SKU is required.',
            ], 422);
        }

        try {
            $result = $publisher->publishSkus($validatedSkus, ! $request->boolean('confirmed'));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Publish to Temu 2 failed: '.$e->getMessage(),
            ], 500);
        }

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function publishPreview(Request $request, Temu2ListingPublishService $publisher)
    {
        $skus = $request->input('skus');
        if (! is_array($skus) || $skus === []) {
            $single = trim((string) $request->input('sku', ''));
            $skus = $single !== '' ? [$single] : [];
        }

        $validatedSkus = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $validatedSkus[] = $sku;
            }
        }
        if ($validatedSkus === []) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least one SKU.',
                'groups' => [],
            ], 422);
        }

        return response()->json($publisher->previewFromSkus($validatedSkus));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file));
        $header = array_map(function ($h) {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
        }, $rows[0]);

        unset($rows[0]);

        $allowedHeaders = ['sku', 'listed', 'buyer_link', 'seller_link'];
        foreach ($header as $h) {
            if (! in_array($h, $allowedHeaders)) {
                return response()->json([
                    'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders),
                ], 422);
            }
        }

        foreach ($rows as $row) {
            if (count($row) < 1) {
                continue;
            }

            $rowData = array_combine($header, $row);
            $sku = trim($rowData['sku'] ?? '');

            if (! $sku) {
                continue;
            }

            if (! ProductMaster::where('sku', $sku)->exists()) {
                continue;
            }

            $status = Temu2ListingStatus::where('sku', $sku)->first();
            $existing = $status ? $status->value : [];

            $fields = ['listed', 'buyer_link', 'seller_link'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                    $existing[$field] = $rowData[$field];
                }
            }

            Temu2ListingStatus::updateOrCreate(
                ['sku' => $sku],
                ['value' => $existing]
            );
        }

        return response()->json(['success' => 'CSV imported successfully']);
    }

    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_temu2_status.csv"',
        ];

        $columns = ['sku', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $status = Temu2ListingStatus::where('sku', $sku)->first();

                fputcsv($file, [
                    'sku' => $sku,
                    'listed' => $status->value['listed'] ?? '',
                    'buyer_link' => $status->value['buyer_link'] ?? '',
                    'seller_link' => $status->value['seller_link'] ?? '',
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
