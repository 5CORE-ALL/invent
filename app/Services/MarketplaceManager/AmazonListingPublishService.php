<?php

namespace App\Services\MarketplaceManager;

use App\Services\AmazonSpApiService;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\ListingManagerPublishStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create/update an Amazon listing via SP-API Listings Items (title, stock, images, package).
 */
class AmazonListingPublishService
{
    public function __construct(private AmazonSpApiService $api)
    {
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{success: bool, message: string, goods_id?: string|null, skus?: list<string>}
     */
    public function publishSku(string $sku, array $details = [], ?string $title = null, ?int $quantity = null): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }
        if (! $this->api->isConfigured()) {
            return ['success' => false, 'message' => 'Amazon SP-API is not connected. Set Amazon client id, secret, refresh token, and seller id.'];
        }

        $title = trim((string) ($title ?? $details['title'] ?? ''));
        $qty = $quantity;
        if ($qty === null && array_key_exists('quantity', $details) && $details['quantity'] !== null && $details['quantity'] !== '') {
            $qty = (int) $details['quantity'];
        }
        $images = $this->httpsImages(is_array($details['images'] ?? null) ? $details['images'] : []);
        foreach (is_array($details['image_source_urls'] ?? null) ? $details['image_source_urls'] : [] as $url) {
            $url = trim((string) $url);
            if ($url !== '' && preg_match('#^https://#i', $url) && ! in_array($url, $images, true)) {
                $images[] = $url;
            }
        }
        foreach (ListingManagerAmazonHydrator::publishImageUrls($sku, null, 9) as $url) {
            $url = trim((string) $url);
            if ($url !== '' && preg_match('#^https://#i', $url) && ! in_array($url, $images, true)) {
                $images[] = $url;
            }
        }
        $images = array_slice($images, 0, 9);

        ListingManagerPublishStatus::forgetAmazonLiveCache($sku);
        $inspect = $this->api->inspectSellerCentralListing($sku);
        $productType = trim((string) ($details['product_type'] ?? $details['category'] ?? ''));
        $createdAsin = '';
        if (! ($inspect['found'] ?? false)) {
            $created = $this->createListing($sku, $details, $title, $qty, $images);
            if (! ($created['success'] ?? false)) {
                return $created;
            }
            $createdAsin = AmazonSpApiService::normalizeAsin($created['asin'] ?? '');
        }
        $asin = AmazonSpApiService::normalizeAsin($inspect['asin'] ?? '')
            ?: $createdAsin
            ?: $this->asinFromDetails($details);
        if ($asin === '') {
            $inspect = $this->waitForSellerCentralListing($sku);
            $asin = AmazonSpApiService::normalizeAsin($inspect['asin'] ?? '')
                ?: $createdAsin
                ?: $this->asinFromDetails($details);
        }
        $existingSku = trim((string) ($inspect['seller_sku'] ?? $sku));
        if ($existingSku === '') {
            $existingSku = $sku;
        }

        $offer = $this->completeUsListing($existingSku, $details, $title, $qty, $images, $asin);
        if (! ($offer['success'] ?? false)) {
            return $offer;
        }

        $ok = ['US offer'];
        $fail = [];

        if ($title !== '') {
            $res = $this->api->updateTitle($existingSku, $title);
            if ($res['success'] ?? false) {
                $ok[] = 'title';
            } else {
                $fail[] = 'title: '.($res['message'] ?? 'update failed');
            }
        }

        if ($qty !== null) {
            $res = $this->api->updateInventoryBySku($existingSku, max(0, $qty));
            if ($res['success'] ?? false) {
                $ok[] = 'quantity';
            } else {
                $fail[] = 'quantity: '.($res['message'] ?? 'update failed');
            }
        }

        if ($images !== []) {
            $res = $this->api->updateListingImages($existingSku, $images);
            if ($res['success'] ?? false) {
                $ok[] = 'images';
            } else {
                $fail[] = 'images: '.($res['message'] ?? 'update failed');
            }
        }

        $this->api->disableNonUsMarketplaceOffers($existingSku, $productType);
        ListingManagerPublishStatus::forgetAmazonLiveCache($sku);

