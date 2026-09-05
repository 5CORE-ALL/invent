<?php

namespace App\Services\MarketplaceManager;

use App\Services\AmazonSpApiService;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\ListingManagerPublishStatus;

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
        $images = [];
        foreach (ListingManagerAmazonHydrator::publishImageUrls($sku, null, 9) as $url) {
            $url = trim((string) $url);
            if ($url !== '' && preg_match('#^https://#i', $url)) {
                $images[] = $url;
            }
        }
        if ($images === [] && is_array($details['images'] ?? null)) {
            foreach ($details['images'] as $url) {
                $url = trim((string) $url);
                if ($url !== '' && preg_match('#^https://#i', $url) && ! in_array($url, $images, true)) {
                    $images[] = $url;
                }
            }
        }

        $existingSku = $this->api->resolveExistingSellerSku($sku);
        if ($existingSku === null) {
            $created = $this->createListing($sku, $details, $title, $qty, $images);
            if (! ($created['success'] ?? false)) {
                return $created;
            }
            $existingSku = $this->api->resolveExistingSellerSku($sku);
            if ($existingSku === null) {
                return [
                    'success' => false,
                    'message' => 'Amazon accepted the submit, but Seller Central still has no SKU '.$sku.'. Open the Product Type and Packaging tabs, fix any Amazon errors, then publish again. A new SKU often stays hidden until product type, package weight, images, and UPC are valid.',
                    'skus' => [$sku],
                ];
            }
        }

        $ok = [];
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

        $confirmed = $this->api->resolveExistingSellerSku($sku);
        $asin = ListingManagerPublishStatus::amazonAsinForSku($sku);
        if ($confirmed === null) {
            return [
                'success' => false,
                'message' => 'Amazon has no listing for '.$sku.'. Fill Product Type and Packaging, add a UPC, then Save & Publish. The app will not mark this Active until the SKU appears in Seller Central.',
                'skus' => [$sku],
            ];
        }

        if ($ok === [] && $fail !== []) {
            return [
                'success' => false,
                'message' => 'Amazon listing update failed. '.implode(' ', $fail),
                'skus' => [$sku],
            ];
        }

        return [
            'success' => true,
            'message' => 'Updated Amazon listing for '.$sku
                .($ok !== [] ? ' ('.implode(', ', $ok).')' : '')
                .'. Confirm it under Manage All Inventory.'
                .($fail !== [] ? ' '.implode(' ', $fail) : ''),
            'goods_id' => $asin ?: $confirmed,
            'skus' => [$sku],
        ];
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

        $mp = 'ATVPDKIKX0DER';
        $brand = trim((string) ($details['brand'] ?? '5 Core Inc.')) ?: '5 Core Inc.';
        $manufacturer = trim((string) ($details['manufacturer'] ?? $brand)) ?: $brand;
        $upc = trim((string) ($details['upc'] ?? ''));
        $price = (float) ($details['price'] ?? 0);
        $quantity = max(0, (int) ($qty ?? $details['quantity'] ?? 0));
        $description = trim((string) ($details['description'] ?? $title));

        $attr = function (mixed $value) use ($mp): array {
            return [['value' => $value, 'marketplace_id' => $mp]];
        };

        $attributes = [
            'item_name' => $attr($title),
            'brand' => $attr($brand),
            'manufacturer' => $attr($manufacturer),
            'part_number' => $attr($sku),
            'product_description' => $attr($description),
            'condition_type' => $attr('new_new'),
            'fulfillment_availability' => [[
                'fulfillment_channel_code' => 'DEFAULT',
                'quantity' => $quantity,
                'marketplace_id' => $mp,
            ]],
            'item_package_dimensions' => [[
                'length' => ['value' => $length, 'unit' => 'inches'],
                'width' => ['value' => $width, 'unit' => 'inches'],
                'height' => ['value' => $height, 'unit' => 'inches'],
                'marketplace_id' => $mp,
            ]],
            'item_package_weight' => [[
                'value' => round($weightLb, 3),
                'unit' => 'pounds',
                'marketplace_id' => $mp,
            ]],
        ];

        if ($price > 0) {
            $attributes['purchasable_offer'] = [[
                'marketplace_id' => $mp,
                'currency' => 'USD',
                'our_price' => [[
                    'schedule' => [['value_with_tax' => round($price, 2)]],
                ]],
            ]];
        }

        foreach (array_values($images) as $i => $url) {
            $key = $i === 0 ? 'main_product_image_locator' : 'other_product_image_locator_'.$i;
            $attributes[$key] = $attr($url);
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

        $result = $this->api->putListingsItem($sku, $productType, $attributes);
        $result['skus'] = [$sku];

        return $result;
    }
}
