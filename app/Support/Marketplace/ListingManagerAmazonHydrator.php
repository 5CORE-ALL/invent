<?php

namespace App\Support\Marketplace;

use App\Models\AmazonListingRaw;
use App\Services\ShopifyApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Hydrate listing-manager drafts from the main store (Shopify) + Amazon catalog.
 */
class ListingManagerAmazonHydrator
{
    /**
     * Fetch rich description HTML (+ images) from main store (Shopify),
     * then product_master / Amazon fallbacks.
     *
     * @return array{html: string, images: list<string>, title: string, source: string}
     */
    public static function fetchMainStoreDescription(string $sku, bool $liveShopify = true): array
    {
        $sku = trim($sku);
        $empty = ['html' => '', 'images' => [], 'title' => '', 'source' => 'none'];
        if ($sku === '') {
            return $empty;
        }

        if ($liveShopify) {
            try {
                $res = app(ShopifyApiService::class)->fetchProductDescriptionHtml($sku);
                if (($res['success'] ?? false) && trim((string) ($res['html'] ?? '')) !== '') {
                    $html = trim((string) $res['html']);
                    $images = [];
                    foreach (($res['images'] ?? []) as $u) {
                        $u = trim((string) $u);
                        if ($u !== '' && preg_match('#^https?://#i', $u) && ! in_array($u, $images, true)) {
                            $images[] = $u;
                        }
                    }
                    // Persist for offline reuse
                    if (Schema::hasTable('product_master') && Schema::hasColumn('product_master', 'description_html')) {
                        DB::table('product_master')->where('sku', $sku)->update(['description_html' => $html]);
                    }

                    return [
                        'html' => $html,
                        'images' => $images,
                        'title' => trim((string) ($res['title'] ?? '')),
                        'source' => 'shopify',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('ListingManager main-store description Shopify fetch failed: '.$e->getMessage(), [
                    'sku' => $sku,
                ]);
            }
        }

        $pm = self::productMaster($sku);
        $html = self::firstNonEmpty([
            $pm['description_html'] ?? null,
            $pm['amazon_aplus_content'] ?? null,
            $pm['description_1500'] ?? null,
            $pm['product_description'] ?? null,
        ]);
        if ($html !== '') {
            $images = self::collectImages(null, [], $pm, $sku);

            return [
                'html' => $html,
                'images' => $images,
                'title' => self::firstNonEmpty([$pm['title150'] ?? null, $pm['title100'] ?? null, $pm['title80'] ?? null]),
                'source' => 'product_master',
            ];
        }

        $hydrated = self::hydrate($sku, false);
        if ($hydrated['description'] !== '') {
            return [
                'html' => $hydrated['description'],
                'images' => $hydrated['images'],
                'title' => $hydrated['title'],
                'source' => 'amazon',
            ];
        }

        return $empty;
    }

    /**
     * @return array{
     *   title: string,
     *   asin: string|null,
     *   price: float|null,
     *   quantity: int|null,
     *   thumbnail: string|null,
     *   description: string,
     *   brand: string,
     *   condition: string,
     *   product_type: string,
     *   images: list<string>,
     *   bullets: list<string>,
     *   package_length: string,
     *   package_width: string,
     *   package_height: string,
     *   package_weight_lb: string,
     *   package_weight_oz: string,
     *   snapshot: array<string, mixed>
     * }
     */
    public static function hydrate(string $sku, bool $withLiveMainStore = false): array
    {
        $sku = trim($sku);
        $listing = AmazonListingRaw::query()->where('seller_sku', $sku)->first();
        $raw = is_array($listing?->raw_data) ? $listing->raw_data : [];
        $pm = self::productMaster($sku);

        $mainStore = $withLiveMainStore
            ? self::fetchMainStoreDescription($sku, true)
            : ['html' => '', 'images' => [], 'title' => '', 'source' => 'none'];

        $title = self::firstNonEmpty([
            $pm['title80'] ?? null,
            $pm['title100'] ?? null,
            $pm['title150'] ?? null,
            $pm['title60'] ?? null,
            $listing?->item_name,
            self::rawGet($raw, 'item-name', 'item_name', 'title'),
            $mainStore['title'] ?? null,
            $sku,
        ]);

        $description = self::firstNonEmpty([
            self::descriptionMasterFromMetrics($sku),
            $pm['description_1500'] ?? null,
            $pm['description_html'] ?? null,
            $pm['product_description'] ?? null,
            $pm['description_1000'] ?? null,
            $pm['description_800'] ?? null,
            $pm['description_600'] ?? null,
            $pm['description_v2_description'] ?? null,
            $pm['amazon_aplus_content'] ?? null,
            $listing?->product_description,
            self::rawGet($raw, 'item-description', 'item_description', 'description'),
            $mainStore['html'] ?? null,
        ]);

        $bullets = self::bullets($listing?->bullet_point);
        if ($description === '' && $bullets !== []) {
            $description = '<ul><li>'.implode('</li><li>', array_map('e', $bullets)).'</li></ul>';
        } elseif ($description !== '' && ! preg_match('/^\s*</', $description)) {
            // Keep plain text; UI can optimize to HTML
            $description = trim($description);
        }

        $priceRaw = self::firstNonEmpty([
            $listing?->your_price,
            self::rawGet($raw, 'price', 'your_price'),
            $listing?->list_price,
        ]);
        $price = $priceRaw !== '' ? (float) $priceRaw : null;

        $qtyRaw = self::firstNonEmpty([
            $listing?->quantity,
            self::rawGet($raw, 'quantity', 'pending-quantity'),
        ]);
        $shopifyQty = self::shopifyQuantity($sku, false);
        $quantity = $shopifyQty !== null ? $shopifyQty : ($qtyRaw !== '' ? (int) $qtyRaw : null);

        $images = self::collectImages($listing, $raw, $pm, $sku);
        $images = ListingManagerImageStore::applyToList($images);
        $dims = self::dimensions($listing?->item_dimensions, $pm);

        $defaultBrand = trim((string) config('listing_manager.default_brand', '5 Core Inc.')) ?: '5 Core Inc.';
        $defaultManufacturer = trim((string) config('listing_manager.default_manufacturer', '5 Core Inc.')) ?: '5 Core Inc.';
        $brand = $defaultBrand;
        $manufacturer = $defaultManufacturer;

        $upc = self::upcFromCpMaster($sku, $pm, $listing, $raw);

        $condition = self::firstNonEmpty([
            $listing?->condition_type_display,
            self::mapCondition(self::rawGet($raw, 'item-condition', 'condition')),
            'New',
        ]);

        return [
            'title' => $title,
            'asin' => self::firstNonEmpty([
                $listing?->asin1,
                self::rawGet($raw, 'asin1', 'asin'),
            ]) ?: null,
            'price' => $price,
            'quantity' => $quantity,
            'thumbnail' => $images[0] ?? null,
            'description' => $description,
            'sku' => $sku,
            'brand' => $brand,
            'manufacturer' => $manufacturer,
            'mpn' => $sku,
            'upc' => $upc,
            'condition' => $condition,
            'product_type' => self::firstNonEmpty([
                $listing?->product_type,
                self::rawGet($raw, 'zshop-category1', 'product_type'),
            ]),
            'images' => $images,
            'bullets' => $bullets,
            'package_length' => $dims['length'],
            'package_width' => $dims['width'],
            'package_height' => $dims['height'],
            'package_weight_lb' => $dims['weight_lb'],
            'package_weight_oz' => $dims['weight_oz'],
            'snapshot' => [
                'seller_sku' => $sku,
                'asin1' => $listing?->asin1,
                'item_name' => $title,
                'your_price' => $price,
                'list_price' => $listing?->list_price,
                'quantity' => $quantity,
                'product_type' => $listing?->product_type,
                'thumbnail_image' => $images[0] ?? null,
                'images' => $images,
                'brand' => $brand,
                'bullet_point' => $bullets,
                'product_description' => $description,
                'item_dimensions' => $listing?->item_dimensions,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $existingDetails
     * @return array<string, mixed>
     */
    public static function detailsFromHydration(array $hydrated, array $existingDetails = [], ?string $channelKey = null): array
    {
        $defaults = [];
        $key = ListingChannelCounts::normalize((string) $channelKey);
        if (in_array($key, ['ebay', 'ebay1', 'ebayone', 'ebay2', 'ebaytwo', 'ebay3', 'ebaythree'], true)) {
            $defaults = EbaySellAccountPolicies::defaultsForChannel($key);
        }

        $defaultBrand = trim((string) config('listing_manager.default_brand', '5 Core Inc.')) ?: '5 Core Inc.';
        $defaultManufacturer = trim((string) config('listing_manager.default_manufacturer', '5 Core Inc.')) ?: '5 Core Inc.';
        $sku = trim((string) ($hydrated['sku'] ?? $hydrated['mpn'] ?? ''));
        $brand = trim((string) ($existingDetails['brand'] ?? '')) ?: $defaultBrand;
        $manufacturer = trim((string) ($existingDetails['manufacturer'] ?? ($hydrated['manufacturer'] ?? ''))) ?: $defaultManufacturer;
        $mpn = trim((string) ($existingDetails['mpn'] ?? '')) ?: $sku;
        $upc = trim((string) ($existingDetails['upc'] ?? ''));
        if ($upc === '' || self::looksLikeAsin($upc)) {
            $upc = trim((string) ($hydrated['upc'] ?? ''));
        }
        if (($upc === '' || self::looksLikeAsin($upc)) && $sku !== '') {
            $upc = self::upcFromCpMaster($sku);
        }

        $existingSpecifics = is_array($existingDetails['item_specifics'] ?? null)
            ? $existingDetails['item_specifics']
            : [];
        $specifics = array_merge($existingSpecifics, array_filter([
            'Brand' => $brand,
            'Manufacturer' => $manufacturer,
            'MPN' => $mpn,
            'UPC' => $upc,
        ]));

        $bullets = $hydrated['bullets'] ?? [];
        $merged = array_merge($existingDetails, [
            'description' => $hydrated['description'] ?: ($existingDetails['description'] ?? ''),
            'condition' => $hydrated['condition'] ?: ($existingDetails['condition'] ?? 'New'),
            'brand' => $brand,
            'manufacturer' => $manufacturer,
            'mpn' => $mpn,
            'upc' => $upc,
            'category' => $hydrated['product_type'] ?: ($existingDetails['category'] ?? ''),
            'image_url' => $hydrated['thumbnail'] ?: ($existingDetails['image_url'] ?? ''),
            'images' => $hydrated['images'] !== [] ? $hydrated['images'] : ($existingDetails['images'] ?? []),
            'bullet_1' => $bullets[0] ?? ($existingDetails['bullet_1'] ?? ''),
            'bullet_2' => $bullets[1] ?? ($existingDetails['bullet_2'] ?? ''),
            'bullet_3' => $bullets[2] ?? ($existingDetails['bullet_3'] ?? ''),
            'bullet_4' => $bullets[3] ?? ($existingDetails['bullet_4'] ?? ''),
            'bullet_5' => $bullets[4] ?? ($existingDetails['bullet_5'] ?? ''),
            'item_specifics' => $specifics,
            'package_length' => $hydrated['package_length'] ?: ($existingDetails['package_length'] ?? ''),
            'package_width' => $hydrated['package_width'] ?: ($existingDetails['package_width'] ?? ''),
            'package_height' => $hydrated['package_height'] ?: ($existingDetails['package_height'] ?? ''),
            'package_weight_lb' => $hydrated['package_weight_lb'] ?: ($existingDetails['package_weight_lb'] ?? ''),
            'package_weight_oz' => $hydrated['package_weight_oz'] ?: ($existingDetails['package_weight_oz'] ?? ''),
            'warehouse_id' => trim((string) ($existingDetails['warehouse_id'] ?? ''))
                ?: (in_array($key, ['tiktok2', 'tiktokshop2', 'tiktoktwo'], true)
                    ? trim((string) config('services.tiktok2.warehouse_id', ''))
                    : (str_contains($key, 'tiktok') ? trim((string) config('services.tiktok.warehouse_id', '')) : '')),
            'location_city' => $existingDetails['location_city'] ?? ($defaults['location_city'] ?? 'Bellefontaine'),
            'location_country' => $existingDetails['location_country'] ?? ($defaults['location_country'] ?? 'US'),
            'location_postal_code' => $existingDetails['location_postal_code'] ?? ($defaults['location_postal_code'] ?? '43311'),
            'shipping_policy_id' => $existingDetails['shipping_policy_id'] ?? ($defaults['shipping_policy_id'] ?? ''),
            'payment_policy_id' => $existingDetails['payment_policy_id'] ?? ($defaults['payment_policy_id'] ?? ''),
            'return_policy_id' => $existingDetails['return_policy_id'] ?? ($defaults['return_policy_id'] ?? ''),
            'listing_format' => $existingDetails['listing_format'] ?? 'FixedPriceItem',
            'duration' => $existingDetails['duration'] ?? 'GTC',
        ]);

        if (ListingManagerEditorProfile::family($key) === 'reverb') {
            $merged = self::applyReverbListingFields($merged, self::productMaster($sku), $hydrated);
        }

        return ListingManagerPublishStatus::normalizeDetails($merged);
    }

    /**
     * Prefill Reverb make/model/finish/year/condition/shipping from Reverb Listing Master.
     *
     * @param  array<string, mixed>  $merged
     * @param  array<string, mixed>  $pm
     * @param  array<string, mixed>  $hydrated
     * @return array<string, mixed>
     */
    private static function applyReverbListingFields(array $merged, array $pm, array $hydrated): array
    {
        $brand = trim((string) ($merged['brand'] ?? ''));
        $sku = trim((string) ($hydrated['sku'] ?? $merged['mpn'] ?? ''));
        $merged['make'] = trim((string) ($merged['make'] ?? ''))
            ?: trim((string) ($pm['reverb_make'] ?? ''))
            ?: $brand;
        $merged['model'] = trim((string) ($merged['model'] ?? ''))
            ?: trim((string) ($pm['reverb_model'] ?? ''))
            ?: $sku;
        $merged['finish'] = trim((string) ($merged['finish'] ?? ''))
            ?: trim((string) ($pm['reverb_finish'] ?? ''));
        $merged['year'] = trim((string) ($merged['year'] ?? ''))
            ?: trim((string) ($pm['reverb_year'] ?? ''));

        $conditionName = trim((string) ($merged['condition_name'] ?? ''))
            ?: trim((string) ($pm['reverb_condition'] ?? ''));
        if ($conditionName !== '') {
            $merged['condition_name'] = $conditionName;
            $merged['condition'] = $conditionName;
        }
        $merged['condition_uuid'] = trim((string) ($merged['condition_uuid'] ?? ''));

        $merged['shipping_profile_id'] = trim((string) ($merged['shipping_profile_id'] ?? ''))
            ?: trim((string) ($pm['reverb_shipping_profile_id'] ?? ''));
        $merged['price_currency'] = trim((string) ($merged['price_currency'] ?? '')) ?: 'USD';
        if (! array_key_exists('offers_enabled', $merged)) {
            $merged['offers_enabled'] = true;
        }
        if (! array_key_exists('has_inventory', $merged)) {
            $merged['has_inventory'] = true;
        }

        $merged['category_uuid'] = trim((string) ($merged['category_uuid'] ?? $merged['primary_category_id'] ?? ''));
        $merged['category_name'] = trim((string) ($merged['category_name'] ?? $merged['primary_category_path'] ?? ''));
        if ($merged['category_uuid'] !== '') {
            $merged['primary_category_id'] = $merged['category_uuid'];
        }
        if ($merged['category_name'] !== '') {
            $merged['primary_category_path'] = $merged['category_name'];
        }

        return $merged;
    }

    /**
     * Amazon ASINs (typically B0 + 8 alphanumerics) must never be shown as UPC.
     */
    public static function looksLikeAsin(string $code): bool
    {
        $code = strtoupper((string) preg_replace('/\s+/', '', $code));

        return $code !== '' && (bool) preg_match('/^B0[A-Z0-9]{8}$/', $code);
    }

    /**
     * UPC / barcode from CP Master (product_master). Never returns an ASIN.
     *
     * @param  array<string, mixed>  $pm
     * @param  array<string, mixed>  $raw
     */
    public static function upcFromCpMaster(
        string $sku,
        ?array $pm = null,
        ?AmazonListingRaw $listing = null,
        array $raw = []
    ): string {
        $sku = trim($sku);
        $pm = $pm ?? ($sku !== '' ? self::productMaster($sku) : []);

        $candidates = [];
        $push = function ($v) use (&$candidates) {
            $code = self::normalizeUpcCandidate($v);
            if ($code === '' || self::looksLikeAsin($code) || in_array($code, $candidates, true)) {
                return;
            }
            $candidates[] = $code;
        };

        $collectFromPm = function (array $row) use ($push): void {
            $push($row['barcode'] ?? null);
            $push($row['upc'] ?? null);
            $values = $row['Values'] ?? $row['values'] ?? null;
            if (is_string($values)) {
                $decoded = json_decode($values, true);
                $values = is_array($decoded) ? $decoded : [];
            }
            if (is_array($values)) {
                foreach (['upc', 'UPC', 'gtin', 'GTIN', 'ean', 'EAN', 'barcode', 'Barcode'] as $key) {
                    $push($values[$key] ?? null);
                }
            }
        };

        $collectFromPm($pm);
        $parent = trim((string) ($pm['parent'] ?? $pm['parent_sku'] ?? ''));
        if ($parent !== '' && strcasecmp($parent, $sku) !== 0) {
            $collectFromPm(self::productMaster($parent));
        }

        $idType = strtolower(trim((string) self::rawGet($raw, 'product-id-type', 'product_id_type', 'external_product_id_type')));
        $amazonIdIsUpc = $idType === '' || in_array($idType, ['3', '4', '5', 'upc', 'ean', 'gtin', 'isbn'], true);
        if ($amazonIdIsUpc) {
            $push($listing?->external_product_id);
            $push(self::rawGet($raw, 'upc', 'product-id', 'product_id'));
        }

        $asin = strtoupper(trim((string) ($listing?->asin1 ?? self::rawGet($raw, 'asin1', 'asin') ?? '')));
        foreach ($candidates as $code) {
            if ($asin !== '' && strcasecmp($code, $asin) === 0) {
                continue;
            }
            $digits = preg_replace('/\D/', '', $code) ?? '';
            if (in_array(strlen($digits), [8, 11, 12, 13, 14], true)) {
                return $digits;
            }
        }

        foreach ($candidates as $code) {
            if ($asin !== '' && strcasecmp($code, $asin) === 0) {
                continue;
            }
            if (! self::looksLikeAsin($code)) {
                return $code;
            }
        }

        return '';
    }

    private static function normalizeUpcCandidate(mixed $v): string
    {
        if (is_int($v) || is_float($v)) {
            $v = sprintf('%.0f', $v);
        }
        $code = trim((string) ($v ?? ''));
        if ($code === '' || $code === '-') {
            return '';
        }
        if (is_numeric($code) && stripos($code, 'e') !== false) {
            $code = sprintf('%.0f', (float) $code);
        }

        return (string) (preg_replace('/\s+/', '', $code) ?? '');
    }

    /**
     * @return array{title: int, description: int}
     */
    public static function limitsForChannel(?string $channelName): array
    {
        $key = ListingChannelCounts::normalize((string) $channelName);
        $all = (array) config('listing_manager.limits', []);
        $limits = $all[$key] ?? $all['default'] ?? ['title' => 200, 'description' => 5000];

        return [
            'title' => (int) ($limits['title'] ?? 200),
            'description' => (int) ($limits['description'] ?? 5000),
        ];
    }

    /**
     * @param  array<int, mixed>|null  $values
     */
    /**
     * Current Shopify inventory for a SKU. Live refresh hits Admin API and writes through shopify_skus.
     */
    public static function shopifyQuantity(string $sku, bool $live = false): ?int
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        try {
            $row = \App\Models\ShopifySku::firstForProductSku($sku);
            if ($live && $row) {
                $row = \App\Services\MarketplaceManager\MarketplaceListingStockResolver::refreshShopifyRowFromLiveVariantApi($row);
            } elseif ($live && ! $row) {
                $liveMap = app(\App\Services\ShopifyApiService::class)->getInventoryQuantitiesBySku([$sku]);
                foreach ($liveMap as $key => $qty) {
                    if (strcasecmp(trim((string) $key), $sku) === 0
                        || \App\Models\ShopifySku::normalizeSkuForShopifyLookup((string) $key)
                            === \App\Models\ShopifySku::normalizeSkuForShopifyLookup($sku)) {
                        return max(0, (int) $qty);
                    }
                }
            }

            $qty = \App\Services\MarketplaceManager\MarketplaceListingStockResolver::shopifyQtyFromRow($row);
            if ($qty !== null) {
                return max(0, $qty);
            }

            $map = \App\Services\MarketplaceManager\MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus([$sku]);
            $upper = strtoupper($sku);
            if (isset($map[$upper])) {
                return max(0, (int) $map[$upper]);
            }
            $norm = \App\Models\ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && isset($map[$norm])) {
                return max(0, (int) $map[$norm]);
            }
        } catch (\Throwable $e) {
            Log::warning('ListingManager Shopify qty failed: '.$e->getMessage(), ['sku' => $sku]);
        }

        return null;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    public static function shopifyQuantities(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map('trim', $skus))));
        if ($skus === []) {
            return [];
        }

        $out = [];
        try {
            $map = \App\Services\MarketplaceManager\MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
            foreach ($skus as $sku) {
                $upper = strtoupper($sku);
                $norm = \App\Models\ShopifySku::normalizeSkuForShopifyLookup($sku);
                if (isset($map[$upper])) {
                    $out[$sku] = max(0, (int) $map[$upper]);
                } elseif ($norm !== '' && isset($map[$norm])) {
                    $out[$sku] = max(0, (int) $map[$norm]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ListingManager Shopify qty batch failed: '.$e->getMessage());
        }

        return $out;
    }

    private static function firstNonEmpty(array $values): string
    {
        foreach ($values as $v) {
            if ($v === null) {
                continue;
            }
            $s = trim((string) $v);
            if ($s !== '') {
                return $s;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function rawGet(array $raw, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $raw) && trim((string) $raw[$key]) !== '') {
                return trim((string) $raw[$key]);
            }
            // BOM-prefixed keys from Amazon flat-file reports
            foreach ($raw as $k => $v) {
                $norm = ltrim((string) $k, "\xEF\xBB\xBF");
                if (strcasecmp($norm, $key) === 0 && trim((string) $v) !== '') {
                    return trim((string) $v);
                }
            }
        }

        return '';
    }

    private static function bullets(mixed $bullets): array
    {
        if (is_string($bullets)) {
            $decoded = json_decode($bullets, true);
            $bullets = is_array($decoded) ? $decoded : [$bullets];
        }
        if (! is_array($bullets)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($b) => trim((string) $b), $bullets)));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $pm
     * @return list<string>
     */
    private static function collectImages(?AmazonListingRaw $listing, array $raw, array $pm, string $sku): array
    {
        $images = [];
        $push = function ($url) use (&$images) {
            $url = self::publicImageUrl($url);
            if ($url === '') {
                return;
            }
            if (! in_array($url, $images, true)) {
                $images[] = $url;
            }
        };

        foreach (self::productImageTableUrls($sku) as $url) {
            $push($url);
        }

        foreach (['main_image', 'image_url', 'main_image_brand'] as $k) {
            $push($pm[$k] ?? null);
        }
        for ($i = 1; $i <= 20; $i++) {
            $push($pm['image'.$i] ?? null);
        }

        $values = $pm['Values'] ?? $pm['values'] ?? [];
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        }
        if (is_array($values)) {
            $push($values['image_path'] ?? null);
            $push($values['main_image'] ?? null);
            $push($values['cdn_url'] ?? null);
        }

        foreach (self::imageMasterFromMetrics($sku) as $url) {
            $push($url);
        }

        $parentSku = trim((string) ($pm['parent'] ?? $pm['parent_sku'] ?? ''));
        if ($parentSku !== '' && strcasecmp($parentSku, $sku) !== 0) {
            foreach (self::productImageTableUrls($parentSku) as $url) {
                $push($url);
            }
            $parentPm = self::productMaster($parentSku);
            foreach (['main_image', 'image_url'] as $k) {
                $push($parentPm[$k] ?? null);
            }
            for ($i = 1; $i <= 20; $i++) {
                $push($parentPm['image'.$i] ?? null);
            }
            foreach (self::imageMasterFromMetrics($parentSku) as $url) {
                $push($url);
            }
        }

        // Listing Manager images come from Image Master only (not Shopify/Amazon).

        return $images;
    }

    /**
     * Ordered Image Master URLs for a SKU (product_images, Product Master slots, metrics JSON).
     *
     * @return list<string>
     */
    public static function imageMasterUrls(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        return self::collectImages(null, [], self::productMaster($sku), $sku);
    }

    /**
     * Public Image Master URLs for listing-page publish (SKU, then parent).
     * Prefers Shopify CDN copies so marketplaces can fetch the photos.
     *
     * @return list<string>
     */
    public static function publishImageUrls(string $sku, ?string $parentSku = null, int $limit = 9): array
    {
        $urls = self::imageMasterUrls($sku);
        $parentSku = trim((string) $parentSku);
        if ($parentSku !== '' && strcasecmp($parentSku, $sku) !== 0) {
            foreach (self::imageMasterUrls($parentSku) as $url) {
                if (! in_array($url, $urls, true)) {
                    $urls[] = $url;
                }
            }
        }

        $published = ListingManagerImageStore::publishUrls($urls, $urls);
        $out = [];
        foreach ($published as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            if (str_starts_with($url, '//')) {
                $url = 'https:'.$url;
            }
            if (! preg_match('#^https?://#i', $url) && str_starts_with($url, '/')) {
                $base = rtrim((string) config('app.url'), '/');
                $url = $base !== '' ? $base.$url : $url;
            }
            if (! preg_match('#^https?://#i', $url) || in_array($url, $out, true)) {
                continue;
            }
            $out[] = $url;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Full Shopify product gallery stored on shopify_catalog_products.image_urls.
     *
     * @return list<string>
     */
    private static function shopifyCatalogImageUrls(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '' || ! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }
        if (! Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
            return [];
        }

        try {
            $raw = DB::table('shopify_catalog_variants as v')
                ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
                ->whereRaw('LOWER(TRIM(COALESCE(v.sku, \'\'))) = ?', [mb_strtolower($sku)])
                ->orderByDesc('v.synced_at')
                ->value('p.image_urls');
        } catch (\Throwable) {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $item) {
            $url = is_array($item)
                ? trim((string) ($item['src'] ?? $item['url'] ?? $item['link'] ?? ''))
                : trim((string) $item);
            $url = self::publicImageUrl($url);
            if ($url !== '' && ! in_array($url, $out, true)) {
                $out[] = $url;
            }
        }

        return $out;
    }

    /**
     * Image Master rows (product_images) — local storage + Shopify CDN copies.
     *
     * @return list<string>
     */
    private static function productImageTableUrls(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '' || ! Schema::hasTable('product_images')) {
            return [];
        }

        $query = DB::table('product_images')->where('sku', $sku)->orderBy('id');
        if (Schema::hasColumn('product_images', 'cdn_url')) {
            $query->select(['image_path', 'cdn_url']);
        } else {
            $query->select(['image_path']);
        }

        $out = [];
        foreach ($query->get() as $row) {
            foreach (['cdn_url', 'image_path'] as $col) {
                $url = self::publicImageUrl($row->{$col} ?? null);
                if ($url !== '' && ! in_array($url, $out, true)) {
                    $out[] = $url;
                }
            }
        }

        return $out;
    }

    private static function publicImageUrl(mixed $value): string
    {
        $url = trim((string) $value);
        if ($url === '' || $url === '-') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }
        if (! preg_match('#^https?://#i', $url) && (ListingManagerImageStore::isStored($url) || str_starts_with($url, '/storage/'))) {
            $url = asset(ltrim($url, '/'));
        }
        $isRemote = (bool) preg_match('#^https?://#i', $url);
        $isStored = ListingManagerImageStore::isStored($url) || str_starts_with($url, '/storage/');
        if (! $isRemote && ! $isStored) {
            $rel = ltrim(str_replace('\\', '/', $url), '/');
            if (str_starts_with($rel, 'storage/')) {
                $url = asset($rel);
            } elseif (
                preg_match('#^(products|product_images|image_master|listing-manager)/#i', $rel)
                || preg_match('/\.(jpe?g|png|webp|gif|avif)(\?|$)/i', $rel)
            ) {
                $url = asset('storage/'.$rel);
            } else {
                return '';
            }
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    private static function productMaster(string $sku): array
    {
        if (! Schema::hasTable('product_master')) {
            return [];
        }
        $row = DB::table('product_master')->where('sku', $sku)->first();

        return $row ? (array) $row : [];
    }

    /**
     * @param  array<string, mixed>  $pm
     * @return array{length: string, width: string, height: string, weight_lb: string, weight_oz: string}
     */
    private static function dimensions(mixed $itemDimensions, array $pm): array
    {
        $out = ['length' => '', 'width' => '', 'height' => '', 'weight_lb' => '', 'weight_oz' => ''];
        $dims = $itemDimensions;
        if (is_string($dims)) {
            $decoded = json_decode($dims, true);
            $dims = is_array($decoded) ? $decoded : null;
        }
        if (is_array($dims)) {
            $out['length'] = self::dimValue($dims['length'] ?? $dims['Length'] ?? null);
            $out['width'] = self::dimValue($dims['width'] ?? $dims['Width'] ?? null);
            $out['height'] = self::dimValue($dims['height'] ?? $dims['Height'] ?? null);
            $weight = $dims['weight'] ?? $dims['Weight'] ?? null;
            if (is_array($weight)) {
                $w = (float) ($weight['value'] ?? 0);
                $unit = strtolower((string) ($weight['unit'] ?? 'pounds'));
                if (str_contains($unit, 'ounce')) {
                    $out['weight_lb'] = (string) (int) floor($w / 16);
                    $out['weight_oz'] = (string) round(fmod($w, 16), 1);
                } else {
                    $out['weight_lb'] = (string) (int) floor($w);
                    $out['weight_oz'] = (string) round(($w - floor($w)) * 16, 1);
                }
            }
        }

        // Optional package blurb from product_master
        $pkg = trim((string) ($pm['description_v2_package'] ?? ''));
        if ($pkg !== '' && ($out['length'] === '' || $out['weight_lb'] === '')) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*[xX×]\s*(\d+(?:\.\d+)?)\s*[xX×]\s*(\d+(?:\.\d+)?)/', $pkg, $m)) {
                $out['length'] = $out['length'] ?: $m[1];
                $out['width'] = $out['width'] ?: $m[2];
                $out['height'] = $out['height'] ?: $m[3];
            }
            if (preg_match('/(\d+(?:\.\d+)?)\s*(?:lb|lbs|pounds?)/i', $pkg, $m)) {
                $out['weight_lb'] = $out['weight_lb'] ?: $m[1];
            }
            if (preg_match('/(\d+(?:\.\d+)?)\s*(?:oz|ounces?)/i', $pkg, $m)) {
                $out['weight_oz'] = $out['weight_oz'] ?: $m[1];
            }
        }

        self::applyProductMasterDimWt($out, $pm);

        return $out;
    }

    /**
     * Dim / Wt from product_master.Values (l, w, h, wt_decl) used by Temu/TikTok publish.
     *
     * @param  array{length: string, width: string, height: string, weight_lb: string, weight_oz: string}  $out
     * @param  array<string, mixed>  $pm
     */
    private static function applyProductMasterDimWt(array &$out, array $pm): void
    {
        $values = $pm['Values'] ?? $pm['values'] ?? [];
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($values)) {
            $values = [];
        }

        $num = static function (array $keys) use ($values, $pm): ?float {
            foreach ($keys as $key) {
                foreach ([$values[$key] ?? null, $pm[$key] ?? null] as $raw) {
                    if ($raw === null || $raw === '') {
                        continue;
                    }
                    if (is_numeric($raw) && (float) $raw > 0) {
                        return (float) $raw;
                    }
                }
            }

            return null;
        };

        if ($out['length'] === '' || (float) $out['length'] <= 0) {
            $n = $num(['l_decl', 'l', 'L', 'length', 'l1']);
            if ($n !== null) {
                $out['length'] = (string) $n;
            }
        }
        if ($out['width'] === '' || (float) $out['width'] <= 0) {
            $n = $num(['w_decl', 'w', 'W', 'width', 'w1']);
            if ($n !== null) {
                $out['width'] = (string) $n;
            }
        }
        if ($out['height'] === '' || (float) $out['height'] <= 0) {
            $n = $num(['h_decl', 'h', 'H', 'height', 'h1']);
            if ($n !== null) {
                $out['height'] = (string) $n;
            }
        }

        $hasWeight = ((float) $out['weight_lb'] + ((float) $out['weight_oz'] / 16)) > 0;
        if ($hasWeight) {
            return;
        }
        $lb = $num(['wt_decl', 'wt_act', 'weight_lb', 'wt']);
        if ($lb !== null) {
            $out['weight_lb'] = (string) (int) floor($lb);
            $out['weight_oz'] = (string) round(($lb - floor($lb)) * 16, 1);

            return;
        }
        $kg = $num(['wt_act_kg', 'weight_kg', 'ctn_weight_kg']);
        if ($kg !== null) {
            $w = $kg * 2.20462;
            $out['weight_lb'] = (string) (int) floor($w);
            $out['weight_oz'] = (string) round(($w - floor($w)) * 16, 1);
        }
    }

