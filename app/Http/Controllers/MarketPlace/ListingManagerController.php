<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonListingRaw;
use App\Models\ChannelMaster;
use App\Models\Ebay2Metric;
use App\Models\ListingManagerChannelDraft;
use App\Models\ListingManagerEnabledChannel;
use App\Models\ProductMaster;
use App\Models\ProductRawImage;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use App\Services\Ebay2ApiService;
use App\Services\ShopifyApiService;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\ListingManagerEbayDescriptionBuilder;
use App\Support\Marketplace\ListingManagerPublishStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ListingManagerController extends Controller
{
    public function index()
    {
        $lastSync = null;
        if (Schema::hasTable('amazon_listings_raw')) {
            $raw = AmazonListingRaw::query()->max('report_imported_at')
                ?: AmazonListingRaw::query()->max('updated_at');
            if ($raw) {
                $lastSync = Carbon::parse($raw);
            }
        }

        return view('market-places.listing-manager.index', [
            'lastSync' => $lastSync,
            'lastSyncHuman' => $lastSync ? $lastSync->diffForHumans() : 'Never',
        ]);
    }

    /**
     * Tabulator / grid data from amazon_listings_raw (Amazon origin).
     */
    public function data(Request $request)
    {
        try {
            if (! Schema::hasTable('amazon_listings_raw')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'meta' => ['in_stock' => 0, 'out_of_stock' => 0, 'total' => 0],
                ]);
            }

            $stock = strtolower(trim((string) $request->input('stock', 'all'))); // all|in|out
            $qName = trim((string) $request->input('q_name', ''));
            $qSku = trim((string) $request->input('q_sku', ''));
            $status = trim((string) $request->input('status', 'all'));
            $productType = trim((string) $request->input('product_type', 'all'));

            $query = AmazonListingRaw::query()
                ->whereNotNull('seller_sku')
                ->where('seller_sku', '!=', '');

            if ($qSku !== '') {
                $query->where('seller_sku', 'like', '%' . $qSku . '%');
            }
            if ($qName !== '') {
                $query->where('item_name', 'like', '%' . $qName . '%');
            }
            if ($productType !== '' && strtolower($productType) !== 'all') {
                $query->where('product_type', $productType);
            }
            if ($status !== '' && strtolower($status) !== 'all') {
                $query->where('condition_type_display', $status);
            }

            // Counts before stock filter (for tabs)
            $baseForTabs = clone $query;
            $inStockCount = (clone $baseForTabs)->where('quantity', '>', 0)->count();
            $outStockCount = (clone $baseForTabs)->where(function ($q) {
                $q->whereNull('quantity')->orWhere('quantity', '<=', 0);
            })->count();

            if ($stock === 'in') {
                $query->where('quantity', '>', 0);
            } elseif ($stock === 'out') {
                $query->where(function ($q) {
                    $q->whereNull('quantity')->orWhere('quantity', '<=', 0);
                });
            }

            $rows = $query->orderByDesc('quantity')->orderBy('seller_sku')->get([
                'id',
                'seller_sku',
                'asin1',
                'item_name',
                'thumbnail_image',
                'quantity',
                'your_price',
                'list_price',
                'product_type',
                'condition_type_display',
                'brand',
                'raw_data',
                'report_imported_at',
                'updated_at',
            ]);

            $draftCounts = [];
            $skus = $rows->pluck('seller_sku')->unique()->values()->all();
            if (Schema::hasTable('listing_manager_channel_drafts') && $skus !== []) {
                $draftCounts = ListingManagerChannelDraft::query()
                    ->whereIn('seller_sku', $skus)
                    ->selectRaw('seller_sku, COUNT(*) as draft_count')
                    ->groupBy('seller_sku')
                    ->pluck('draft_count', 'seller_sku')
                    ->all();
            }
            $imageLookups = $this->imageLookupsForSkus($skus);

            $data = $rows->map(function (AmazonListingRaw $row) use ($draftCounts, $imageLookups) {
                $raw = is_array($row->raw_data) ? $row->raw_data : [];
                $rawName = '';
                foreach ($raw as $k => $v) {
                    $norm = ltrim((string) $k, "\xEF\xBB\xBF");
                    if (strcasecmp($norm, 'item-name') === 0 || strcasecmp($norm, 'item_name') === 0) {
                        $rawName = trim((string) $v);
                        break;
                    }
                }
                $qty = (int) ($row->quantity ?? ($raw['quantity'] ?? 0));
                $price = $row->your_price !== null ? (float) $row->your_price : (isset($raw['price']) ? (float) $raw['price'] : null);
                $listPrice = $row->list_price !== null ? (float) $row->list_price : null;
                $thumb = trim((string) ($row->thumbnail_image ?: ($raw['image-url'] ?? $raw['image-url-1'] ?? '')));
                $sku = (string) $row->seller_sku;
                $hero = $imageLookups['hero'][$sku] ?? ($thumb !== '' ? $thumb : null);
                $rawInfo = $imageLookups['raw'][$sku] ?? ['url' => null, 'count' => 0, 'previewable' => false];
                $rawAi = $imageLookups['raw_ai'][$sku] ?? ['url' => null, 'count' => 0, 'previewable' => false];
                $rawBatch = $imageLookups['raw_batch'][$sku] ?? ['url' => null, 'count' => 0, 'previewable' => false];

                return [
                    'id' => $row->id,
                    'thumbnail' => $thumb !== '' ? $thumb : null,
                    'hero_image' => $hero,
                    'raw_ai_image' => $rawAi['url'],
                    'raw_ai_image_count' => (int) $rawAi['count'],
                    'raw_ai_image_previewable' => (bool) $rawAi['previewable'],
                    'raw_image' => $rawInfo['url'],
                    'raw_image_count' => (int) $rawInfo['count'],
                    'raw_image_previewable' => (bool) $rawInfo['previewable'],
                    'raw_batch_image' => $rawBatch['url'],
                    'raw_batch_image_count' => (int) $rawBatch['count'],
                    'raw_batch_image_previewable' => (bool) $rawBatch['previewable'],
                    'name' => trim((string) ($row->item_name ?: $rawName ?: $row->seller_sku)),
                    'sku' => $sku,
                    'asin' => $row->asin1 ?: ($raw['asin1'] ?? null),
                    'origin' => 'Amazon',
                    'manage_stock' => 'Yes',
                    'in_stock' => $qty > 0 ? 'Yes' : 'No',
                    'total_available' => $qty,
                    'variants' => 0,
                    'price' => $price,
                    'sale_price' => ($listPrice && $price && $listPrice > $price) ? $price : null,
                    'list_price' => $listPrice,
                    'product_type' => $row->product_type,
                    'condition' => $row->condition_type_display,
                    'brand' => $row->brand,
                    'draft_channels' => (int) ($draftCounts[$sku] ?? 0),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'in_stock' => $inStockCount,
                    'out_of_stock' => $outStockCount,
                    'total' => $inStockCount + $outStockCount,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('ListingManager data failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * LitCommerce-style product detail (All Products → click name).
     * Combines Amazon SP-API / amazon_listings_raw + Shopify main store when available.
     */
    public function showProduct(Request $request)
    {
        $sku = trim((string) $request->input('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
        $listing = AmazonListingRaw::query()->where('seller_sku', $sku)->first();
        $raw = is_array($listing?->raw_data) ? $listing->raw_data : [];
        $pm = Schema::hasTable('product_master')
            ? (array) (DB::table('product_master')->where('sku', $sku)->first() ?: [])
            : [];

        // Live Amazon images via Listings Items / Catalog
        $amazonImages = $this->fetchLiveAmazonImages($sku, (string) ($hydrated['asin'] ?? ''));
        if ($amazonImages === [] && ! empty($hydrated['images'])) {
            $amazonImages = $hydrated['images'];
        }

        // Enrich title/desc/price from Amazon listings item API when columns sparse
        $amazonListing = [];
        try {
            $amazonListing = (new AmazonSpApiService())->getListingsItemMedia($sku);
        } catch (\Throwable $e) {
            Log::warning('ListingManager showProduct listings media: '.$e->getMessage());
        }

        // Main store (Shopify) product payload
        $shopify = ['success' => false];
        try {
            $shopify = app(ShopifyApiService::class)->fetchProductDescriptionHtml($sku);
        } catch (\Throwable $e) {
            Log::warning('ListingManager showProduct Shopify: '.$e->getMessage());
        }

        $shopifyImages = array_values(array_filter(($shopify['images'] ?? []) ?: []));
        $imageLookups = $this->imageLookupsForSkus([$sku]);
        $heroImage = $imageLookups['hero'][$sku] ?? null;
        $pmImages = $imageLookups['pm_images'][$sku] ?? [];
        // Prefer live Amazon Marketplace API images; fall back to Image Master / Shopify.
        $images = $amazonImages !== [] ? $amazonImages : ($pmImages !== [] ? $pmImages : $shopifyImages);
        if ($images === [] && $shopifyImages !== []) {
            $images = $shopifyImages;
        }
        if ($heroImage && ($images === [] || ($images[0] ?? '') !== $heroImage)) {
            $images = array_values(array_unique(array_merge([$heroImage], $images)));
        }
        if ($heroImage === null && $images !== []) {
            $heroImage = $images[0];
        }
        $description = trim((string) (
            ($shopify['success'] ?? false) ? ($shopify['html'] ?? '') : ''
        ));
        if ($description === '') {
            $description = (string) ($hydrated['description'] ?: ($pm['description_html'] ?? ''));
        }

        $title = self::firstFilled([
            $hydrated['title'] ?? null,
            $listing?->item_name,
            ($shopify['success'] ?? false) ? ($shopify['title'] ?? null) : null,
            $pm['title150'] ?? null,
            $sku,
        ]);

        $price = $hydrated['price'];
        $qty = $hydrated['quantity'];
        $listPrice = $listing?->list_price !== null ? (float) $listing->list_price : null;
        $salePrice = ($listPrice && $price && $listPrice > $price) ? $price : null;

        $dims = [
            'length' => $hydrated['package_length'] ?: ($pm['package_length'] ?? ''),
            'width' => $hydrated['package_width'] ?: '',
            'height' => $hydrated['package_height'] ?: '',
            'weight_lb' => $hydrated['package_weight_lb'] ?: '',
            'weight_oz' => $hydrated['package_weight_oz'] ?: '',
        ];
        if (is_array($listing?->item_dimensions)) {
            $idims = $listing->item_dimensions;
            $dims['length'] = $dims['length'] ?: (string) ($idims['length']['value'] ?? $idims['length'] ?? '');
            $dims['width'] = $dims['width'] ?: (string) ($idims['width']['value'] ?? $idims['width'] ?? '');
            $dims['height'] = $dims['height'] ?: (string) ($idims['height']['value'] ?? $idims['height'] ?? '');
        }

        // Channel drafts / listed status for Listings tab
        $drafts = [];
        if (Schema::hasTable('listing_manager_channel_drafts')) {
            $drafts = ListingManagerChannelDraft::query()
                ->with('channel:id,channel,logo')
                ->where('seller_sku', $sku)
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (ListingManagerChannelDraft $d) => $this->serializeDraft($d))
                ->values()
                ->all();
        }

        $amazonLive = ListingManagerPublishStatus::check('Amazon', $sku);
        $listedOn = [];
        if ($amazonLive['listed'] || trim((string) ($hydrated['asin'] ?? $listing?->asin1 ?? '')) !== '') {
            $asin = (string) ($hydrated['asin'] ?? $listing?->asin1 ?? '');
            $listedOn[] = [
                'channel' => 'Amazon',
                'logo' => null,
                'product_name' => $title,
                'qty' => $qty,
                'price' => $price,
                'status' => strtolower((string) ($raw['status'] ?? 'Active')) === 'active' ? 'ACTIVE' : (string) ($raw['status'] ?? 'ACTIVE'),
                'external_url' => $asin !== '' ? 'https://www.amazon.com/dp/'.$asin : null,
                'listing_id' => $asin,
            ];
        }
        foreach ($drafts as $d) {
            if (($d['status'] ?? '') === 'listed') {
                $listedOn[] = [
                    'channel' => $d['channel'] ?? '',
                    'logo' => $d['channel_logo'] ?? null,
                    'product_name' => $d['title'] ?? $title,
                    'qty' => $d['quantity'] ?? null,
                    'price' => $d['price'] ?? null,
                    'status' => 'ACTIVE',
                    'external_url' => ! empty($d['external_listing_id']) && stripos((string) $d['channel'], 'ebay') !== false
                        ? 'https://www.ebay.com/itm/'.$d['external_listing_id']
                        : ($d['listing_page_url'] ?? null),
                    'listing_id' => $d['external_listing_id'] ?? null,
                    'draft_id' => $d['id'] ?? null,
                ];
            }
        }

        $enabledChannels = [];
        try {
            $chRes = $this->channels($request)->getData(true);
            foreach (($chRes['channels'] ?? []) as $ch) {
                if (! ($ch['enabled'] ?? false)) {
                    continue;
                }
                $name = (string) ($ch['channel'] ?? '');
                $already = collect($listedOn)->contains(fn ($r) => strcasecmp((string) $r['channel'], $name) === 0);
                if (! $already) {
                    $enabledChannels[] = [
                        'id' => $ch['id'],
                        'channel' => $name,
                        'logo' => $ch['logo'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $metafields = [];
        foreach ([
            'asin' => $hydrated['asin'] ?? $listing?->asin1,
            'brand' => $hydrated['brand'] ?? $listing?->brand,
            'product_type' => $hydrated['product_type'] ?? $listing?->product_type,
            'condition' => $hydrated['condition'] ?? $listing?->condition_type_display,
            'external_product_id' => $listing?->external_product_id ?? ($raw['product-id'] ?? null),
            'listing_id' => $raw['listing-id'] ?? null,
            'fulfillment_channel' => $raw['fulfillment-channel'] ?? null,
            'merchant_shipping_group' => $listing?->merchant_shipping_group ?? ($raw['merchant-shipping-group'] ?? null),
            'package_length' => $dims['length'],
            'package_width' => $dims['width'],
            'package_height' => $dims['height'],
            'package_weight_lb' => $dims['weight_lb'],
            'package_weight_oz' => $dims['weight_oz'],
            'shopify_product_id' => $shopify['product_id'] ?? null,
            'amazon_image_count' => count($amazonImages),
            'shopify_image_count' => count($shopifyImages),
        ] as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $metafields[] = ['name' => $k, 'value' => is_scalar($v) ? (string) $v : json_encode($v)];
        }
        // Extra product_master keys that look like metafields
        foreach ($pm as $k => $v) {
            if ($v === null || $v === '' || in_array($k, ['id', 'sku', 'created_at', 'updated_at'], true)) {
                continue;
            }
            if (preg_match('/^(title|description|image|main_image)/i', (string) $k)) {
                continue;
            }
            if (is_scalar($v) || (is_string($v) && strlen($v) < 2000)) {
                $metafields[] = ['name' => (string) $k, 'value' => is_scalar($v) ? (string) $v : (string) $v];
            }
        }

        $changelog = [];
        if ($listing?->updated_at) {
            $changelog[] = [
                'thumbnail' => $images[0] ?? $hydrated['thumbnail'],
                'details' => 'Amazon listing data updated',
                'changed_at' => $listing->updated_at->toDateTimeString(),
            ];
        }
        if ($listing?->report_imported_at) {
            $changelog[] = [
                'thumbnail' => $images[0] ?? null,
                'details' => 'Imported from Amazon listings report',
                'changed_at' => Carbon::parse($listing->report_imported_at)->toDateTimeString(),
            ];
        }
        foreach ($drafts as $d) {
            $changelog[] = [
                'thumbnail' => $d['thumbnail'] ?? null,
                'details' => 'Channel draft '.$d['channel'].' → '.$d['status'].($d['ui_status'] ? ' ('.$d['ui_status'].')' : ''),
                'changed_at' => $d['updated_at'] ?? null,
            ];
        }

        $shopifyAdminUrl = null;
        $pid = $shopify['product_id'] ?? null;
        if ($pid) {
            $domain = rtrim(preg_replace('#^https?://#', '', (string) (config('services.shopify.store_url') ?: config('services.shopify.domain') ?: '')), '/');
            $numeric = preg_replace('/\D+/', '', (string) $pid);
            if ($domain && $numeric !== '') {
                $shopifyAdminUrl = 'https://'.$domain.'/admin/products/'.$numeric;
            }
        }

        return response()->json([
            'success' => true,
            'product' => [
                'sku' => $sku,
                'asin' => $hydrated['asin'] ?? $listing?->asin1,
                'title' => $title,
                'status' => strtoupper((string) ($raw['status'] ?? 'ACTIVE')),
                'origin' => ($shopify['success'] ?? false) ? 'Main Store' : 'Amazon',
                'upc' => (string) ($listing?->external_product_id ?? ($raw['product-id'] ?? ($pm['upc'] ?? ''))),
                'mpn' => (string) ($pm['mpn'] ?? $pm['part_number'] ?? $listing?->part_number ?? ''),
                'vendor' => (string) ($hydrated['brand'] ?: ($pm['brand'] ?? $listing?->brand ?? '')),
                'product_type' => (string) ($hydrated['product_type'] ?: ($pm['product_type'] ?? $listing?->product_type ?? '')),
                'tags' => (string) ($pm['tags'] ?? $pm['generic_keyword'] ?? ''),
                'collections' => (string) ($pm['collection'] ?? ''),
                'price' => $price,
                'sale_price' => $salePrice,
                'list_price' => $listPrice,
                'msrp' => $listPrice,
                'cost' => null,
                'manage_stock' => 'Yes',
                'in_stock' => (($qty ?? 0) > 0) ? 'Yes' : 'No',
                'quantity' => $qty,
                'condition' => (string) ($hydrated['condition'] ?: 'New'),
                'package_weight' => trim(($dims['weight_lb'] !== '' ? $dims['weight_lb'].' lb' : '').($dims['weight_oz'] !== '' ? ' '.$dims['weight_oz'].' oz' : '')),
                'package_dimensions' => trim(implode(' x ', array_filter([
                    $dims['length'] !== '' ? 'L: '.$dims['length'] : '',
                    $dims['width'] !== '' ? 'W: '.$dims['width'] : '',
                    $dims['height'] !== '' ? 'H: '.$dims['height'] : '',
                ]))).($dims['length'] !== '' ? ' in' : ''),
                'store_url' => (static function () use ($hydrated, $listing) {
                    $asin = trim((string) ($hydrated['asin'] ?? $listing?->asin1 ?? ''));

                    return $asin !== '' ? 'https://www.amazon.com/dp/'.$asin : null;
                })(),
                'description' => $description,
                'seo_description' => (string) ($pm['seo_description'] ?? $pm['meta_description'] ?? ''),
                'meta_title' => (string) ($pm['meta_title'] ?? ''),
                'short_description' => (string) ($pm['short_description'] ?? ''),
                'images' => $images,
                'hero_image' => $heroImage,
                'amazon_images' => $amazonImages,
                'shopify_images' => $shopifyImages,
                'variations' => [[
                    'sku' => $sku,
                    'title' => $title,
                    'price' => $price,
                    'quantity' => $qty,
                    'asin' => $hydrated['asin'] ?? $listing?->asin1,
                ]],
                'metafields' => $metafields,
                'listed_on' => $listedOn,
                'not_listed_on' => $enabledChannels,
                'drafts' => $drafts,
                'changelog' => $changelog,
                'shopify_admin_url' => $shopifyAdminUrl,
                'shopify_ok' => (bool) ($shopify['success'] ?? false),
                'amazon_media_ok' => (bool) ($amazonListing['success'] ?? false),
                'updated_at' => $listing?->updated_at?->toDateTimeString(),
                'imported_at' => $listing?->report_imported_at
                    ? Carbon::parse($listing->report_imported_at)->toDateTimeString()
                    : null,
            ],
        ]);
    }

    private static function firstFilled(array $values): string
    {
        foreach ($values as $v) {
            $s = trim((string) ($v ?? ''));
            if ($s !== '') {
                return $s;
            }
        }

        return '';
    }

    /**
     * Import / refresh all Amazon listing details into amazon_listings_raw.
     */
    public function importFromAmazon(Request $request)
    {
        try {
            set_time_limit(0);
            $service = new AmazonSpApiService();
            $result = $service->fetchAndStoreListingsReport();

            if (! ($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Amazon import failed.',
                ], 422);
            }

            $count = (int) ($result['count'] ?? 0);
            Cache::put('listing_manager_last_import_at', now()->toIso8601String(), now()->addDays(7));

            return response()->json([
                'success' => true,
                'message' => "Imported {$count} Amazon listings successfully.",
                'count' => $count,
                'last_sync' => now()->diffForHumans(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ListingManager importFromAmazon failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Channels available for "List Products On Channel" modal.
     */
    public function channels(Request $request)
    {
        $allActive = ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->orderBy('channel')
            ->get(['id', 'channel', 'logo']);

        $enabledIds = Schema::hasTable('listing_manager_enabled_channels')
            ? ListingManagerEnabledChannel::query()
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->pluck('channel_id')
                ->all()
            : [];

        // Default: active channels with a listing page (except Amazon itself as origin)
        if ($enabledIds === []) {
            $enabledIds = $allActive
                ->filter(function ($c) {
                    $name = (string) $c->channel;
                    $key = ListingChannelCounts::normalize($name);
                    if (in_array($key, ['amazon', 'amazonfba'], true)) {
                        return false;
                    }

                    return ListingChannelCounts::hasListingSource($name);
                })
                ->pluck('id')
                ->all();
        }

        $enabledSet = array_fill_keys(array_map('intval', $enabledIds), true);

        $channels = $allActive->map(function ($c) use ($enabledSet) {
            return [
                'id' => $c->id,
                'channel' => $c->channel,
                'logo' => $c->logo,
                'enabled' => isset($enabledSet[(int) $c->id]),
                'listing_url' => ListingChannelCounts::listingUrl((string) $c->channel),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'channels' => $channels,
            'enabled_ids' => array_values(array_map('intval', $enabledIds)),
        ]);
    }

    /**
     * Save which marketplaces appear in the List-on-Channel modal.
     */
    public function saveEnabledChannels(Request $request)
    {
        $request->validate([
            'channel_ids' => 'required|array',
            'channel_ids.*' => 'integer|exists:channel_master,id',
        ]);

        if (! Schema::hasTable('listing_manager_enabled_channels')) {
            return response()->json(['success' => false, 'message' => 'Run migrations first.'], 500);
        }

        $ids = array_values(array_unique(array_map('intval', $request->input('channel_ids', []))));

        ListingManagerEnabledChannel::query()->delete();
        foreach ($ids as $i => $id) {
            ListingManagerEnabledChannel::create([
                'channel_id' => $id,
                'is_enabled' => true,
                'sort_order' => $i,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Marketplaces updated.',
            'enabled_ids' => $ids,
        ]);
    }

    /**
     * Add selected Amazon products to channel drafts (List Products On Channel).
     */
    public function addToChannelDrafts(Request $request)
    {
        $request->validate([
            'skus' => 'required|array|min:1',
            'skus.*' => 'string|max:255',
            'channel_ids' => 'required|array|min:1',
            'channel_ids.*' => 'integer|exists:channel_master,id',
        ]);

        if (! Schema::hasTable('listing_manager_channel_drafts')) {
            return response()->json(['success' => false, 'message' => 'Run migrations first.'], 500);
        }

        $skus = array_values(array_unique(array_filter(array_map('trim', $request->input('skus', [])))));
        $channelIds = array_values(array_unique(array_map('intval', $request->input('channel_ids', []))));

        $channelMap = ChannelMaster::query()
            ->whereIn('id', $channelIds)
            ->pluck('channel', 'id');

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($channelIds as $channelId) {
            $channelName = (string) ($channelMap[$channelId] ?? '');
            foreach ($skus as $sku) {
                $hydrated = ListingManagerAmazonHydrator::hydrate($sku);
                if ($hydrated['title'] === '' && $hydrated['images'] === [] && $hydrated['price'] === null) {
                    // Still allow if amazon_listings_raw row exists
                    if (! AmazonListingRaw::query()->where('seller_sku', $sku)->exists()) {
                        $skipped++;
                        continue;
                    }
                }

                $details = $this->ensureIdentifierDefaults(
                    ListingManagerAmazonHydrator::detailsFromHydration($hydrated, [], $channelName),
                    $sku
                );
                $ready = ListingManagerPublishStatus::readiness(
                    $hydrated['title'],
                    $hydrated['price'],
                    $hydrated['quantity'],
                    $details,
                    'draft',
                    $channelName
                );

                $payload = [
                    'asin' => $hydrated['asin'],
                    'title' => $hydrated['title'],
                    'thumbnail_image' => $hydrated['thumbnail'],
                    'price' => $hydrated['price'],
                    'quantity' => $hydrated['quantity'],
                    'status' => $ready['ready'] ? 'ready' : 'draft',
                    'listing_details' => $details,
                    'amazon_snapshot' => $hydrated['snapshot'],
                    'created_by' => optional(Auth::user())->id,
                    'notes' => 'Added from Listing Manager',
                ];

                $existing = ListingManagerChannelDraft::query()
                    ->where('channel_id', $channelId)
                    ->where('seller_sku', $sku)
                    ->first();

                if ($existing) {
                    if ($existing->status !== 'listed') {
                        $existing->fill($payload)->save();
                    }
                    $updated++;
                } else {
                    ListingManagerChannelDraft::create(array_merge($payload, [
                        'channel_id' => $channelId,
                        'seller_sku' => $sku,
                    ]));
                    $created++;
                }
            }
        }

        $channelNames = $channelMap->implode(', ');

        return response()->json([
            'success' => true,
            'message' => "Added {$created} draft(s)" . ($updated ? ", updated {$updated}" : '') .
                " for: {$channelNames}." .
                ' Open Channel Drafts → complete Missing Info → Save & Publish to eBay.',
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Channel Drafts grid for Listing Manager.
     */
    public function drafts(Request $request)
    {
        if (! Schema::hasTable('listing_manager_channel_drafts')) {
            return response()->json(['success' => true, 'data' => [], 'meta' => ['total' => 0]]);
        }

        $channelId = (int) $request->input('channel_id', 0);
        $status = trim((string) $request->input('status', 'all'));
        $tab = strtolower(trim((string) $request->input('tab', 'drafts'))); // drafts|active|all
        $q = trim((string) $request->input('q', ''));
        $qSku = trim((string) $request->input('q_sku', ''));

        $query = ListingManagerChannelDraft::query()->with('channel:id,channel,logo');

        if ($channelId > 0) {
            $query->where('channel_id', $channelId);
        }
        if ($status !== '' && strtolower($status) !== 'all') {
            $query->where('status', $status);
        }
        if ($tab === 'drafts') {
            $query->whereIn('status', ['draft', 'ready', 'queued', 'failed']);
        } elseif ($tab === 'active') {
            $query->where('status', 'listed');
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', '%' . $q . '%')
                    ->orWhere('asin', 'like', '%' . $q . '%');
            });
        }
        if ($qSku !== '') {
            $query->where('seller_sku', 'like', '%' . $qSku . '%');
        }

        $rows = $query->orderByDesc('updated_at')->limit(2000)->get();
        $imageLookups = $this->imageLookupsForSkus($rows->pluck('seller_sku')->unique()->values()->all());

        $data = $rows->map(function (ListingManagerChannelDraft $d) use ($imageLookups) {
            $row = $this->serializeDraft($d);
            $sku = trim((string) ($row['sku'] ?? ''));
            $rawInfo = $imageLookups['raw'][$sku] ?? ['url' => null, 'count' => 0, 'previewable' => false];
            $rawAi = $imageLookups['raw_ai'][$sku] ?? ['url' => null, 'count' => 0, 'previewable' => false];
            $rawBatch = $imageLookups['raw_batch'][$sku] ?? ['url' => null, 'count' => 0, 'previewable' => false];
            $row['hero_image'] = $imageLookups['hero'][$sku] ?? ($row['thumbnail'] ?? null);
            $row['raw_ai_image'] = $rawAi['url'];
            $row['raw_ai_image_count'] = (int) $rawAi['count'];
            $row['raw_ai_image_previewable'] = (bool) $rawAi['previewable'];
            $row['raw_image'] = $rawInfo['url'];
            $row['raw_image_count'] = (int) $rawInfo['count'];
            $row['raw_image_previewable'] = (bool) $rawInfo['previewable'];
            $row['raw_batch_image'] = $rawBatch['url'];
            $row['raw_batch_image_count'] = (int) $rawBatch['count'];
            $row['raw_batch_image_previewable'] = (bool) $rawBatch['previewable'];

            return $row;
        })->values();

        $baseMeta = ListingManagerChannelDraft::query();
        if ($channelId > 0) {
            $baseMeta->where('channel_id', $channelId);
        }
        $draftCount = (clone $baseMeta)->whereIn('status', ['draft', 'ready', 'queued', 'failed'])->count();
        $activeCount = (clone $baseMeta)->where('status', 'listed')->count();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $data->count(),
                'drafts_tab' => $draftCount,
                'active_tab' => $activeCount,
                'draft' => $data->where('status', 'draft')->count(),
                'ready' => $data->where('status', 'ready')->count(),
                'listed' => $data->where('status', 'listed')->count(),
                'failed' => $data->where('status', 'failed')->count(),
                'missing_info' => $data->where('ui_status', 'Missing Info')->count(),
            ],
        ]);
    }

    public function showDraft(int $id)
    {
        $draft = ListingManagerChannelDraft::query()->with('channel:id,channel,logo')->findOrFail($id);
        $this->backfillDraftFromStore($draft);

        return response()->json([
            'success' => true,
            'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
        ]);
    }

    /**
     * Force reload title/description/images/price/package from Amazon / product_master.
     */
    public function reloadDraftFromStore(int $id)
    {
        $draft = ListingManagerChannelDraft::query()->with('channel:id,channel,logo')->findOrFail($id);
        if ($draft->status === 'listed') {
            return response()->json(['success' => false, 'message' => 'Cannot reload a published listing.'], 422);
        }

        $this->backfillDraftFromStore($draft, true);

        return response()->json([
            'success' => true,
            'message' => 'Reloaded from main store (Shopify description + Amazon catalog).',
            'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
        ]);
    }

    /**
     * Explicitly load description HTML from main store (Shopify) into the draft.
     */
    public function loadDescriptionFromStore(int $id)
    {
        $draft = ListingManagerChannelDraft::query()->with('channel:id,channel,logo')->findOrFail($id);
        if ($draft->status === 'listed') {
            return response()->json(['success' => false, 'message' => 'Cannot change description on a published listing.'], 422);
        }

        $main = ListingManagerAmazonHydrator::fetchMainStoreDescription((string) $draft->seller_sku, true);
        if (trim($main['html']) === '') {
            return response()->json([
                'success' => false,
                'message' => 'No description found on main store (Shopify / product master) for this SKU.',
            ], 422);
        }

        $details = ListingManagerPublishStatus::normalizeDetails(
            is_array($draft->listing_details) ? $draft->listing_details : []
        );
        $details['description'] = $main['html'];
        if ($main['images'] !== []) {
            $details['images'] = $main['images'];
            $details['image_url'] = $main['images'][0];
            $draft->thumbnail_image = $main['images'][0];
        }
        if ($main['title'] !== '' && trim((string) $draft->title) === '') {
            $draft->title = $main['title'];
        }
        $draft->listing_details = $details;
        $snap = is_array($draft->amazon_snapshot) ? $draft->amazon_snapshot : [];
        $snap['product_description'] = $main['html'];
        $snap['description_source'] = $main['source'];
        if ($main['images'] !== []) {
            $snap['images'] = $main['images'];
            $snap['thumbnail_image'] = $main['images'][0];
        }
        $draft->amazon_snapshot = $snap;

        $channelName = (string) ($draft->channel->channel ?? '');
        $ready = ListingManagerPublishStatus::readiness(
            $draft->title,
            $draft->price,
            $draft->quantity,
            $details,
            (string) $draft->status,
            $channelName
        );
        $draft->status = $ready['ready'] ? 'ready' : 'draft';
        $draft->save();

        return response()->json([
            'success' => true,
            'message' => 'Description loaded from main store ('.$main['source'].').',
            'source' => $main['source'],
            'description' => $main['html'],
            'images' => $main['images'],
            'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
        ]);
    }

    public function searchCategories(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'categories' => []]);
        }

        $ebay = new Ebay2ApiService();
        if (! $ebay->isConfigured()) {
            return response()->json([
                'success' => false,
                'categories' => [],
                'message' => 'Ebay 2 API is not configured.',
            ], 422);
        }

        $result = $ebay->getCategorySuggestions($q);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function businessPolicies()
    {
        $defaults = (array) config('listing_manager.ebay2_defaults', []);
        $ebay = new Ebay2ApiService();
        $result = $ebay->isConfigured()
            ? $ebay->getBusinessPolicies()
            : ['success' => false, 'shipping' => [], 'payment' => [], 'return' => []];

        // Ensure screenshot defaults appear even if Account API scope is missing
        $ensure = function (array $list, string $id, string $name): array {
            $id = trim($id);
            if ($id === '') {
                return $list;
            }
            foreach ($list as $row) {
                if ((string) ($row['id'] ?? '') === $id) {
                    return $list;
                }
            }
            array_unshift($list, ['id' => $id, 'name' => $name !== '' ? "{$name} ({$id})" : $id]);

            return $list;
        };

        $shipping = $result['shipping'] ?? [];
        // Prefer matching "As Per Weight" by name when ID unknown
        $shipName = (string) ($defaults['shipping_policy_name'] ?? 'As Per Weight');
        $shipId = (string) ($defaults['shipping_policy_id'] ?? '');
        if ($shipId === '') {
            foreach ($shipping as $row) {
                if (stripos((string) ($row['name'] ?? ''), $shipName) !== false) {
                    $shipId = (string) $row['id'];
                    break;
                }
            }
        }
        $shipping = $ensure($shipping, $shipId, $shipName);
        $payment = $ensure(
            $result['payment'] ?? [],
            (string) ($defaults['payment_policy_id'] ?? ''),
            (string) ($defaults['payment_policy_name'] ?? 'eBay Managed Payments')
        );
        $return = $ensure(
            $result['return'] ?? [],
            (string) ($defaults['return_policy_id'] ?? ''),
            (string) ($defaults['return_policy_name'] ?? '30 days money back')
        );

        return response()->json([
            'success' => true,
            'shipping' => $shipping,
            'payment' => $payment,
            'return' => $return,
            'defaults' => [
                'shipping_policy_id' => $shipId,
                'payment_policy_id' => (string) ($defaults['payment_policy_id'] ?? ''),
                'return_policy_id' => (string) ($defaults['return_policy_id'] ?? ''),
                'location_city' => (string) ($defaults['location_city'] ?? 'Bellefontaine'),
                'location_country' => (string) ($defaults['location_country'] ?? 'US'),
                'location_postal_code' => (string) ($defaults['location_postal_code'] ?? '43311'),
            ],
            'api_ok' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
        ]);
    }

    public function uploadDraftImage(Request $request, int $id)
    {
        $draft = ListingManagerChannelDraft::query()->findOrFail($id);
        $request->validate([
            'image' => 'required|image|max:8192',
        ]);

        $path = $request->file('image')->store('listing-manager/'.$draft->id, 'public');
        $url = Storage::disk('public')->url($path);

        $details = ListingManagerPublishStatus::normalizeDetails(
            is_array($draft->listing_details) ? $draft->listing_details : []
        );
        $images = is_array($details['images'] ?? null) ? $details['images'] : [];
        $images[] = $url;
        $details['images'] = array_values(array_unique($images));
        $details['image_url'] = $details['images'][0] ?? $url;
        $draft->listing_details = $details;
        $draft->thumbnail_image = $details['image_url'];
        $draft->save();

        return response()->json([
            'success' => true,
            'url' => $url,
            'images' => $details['images'],
            'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
        ]);
    }

    /**
     * Optimize draft description to LitCommerce-style HTML (text + images).
     */
    public function optimizeDescription(Request $request, int $id)
    {
        $draft = ListingManagerChannelDraft::query()->with('channel:id,channel,logo')->findOrFail($id);
        $details = ListingManagerPublishStatus::normalizeDetails(
            is_array($draft->listing_details) ? $draft->listing_details : []
        );

        $source = trim((string) $request->input('description', $details['description'] ?? ''));
        if ($source === '') {
            $source = trim((string) ($draft->title ?? ''));
        }

        $images = $request->input('images');
        if (! is_array($images) || $images === []) {
            $images = is_array($details['images'] ?? null) ? $details['images'] : [];
        }
        $images = array_values(array_filter(array_map(fn ($u) => trim((string) $u), $images)));

        // If draft still has no usable images, pull live Amazon media first
        if ($images === []) {
            $images = $this->fetchLiveAmazonImages((string) $draft->seller_sku, (string) ($draft->asin ?? ''));
        }

        $bullets = array_values(array_filter([
            $details['bullet_1'] ?? '',
            $details['bullet_2'] ?? '',
            $details['bullet_3'] ?? '',
            $details['bullet_4'] ?? '',
            $details['bullet_5'] ?? '',
        ], fn ($b) => trim((string) $b) !== ''));

        $html = ListingManagerEbayDescriptionBuilder::optimize(
            $source,
            $images,
            (string) ($draft->title ?? ''),
            $bullets
        );

        if (trim($html) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nothing to optimize — add description text or images first.',
            ], 422);
        }

        $details['description'] = $html;
        if ($images !== []) {
            $details['images'] = $images;
            $details['image_url'] = $images[0];
            $draft->thumbnail_image = $images[0];
        }
        $draft->listing_details = $details;
        $draft->save();

        return response()->json([
            'success' => true,
            'message' => 'Description optimized to HTML with '.count($images).' image(s).',
            'description' => $html,
            'images' => $images,
            'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
        ]);
    }

    /**
     * Load real product images from Amazon Listings/Catalog APIs into the draft.
     */
    public function loadDraftImages(int $id)
    {
        $draft = ListingManagerChannelDraft::query()->with('channel:id,channel,logo')->findOrFail($id);
        $sku = trim((string) $draft->seller_sku);
        $images = $this->fetchLiveAmazonImages($sku, (string) ($draft->asin ?? ''));

        if ($images === []) {
            return response()->json([
                'success' => false,
                'message' => 'No images found for this SKU on Amazon. Upload images manually or check Amazon listing media.',
                'images' => [],
            ], 422);
        }

        $details = ListingManagerPublishStatus::normalizeDetails(
            is_array($draft->listing_details) ? $draft->listing_details : []
        );
        $details['images'] = $images;
        $details['image_url'] = $images[0];
        $draft->listing_details = $details;
        $draft->thumbnail_image = $images[0];

        $snap = is_array($draft->amazon_snapshot) ? $draft->amazon_snapshot : [];
        $snap['images'] = $images;
        $snap['thumbnail_image'] = $images[0];
        $draft->amazon_snapshot = $snap;

        if ($draft->status !== 'listed') {
            $ready = ListingManagerPublishStatus::readiness(
                $draft->title,
                $draft->price,
                $draft->quantity,
                $details,
                (string) $draft->status,
                (string) ($draft->channel->channel ?? '')
            );
            $draft->status = $ready['ready'] ? 'ready' : 'draft';
        }
        $draft->save();

        // Persist thumbnail on amazon_listings_raw for next time
        if (Schema::hasTable('amazon_listings_raw') && Schema::hasColumn('amazon_listings_raw', 'thumbnail_image')) {
            AmazonListingRaw::query()
                ->where('seller_sku', $sku)
                ->update(['thumbnail_image' => $images[0]]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Loaded '.count($images).' image(s) from Amazon.',
            'images' => $images,
            'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
        ]);
    }

    /**
     * @return list<string>
     */
    private function fetchLiveAmazonImages(string $sku, string $asin = ''): array
    {
        $images = [];
        $push = function ($url) use (&$images) {
            $url = trim((string) $url);
            if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                return;
            }
            // Skip known-broken Amazon ASIN placeholder patterns
            if (preg_match('#/images/P/[A-Z0-9]+#i', $url) && ! preg_match('#m\.media-amazon\.com/images/I/#i', $url)) {
                return;
            }
            if (! in_array($url, $images, true)) {
                $images[] = $url;
            }
        };

        // 1) Local store first (real URLs only)
        $hydrated = ListingManagerAmazonHydrator::hydrate($sku);
        foreach ($hydrated['images'] as $u) {
            $push($u);
        }

        // 2) Amazon Listings Items media (best source)
        try {
            $media = (new AmazonSpApiService())->getListingsItemMedia($sku);
            if (! empty($media['success']) && ! empty($media['images']) && is_array($media['images'])) {
                foreach ($media['images'] as $img) {
                    if (is_string($img)) {
                        $push($img);
                    } elseif (is_array($img)) {
                        $push($img['url'] ?? $img['link'] ?? $img['media_location'] ?? null);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ListingManager loadDraftImages listings media failed: '.$e->getMessage());
        }

        // 3) Catalog Items API images by ASIN
        if ($images === []) {
            $asin = $asin !== '' ? $asin : (string) ($hydrated['asin'] ?? '');
            if ($asin !== '') {
                try {
                    $ctx = [];
                    $catalog = (new AmazonSpApiService())->getCatalogItemByAsin($asin, $ctx, 'images,summaries');
                    if (is_array($catalog)) {
                        foreach (($catalog['images'] ?? []) as $group) {
                            foreach (($group['images'] ?? []) as $img) {
                                $push($img['link'] ?? $img['url'] ?? null);
                            }
                        }
                        foreach (($catalog['summaries'] ?? []) as $summary) {
                            $push($summary['mainImage']['link'] ?? $summary['mainImage']['url'] ?? null);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('ListingManager loadDraftImages catalog failed: '.$e->getMessage());
                }
            }
        }

        return array_values($images);
    }

    /**
     * Update LitCommerce-style listing details on a draft.
     */
    public function updateDraft(Request $request, int $id)
    {
        $draft = ListingManagerChannelDraft::query()->with('channel:id,channel')->findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:1000',
            'price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:2000',
            'listing_details' => 'nullable|array',
        ]);

        if (array_key_exists('title', $validated)) {
            $draft->title = $validated['title'];
        }
        if (array_key_exists('price', $validated)) {
            $draft->price = $validated['price'];
        }
        if (array_key_exists('quantity', $validated)) {
            $draft->quantity = $validated['quantity'];
        }
        if (array_key_exists('notes', $validated)) {
            $draft->notes = $validated['notes'];
        }

        $details = is_array($draft->listing_details) ? $draft->listing_details : [];
        if (isset($validated['listing_details']) && is_array($validated['listing_details'])) {
            $details = $this->ensureIdentifierDefaults(
                ListingManagerPublishStatus::normalizeDetails(array_merge($details, $validated['listing_details'])),
                (string) $draft->seller_sku
            );
            if (! empty($details['image_url'])) {
                $draft->thumbnail_image = $details['image_url'];
            } elseif (! empty($details['images'][0])) {
                $draft->thumbnail_image = $details['images'][0];
            }
            $draft->listing_details = $details;
        } else {
            $details = $this->ensureIdentifierDefaults(
                ListingManagerPublishStatus::normalizeDetails($details),
                (string) $draft->seller_sku
            );
            $draft->listing_details = $details;
        }

        $channelName = (string) ($draft->channel->channel ?? '');
        if ($draft->status !== 'listed') {
            $ready = ListingManagerPublishStatus::readiness(
                $draft->title,
                $draft->price,
                $draft->quantity,
                $details,
                $draft->status,
                $channelName
            );
            $draft->status = $ready['ready'] ? 'ready' : 'draft';
        }

        $draft->save();
        $draft->load('channel:id,channel,logo');

        return response()->json([
            'success' => true,
            'message' => 'Draft saved.',
            'draft' => $this->serializeDraft($draft, true),
        ]);
    }

    /**
     * Save & Publish draft to marketplace (Ebay 2 via AddFixedPriceItem).
     */
    public function publishDraft(Request $request, int $id)
    {
        $draft = ListingManagerChannelDraft::query()->with('channel:id,channel')->findOrFail($id);
        $details = $this->ensureIdentifierDefaults(
            ListingManagerPublishStatus::normalizeDetails(
                is_array($draft->listing_details) ? $draft->listing_details : []
            ),
            (string) $draft->seller_sku
        );
        $draft->listing_details = $details;
        $draft->save();

        $channelName = (string) ($draft->channel->channel ?? '');
        $ready = ListingManagerPublishStatus::readiness(
            $draft->title,
            $draft->price,
            $draft->quantity,
            $details,
            $draft->status,
            $channelName
        );

        if (! $ready['ready']) {
            return response()->json([
                'success' => false,
                'message' => 'Complete all required fields before publishing.',
                'draft' => $this->serializeDraft($draft, true),
            ], 422);
        }

        if ($draft->status === 'listed' && trim((string) $draft->external_listing_id) !== '') {
            return response()->json([
                'success' => true,
                'message' => 'Already published (ItemID ' . $draft->external_listing_id . ').',
                'draft' => $this->serializeDraft($draft, true),
            ]);
        }

        $channelKey = ListingChannelCounts::normalize((string) ($draft->channel->channel ?? ''));
        if (! in_array($channelKey, ['ebay2', 'ebaytwo'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Save & Publish is currently enabled for Ebay 2 only. Other channels save as Ready drafts.',
            ], 422);
        }

        $ebay = new Ebay2ApiService();
        if (! $ebay->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Ebay 2 API credentials are not configured.',
            ], 422);
        }

        $result = $ebay->addFixedPriceItem(array_merge($details, [
            'sku' => $draft->seller_sku,
            'title' => $draft->title,
            'price' => $draft->price,
            'quantity' => $draft->quantity,
        ]));

        if (! ($result['success'] ?? false)) {
            $draft->status = 'failed';
            $draft->notes = trim((string) $draft->notes . "\nPublish failed: " . ($result['message'] ?? 'Unknown error'));
            $draft->save();

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Publish failed.',
                'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
            ], 422);
        }

        $itemId = trim((string) ($result['item_id'] ?? ''));
        $draft->status = 'listed';
        $draft->external_listing_id = $itemId !== '' ? $itemId : $draft->external_listing_id;
        $draft->listed_at = now();
        $draft->publish_checked_at = now();
        $draft->listing_details = $details;
        $draft->notes = trim((string) $draft->notes . "\nPublished to Ebay 2 via Listing Manager.");
        $draft->save();

        if ($itemId !== '' && Schema::hasTable('ebay_2_metrics')) {
            Ebay2Metric::query()->updateOrCreate(
                ['sku' => $draft->seller_sku],
                [
                    'item_id' => $itemId,
                    'ebay_title' => $draft->title,
                    'ebay_price' => $draft->price,
                    'ebay_stock' => $draft->quantity,
                    'ebay_link' => 'https://www.ebay.com/itm/' . $itemId,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Published to Ebay 2.',
            'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
        ]);
    }

    /**
     * Re-check marketplace metrics: if item is live, mark draft as listed.
     */
    public function refreshDraftStatuses(Request $request)
    {
        if (! Schema::hasTable('listing_manager_channel_drafts')) {
            return response()->json(['success' => false, 'message' => 'Drafts table missing.'], 500);
        }

        $ids = $request->input('ids', []);
        $query = ListingManagerChannelDraft::query()->with('channel:id,channel');
        if (is_array($ids) && $ids !== []) {
            $query->whereIn('id', array_map('intval', $ids));
        } else {
            $query->whereIn('status', ['draft', 'ready', 'queued', 'failed']);
        }

        $listed = 0;
        $stillMissing = 0;
        $checked = 0;

        foreach ($query->get() as $draft) {
            $checked++;
            $channelName = (string) ($draft->channel->channel ?? '');
            $result = ListingManagerPublishStatus::check($channelName, (string) $draft->seller_sku);
            $draft->publish_checked_at = now();

            if ($result['listed']) {
                $draft->status = 'listed';
                $draft->external_listing_id = $result['listing_id'];
                $draft->listed_at = $draft->listed_at ?: now();
                $draft->notes = trim((string) $draft->notes . "\nLive on {$channelName} via {$result['source']}.");
                $listed++;
            } else {
                if ($draft->status === 'listed') {
                    // keep listed if we had an id before unless explicitly cleared
                } else {
                    $ready = ListingManagerPublishStatus::readiness(
                        $draft->title,
                        $draft->price,
                        $draft->quantity,
                        is_array($draft->listing_details) ? $draft->listing_details : []
                    );
                    $draft->status = $ready['ready'] ? 'ready' : 'draft';
                }
                $stillMissing++;
            }
            $draft->save();
        }

        return response()->json([
            'success' => true,
            'message' => "Checked {$checked} draft(s): {$listed} live / published, {$stillMissing} not live yet.",
            'listed' => $listed,
            'not_listed' => $stillMissing,
            'checked' => $checked,
        ]);
    }

    public function deleteDraft(int $id)
    {
        $draft = ListingManagerChannelDraft::query()->findOrFail($id);
        $draft->delete();

        return response()->json(['success' => true, 'message' => 'Draft removed.']);
    }

    public function productTypes()
    {
        if (! Schema::hasTable('amazon_listings_raw')) {
            return response()->json(['success' => true, 'types' => []]);
        }

        $types = AmazonListingRaw::query()
            ->whereNotNull('product_type')
            ->where('product_type', '!=', '')
            ->distinct()
            ->orderBy('product_type')
            ->limit(200)
            ->pluck('product_type')
            ->values();

        return response()->json(['success' => true, 'types' => $types]);
    }

    /**
     * Fill blank draft fields from Amazon / product_master (or force overwrite).
     */
    private function backfillDraftFromStore(ListingManagerChannelDraft $draft, bool $force = false): void
    {
        if ($draft->status === 'listed' && ! $force) {
            return;
        }

        $channelName = (string) ($draft->channel->channel ?? '');
        $details = is_array($draft->listing_details) ? $draft->listing_details : [];
        $images = is_array($details['images'] ?? null) ? $details['images'] : [];
        $desc = trim((string) ($details['description'] ?? ''));

        // Pull Shopify main-store HTML when description is missing or still plain Amazon text
        $needsMainStoreDesc = $force
            || $desc === ''
            || (! str_contains($desc, '<img') && ! str_contains($desc, 'shopify') && mb_strlen(strip_tags($desc)) < 2500);

        $hydrated = ListingManagerAmazonHydrator::hydrate((string) $draft->seller_sku, $needsMainStoreDesc);

        $needsTitle = $force || trim((string) $draft->title) === '';
        $needsDesc = $needsMainStoreDesc;
        $needsImages = $force || $images === [];
        $needsPrice = $force || $draft->price === null || (float) $draft->price <= 0;
        $needsQty = $force || $draft->quantity === null;
        $needsPkg = $force
            || trim((string) ($details['package_length'] ?? '')) === ''
            || trim((string) ($details['package_weight_lb'] ?? '')) === '';

        if (! ($needsTitle || $needsDesc || $needsImages || $needsPrice || $needsQty || $needsPkg || $force)) {
            $draft->listing_details = ListingManagerPublishStatus::normalizeDetails($details);
            $draft->save();

            return;
        }

        $base = $force ? [] : $details;
        $merged = ListingManagerAmazonHydrator::detailsFromHydration($hydrated, $base, $channelName);
        if (! $force) {
            foreach (['primary_category_id', 'primary_category_path', 'secondary_category_id', 'shipping_policy_id', 'payment_policy_id', 'return_policy_id', 'condition'] as $keep) {
                if (trim((string) ($details[$keep] ?? '')) !== '') {
                    $merged[$keep] = $details[$keep];
                }
            }
        }

        // Always prefer main-store description when we fetched it
        if ($needsDesc && trim((string) ($hydrated['description'] ?? '')) !== '') {
            $merged['description'] = $hydrated['description'];
            if (! empty($hydrated['images'])) {
                $merged['images'] = $hydrated['images'];
                $merged['image_url'] = $hydrated['images'][0];
            }
        }

        if ($needsTitle || $force) {
            $draft->title = $hydrated['title'] ?: $draft->title;
        }
        if ($needsPrice || $force) {
            $draft->price = $hydrated['price'] ?? $draft->price;
        }
        if ($needsQty || $force) {
            $draft->quantity = $hydrated['quantity'] ?? $draft->quantity;
        }
        if ($needsImages || $force || ($needsDesc && ! empty($hydrated['images']))) {
            $draft->thumbnail_image = $hydrated['thumbnail'] ?: $draft->thumbnail_image;
        }
        if ($hydrated['asin']) {
            $draft->asin = $hydrated['asin'];
        }
        $draft->listing_details = $merged;
        $draft->amazon_snapshot = $hydrated['snapshot'];

        if ($draft->status !== 'listed') {
            $ready = ListingManagerPublishStatus::readiness(
                $draft->title,
                $draft->price,
                $draft->quantity,
                $merged,
                (string) $draft->status,
                $channelName
            );
            $draft->status = $ready['ready'] ? 'ready' : 'draft';
        }

        $draft->save();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Brand = 5 Core, MPN = SKU, UPC from CP Master (product_master).
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function ensureIdentifierDefaults(array $details, string $sku): array
    {
        $sku = trim($sku);
        $defaultBrand = trim((string) config('listing_manager.default_brand', '5 Core')) ?: '5 Core';
        $brand = trim((string) ($details['brand'] ?? '')) ?: $defaultBrand;
        $mpn = trim((string) ($details['mpn'] ?? '')) ?: $sku;
        $upc = trim((string) ($details['upc'] ?? ''));
        if ($upc === '' && $sku !== '') {
            $upc = ListingManagerAmazonHydrator::upcFromCpMaster($sku);
        }

        $specifics = is_array($details['item_specifics'] ?? null) ? $details['item_specifics'] : [];
        $specifics['Brand'] = $brand;
        $specifics['MPN'] = $mpn;
        if ($upc !== '') {
            $specifics['UPC'] = $upc;
        }

        $details['brand'] = $brand;
        $details['mpn'] = $mpn;
        $details['upc'] = $upc;
        $details['item_specifics'] = $specifics;

        return ListingManagerPublishStatus::normalizeDetails($details);
    }

    private function serializeDraft(ListingManagerChannelDraft $d, bool $full = false): array
    {
        $channelName = (string) ($d->channel->channel ?? '');
        $details = $this->ensureIdentifierDefaults(
            ListingManagerPublishStatus::normalizeDetails(
                is_array($d->listing_details) ? $d->listing_details : []
            ),
            (string) $d->seller_sku
        );
        $ready = ListingManagerPublishStatus::readiness(
            $d->title,
            $d->price,
            $d->quantity,
            $details,
            (string) $d->status,
            $channelName
        );
        $limits = ListingManagerAmazonHydrator::limitsForChannel($channelName);

        $payload = [
            'id' => $d->id,
            'channel_id' => $d->channel_id,
            'channel' => $channelName,
            'channel_logo' => $d->channel->logo ?? null,
            'listing_page_url' => ListingChannelCounts::listingUrl($channelName),
            'sku' => $d->seller_sku,
            'asin' => $d->asin,
            'title' => $d->title,
            'thumbnail' => $d->thumbnail_image,
            'price' => $d->price !== null ? (float) $d->price : null,
            'quantity' => $d->quantity,
            'status' => $d->status,
            'ui_status' => $ready['ui_status'],
            'external_listing_id' => $d->external_listing_id,
            'is_ready' => $ready['ready'],
            'missing_fields' => $ready['missing'],
            'tab_errors' => $ready['tab_errors'],
            'banners' => $ready['banners'],
            'limits' => $limits,
            'is_live' => $d->status === 'listed' && trim((string) $d->external_listing_id) !== '',
            'listed_at' => $d->listed_at?->toDateTimeString(),
            'publish_checked_at' => $d->publish_checked_at?->toDateTimeString(),
            'notes' => $d->notes,
            'updated_at' => $d->updated_at?->toDateTimeString(),
            'amazon_snapshot' => is_array($d->amazon_snapshot) ? $d->amazon_snapshot : [],
        ];

        if ($full) {
            $payload['listing_details'] = $details;
        }

        return $payload;
    }

    /**
     * Image Master hero + Raw Images lookups for listing-manager columns.
     *
     * @param  list<string>  $skus
     * @return array{hero: array<string, string>, raw: array<string, array{url: ?string, count: int, previewable: bool}>, raw_ai: array<string, array{url: ?string, count: int, previewable: bool}>, raw_batch: array<string, array{url: ?string, count: int, previewable: bool}>, pm_images: array<string, list<string>>}
     */
    private function imageLookupsForSkus(array $skus): array
    {
        $hero = [];
        $raw = [];
        $rawAi = [];
        $rawBatch = [];
        $pmImages = [];
        $skus = array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $skus)));
        if ($skus === []) {
            return ['hero' => $hero, 'raw' => $raw, 'raw_ai' => $rawAi, 'raw_batch' => $rawBatch, 'pm_images' => $pmImages];
        }

        $skuSet = array_fill_keys($skus, true);
        $heroByNorm = [];
        $pmByNorm = [];
        $rawByNorm = [];
        $rawAiByNorm = [];
        $rawBatchByNorm = [];

        if (Schema::hasTable('product_master')) {
            $select = ['sku', 'Values'];
            foreach (['main_image', 'image1', 'image2', 'image3', 'image4', 'image5', 'image6'] as $col) {
                if (Schema::hasColumn('product_master', $col)) {
                    $select[] = $col;
                }
            }
            $pmRows = ProductMaster::query()->whereIn('sku', $skus)->get($select);
            foreach ($pmRows as $pm) {
                $sku = trim((string) $pm->sku);
                if ($sku === '') {
                    continue;
                }
                $urls = $this->productMasterImageUrls($pm);
                if ($urls === []) {
                    continue;
                }
                $pmImages[$sku] = $urls;
                $hero[$sku] = $urls[0];
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm !== '') {
                    $heroByNorm[$norm] = $urls[0];
                    $pmByNorm[$norm] = $urls;
                }
            }
        }

        if (Schema::hasTable('shopify_skus')) {
            $shopifyRows = ShopifySku::query()->whereIn('sku', $skus)->get(['sku', 'image_src']);
            foreach ($shopifyRows as $row) {
                $sku = trim((string) $row->sku);
                $url = $this->normalizePublicImageUrl($row->image_src);
                if ($sku === '' || ! $url) {
                    continue;
                }
                if (! isset($hero[$sku])) {
                    $hero[$sku] = $url;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm !== '' && ! isset($heroByNorm[$norm])) {
                    $heroByNorm[$norm] = $url;
                }
            }
        }

        if (Schema::hasTable('product_raw_images')) {
            $hasKind = Schema::hasColumn('product_raw_images', 'kind');
            foreach (ProductRawImage::query()->whereIn('sku', $skus)->orderBy('id')->get() as $img) {
                $sku = trim((string) $img->sku);
                if ($sku === '') {
                    continue;
                }
                $isBatch = $hasKind && $img->isBatchKind();
                if ($isBatch) {
                    $this->accumulateRawLookup($rawBatch, $rawBatchByNorm, $sku, $img);
                } elseif ($img->isAiGenerated()) {
                    $this->accumulateRawLookup($rawAi, $rawAiByNorm, $sku, $img);
                } else {
                    $this->accumulateRawLookup($raw, $rawByNorm, $sku, $img);
                }
            }
        }

        foreach ($skus as $sku) {
            if (! isset($skuSet[$sku])) {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm === '') {
                continue;
            }
            if (! isset($hero[$sku]) && isset($heroByNorm[$norm])) {
                $hero[$sku] = $heroByNorm[$norm];
            }
            if (! isset($pmImages[$sku]) && isset($pmByNorm[$norm])) {
                $pmImages[$sku] = $pmByNorm[$norm];
            }
            if (! isset($raw[$sku]) && isset($rawByNorm[$norm])) {
                $raw[$sku] = $rawByNorm[$norm];
            }
            if (! isset($rawAi[$sku]) && isset($rawAiByNorm[$norm])) {
                $rawAi[$sku] = $rawAiByNorm[$norm];
            }
            if (! isset($rawBatch[$sku]) && isset($rawBatchByNorm[$norm])) {
                $rawBatch[$sku] = $rawBatchByNorm[$norm];
            }
        }

        return ['hero' => $hero, 'raw' => $raw, 'raw_ai' => $rawAi, 'raw_batch' => $rawBatch, 'pm_images' => $pmImages];
    }

    /**
     * @param  array<string, array{url: ?string, count: int, previewable: bool}>  $map
     * @param  array<string, array{url: ?string, count: int, previewable: bool}>  $byNorm
     */
    private function accumulateRawLookup(array &$map, array &$byNorm, string $sku, ProductRawImage $img): void
    {
        if (! isset($map[$sku])) {
            $ui = $img->toUiArray();
            $map[$sku] = [
                'url' => $ui['url'] ?? null,
                'count' => 0,
                'previewable' => (bool) ($ui['previewable'] ?? false),
            ];
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && ! isset($byNorm[$norm])) {
                $byNorm[$norm] = $map[$sku];
            }
        }
        $map[$sku]['count']++;
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '' && isset($byNorm[$norm])) {
            $byNorm[$norm]['count'] = $map[$sku]['count'];
        }
    }

    /**
     * Same order as Image Master preview: main_image, image1–image6, Values.image_path.
     *
     * @return list<string>
     */
    private function productMasterImageUrls(ProductMaster $pm): array
    {
        $urls = [];
        foreach (['main_image', 'image1', 'image2', 'image3', 'image4', 'image5', 'image6'] as $field) {
            $url = $this->normalizePublicImageUrl($pm->{$field} ?? null);
            if ($url && ! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        $values = $pm->Values;
        if (is_string($values)) {
            $values = json_decode($values, true);
        }
        if (is_array($values)) {
            $url = $this->normalizePublicImageUrl($values['image_path'] ?? null);
            if ($url && ! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function normalizePublicImageUrl(mixed $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '-') {
            return null;
        }
        if (str_starts_with($v, '//')) {
            return 'https:'.$v;
        }
        if (str_starts_with($v, 'http://') || str_starts_with($v, 'https://')) {
            return $v;
        }

        return '/'.ltrim($v, '/');
    }
}
