<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;



use App\Http\Controllers\Controller;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingCountsEngine;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TemuListingStatus;
use App\Services\MarketplaceManager\TemuListingPublishService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ListingTemuController extends Controller
{
    use HandlesListingPublishActions;

    protected function listingPublishChannel(): string
    {
        return 'temu';
    }

    public function listingTemu(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('temu_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingTemu', [
            'temuPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingTemuData(Request $request)
    {
        $rows = AutomatedListingPage::rows('temu');

        // Temu 1 only: SKU ID imported from the Temu API (temu_metrics.sku_id) for the SKU ID column.
        $skuIds = [];
        try {
            \App\Models\TemuMetric::query()
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->whereNotNull('sku_id')->where('sku_id', '!=', '')
                ->get(['sku', 'sku_id'])
                ->each(function ($metric) use (&$skuIds) {
                    $sku = trim((string) $metric->sku);
                    $id = trim((string) $metric->sku_id);
                    if ($sku === '' || ! preg_match('/^\d+$/', $id)) {
                        return;
                    }
                    foreach (array_merge([strtolower($sku)], ListingCountsEngine::skuIndexKeys($sku)) as $key) {
                        if ($key !== '' && ! isset($skuIds[$key])) {
                            $skuIds[$key] = $id;
                        }
                    }
                });
        } catch (\Throwable $e) {
            // the SKU ID column is informational; never break the page
        }
        foreach ($rows as $row) {
            $skuId = ListingCountsEngine::listingIdFromMap($skuIds, (string) ($row->sku ?? ''));
            $row->sku_id = $skuId !== '' ? $skuId : null;
        }

        try {
            $rows = \App\Services\MarketplaceManager\TemuGoodsStatusSync::attach($rows);
        } catch (\Throwable $e) {
            // the Temu status columns are informational; never break the page
        }

        try {
            $rows = \App\Support\Marketplace\TemuSkuVsParent::attach($rows);
        } catch (\Throwable $e) {
            // SKU VS Parent is informational; never break the page
        }

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

        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|url',
            'seller_link' => 'nullable|url',
        ]);

        $sku = $validated['sku'];
        $status = TemuListingStatus::where('sku', $sku)->first();

        $existing = $status ? $status->value : [];

        // Only update the fields that are present in the request
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        TemuListingStatus::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('temu');
    }

    public function publish(Request $request, TemuListingPublishService $publisher)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $skus = $this->skusFromRequest($request);
        if ($skus === []) {
            return response()->json([
                'success' => false,
                'message' => 'SKU is required.',
            ], 422);
        }

        try {
            $mode = strtolower(trim((string) $request->input('mode', 'variation'))) === 'single'
                ? 'single'
                : 'variation';
            $parentHint = trim((string) $request->input('parent', $request->input('parent_hint', '')));
            $result = $publisher->publishSkus($skus, ! $request->boolean('confirmed'), $mode, $parentHint);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Publish to Temu failed: '.$e->getMessage(),
            ], 500);
        }

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /** What is wrong with an Incomplete Temu 1 goods (red triangle popup). */
    public function incompleteDetails(Request $request, TemuListingPublishService $publisher)
    {
        $sku = trim((string) $request->input('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        try {
            $result = $publisher->incompleteDetails($sku);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Could not read the Incomplete product: '.$e->getMessage()], 500);
        }

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /** Send the master data to the Incomplete Temu 1 goods so it can be completed. */
    public function completeIncomplete(Request $request, TemuListingPublishService $publisher)
    {
        $sku = trim((string) $request->input('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(240);
        }
        try {
            $result = $publisher->completeIncompleteGoods($sku);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Complete failed: '.$e->getMessage()], 500);
        }

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function publishPreview(Request $request, TemuListingPublishService $publisher)
    {
        $skus = $this->skusFromRequest($request);
        if ($skus === []) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least one SKU.',
                'groups' => [],
            ], 422);
        }

        $raw = $request->input('sku_parents', $request->input('skuParents', []));
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        $parents = [];
        foreach (is_array($raw) ? $raw : [] as $sku => $parent) {
            if (is_array($parent)) {
                $sku = $parent['sku'] ?? $sku;
                $parent = $parent['parent'] ?? '';
            }
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $parents[$sku] = trim((string) $parent);
            }
        }

        return response()->json($publisher->previewFromSkus($skus, $parents));
    }

    /**
     * @return list<string>
     */
    private function skusFromRequest(Request $request): array
    {
        $skus = $request->input('skus');
        if (! is_array($skus) || $skus === []) {
            $single = trim((string) $request->input('sku', ''));
            $skus = $single !== '' ? [$single] : [];
        }

        $out = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $out[] = $sku;
            }
        }

        return $out;
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file));
        // $header = array_map('trim', $rows[0]); // first row = header
        $header = array_map(function ($h) {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)); // remove BOM if present
        }, $rows[0]);

        unset($rows[0]);

        $allowedHeaders = ['sku','listed', 'buyer_link', 'seller_link'];
        foreach ($header as $h) {
            if (!in_array($h, $allowedHeaders)) {
                return response()->json([
                    'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders)
                ], 422);
            }
        }

        foreach ($rows as $row) {
            if (count($row) < 1) {
                continue; // skip empty
            }

            $rowData = array_combine($header, $row);
            $sku = trim($rowData['sku'] ?? '');

            if (!$sku) {
                continue;
            }

            // Only import SKUs that exist in product_masters
            if (!ProductMaster::where('sku', $sku)->exists()) {
                continue;
            }

            $status = TemuListingStatus::where('sku', $sku)->first();
            $existing = $status ? $status->value : [];

            $fields = ['listed', 'buyer_link', 'seller_link'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                    $existing[$field] = $rowData[$field];
                }
            }

            TemuListingStatus::updateOrCreate(
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
            'Content-Disposition' => 'attachment; filename="listing_status.csv"',
        ];

        $columns = ['sku', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all SKUs from product master
            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $status = TemuListingStatus::where('sku', $sku)->first();

                $row = [
                    'sku'         => $sku,
                    'listed'      => $status->value['listed'] ?? '',
                    'buyer_link'  => $status->value['buyer_link'] ?? '',
                    'seller_link' => $status->value['seller_link'] ?? '',
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
