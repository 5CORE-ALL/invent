<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressMetric;
use App\Models\AliexpressOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AliexpressDetailFormatter
{
    /**
     * @param  array<string, mixed>|null  $aeLive
     * @param  array<int, array<string, mixed>>  $aeSkuRows
     * @return array<string, mixed>
     */
    public function formatProduct(?array $aeLive, ?AliexpressMetric $metric, ShopifySku $shopify, array $aeSkuRows = []): array
    {
        $ae = $this->arr($aeLive);
        $shopifyQty = $shopify->available_to_sell ?? $shopify->inv ?? $shopify->on_hand ?? null;
        $shopifyPrice = $shopify->b2c_price ?? $shopify->price ?? null;

        $shopifyCatalog = $this->loadShopifyCatalogRow($shopify);
        $shopifyImages = $this->extractShopifyImages($shopify, $shopifyCatalog);
        $aeImages = $this->extractProductImages($ae, $aeSkuRows);
        $shopifyDescription = $this->resolveShopifyDescription($shopify, $shopifyCatalog);
        $descriptions = $this->extractProductDescriptions($ae);
        $variants = $this->formatProductVariants($ae, $aeSkuRows, $metric, $shopify);
        $properties = $this->extractProductProperties($ae);
        $shopifyProperties = $this->extractShopifyProperties($shopify, $shopifyCatalog);

        $cachedPrice = $this->money($metric?->price);
        $minPrice = $this->money($ae['product_min_price'] ?? null) ?? $cachedPrice;
        $maxPrice = $this->money($ae['product_max_price'] ?? null) ?? $cachedPrice;

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
                'committed' => $shopify->committed,
                'incoming' => $shopify->incoming,
                'unavailable' => $shopify->unavailable,
                'b2c_price' => $shopifyPrice,
                'b2b_price' => $shopify->b2b_price,
                'price' => $shopify->price,
                'shopify_l30' => $shopify->shopify_l30,
                'images' => $shopifyImages,
                'main_image' => $shopifyImages[0] ?? $shopify->image_src,
                'description_html' => $shopifyDescription['html'],
                'description_source' => $shopifyDescription['source'],
                'properties' => $shopifyProperties,
                'catalog_store' => $shopifyCatalog?->store ?? null,
                'shopify_product_id' => $shopifyCatalog?->shopify_product_id
                    ? (string) $shopifyCatalog->shopify_product_id
                    : null,
                'vendor' => $this->str($shopifyCatalog?->vendor ?? null),
                'product_type' => $this->str($shopifyCatalog?->product_type ?? null),
                'handle' => $this->str($shopifyCatalog?->handle ?? null),
                'catalog_status' => $this->str($shopifyCatalog?->status ?? null),
            ],
            'link' => [
                'product_id' => $metric?->product_id,
                'title' => $metric?->product_name,
                'price' => $metric?->price,
                'l30' => $metric?->l30,
                'l60' => $metric?->l60,
                'last_order_date' => $metric?->last_order_date,
                'bullet_points' => $metric?->bullet_points,
            ],
            'aliexpress' => [
                'product_id' => $this->str($ae['product_id'] ?? $metric?->product_id),
                'title' => $this->extractProductTitle($ae) ?? $metric?->product_name,
                'status' => $this->str($ae['product_status_type'] ?? $ae['status'] ?? $ae['product_status'] ?? null),
                'category_id' => $this->str($ae['category_id'] ?? $ae['categoryId'] ?? null),
                'currency' => $this->str($ae['currency_code'] ?? $ae['currency'] ?? null),
                'unit' => $this->str($ae['product_unit'] ?? null),
                'package_type' => $this->str($ae['package_type'] ?? null),
                'bulk_order' => $ae['bulk_order'] ?? null,
                'bulk_discount' => $ae['bulk_discount'] ?? null,
                'freight_template_id' => $this->str($ae['freight_template_id'] ?? null),
                'gmt_create' => $this->str($ae['gmt_create'] ?? $ae['create_time'] ?? null),
                'gmt_modified' => $this->str($ae['gmt_modified'] ?? $ae['modified_time'] ?? null),
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'cached_price' => $cachedPrice,
                'images' => $aeImages,
                'main_image' => $aeImages[0] ?? null,
                'descriptions' => $descriptions,
                'variants' => $variants,
                'properties' => $properties,
                'subjects' => $this->extractMultiLanguageSubjects($ae),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderRoot
     * @param  Collection<int, AliexpressOrderMetric>  $lines
     * @return array<string, mixed>
     */
    public function formatOrder(array $orderRoot, Collection $lines, AliexpressOrderMetric $primaryLine): array
    {
        $order = $this->arr($orderRoot);
        $buyer = $this->arr($order['buyer_info'] ?? []);
        $addr = $this->arr($order['receipt_address'] ?? []);
        $amounts = $this->extractOrderAmounts($order, $lines);
        $funds = $this->extractOrderFunds($order);
        $logistics = $this->extractLogisticsList($order);
        $apiLines = $this->extractRichOrderLines($order, $lines);
        $phone = $this->formatOrderPhone($addr);

        return [
            'summary' => [
                'order_id' => (string) ($order['order_id'] ?? $order['id'] ?? $primaryLine->order_id),
                'order_number' => $order['order_number'] ?? $primaryLine->order_number ?? null,
                'status' => $order['order_status'] ?? $order['status'] ?? $primaryLine->status,
                'buyer_remark' => $order['buyer_remark'] ?? $order['memo'] ?? null,
                'seller_remark' => $order['seller_remark'] ?? $order['memo'] ?? null,
                'buyer_login_id' => $buyer['login_id'] ?? $order['buyerloginid'] ?? $order['buyer_login_id'] ?? null,
                'created' => $order['gmt_create'] ?? $order['create_time'] ?? $primaryLine->order_date,
                'paid' => $order['gmt_pay_time'] ?? $order['gmt_pay_success'] ?? $order['pay_time'] ?? null,
                'sent' => $order['gmt_send_goods_time'] ?? $this->firstLogisticsField($logistics, 'shipped_at'),
                'finished' => $order['gmt_receive_goods_time'] ?? $order['end_time'] ?? $order['gmt_trade_end'] ?? null,
                'modified' => $order['gmt_modified'] ?? null,
            ],
            'amounts' => $amounts,
            'funds' => $funds,
            'buyer' => [
                'name' => $buyer['first_name'] ?? $addr['contact_person'] ?? $order['buyer_signer_fullname'] ?? null,
                'last_name' => $buyer['last_name'] ?? null,
                'login_id' => $buyer['login_id'] ?? $order['buyerloginid'] ?? $order['buyer_login_id'] ?? null,
                'email' => $this->resolveBuyerEmail($buyer, $addr, $order),
                'phone' => $phone,
                'country' => $buyer['country'] ?? $addr['country'] ?? $addr['country_name'] ?? null,
            ],
            'shipping' => [
                'recipient' => $addr['contact_person'] ?? $addr['receiver'] ?? null,
                'detail_address' => $addr['detail_address'] ?? null,
                'address_line_1' => $addr['address'] ?? null,
                'address_line_2' => $addr['address2'] ?? null,
                'city' => $addr['city'] ?? null,
                'province' => $addr['province'] ?? $addr['state'] ?? null,
                'zip' => $addr['zip'] ?? $addr['zip_code'] ?? null,
                'country' => $addr['country'] ?? null,
                'country_name' => $addr['country_name'] ?? null,
                'localized_address' => $addr['localized_address'] ?? null,
                'email' => $this->resolveBuyerEmail($buyer, $addr, $order),
                'phone' => $phone,
                'tax_number' => $addr['tax_number'] ?? $addr['cpf'] ?? $addr['passport_no'] ?? null,
                'full_address' => $this->joinAddress($addr),
            ],
            'shipment' => $this->extractShipmentSummary($order, $logistics),
            'logistics' => $logistics,
            'line_items' => $apiLines,
            'shopify' => [
                'shopify_order_id' => $primaryLine->shopify_order_id,
                'import_status' => $primaryLine->import_status,
                'pushed_to_shopify_at' => $primaryLine->pushed_to_shopify_at,
            ],
            'payment' => [
                'method' => $order['payment_type'] ?? $order['pay_type'] ?? null,
                'currency' => $funds['currency'] ?? $this->moneyCurrency($order['order_amount'] ?? $order['pay_amount'] ?? null) ?? ($amounts['currency'] ?? null),
                'paid_at' => $order['gmt_pay_time'] ?? $order['gmt_pay_success'] ?? null,
                'total_paid' => $funds['customer_total_paid'] ?? $amounts['pay_amount'],
            ],
        ];
    }

    /**
     * Build Shopify REST order payload from formatted AliExpress order detail.
     *
     * @param  array<string, mixed>  $detail
     * @param  Collection<int, AliexpressOrderMetric>  $lines
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
                    'price' => number_format((float) ($line->amount ?? 0), 2, '.', ''),
                ];
            }
        }

        $recipient = trim((string) ($shipping['recipient'] ?? ''));
        [$firstName, $lastName] = $this->splitShopifyCustomerName($detail, $recipient);
        $lastName = $this->ensureShopifyLastName($lastName);
        $funds = $detail['funds'] ?? [];
        $currency = (string) ($payment['currency'] ?? $funds['currency'] ?? 'USD');
        $customerPaid = isset($payment['total_paid']) ? (float) $payment['total_paid'] : null;

        $noteLines = [
            'Imported from AliExpress Order #'.$orderRef,
        ];
        if (! empty($payment['method'])) {
            $noteLines[] = 'Payment method: '.$payment['method'];
        }
        if (! empty($payment['paid_at'])) {
            $noteLines[] = 'Payment time: '.$payment['paid_at'];
        }
        if (isset($funds['order_amount'])) {
            $noteLines[] = sprintf('Order amount: %s %.2f', $currency, (float) $funds['order_amount']);
        }
        if ($customerPaid !== null) {
            $noteLines[] = sprintf('Customer paid: %s %.2f', $currency, $customerPaid);
        }
        if (isset($funds['seller_amount_paid'])) {
            $noteLines[] = sprintf('Seller amount paid: %s %.2f', $currency, (float) $funds['seller_amount_paid']);
        }
        foreach ([
            'Platform commission' => $funds['platform_commission'] ?? null,
            'Transaction service fee' => $funds['transaction_service_fee'] ?? null,
            'Platform offer tax' => $funds['platform_offer_tax'] ?? null,
        ] as $label => $value) {
            if ($value !== null) {
                $noteLines[] = sprintf('%s: %s %.2f', $label, $currency, (float) $value);
            }
        }
        if (! empty($shipment['tracking'])) {
            $noteLines[] = 'Tracking: '.$shipment['tracking'];
        }
        if (! empty($shipment['service'])) {
            $noteLines[] = 'Shipping method: '.$shipment['service'];
        }
        if (! empty($shipping['tax_number'])) {
            $noteLines[] = 'Tax number: '.$shipping['tax_number'];
        }

        [$shopifyEmail, $emailIsPlaceholder] = $this->resolveShopifyCustomerEmail($orderRef, $shipping['email'] ?? null);

        $noteAttrs = [
            ['name' => 'aliexpress_order_id', 'value' => $orderRef],
        ];
        if ($emailIsPlaceholder) {
            $noteAttrs[] = ['name' => 'aliexpress_email_is_placeholder', 'value' => 'true'];
        }
        foreach ([
            'aliexpress_buyer_login' => $summary['buyer_login_id'] ?? null,
            'aliexpress_payment_method' => $payment['method'] ?? null,
            'aliexpress_tracking_number' => $shipment['tracking'] ?? null,
            'aliexpress_shipping_method' => $shipment['service'] ?? null,
            'aliexpress_tax_number' => $shipping['tax_number'] ?? null,
            'aliexpress_buyer_email' => $shipping['email'] ?? null,
            'aliexpress_buyer_phone' => $shipping['phone'] ?? null,
            'aliexpress_platform_promotion' => isset($funds['platform_promotion']) ? number_format((float) $funds['platform_promotion'], 2, '.', '') : null,
            'aliexpress_platform_offer' => isset($funds['platform_offer']) ? number_format((float) $funds['platform_offer'], 2, '.', '') : null,
            'aliexpress_order_amount' => isset($funds['order_amount']) ? number_format((float) $funds['order_amount'], 2, '.', '') : null,
            'aliexpress_customer_paid' => $customerPaid !== null ? number_format($customerPaid, 2, '.', '') : null,
            'aliexpress_seller_amount_paid' => isset($funds['seller_amount_paid']) ? number_format((float) $funds['seller_amount_paid'], 2, '.', '') : null,
            'aliexpress_platform_commission' => isset($funds['platform_commission']) ? number_format((float) $funds['platform_commission'], 2, '.', '') : null,
            'aliexpress_transaction_service_fee' => isset($funds['transaction_service_fee']) ? number_format((float) $funds['transaction_service_fee'], 2, '.', '') : null,
            'aliexpress_platform_offer_tax' => isset($funds['platform_offer_tax']) ? number_format((float) $funds['platform_offer_tax'], 2, '.', '') : null,
        ] as $name => $value) {
            if ($value !== null && $value !== '') {
                $noteAttrs[] = ['name' => $name, 'value' => (string) $value];
            }
        }

        $customerFields = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $shopifyEmail,
        ];

        $payload = [
            'line_items' => [],
            'financial_status' => 'paid',
            'inventory_behaviour' => 'decrement_obeying_policy',
            'tags' => implode(', ', array_values(array_unique(array_filter($tags)))),
            'note' => implode("\n", $noteLines),
            'note_attributes' => $noteAttrs,
            'customer' => $customerFields,
        ];
        $payload = array_merge($payload, $this->resolveShopifySourceAttribution($orderRef));

        $shippingCost = (float) ($amounts['shipping_cost'] ?? 0);
        if ($shippingCost > 0) {
            $payload['shipping_lines'] = [[
                'title' => (string) ($shipment['service'] ?? 'AliExpress shipping'),
                'price' => number_format($shippingCost, 2, '.', ''),
                'code' => 'aliexpress',
            ]];
        }

        $address1 = trim((string) ($shipping['address_line_1'] ?? ''));
        if ($address1 === '' && ! empty($shipping['detail_address'])) {
            $address1 = (string) $shipping['detail_address'];
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
        }

        $payload['line_items'] = $lineItems;

        $lineSubtotal = 0.0;
        foreach ($lineItems as $item) {
            $lineSubtotal += ((float) ($item['price'] ?? 0)) * max(1, (int) ($item['quantity'] ?? 1));
        }
        $lineSubtotal += $shippingCost;

        if ($customerPaid !== null && $lineSubtotal > 0 && $customerPaid < $lineSubtotal) {
            $payload['discount_codes'] = [[
                'code' => 'AliExpress promotion',
                'amount' => number_format(round($lineSubtotal - $customerPaid, 2), 2, '.', ''),
                'type' => 'fixed_amount',
            ]];
        }

        if ($customerPaid !== null && $customerPaid > 0) {
            $payload['transactions'] = [[
                'kind' => 'sale',
                'status' => 'success',
                'amount' => number_format($customerPaid, 2, '.', ''),
                'gateway' => 'aliexpress',
                'currency' => $currency,
            ]];
        }

        return $payload;
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
            trim(trim((string) ($buyer['first_name'] ?? '')).' '.trim((string) ($buyer['last_name'] ?? ''))),
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
            return ['AliExpress', 'Customer'];
        }

        $parts = preg_split('/\s+/u', $fullName, 2) ?: [];
        $firstName = (string) ($parts[0] ?? 'AliExpress');
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

    /**
     * Shopify channel attribution for API-created orders.
     *
     * @return array{source_name: string, source_identifier: string, source_url: string, referring_site: string}
     */
    public function resolveShopifySourceAttribution(string $orderRef, ?array $settings = null): array
    {
        $settings ??= MarketplaceSyncSettings::getFor('aliexpress');
        $handle = trim((string) (
            $settings['order']['shopify_source_name']
            ?? config('services.aliexpress.shopify_source_name')
            ?? 'aliexpress'
        ));
        $urlTemplate = (string) (
            $settings['order']['shopify_source_url_template']
            ?? config('services.aliexpress.shopify_source_url_template')
            ?? 'https://csp.aliexpress.com/m_apps/order-manage/order_detail?orderId={order_id}'
        );

        return [
            'source_name' => $handle !== '' ? $handle : 'aliexpress',
            'source_identifier' => $orderRef,
            'source_url' => str_replace('{order_id}', $orderRef, $urlTemplate),
            'referring_site' => 'https://www.aliexpress.com/',
        ];
    }

    public function shopifySourceDisplayName(?array $settings = null): string
    {
        $settings ??= MarketplaceSyncSettings::getFor('aliexpress');
        $name = trim((string) (
            $settings['order']['shopify_source_display_name']
            ?? config('services.aliexpress.shopify_source_display_name')
            ?? 'AliExpress'
        ));

        return $name !== '' ? $name : 'AliExpress';
    }

    /**
     * @return array{0: string, 1: bool} email, is_placeholder
     */
    public function resolveShopifyCustomerEmail(string $orderRef, ?string $rawEmail): array
    {
        $email = $this->normalizeEmail($rawEmail);
        if ($email !== null) {
            return [$email, false];
        }

        $domain = (string) env('ALIEXPRESS_SHOPIFY_PLACEHOLDER_EMAIL_DOMAIN', 'import.5coremanagement.com');
        $slug = preg_replace('/[^a-zA-Z0-9]/', '', $orderRef) ?: 'order';

        return ['aliexpress-'.$slug.'@'.$domain, true];
    }

    /**
     * @param  array<string, mixed>  $buyer
     * @param  array<string, mixed>  $addr
     * @param  array<string, mixed>  $order
     */
    protected function resolveBuyerEmail(array $buyer, array $addr, array $order): ?string
    {
        foreach ([
            $addr['email'] ?? null,
            $buyer['email'] ?? null,
            $order['buyer_email'] ?? null,
            $order['email'] ?? null,
            $buyer['buyer_email'] ?? null,
        ] as $candidate) {
            $email = $this->normalizeEmail($candidate);
            if ($email !== null) {
                return $email;
            }
        }

        return null;
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

    protected function cleanPersonName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '') {
            return '';
        }

        $name = preg_replace('/^[:\-.,\s]+/u', '', $name) ?? $name;
        $name = preg_replace('/[:\-.,\s]+$/u', '', $name) ?? $name;

        $parts = preg_split('/\s+/u', $name) ?: [];
        $parts = array_values(array_filter($parts, function (string $part): bool {
            return (bool) preg_match('/[\p{L}\p{N}]/u', $part);
        }));

        return trim(implode(' ', $parts));
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
        ];
        $key = strtolower($country);

        return $map[$key] ?? $country;
    }

    /**
     * @param  array<string, mixed>  $addr
     */
    protected function formatOrderPhone(array $addr): ?string
    {
        $mobile = trim((string) ($addr['mobile_no'] ?? ''));
        $phone = trim((string) ($addr['phone_number'] ?? $addr['phone'] ?? ''));
        $country = trim((string) ($addr['phone_country'] ?? ''));
        $area = trim((string) ($addr['phone_area'] ?? ''));

        if ($mobile !== '') {
            if ($country !== '' && ! str_starts_with($mobile, '+')) {
                return trim($country.' '.$mobile);
            }

            return $mobile;
        }

        if ($phone !== '') {
            $prefix = trim($country.' '.$area);

            return $prefix !== '' ? trim($prefix.' '.$phone) : $phone;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    protected function extractOrderFunds(array $order): array
    {
        $childOrders = $this->list(
            $order['child_order_list']['global_aeop_tp_child_order_dto']
            ?? $order['child_order_list']
            ?? []
        );

        $productTotal = null;
        if ($childOrders !== []) {
            $sum = 0.0;
            foreach ($childOrders as $child) {
                $child = $this->arr($child);
                $price = $this->money($child['product_price'] ?? $child['init_order_amt'] ?? null);
                $qty = max(1, (int) ($child['product_count'] ?? 1));
                if ($price !== null) {
                    $sum += $price * $qty;
                }
            }
            if ($sum > 0) {
                $productTotal = $sum;
            }
        }

        $orderAmount = $this->money($order['order_amount'] ?? $order['total_amount'] ?? null);
        $escrowRate = $this->resolveEscrowFeeRate($order, $childOrders);
        $platformCommission = $this->money($order['escrow_fee'] ?? $order['platform_commission'] ?? null);
        if ($platformCommission === null && $orderAmount !== null && $escrowRate !== null) {
            $platformCommission = round($orderAmount * $escrowRate, 2);
        }

        $transactionFee = $this->money($order['service_fee'] ?? $order['transaction_fee'] ?? $order['transaction_service_fee'] ?? null);
        $platformTax = $this->money($order['tax_amount'] ?? $order['platform_tax'] ?? $order['platform_offer_tax'] ?? null);
        $affiliateCommission = $this->money($order['affiliate_fee'] ?? $order['affiliate_commission'] ?? null);
        $cashbackPaidBySeller = $this->money($order['cashback_fee'] ?? $order['seller_cashback'] ?? null);

        if ($transactionFee === null && $platformTax === null && $orderAmount !== null) {
            [$transactionFee, $platformTax] = $this->estimateSettlementFees($orderAmount);
        }

        $platformPromotion = $this->sumPlatformDiscounts($order, $childOrders);
        $platformOffer = $this->resolvePlatformOfferAmount($order, $childOrders, $platformPromotion);
        $storePromotion = $this->money($order['promotion_amount'] ?? $order['seller_discount_amount'] ?? null);
        $shippingCost = $this->money($order['logistics_amount'] ?? null) ?? 0.0;
        $customerTotalPaid = $this->resolveCustomerPaidAmount($order, $orderAmount, $platformPromotion, $platformOffer, $shippingCost);
        $sellerAmountPaid = $this->resolveSellerPaidAmount(
            $order,
            $orderAmount,
            $platformCommission,
            $transactionFee,
            $platformTax,
            $affiliateCommission,
            $cashbackPaidBySeller
        );

        return [
            'product_total' => $productTotal ?? $this->money($order['init_oder_amount'] ?? $order['init_order_amt'] ?? null),
            'shipping_cost' => $this->money($order['logistics_amount'] ?? null),
            'adjustment' => $this->money($order['adjust_fee'] ?? $order['adjustment_amount'] ?? null),
            'store_promotion' => $storePromotion,
            'platform_promotion' => $platformPromotion > 0 ? $platformPromotion : null,
            'platform_offer' => $platformOffer,
            'order_amount' => $orderAmount,
            'platform_commission' => $platformCommission,
            'affiliate_commission' => $affiliateCommission,
            'cashback_paid_by_seller' => $cashbackPaidBySeller,
            'transaction_service_fee' => $transactionFee,
            'platform_offer_tax' => $platformTax,
            'customer_total_paid' => $customerTotalPaid,
            'amount_paid' => $customerTotalPaid,
            'seller_amount_paid' => $sellerAmountPaid,
            'currency' => $this->moneyCurrency($order['order_amount'] ?? $order['pay_amount'] ?? null)
                ?? $this->str($order['settlement_currency'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $childOrders
     */
    protected function resolveEscrowFeeRate(array $order, array $childOrders = []): ?float
    {
        $rates = [];
        foreach ([$order['escrow_fee_rate'] ?? null] as $rate) {
            if ($rate !== null && $rate !== '') {
                $rates[] = (float) $rate;
            }
        }
        foreach ($childOrders as $child) {
            $child = $this->arr($child);
            if (($child['escrow_fee_rate'] ?? null) !== null && $child['escrow_fee_rate'] !== '') {
                $rates[] = (float) $child['escrow_fee_rate'];
            }
        }

        return $rates !== [] ? max($rates) : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $childOrders
     */
    protected function sumPlatformDiscounts(array $order, array $childOrders = []): float
    {
        $detailTotal = 0.0;
        foreach ($childOrders as $child) {
            $child = $this->arr($child);
            foreach ($this->list($child['child_order_discount_detail_list'] ?? []) as $row) {
                $row = $this->arr($row);
                if (strtoupper((string) ($row['promotion_owner'] ?? '')) !== 'PLATFORM') {
                    continue;
                }
                $detailTotal += $this->money($row['discount_detail'] ?? null) ?? 0.0;
            }
        }
        if ($detailTotal > 0) {
            return round($detailTotal, 2);
        }

        return $this->money($order['order_discount_info'] ?? null) ?? 0.0;
    }

    /**
     * Platform subsidy shown in AE fund UI ("Platform offer" / expected available).
     *
     * @param  array<int, array<string, mixed>>  $childOrders
     */
    protected function resolvePlatformOfferAmount(array $order, array $childOrders, float $platformPromotion): ?float
    {
        foreach ([
            $order['platform_offer_amount'] ?? null,
            $order['platform_offer'] ?? null,
            $order['platform_subsidy'] ?? null,
        ] as $candidate) {
            if (($amount = $this->money($candidate)) !== null) {
                return $amount;
            }
        }

        foreach ($childOrders as $child) {
            $child = $this->arr($child);
            foreach ($this->list($child['child_order_discount_detail_list'] ?? []) as $row) {
                $row = $this->arr($row);
                if (strtoupper((string) ($row['promotion_owner'] ?? '')) !== 'PLATFORM') {
                    continue;
                }
                if (($amount = $this->money($row['platform_offer_amount'] ?? $row['seller_discount'] ?? null)) !== null) {
                    return $amount;
                }
            }
        }

        if ($platformPromotion <= 0) {
            return null;
        }

        // AE seller UI rounds platform coupon down to the nearest $0.10 (2.93 -> 2.90).
        return floor(round($platformPromotion, 2) * 10) / 10;
    }

    protected function resolveCustomerPaidAmount(
        array $order,
        ?float $orderAmount,
        float $platformPromotion,
        ?float $platformOffer,
        float $shippingCost = 0.0
    ): ?float {
        if (($paid = $this->money($order['pay_amount'] ?? null)) !== null) {
            return $paid;
        }

        $settlementPay = $this->money($order['pay_amount_by_settlement_cur'] ?? null);
        if ($orderAmount !== null && $settlementPay !== null && $platformOffer !== null && $settlementPay > $orderAmount) {
            // Matches AE payment details: customer paid = settlement pay - platform offer.
            $customerPaid = round($settlementPay - $platformOffer, 2);
            if ($customerPaid > 0 && $customerPaid <= $orderAmount + $shippingCost) {
                return $customerPaid;
            }
        }

        if ($orderAmount !== null && $settlementPay !== null && $platformPromotion > 0 && $settlementPay > $orderAmount) {
            $settlementPremium = max(0, round($settlementPay - $orderAmount - $shippingCost, 2));
            $offer = $platformOffer ?? $platformPromotion;
            $buyerDiscount = max(0, round($offer - $settlementPremium, 2));

            return round(max(0, $orderAmount + $shippingCost - $buyerDiscount), 2);
        }

        if ($orderAmount !== null && $platformPromotion > 0) {
            return round(max(0, $orderAmount + $shippingCost - $platformPromotion), 2);
        }

        return $orderAmount !== null ? round($orderAmount + $shippingCost, 2) : $settlementPay;
    }

    protected function resolveSellerPaidAmount(
        array $order,
        ?float $orderAmount,
        ?float $platformCommission,
        ?float $transactionFee,
        ?float $platformTax,
        ?float $affiliateCommission,
        ?float $cashbackPaidBySeller
    ): ?float {
        $loanAmount = $this->money($this->arr($order['loan_info'] ?? [])['loan_amount'] ?? null);
        if ($loanAmount !== null) {
            return $loanAmount;
        }

        if ($orderAmount === null) {
            return null;
        }

        $deductions = 0.0;
        foreach ([$platformCommission, $transactionFee, $platformTax, $affiliateCommission, $cashbackPaidBySeller] as $fee) {
            if ($fee !== null && $fee > 0) {
                $deductions += $fee;
            }
        }

        return round(max(0, $orderAmount - $deductions), 2);
    }

    /**
     * Estimate AE settlement fees when the order detail API omits explicit fee amounts.
     *
     * @return array{0: float, 1: float}
     */
    protected function estimateSettlementFees(float $orderAmount): array
    {
        return [
            round($orderAmount * 0.025, 2),
            round($orderAmount * 0.01305, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $logistics
     * @return array<string, mixed>
     */
    protected function extractShipmentSummary(array $order, array $logistics): array
    {
        $first = $logistics[0] ?? [];

        return [
            'shipped_at' => $first['shipped_at'] ?? $order['gmt_send_goods_time'] ?? null,
            'service' => $first['service'] ?? $this->str($order['logistics_type'] ?? null),
            'tracking' => $first['tracking'] ?? $this->str($order['logistics_no'] ?? null),
            'status' => $first['status'] ?? $this->str($order['logistics_status'] ?? null),
            'status_message' => $first['status_message'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $logistics
     */
    protected function firstLogisticsField(array $logistics, string $field): ?string
    {
        foreach ($logistics as $row) {
            if (! empty($row[$field])) {
                return (string) $row[$field];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $ae
     * @param  array<int, array<string, mixed>>  $aeSkuRows
     * @return array<int, string>
     */
    protected function extractProductImages(array $ae, array $aeSkuRows): array
    {
        $urls = [];

        foreach ([
            $ae['main_image_url'] ?? null,
            $ae['product_main_image'] ?? null,
            $ae['image_url'] ?? null,
        ] as $url) {
            $urls = array_merge($urls, $this->splitImageUrls($url));
        }

        $urls = array_merge($urls, $this->splitImageUrls($ae['image_u_r_ls'] ?? $ae['image_urls'] ?? null));

        foreach (['aeop_a_e_product_propertys', 'aeop_ae_product_propertys', 'product_properties'] as $key) {
            foreach ($this->list($ae[$key] ?? []) as $prop) {
                $prop = $this->arr($prop);
                if (($prop['attr_name'] ?? '') === 'image' || isset($prop['attr_value'])) {
                    $urls = array_merge($urls, $this->splitImageUrls($prop['attr_value'] ?? $prop['attr_value_id'] ?? null));
                }
            }
        }

        foreach ($aeSkuRows as $row) {
            $urls = array_merge($urls, $this->splitImageUrls($row['image'] ?? $row['sku_image'] ?? null));
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * @return array{html: ?string, source: ?string}
     */
    protected function resolveShopifyDescription(ShopifySku $shopify, ?object $catalogRow): array
    {
        $bodyHtml = trim((string) ($catalogRow->body_html ?? ''));
        if ($bodyHtml !== '') {
            return ['html' => $bodyHtml, 'source' => 'shopify_catalog'];
        }

        $pmHtml = $this->resolveProductMasterDescriptionHtml($shopify->sku);
        if ($pmHtml !== null) {
            return ['html' => $pmHtml, 'source' => 'product_master'];
        }

        return ['html' => null, 'source' => null];
    }

    protected function resolveProductMasterDescriptionHtml(?string $sku): ?string
    {
        if ($sku === null || trim($sku) === '') {
            return null;
        }

        $pm = ProductMaster::query()
            ->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower(trim($sku))])
            ->first();

        if (! $pm) {
            return null;
        }

        $html = trim((string) ($pm->description_html ?? ''));
        if ($html !== '') {
            return $html;
        }

        foreach (['description_1500', 'description_1000', 'description_800', 'description_600', 'product_description'] as $col) {
            $text = trim((string) ($pm->{$col} ?? ''));
            if ($text !== '') {
                return '<p>'.nl2br(e($text), false).'</p>';
            }
        }

        return null;
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    protected function extractShopifyProperties(ShopifySku $shopify, ?object $catalogRow): array
    {
        $out = [];

        foreach ([
            'Vendor' => $this->str($catalogRow?->vendor ?? null),
            'Product type' => $this->str($catalogRow?->product_type ?? null),
            'Catalog status' => $this->str($catalogRow?->status ?? null),
            'Handle' => $this->str($catalogRow?->handle ?? null),
            'Store' => $this->str($catalogRow?->store ?? null),
        ] as $name => $value) {
            if ($value) {
                $out[] = ['name' => $name, 'value' => $value];
            }
        }

        $pm = ProductMaster::query()
            ->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower(trim((string) $shopify->sku))])
            ->first();

        if ($pm) {
            foreach ([
                'Category' => $this->str($pm->category ?? null),
                'Group' => $this->str($pm->group ?? null),
            ] as $name => $value) {
                if ($value) {
                    $out[] = ['name' => $name, 'value' => $value];
                }
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    protected function extractShopifyImages(ShopifySku $shopify, ?object $catalogRow): array
    {
        $urls = $this->parseCatalogImageUrls($catalogRow);

        if ($shopify->image_src) {
            array_unshift($urls, $shopify->image_src);
        }

        if ($urls === []) {
            $pm = ProductMaster::query()
                ->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower(trim((string) $shopify->sku))])
                ->first();

            if ($pm) {
                foreach (array_merge(
                    [$pm->main_image ?? null, $pm->main_image_brand ?? null],
                    array_map(fn ($i) => $pm->{"image{$i}"} ?? null, range(1, 12))
                ) as $url) {
                    $url = trim((string) $url);
                    if ($url !== '') {
                        $urls[] = $url;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * @return array<int, string>
     */
    protected function parseCatalogImageUrls(?object $catalogRow): array
    {
        if (! $catalogRow) {
            return [];
        }

        $urls = [];

        if (Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
            $decoded = json_decode((string) ($catalogRow->image_urls ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $urls[] = trim($item);
                    } elseif (is_array($item) && ! empty($item['src'])) {
                        $urls[] = trim((string) $item['src']);
                    }
                }
            }
        }

        if ($urls === [] && Schema::hasColumn('shopify_catalog_products', 'images')) {
            $decoded = json_decode((string) ($catalogRow->images ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $urls[] = trim($item);
                    } elseif (is_array($item) && ! empty($item['src'])) {
                        $urls[] = trim((string) $item['src']);
                    }
                }
            }
        }

        if ($urls === [] && ! empty($catalogRow->image_src)) {
            $urls[] = trim((string) $catalogRow->image_src);
        }

        return array_values(array_unique(array_filter($urls)));
    }

    protected function loadShopifyCatalogRow(ShopifySku $shopify): ?object
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return null;
        }

        $select = [
            'p.id',
            'p.title',
            'p.handle',
            'p.status',
            'p.body_html',
            'p.vendor',
            'p.product_type',
            'v.store',
            'v.shopify_variant_id',
            'v.shopify_product_id',
        ];

        if (Schema::hasColumn('shopify_catalog_products', 'image_src')) {
            $select[] = 'p.image_src';
        }
        if (Schema::hasColumn('shopify_catalog_products', 'images')) {
            $select[] = 'p.images';
        }
        if (Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
            $select[] = 'p.image_urls';
        }

        $base = DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id');

        if ($shopify->variant_id) {
            $row = (clone $base)
                ->where('v.shopify_variant_id', $shopify->variant_id)
                ->orderByDesc('v.synced_at')
                ->orderByDesc('v.id')
                ->select($select)
                ->first();

            if ($row) {
                return $row;
            }
        }

        $sku = trim((string) $shopify->sku);
        if ($sku === '') {
            return null;
        }

        return (clone $base)
            ->whereRaw('LOWER(TRIM(COALESCE(v.sku, \'\'))) = ?', [mb_strtolower($sku)])
            ->orderByDesc('v.synced_at')
            ->orderByDesc('v.id')
            ->select($select)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $ae
     * @return array<int, array{language: ?string, html: string}>
     */
    protected function extractProductDescriptions(array $ae): array
    {
        $candidates = [];
        $list = $this->list($ae['multi_language_description_list'] ?? $ae['aeop_a_e_product_description'] ?? []);

        if ($list !== []) {
            foreach ($list as $desc) {
                $desc = $this->arr($desc);
                $candidates[] = [
                    'language' => $this->str($desc['language'] ?? $desc['locale'] ?? null),
                    'web' => $desc['web_detail'] ?? $desc['detail'] ?? $desc['description'] ?? null,
                    'mobile' => $desc['mobile_detail'] ?? $desc['mobile_desc'] ?? null,
                ];
            }
        } else {
            foreach (['detail', 'product_description'] as $key) {
                if (! empty($ae[$key])) {
                    $candidates[] = ['language' => null, 'web' => $ae[$key], 'mobile' => null];
                }
            }
            if (! empty($ae['mobile_detail'])) {
                $candidates[] = ['language' => null, 'web' => null, 'mobile' => $ae['mobile_detail']];
            }
        }

        $out = [];
        $seenHashes = [];

        foreach ($candidates as $candidate) {
            $webHtml = $this->renderDescriptionContent($candidate['web']);
            $mobileHtml = $this->renderDescriptionContent($candidate['mobile']);
            $html = $this->pickBestDescriptionHtml($webHtml, $mobileHtml);
            if ($html === null) {
                continue;
            }

            $hash = $this->descriptionContentHash($html);
            if (isset($seenHashes[$hash])) {
                continue;
            }
            $seenHashes[$hash] = true;

            $out[] = [
                'language' => $candidate['language'],
                'html' => $html,
            ];
        }

        return $out;
    }

    protected function pickBestDescriptionHtml(?string $web, ?string $mobile): ?string
    {
        if ($web && $mobile) {
            if ($this->descriptionContentHash($web) === $this->descriptionContentHash($mobile)) {
                return $web;
            }

            return strlen(strip_tags($web)) >= strlen(strip_tags($mobile)) ? $web : $mobile;
        }

        return $web ?? $mobile;
    }

    protected function descriptionContentHash(string $html): string
    {
        $text = preg_replace('/\s+/', ' ', trim(strip_tags($html)) ?? '');

        return md5($text);
    }

    /**
     * AliExpress descriptions are often JSON module trees (moduleList / mobileDetail), not plain HTML.
     */
    protected function renderDescriptionContent(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            $html = $this->renderDescriptionModules($raw);

            return $html !== '' ? $html : null;
        }

        if (! is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] === '{' || $trimmed[0] === '[') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $html = $this->renderDescriptionModules($decoded);
                if ($html !== '') {
                    return $html;
                }
            }
        }

        if (stripos($trimmed, '<') !== false && stripos($trimmed, '>') !== false) {
            return $trimmed;
        }

        return '<p>'.nl2br(e($trimmed), false).'</p>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderDescriptionModules(array $data): string
    {
        $modules = $this->list(
            $data['moduleList']
            ?? $data['mobileDetail']
            ?? $data['module_list']
            ?? (isset($data[0]['type']) ? $data : [])
        );

        if ($modules === []) {
            return '';
        }

        $html = '';
        foreach ($modules as $module) {
            $module = $this->arr($module);
            $type = strtolower((string) ($module['type'] ?? ''));

            if ($type === 'html') {
                $content = $module['html']['content'] ?? $module['content'] ?? null;
                if (is_string($content) && trim($content) !== '') {
                    $html .= $content;
                }
                continue;
            }

            if ($type === 'text') {
                foreach ($this->list($module['texts'] ?? []) as $text) {
                    $text = $this->arr($text);
                    $content = trim((string) ($text['content'] ?? ''));
                    if ($content === '') {
                        continue;
                    }
                    $class = strtolower((string) ($text['class'] ?? $text['style'] ?? ''));
                    if (str_contains($class, 'title') || str_contains($class, 'head')) {
                        $html .= '<h5 class="ae-desc-title">'.e($content).'</h5>';
                    } else {
                        $html .= '<p class="ae-desc-body">'.nl2br(e($content), false).'</p>';
                    }
                }
                continue;
            }

            if ($type === 'image') {
                foreach ($this->list($module['images'] ?? []) as $image) {
                    $image = $this->arr($image);
                    $url = $this->str($image['url'] ?? $image['imgUrl'] ?? $image['image_url'] ?? null);
                    if ($url === null) {
                        continue;
                    }
                    $width = (int) ($image['width'] ?? $image['style']['width'] ?? 0);
                    $style = $width > 0 ? 'max-width:'.min($width, 800).'px;' : 'max-width:100%;';
                    $html .= '<div class="ae-desc-image my-2"><img src="'.e($url).'" alt="" class="img-fluid rounded border" style="'.$style.'"></div>';
                }
            }
        }

        return trim($html);
    }

    /**
     * @param  array<string, mixed>  $ae
     * @param  array<int, array<string, mixed>>  $aeSkuRows
     * @return array<int, array<string, mixed>>
     */
    protected function formatProductVariants(array $ae, array $aeSkuRows, ?AliexpressMetric $metric = null, ?ShopifySku $shopify = null): array
    {
        $variants = [];

        $skuList = $this->list(
            $ae['aeop_a_e_product_sku_list']
            ?? $ae['aeop_ae_product_sku_list']
            ?? $ae['aeop_a_e_product_s_k_u_list']
            ?? []
        );

        if ($skuList !== []) {
            foreach ($skuList as $skuRow) {
                $skuRow = $this->arr($skuRow);
                $variants[] = [
                    'sku' => $this->str($skuRow['sku_code'] ?? $skuRow['sku'] ?? null),
                    'price' => $this->money($skuRow['sku_price'] ?? $skuRow['price'] ?? null),
                    'stock' => $skuRow['ipm_sku_stock'] ?? $skuRow['sku_stock'] ?? $skuRow['stock'] ?? null,
                    'image' => $this->str($skuRow['sku_image'] ?? $skuRow['image'] ?? null),
                    'ean' => $this->str($skuRow['ean_code'] ?? $skuRow['barcode'] ?? null),
                    'properties' => $this->formatSkuProperties($skuRow),
                ];
            }
        }

        if ($variants === [] && $aeSkuRows !== []) {
            foreach ($aeSkuRows as $row) {
                $variants[] = [
                    'sku' => $this->str($row['sku'] ?? null),
                    'price' => $this->money($row['price'] ?? null),
                    'stock' => $row['stock'] ?? null,
                    'image' => null,
                    'ean' => null,
                    'properties' => [],
                ];
            }
        }

        if ($variants === [] && $metric && $metric->product_id && $metric->sku && $metric->sku !== $metric->product_id) {
            $variants[] = [
                'sku' => $this->str($metric->sku),
                'price' => $this->money($metric->price),
                'stock' => null,
                'image' => null,
                'ean' => null,
                'properties' => [],
                'source' => 'cached',
            ];
        }

        if ($variants === [] && $shopify && $shopify->sku) {
            $variants[] = [
                'sku' => $this->str($shopify->sku),
                'price' => $this->money($shopify->b2c_price ?? $shopify->price),
                'stock' => $shopify->available_to_sell ?? $shopify->inv ?? $shopify->on_hand,
                'image' => $shopify->image_src,
                'ean' => null,
                'properties' => [],
                'source' => 'shopify',
            ];
        }

        return $variants;
    }

    /**
     * @param  array<string, mixed>  $ae
     * @return array<int, array{name: string, value: string}>
     */
    protected function extractProductProperties(array $ae): array
    {
        $out = [];
        foreach (['aeop_a_e_product_propertys', 'aeop_ae_product_propertys', 'product_properties'] as $key) {
            foreach ($this->list($ae[$key] ?? []) as $prop) {
                $prop = $this->arr($prop);
                $name = $this->str($prop['attr_name'] ?? $prop['name'] ?? null);
                $value = $this->str($prop['attr_value'] ?? $prop['value'] ?? $prop['attr_value_id'] ?? null);
                if ($name && $value && strtolower($name) !== 'image') {
                    $out[] = ['name' => $name, 'value' => $value];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $ae
     * @return array<int, array{language: ?string, subject: ?string}>
     */
    protected function extractMultiLanguageSubjects(array $ae): array
    {
        $out = [];
        foreach ($this->list($ae['multi_language_subject_list'] ?? []) as $row) {
            $row = $this->arr($row);
            $out[] = [
                'language' => $this->str($row['language'] ?? $row['locale'] ?? null),
                'subject' => $this->str($row['subject'] ?? $row['title'] ?? null),
            ];
        }

        return array_values(array_filter($out, fn ($r) => ($r['subject'] ?? '') !== ''));
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  Collection<int, AliexpressOrderMetric>  $lines
     * @return array<string, mixed>
     */
    protected function extractOrderAmounts(array $order, Collection $lines): array
    {
        return [
            'order_total' => $this->money($order['order_amount'] ?? $order['total_amount'] ?? null)
                ?? $this->sumLineTotals($lines),
            'pay_amount' => $this->money($order['pay_amount'] ?? null),
            'shipping_cost' => $this->money($order['logistics_amount'] ?? $order['shipping_cost'] ?? null),
            'discount' => $this->money($order['discount_amount'] ?? $order['promotion_amount'] ?? null),
            'tax' => $this->money($order['tax_amount'] ?? null),
            'currency' => $this->moneyCurrency($order['order_amount'] ?? $order['pay_amount'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<int, array<string, mixed>>
     */
    protected function extractLogisticsList(array $order): array
    {
        $out = [];
        $list = $this->list(
            $order['logistic_info_list']['global_aeop_tp_logistic_info_dto']
            ?? $order['logistic_info_list']['aeop_tp_logistics_info_dto']
            ?? $order['logistics_info_list']['aeop_tp_logistics_info_dto']
            ?? $order['logistic_info_list']
            ?? $order['logistics_info_list']
            ?? $order['child_order_list']
            ?? []
        );

        foreach ($list as $row) {
            $row = $this->arr($row);
            $out[] = [
                'service' => $this->str($row['logistics_service_name'] ?? $row['logistics_type'] ?? null),
                'tracking' => $this->str($row['logistics_no'] ?? $row['tracking_number'] ?? null),
                'status' => $this->str($row['logistics_status'] ?? $row['receive_status'] ?? null),
                'status_message' => $this->str($row['recv_status_desc'] ?? $row['status_desc'] ?? null),
                'send_type' => $this->str($row['send_type'] ?? $row['logistics_type_code'] ?? null),
                'receive_status' => $this->str($row['receive_status'] ?? null),
                'shipped_at' => $this->str($row['gmt_send'] ?? null),
                'received_at' => $this->str($row['gmt_received'] ?? null),
            ];
        }

        if ($out === [] && ! empty($order['logistics_no'])) {
            $out[] = [
                'service' => $this->str($order['logistics_type'] ?? null),
                'tracking' => $this->str($order['logistics_no']),
                'status' => null,
                'send_type' => null,
                'receive_status' => null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  Collection<int, AliexpressOrderMetric>  $lines
     * @return array<int, array<string, mixed>>
     */
    protected function extractRichOrderLines(array $order, Collection $lines): array
    {
        $apiProducts = $this->list(
            $order['product_list']['order_product_dto']
            ?? $order['product_list']['aeop_order_product_dto']
            ?? $order['product_list']
            ?? $order['child_order_list']['global_aeop_tp_child_order_dto']
            ?? $order['child_order_list']
            ?? []
        );

        $bySku = [];
        foreach ($lines as $line) {
            $bySku[(string) $line->sku] = $line;
        }

        $out = [];
        foreach ($apiProducts as $product) {
            $product = $this->arr($product);
            $sku = $this->str($product['sku_code'] ?? $product['sku'] ?? $product['skuCode'] ?? null) ?: '__unknown__';
            $db = $bySku[$sku] ?? null;
            $unit = $product['product_unit_price'] ?? $product['product_price'] ?? $product['total_product_amount'] ?? null;

            $out[] = [
                'sku' => $sku,
                'product_id' => $this->str($product['product_id'] ?? $db?->product_id),
                'title' => $this->str($product['product_name'] ?? $product['subject'] ?? $db?->display_title),
                'quantity' => (int) ($product['product_count'] ?? $product['quantity'] ?? $db?->quantity ?? 1),
                'unit_price' => $this->money($unit),
                'line_total' => $this->multiplyMoney($this->money($unit), (int) ($product['product_count'] ?? 1)),
                'image' => $this->resolveOrderLineImage($product, $db),
                'child_order_id' => $this->str($product['child_order_id'] ?? $product['order_sort_id'] ?? null),
                'status' => $this->str($product['order_status'] ?? $product['logistics_status'] ?? null),
                'import_status' => $db?->import_status,
                'shopify_order_id' => $db?->shopify_order_id,
            ];
        }

        if ($out === []) {
            foreach ($lines as $line) {
                $rawLine = is_array($line->raw_payload) ? ($line->raw_payload['line'] ?? []) : [];
                $out[] = [
                    'sku' => $line->sku,
                    'product_id' => $line->product_id,
                    'title' => $line->display_title,
                    'quantity' => $line->quantity ?? 1,
                    'unit_price' => is_numeric($line->amount) ? (float) $line->amount : null,
                    'line_total' => is_numeric($line->amount) ? (float) $line->amount * max(1, (int) $line->quantity) : null,
                    'image' => $this->resolveOrderLineImage($rawLine, $line),
                    'child_order_id' => null,
                    'status' => $line->status,
                    'import_status' => $line->import_status,
                    'shopify_order_id' => $line->shopify_order_id,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function resolveOrderLineImage(array $product, ?AliexpressOrderMetric $db = null): ?string
    {
        foreach ([
            $product['snapshot_small_photo_path'] ?? null,
            $product['product_img_url'] ?? null,
            $product['product_image'] ?? null,
            $product['sku_image'] ?? null,
            $product['image_url'] ?? null,
        ] as $candidate) {
            $normalized = $this->normalizeOrderImageUrl(is_string($candidate) ? $candidate : null);
            if ($normalized) {
                return $normalized;
            }
        }

        $fromAttrs = $this->extractImageFromProductAttributes($product['product_attributes'] ?? null);
        if ($fromAttrs) {
            return $fromAttrs;
        }

        $sku = $this->str($product['sku_code'] ?? $product['sku'] ?? $db?->sku);
        $productId = $this->str($product['product_id'] ?? $db?->product_id);

        return $this->resolveOrderLineImageFallback($sku, $productId);
    }

    protected function normalizeOrderImageUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim(str_replace('\\', '/', $url));
        if ($url === '') {
            return null;
        }

        // Shopify / other non-AE CDNs — keep as-is.
        if (preg_match('#^https?://#i', $url)
            && ! str_contains($url, 'alicdn.com')
            && ! str_contains($url, 'aliexpress-media.com')
            && ! str_contains($url, 'aliexpress.com')) {
            return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
        }

        $filename = $this->extractOrderImageFilename($url);
        if ($filename) {
            return $this->buildAliexpressMediaImageUrl($filename);
        }

        if (preg_match_all('#https?://[^\s"\'<>]+#i', $url, $matches) && $matches[0] !== []) {
            $url = (string) end($matches[0]);
            $filename = $this->extractOrderImageFilename($url);
            if ($filename) {
                return $this->buildAliexpressMediaImageUrl($filename);
            }
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    protected function extractOrderImageFilename(string $url): ?string
    {
        if (preg_match('~/([^/?#]+\.(?:jpg|jpeg|png|gif|webp))(?:\?.*)?$~i', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('~^[^/]+\.(?:jpg|jpeg|png|gif|webp)$~i', $url)) {
            return $url;
        }

        return null;
    }

    protected function buildAliexpressMediaImageUrl(string $filename): string
    {
        return 'https://ae-pic-a1.aliexpress-media.com/kf/'.ltrim($filename, '/');
    }

    protected function extractImageFromProductAttributes(mixed $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        foreach ($this->list($decoded['sku'] ?? []) as $skuRow) {
            $skuRow = $this->arr($skuRow);
            $img = $skuRow['skuImg'] ?? $skuRow['sku_image'] ?? $skuRow['image'] ?? null;
            if (! is_string($img) || trim($img) === '') {
                continue;
            }
            $img = trim($img);
            $normalized = $this->normalizeOrderImageUrl($img);
            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }

    protected function resolveOrderLineImageFallback(?string $sku, ?string $productId): ?string
    {
        if ($sku && ! in_array($sku, ['__order__', '__unknown__'], true)) {
            $shopify = ShopifySku::firstForProductSku($sku);
            if ($shopify?->image_src) {
                return $this->normalizeOrderImageUrl($shopify->image_src);
            }
        }

        if ($productId) {
            $metric = AliexpressMetric::query()->where('product_id', $productId)->first();
            if ($metric && Schema::hasTable('shopify_skus')) {
                $shopify = ShopifySku::firstForProductSku($metric->sku);
                if ($shopify?->image_src) {
                    return $this->normalizeOrderImageUrl($shopify->image_src);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $skuRow
     * @return array<int, array{name: string, value: string}>
     */
    protected function formatSkuProperties(array $skuRow): array
    {
        $out = [];
        foreach ($this->list($skuRow['aeop_s_k_u_property_list'] ?? $skuRow['sku_property_list'] ?? []) as $prop) {
            $prop = $this->arr($prop);
            $name = $this->str($prop['sku_property_name'] ?? $prop['property_name'] ?? null);
            $value = $this->str($prop['property_value_definition_name'] ?? $prop['sku_property_value'] ?? null);
            if ($name && $value) {
                $out[] = ['name' => $name, 'value' => $value];
            }
        }

        return $out;
    }

    protected function extractProductTitle(array $ae): ?string
    {
        foreach (['subject', 'product_name', 'title', 'product_title'] as $key) {
            if (! empty($ae[$key]) && is_string($ae[$key])) {
                return trim($ae[$key]);
            }
        }

        $subjects = $this->extractMultiLanguageSubjects($ae);

        return $subjects[0]['subject'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $addr
     */
    protected function joinAddress(array $addr): ?string
    {
        $parts = array_filter([
            $addr['contact_person'] ?? $addr['receiver'] ?? null,
            $addr['address'] ?? $addr['detail_address'] ?? null,
            $addr['address2'] ?? null,
            $addr['city'] ?? null,
            $addr['province'] ?? $addr['state'] ?? null,
            $addr['zip'] ?? $addr['zip_code'] ?? null,
            $addr['country'] ?? null,
        ]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    /**
     * @param  Collection<int, AliexpressOrderMetric>  $lines
     */
    protected function sumLineTotals(Collection $lines): ?float
    {
        $sum = $lines->sum(fn ($row) => is_numeric($row->amount) ? (float) $row->amount * max(1, (int) $row->quantity) : 0);

        return $sum > 0 ? $sum : null;
    }

    /**
     * @return array<int, string>
     */
    protected function splitImageUrls(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($v) => $this->str($v), $value)));
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $parts = preg_split('/[;,\s]+/', trim($value)) ?: [];

        return array_values(array_filter(array_map(fn ($v) => $this->str($v), $parts)));
    }

    protected function money(mixed $value): ?float
    {
        if (is_array($value)) {
            $value = $value['amount'] ?? $value['value'] ?? null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    protected function moneyCurrency(mixed $value): ?string
    {
        if (is_array($value)) {
            return $this->str($value['currency_code'] ?? $value['currency'] ?? null);
        }

        return null;
    }

    protected function multiplyMoney(?float $amount, int $qty): ?float
    {
        if ($amount === null) {
            return null;
        }

        return $amount * max(1, $qty);
    }

    protected function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            $s = trim((string) $value);

            return $s !== '' ? $s : null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function arr(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?: [];
        }

        return [];
    }

    /**
     * @return array<int, mixed>
     */
    protected function list(mixed $list): array
    {
        $list = $this->arr($list);
        if ($list === []) {
            return [];
        }
        if (! isset($list[0]) && (isset($list['product_id']) || isset($list['sku_code']) || isset($list['order_id']) || isset($list['attr_name']))) {
            return [$list];
        }

        return array_values($list);
    }
}
