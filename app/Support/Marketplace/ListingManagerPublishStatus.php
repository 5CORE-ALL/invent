<?php

namespace App\Support\Marketplace;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\Temu2Metric;
use App\Models\TemuMetric;
use Illuminate\Support\Facades\Schema;

/**
 * Detect whether a draft SKU is already live on a marketplace
 * and validate LitCommerce-style required listing fields.
 */
class ListingManagerPublishStatus
{
    /**
     * @return array{listed: bool, listing_id: string|null, source: string}
     */
    public static function check(string $channelName, string $sku): array
    {
        $sku = trim($sku);
        $key = ListingChannelCounts::normalize($channelName);
        if ($sku === '' || $key === '') {
            return ['listed' => false, 'listing_id' => null, 'source' => 'none'];
        }

        if (in_array($key, ['ebay2', 'ebaytwo'], true) && Schema::hasTable('ebay_2_metrics')) {
            $id = self::idFromColumn(Ebay2Metric::class, $sku, 'item_id', true);

            return ['listed' => $id !== null, 'listing_id' => $id, 'source' => 'ebay_2_metrics.item_id'];
        }
        if (in_array($key, ['ebay', 'ebay1', 'ebayone'], true) && class_exists(EbayMetric::class)) {
            $id = self::idFromColumn(EbayMetric::class, $sku, 'item_id', true);

            return ['listed' => $id !== null, 'listing_id' => $id, 'source' => 'ebay_metrics.item_id'];
        }
        if (in_array($key, ['ebay3', 'ebaythree'], true) && class_exists(Ebay3Metric::class)) {
            $id = self::idFromColumn(Ebay3Metric::class, $sku, 'item_id', true);

            return ['listed' => $id !== null, 'listing_id' => $id, 'source' => 'ebay_3_metrics.item_id'];
        }
        if ($key === 'temu' && class_exists(TemuMetric::class)) {
            $id = self::idFromColumn(TemuMetric::class, $sku, 'goods_id', true);

            return ['listed' => $id !== null, 'listing_id' => $id, 'source' => 'temu_metrics.goods_id'];
        }
        if (in_array($key, ['temu2', 'temutwo'], true) && class_exists(Temu2Metric::class)) {
            $id = self::idFromColumn(Temu2Metric::class, $sku, 'goods_id', true);

            return ['listed' => $id !== null, 'listing_id' => $id, 'source' => 'temu2_metrics.goods_id'];
        }

        $cfg = ChannelListingRegistry::get($key)
            ?? ChannelListingRegistry::get(match ($key) {
                'ebay3' => 'ebaythree',
                'tiktok' => 'tiktokshop',
                'tiktok2' => 'tiktokshop2',
                'bestbuy' => 'bestbuyusa',
                'facebookmarketplace' => 'fbmarketplace',
                'shopify' => 'shopifyb2c',
                default => $key,
            });

        if ($cfg !== null) {
            $map = ChannelListingRegistry::loadListedIds($cfg, [$sku]);
            $id = trim((string) ($map[strtolower($sku)] ?? ''));
            if ($id !== '') {
                return [
                    'listed' => true,
                    'listing_id' => $id,
                    'source' => 'channel_registry',
                ];
            }
        }

        return ['listed' => false, 'listing_id' => null, 'source' => 'none'];
    }

    /**
     * @param  class-string  $model
     */
    private static function idFromColumn(string $model, string $sku, string $column, bool $rejectSku): ?string
    {
        $row = $model::query()->where('sku', $sku)->orderByDesc('id')->first([$column, 'sku']);
        if (! $row) {
            return null;
        }
        $id = trim((string) ($row->{$column} ?? ''));
        if ($id === '') {
            return null;
        }
        if ($rejectSku && strcasecmp($id, $sku) === 0) {
            return null;
        }

        return $id;
    }

