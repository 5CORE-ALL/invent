<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonListingRaw;
use App\Models\ChannelMaster;
use App\Models\ListingManagerChannelDraft;
use App\Models\ListingManagerEnabledChannel;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use App\Services\Ebay2ApiService;
use App\Services\ReverbApiService;
use App\Services\MarketplaceManager\ListingManagerPublishDispatcher;
use App\Services\ShopifyApiService;
use App\Services\Support\MarketplaceApiConfigService;
use App\Services\TikTok2ShopService;
use App\Services\TikTokShopService;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\ListingManagerImageStore;
use App\Support\Marketplace\ListingManagerEbayDescriptionBuilder;
use App\Support\Marketplace\ListingManagerEditorProfile;
use App\Support\Marketplace\ListingManagerFamily;
use App\Support\Marketplace\ListingManagerMasterLoader;
use App\Support\Marketplace\ListingManagerProductPublisher;
use App\Support\Marketplace\ListingManagerPublishStatus;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ListingManagerController extends Controller
{
    private const SYNC_FAMILY_CACHE = 'lm.sync_family.user.';

    public function index()
    {
        return view('market-places.listing-manager.index');
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

            $rows = $query->orderBy('item_name')->orderBy('seller_sku')->get([
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
            $shopifyQty = [];
            $skus = $rows->pluck('seller_sku')->unique()->values()->all();
            if ($skus !== []) {
                $shopifyQty = ListingManagerAmazonHydrator::shopifyQuantities($skus);
                if (Schema::hasTable('listing_manager_channel_drafts')) {
                    $draftCounts = ListingManagerChannelDraft::query()
                        ->whereIn('seller_sku', $skus)
                        ->selectRaw('seller_sku, COUNT(*) as draft_count')
                        ->groupBy('seller_sku')
                        ->pluck('draft_count', 'seller_sku')
                        ->all();
                }
            }
            $imageLookups = $this->imageLookupsForSkus($skus);

            $data = $rows->map(function (AmazonListingRaw $row) use ($draftCounts, $imageLookups, $shopifyQty) {
                $raw = is_array($row->raw_data) ? $row->raw_data : [];
                $rawName = '';
                foreach ($raw as $k => $v) {
                    $norm = ltrim((string) $k, "\xEF\xBB\xBF");
                    if (strcasecmp($norm, 'item-name') === 0 || strcasecmp($norm, 'item_name') === 0) {
                        $rawName = trim((string) $v);
                        break;
                    }
                }
                $sku = (string) $row->seller_sku;
                $amazonQty = (int) ($row->quantity ?? ($raw['quantity'] ?? 0));
                $qty = array_key_exists($sku, $shopifyQty) ? (int) $shopifyQty[$sku] : $amazonQty;
                $price = $row->your_price !== null ? (float) $row->your_price : (isset($raw['price']) ? (float) $raw['price'] : null);
                $listPrice = $row->list_price !== null ? (float) $row->list_price : null;
                $thumb = trim((string) ($row->thumbnail_image ?: ($raw['image-url'] ?? $raw['image-url-1'] ?? '')));
                $cachedImages = ListingManagerImageStore::cachedForSku($sku);
                if ($cachedImages !== []) {
                    $thumb = $cachedImages[0];
                } elseif ($thumb !== '') {
                    $thumb = ListingManagerImageStore::localUrlIfCached($thumb) ?? $thumb;
                }
                $hero = $imageLookups['hero'][$sku] ?? ($thumb !== '' ? $thumb : null);

                return [
                    'id' => $row->id,
                    'thumbnail' => $thumb !== '' ? $thumb : null,
                    'hero_image' => $hero,
                    'name' => trim((string) ($row->item_name ?: $rawName ?: $row->seller_sku)),
                    'sku' => $sku,
                    'asin' => $row->asin1 ?: ($raw['asin1'] ?? null),
                    'origin' => 'Amazon',
                    'manage_stock' => 'Yes',
                    'in_stock' => $qty > 0 ? 'Yes' : 'No',
                    'total_available' => $qty,
                    'qty_source' => array_key_exists($sku, $shopifyQty) ? 'shopify' : 'amazon',
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
                    'total' => $data->count(),
                    'in_stock' => $data->where('total_available', '>', 0)->count(),
                    'out_of_stock' => $data->where('total_available', '<=', 0)->count(),
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

        $amazonImages = [];
        $shopifyImages = [];
        $shopify = ['success' => false];
        $cachedImages = ListingManagerImageStore::cachedForSku($sku);
        if ($cachedImages !== []) {
            $images = $cachedImages;
        } else {
            $amazonImages = $this->fetchLiveAmazonImages($sku, (string) ($hydrated['asin'] ?? ''));
            if ($amazonImages === [] && ! empty($hydrated['images'])) {
                $amazonImages = $hydrated['images'];
            }

            $pmImages = array_values(array_filter($hydrated['images'] ?? []));
            $images = $pmImages !== [] ? $pmImages : ($amazonImages !== [] ? $amazonImages : []);
            $images = ListingManagerImageStore::localizeMany($images, $sku);
        }

        try {
            $shopify = app(ShopifyApiService::class)->fetchProductDescriptionHtml($sku);
        } catch (\Throwable $e) {
            Log::warning('ListingManager showProduct Shopify: '.$e->getMessage());
        }
        $shopifyImages = array_values(array_filter(($shopify['images'] ?? []) ?: []));
        if ($cachedImages === [] && $images === [] && $shopifyImages !== []) {
            $images = ListingManagerImageStore::localizeMany($shopifyImages, $sku);
        }

        $imageLookups = $this->imageLookupsForSkus([$sku]);
        $heroImage = $imageLookups['hero'][$sku] ?? null;
        $pmImages = $imageLookups['pm_images'][$sku] ?? [];
        if ($images === []) {
            $images = $amazonImages !== [] ? $amazonImages : ($pmImages !== [] ? $pmImages : $shopifyImages);
            if ($images !== []) {
                $images = ListingManagerImageStore::localizeMany($images, $sku);
            }
        }
        if ($heroImage && ($images === [] || ($images[0] ?? '') !== $heroImage)) {
            $images = array_values(array_unique(array_merge([$heroImage], $images)));
        }
        if ($heroImage === null && $images !== []) {
            $heroImage = $images[0];
        }
        $description = self::firstFilled([
            $hydrated['description'] ?? null,
            $pm['description_1500'] ?? null,
            $pm['description_html'] ?? null,
            $pm['product_description'] ?? null,
            ($shopify['success'] ?? false) ? ($shopify['html'] ?? null) : null,
        ]);

        $title = self::firstFilled([
            $pm['title80'] ?? null,
            $pm['title100'] ?? null,
            $pm['title150'] ?? null,
            $hydrated['title'] ?? null,
            $listing?->item_name,
            ($shopify['success'] ?? false) ? ($shopify['title'] ?? null) : null,
            $sku,
        ]);

        $price = $hydrated['price'];
        $qty = ListingManagerAmazonHydrator::shopifyQuantity($sku, false) ?? $hydrated['quantity'];
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
            $channelName = (string) ($d['channel'] ?? '');
            if ($channelName !== '' && ! $this->channelHasConnectedListingApi($channelName)) {
                continue;
            }
            $live = ListingManagerPublishStatus::check($channelName, $sku);
            if (($d['status'] ?? '') === 'listed' || ($live['listed'] ?? false)) {
                $listedOn[] = [
                    'channel' => $d['channel'] ?? '',
                    'channel_id' => $d['channel_id'] ?? null,
                    'logo' => $d['channel_logo'] ?? null,
                    'product_name' => $d['title'] ?? $title,
                    'qty' => $d['quantity'] ?? null,
                    'price' => $d['price'] ?? null,
                    'status' => 'ACTIVE',
                    'external_url' => ! empty($d['external_listing_id']) && stripos((string) $d['channel'], 'ebay') !== false
                        ? 'https://www.ebay.com/itm/'.$d['external_listing_id']
                        : ($d['listing_page_url'] ?? null),
                    'listing_id' => $d['external_listing_id'] ?? ($live['listing_id'] ?? null),
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
                if ($already) {
                    continue;
                }
                $live = ListingManagerPublishStatus::check($name, $sku);
                if ($live['listed'] ?? false) {
                    $listedOn[] = [
                        'channel' => $name,
                        'channel_id' => $ch['id'] ?? null,
                        'logo' => $ch['logo'] ?? null,
                        'product_name' => $title,
                        'qty' => $qty,
                        'price' => $price,
                        'status' => 'ACTIVE',
                        'external_url' => null,
                        'listing_id' => $live['listing_id'] ?? null,
                    ];
                    continue;
                }
                $enabledChannels[] = [
                    'id' => $ch['id'],
                    'channel' => $name,
                    'logo' => $ch['logo'] ?? null,
                ];
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

        return response()->json([
            'success' => true,
            'product' => [
                'sku' => $sku,
                'asin' => $hydrated['asin'] ?? $listing?->asin1,
                'title' => $title,
                'status' => strtoupper((string) ($raw['status'] ?? 'ACTIVE')),
                'origin' => ($pm !== [] && (trim((string) ($pm['title80'] ?? $pm['title150'] ?? '')) !== '' || trim((string) ($pm['description_1500'] ?? '')) !== ''))
                    ? 'Product Master'
                    : (($shopify['success'] ?? false) ? 'Main Store' : 'Amazon'),
                'upc' => (string) ($hydrated['upc'] ?? ListingManagerAmazonHydrator::upcFromCpMaster($sku, $pm, $listing, $raw)),
                'mpn' => (string) ($pm['mpn'] ?? $pm['part_number'] ?? $listing?->part_number ?? ''),
                'vendor' => (string) ($hydrated['brand'] ?: config('listing_manager.default_brand', '5 Core Inc.')),
            'manufacturer' => (string) ($hydrated['manufacturer'] ?: config('listing_manager.default_manufacturer', '5 Core Inc.')),
            'parent' => ListingManagerFamily::parentKey($sku),
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
                'variations' => $this->variationRowsForSku($sku),
                'metafields' => $metafields,
                'listed_on' => $listedOn,
                'not_listed_on' => $enabledChannels,
                'drafts' => $drafts,
                'changelog' => $changelog,
                'master_content' => ListingManagerMasterLoader::contentPack($sku),
                'sync_prefs' => $this->loadSyncFamilyPrefs(),
                'shopify_ok' => (bool) ($shopify['success'] ?? false),
                'amazon_media_ok' => $images !== [],
                'updated_at' => $listing?->updated_at?->toDateTimeString(),
                'imported_at' => $listing?->report_imported_at
                    ? Carbon::parse($listing->report_imported_at)->toDateTimeString()
                    : null,
            ],
        ]);
    }

    /**
     * Save Product Info edits to Product Master (no marketplace API calls).
     */
    public function saveProduct(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'title' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'upc' => 'nullable|string|max:64',
            'vendor' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'product_type' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:1000',
            'short_description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'condition' => 'nullable|string|max:64',
        ]);

        $publisher = new ListingManagerProductPublisher();
        $saved = $publisher->saveLocal((string) $validated['sku'], $validated);
        if (! $saved['saved']) {
            return response()->json(['success' => false, 'message' => $saved['message']], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $saved['message'],
        ]);
    }

    /**
     * Save Product Info edits and push title / description / price to selected marketplaces.
     */
    public function pushProductToMarketplaces(Request $request)
    {
        $ids = $request->input('channel_ids', $request->input('channel_id'));
        if (! is_array($ids)) {
            $ids = $ids !== null && $ids !== '' ? [$ids] : [];
        }
        $request->merge([
            'channel_ids' => array_values(array_unique(array_filter(array_map('intval', $ids)))),
        ]);

        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'channel_ids' => 'required|array|min:1',
            'channel_ids.*' => 'integer|exists:channel_master,id',
            'parts' => 'nullable|array',
            'parts.*' => 'string|in:title,description,price',
            'skip_save' => 'nullable|boolean',
            'title' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'upc' => 'nullable|string|max:64',
            'vendor' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'product_type' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:1000',
            'short_description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'condition' => 'nullable|string|max:64',
        ]);

        @set_time_limit(120);
        @ini_set('max_execution_time', '120');

        try {
            $sku = (string) $validated['sku'];
            $parts = array_values($validated['parts'] ?? ['title', 'description', 'price']);
            $publisher = new ListingManagerProductPublisher();
            $saved = ['saved' => true, 'message' => 'Product Master already saved.'];
            if (! $request->boolean('skip_save')) {
                $saved = $publisher->saveLocal($sku, $validated);
            }

            $channels = ChannelMaster::query()
                ->whereIn('id', $validated['channel_ids'])
                ->get(['id', 'channel']);

            $rows = $publisher->pushSelectedChannels($sku, $channels, $validated, $parts);
            $ok = 0;
            $fail = 0;
            $draftCount = 0;
            foreach ($rows as $row) {
                if (! empty($row['success'])) {
                    $ok++;
                } else {
                    $fail++;
                }
                if (($row['mode'] ?? '') === 'draft') {
                    $draftCount++;
                }
            }

            return response()->json([
                'success' => $fail === 0,
                'message' => $saved['message']
                    .' Live updates: '.$ok.'.'
                    .($fail > 0 ? ' '.$fail.' failed.' : '')
                    .($draftCount > 0 ? ' '.$draftCount.' new-to-marketplace channel(s) saved as draft only.' : ''),
                'saved' => $saved['saved'],
                'drafts_updated' => $draftCount,
                'total_success' => $ok,
                'total_failed' => $fail,
                'results' => $rows,
            ]);
        } catch (\Throwable $e) {
            Log::error('ListingManager pushProductToMarketplaces failed', [
                'sku' => $request->input('sku'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Could not update marketplaces.',
                'results' => [],
                'total_success' => 0,
                'total_failed' => 1,
            ], 200);
        }
    }

    /**
     * Fetch a Product Master slice for the All Products modal (does not persist).
     */
    public function loadProductFromMaster(Request $request)
    {
        $sku = trim((string) $request->input('sku', ''));
        $source = trim((string) $request->input('source', ''));
        $payload = ListingManagerMasterLoader::load($sku, $source);
        $siblings = $request->boolean('sync_siblings');
        $parent = $request->boolean('sync_parent');
        if (($payload['success'] ?? false) && ($siblings || $parent)) {
            $copied = ListingManagerMasterLoader::copyToFamily($sku, $source, $siblings, $parent);
            $payload['copied'] = $copied['copied'];
            $payload['copied_skus'] = $copied['skus'];
            if ($copied['copied'] > 0) {
                $payload['message'] = trim((string) ($payload['message'] ?? 'Synced.')).
                    ' Copied to '.$copied['copied'].' related SKU'.($copied['copied'] === 1 ? '' : 's').'.';
            }
        }

        return response()->json($payload, ($payload['success'] ?? false) ? 200 : 422);
    }

    public function saveMasterField(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'field' => 'required|string|in:title150,title100,title80,title60,bullet1,bullet2,bullet3,bullet4,bullet5,description_html',
            'value' => 'nullable|string',
            'sync_siblings' => 'sometimes|boolean',
            'sync_parent' => 'sometimes|boolean',
        ]);

        $sku = trim($validated['sku']);
        $field = $validated['field'];
        $value = trim((string) ($validated['value'] ?? ''));
        if ($field === 'title150') {
            $value = mb_substr($value, 0, 170);
        } elseif ($field === 'title100') {
            $value = mb_substr($value, 0, 105);
        } elseif ($field === 'title80') {
            $value = mb_substr($value, 0, 80);
        } elseif ($field === 'title60') {
            $value = mb_substr($value, 0, 60);
        }

        if (! Schema::hasTable('product_master')) {
            return response()->json(['success' => false, 'message' => 'product_master is missing.'], 422);
        }

        $column = $field;
        if ($field === 'description_html' && ! Schema::hasColumn('product_master', 'description_html')) {
            $column = Schema::hasColumn('product_master', 'description_1500') ? 'description_1500' : '';
        }
        if ($column === '' || ! Schema::hasColumn('product_master', $column)) {
            return response()->json(['success' => false, 'message' => "Column {$field} is not available."], 422);
        }

        $payload = [
            $column => $value === '' ? null : $value,
            'updated_at' => now(),
        ];
        if ($field === 'description_html' && $column === 'description_html' && Schema::hasColumn('product_master', 'description_1500')) {
            $plain = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $payload['description_1500'] = $plain === '' ? null : mb_substr($plain, 0, 4000);
        }

        $updated = DB::table('product_master')->where('sku', $sku)->update($payload);
        if ($updated === 0) {
            $updated = DB::table('product_master')->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower($sku)])->update($payload);
        }
        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'SKU not found in Product Master.'], 404);
        }

        $source = str_starts_with($field, 'title') ? 'title' : (str_starts_with($field, 'bullet') ? 'bullets' : 'description');
        $copied = ListingManagerMasterLoader::copyToFamily(
            $sku,
            $source,
            $request->boolean('sync_siblings'),
            $request->boolean('sync_parent')
        );
        $pack = ListingManagerMasterLoader::contentPack($sku);
        $message = 'Saved '.$field.'.';
        if ($copied['copied'] > 0) {
            $message .= ' Copied to '.$copied['copied'].' related SKU'.($copied['copied'] === 1 ? '' : 's').'.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'field' => $field,
            'value' => $value,
            'copied' => $copied['copied'],
            'master_content' => $pack,
        ]);
    }

    public function syncFamilyPrefs(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'prefs' => $this->loadSyncFamilyPrefs(),
        ]);
    }

    public function saveSyncFamilyPrefs(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'siblings' => 'required|boolean',
            'parent' => 'required|boolean',
        ]);
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $prefs = $this->rememberSyncFamilyPrefs($request->boolean('siblings'), $request->boolean('parent'));

        return response()->json(['success' => true, 'prefs' => $prefs]);
    }

    /**
     * @return array{siblings: bool, parent: bool}
     */
    private function rememberSyncFamilyPrefs(bool $siblings, bool $parent): array
    {
        $prefs = ['siblings' => $siblings, 'parent' => $parent];
        $userId = (int) (Auth::id() ?? 0);
        if ($userId > 0) {
            Cache::forever(self::SYNC_FAMILY_CACHE.$userId, $prefs);
        }

        return $prefs;
    }

    /**
     * @return array{siblings: bool, parent: bool}
     */
    private function loadSyncFamilyPrefs(): array
    {
        $defaults = ['siblings' => false, 'parent' => false];
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            return $defaults;
        }
        $saved = Cache::get(self::SYNC_FAMILY_CACHE.$userId);
        if (! is_array($saved)) {
            return $defaults;
        }

        return [
            'siblings' => (bool) ($saved['siblings'] ?? false),
            'parent' => (bool) ($saved['parent'] ?? false),
        ];
    }

    /**
     * Fetch a Product Master slice into a channel draft and persist.
     */
    public function loadDraftFromMaster(Request $request, int $id)
    {
        $draft = ListingManagerChannelDraft::query()->with('channel:id,channel,logo')->findOrFail($id);
        if ($draft->status === 'listed') {
            return response()->json(['success' => false, 'message' => 'Cannot change a published listing from masters.'], 422);
        }

        $source = trim((string) $request->input('source', ''));
        $channelName = (string) ($draft->channel->channel ?? '');
        $payload = ListingManagerMasterLoader::load((string) $draft->seller_sku, $source, $channelName);
        if (! ($payload['success'] ?? false)) {
            return response()->json($payload, 422);
        }

        $details = ListingManagerPublishStatus::normalizeDetails(
            is_array($draft->listing_details) ? $draft->listing_details : []
        );

        if (! empty($payload['title'])) {
            $draft->title = (string) $payload['title'];
        }
        if (array_key_exists('price', $payload) && $payload['price'] !== null) {
            $draft->price = (float) $payload['price'];
        }
        if (array_key_exists('quantity', $payload) && $payload['quantity'] !== null) {
            $draft->quantity = (int) $payload['quantity'];
        }
        if (! empty($payload['description'])) {
            $details['description'] = (string) $payload['description'];
        }
        if (! empty($payload['images']) && is_array($payload['images'])) {
            $family = ListingManagerEditorProfile::family(ListingChannelCounts::normalize($channelName));
            $sourceImages = array_values(array_filter(array_map('strval', $payload['images'])));
            if ($family === 'tiktok') {
                $sourceImages = array_slice($sourceImages, 0, 9);
            }
            $images = ListingManagerImageStore::localizeMany($sourceImages, (string) $draft->seller_sku);
            if ($images === []) {
                $images = $sourceImages;
            }
            if ($images !== []) {
                $details['images'] = $images;
                $details['image_url'] = $images[0];
                $details['image_source_urls'] = ListingManagerImageStore::sourceUrlsForSku((string) $draft->seller_sku);
                $draft->thumbnail_image = $images[0];
                $snap = is_array($draft->amazon_snapshot) ? $draft->amazon_snapshot : [];
                $snap['images'] = $images;
                $snap['thumbnail_image'] = $images[0];
                $draft->amazon_snapshot = $snap;
                $payload['images'] = $images;
            }
        }
        if (! empty($payload['videos']) && is_array($payload['videos'])) {
            $details['videos'] = $payload['videos'];
        }
        if (! empty($payload['bullets']) && is_array($payload['bullets'])) {
            foreach (array_values($payload['bullets']) as $i => $bullet) {
                if ($i > 4) {
                    break;
                }
                $details['bullet_'.($i + 1)] = $bullet;
            }
        }
        foreach (['upc', 'brand', 'manufacturer', 'make', 'model', 'finish', 'year', 'condition_name', 'shipping_profile_id', 'package_length', 'package_width', 'package_height', 'package_weight_lb', 'package_weight_oz'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                $details[$key] = $payload[$key];
            }
        }

        $draft->listing_details = $details;
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

        $payload['draft'] = $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true);

        return response()->json($payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function variationRowsForSku(string $sku): array
    {
        $family = ListingManagerFamily::forSku($sku);
        $rows = [];
        foreach ($family['children'] as $child) {
            $childSku = (string) $child['sku'];
            try {
                $hydrated = ListingManagerAmazonHydrator::hydrate($childSku, false);
                $listing = AmazonListingRaw::query()->where('seller_sku', $childSku)->first();
            } catch (\Throwable $e) {
                $hydrated = ['title' => $childSku, 'price' => null, 'quantity' => null, 'asin' => null];
                $listing = null;
            }
            $rows[] = [
                'sku' => $childSku,
                'title' => $hydrated['title'] ?: $childSku,
                'price' => $hydrated['price'] ?? null,
                'quantity' => ListingManagerAmazonHydrator::shopifyQuantity($childSku, false) ?? ($hydrated['quantity'] ?? null),
                'asin' => $hydrated['asin'] ?? $listing?->asin1,
                'is_current' => (bool) $child['is_current'],
                'variation_label' => $child['variation_label'],
                'parent' => $family['parent'],
            ];
        }

        return $rows;
    }

    /**
     * @param  list<mixed>  $skus
     * @return list<string>
     */
    private function uniqueNormalizedSkus(array $skus): array
    {
        $out = [];
        foreach ($skus as $sku) {
            $sku = preg_replace('/\s+/u', ' ', trim((string) $sku)) ?? trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $key = strtoupper($sku);
            if (isset($out[$key])) {
                continue;
            }
            $out[$key] = $sku;
        }

        return array_values($out);
    }

    private function channelHasConnectedListingApi(string $channelName): bool
    {
        $channelName = trim($channelName);
        if ($channelName === '') {
            return false;
        }

        return app(MarketplaceApiConfigService::class)->isConfigured($channelName);
    }

    private function findChannelDraft(int $channelId, string $sku): ?ListingManagerChannelDraft
    {
        $sku = preg_replace('/\s+/u', ' ', trim($sku)) ?? trim($sku);
        if ($sku === '') {
            return null;
        }

        $normalized = strtoupper($sku);

        return ListingManagerChannelDraft::query()
            ->where('channel_id', $channelId)
            ->where(function ($q) use ($sku, $normalized) {
                $q->where('seller_sku', $sku)
                    ->orWhereRaw('UPPER(TRIM(seller_sku)) = ?', [$normalized])
                    ->orWhereRaw("UPPER(TRIM(REGEXP_REPLACE(seller_sku, '[[:space:]]+', ' '))) = ?", [$normalized]);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertChannelDraft(int $channelId, string $sku, array $payload): string
    {
        $sku = preg_replace('/\s+/u', ' ', trim($sku)) ?? trim($sku);
        $existing = $this->findChannelDraft($channelId, $sku);

        try {
            if ($existing) {
                if ($existing->status !== 'listed') {
                    $existing->fill($payload)->save();
                }

                return 'updated';
            }

            ListingManagerChannelDraft::create(array_merge($payload, [
                'channel_id' => $channelId,
                'seller_sku' => $sku,
            ]));

            return 'created';
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062 && ! str_contains(strtolower($e->getMessage()), 'duplicate')) {
                throw $e;
            }

            $existing = $this->findChannelDraft($channelId, $sku);
            if ($existing && $existing->status !== 'listed') {
                $existing->fill($payload)->save();
            }

            return 'updated';
        }
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

            return response()->json([
                'success' => true,
                'message' => "Imported {$count} Amazon listings successfully.",
                'count' => $count,
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
            ->get(['id', 'channel', 'logo'])
            ->filter(function ($c) {
                $key = ListingChannelCounts::normalize((string) $c->channel);
                if (in_array($key, ['amazon', 'amazonfba'], true)) {
                    return false;
                }

                return $this->channelHasConnectedListingApi((string) $c->channel);
            })
            ->values();

        $enabledIds = Schema::hasTable('listing_manager_enabled_channels')
            ? ListingManagerEnabledChannel::query()
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->pluck('channel_id')
                ->all()
            : [];

        $availableIds = $allActive->pluck('id')->map(fn ($id) => (int) $id)->all();
        $enabledIds = array_values(array_intersect(array_map('intval', $enabledIds), $availableIds));

        // Default: every connected listing API (Amazon is the origin catalog, not a push target).
        if ($enabledIds === []) {
            $enabledIds = $availableIds;
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

        try {
        $skus = $this->uniqueNormalizedSkus($request->input('skus', []));
        if ($request->boolean('include_siblings')) {
            $expanded = [];
            foreach ($skus as $sku) {
                foreach (ListingManagerFamily::forSku($sku)['skus'] as $child) {
                    $expanded[] = $child;
                }
            }
            $skus = $this->uniqueNormalizedSkus($expanded);
        }
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
                try {
                $hydrated = ListingManagerAmazonHydrator::hydrate($sku);
                if (! empty($hydrated['images'])) {
                    $hydrated['images'] = ListingManagerImageStore::localizeMany($hydrated['images'], $sku);
                    $hydrated['thumbnail'] = $hydrated['images'][0] ?? $hydrated['thumbnail'];
                }
                if ($hydrated['title'] === '' && $hydrated['images'] === [] && $hydrated['price'] === null) {
                        if (! AmazonListingRaw::query()->where('seller_sku', $sku)->exists()) {
                            $skipped++;
                            continue;
                        }
                    }

                    $details = $this->ensureIdentifierDefaults(
                        ListingManagerAmazonHydrator::detailsFromHydration($hydrated, [], $channelName),
                        $sku
                    );
                    $details['image_source_urls'] = ListingManagerImageStore::sourceUrlsForSku($sku);
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

                    $result = $this->upsertChannelDraft($channelId, $sku, $payload);
                    if ($result === 'created') {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Throwable $skuError) {
                    Log::warning('ListingManager draft skip: '.$skuError->getMessage(), [
                        'sku' => $sku,
                        'channel_id' => $channelId,
                    ]);
                    $skipped++;
                }
            }
        }

        $channelNames = $channelMap->implode(', ');

        return response()->json([
            'success' => true,
            'message' => "Added {$created} draft(s)" . ($updated ? ", updated {$updated}" : '') .
                " for: {$channelNames}." .
                ' Open Channel Listings → complete required fields → Save & Publish.',
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
        } catch (\Throwable $e) {
            Log::error('ListingManager addToChannelDrafts failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Could not add drafts: '.$e->getMessage(),
            ], 422);
        }
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
            $row['hero_image'] = $imageLookups['hero'][$sku] ?? ($row['thumbnail'] ?? null);

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
        try {
            $this->backfillDraftFromStore($draft);
        } catch (\Throwable $e) {
            Log::warning('ListingManager showDraft backfill: '.$e->getMessage(), ['id' => $id]);
        }
        try {
            $this->persistDraftImages($draft->fresh());
        } catch (\Throwable $e) {
            Log::warning('ListingManager showDraft images: '.$e->getMessage(), ['id' => $id]);
        }
        try {
            $this->ensureDraftMediaAndPackage($draft->fresh(['channel:id,channel,logo']));
        } catch (\Throwable $e) {
            Log::warning('ListingManager showDraft media/package: '.$e->getMessage(), ['id' => $id]);
        }

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

        ListingManagerImageStore::forgetSku((string) $draft->seller_sku);
        $this->backfillDraftFromStore($draft, true);
        $this->persistDraftImages($draft->fresh(), true);

        return response()->json([
            'success' => true,
            'message' => 'Reloaded from main store (Shopify description + Amazon catalog).',
            'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
        ]);
    }

    /**
     * Load description HTML from Amazon, Description Master, or Shopify.
     */
    public function loadDescriptionFromStore(Request $request, int $id)
    {
        $draft = ListingManagerChannelDraft::query()->with('channel:id,channel,logo')->findOrFail($id);
        if ($draft->status === 'listed') {
            return response()->json(['success' => false, 'message' => 'Cannot change description on a published listing.'], 422);
        }

        $source = strtolower(trim((string) $request->input('source', 'shopify')));
        $sku = (string) $draft->seller_sku;
        $html = '';
        $label = 'Shopify';

        if ($source === 'amazon') {
            $html = ListingManagerAmazonHydrator::amazonDescription($sku);
            $label = 'Amazon';
        } elseif (in_array($source, ['description_master', 'master', 'description-master'], true)) {
            $html = ListingManagerAmazonHydrator::descriptionMaster($sku);
            $label = 'Description Master';
        } else {
            $html = ListingManagerAmazonHydrator::shopifyDescription($sku);
            $label = 'Shopify';
        }

        if (trim($html) === '') {
            return response()->json([
                'success' => false,
                'message' => 'No description found on '.$label.' for this SKU.',
            ], 422);
        }

        $details = ListingManagerPublishStatus::normalizeDetails(
            is_array($draft->listing_details) ? $draft->listing_details : []
        );
        $details['description'] = $html;
        $draft->listing_details = $details;
        $snap = is_array($draft->amazon_snapshot) ? $draft->amazon_snapshot : [];
        $snap['product_description'] = $html;
        $snap['description_source'] = $source;
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
            'message' => 'Description loaded from '.$label.'.',
            'source' => $source,
            'description' => $html,
            'images' => [],
            'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
        ]);
    }

    public function searchCategories(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $channel = trim((string) $request->input('channel', ''));
        $title = trim((string) $request->input('title', ''));
        $description = trim((string) $request->input('description', ''));
        $family = ListingManagerEditorProfile::family(ListingChannelCounts::normalize($channel));

        if ($family === 'tiktok') {
            $key = ListingChannelCounts::normalize($channel);
            $svc = in_array($key, ['tiktok2', 'tiktokshop2', 'tiktoktwo'], true)
                ? app(TikTok2ShopService::class)
                : app(TikTokShopService::class);
            $result = $svc->searchListingCategories($q, $title, $description);

            return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
        }

        if ($family === 'reverb') {
            $result = app(ReverbApiService::class)->searchListingCategories($q, $title);

            return response()->json($result);
        }

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
        $url = '/storage/'.$path;
        ListingManagerImageStore::rememberSku((string) $draft->seller_sku, [$url]);

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

        // If draft still has no usable images, pull Image Master only
        if ($images === []) {
            $images = ListingManagerAmazonHydrator::imageMasterUrls((string) $draft->seller_sku);
        }
        $images = ListingManagerImageStore::localizeMany($images, (string) $draft->seller_sku);

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
     * Load product images from Image Master only.
     */
    public function loadDraftImages(int $id)
    {
        $draft = ListingManagerChannelDraft::query()->with('channel:id,channel,logo')->findOrFail($id);
        $sku = trim((string) $draft->seller_sku);
        $family = ListingManagerEditorProfile::family(ListingChannelCounts::normalize((string) ($draft->channel->channel ?? '')));
        $source = ListingManagerAmazonHydrator::imageMasterUrls($sku);
        if ($family === 'tiktok') {
            $source = array_slice($source, 0, 9);
        }

        if ($source === []) {
            return response()->json([
                'success' => false,
                'message' => 'No images found on Image Master for this SKU. Add photos on /image-master, then try again.',
                'images' => [],
            ], 422);
        }

        $images = ListingManagerImageStore::localizeMany($source, $sku);
        if ($images === []) {
            $images = array_values(array_filter(array_map('strval', $source)));
        }
        if ($images === []) {
            return response()->json([
                'success' => false,
                'message' => 'No images found on Image Master for this SKU. Add photos on /image-master, then try again.',
                'images' => [],
            ], 422);
        }

        $details = ListingManagerPublishStatus::normalizeDetails(
            is_array($draft->listing_details) ? $draft->listing_details : []
        );
        $details['images'] = $images;
        $details['image_url'] = $images[0];
        $details['image_source_urls'] = ListingManagerImageStore::sourceUrlsForSku($sku);
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
            'message' => 'Loaded '.count($images).' image(s) from Image Master.',
            'images' => $images,
            'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
        ]);
    }

    /**
     * @return list<string>
     */
    private function fetchLiveAmazonImages(string $sku, string $asin = '', bool $includeLocal = true): array
    {
        $images = [];
        $push = function ($url) use (&$images) {
            $url = trim((string) $url);
            if ($url === '') {
                return;
            }
            $ok = preg_match('#^https?://#i', $url)
                || str_starts_with($url, '/storage/')
                || ListingManagerImageStore::isStored($url);
            if (! $ok) {
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

        $hydrated = [];
        if ($includeLocal) {
            $hydrated = ListingManagerAmazonHydrator::hydrate($sku);
            foreach ($hydrated['images'] as $u) {
                $push($u);
            }
            if ($images !== []) {
                return array_values($images);
            }
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
     * @param  list<mixed>  ...$groups
     * @return list<string>
     */
    private function mergeDraftImageUrls(array ...$groups): array
    {
        $out = [];
        $seen = [];
        foreach ($groups as $group) {
            foreach ($group as $url) {
                $url = trim((string) $url);
                if ($url === '') {
                    continue;
                }
                $key = strtolower((string) preg_replace('/[?#].*$/', '', $url));
                $key = (string) preg_replace('/_(grande|large|medium|small|pico|compact|master|\d+x\d+)(?=\.(jpe?g|png|webp|gif))/i', '', $key);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $url;
            }
        }

        return $out;
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
     * Save & Publish draft to the selected marketplace.
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

        $liveQty = ListingManagerAmazonHydrator::shopifyQuantity((string) $draft->seller_sku, true);
        if ($liveQty !== null) {
            $draft->quantity = $liveQty;
            $draft->save();
        }

        $details['images'] = ListingManagerImageStore::publishUrls(
            is_array($details['images'] ?? null) ? $details['images'] : [],
            is_array($details['image_source_urls'] ?? null)
                ? $details['image_source_urls']
                : ListingManagerImageStore::sourceUrlsForSku((string) $draft->seller_sku)
        );

        $result = app(ListingManagerPublishDispatcher::class)->publish($draft, $details);

        if (! ($result['success'] ?? false)) {
            if (! empty($result['queued'])) {
                $draft->status = 'ready';
                $draft->notes = trim((string) $draft->notes."\n".$result['message']);
                $draft->save();

                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
                ], 422);
            }

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
        $siblingSkus = is_array($result['sibling_skus'] ?? null) ? $result['sibling_skus'] : [(string) $draft->seller_sku];
        $now = now();
        foreach ($siblingSkus as $siblingSku) {
            $row = $siblingSku === (string) $draft->seller_sku
                ? $draft
                : ListingManagerChannelDraft::query()
                    ->where('channel_id', $draft->channel_id)
                    ->where('seller_sku', $siblingSku)
                    ->first();
            if (! $row) {
                continue;
            }
            $row->status = 'listed';
            $row->external_listing_id = $itemId !== '' ? $itemId : $row->external_listing_id;
            $row->listed_at = $now;
            $row->publish_checked_at = $now;
            if ($row->id === $draft->id) {
                $row->listing_details = $details;
            }
            $row->notes = trim((string) $row->notes."\nPublished to {$channelName} via Listing Manager.");
            $row->save();
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? ('Published to '.$channelName.'.'),
            'draft' => $this->serializeDraft($draft->fresh()->load('channel:id,channel,logo'), true),
        ]);
    }

    /**
     * Copy title / description / images / identifiers from this draft onto sibling drafts.
     */
    public function copyToSiblings(Request $request, int $id)
    {
        $draft = ListingManagerChannelDraft::query()->with('channel:id,channel,logo')->findOrFail($id);
        if ($draft->status === 'listed') {
            return response()->json(['success' => false, 'message' => 'Cannot copy a published listing onto siblings.'], 422);
        }

        $details = $this->ensureIdentifierDefaults(
            ListingManagerPublishStatus::normalizeDetails(
                is_array($draft->listing_details) ? $draft->listing_details : []
            ),
            (string) $draft->seller_sku
        );
        $family = ListingManagerFamily::forSku((string) $draft->seller_sku);
        $copied = 0;
        $created = 0;

        foreach ($family['skus'] as $siblingSku) {
            if (strcasecmp($siblingSku, (string) $draft->seller_sku) === 0) {
                continue;
            }
            $sibling = $this->findChannelDraft((int) $draft->channel_id, $siblingSku);

            $hydrated = ListingManagerAmazonHydrator::hydrate($siblingSku, false);
            $siblingDetails = $this->ensureIdentifierDefaults(
                ListingManagerAmazonHydrator::detailsFromHydration($hydrated, $details, (string) ($draft->channel->channel ?? '')),
                $siblingSku
            );
            $siblingDetails['description'] = $details['description'] ?? $siblingDetails['description'];
            $siblingDetails['images'] = $details['images'] ?? $siblingDetails['images'];
            $siblingDetails['image_url'] = $details['image_url'] ?? $siblingDetails['image_url'];
            $siblingDetails['brand'] = $details['brand'] ?? $siblingDetails['brand'];
            $siblingDetails['manufacturer'] = $details['manufacturer'] ?? $siblingDetails['manufacturer'];
            foreach (['primary_category_id', 'primary_category_path', 'secondary_category_id', 'shipping_policy_id', 'payment_policy_id', 'return_policy_id', 'condition', 'location_city', 'location_country', 'location_postal_code'] as $keep) {
                if (trim((string) ($details[$keep] ?? '')) !== '') {
                    $siblingDetails[$keep] = $details[$keep];
                }
            }

            $payload = [
                'asin' => $hydrated['asin'] ?: ($sibling?->asin),
                'title' => $draft->title,
                'thumbnail_image' => $draft->thumbnail_image ?: $hydrated['thumbnail'],
                'price' => $hydrated['price'] ?? ($sibling?->price ?? $draft->price),
                'quantity' => $hydrated['quantity'] ?? ($sibling?->quantity ?? $draft->quantity),
                'listing_details' => $siblingDetails,
                'amazon_snapshot' => $hydrated['snapshot'],
                'notes' => trim((string) (($sibling?->notes ?? '')."\nCopied listing details from {$draft->seller_sku}.")),
            ];
            $ready = ListingManagerPublishStatus::readiness(
                $payload['title'],
                $payload['price'],
                $payload['quantity'],
                $siblingDetails,
                $sibling?->status ?? 'draft',
                (string) ($draft->channel->channel ?? '')
            );
            $payload['status'] = ($sibling && $sibling->status === 'listed') ? 'listed' : ($ready['ready'] ? 'ready' : 'draft');

            $result = $this->upsertChannelDraft((int) $draft->channel_id, $siblingSku, $payload);
            if ($result === 'created') {
                $created++;
            } else {
                $copied++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Copied listing details to {$copied} sibling draft(s)".($created ? ", created {$created}" : '').'.',
            'copied' => $copied,
            'created' => $created,
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
                        is_array($draft->listing_details) ? $draft->listing_details : [],
                        (string) $draft->status,
                        $channelName
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
            || ((float) ($details['package_weight_lb'] ?? 0) + ((float) ($details['package_weight_oz'] ?? 0) / 16)) <= 0;

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
        $draft->save();
        $this->persistDraftImages($draft, $force);

        if ($draft->status !== 'listed') {
            $ready = ListingManagerPublishStatus::readiness(
                $draft->title,
                $draft->price,
                $draft->quantity,
                is_array($draft->listing_details) ? $draft->listing_details : $merged,
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
     * Brand / Manufacturer = 5 Core Inc, MPN = SKU, UPC from CP Master (product_master).
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function ensureIdentifierDefaults(array $details, string $sku): array
    {
        $sku = trim($sku);
        $defaultBrand = trim((string) config('listing_manager.default_brand', '5 Core Inc.')) ?: '5 Core Inc.';
        $defaultManufacturer = trim((string) config('listing_manager.default_manufacturer', '5 Core Inc.')) ?: '5 Core Inc.';
        $brand = trim((string) ($details['brand'] ?? '')) ?: $defaultBrand;
        $manufacturer = trim((string) ($details['manufacturer'] ?? '')) ?: $defaultManufacturer;
        $mpn = trim((string) ($details['mpn'] ?? '')) ?: $sku;
        $specifics = is_array($details['item_specifics'] ?? null) ? $details['item_specifics'] : [];
        $upc = trim((string) ($details['upc'] ?? ''));
        $specificsUpc = trim((string) ($specifics['UPC'] ?? ''));
        if ($upc === '' || ListingManagerAmazonHydrator::looksLikeAsin($upc)) {
            $upc = $specificsUpc;
        }
        if (($upc === '' || ListingManagerAmazonHydrator::looksLikeAsin($upc)) && $sku !== '') {
            $upc = ListingManagerAmazonHydrator::upcFromCpMaster($sku);
        }
        $specifics['Brand'] = $brand;
        $specifics['Manufacturer'] = $manufacturer;
        $specifics['MPN'] = $mpn;
        if ($upc !== '') {
            $specifics['UPC'] = $upc;
        }

        $details['brand'] = $brand;
        $details['manufacturer'] = $manufacturer;
        $details['mpn'] = $mpn;
        $details['upc'] = $upc;
        $details['item_specifics'] = $specifics;

        return ListingManagerPublishStatus::normalizeDetails($details);
    }

    public function media(string $file)
    {
        return ListingManagerImageStore::serve($file);
    }

    /**
     * Fill missing TikTok/Temu images and package weight from Product Master / Amazon when the editor opens.
     */
    private function ensureDraftMediaAndPackage(?ListingManagerChannelDraft $draft): void
    {
        if (! $draft || $draft->status === 'listed') {
            return;
        }

        $details = ListingManagerPublishStatus::normalizeDetails(
            is_array($draft->listing_details) ? $draft->listing_details : []
        );
        $images = is_array($details['images'] ?? null) ? $details['images'] : [];
        $weightOk = ((float) ($details['package_weight_lb'] ?? 0) + ((float) ($details['package_weight_oz'] ?? 0) / 16)) > 0;
        $family = ListingManagerEditorProfile::family(ListingChannelCounts::normalize((string) ($draft->channel->channel ?? '')));
        $needsImages = $images === [] && trim((string) ($details['image_url'] ?? '')) === '';
        if (! $needsImages && $weightOk) {
            return;
        }

        if ($needsImages) {
            $fetched = ListingManagerAmazonHydrator::imageMasterUrls((string) $draft->seller_sku);
            if ($family === 'tiktok') {
                $fetched = array_slice($fetched, 0, 9);
            }
            if ($fetched !== []) {
                $stored = ListingManagerImageStore::localizeMany($fetched, (string) $draft->seller_sku);
                if ($stored === []) {
                    $stored = $fetched;
                }
                if ($stored !== [] && count($stored) > count($images)) {
                    $details['images'] = $stored;
                    $details['image_url'] = $stored[0];
                    $details['image_source_urls'] = ListingManagerImageStore::sourceUrlsForSku((string) $draft->seller_sku);
                    $draft->thumbnail_image = $stored[0];
                }
            }
        }

        if (! $weightOk) {
            $hydrated = ListingManagerAmazonHydrator::hydrate((string) $draft->seller_sku, false);
            $channelName = (string) ($draft->channel->channel ?? '');
            $merged = ListingManagerAmazonHydrator::detailsFromHydration($hydrated, $details, $channelName);
            foreach (['package_length', 'package_width', 'package_height', 'package_weight_lb', 'package_weight_oz'] as $key) {
                if (trim((string) ($details[$key] ?? '')) === '' || ($key === 'package_weight_lb' && ! $weightOk)) {
                    $details[$key] = $merged[$key] ?? $details[$key] ?? '';
                }
            }
        }

        $draft->listing_details = ListingManagerPublishStatus::normalizeDetails($details);
        if ($draft->status !== 'listed') {
            $ready = ListingManagerPublishStatus::readiness(
                $draft->title,
                $draft->price,
                $draft->quantity,
                $draft->listing_details,
                (string) $draft->status,
                (string) ($draft->channel->channel ?? '')
            );
            $draft->status = $ready['ready'] ? 'ready' : 'draft';
        }
        $draft->save();
    }

    /**
     * Download remote draft images into local storage and point the draft at those files.
     */
    private function persistDraftImages(?ListingManagerChannelDraft $draft, bool $force = false): void
    {
        if (! $draft || ($draft->status === 'listed' && ! $force)) {
            return;
        }

        $details = ListingManagerPublishStatus::normalizeDetails(
            is_array($draft->listing_details) ? $draft->listing_details : []
        );
        $images = is_array($details['images'] ?? null) ? $details['images'] : [];
        if ($images === [] && trim((string) $draft->thumbnail_image) !== '') {
            $images = [(string) $draft->thumbnail_image];
        }
        $needsDownload = $force;
        foreach ($images as $url) {
            $url = trim((string) $url);
            if ($url !== '' && preg_match('#^https?://#i', $url) && ! ListingManagerImageStore::isStored($url)) {
                $needsDownload = true;
                break;
            }
        }
        if (! $needsDownload) {
            return;
        }

        $stored = ListingManagerImageStore::localizeMany($images, (string) $draft->seller_sku);
        if ($stored === []) {
            return;
        }

        $details['images'] = $stored;
        $details['image_url'] = $stored[0];
        $details['image_source_urls'] = ListingManagerImageStore::sourceUrlsForSku((string) $draft->seller_sku);
        $draft->listing_details = $details;
        $draft->thumbnail_image = $stored[0];
        $snap = is_array($draft->amazon_snapshot) ? $draft->amazon_snapshot : [];
        $snap['images'] = $stored;
        $snap['thumbnail_image'] = $stored[0];
        $draft->amazon_snapshot = $snap;
        $draft->save();
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
            'can_direct_publish' => ListingManagerPublishDispatcher::canDirectPublish($channelName),
            'editor' => ListingManagerEditorProfile::forChannel($channelName),
        ];

        if ($full) {
            $payload['listing_details'] = $details;
            try {
                $payload['family'] = ListingManagerFamily::forSku((string) $d->seller_sku);
                $payload['variations'] = $this->variationRowsForSku((string) $d->seller_sku);
            } catch (\Throwable $e) {
                Log::warning('ListingManager serializeDraft family: '.$e->getMessage(), ['id' => $d->id]);
                $payload['family'] = [
                    'parent' => (string) $d->seller_sku,
                    'children' => [],
                    'skus' => [(string) $d->seller_sku],
                ];
                $payload['variations'] = [];
            }
        }

        return $payload;
    }

    /**
     * Image Master hero lookups for listing-manager columns.
     *
     * @param  list<string>  $skus
     * @return array{hero: array<string, string>, pm_images: array<string, list<string>>}
     */
    private function imageLookupsForSkus(array $skus): array
    {
        $hero = [];
        $pmImages = [];
        $skus = array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $skus)));
        if ($skus === []) {
            return ['hero' => $hero, 'pm_images' => $pmImages];
        }

        $skuSet = array_fill_keys($skus, true);
        $heroByNorm = [];
        $pmByNorm = [];

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
        }

        return ['hero' => $hero, 'pm_images' => $pmImages];
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
