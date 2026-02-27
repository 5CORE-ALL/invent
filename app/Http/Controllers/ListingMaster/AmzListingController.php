<?php

namespace App\Http\Controllers\ListingMaster;

use App\Http\Controllers\Controller;
use App\Models\AmazonListingRaw;
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

            $allKeys = ['id', 'seller_sku', 'asin1', 'report_imported_at', 'thumbnail_image'];
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

                $data[] = array_merge(
                    [
                        'id' => $row->id ?? null,
                        'seller_sku' => $row->seller_sku ?? '',
                        'asin1' => $row->asin1 ?? '',
                        'report_imported_at' => $reportImportedAt,
                        'thumbnail_image' => $thumbnail,
                    ],
                    $raw,
                    ['raw_data' => $rawDataString]
                );
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
            return response()->json([
                'status' => 200,
                'message' => 'Import completed. ' . ($result['count'] ?? 0) . ' listings stored.',
                'count' => $result['count'] ?? 0,
            ]);
        }

        return response()->json([
            'status' => 422,
            'message' => $result['message'] ?? 'Import failed.',
        ], 422);
    }
}
