<?php

namespace App\Http\Controllers\ListingMaster;

use App\Http\Controllers\Controller;
use App\Models\AmazonListingRaw;
use App\Models\ProductMaster;
use App\Services\AmazonSpApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmzListingController extends Controller
{
    /** Max records per page to avoid memory issues. */
    private const MAX_PER_PAGE = 100;

    /** Default per page when not specified. */
    private const DEFAULT_PER_PAGE = 50;

    public function index()
    {
        return view('listing-master.amz-data');
    }

    /**
     * Normalize report_imported_at to ISO8601 string (Carbon, string, or null).
     */
    private function normalizeReportImportedAt($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value)->toIso8601String();
        }
        return null;
    }

    /**
     * Decode raw_data safely: handle null, string (JSON), or array.
     */
    private function decodeRawData($raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('AmzListingController: JSON decode error for raw_data', [
                'json_error' => json_last_error_msg(),
                'length' => strlen($raw),
            ]);
            return [];
        }
        return is_array($decoded) ? $decoded : [];
    }

    public function data(Request $request)
    {
        try {
            $perPage = (int) ($request->get('size') ?: $request->get('per_page', self::DEFAULT_PER_PAGE));
            $page = max(1, (int) $request->get('page', 1));
            Log::info('AmzListingController: Amazon list data request', ['per_page' => $perPage, 'page' => $page]);
            $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

            $query = AmazonListingRaw::query()->orderBy('id');
            $total = AmazonListingRaw::count();

            $lastImport = AmazonListingRaw::query()->whereNotNull('report_imported_at')->max('report_imported_at');
            $lastImportStr = $this->normalizeReportImportedAt($lastImport);

            $activeCount = 0;
            if ($total > 0) {
                try {
                    $activeCount = AmazonListingRaw::query()
                        ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.quantity')) AS UNSIGNED) > 0")
                        ->count();
                } catch (\Throwable $e) {
                    Log::warning('AmzListingController: active count query failed', ['error' => $e->getMessage()]);
                    $activeCount = $total;
                }
            }

            $rows = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

            $attributeColumns = [
                'item_name', 'brand', 'external_product_id', 'condition_type', 'condition_type_display',
                'color', 'material', 'style', 'size',
                'model_number', 'model_name', 'part_number', 'manufacturer',
                'exterior_finish', 'number_of_items', 'assembly_required', 'item_type_keyword', 'generic_keyword',
                'handling_time', 'merchant_shipping_group', 'minimum_advertised_price', 'your_price', 'list_price',
                'country_of_origin', 'warranty_description', 'voltage', 'noise_level', 'item_dimensions',
                'included_components', 'product_description', 'product_type', 'quantity', 'bullet_point',
            ];
            $allKeys = array_merge(['id', 'seller_sku', 'asin1', 'report_imported_at', 'thumbnail_image'], $attributeColumns);
            $data = [];
            $autoFetchLimit = $perPage;
            $fetchCount = 0;
            $mediaService = new AmazonSpApiService();
            $model = new AmazonListingRaw;
            $canPersistThumbnail = Schema::hasColumn($model->getTable(), 'thumbnail_image');

            foreach ($rows as $row) {
                $raw = $this->decodeRawData($row->raw_data);
                $allKeys = array_unique(array_merge($allKeys, array_keys($raw)));

                $reportImportedAt = $this->normalizeReportImportedAt($row->report_imported_at ?? null);

                $rawDataString = $row->getRawOriginal('raw_data');
                if ($rawDataString === null && is_array($raw)) {
                    $rawDataString = json_encode($raw, JSON_UNESCAPED_UNICODE);
                }

                $thumbnail = $row->thumbnail_image;

                if (! $thumbnail && $row->seller_sku && $fetchCount < $autoFetchLimit) {
                    try {
                        $thumbnail = $mediaService->syncThumbnailForSku($row->seller_sku);
                        if ($thumbnail && $canPersistThumbnail) {
                            $row->thumbnail_image = $thumbnail;
                            $row->save();
                        }
                    } catch (\Throwable $e) {
                        Log::warning('AmzListingController: thumbnail sync failed', [
                            'sku' => $row->seller_sku,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    $fetchCount++;
                }

                $base = [
                    'id' => $row->id ?? null,
                    'seller_sku' => $row->seller_sku ?? '',
                    'asin1' => $row->asin1 ?? '',
                    'report_imported_at' => $reportImportedAt,
                    'thumbnail_image' => $thumbnail,
                ];
                foreach ($attributeColumns as $col) {
                    if (Schema::hasColumn($model->getTable(), $col)) {
                        $base[$col] = $row->{$col};
                    }
                }
                // Map condition-type from raw_data to display if not already set
                $condRaw = $raw['condition-type'] ?? $raw['condition_type'] ?? null;
                if ($condRaw !== null && empty($base['condition_type_display'])) {
                    $base['condition_type_display'] = \App\Services\AmazonSpApiService::mapConditionType((string) $condRaw);
                }
                $data[] = array_merge($base, $raw, ['raw_data' => $rawDataString]);
            }

            $columns = array_values($allKeys);

            $thumbnailsWithImage = count(array_filter($data, fn ($r) => ! empty($r['thumbnail_image'] ?? null)));
            Log::info('AmzListingController: Amazon list data response', [
                'page' => $page,
                'returned' => count($data),
                'thumbnails_fetched' => $fetchCount,
                'thumbnails_with_image' => $thumbnailsWithImage,
            ]);

            return response()->json([
                'status' => 200,
                'data' => $data,
                'columns' => $columns,
                'total' => $total,
                'per_page' => $perPage,
                'page' => $page,
                'stats' => [
                    'total_listings' => $total,
                    'active_listings' => $activeCount,
                    'last_import_at' => $lastImportStr,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('AmzListingController: data() exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while loading listings.',
                'data' => [],
                'columns' => [],
                'total' => 0,
                'stats' => [
                    'total_listings' => 0,
                    'active_listings' => 0,
                    'last_import_at' => null,
                ],
            ], 500);
        }
    }

    /**
     * Fetch listing media (images, videos, bullet points) by seller SKU via Listings Items API.
     * GET_MERCHANT_LISTINGS_ALL_DATA does not include image/video URLs.
     */
    public function media(Request $request)
    {
        $sellerSku = $request->get('seller_sku') ?: $request->get('sku');
        if (empty($sellerSku) || ! is_string($sellerSku)) {
            return response()->json([
                'status' => 422,
                'message' => 'seller_sku or sku is required.',
                'images' => [],
                'videos' => [],
                'bullet_points' => [],
            ], 422);
        }

        // Allow cache bypass while debugging media issues:
        // /listing-master/amz-data/media?seller_sku=...&no_cache=1
        $useCache = ! $request->boolean('no_cache') && ! $request->boolean('debug');
        $cacheKey = 'amz_listing_media_' . md5($sellerSku);

        if ($useCache) {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($cached !== null && is_array($cached)) {
                return response()->json([
                    'status' => 200,
                    'images' => $cached['images'] ?? [],
                    'videos' => $cached['videos'] ?? [],
                    'bullet_points' => $cached['bullet_points'] ?? [],
                    'from_cache' => true,
                ]);
            }
        }
        $service = new AmazonSpApiService();
        $result = $service->getListingsItemMedia($sellerSku);
        if (! $result['success']) {
            return response()->json([
                'status' => 422,
                'message' => $result['message'] ?? 'Failed to fetch media.',
                'images' => $result['images'] ?? [],
                'videos' => $result['videos'] ?? [],
                'bullet_points' => $result['bullet_points'] ?? [],
            ], 422);
        }
        $images = $result['images'] ?? [];
        $videos = $result['videos'] ?? [];
        $bulletPoints = $result['bullet_points'] ?? [];

        if ($useCache) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, [
                'images' => $images,
                'videos' => $videos,
                'bullet_points' => $bulletPoints,
            ], now()->addHours(1));
        }

        return response()->json([
            'status' => 200,
            'images' => $images,
            'videos' => $videos,
            'bullet_points' => $bulletPoints,
        ]);
    }

    /** Alias for media() for backward compatibility. */
    public function images(Request $request)
    {
        return $this->media($request);
    }

    public function import(Request $request)
    {
        $service = new AmazonSpApiService();
        $result = $service->fetchAndStoreListingsReport();

        if ($result['success']) {
            $extractAfter = $request->boolean('extract_titles_after');
            $extractPayload = null;
            if ($extractAfter) {
                $extractPayload = $this->runExtractTitlesToTitleMaster();
            }

            $message = 'Import completed. ' . ($result['count'] ?? 0) . ' listings stored.';
            if ($extractPayload !== null) {
                $message .= ' Extracted ' . ($extractPayload['count'] ?? 0) . ' titles to Title Master.';
            }

            return response()->json(array_filter([
                'status' => 200,
                'message' => $message,
                'count' => $result['count'] ?? 0,
                'extract_count' => $extractPayload['count'] ?? null,
                'extract_skipped' => $extractPayload['skipped'] ?? null,
            ]));
        }

        return response()->json([
            'status' => 422,
            'message' => $result['message'] ?? 'Import failed.',
        ], 422);
    }

    /**
     * Run extraction logic and return [ 'count' => int, 'skipped' => int, 'skipped_skus' => array ].
     * When $collectSkippedSkus is true, populates skipped_skus (max 50) and logs SKUs not in product_master.
     */
    private function runExtractTitlesToTitleMaster(bool $collectSkippedSkus = false): array
    {
        $listings = AmazonListingRaw::all();
        $count = 0;
        $skipped = 0;
        $skippedSkus = [];

        foreach ($listings as $listing) {
            if (empty($listing->seller_sku)) {
                $skipped++;
                continue;
            }
            $itemName = null;
            $rawData = $listing->raw_data ? (is_string($listing->raw_data) ? json_decode($listing->raw_data, true) : $listing->raw_data) : null;
            if ($rawData && is_array($rawData)) {
                $possibleKeys = ['item-name', 'item_name', 'product-title', 'title', 'Item Name', 'itemName'];
                foreach ($possibleKeys as $key) {
                    if (! empty($rawData[$key]) && is_string($rawData[$key])) {
                        $itemName = trim($rawData[$key]);
                        break;
                    }
                }
            }
            if (empty($itemName) && ! empty($listing->item_name)) {
                $itemName = trim($listing->item_name);
            }
            if (empty($itemName)) {
                $skipped++;
                if ($collectSkippedSkus) {
                    $skippedSkus[] = $listing->seller_sku;
                }
                continue;
            }
            $title150 = mb_substr(trim($itemName), 0, 150);
            $product = ProductMaster::where('sku', $listing->seller_sku)->first();
            if (! $product) {
                $skipped++;
                if ($collectSkippedSkus) {
                    $skippedSkus[] = $listing->seller_sku;
                    Log::channel('single')->info('Extract titles: SKU not found in product_master', ['sku' => $listing->seller_sku]);
                }
                continue;
            }
            $product->title150 = $title150;
            $product->save();
            $count++;
        }

        $out = ['count' => $count, 'skipped' => $skipped];
        if ($collectSkippedSkus) {
            $out['skipped_skus'] = array_slice($skippedSkus, 0, 50);
        }

        return $out;
    }

    /**
     * Extract item_name from amazon_listings_raw and update product_master.title150 for matching SKUs.
     * POST /listing-master/amz-data/extract-titles
     */
    public function extractTitlesToTitleMaster(Request $request)
    {
        $startTime = microtime(true);
        try {
            Log::info('🔵 TITLE EXTRACTION STARTED', [
                'timestamp' => now()->toDateTimeString(),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            ]);

            $listings = AmazonListingRaw::all();
            $totalListings = $listings->count();

            Log::info('📊 Amazon Listings loaded', [
                'total_records' => $totalListings,
                'sample_skus' => $listings->take(5)->pluck('seller_sku')->toArray(),
            ]);

            $count = 0;
            $skipped = 0;
            $skippedNoSku = 0;
            $skippedNoItemName = 0;
            $skippedNoProduct = 0;
            $skippedReasons = [];

            foreach ($listings as $index => $listing) {
                if ($index > 0 && $index % 100 === 0) {
                    Log::info('⏳ Progress', [
                        'processed' => $index,
                        'successful' => $count,
                        'skipped' => $skipped,
                    ]);
                }

                if (empty($listing->seller_sku)) {
                    $skippedNoSku++;
                    $skipped++;
                    continue;
                }

                Log::debug('🔍 Processing SKU', [
                    'sku' => $listing->seller_sku,
                    'asin' => $listing->asin1,
                ]);

                $itemName = null;
                $rawData = null;
                if ($listing->raw_data) {
                    $rawData = is_string($listing->raw_data)
                        ? json_decode($listing->raw_data, true)
                        : $listing->raw_data;
                }

                if ($rawData && is_array($rawData)) {
                    $possibleKeys = ['item-name', 'item_name', 'product-title', 'title', 'Item Name', 'itemName'];
                    foreach ($possibleKeys as $key) {
                        if (! empty($rawData[$key]) && is_string($rawData[$key])) {
                            $itemName = trim($rawData[$key]);
                            Log::info('✅ Found title in raw_data', [
                                'sku' => $listing->seller_sku,
                                'key' => $key,
                                'title_preview' => mb_substr($itemName, 0, 50),
                            ]);
                            break;
                        }
                    }
                }

                if (empty($itemName) && ! empty($listing->item_name)) {
                    $itemName = trim($listing->item_name);
                    Log::info('✅ Found title in item_name column', [
                        'sku' => $listing->seller_sku,
                        'title_preview' => mb_substr($itemName, 0, 50),
                    ]);
                }

                if (empty($itemName)) {
                    Log::warning('❌ No title found anywhere', [
                        'sku' => $listing->seller_sku,
                        'has_raw_data' => ! empty($listing->raw_data),
                        'has_item_name_column' => ! empty($listing->item_name),
                    ]);
                    $skippedNoItemName++;
                    $skipped++;
                    $skippedReasons[] = [
                        'sku' => $listing->seller_sku,
                        'reason' => 'No item name in Amazon data',
                    ];
                    continue;
                }

                $title150 = mb_substr(trim($itemName), 0, 150);
                $product = ProductMaster::where('sku', $listing->seller_sku)->first();

                if (! $product) {
                    $skippedNoProduct++;
                    $skipped++;
                    $skippedReasons[] = [
                        'sku' => $listing->seller_sku,
                        'reason' => 'SKU not found in product_master',
                    ];
                    Log::channel('single')->info('Extract titles: SKU not found in product_master', ['sku' => $listing->seller_sku]);
                    continue;
                }

                $oldTitle = $product->title150;
                $product->title150 = $title150;
                $product->save();
                $count++;

                Log::info('✅ Title updated', [
                    'sku' => $listing->seller_sku,
                    'old_title' => $oldTitle ? mb_substr($oldTitle, 0, 50) . '...' : 'EMPTY',
                    'new_title' => mb_substr($title150, 0, 50) . '...',
                    'asin' => $listing->asin1,
                ]);
            }

            $executionTime = round(microtime(true) - $startTime, 2);
            Log::info('🔵 TITLE EXTRACTION COMPLETED', [
                'total_listings' => $totalListings,
                'successful_updates' => $count,
                'total_skipped' => $skipped,
                'breakdown' => [
                    'no_sku' => $skippedNoSku,
                    'no_item_name' => $skippedNoItemName,
                    'sku_not_in_product_master' => $skippedNoProduct,
                ],
                'sample_skipped' => array_slice($skippedReasons, 0, 20),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'execution_time' => $executionTime . ' seconds',
            ]);

            return response()->json([
                'success' => true,
                'count' => $count,
                'skipped' => $skipped,
                'skipped_skus' => array_slice(array_column($skippedReasons, 'sku'), 0, 50),
                'details' => [
                    'no_sku' => $skippedNoSku,
                    'no_item_name' => $skippedNoItemName,
                    'sku_not_found' => $skippedNoProduct,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('❌ TITLE EXTRACTION FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze Amazon listings data quality (item_name presence, raw_data keys).
     * GET /listing-master/amz-data/analyze
     */
    public function analyzeAmazonData()
    {
        Log::info('📊 AMAZON DATA ANALYSIS STARTED');

        $total = AmazonListingRaw::count();
        $withSku = AmazonListingRaw::whereNotNull('seller_sku')->where('seller_sku', '!=', '')->count();
        $withItemName = AmazonListingRaw::whereNotNull('item_name')->where('item_name', '!=', '')->count();
        $withRawData = AmazonListingRaw::whereNotNull('raw_data')->count();

        $hasItemNameInRaw = 0;
        $sampleMissing = [];

        AmazonListingRaw::chunk(100, function ($listings) use (&$hasItemNameInRaw, &$sampleMissing) {
            foreach ($listings as $listing) {
                if ($listing->raw_data) {
                    $raw = is_string($listing->raw_data)
                        ? json_decode($listing->raw_data, true)
                        : $listing->raw_data;
                    if (is_array($raw) && (! empty($raw['item-name']) || ! empty($raw['item_name']))) {
                        $hasItemNameInRaw++;
                    } elseif (empty($listing->item_name) && count($sampleMissing) < 20) {
                        $sampleMissing[] = [
                            'sku' => $listing->seller_sku,
                            'asin' => $listing->asin1,
                            'has_raw' => ! empty($listing->raw_data),
                        ];
                    }
                }
            }
        });

        $missingPct = $total > 0 ? round((($total - $withItemName) / $total) * 100, 2) . '%' : '0%';
        Log::info('📊 AMAZON DATA ANALYSIS RESULTS', [
            'total_records' => $total,
            'with_sku' => $withSku,
            'with_item_name_column' => $withItemName,
            'with_raw_data' => $withRawData,
            'has_item_name_in_raw' => $hasItemNameInRaw,
            'sample_missing_titles' => $sampleMissing,
            'missing_percentage' => $missingPct,
        ]);

        return response()->json([
            'total' => $total,
            'with_item_name' => $withItemName,
            'in_raw_data' => $hasItemNameInRaw,
            'sample_missing' => $sampleMissing,
        ]);
    }

    /**
     * Debug a specific SKU in Amazon listings and product_master.
     * GET /listing-master/amz-data/debug/{sku}
     */
    public function debugSku(string $sku)
    {
        $sku = trim($sku);
        Log::info('🔍 DEBUGGING SKU: ' . $sku);

        $amazon = AmazonListingRaw::where('seller_sku', $sku)
            ->orWhere('seller_sku', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $sku) . '%')
            ->first();

        if (! $amazon) {
            Log::warning('❌ SKU not found in Amazon listings', ['sku' => $sku]);

            return response()->json(['error' => 'SKU not in Amazon listings', 'sku' => $sku], 404);
        }

        $rawKeys = [];
        if ($amazon->raw_data) {
            $decoded = is_string($amazon->raw_data) ? json_decode($amazon->raw_data, true) : $amazon->raw_data;
            $rawKeys = is_array($decoded) ? array_keys($decoded) : [];
        }
        Log::info('✅ Found in Amazon', [
            'sku' => $amazon->seller_sku,
            'asin' => $amazon->asin1,
            'item_name_column' => $amazon->item_name ? mb_substr($amazon->item_name, 0, 100) : null,
            'raw_data_keys' => $rawKeys,
        ]);

        $product = ProductMaster::where('sku', $amazon->seller_sku)->first();
        if (! $product) {
            Log::warning('❌ SKU not found in Product Master', ['sku' => $amazon->seller_sku]);
        } else {
            Log::info('✅ Found in Product Master', [
                'sku' => $product->sku,
                'current_title150' => $product->title150 ? mb_substr($product->title150, 0, 80) : null,
            ]);
        }

        return response()->json([
            'amazon' => [
                'sku' => $amazon->seller_sku,
                'asin' => $amazon->asin1,
                'item_name' => $amazon->item_name,
                'has_raw_data' => ! empty($amazon->raw_data),
            ],
            'product_master' => $product ? [
                'sku' => $product->sku,
                'title150' => $product->title150,
            ] : null,
        ]);
    }

    /**
     * Inspect raw_data structure and title sources for a given SKU.
     * GET /listing-master/amz-data/check-raw/{sku}
     */
    public function checkRawData(string $sku)
    {
        $sku = trim($sku);
        $listing = AmazonListingRaw::where('seller_sku', $sku)->first();

        if (! $listing) {
            return response()->json(['error' => 'SKU not found', 'sku' => $sku], 404);
        }

        $rawData = is_string($listing->raw_data)
            ? json_decode($listing->raw_data, true)
            : $listing->raw_data;

        $titleSources = [];
        $possibleKeys = ['item-name', 'item_name', 'product-title', 'title', 'Item Name', 'itemName'];
        foreach ($possibleKeys as $key) {
            $val = $rawData[$key] ?? null;
            $titleSources[$key] = $val !== null && is_string($val) ? mb_substr($val, 0, 100) : $val;
        }

        $sample = [];
        if (is_array($rawData)) {
            $keys = array_keys($rawData);
            $firstKeys = array_slice($keys, 0, 10);
            foreach ($firstKeys as $k) {
                $v = $rawData[$k];
                $sample[$k] = is_string($v) ? mb_substr($v, 0, 80) : $v;
            }
        }

        return response()->json([
            'sku' => $listing->seller_sku,
            'asin' => $listing->asin1,
            'item_name_column' => $listing->item_name,
            'raw_data_keys' => is_array($rawData) ? array_keys($rawData) : [],
            'title_in_raw_data' => $titleSources,
            'full_raw_data_sample' => $sample,
        ]);
    }

    /**
     * Enrich a single SKU (e.g. 3501 USB) for testing.
     * POST /listing-master/amz-data/enrich-single with seller_sku=3501 USB
     */
    public function enrichSingle(Request $request)
    {
        $sellerSku = $request->input('seller_sku') ?: $request->input('sku');
        if (empty($sellerSku) || ! is_string($sellerSku)) {
            return response()->json([
                'status' => 422,
                'message' => 'seller_sku or sku is required.',
            ], 422);
        }
        $service = new AmazonSpApiService();
        $result = $service->enrichSingleSku(trim($sellerSku), true);
        if (isset($result['error'])) {
            return response()->json([
                'status' => 422,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Enriched successfully.',
            'sku' => $result['sku'],
            'asin' => $result['asin'],
            'updates_count' => $result['updates_count'],
            'warnings' => $result['warnings'] ?? [],
        ]);
    }

    /**
     * Debug endpoint to compare local vs production config.
     * GET /listing-master/amz-data/import-debug
     */
    public function importDebug()
    {
        $service = new \App\Services\AmazonSpApiService();
        $accessToken = $service->getAccessToken();
        $rawCount = \App\Models\AmazonListingRaw::count();

        return response()->json([
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'marketplace_id' => config('services.amazon_sp.marketplace_id'),
            'access_token_ok' => !empty($accessToken),
            'amazon_listings_raw_count' => $rawCount,
        ]);
    }
}
