<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\WayfairDailyData;
use App\Models\WayfairPricingPrice;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;

class WayfairDetailFormatter
{
    /**
     * @param  array<string, mixed>|null  $wfLive
     * @return array<string, mixed>
     */
    public function formatProduct(?array $wfLive, ?WayfairPricingPrice $product, ShopifySku $shopify): array
    {
        $wf = is_array($wfLive) ? $wfLive : [];
        $sku = (string) $shopify->sku;
        $linked = $product !== null && trim((string) $product->sku) !== '';
        $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromRow($shopify);
        $wfStock = $linked ? ($wf['inventory'] ?? $product?->wayfair_stock) : null;

        return [
            'shopify' => [
                'sku' => $shopify->sku,
                'goods_summary' => $shopify->product_title ?? $shopify->variant_title,
                'variant_title' => $shopify->variant_title,
                'variant_id' => $shopify->variant_id,
                'product_link' => $shopify->product_link,
                'image' => $shopify->image_src,
                'available_to_sell' => $shopifyQty,
                'b2c_price' => $shopify->b2c_price ?? $shopify->price,
                'price' => $shopify->price,
            ],
            'link' => [
                'product_id' => $product?->sku,
                'title' => $product?->sku,
                'price' => $product?->price,
                'quantity' => $wfStock,
                'last_synced_at' => $product?->updated_at,
            ],
            'wayfair' => [
                'product_id' => $wf['product_id'] ?? $product?->sku,
                'title' => $wf['title'] ?? $product?->sku ?? $sku,
                'status' => $wf['state'] ?? 'active',
                'stock' => $wfStock,
                'price' => $wf['price'] ?? $product?->price,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderRoot
     * @param  Collection<int, WayfairDailyData>  $lines
     * @return array<string, mixed>
     */
    public function formatOrder(array $orderRoot, Collection $lines, WayfairDailyData $primaryLine): array
    {
        $order = is_array($orderRoot) ? $orderRoot : [];
        $shipTo = is_array($order['shipTo'] ?? null) ? $order['shipTo'] : [];
        $shippingInfo = is_array($order['shippingInfo'] ?? null) ? $order['shippingInfo'] : [];

        $name = trim((string) ($shipTo['name'] ?? $order['customerName'] ?? $primaryLine->customer_name ?? ''));
        $address1 = trim((string) ($shipTo['address1'] ?? $order['customerAddress1'] ?? $primaryLine->customer_address1 ?? ''));
        $address2 = trim((string) ($shipTo['address2'] ?? $order['customerAddress2'] ?? $primaryLine->customer_address2 ?? ''));
        $city = trim((string) ($shipTo['city'] ?? $order['customerCity'] ?? $primaryLine->customer_city ?? ''));
        $state = trim((string) ($shipTo['state'] ?? $order['customerState'] ?? $primaryLine->customer_state ?? ''));
        $zip = trim((string) ($shipTo['postalCode'] ?? $order['customerPostalCode'] ?? $primaryLine->customer_postal_code ?? ''));
        $country = trim((string) ($shipTo['country'] ?? $primaryLine->customer_country ?? ''));
        $phone = trim((string) ($shipTo['phoneNumber'] ?? $primaryLine->customer_phone ?? ''));
        $poNumber = (string) ($order['poNumber'] ?? $order['po_number'] ?? $primaryLine->po_number);
        $poDate = $order['poDate'] ?? $order['po_date'] ?? $primaryLine->po_date;
        $carrier = (string) ($shippingInfo['carrierCode'] ?? $primaryLine->carrier_code ?? '');
        $shipSpeed = (string) ($shippingInfo['shipSpeed'] ?? $primaryLine->ship_speed ?? '');

        $productTotal = 0.0;
        $lineItems = $lines->map(function (WayfairDailyData $line) use (&$productTotal) {
            $qty = max(1, (int) ($line->quantity ?? 1));
            $unit = (float) ($line->unit_price ?? 0);
            $lineTotal = $line->total_price !== null ? (float) $line->total_price : ($unit * $qty);
            $productTotal += $lineTotal;

            return [
                'sku' => $line->sku,
                'title' => $line->display_title ?: $line->sku,
                'quantity' => $qty,
                'stock' => $qty,
                'unit_price' => $unit,
                'amount' => $lineTotal,
                'line_total' => $lineTotal,
                'status' => $line->status,
                'shopify_order_id' => $line->shopify_order_id,
            ];
        })->values()->all();

        $fullAddress = trim(implode(', ', array_filter([$address1, $address2, $city, $state, $zip, $country])));

        return [
            'summary' => [
                'order_id' => $poNumber,
                'order_number' => $poNumber,
                'status' => $order['status'] ?? $primaryLine->status,
                'created' => $poDate,
            ],
            'amounts' => [
                'total' => $productTotal,
                'currency' => 'USD',
            ],
            'funds' => [
                'product_total' => $productTotal,
                'order_amount' => $productTotal,
                'customer_total_paid' => $productTotal,
                'currency' => 'USD',
            ],
            'payment' => [
                'method' => 'Wayfair',
                'currency' => 'USD',
                'total_paid' => $productTotal,
                'paid_at' => $poDate,
            ],
            'shipping' => [
                'recipient' => $name !== '' ? $name : null,
                'detail_address' => $address1 !== '' ? $address1 : null,
                'address_line_1' => $address1 !== '' ? $address1 : null,
                'address_line_2' => $address2 !== '' ? $address2 : null,
                'city' => $city !== '' ? $city : null,
                'province' => $state !== '' ? $state : null,
                'zip' => $zip !== '' ? $zip : null,
                'country' => $country !== '' ? $country : null,
                'country_name' => $country !== '' ? $country : null,
                'full_address' => $fullAddress !== '' ? $fullAddress : null,
                'phone' => $phone !== '' ? $phone : null,
                'email' => null,
                'tracking' => null,
                'carrier' => $carrier !== '' ? $carrier : null,
            ],
            'shipment' => [
                'service' => $shipSpeed !== '' ? $shipSpeed : ($carrier !== '' ? $carrier : null),
                'tracking' => null,
                'status' => $primaryLine->status,
            ],
            'buyer' => [
                'name' => $name !== '' ? $name : null,
                'phone' => $phone !== '' ? $phone : null,
                'country' => $country !== '' ? $country : null,
            ],
            'line_items' => $lineItems,
            'lines' => $lineItems,
            'shopify' => [
                'shopify_order_id' => $primaryLine->shopify_order_id,
                'import_status' => $primaryLine->import_status,
                'pushed_to_shopify_at' => $primaryLine->pushed_to_shopify_at,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  Collection<int, WayfairDailyData>  $lines
     * @param  array<int, string>  $tags
     * @return array<string, mixed>
     */
    public function buildShopifyOrderPayload(array $detail, Collection $lines, array $tags): array
    {
        $summary = $detail['summary'] ?? [];
        $shipping = $detail['shipping'] ?? [];
        $payment = $detail['payment'] ?? [];
        $amounts = $detail['amounts'] ?? [];
        $shipment = $detail['shipment'] ?? [];
        $orderRef = (string) ($summary['order_id'] ?? '');

        $lineItems = [];
        foreach ($detail['line_items'] ?? [] as $item) {
            $sku = (string) ($item['sku'] ?? '');
            if (in_array($sku, ['__order__', '__unknown__', ''], true)) {
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
                $sku = (string) $line->sku;
                if (in_array($sku, ['__order__', '__unknown__', ''], true)) {
                    continue;
                }
                $lineItems[] = [
                    'sku' => $sku,
                    'title' => (string) ($line->display_title ?: $sku),
                    'quantity' => max(1, (int) ($line->quantity ?? 1)),
                    'price' => number_format((float) ($line->unit_price ?? 0), 2, '.', ''),
                ];
            }
        }

        $recipient = trim((string) ($shipping['recipient'] ?? ''));
        [$firstName, $lastName] = $this->splitShopifyCustomerName($detail, $recipient);
        $lastName = $this->ensureShopifyLastName($lastName);
        $currency = (string) ($payment['currency'] ?? $amounts['currency'] ?? 'USD');
        $customerPaid = isset($payment['total_paid']) ? (float) $payment['total_paid'] : null;

        $noteLines = ['Imported from Wayfair PO #'.$orderRef];
        if (! empty($shipment['service'])) {
            $noteLines[] = 'Shipping method: '.$shipment['service'];
        }

        [$shopifyEmail, $emailIsPlaceholder] = $this->resolveShopifyCustomerEmail($orderRef, $shipping['email'] ?? null);

        $noteAttrs = [
            ['name' => 'wayfair_order_id', 'value' => $orderRef],
            ['name' => 'wayfair_po_number', 'value' => $orderRef],
        ];
        if ($emailIsPlaceholder) {
            $noteAttrs[] = ['name' => 'wayfair_email_is_placeholder', 'value' => 'true'];
        }
        foreach ([
            'wayfair_buyer_phone' => $shipping['phone'] ?? null,
            'wayfair_shipping_method' => $shipment['service'] ?? null,
            'wayfair_order_amount' => $customerPaid !== null ? number_format($customerPaid, 2, '.', '') : null,
        ] as $name => $value) {
            if ($value !== null && $value !== '') {
                $noteAttrs[] = ['name' => $name, 'value' => (string) $value];
            }
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

        $address1 = trim((string) ($shipping['address_line_1'] ?? ''));
        if ($address1 === '' && ! empty($shipping['detail_address'])) {
            $address1 = trim((string) $shipping['detail_address']);
        }
        if ($address1 === '' && ! empty($shipping['full_address'])) {
            $address1 = trim((string) $shipping['full_address']);
        }

        if ($address1 !== '') {
            $countryCode = $this->normalizeCountryCode($shipping['country'] ?? $shipping['country_name'] ?? null);
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

        if ($customerPaid !== null && $customerPaid > 0) {
            $payload['transactions'] = [[
                'kind' => 'sale',
                'status' => 'success',
                'amount' => number_format($customerPaid, 2, '.', ''),
                'gateway' => 'wayfair',
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
        $settings ??= MarketplaceSyncSettings::getFor('wayfair');
        $handle = trim((string) (
            $settings['order']['shopify_source_name']
            ?? config('services.wayfair.shopify_source_name')
            ?? 'wayfair'
        ));
        $urlTemplate = (string) (
            $settings['order']['shopify_source_url_template']
            ?? config('services.wayfair.shopify_source_url_template')
            ?? 'https://partners.wayfair.com/d/orders/{order_id}'
        );

        return [
            'source_name' => $handle !== '' ? $handle : 'wayfair',
            'source_identifier' => $orderRef,
            'source_url' => str_replace('{order_id}', $orderRef, $urlTemplate),
            'referring_site' => 'https://www.wayfair.com/',
        ];
    }

    public function shopifySourceDisplayName(?array $settings = null): string
    {
        $settings ??= MarketplaceSyncSettings::getFor('wayfair');
        $name = trim((string) (
            $settings['order']['shopify_source_display_name']
            ?? config('services.wayfair.shopify_source_display_name')
            ?? 'Wayfair'
        ));

        return $name !== '' ? $name : 'Wayfair';
    }

    /**
     * @return array{0: string, 1: bool}
     */
    public function resolveShopifyCustomerEmail(string $orderRef, ?string $rawEmail): array
    {
        $email = strtolower(trim((string) $rawEmail));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [$email, false];
        }

        $domain = (string) env('WAYFAIR_SHOPIFY_PLACEHOLDER_EMAIL_DOMAIN', 'import.5coremanagement.com');
        $slug = preg_replace('/[^a-zA-Z0-9]/', '', $orderRef) ?: 'order';

        return ['wayfair-'.$slug.'@'.$domain, true];
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
            $cleaned = trim(preg_replace('/\s+/u', ' ', (string) $candidate) ?? '');
            if ($cleaned !== '') {
                $fullName = $cleaned;
                break;
            }
        }

        if ($fullName === '') {
            return ['Wayfair', 'Customer'];
        }

        $parts = preg_split('/\s+/u', $fullName, 2) ?: [];
        $firstName = (string) ($parts[0] ?? 'Wayfair');
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
            'great britain' => 'GB',
            'canada' => 'CA',
        ];
        $key = strtolower($country);

        return $map[$key] ?? $country;
    }
}