        return [
            'success' => true,
            'message' => 'Published Amazon listing for '.$sku
                .($asin !== '' ? ' (ASIN '.$asin : ' (SKU '.$existingSku)
                .', '.implode(', ', $ok)
                .'). Only the US offer is enabled.'
                .($fail !== [] ? ' '.implode(' ', $fail) : ''),
            'goods_id' => $asin,
            'skus' => [$sku],
        ];
    }

    /**
     * @return array{checked: bool, found: bool, seller_sku?: string, asin?: string, message?: string}
     */
    private function waitForSellerCentralListing(string $sku): array
    {
        $last = ['checked' => true, 'found' => false];
        for ($i = 0; $i < 6; $i++) {
            if ($i > 0) {
                sleep(2);
            }
            $last = $this->api->inspectSellerCentralListing($sku);
            if (AmazonSpApiService::normalizeAsin($last['asin'] ?? '') !== '') {
                return $last;
            }
        }

        return $last;
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $images
     * @return array{success: bool, message: string, skus?: list<string>}
     */
    private function createListing(string $sku, array $details, string $title, ?int $qty, array $images): array
    {
        $productType = trim((string) ($details['product_type'] ?? $details['category'] ?? $details['primary_category_id'] ?? ''));
        if ($productType === '' || preg_match('/^\d+$/', $productType)) {
            return [
                'success' => false,
                'message' => 'Amazon has no listing for '.$sku.' yet. Open the Product Type tab, enter the Amazon product type, fill Packaging, then Save & Publish. The app cannot mark this Active until Seller Central has the SKU.',
                'skus' => [$sku],
            ];
        }
        if ($title === '') {
            return ['success' => false, 'message' => 'Title is required to create an Amazon listing. Fill the Title & Description tab.', 'skus' => [$sku]];
        }
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one HTTPS image is required to create an Amazon listing. Use the Images tab.', 'skus' => [$sku]];
        }

        $length = (float) ($details['package_length'] ?? 0);
        $width = (float) ($details['package_width'] ?? 0);
        $height = (float) ($details['package_height'] ?? 0);
        $lb = (float) ($details['package_weight_lb'] ?? 0);
        $oz = (float) ($details['package_weight_oz'] ?? 0);
        $weightLb = $lb + ($oz / 16);
        if ($length <= 0 || $width <= 0 || $height <= 0 || $weightLb <= 0) {
            return [
                'success' => false,
                'message' => 'Amazon will not create '.$sku.' without package size and weight. Open the Packaging tab and load Dim/Wt Master.',
                'skus' => [$sku],
            ];
        }

        $attributes = $this->usListingAttributes($sku, $details, $title, $qty, $images);

        $result = $this->api->putListingsItem($sku, $productType, $attributes, 'LISTING');
        $result['skus'] = [$sku];

        return $result;
    }

    /**
     * After Amazon assigns an ASIN, send item/package size plus the US offer.
     *
     * @param  array<string, mixed>  $details
     * @param  list<string>  $images
     * @return array{success: bool, message: string, skus?: list<string>}
     */
    private function completeUsListing(string $sku, array $details, string $title, ?int $qty, array $images, string $asin): array
    {
        $productType = trim((string) ($details['product_type'] ?? $details['category'] ?? $details['primary_category_id'] ?? ''));
        if ($productType === '' || preg_match('/^\d+$/', $productType)) {
            return [
                'success' => false,
                'message' => 'Amazon product type is required to add the US offer for '.$sku.'.',
                'skus' => [$sku],
            ];
        }

        $attributes = $this->usListingAttributes($sku, $details, $title, $qty, $images);
        $asin = trim($asin);
        if ($asin !== '') {
            $attributes['merchant_suggested_asin'] = [[
                'value' => $asin,
                'marketplace_id' => 'ATVPDKIKX0DER',
            ]];
        }
        if (! isset($attributes['purchasable_offer'])) {
            return [
                'success' => false,
                'message' => 'Price is required to add the US offer for '.$sku.'.',
                'skus' => [$sku],
            ];
        }

        $listing = $this->api->putListingsItem($sku, $productType, $attributes, 'LISTING');
        if ($listing['success'] ?? false) {
            $listing['skus'] = [$sku];

            return $listing;
        }

        $this->api->putListingsItem($sku, $productType, self::sizeAttributesFromDetails($details) + (
            $asin !== '' ? ['merchant_suggested_asin' => $attributes['merchant_suggested_asin']] : []
        ), 'LISTING_PRODUCT_ONLY');

        $offer = $this->api->putListingsItem($sku, $productType, self::usOfferAttributes($sku, $details, $qty, $asin), 'LISTING_OFFER_ONLY');
        $offer['skus'] = [$sku];
        if ($offer['success'] ?? false) {
            return $offer;
        }

        $listing['skus'] = [$sku];
        $listing['message'] = trim(implode(' ', array_filter([
            trim((string) ($listing['message'] ?? '')),
            trim((string) ($offer['message'] ?? '')),
        ]))) ?: ('Amazon did not accept the US offer for '.$sku.'.');

        return $listing;
    }

    /**
     * Price, quantity, handling time, and shipping template for the US marketplace.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public static function usOfferAttributes(string $sku, array $details, ?int $qty = null, string $asin = ''): array
    {
        $mp = 'ATVPDKIKX0DER';
        $price = (float) ($details['price'] ?? 0);
        $listPrice = (float) ($details['list_price'] ?? 0);
        if ($listPrice <= 0) {
            $listPrice = $price;
        }
        $quantity = max(0, (int) ($qty ?? $details['quantity'] ?? 0));
        $handling = self::handlingDays($details);
        $shippingGroup = self::shippingGroup($details);

        $attributes = [
            'condition_type' => [[
                'value' => 'new_new',
                'marketplace_id' => $mp,
            ]],
            'fulfillment_availability' => [[
                'fulfillment_channel_code' => 'DEFAULT',
                'quantity' => $quantity,
                'lead_time_to_ship_max_days' => $handling,
                'marketplace_id' => $mp,
            ]],
        ];

        if ($price > 0) {
            $attributes['purchasable_offer'] = [[
                'marketplace_id' => $mp,
                'marketplaceId' => $mp,
                'currency' => 'USD',
                'audience' => 'ALL',
                'our_price' => [[
                    'schedule' => [['value_with_tax' => round($price, 2)]],
                ]],
            ]];
        }
        if ($listPrice > 0) {
            $attributes['list_price'] = [[
                'currency' => 'USD',
                'value' => round($listPrice, 2),
                'value_with_tax' => round($listPrice, 2),
                'marketplace_id' => $mp,
            ]];
        }
        $asin = trim($asin);
        if ($asin !== '') {
            $attributes['merchant_suggested_asin'] = [[
                'value' => $asin,
                'marketplace_id' => $mp,
            ]];
        }
        $attributes['merchant_shipping_group'] = [[
            'value' => $shippingGroup !== '' ? $shippingGroup : 'legacy-template-id',
            'marketplace_id' => $mp,
        ]];

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function handlingDays(array $details): int
    {
        foreach (['handling_time', 'lead_time_to_ship_max_days', 'handling_days'] as $key) {
            if (! array_key_exists($key, $details) || $details[$key] === '' || $details[$key] === null) {
                continue;
            }
            $days = (int) $details[$key];
            if ($days >= 0 && $days <= 30) {
                return max(1, $days);
            }
        }

        return 2;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function shippingGroup(array $details): string
    {
        foreach (['merchant_shipping_group', 'shipping_template', 'shipping_group'] as $key) {
            $value = trim((string) ($details[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $configured = trim((string) config('listing_manager.amazon_default_shipping_group', ''));
        if ($configured !== '') {
            return $configured;
        }

        try {
            $fromDb = (string) Cache::remember('lm.amazon.default_shipping_group', 3600, static function (): string {
                if (! Schema::hasTable('amazon_listings_raw')) {
                    return '';
                }

                $row = DB::table('amazon_listings_raw')
                    ->whereNotNull('merchant_shipping_group')
                    ->where('merchant_shipping_group', '!=', '')
                    ->select('merchant_shipping_group')
                    ->groupBy('merchant_shipping_group')
                    ->orderByRaw('COUNT(*) DESC')
                    ->limit(1)
                    ->value('merchant_shipping_group');

                return trim((string) $row);
            });
            if ($fromDb !== '') {
                return $fromDb;
            }
        } catch (\Throwable) {
            // Amazon's default migrated template.
        }

        return 'legacy-template-id';
    }

    /**
     * Amazon requires item size in addition to package size. The app only stores package Dim/Wt.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public static function sizeAttributesFromDetails(array $details): array
    {
        $length = (float) ($details['item_length'] ?? $details['package_length'] ?? 0);
        $width = (float) ($details['item_width'] ?? $details['package_width'] ?? 0);
        $height = (float) ($details['item_height'] ?? $details['package_height'] ?? 0);
        $lb = (float) ($details['item_weight_lb'] ?? $details['package_weight_lb'] ?? 0);
        $oz = (float) ($details['item_weight_oz'] ?? $details['package_weight_oz'] ?? 0);
        $weightLb = $lb + ($oz / 16);
        if ($weightLb <= 0) {
            $weightLb = (float) ($details['item_weight'] ?? 0);
        }

        return self::sizeAttributes($length, $width, $height, $weightLb);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sizeAttributes(float $length, float $width, float $height, float $weightLb, string $mp = 'ATVPDKIKX0DER'): array
    {
        $attributes = [];
        if ($length > 0 && $width > 0 && $height > 0) {
            $box = [[
                'length' => ['value' => $length, 'unit' => 'inches'],
                'width' => ['value' => $width, 'unit' => 'inches'],
                'height' => ['value' => $height, 'unit' => 'inches'],
                'marketplace_id' => $mp,
            ]];
            $attributes['item_package_dimensions'] = $box;
            $attributes['item_length_width_height'] = $box;
            $attributes['item_dimensions'] = $box;
            $attributes['item_display_dimensions'] = $box;
        }
        if ($weightLb > 0) {
            $weight = [[
                'value' => round($weightLb, 3),
                'unit' => 'pounds',
                'marketplace_id' => $mp,
            ]];
            $attributes['item_package_weight'] = $weight;
            $attributes['item_weight'] = $weight;
            $attributes['item_display_weight'] = $weight;
            $attributes['website_shipping_weight'] = $weight;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $images
     * @return array<string, mixed>
     */
    private function usListingAttributes(string $sku, array $details, string $title, ?int $qty, array $images): array
    {
        $mp = 'ATVPDKIKX0DER';
        $brand = self::displayBrand($details);
        $manufacturer = self::displayManufacturer($details);
        $upc = trim((string) ($details['upc'] ?? ''));
        $price = (float) ($details['price'] ?? 0);
        $listPrice = (float) ($details['list_price'] ?? 0);
        if ($listPrice <= 0) {
            $listPrice = $price;
        }
        $quantity = max(0, (int) ($qty ?? $details['quantity'] ?? 0));
        $description = trim((string) ($details['description'] ?? $title));
        $color = ListingManagerAmazonHydrator::colorFromSku($sku, (string) ($details['color'] ?? ''));
        $origin = ListingManagerAmazonHydrator::amazonCountryOfOrigin((string) ($details['country_of_origin'] ?? ''));
        $dgr = ListingManagerAmazonHydrator::amazonDangerousGoods((string) ($details['dangerous_goods_regulations'] ?? ''));
        $bullets = $this->bulletPointsFromDetails($details, $title);

        $attr = static function (mixed $value) use ($mp): array {
            return [['value' => $value, 'marketplace_id' => $mp]];
        };
        $text = static function (string $value) use ($mp): array {
            return [['value' => $value, 'language_tag' => 'en_US', 'marketplace_id' => $mp]];
        };

        $attributes = [
            'item_name' => $attr($title),
            'brand' => $attr($brand),
            'manufacturer' => $attr($manufacturer),
            'part_number' => $attr($sku),
            'product_description' => $attr($description),
            'condition_type' => $attr('new_new'),
            'color' => $text($color),
            'country_of_origin' => $attr($origin),
            'dangerous_goods_regulations' => $attr($dgr),
            'supplier_declared_dg_hz_regulation' => $attr($dgr),
            'batteries_required' => $attr(false),
            'bullet_point' => array_map(static fn (string $line) => [
                'value' => $line,
                'language_tag' => 'en_US',
                'marketplace_id' => $mp,
            ], $bullets),
            'fulfillment_availability' => [[
                'fulfillment_channel_code' => 'DEFAULT',
                'quantity' => $quantity,
                'lead_time_to_ship_max_days' => self::handlingDays($details),
                'marketplace_id' => $mp,
            ]],
        ];

        $attributes = array_merge($attributes, self::sizeAttributesFromDetails($details));
        if ($price > 0) {
            $attributes['purchasable_offer'] = [[
                'marketplace_id' => $mp,
                'marketplaceId' => $mp,
                'currency' => 'USD',
                'audience' => 'ALL',
                'our_price' => [[
                    'schedule' => [['value_with_tax' => round($price, 2)]],
                ]],
            ]];
        }
        $shippingGroup = self::shippingGroup($details);
        $attributes['merchant_shipping_group'] = [[
            'value' => $shippingGroup !== '' ? $shippingGroup : 'legacy-template-id',
            'marketplace_id' => $mp,
        ]];
        if ($listPrice > 0) {
            $attributes['list_price'] = [[
                'currency' => 'USD',
                'value' => round($listPrice, 2),
                'value_with_tax' => round($listPrice, 2),
                'marketplace_id' => $mp,
            ]];
        }
        foreach (array_values($images) as $i => $url) {
            $key = $i === 0 ? 'main_product_image_locator' : 'other_product_image_locator_'.$i;
            $attributes[$key] = [[
                'media_location' => $url,
                'marketplace_id' => $mp,
            ]];
        }
        if ($upc !== '' && ! preg_match('/^B0/i', $upc)) {
            $attributes['externally_assigned_product_identifier'] = [[
                'type' => 'upc',
                'value' => $upc,
                'marketplace_id' => $mp,
            ]];
        } else {
            $attributes['supplier_declared_has_product_identifier_exemption'] = $attr(true);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function asinFromDetails(array $details): string
    {
        foreach (['asin', 'amazon_asin', 'external_listing_id'] as $key) {
            $asin = AmazonSpApiService::normalizeAsin($details[$key] ?? '');
            if ($asin !== '') {
                return $asin;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function displayBrand(array $details): string
    {
        $brand = trim((string) ($details['brand'] ?? $details['vendor'] ?? ''));
        if ($brand === '') {
            $brand = trim((string) config('listing_manager.default_brand', '5 Core'));
        }

        return self::withoutIncSuffix($brand) ?: '5 Core';
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function displayManufacturer(array $details): string
    {
        $manufacturer = trim((string) ($details['manufacturer'] ?? ''));
        if ($manufacturer === '') {
            $manufacturer = self::displayBrand($details);
        }

        return self::withoutIncSuffix($manufacturer) ?: '5 Core';
    }

    public static function withoutIncSuffix(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+Inc\.?$/i', '', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param  list<mixed>  $urls
     * @return list<string>
     */
    private function httpsImages(array $urls): array
    {
        $out = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url !== '' && preg_match('#^https://#i', $url) && ! in_array($url, $out, true)) {
                $out[] = $url;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $details
     * @return list<string>
     */
    private function bulletPointsFromDetails(array $details, string $title): array
    {
        $lines = [];
        foreach (['bullet_1', 'bullet_2', 'bullet_3', 'bullet_4', 'bullet_5'] as $key) {
            $line = trim((string) ($details[$key] ?? ''));
            if ($line !== '' && ! in_array($line, $lines, true)) {
                $lines[] = mb_substr($line, 0, 500);
            }
        }
        if ($lines === []) {
            $blob = trim((string) ($details['bullets'] ?? ''));
            foreach (preg_split('/\r\n|\r|\n/', $blob) ?: [] as $line) {
                $line = trim((string) $line);
                if ($line !== '' && ! in_array($line, $lines, true)) {
                    $lines[] = mb_substr($line, 0, 500);
                }
            }
        }
        if ($lines === [] && $title !== '') {
            $lines[] = mb_substr($title, 0, 500);
        }

        return array_slice($lines, 0, 5);
    }
}