    private static function dimValue(mixed $v): string
    {
        if (is_array($v)) {
            $n = $v['value'] ?? null;

            return $n === null || $n === '' ? '' : (string) $n;
        }

        return $v === null || $v === '' ? '' : (string) $v;
    }

    private static function mapCondition(string $raw): string
    {
        return match (trim($raw)) {
            '11', '1000', 'New' => 'New',
            '1', 'Used' => 'Used',
            default => $raw !== '' ? $raw : 'New',
        };
    }

    public static function descriptionMaster(string $sku): string
    {
        return self::descriptionMasterFromMetrics($sku);
    }

    /**
     * Live Shopify product description (cached description_html only as fallback).
     */
    public static function shopifyDescription(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }

        try {
            $res = app(ShopifyApiService::class)->fetchProductDescriptionHtml($sku);
            if (($res['success'] ?? false) && trim((string) ($res['html'] ?? '')) !== '') {
                $html = trim((string) $res['html']);
                if (Schema::hasTable('product_master') && Schema::hasColumn('product_master', 'description_html')) {
                    DB::table('product_master')->where('sku', $sku)->update(['description_html' => $html]);
                }

                return $html;
            }
        } catch (\Throwable $e) {
            Log::warning('ListingManager Shopify description fetch failed: '.$e->getMessage(), [
                'sku' => $sku,
            ]);
        }