    /**
     * LitCommerce-style readiness with per-tab errors.
     *
     * @param  array<string, mixed>|null  $details
     * @return array{
     *   ready: bool,
     *   missing: list<string>,
     *   tab_errors: array<string, list<string>>,
     *   ui_status: string,
     *   banners: list<string>
     * }
     */
    public static function readiness(
        ?string $title,
        mixed $price,
        mixed $quantity,
        ?array $details,
        string $status = 'draft',
        ?string $channelName = null
    ): array {
        $details = is_array($details) ? $details : [];
        $limits = ListingManagerAmazonHydrator::limitsForChannel($channelName);
        $titleLimit = max(1, (int) ($limits['title'] ?? 80));
        $descLimit = max(1, (int) ($limits['description'] ?? 500000));

        $tabErrors = [
            'identifiers' => [],
            'title_description' => [],
            'images' => [],
            'pricing' => [],
            'category' => [],
            'business_policies' => [],
            'auto_relist' => [],
        ];

        $titleTrim = trim((string) $title);
        if ($titleTrim === '') {
            $tabErrors['title_description'][] = 'Title is required.';
        } elseif (mb_strlen($titleTrim) > $titleLimit) {
            $tabErrors['title_description'][] = "Title must be {$titleLimit} characters or less.";
        }

        $desc = trim((string) ($details['description'] ?? ''));
        if ($desc === '') {
            $tabErrors['title_description'][] = 'Description is required.';
        } elseif (mb_strlen($desc) > $descLimit) {
            $tabErrors['title_description'][] = "Description must be {$descLimit} characters or less.";
        }

        $images = $details['images'] ?? [];
        if (! is_array($images)) {
            $images = [];
        }
        $images = array_values(array_filter(array_map(fn ($u) => trim((string) $u), $images)));
        $imageUrl = trim((string) ($details['image_url'] ?? ''));
        if ($images === [] && $imageUrl === '') {
            $tabErrors['images'][] = 'At least one image is required.';
        }

        if ($price === null || $price === '' || (float) $price <= 0) {
            $tabErrors['pricing'][] = 'Price is required.';
        }
        if ($quantity === null || $quantity === '') {
            $tabErrors['pricing'][] = 'Quantity is required.';
        } elseif ((int) $quantity < 0) {
            $tabErrors['pricing'][] = 'Quantity cannot be negative.';
        }

        $categoryId = trim((string) ($details['primary_category_id'] ?? $details['category_id'] ?? ''));
        if ($categoryId === '') {
            $tabErrors['category'][] = 'Primary category is required.';
        }
        $condition = trim((string) ($details['condition'] ?? ''));
        if ($condition === '' || strcasecmp($condition, 'Please select') === 0) {
            $tabErrors['category'][] = 'Condition is required.';
        }

        foreach (['shipping_policy_id' => 'Shipping Policy', 'payment_policy_id' => 'Payment Policy', 'return_policy_id' => 'Return Policy'] as $key => $label) {
            if (trim((string) ($details[$key] ?? '')) === '') {
                $tabErrors['business_policies'][] = "{$label} is required.";
            }
        }
        if (trim((string) ($details['location_country'] ?? '')) === '') {
            $tabErrors['business_policies'][] = 'Item location country is required.';
        }
        if (trim((string) ($details['location_postal_code'] ?? '')) === '') {
            $tabErrors['business_policies'][] = 'Postal code is required.';
        }

        $missing = [];
        $banners = [];
        foreach ($tabErrors as $tab => $errors) {
            if ($errors === []) {
                continue;
            }
            $missing = array_merge($missing, $errors);
            $label = match ($tab) {
                'title_description' => 'Title & Description',
                'business_policies' => 'Business Policies',
                'auto_relist' => 'Auto Relist',
                default => ucfirst(str_replace('_', ' ', $tab)),
            };
            $banners[] = "{$label} tab is missing required information. Please fill in those required fields.";
        }

        $ready = $missing === [];
        $uiStatus = 'Missing Info';
        if (in_array($status, ['listed', 'active'], true)) {
            $uiStatus = 'Active';
        } elseif ($ready) {
            $uiStatus = 'Ready';
        }

        return [
            'ready' => $ready,
            'missing' => $missing,
            'tab_errors' => $tabErrors,
            'ui_status' => $uiStatus,
            'banners' => $banners,
        ];
    }

