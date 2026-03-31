<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use App\Models\ProductMaster;
use App\Services\AmazonSpApiService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\MacysApiService;
use App\Services\ReverbApiService;
use App\Services\ShopifyApiService;
use App\Services\ShopifyPLSApiService;
use App\Services\Support\EbaySellInventoryListingResolver;
use App\Services\Support\EbayTradingReviseItem;
use App\Services\TemuApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImageMasterController extends Controller
{
    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('image-master', compact('mode', 'demo'));
    }

    /**
     * Product rows from Product Master + per-marketplace image_master_json (push state / last URLs).
     */
    public function getData(Request $request)
    {
        try {
            $baseResponse = app(PMController::class)->getViewProductData($request);
            $baseData = $baseResponse->getData(true);
            $products = $baseData['data'] ?? [];

            $marketTables = $this->marketplaceTableMap();
            $metricsByMarketplace = [];
            foreach ($marketTables as $marketplace => $table) {
                $metricsByMarketplace[$marketplace] = $this->loadImageMetricsBySku($table);
            }

            foreach ($products as &$row) {
                $sku = $this->normalizeSku($row['SKU'] ?? null);
                $im = [];
                foreach (array_keys($marketTables) as $mp) {
                    $im[$mp] = $metricsByMarketplace[$mp][$sku] ?? '';
                }
                $row['image_master'] = $im;
                $row['preview_thumb'] = $this->firstPreviewUrl($row);
            }

            return response()->json([
                'message' => 'Data loaded from database',
                'data' => $products,
                'status' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('ImageMaster getData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load image master data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * Amazon listing images via Listings Items API (same media path as catalog enrichment).
     */
    public function getAmazonImages(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
        ]);
        $sku = $this->normalizeSku($validated['sku']);

        try {
            $service = app(AmazonSpApiService::class);
            $res = $service->getListingsItemMedia($sku);

            return response()->json([
                'success' => (bool) ($res['success'] ?? false),
                'images' => $res['images'] ?? [],
                'videos' => $res['videos'] ?? [],
                'message' => $res['message'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'images' => [],
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * eBay gallery URLs from GetItem (Trading API). account: ebay | ebay2 | ebay3
     */
    public function getEbayImages(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'account' => 'nullable|string|in:ebay,ebay2,ebay3',
        ]);
        $sku = $this->normalizeSku($validated['sku']);
        $account = $validated['account'] ?? 'ebay';

        $tableMap = [
            'ebay' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
        ];
        $serviceMap = [
            'ebay' => EbayApiService::class,
            'ebay2' => Ebay2ApiService::class,
            'ebay3' => EbayThreeApiService::class,
        ];

        $table = $tableMap[$account];
        if (! Schema::hasTable($table)) {
            return response()->json(['success' => false, 'images' => [], 'message' => 'Metrics table missing.'], 422);
        }

        $row = DB::table($table)->where(function ($q) use ($sku) {
            $q->where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku));
        })->first();
        if (! $row && Schema::hasColumn($table, 'item_id')) {
            $row = DB::table($table)->where('item_id', $sku)->first();
        }
        $itemId = ($row && ! empty($row->item_id)) ? trim((string) $row->item_id) : null;

        try {
            $svc = app($serviceMap[$account]);
            if (! $itemId) {
                $token = $svc->generateBearerToken();
                $itemId = EbaySellInventoryListingResolver::resolveWithTradingFallback(
                    $token,
                    $svc->getTradingEndpoint(),
                    $svc->getTradingHeadersForResolver(),
                    $sku
                );
            }
            if (! $itemId) {
                return response()->json([
                    'success' => false,
                    'images' => [],
                    'message' => 'No eBay listing found for this SKU (metrics item_id empty and Inventory/GetSellerList lookup failed).',
                ], 422);
            }

            $getItem = $svc->getItem((string) $itemId);
            if (! $getItem) {
                return response()->json(['success' => false, 'images' => [], 'message' => 'GetItem failed.'], 502);
            }
            $urls = EbayTradingReviseItem::extractPictureUrlsFromGetItem($getItem);

            return response()->json([
                'success' => true,
                'images' => $urls,
                'item_id' => (string) $itemId,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'images' => [], 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Push ordered image URLs to marketplace and persist image_master_json on success (or local-only for unsupported APIs).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pushToMarketplace(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'updates' => 'required|array|min:1',
            'updates.*.marketplace' => 'required|string',
            'updates.*.images' => 'required|array|max:12',
            'updates.*.images.*' => 'nullable|string|max:2048',
        ]);

        $sku = $this->normalizeSku($validated['sku']);
        $allowed = array_keys($this->marketplaceTableMap());
        $results = [];

        foreach ($validated['updates'] as $u) {
            $mp = strtolower(trim($u['marketplace']));
            $images = array_values(array_filter(array_map('trim', $u['images'] ?? []), fn ($s) => $s !== ''));
            $images = array_slice($images, 0, 12);

            if (! in_array($mp, $allowed, true)) {
                $results[$mp] = ['success' => false, 'message' => 'Unknown marketplace'];
                continue;
            }
            if ($images === []) {
                $results[$mp] = ['success' => false, 'message' => 'No image URLs provided'];
                continue;
            }

            $remote = $this->pushImagesToRemote($mp, $sku, $images);
            $remoteOk = (bool) ($remote['success'] ?? false);
            $saved = $remoteOk && $this->saveImageMetricsToTable($mp, $sku, $images);
            $ok = $remoteOk && $saved;
            $results[$mp] = [
                'success' => $ok,
                'message' => ($remote['message'] ?? '').($saved ? '' : ' Metrics not saved.'),
            ];
        }

        $totalSuccess = collect($results)->where('success', true)->count();
        $totalFailed = collect($results)->where('success', false)->count();

        return response()->json([
            'success' => $totalFailed === 0,
            'results' => $results,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'message' => "Updated {$totalSuccess} marketplace(s).".($totalFailed > 0 ? " {$totalFailed} failed." : ''),
        ]);
    }

    /**
     * Save ordered URLs to Product Master image1–image12 and main_image.
     */
    public function saveProductMasterImages(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'images' => 'required|array|max:12',
            'images.*' => 'nullable|string|max:2048',
        ]);
        $sku = $this->normalizeSku($validated['sku']);
        $images = array_values(array_slice($validated['images'], 0, 12));

        $product = ProductMaster::query()->where('sku', $sku)->first();
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        for ($i = 0; $i < 12; $i++) {
            $col = 'image'.($i + 1);
            $product->{$col} = $images[$i] ?? null;
        }
        $product->main_image = $images[0] ?? $product->main_image;

        try {
            $product->save();

            return response()->json(['success' => true, 'message' => 'Product Master images saved.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload files to public disk; returns URL paths for use in push / PM save.
     */
    public function uploadImages(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'files' => 'required|array|min:1|max:12',
            'files.*' => 'file|image|max:10240',
        ]);
        $sku = preg_replace('/[^a-zA-Z0-9_\- ]/', '_', $this->normalizeSku($validated['sku']));
        $urls = [];

        foreach ($request->file('files', []) as $file) {
            if (! $file) {
                continue;
            }
            $path = $file->store("image-master/{$sku}", 'public');
            $urls[] = asset('storage/'.$path);
        }

        return response()->json(['success' => true, 'urls' => $urls]);
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function pushImagesToRemote(string $marketplace, string $sku, array $imageUrls): array
    {
        try {
            switch ($marketplace) {
                case 'ebay':
                    return app(EbayApiService::class)->updateListingImages($sku, $imageUrls);
                case 'ebay2':
                    return app(Ebay2ApiService::class)->updateListingImages($sku, $imageUrls);
                case 'ebay3':
                    return app(EbayThreeApiService::class)->updateListingImages($sku, $imageUrls);
                case 'amazon':
                    return app(AmazonSpApiService::class)->updateListingImages($sku, $imageUrls);
                case 'temu':
                    return app(TemuApiService::class)->updateListingImages($sku, $imageUrls);
                case 'shopify_main':
                    return app(ShopifyApiService::class)->updateListingImages($sku, $imageUrls);
                case 'shopify_pls':
                    return app(ShopifyPLSApiService::class)->updateListingImages($sku, $imageUrls);
                case 'macy':
                    return app(MacysApiService::class)->updateListingImages($sku, $imageUrls);
                case 'reverb':
                    return app(ReverbApiService::class)->updateListingImages($sku, $imageUrls);
                default:
                    return [
                        'success' => false,
                        'message' => 'Image push is not implemented for '.$marketplace.' yet.',
                    ];
            }
        } catch (\Throwable $e) {
            Log::warning('ImageMaster pushImagesToRemote failed', ['mp' => $marketplace, 'sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function saveImageMetricsToTable(string $marketplace, string $sku, array $imageUrls): bool
    {
        $table = $this->marketplaceTableMap()[$marketplace] ?? null;
        if (! $table || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'image_master_json')) {
            return false;
        }
        if (! Schema::hasColumn($table, 'sku')) {
            return false;
        }

        $payload = json_encode(array_values($imageUrls), JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return false;
        }

        try {
            $now = now();
            DB::table($table)->updateOrInsert(
                ['sku' => $sku],
                [
                    'image_master_json' => $payload,
                    'updated_at' => $now,
                ]
            );

            // Ensure created_at on first insert when column exists and was null
            if (Schema::hasColumn($table, 'created_at')) {
                DB::table($table)->where('sku', $sku)->whereNull('created_at')->update(['created_at' => $now]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning("ImageMaster: could not save {$table}", ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Marketplace key => metrics table name (same pattern as Description / Bullet masters).
     */
    private function marketplaceTableMap(): array
    {
        return [
            'ebay' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
            'amazon' => 'amazon_metrics',
            'temu' => 'temu_metrics',
            'macy' => 'macy_metrics',
            'reverb' => 'reverb_metrics',
            'shopify_main' => 'shopify_metrics',
            'shopify_pls' => 'shopify_pls_metrics',
            'walmart' => 'walmart_metrics',
            'wayfair' => 'wayfair_metrics',
            'shein' => 'shein_metrics',
            'doba' => 'doba_metrics',
            'aliexpress' => 'aliexpress_metrics',
            'bestbuy' => 'bestbuy_metrics',
        ];
    }

    /**
     * @return array<string, string> sku => image_master_json raw or ''
     */
    private function loadImageMetricsBySku(string $table): array
    {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return [];
            }
            if (! Schema::hasColumn($table, 'image_master_json')) {
                return [];
            }

            return DB::table($table)
                ->select('sku', 'image_master_json')
                ->whereNotNull('sku')
                ->get()
                ->mapWithKeys(function ($row) {
                    $raw = (string) ($row->image_master_json ?? '');
                    $trim = trim($raw);

                    return [$this->normalizeSku($row->sku) => $trim];
                })
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning("ImageMaster: load metrics failed for {$table}", ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function normalizeSku(?string $sku): string
    {
        if (! $sku) {
            return '';
        }

        return str_replace("\u{00a0}", ' ', trim((string) $sku));
    }

    /**
     * First displayable image URL for table preview column.
     *
     * @param  array<string, mixed>  $row
     */
    private function firstPreviewUrl(array $row): ?string
    {
        foreach (['image_path', 'main_image', 'image1', 'image2', 'image3'] as $k) {
            $v = $row[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $v = trim($v);
                if (str_starts_with($v, 'http') || str_starts_with($v, '//')) {
                    return $v;
                }

                return '/'.ltrim($v, '/');
            }
        }

        return null;
    }
}
