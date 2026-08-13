<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonListingStatus;
use App\Models\AmazonOrder;
use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AmazonDetailFormatter
{
    /**
     * @return array<string, mixed>
     */
    public function formatProduct(?AmazonListingStatus $listing, ShopifySku $shopify): array
    {
        $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromRow($shopify);
        $linked = AmazonListingStatusHelper::isLinked($listing, (string) $shopify->sku);
        $value = AmazonListingStatusHelper::valueArray($listing);
        $amazonQty = null;
        if ($linked && Schema::hasTable('product_stock_mappings')) {
            $map = ProductStockMapping::query()->where('sku', $shopify->sku)->first();
            if ($map && $map->inventory_amazon !== null && $map->inventory_amazon !== '') {
                $amazonQty = (int) $map->inventory_amazon;
            }
        }

        return [
            'shopify' => [
                'sku' => $shopify->sku,
                'product_title' => $shopify->product_title,
                'variant_title' => $shopify->variant_title,
                'variant_id' => $shopify->variant_id,
                'product_link' => $shopify->product_link,
                'image' => $shopify->image_src,
                'available_to_sell' => $shopifyQty,
                'on_hand' => $shopify->on_hand,
                'b2c_price' => $shopify->b2c_price ?? $shopify->price,
                'price' => $shopify->price,
            ],
            'link' => [
                'product_id' => $linked ? AmazonListingStatusHelper::resolveProductId($listing) : null,
                'asin' => $linked ? AmazonListingStatusHelper::resolveAsin($listing) : null,
                'title' => $value['title'] ?? null,
                'remaining_inventory' => $amazonQty,
                'link_synced_at' => $listing?->updated_at,
            ],
            'amazon' => [
                'product_id' => $linked ? AmazonListingStatusHelper::resolveProductId($listing) : null,
                'asin' => $linked ? AmazonListingStatusHelper::resolveAsin($listing) : null,
                'title' => $value['title'] ?? null,
                'status' => $linked ? AmazonListingStatusHelper::resolveListingState($listing) : null,
                'stock' => $amazonQty,
                'nr_req' => $value['nr_req'] ?? null,
                'listing_status' => $value['listing_status'] ?? $value['status'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderRoot
     * @param  Collection<int, \App\Models\AmazonOrderItem>  $items
     * @return array<string, mixed>
     */
    public function formatOrder(array $orderRoot, Collection $items, AmazonOrder $order): array
    {
        $addr = is_array($orderRoot['ShippingAddress'] ?? null)
            ? $orderRoot['ShippingAddress']
            : (is_array($orderRoot['shippingAddress'] ?? null) ? $orderRoot['shippingAddress'] : []);
        $buyer = is_array($orderRoot['BuyerInfo'] ?? null)
            ? $orderRoot['BuyerInfo']
            : (is_array($orderRoot['buyerInfo'] ?? null) ? $orderRoot['buyerInfo'] : []);

        $buyerEmail = $buyer['BuyerEmail'] ?? $buyer['buyerEmail'] ?? null;
        $buyerName = $buyer['BuyerName'] ?? $buyer['buyerName'] ?? ($orderRoot['BuyerName'] ?? null);
        $shipName = $addr['Name'] ?? $addr['name'] ?? $buyerName;

        $shippingCost = 0.0;
        $lineItems = [];
        foreach ($items as $item) {
            $itemRaw = AmazonOrder::decodeRawPayload($item->raw_data ?? null);
            $qty = max(1, (int) ($item->quantity ?? 1));
            $itemPrice = (float) (data_get($itemRaw, 'ItemPrice.Amount') ?? 0);
            $itemShip = (float) (data_get($itemRaw, 'ShippingPrice.Amount') ?? 0);
            $promo = (float) (data_get($itemRaw, 'PromotionDiscount.Amount') ?? 0);
            if ($itemPrice <= 0) {
                $stored = (float) ($item->price ?? 0);
                $itemPrice = max(0, $stored - $itemShip);
            } else {
                $itemPrice = max(0, $itemPrice - $promo);
            }
            $shippingCost += $itemShip;
            $lineItems[] = [
                'sku' => (string) ($item->sku ?? ''),
                'asin' => (string) ($item->asin ?? ''),
                'title' => (string) ($item->title ?? $item->sku ?? ''),
                'quantity' => $qty,
                'unit_price' => round($itemPrice / $qty, 2),
                'price' => $itemPrice,
            ];
        }

        return [
            'summary' => [
                'order_id' => (string) ($orderRoot['AmazonOrderId'] ?? $order->amazon_order_id),
                'status' => $orderRoot['OrderStatus'] ?? $order->status,
                'created' => $orderRoot['PurchaseDate'] ?? $order->order_date,
                'fulfillment_channel' => $orderRoot['FulfillmentChannel'] ?? $orderRoot['fulfillmentChannel'] ?? $order->fulfillmentChannel(),
            ],
            'amounts' => [
                'total' => $order->total_amount,
                'currency' => $order->currency ?: 'USD',
                'shipping_cost' => $shippingCost,
            ],
            'buyer' => [
                'email' => $buyerEmail,
                'name' => $buyerName,
            ],
            'shipping' => [
                'recipient' => $shipName,
                'address_line_1' => $addr['AddressLine1'] ?? $addr['addressLine1'] ?? null,
                'address_line_2' => $addr['AddressLine2'] ?? $addr['addressLine2'] ?? null,
                'city' => $addr['City'] ?? $addr['city'] ?? null,
                'province' => $addr['StateOrRegion'] ?? $addr['stateOrRegion'] ?? null,
                'zip' => $addr['PostalCode'] ?? $addr['postalCode'] ?? null,
                'country' => $addr['CountryCode'] ?? $addr['countryCode'] ?? null,
                'phone' => $addr['Phone'] ?? $addr['phone'] ?? null,
                'email' => $buyerEmail,
            ],
            'line_items' => $lineItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  Collection<int, \App\Models\AmazonOrderItem>  $lines
     * @param  array<int, string>  $tags
     * @return array<string, mixed>
     */
    public function buildShopifyOrderPayload(array $detail, Collection $lines, array $tags): array
    {
        $summary = $detail['summary'] ?? [];
        $shipping = $detail['shipping'] ?? [];
        $amounts = $detail['amounts'] ?? [];
        $orderRef = (string) ($summary['order_id'] ?? '');
        $currency = (string) ($amounts['currency'] ?? 'USD');

        $lineItems = [];
        foreach ($detail['line_items'] ?? [] as $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $lineItems[] = [
                'sku' => $sku,
                'title' => (string) ($item['title'] ?? $sku),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'price' => number_format((float) ($item['unit_price'] ?? 0), 2, '.', ''),
            ];
        }

        if ($lineItems === []) {
            foreach ($lines as $line) {
                $sku = trim((string) ($line->sku ?? ''));
                if ($sku === '') {
                    continue;
                }
                $qty = max(1, (int) ($line->quantity ?? 1));
                $unit = $qty > 0 ? ((float) ($line->price ?? 0)) / $qty : (float) ($line->price ?? 0);
                $lineItems[] = [
                    'sku' => $sku,
                    'title' => (string) ($line->title ?: $sku),
                    'quantity' => $qty,
                    'price' => number_format($unit, 2, '.', ''),
                ];
            }
        }

        $recipient = trim((string) ($shipping['recipient'] ?? ''));
        [$firstName, $lastName] = $this->splitShopifyCustomerName($detail, $recipient);
        $lastName = $this->ensureShopifyLastName($lastName);

        $noteLines = [
            'Imported from Amazon Order #'.$orderRef,
        ];
        if (! empty($summary['fulfillment_channel'])) {
            $noteLines[] = 'Fulfillment: '.$summary['fulfillment_channel'];
        }
        if (! empty($summary['status'])) {
            $noteLines[] = 'Amazon status: '.$summary['status'];
        }

        [$shopifyEmail, $emailIsPlaceholder] = $this->resolveShopifyCustomerEmail($orderRef, $shipping['email'] ?? null);

        $noteAttrs = [
            ['name' => 'amazon_order_id', 'value' => $orderRef],
        ];
        if ($emailIsPlaceholder) {
            $noteAttrs[] = ['name' => 'amazon_email_is_placeholder', 'value' => 'true'];
        }

        $payload = [
            'line_items' => [],
            'financial_status' => 'paid',
            'inventory_behaviour' => 'decrement_ignoring_policy',
            'tags' => implode(', ', array_values(array_unique(array_filter($tags)))),
            'note' => implode("\n", $noteLines),
            'note_attributes' => $noteAttrs,
            'customer' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $shopifyEmail,
            ],
        ];
        $payload = array_merge($payload, $this->resolveShopifySourceAttribution($orderRef));

        $settings = MarketplaceSyncSettings::getFor('amazon');
        if (! empty($settings['order']['keep_order_number_from_channel']) && $orderRef !== '') {
            $payload['name'] = $orderRef;
        }

        $shippingCost = (float) ($amounts['shipping_cost'] ?? 0);
        if ($shippingCost > 0) {
            $payload['shipping_lines'] = [[
                'title' => 'Amazon shipping',
                'price' => number_format($shippingCost, 2, '.', ''),
                'code' => 'amazon',
            ]];
        }

        $address1 = trim((string) ($shipping['address_line_1'] ?? ''));
        if ($address1 !== '') {
            $countryCode = $this->normalizeCountryCode($shipping['country'] ?? null);
            $province = trim((string) ($shipping['province'] ?? ''));
            $address = array_filter([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'address1' => $address1,
                'address2' => $shipping['address_line_2'] ?? null,
                'city' => $shipping['city'] ?? null,
                'province' => $province !== '' ? $province : null,
                'province_code' => $this->resolveProvinceCode($province, $countryCode),
                'country_code' => $countryCode,
                'zip' => $shipping['zip'] ?? null,
                'phone' => $shipping['phone'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            $payload['shipping_address'] = $address;
            $payload['billing_address'] = $address;
            $payload['customer']['addresses'] = [array_merge($address, ['default' => true])];
        }

        $payload['line_items'] = $lineItems;

        $paid = (float) ($amounts['total'] ?? 0);
        if ($paid <= 0) {
            foreach ($lineItems as $item) {
                $paid += ((float) ($item['price'] ?? 0)) * max(1, (int) ($item['quantity'] ?? 1));
            }
            $paid += $shippingCost;
        }
        if ($paid > 0) {
            $payload['transactions'] = [[
                'kind' => 'sale',
                'status' => 'success',
                'amount' => number_format($paid, 2, '.', ''),
                'gateway' => 'amazon',
                'currency' => $currency,
            ]];
        }

        return $payload;
    }

    /**
     * @return array{source_name: string, source_identifier: string, source_url: string, referring_site: string}
     */
    public function resolveShopifySourceAttribution(string $orderRef, ?array $settings = null): array
    {
        $settings ??= MarketplaceSyncSettings::getFor('amazon');
        $handle = trim((string) ($settings['order']['shopify_source_name'] ?? 'amazon'));

        return [
            'source_name' => $handle !== '' ? $handle : 'amazon',
            'source_identifier' => $orderRef,
            'source_url' => 'https://sellercentral.amazon.com/orders-v3/order/'.$orderRef,
            'referring_site' => 'https://www.amazon.com/',
        ];
    }

    public function shopifySourceDisplayName(?array $settings = null): string
    {
        $settings ??= MarketplaceSyncSettings::getFor('amazon');
        $name = trim((string) ($settings['order']['shopify_source_display_name'] ?? 'Amz'));

        return $name !== '' ? $name : 'Amz';
    }

    /**
     * @return array{0: string, 1: bool}
     */
    public function resolveShopifyCustomerEmail(string $orderRef, ?string $rawEmail): array
    {
        $email = $this->normalizeEmail($rawEmail);
        if ($email !== null) {
            return [$email, false];
        }

        $domain = (string) env('AMAZON_SHOPIFY_PLACEHOLDER_EMAIL_DOMAIN', 'import.5coremanagement.com');
        $slug = preg_replace('/[^a-zA-Z0-9]/', '', $orderRef) ?: 'order';

        return ['amazon-'.$slug.'@'.$domain, true];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array{0: string, 1: string}
     */
    protected function splitShopifyCustomerName(array $detail, string $recipient = ''): array
    {
        $buyer = $detail['buyer'] ?? [];
        $candidates = array_filter([
            $recipient,
            trim((string) ($buyer['name'] ?? '')),
        ], fn ($name) => $name !== '');

        $fullName = '';
        foreach ($candidates as $candidate) {
            $cleaned = $this->cleanPersonName((string) $candidate);
            if ($cleaned !== '') {
                $fullName = $cleaned;
                break;
            }
        }

        if ($fullName === '') {
            return ['Amazon', 'Customer'];
        }

        $parts = preg_split('/\s+/u', $fullName, 2) ?: [];
        $firstName = (string) ($parts[0] ?? 'Amazon');
        if (! isset($parts[1])) {
            return [$firstName, $this->ensureShopifyLastName('')];
        }

        return [$firstName, $this->ensureShopifyLastName((string) $parts[1])];
    }

    protected function ensureShopifyLastName(string $lastName): string
    {
        $lastName = trim($lastName);

        return $lastName !== '' ? $lastName : '.';
    }

    protected function cleanPersonName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $parts = array_values(array_filter($parts, function (string $part): bool {
            return (bool) preg_match('/[\p{L}\p{N}]/u', $part);
        }));

        return trim(implode(' ', $parts));
    }

    protected function normalizeEmail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $email = strtolower(trim((string) $value));
        if ($email === '') {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function normalizeCountryCode(?string $country): ?string
    {
        if ($country === null || trim($country) === '') {
            return null;
        }
        $country = trim($country);
        if (strlen($country) === 2) {
            return strtoupper($country);
        }
        $map = [
            'united states' => 'US',
            'usa' => 'US',
            'united kingdom' => 'GB',
        ];

        return $map[strtolower($country)] ?? $country;
    }

    protected function resolveProvinceCode(string $province, ?string $countryCode): ?string
    {
        $province = trim($province);
        if ($province === '') {
            return null;
        }
        if (strlen($province) === 2 && ctype_alpha($province)) {
            return strtoupper($province);
        }
        if ($countryCode !== 'US') {
            return null;
        }

        $map = [
            'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
            'california' => 'CA', 'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE',
            'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID',
            'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS',
            'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
            'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS',
            'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
            'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
            'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK',
            'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC',
            'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT',
            'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV',
            'wisconsin' => 'WI', 'wyoming' => 'WY', 'district of columbia' => 'DC',
        ];

        return $map[strtolower($province)] ?? null;
    }
}