    /**
     * Normalize draft listing_details to LitCommerce field set.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public static function normalizeDetails(array $details): array
    {
        $images = $details['images'] ?? [];
        if (! is_array($images)) {
            $images = [];
        }
        $imageUrl = trim((string) ($details['image_url'] ?? ''));
        if ($imageUrl !== '' && $images === []) {
            $images = [$imageUrl];
        }
        $images = array_values(array_filter(array_map(fn ($u) => trim((string) $u), $images)));
        if ($images !== [] && $imageUrl === '') {
            $imageUrl = $images[0];
        }

        $specifics = $details['item_specifics'] ?? [];
        if (! is_array($specifics)) {
            $specifics = [];
        }
        $defaultBrand = trim((string) config('listing_manager.default_brand', '5 Core')) ?: '5 Core';
        $brand = trim((string) ($details['brand'] ?? ($specifics['Brand'] ?? ''))) ?: $defaultBrand;
        $mpn = trim((string) ($details['mpn'] ?? ($specifics['MPN'] ?? '')));
        $upc = trim((string) ($details['upc'] ?? ($specifics['UPC'] ?? '')));
        if ($brand !== '') {
            $specifics['Brand'] = $brand;
        }
        if ($mpn !== '') {
            $specifics['MPN'] = $mpn;
        }
        if ($upc !== '') {
            $specifics['UPC'] = $upc;
        }

        $defaults = (array) config('listing_manager.ebay2_defaults', []);
        $merged = array_merge([
            'description' => '',
            'condition' => 'New',
            'condition_id' => '1000',
            'condition_description' => '',
            'brand' => '',
            'mpn' => '',
            'upc' => '',
            'ean' => '',
            'isbn' => '',
            'epid' => '',
            'category' => '',
            'primary_category_id' => '',
            'primary_category_path' => '',
            'secondary_category_id' => '',
            'listing_format' => 'FixedPriceItem',
            'duration' => 'GTC',
            'image_url' => '',
            'images' => [],
            'bullet_1' => '',
            'bullet_2' => '',
            'bullet_3' => '',
            'bullet_4' => '',
            'bullet_5' => '',
            'item_specifics' => [],
            'location_city' => '',
            'location_country' => '',
            'location_postal_code' => '',
            'shipping_policy_id' => '',
            'payment_policy_id' => '',
            'return_policy_id' => '',
            'package_length' => '',
            'package_width' => '',
            'package_height' => '',
            'package_weight_lb' => '',
            'package_weight_oz' => '',
            'vat_percent' => '',
            'gallery_plus' => false,
            'best_offer' => false,
            'auto_relist' => false,
            'private_listing' => false,
        ], $details, [
            'brand' => $brand,
            'mpn' => $mpn,
            'upc' => $upc,
            'image_url' => $imageUrl,
            'images' => $images,
            'item_specifics' => $specifics,
            'condition_id' => self::conditionId(trim((string) ($details['condition'] ?? 'New'))),
        ]);

        foreach ([
            'location_city' => 'location_city',
            'location_country' => 'location_country',
            'location_postal_code' => 'location_postal_code',
            'shipping_policy_id' => 'shipping_policy_id',
            'payment_policy_id' => 'payment_policy_id',
            'return_policy_id' => 'return_policy_id',
        ] as $field => $cfgKey) {
            if (trim((string) ($merged[$field] ?? '')) === '' && trim((string) ($defaults[$cfgKey] ?? '')) !== '') {
                $merged[$field] = $defaults[$cfgKey];
            }
        }

        return $merged;
    }

    public static function conditionId(string $condition): string
    {
        $map = [
            'new' => '1000',
            'new other' => '1500',
            'new with defects' => '1750',
            'manufacturer refurbished' => '2000',
            'seller refurbished' => '2500',
            'refurbished' => '2500',
            'used' => '3000',
            'used excellent' => '3000',
            'for parts or not working' => '7000',
        ];

        return $map[strtolower(trim($condition))] ?? '1000';
    }
}
