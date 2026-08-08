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
            $mainStore['title'] ?? null,
            $listing?->item_name,
            self::rawGet($raw, 'item-name', 'item_name', 'title'),
            $pm['title80'] ?? null,
            $pm['title100'] ?? null,
            $pm['title150'] ?? null,
            $sku,
        ]);

        $description = self::firstNonEmpty([
            $mainStore['html'] ?? null,
            $pm['description_html'] ?? null,
            $pm['amazon_aplus_content'] ?? null,
            $listing?->product_description,
            self::rawGet($raw, 'item-description', 'item_description', 'description'),
            $pm['product_description'] ?? null,
            $pm['description_1500'] ?? null,
            $pm['description_v2_description'] ?? null,
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
        $quantity = $qtyRaw !== '' ? (int) $qtyRaw : null;

        $images = self::collectImages($listing, $raw, $pm, $sku);
        foreach (($mainStore['images'] ?? []) as $u) {
            $u = trim((string) $u);
            if ($u !== '' && ! in_array($u, $images, true)) {
                $images[] = $u;
            }
        }
        $dims = self::dimensions($listing?->item_dimensions, $pm);

        $defaultBrand = trim((string) config('listing_manager.default_brand', '5 Core')) ?: '5 Core';
        $brand = self::firstNonEmpty([
            $listing?->brand,
            $pm['brand'] ?? null,
            self::rawGet($raw, 'brand'),
            $defaultBrand,
        ]) ?: $defaultBrand;

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
        if (in_array($key, ['ebay2', 'ebaytwo'], true)) {
            $defaults = (array) config('listing_manager.ebay2_defaults', []);
        }

        $defaultBrand = trim((string) config('listing_manager.default_brand', '5 Core')) ?: '5 Core';
        $sku = trim((string) ($hydrated['sku'] ?? $hydrated['mpn'] ?? ''));
        $brand = trim((string) ($hydrated['brand'] ?: ($existingDetails['brand'] ?? ''))) ?: $defaultBrand;
        $mpn = trim((string) ($existingDetails['mpn'] ?? '')) ?: $sku;
        $upc = trim((string) ($existingDetails['upc'] ?? '')) ?: trim((string) ($hydrated['upc'] ?? ''));
        if ($upc === '' && $sku !== '') {
            $upc = self::upcFromCpMaster($sku);
        }

        $existingSpecifics = is_array($existingDetails['item_specifics'] ?? null)
            ? $existingDetails['item_specifics']
            : [];
        $specifics = array_merge($existingSpecifics, array_filter([
            'Brand' => $brand,
            'MPN' => $mpn,
            'UPC' => $upc,
        ]));

        $bullets = $hydrated['bullets'] ?? [];
        $merged = array_merge($existingDetails, [
            'description' => $hydrated['description'] ?: ($existingDetails['description'] ?? ''),
            'condition' => $hydrated['condition'] ?: ($existingDetails['condition'] ?? 'New'),
            'brand' => $brand,
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
            'location_city' => $existingDetails['location_city'] ?? ($defaults['location_city'] ?? 'Bellefontaine'),
            'location_country' => $existingDetails['location_country'] ?? ($defaults['location_country'] ?? 'US'),
            'location_postal_code' => $existingDetails['location_postal_code'] ?? ($defaults['location_postal_code'] ?? '43311'),
            'shipping_policy_id' => $existingDetails['shipping_policy_id'] ?? ($defaults['shipping_policy_id'] ?? ''),
            'payment_policy_id' => $existingDetails['payment_policy_id'] ?? ($defaults['payment_policy_id'] ?? ''),
            'return_policy_id' => $existingDetails['return_policy_id'] ?? ($defaults['return_policy_id'] ?? ''),
            'listing_format' => $existingDetails['listing_format'] ?? 'FixedPriceItem',
            'duration' => $existingDetails['duration'] ?? 'GTC',
        ]);

        return ListingManagerPublishStatus::normalizeDetails($merged);
    }

    /**
     * UPC / barcode from CP Master (product_master).
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
            $code = preg_replace('/\s+/', '', trim((string) ($v ?? ''))) ?? '';
            if ($code === '' || $code === '-' || in_array($code, $candidates, true)) {
                return;
            }
            $candidates[] = $code;
        };

        $push($pm['barcode'] ?? null);
        if (Schema::hasTable('product_master') && Schema::hasColumn('product_master', 'upc')) {
            $push($pm['upc'] ?? null);
        }

        $values = $pm['Values'] ?? null;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        }
        if (is_array($values)) {
            foreach (['upc', 'UPC', 'gtin', 'GTIN', 'ean', 'EAN', 'barcode', 'Barcode'] as $key) {
                $push($values[$key] ?? null);
            }
        }

        // Fallbacks only if CP Master empty
        $push($listing?->external_product_id);
        $push(self::rawGet($raw, 'product-id', 'product_id', 'upc'));

        foreach ($candidates as $code) {
            // Prefer numeric UPC/EAN/GTIN lengths
            $digits = preg_replace('/\D/', '', $code) ?? '';
            if (in_array(strlen($digits), [8, 12, 13, 14], true)) {
                return $digits;
            }
        }

        return $candidates[0] ?? '';
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
            $url = trim((string) $url);
            if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                return;
            }
            if (! in_array($url, $images, true)) {
                $images[] = $url;
            }
        };

        $push($listing?->thumbnail_image);
        $push(self::rawGet($raw, 'image-url', 'image_url', 'main_image'));

        foreach (['main_image', 'image_url', 'main_image_brand'] as $k) {
            $push($pm[$k] ?? null);
        }
        for ($i = 1; $i <= 20; $i++) {
            $push($pm['image'.$i] ?? null);
        }

        if (Schema::hasTable('amazon_metrics') && Schema::hasColumn('amazon_metrics', 'image_urls')) {
            $row = DB::table('amazon_metrics')->where('sku', $sku)->value('image_urls');
            $decoded = is_string($row) ? json_decode($row, true) : $row;
            if (is_array($decoded)) {
                foreach ($decoded as $u) {
                    if (is_array($u)) {
                        $push($u['url'] ?? $u['link'] ?? null);
                    } else {
                        $push($u);
                    }
                }
            }
        }

        // Do not invent /images/P/{ASIN} placeholders — those often render blank.
        // Live Amazon media is fetched via ListingManagerController::loadDraftImages.

        return $images;
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

        return $out;
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
}
