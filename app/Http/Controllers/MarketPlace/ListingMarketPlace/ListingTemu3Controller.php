<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\Temu3ListingStatus;
use App\Services\MarketplaceManager\Temu3ListingPublishService;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingTemu3Controller extends Controller
{
    use HandlesListingPublishActions;

    protected function listingPublishChannel(): string
    {
        return 'temu3';
    }

    public function listingTemu3(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('temu3_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingTemu3', [
            'temuPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingTemu3Data(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('temu3'),
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

        $status = Temu3ListingStatus::where('sku', $validated['sku'])->first();
        $existing = $status ? $status->value : [];

        foreach (['nr_req', 'listed', 'buyer_link', 'seller_link'] as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        Temu3ListingStatus::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('temu3');
    }

    public function publish(Request $request, Temu3ListingPublishService $publisher)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $validatedSkus = $this->skusFromRequest($request);
        if ($validatedSkus === []) {
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
            $result = $publisher->publishSkus(
                $validatedSkus,
                ! $request->boolean('confirmed'),
                $mode,
                $parentHint
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Publish to Temu 3 failed: '.$e->getMessage(),
            ], 500);
        }

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function publishPreview(Request $request, Temu3ListingPublishService $publisher)
    {
        $validatedSkus = $this->skusFromRequest($request);
        if ($validatedSkus === []) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least one SKU.',
                'groups' => [],
            ], 422);
        }

        return response()->json($publisher->previewFromSkus($validatedSkus, $this->skuParentsFromPreviewRequest($request)));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $rows = array_map('str_getcsv', file($request->file('file')));
        $header = array_map(function ($h) {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
        }, $rows[0]);
        unset($rows[0]);

        $allowedHeaders = ['sku', 'listed', 'buyer_link', 'seller_link'];
        foreach ($header as $h) {
            if (! in_array($h, $allowedHeaders)) {
                return response()->json([
                    'error' => "Invalid header '$h'. Allowed headers: ".implode(', ', $allowedHeaders),
                ], 422);
            }
        }

        foreach ($rows as $row) {
            if (count($row) < 1 || count($row) !== count($header)) {
                continue;
            }

            $rowData = array_combine($header, $row);
            $sku = trim($rowData['sku'] ?? '');
            if (! $sku || ! ProductMaster::where('sku', $sku)->exists()) {
                continue;
            }

            $status = Temu3ListingStatus::where('sku', $sku)->first();
            $existing = $status ? $status->value : [];

            foreach (['listed', 'buyer_link', 'seller_link'] as $field) {
                if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                    $existing[$field] = $rowData[$field];
                }
            }

            Temu3ListingStatus::updateOrCreate(
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
            'Content-Disposition' => 'attachment; filename="listing_temu3_status.csv"',
        ];

        $columns = ['sku', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $statuses = Temu3ListingStatus::query()->get(['sku', 'value'])->keyBy('sku');
            foreach (ProductMaster::pluck('sku') as $sku) {
                $value = $statuses->get($sku)?->value ?? [];
                fputcsv($file, [
                    $sku,
                    $value['listed'] ?? '',
                    $value['buyer_link'] ?? '',
                    $value['seller_link'] ?? '',
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
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

    /**
     * @return array<string, string>
     */
    private function skuParentsFromPreviewRequest(Request $request): array
    {
        $raw = $request->input('sku_parents', $request->input('skuParents', []));
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $sku => $parent) {
            if (is_array($parent)) {
                $sku = $parent['sku'] ?? $sku;
                $parent = $parent['parent'] ?? '';
            }
            $sku = trim((string) $sku);
            $parent = trim((string) $parent);
            if ($sku !== '') {
                $out[$sku] = $parent;
            }
        }

        return $out;
    }
}