        $pm = self::productMaster($sku);

        return trim((string) ($pm['description_html'] ?? ''));
    }

    /**
     * Amazon listing / A+ description for this SKU (not Shopify, not Description Master).
     */
    public static function amazonDescription(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }
        $listing = AmazonListingRaw::query()->where('seller_sku', $sku)->first();
        $raw = is_array($listing?->raw_data) ? $listing->raw_data : [];
        $pm = self::productMaster($sku);
        $html = self::firstNonEmpty([
            $listing?->product_description,
            self::rawGet($raw, 'item-description', 'item_description', 'description'),
            $pm['amazon_aplus_content'] ?? null,
        ]);
        if ($html !== '') {
            return $html;
        }
        $bullets = self::bullets($listing?->bullet_point);
        if ($bullets !== []) {
            return '<ul><li>'.implode('</li><li>', array_map('e', $bullets)).'</li></ul>';
        }

        return '';
    }

    private static function descriptionMasterFromMetrics(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }

        foreach (\App\Services\Support\ProductMasterMarketplaceMaps::descriptionTableMap() as $table) {
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'description_master')) {
                    continue;
                }
                $raw = DB::table($table)
                    ->where('sku', $sku)
                    ->whereNotNull('description_master')
                    ->where('description_master', '!=', '')
                    ->value('description_master');
                $text = trim((string) $raw);
                if ($text !== '') {
                    return $text;
                }
            } catch (\Throwable) {
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private static function imageMasterFromMetrics(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $images = [];
        foreach (\App\Services\Support\ProductMasterMarketplaceMaps::metricsTableMap() as $table) {
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'image_master_json')) {
                    continue;
                }
                $raw = DB::table($table)->where('sku', $sku)->value('image_master_json');
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                if (! is_array($decoded)) {
                    continue;
                }
                foreach ($decoded as $item) {
                    $url = is_array($item)
                        ? trim((string) ($item['url'] ?? $item['link'] ?? ''))
                        : trim((string) $item);
                    $ok = $url !== '' && (
                        preg_match('#^https?://#i', $url)
                        || ListingManagerImageStore::isStored($url)
                        || str_starts_with($url, '/storage/')
                        || preg_match('#^(products|product_images|image_master)/#i', ltrim($url, '/'))
                        || preg_match('/\.(jpe?g|png|webp|gif|avif)(\?|$)/i', $url)
                    );
                    if ($ok && ! in_array($url, $images, true)) {
                        $images[] = $url;
                    }
                }
                if ($images !== []) {
                    return $images;
                }
            } catch (\Throwable) {
            }
        }

        return $images;
    }
}
