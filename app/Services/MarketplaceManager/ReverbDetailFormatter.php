<?php

namespace App\Services\MarketplaceManager;

use App\Models\ReverbMetric;
use App\Models\ReverbOrderMetric;
use App\Models\ReverbPricingPrice;
use App\Models\MarketplaceSyncSettings;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReverbDetailFormatter
{
    /**
     * @param  array<string, mixed>|null  $aeLive
     * @param  array<int, array<string, mixed>>  $aeSkuRows
     * @return array<string, mixed>
     */
    public function formatProduct(?array $aeLive, ?ReverbMetric $metric, ShopifySku $shopify, array $aeSkuRows = []): array
    {
        $ae = $this->unwrapReverbListing($aeLive);
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
        $livePrice = $this->money($ae['price'] ?? null)
            ?? $this->money(is_array($ae['buyer_price'] ?? null) ? ($ae['buyer_price']['amount'] ?? null) : null)
            ?? $cachedPrice;
        $aeStock = $this->resolveProductAeStock($variants, $metric, (string) ($shopify->sku ?? ''));
        if ($aeStock === null && isset($ae['inventory']) && is_numeric($ae['inventory'])) {
            $aeStock = (int) $ae['inventory'];
        }

        $state = is_array($ae['state'] ?? null) ? $ae['state'] : [];
        $condition = is_array($ae['condition'] ?? null) ? $ae['condition'] : [];
        $category = $this->list($ae['categories'] ?? [])[0] ?? null;
        $category = is_array($category) ? $category : [];
        $location = is_array($ae['location'] ?? null) ? $ae['location'] : [];
        $stats = is_array($ae['stats'] ?? null) ? $ae['stats'] : [];
        $returnPolicy = is_array($ae['return_policy'] ?? null) ? $ae['return_policy'] : [];
        $shippingRate = $this->extractReverbShippingDisplay($ae);
        $listingUrl = $this->str($ae['_links']['web']['href'] ?? null);

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
                'rv_stock' => $aeStock,
                'last_synced_at' => $this->resolveListingLastSyncedAt($metric, (string) ($shopify->sku ?? '')),
                'link_synced_at' => $metric?->updated_at,
                'inventory_synced_at' => $this->pricingSyncedAt((string) ($shopify->sku ?? '')),
            ],
            'reverb' => [
                'product_id' => $this->str($ae['product_id'] ?? $ae['id'] ?? $metric?->product_id),
                'title' => $this->extractProductTitle($ae) ?? $metric?->product_name,
                'status' => $this->str($state['description'] ?? $state['slug'] ?? (! empty($ae['live']) ? 'Live' : null)),
                'condition' => $this->str($condition['display_name'] ?? $condition['slug'] ?? null),
                'make' => $this->str($ae['make'] ?? null),
                'model' => $this->str($ae['model'] ?? null),
                'finish' => $this->str($ae['finish'] ?? null),
                'year' => $this->str($ae['year'] ?? null),
                'category' => $this->str($category['full_name'] ?? null),
                'category_id' => $this->str($category['uuid'] ?? null),
                'currency' => $this->str($ae['listing_currency'] ?? (is_array($ae['price'] ?? null) ? ($ae['price']['currency'] ?? null) : null)),
                'shop_name' => $this->str($ae['shop_name'] ?? null),
                'location' => $this->str($location['display_location'] ?? null),
                'handmade' => array_key_exists('handmade', $ae) ? ((bool) $ae['handmade'] ? 'Yes' : 'No') : null,
                'offers_enabled' => array_key_exists('offers_enabled', $ae) ? ((bool) $ae['offers_enabled'] ? 'Yes' : 'No') : null,
                'local_pickup_only' => array_key_exists('local_pickup_only', $ae) ? ((bool) $ae['local_pickup_only'] ? 'Yes' : 'No') : null,
                'shipping_rate' => $shippingRate,
                'return_policy' => $this->str($returnPolicy['description'] ?? null),
                'views' => $stats['views'] ?? null,
                'watches' => $stats['watches'] ?? null,
                'listing_url' => $listingUrl,
                'gmt_create' => $this->str($ae['created_at'] ?? null),
                'gmt_modified' => $this->str($ae['published_at'] ?? null),
                'min_price' => $livePrice,
                'max_price' => $livePrice,
                'cached_price' => $cachedPrice,
                'stock' => $aeStock,
                'images' => $aeImages,
                'main_image' => $aeImages[0] ?? null,
                'descriptions' => $descriptions,
                'variants' => $variants,
                'properties' => $properties,
                'subjects' => [],
            ],
        ];
    }

    /**
     * Prefer the full Reverb listing payload when Manager aliases wrap it.
     *
     * @param  array<string, mixed>|null  $aeLive
     * @return array<string, mixed>
     */
    protected function unwrapReverbListing(?array $aeLive): array
    {
        $ae = $this->arr($aeLive);
        if (isset($ae['raw']) && is_array($ae['raw']) && $ae['raw'] !== []) {
            return array_merge($ae['raw'], array_filter([
                'product_id' => $ae['product_id'] ?? null,
                'sku' => $ae['sku'] ?? null,
                'product_name' => $ae['product_name'] ?? null,
                'inventory' => $ae['inventory'] ?? null,
                'price' => $ae['price'] ?? null,
            ], static fn ($v) => $v !== null && $v !== ''));
        }

        return $ae;
    }

    /**
     * @param  array<string, mixed>  $ae
     */
    protected function extractReverbShippingDisplay(array $ae): ?string
    {
        $shipping = is_array($ae['shipping'] ?? null) ? $ae['shipping'] : [];
        $rate = $shipping['user_region_rate']['rate']['display']
            ?? $shipping['rates'][0]['rate']['display']
            ?? null;
        if (is_string($rate) && trim($rate) !== '') {
            return trim($rate);
        }

        $amount = $shipping['user_region_rate']['rate']['amount']
            ?? $shipping['rates'][0]['rate']['amount']
            ?? null;

        return is_numeric($amount) ? '$'.number_format((float) $amount, 2) : null;
    }

    /**
     * @param  array<string, mixed>  $orderRoot
     * @param  Collection<int, ReverbOrderMetric>  $lines
     * @return array<string, mixed>
     */
    public function formatOrder(array $orderRoot, Collection $lines, ReverbOrderMetric $primaryLine): array
    {
        $order = $this->arr($orderRoot);
        $addr = $this->arr($order['shipping_address'] ?? $order['receipt_address'] ?? []);
        $buyer = $this->arr($order['buyer_info'] ?? []);
        $amounts = $this->extractOrderAmounts($order, $lines);
        $funds = $this->extractOrderFunds($order);
        $logistics = $this->extractLogisticsList($order);
        $apiLines = $this->extractRichOrderLines($order, $lines);
        $phone = $this->formatOrderPhone($addr)
            ?? $this->str($addr['phone'] ?? $addr['unformatted_phone'] ?? $order['buyer_phone'] ?? null);

        $recipient = $this->str(
            $addr['name']
            ?? $addr['contact_person']
            ?? $addr['receiver']
            ?? $order['buyer_name']
            ?? null
        );
        $email = $this->resolveBuyerEmail($buyer, $addr, $order);
        $firstName = $this->str($order['buyer_first_name'] ?? $buyer['first_name'] ?? null);
        $lastName = $this->str($order['buyer_last_name'] ?? $buyer['last_name'] ?? null);
        if (($firstName === null || $firstName === '') && $recipient) {
            $parts = preg_split('/\s+/', trim($recipient), 2) ?: [];
            $firstName = $parts[0] ?? null;
            $lastName = $lastName ?: ($parts[1] ?? null);
        }

        return [
            'summary' => [
                'order_id' => (string) (
                    $order['order_number']
                    ?? $order['order_id']
                    ?? $primaryLine->orderRef()
                    ?: ($order['id'] ?? '')
                ),
                'order_number' => $order['order_number']
                    ?? $primaryLine->order_number
                    ?? $primaryLine->order_id
                    ?? null,
                'status' => $order['status'] ?? $order['order_status'] ?? $primaryLine->status,
                'buyer_remark' => $this->str(
                    is_array($order['order_notes'] ?? null) && $order['order_notes'] !== []
                        ? (string) ($order['order_notes'][0]['body'] ?? $order['order_notes'][0] ?? '')
                        : ($order['buyer_remark'] ?? $order['memo'] ?? null)
                ),
                'seller_remark' => $this->str($order['seller_remark'] ?? null),
                'buyer_login_id' => $this->str(
                    $order['buyer_id']
                    ?? $buyer['login_id']
                    ?? $order['buyerloginid']
                    ?? $order['buyer_login_id']
                    ?? null
                ),
                'created' => $order['created_at'] ?? $order['gmt_create'] ?? $order['create_time'] ?? $primaryLine->order_date,
                'paid' => $order['paid_at'] ?? $order['gmt_pay_time'] ?? $order['gmt_pay_success'] ?? $primaryLine->order_paid_at,
                'sent' => $order['shipped_at'] ?? $order['shipping_date'] ?? $order['gmt_send_goods_time'] ?? $this->firstLogisticsField($logistics, 'shipped_at'),
                'finished' => $order['gmt_receive_goods_time'] ?? $order['end_time'] ?? null,
                'modified' => $order['updated_at'] ?? $order['gmt_modified'] ?? null,
            ],
            'amounts' => $amounts,
            'funds' => $funds,
            'buyer' => [
                'name' => $firstName,
                'last_name' => $lastName,
                'login_id' => $this->str($order['buyer_id'] ?? $buyer['login_id'] ?? null),
                'email' => $email,
                'phone' => $phone,
                'country' => $this->str($addr['country_code'] ?? $buyer['country'] ?? $addr['country'] ?? $addr['country_name'] ?? null),
            ],
            'shipping' => [
                'recipient' => $recipient,
                'detail_address' => $this->str($addr['street_address'] ?? $addr['detail_address'] ?? null),
                'address_line_1' => $this->str($addr['street_address'] ?? $addr['address'] ?? null),
                'address_line_2' => $this->str($addr['extended_address'] ?? $addr['address2'] ?? null),
                'city' => $this->str($addr['locality'] ?? $addr['city'] ?? null),
                'province' => $this->str($addr['region'] ?? $addr['province'] ?? $addr['state'] ?? null),
                'zip' => $this->str($addr['postal_code'] ?? $addr['zip'] ?? $addr['zip_code'] ?? null),
                'country' => $this->str($addr['country_code'] ?? $addr['country'] ?? null),
                'country_name' => $this->str($addr['display_location'] ?? $addr['country_name'] ?? null),
                'localized_address' => $this->str($addr['display_location'] ?? $addr['localized_address'] ?? null),
                'email' => $email,
                'phone' => $phone,
                'tax_number' => $this->str($addr['tax_number'] ?? $addr['cpf'] ?? null),
                'full_address' => $this->joinReverbAddress($addr, $recipient),
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
                'method' => $this->str($order['payment_method'] ?? $order['payment_type'] ?? $order['pay_type'] ?? null),
                'currency' => $funds['currency']
                    ?? $this->moneyCurrency($order['total'] ?? $order['amount_product'] ?? null)
                    ?? ($amounts['currency'] ?? null),
                'paid_at' => $order['paid_at'] ?? $order['gmt_pay_time'] ?? $order['gmt_pay_success'] ?? null,
                'total_paid' => $funds['customer_total_paid'] ?? $amounts['pay_amount'] ?? $this->money($order['total'] ?? null),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $addr
     */
    protected function joinReverbAddress(array $addr, ?string $recipient = null): ?string
    {
        $parts = array_filter([
            $recipient,
            $addr['street_address'] ?? $addr['address'] ?? $addr['detail_address'] ?? null,
            $addr['extended_address'] ?? $addr['address2'] ?? null,
            $addr['locality'] ?? $addr['city'] ?? null,
            $addr['region'] ?? $addr['province'] ?? $addr['state'] ?? null,
            $addr['postal_code'] ?? $addr['zip'] ?? $addr['zip_code'] ?? null,
            $addr['country_code'] ?? $addr['country'] ?? $addr['country_name'] ?? null,
        ]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    /**
     * Build Shopify REST order payload from formatted Reverb order detail.
     *
     * @param  array<string, mixed>  $detail
     * @param  Collection<int, ReverbOrderMetric>  $lines
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
            'Imported from Reverb Order #'.$orderRef,
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
        $taxAmount = (float) ($amounts['tax'] ?? $funds['tax'] ?? 0);
        if ($taxAmount > 0) {
            $noteLines[] = sprintf('Tax: %s %.2f', $currency, $taxAmount);
        }
        foreach ([
            'Platform commission' => $funds['platform_commission'] ?? null,
            'Transaction service fee' => $funds['transaction_service_fee'] ?? null,
            'Tax on fees' => $funds['tax_on_fees'] ?? $funds['platform_offer_tax'] ?? null,
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
            ['name' => 'reverb_order_id', 'value' => $orderRef],
        ];
        if ($emailIsPlaceholder) {
            $noteAttrs[] = ['name' => 'reverb_email_is_placeholder', 'value' => 'true'];
        }
        foreach ([
            'reverb_buyer_login' => $summary['buyer_login_id'] ?? null,
            'reverb_payment_method' => $payment['method'] ?? null,
            'reverb_tracking_number' => $shipment['tracking'] ?? null,
            'reverb_shipping_method' => $shipment['service'] ?? null,
            'reverb_tax_number' => $shipping['tax_number'] ?? null,
            'reverb_buyer_email' => $shipping['email'] ?? null,
            'reverb_buyer_phone' => $shipping['phone'] ?? null,
            'reverb_platform_promotion' => isset($funds['platform_promotion']) ? number_format((float) $funds['platform_promotion'], 2, '.', '') : null,
            'reverb_platform_offer' => isset($funds['platform_offer']) ? number_format((float) $funds['platform_offer'], 2, '.', '') : null,
            'reverb_order_amount' => isset($funds['order_amount']) ? number_format((float) $funds['order_amount'], 2, '.', '') : null,
            'reverb_customer_paid' => $customerPaid !== null ? number_format($customerPaid, 2, '.', '') : null,
            'reverb_seller_amount_paid' => isset($funds['seller_amount_paid']) ? number_format((float) $funds['seller_amount_paid'], 2, '.', '') : null,
            'reverb_platform_commission' => isset($funds['platform_commission']) ? number_format((float) $funds['platform_commission'], 2, '.', '') : null,
            'reverb_transaction_service_fee' => isset($funds['transaction_service_fee']) ? number_format((float) $funds['transaction_service_fee'], 2, '.', '') : null,
            'reverb_tax' => $taxAmount > 0 ? number_format($taxAmount, 2, '.', '') : null,
            'reverb_tax_on_fees' => isset($funds['tax_on_fees']) ? number_format((float) $funds['tax_on_fees'], 2, '.', '') : (isset($funds['platform_offer_tax']) ? number_format((float) $funds['platform_offer_tax'], 2, '.', '') : null),
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

        $shippingCost = (float) ($amounts['shipping_cost'] ?? $funds['shipping_cost'] ?? 0);
        if ($shippingCost > 0) {
            $payload['shipping_lines'] = [[
                'title' => (string) ($shipment['service'] ?? 'Reverb shipping'),
                'price' => number_format($shippingCost, 2, '.', ''),
                'code' => 'reverb',
            ]];
        }

        // Buyer-paid tax (matches LitCommerce "Taxes" / Reverb amount_tax).
        if ($taxAmount > 0) {
            $taxableSubtotal = 0.0;
            foreach ($lineItems as $item) {
                $taxableSubtotal += ((float) ($item['price'] ?? 0)) * max(1, (int) ($item['quantity'] ?? 1));
            }
            $rate = $taxableSubtotal > 0 ? round($taxAmount / $taxableSubtotal, 4) : 0.0;
            $pctLabel = $rate > 0
                ? rtrim(rtrim(number_format($rate * 100, 2, '.', ''), '0'), '.')
                : null;
            $payload['taxes_included'] = false;
            $payload['tax_lines'] = [[
                'title' => $pctLabel !== null && $pctLabel !== '' ? 'TAX '.$pctLabel.'%' : 'Tax',
                'price' => number_format($taxAmount, 2, '.', ''),
                'rate' => $rate,
                'channel_liable' => false,
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
        $lineSubtotal += $shippingCost + max(0.0, $taxAmount);

        if ($customerPaid !== null && $lineSubtotal > 0 && $customerPaid < $lineSubtotal) {
            $payload['discount_codes'] = [[
                'code' => 'Reverb promotion',
                'amount' => number_format(round($lineSubtotal - $customerPaid, 2), 2, '.', ''),
                'type' => 'fixed_amount',
            ]];
        }

        if ($customerPaid !== null && $customerPaid > 0) {
            $payload['transactions'] = [[
                'kind' => 'sale',
                'status' => 'success',
                'amount' => number_format($customerPaid, 2, '.', ''),
                'gateway' => 'reverb',
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
            return ['Reverb', 'Customer'];
        }

        $parts = preg_split('/\s+/u', $fullName, 2) ?: [];
        $firstName = (string) ($parts[0] ?? 'Reverb');
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
        $settings ??= MarketplaceSyncSettings::getFor('reverb');
        $handle = trim((string) (
            $settings['order']['shopify_source_name']
            ?? config('services.reverb.shopify_source_name')
            ?? 'reverb'
        ));
        $urlTemplate = (string) (
            $settings['order']['shopify_source_url_template']
            ?? config('services.reverb.shopify_source_url_template')
            ?? 'https://reverb.com/my/selling/orders/{order_id}'
        );

        return [
            'source_name' => $handle !== '' ? $handle : 'reverb',
            'source_identifier' => $orderRef,
            'source_url' => str_replace('{order_id}', $orderRef, $urlTemplate),
            'referring_site' => 'https://www.reverb.com/',
        ];
    }

    public function shopifySourceDisplayName(?array $settings = null): string
    {
        $settings ??= MarketplaceSyncSettings::getFor('reverb');
        $name = trim((string) (
            $settings['order']['shopify_source_display_name']
            ?? config('services.reverb.shopify_source_display_name')
            ?? 'Reverb'
        ));

        return $name !== '' ? $name : 'Reverb';
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

        $domain = (string) env('REVERB_SHOPIFY_PLACEHOLDER_EMAIL_DOMAIN', 'import.5coremanagement.com');
        $slug = preg_replace('/[^a-zA-Z0-9]/', '', $orderRef) ?: 'order';

        return ['reverb-'.$slug.'@'.$domain, true];
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
        // Reverb selling orders expose fee/payout money objects directly.
        if (isset($order['amount_product']) || isset($order['direct_checkout_payout']) || isset($order['selling_fee'])) {
            $productTotal = $this->money($order['amount_product'] ?? $order['amount_product_subtotal'] ?? null);
            $shippingCost = $this->money($order['shipping'] ?? null);
            $tax = $this->money($order['amount_tax'] ?? null);
            $orderAmount = $this->money($order['total'] ?? null);
            $sellingFee = $this->money($order['selling_fee'] ?? null);
            $bumpFee = $this->money($order['bump_fee'] ?? null);
            $checkoutFee = $this->money($order['direct_checkout_fee'] ?? null);
            $taxOnFees = $this->money($order['tax_on_fees'] ?? null);
            $sellerPayout = $this->money($order['direct_checkout_payout'] ?? null);
            $transactionFee = null;
            if ($sellingFee !== null || $bumpFee !== null || $checkoutFee !== null) {
                $transactionFee = round(($sellingFee ?? 0) + ($bumpFee ?? 0) + ($checkoutFee ?? 0) + ($taxOnFees ?? 0), 2);
            }

            return [
                'product_total' => $productTotal,
                'shipping_cost' => $shippingCost,
                'adjustment' => null,
                'store_promotion' => null,
                'platform_promotion' => null,
                'platform_offer' => null,
                'order_amount' => $orderAmount,
                'platform_commission' => $sellingFee,
                'affiliate_commission' => $bumpFee,
                'cashback_paid_by_seller' => null,
                'transaction_service_fee' => $checkoutFee ?? $transactionFee,
                // Buyer sales tax (amount_tax) — separate from tax_on_fees (seller fee tax).
                'platform_offer_tax' => $taxOnFees,
                'customer_total_paid' => $orderAmount,
                'amount_paid' => $orderAmount,
                'seller_amount_paid' => $sellerPayout,
                'loan_status' => null,
                'fund_status' => $this->str($order['tax_responsible_party'] ?? null),
                'currency' => $this->moneyCurrency($order['total'] ?? $order['amount_product'] ?? null) ?? 'USD',
                'selling_fee' => $sellingFee,
                'bump_fee' => $bumpFee,
                'direct_checkout_fee' => $checkoutFee,
                'tax' => $tax,
                'tax_on_fees' => $taxOnFees,
            ];
        }

        $fundContext = $this->resolveFundContext($order);
        $order = $fundContext['order'];
        $childOrders = $fundContext['child_orders'];
        $loanSonOrders = $fundContext['loan_son_orders'];

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

        $platformCommission = $this->sumLoanSonMoney($loanSonOrders, 'escrow_fee')
            ?? $this->money($order['escrow_fee'] ?? $order['platform_commission'] ?? null);
        if ($platformCommission === null) {
            foreach ($childOrders as $child) {
                $child = $this->arr($child);
                if (($fee = $this->money($child['escrow_fee'] ?? null)) !== null) {
                    $platformCommission = ($platformCommission ?? 0) + $fee;
                }
            }
            if ($platformCommission !== null && $platformCommission <= 0) {
                $platformCommission = null;
            }
        }
        if ($platformCommission === null && $orderAmount !== null && $escrowRate !== null) {
            $platformCommission = round($orderAmount * $escrowRate, 2);
        }

        $transactionFee = $this->firstFundMoney(
            $order,
            $loanSonOrders,
            ['transaction_service_fee', 'service_fee', 'transaction_fee']
        );
        $platformTax = $this->firstFundMoney(
            $order,
            $loanSonOrders,
            ['platform_offer_tax', 'platform_tax', 'tax_amount', 'offer_tax']
        );
        $affiliateCommission = $this->sumLoanSonMoney($loanSonOrders, 'affiliate_commission')
            ?? $this->money($order['affiliate_fee'] ?? $order['affiliate_commission'] ?? null);
        $cashbackPaidBySeller = $this->money($order['cashback_fee'] ?? $order['seller_cashback'] ?? null);

        $platformPromotion = $this->sumDiscountsByOwner($childOrders, 'PLATFORM');
        $storePromotion = $this->extractStorePromotion($order, $childOrders, $productTotal, $orderAmount);
        $platformOffer = $this->resolvePlatformOfferAmount($order, $childOrders, $platformPromotion, $orderAmount);
        $shippingCost = $this->money($order['logistics_amount'] ?? null) ?? 0.0;
        $customerTotalPaid = $this->resolveCustomerPaidAmount($order, $orderAmount, $platformPromotion, $platformOffer, $shippingCost);
        $sellerAmountPaid = $this->resolveSellerPaidAmount(
            $order,
            $childOrders,
            $loanSonOrders,
            $orderAmount,
            $platformCommission,
            $transactionFee,
            $platformTax,
            $affiliateCommission,
            $cashbackPaidBySeller,
            $platformOffer
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
            'loan_status' => $this->str($order['loan_status'] ?? null),
            'fund_status' => $this->str($order['fund_status'] ?? null),
            'currency' => $this->moneyCurrency($order['order_amount'] ?? $order['pay_amount'] ?? null)
                ?? $this->str($order['settlement_currency'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array{order: array<string, mixed>, child_orders: array<int, array<string, mixed>>, loan_son_orders: array<int, array<string, mixed>>}
     */
    protected function resolveFundContext(array $order): array
    {
        $sources = $this->arr($order['fund_sources'] ?? []);
        $tradeDetail = $this->arr($sources['trade_detail'] ?? $order['trade_detail'] ?? []);
        $loanFund = $this->arr($sources['loan_fund'] ?? $order['loan_fund'] ?? []);
        $loanSonOrders = $this->list($loanFund['son_orders'] ?? []);

        if ($loanSonOrders === []) {
            $firstLoan = $this->arr($loanFund['first'] ?? []);
            $loanSonOrders = $this->list($firstLoan['son_order_list']['son_order_loan_vo'] ?? $firstLoan['son_order_list'] ?? []);
        }

        $childOrders = $this->list(
            $order['child_order_list']['global_aeop_tp_child_order_dto']
            ?? $order['child_order_list']
            ?? []
        );

        if ($tradeDetail !== []) {
            $order = array_replace($order, array_filter([
                'pay_amount_by_settlement_cur' => $tradeDetail['pay_amount_by_settlement_cur'] ?? null,
                'settlement_currency' => $tradeDetail['settlement_currency'] ?? null,
                'loan_status' => $tradeDetail['loan_status'] ?? null,
                'fund_status' => $tradeDetail['fund_status'] ?? null,
                'escrow_fee' => $tradeDetail['escrow_fee'] ?? null,
                'escrow_fee_rate' => $tradeDetail['escrow_fee_rate'] ?? null,
                'gmt_pay_success' => $tradeDetail['gmt_pay_success'] ?? null,
                'gmt_pay_time' => $tradeDetail['gmt_pay_time'] ?? null,
                'loan_info' => $tradeDetail['loan_info'] ?? null,
                'payment_amount' => $tradeDetail['payment_amount'] ?? null,
                'promotion_fee' => $tradeDetail['promotion_fee'] ?? null,
                'seller_order_amount' => $tradeDetail['seller_order_amount'] ?? null,
                'new_seller_order_amount' => $tradeDetail['new_seller_order_amount'] ?? null,
            ], fn ($value) => $value !== null && $value !== '' && $value !== []));
        }

        return [
            'order' => $order,
            'child_orders' => $childOrders,
            'loan_son_orders' => $loanSonOrders,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $loanSonOrders
     */
    protected function sumLoanSonMoney(array $loanSonOrders, string $field): ?float
    {
        $sum = 0.0;
        $found = false;
        foreach ($loanSonOrders as $row) {
            $amount = $this->money($this->arr($row)[$field] ?? null);
            if ($amount !== null && $amount > 0) {
                $sum += $amount;
                $found = true;
            }
        }

        return $found ? round($sum, 2) : null;
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<int, array<string, mixed>>  $loanSonOrders
     * @param  array<int, string>  $keys
     */
    protected function firstFundMoney(array $order, array $loanSonOrders, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (($amount = $this->money($order[$key] ?? null)) !== null) {
                return $amount;
            }
        }

        foreach ($loanSonOrders as $row) {
            $row = $this->arr($row);
            foreach ($keys as $key) {
                if (($amount = $this->money($row[$key] ?? null)) !== null) {
                    return $amount;
                }
            }
        }

        $sources = $this->arr($order['fund_sources'] ?? []);
        foreach (['trade_detail', 'loan_fund'] as $bucket) {
            if (($amount = $this->findFundMoneyByKeys($this->arr($sources[$bucket] ?? []), $keys)) !== null) {
                return $amount;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    protected function findFundMoneyByKeys(array $data, array $keys, int $depth = 0): ?float
    {
        if ($depth > 8 || $data === []) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && ($amount = $this->money($data[$key])) !== null) {
                return $amount;
            }
        }

        foreach ($data as $value) {
            if (! is_array($value)) {
                continue;
            }
            if (($amount = $this->findFundMoneyByKeys($this->arr($value), $keys, $depth + 1)) !== null) {
                return $amount;
            }
        }

        return null;
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
     * Sum discount rows from child_order_discount_detail_list for a promotion owner.
     *
     * @param  array<int, array<string, mixed>>  $childOrders
     */
    protected function sumDiscountsByOwner(array $childOrders, string $owner): float
    {
        $owner = strtoupper(trim($owner));
        $total = 0.0;

        foreach ($childOrders as $child) {
            $child = $this->arr($child);
            foreach ($this->list($child['child_order_discount_detail_list'] ?? []) as $row) {
                $row = $this->arr($row);
                if (strtoupper((string) ($row['promotion_owner'] ?? '')) !== $owner) {
                    continue;
                }
                $total += $this->money($row['discount_detail'] ?? null) ?? 0.0;
            }
        }

        return $total > 0 ? round($total, 2) : 0.0;
    }

    /**
     * Store / seller coupon discounts (shopCoupon with promotion_owner SELLER).
     *
     * @param  array<int, array<string, mixed>>  $childOrders
     */
    protected function extractStorePromotion(
        array $order,
        array $childOrders,
        ?float $productTotal,
        ?float $orderAmount
    ): ?float {
        $fromDetails = $this->sumDiscountsByOwner($childOrders, 'SELLER');
        if ($fromDetails > 0) {
            return $fromDetails;
        }

        $promotionFee = $this->money($order['promotion_fee'] ?? null);
        if ($promotionFee !== null && $promotionFee > 0 && $this->sumDiscountsByOwner($childOrders, 'PLATFORM') <= 0) {
            return $promotionFee;
        }

        foreach ([
            $order['promotion_amount'] ?? null,
            $order['seller_discount_amount'] ?? null,
        ] as $candidate) {
            if (($amount = $this->money($candidate)) !== null && $amount > 0) {
                return $amount;
            }
        }

        foreach ($childOrders as $child) {
            $child = $this->arr($child);
            if (($amount = $this->money($child['child_order_discount_info'] ?? null)) !== null && $amount > 0) {
                $hasPlatform = $this->sumDiscountsByOwner([$child], 'PLATFORM') > 0;
                $hasSeller = $this->sumDiscountsByOwner([$child], 'SELLER') > 0;
                if ($hasSeller && ! $hasPlatform) {
                    return $amount;
                }
            }
        }

        if ($productTotal !== null && $orderAmount !== null && $productTotal > $orderAmount) {
            return round($productTotal - $orderAmount, 2);
        }

        return null;
    }

    /**
     * Platform subsidy shown in AE fund UI ("Platform offer" / expected available).
     *
     * @param  array<int, array<string, mixed>>  $childOrders
     */
    protected function resolvePlatformOfferAmount(
        array $order,
        array $childOrders,
        float $platformPromotion,
        ?float $orderAmount
    ): ?float {
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
                if (($amount = $this->money($row['platform_offer_amount'] ?? null)) !== null) {
                    return $amount;
                }
            }
        }

        if ($platformPromotion <= 0) {
            return null;
        }

        $paymentAmount = $this->money($order['payment_amount'] ?? null);
        $settlementPay = $this->money($order['pay_amount_by_settlement_cur'] ?? null);
        if ($paymentAmount !== null && $settlementPay !== null && $settlementPay > $paymentAmount) {
            return round($settlementPay - $paymentAmount, 2);
        }

        if ($settlementPay === null || $orderAmount === null || $settlementPay <= $orderAmount) {
            return null;
        }

        // Platform subsidy orders: AE seller UI rounds platform coupon down to nearest $0.10 (2.93 -> 2.90).
        return floor(round($platformPromotion, 2) * 10) / 10;
    }

    protected function resolveCustomerPaidAmount(
        array $order,
        ?float $orderAmount,
        float $platformPromotion,
        ?float $platformOffer,
        float $shippingCost = 0.0
    ): ?float {
        if (($paid = $this->money($order['payment_amount'] ?? null)) !== null) {
            return $paid;
        }

        if (($paid = $this->money($order['pay_amount'] ?? null)) !== null) {
            return $paid;
        }

        $settlementPay = $this->money($order['pay_amount_by_settlement_cur'] ?? null);
        if ($settlementPay !== null && $platformOffer !== null && $platformOffer > 0 && $settlementPay > ($orderAmount ?? 0)) {
            // Platform subsidy: customer paid = settlement pay - platform offer.
            return round($settlementPay - $platformOffer, 2);
        }

        if ($orderAmount !== null) {
            // Store coupons are already reflected in order_amount; buyer pays order total + shipping.
            return round($orderAmount + $shippingCost, 2);
        }

        return $settlementPay;
    }

    protected function resolveSellerPaidAmount(
        array $order,
        array $childOrders,
        array $loanSonOrders,
        ?float $orderAmount,
        ?float $platformCommission,
        ?float $transactionFee,
        ?float $platformTax,
        ?float $affiliateCommission,
        ?float $cashbackPaidBySeller,
        ?float $platformOffer = null
    ): ?float {
        if (($sellerPaid = $this->sumLoanSonMoney($loanSonOrders, 'real_loan_amount')) !== null) {
            return $sellerPaid;
        }

        foreach ([
            $order['new_seller_order_amount'] ?? null,
            $order['seller_order_amount'] ?? null,
        ] as $candidate) {
            $sellerOrderAmount = $this->money($candidate);
            if ($sellerOrderAmount === null) {
                continue;
            }
            $fees = array_values(array_filter(
                [$platformCommission, $transactionFee, $platformTax, $affiliateCommission, $cashbackPaidBySeller],
                fn ($fee) => $fee !== null && $fee > 0
            ));
            if ($fees !== [] && $orderAmount !== null && $sellerOrderAmount < $orderAmount) {
                return round(max(0, $sellerOrderAmount - array_sum($fees)), 2);
            }
        }

        foreach ([$this->arr($order['loan_info'] ?? [])] as $loanInfo) {
            if (($loanAmount = $this->money($loanInfo['loan_amount'] ?? null)) !== null) {
                return $loanAmount;
            }
        }

        foreach ($childOrders as $child) {
            $child = $this->arr($child);
            if (($loanAmount = $this->money($this->arr($child['loan_info'] ?? [])['loan_amount'] ?? null)) !== null) {
                return $loanAmount;
            }
        }

        if (($loanAmount = $this->sumLoanSonMoney($loanSonOrders, 'loan_amount')) !== null) {
            return $loanAmount;
        }

        if ($orderAmount === null) {
            return null;
        }

        $fees = array_values(array_filter(
            [$platformCommission, $transactionFee, $platformTax, $affiliateCommission, $cashbackPaidBySeller],
            fn ($fee) => $fee !== null && $fee > 0
        ));

        if ($fees === []) {
            return null;
        }

        // Platform-subsidy orders deduct transaction service fee and platform offer tax in AE
        // seller UI; those amounts are not exposed until loan settlement in the Open API.
        if ($platformOffer !== null && $platformOffer > 0
            && ($transactionFee === null || $platformTax === null)
            && $this->str($order['loan_status'] ?? null) === 'loan_none') {
            return null;
        }

        return round(max(0, $orderAmount - array_sum($fees)), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $logistics
     * @return array<string, mixed>
     */
    protected function extractShipmentSummary(array $order, array $logistics): array
    {
        $first = $logistics[0] ?? [];

        return [
            'shipped_at' => $first['shipped_at']
                ?? $order['shipped_at']
                ?? $order['shipping_date']
                ?? $order['gmt_send_goods_time']
                ?? null,
            'service' => $first['service']
                ?? $this->str($order['shipping_provider'] ?? $order['shipping_method'] ?? $order['logistics_type'] ?? null),
            'tracking' => $first['tracking']
                ?? $this->str($order['shipping_code'] ?? $order['logistics_no'] ?? null),
            'status' => $first['status']
                ?? $this->str($order['shipment_status'] ?? $order['logistics_status'] ?? null),
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

        foreach ($this->list($ae['photos'] ?? []) as $photo) {
            $photo = $this->arr($photo);
            $links = $this->arr($photo['_links'] ?? []);
            foreach (['full', 'large_crop', 'thumbnail', 'small_crop'] as $rel) {
                $href = $this->str($links[$rel]['href'] ?? null);
                if ($href) {
                    $urls[] = $href;
                    break;
                }
            }
            foreach (['url', 'image_url', 'href'] as $key) {
                $urls = array_merge($urls, $this->splitImageUrls($photo[$key] ?? null));
            }
        }

        if ($urls === []) {
            foreach ($this->list($ae['cloudinary_photos'] ?? []) as $photo) {
                $photo = $this->arr($photo);
                $urls = array_merge($urls, $this->splitImageUrls($photo['preview_url'] ?? $photo['url'] ?? null));
            }
        }

        foreach ([
            $ae['main_image_url'] ?? null,
            $ae['product_main_image'] ?? null,
            $ae['image_url'] ?? null,
        ] as $url) {
            $urls = array_merge($urls, $this->splitImageUrls($url));
        }

        $urls = array_merge($urls, $this->splitImageUrls($ae['image_u_r_ls'] ?? $ae['image_urls'] ?? null));

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

        if (! empty($ae['description'])) {
            $candidates[] = ['language' => null, 'web' => $ae['description'], 'mobile' => null];
        }

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
                if (! empty($ae[$key]) && $key !== 'description') {
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
     * Reverb descriptions are often JSON module trees (moduleList / mobileDetail), not plain HTML.
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
    protected function formatProductVariants(array $ae, array $aeSkuRows, ?ReverbMetric $metric = null, ?ShopifySku $shopify = null): array
    {
        $variants = [];

        $sku = $this->str($ae['sku'] ?? null);
        $productId = $this->str($ae['product_id'] ?? $ae['id'] ?? null);
        if ($sku && $productId && $sku !== $productId) {
            $price = $this->money($ae['price'] ?? null);
            if ($price === null && is_array($ae['buyer_price'] ?? null)) {
                $price = $this->money($ae['buyer_price']['amount'] ?? null);
            }
            $variants[] = [
                'sku' => $sku,
                'price' => $price,
                'stock' => is_numeric($ae['inventory'] ?? null) ? (int) $ae['inventory'] : null,
                'image' => null,
                'ean' => null,
                'properties' => [],
                'source' => 'reverb',
            ];
        }

        if ($variants === [] && $aeSkuRows !== []) {
            foreach ($aeSkuRows as $row) {
                $variants[] = [
                    'sku' => $this->str($row['sku'] ?? null),
                    'price' => $this->money($row['price'] ?? null),
                    'stock' => $row['stock'] ?? $row['inventory'] ?? null,
                    'image' => null,
                    'ean' => null,
                    'properties' => [],
                    'source' => 'reverb',
                ];
            }
        }

        if ($variants === [] && $metric && $metric->product_id && $metric->sku && $metric->sku !== $metric->product_id) {
            $variants[] = [
                'sku' => $this->str($metric->sku),
                'price' => $this->money($metric->price),
                'stock' => $this->localAeStockForSku((string) $metric->sku),
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
     * Prefer live/API variant stock for this SKU, else local rv_stock from pricing sync.
     *
     * @param  array<int, array<string, mixed>>  $variants
     */
    protected function resolveProductAeStock(array $variants, ?ReverbMetric $metric, string $shopifySku): ?int
    {
        $targets = array_values(array_filter([
            strtoupper(trim($shopifySku)),
            strtoupper(trim((string) ($metric?->sku ?? ''))),
        ]));

        foreach ($variants as $variant) {
            if (($variant['source'] ?? '') === 'shopify') {
                continue;
            }
            $sku = strtoupper(trim((string) ($variant['sku'] ?? '')));
            if ($sku === '' || ($targets !== [] && ! in_array($sku, $targets, true))) {
                continue;
            }
            if (isset($variant['stock']) && $variant['stock'] !== null && $variant['stock'] !== '') {
                return (int) $variant['stock'];
            }
        }

        foreach ($targets as $sku) {
            if (($stock = $this->localAeStockForSku($sku)) !== null) {
                return $stock;
            }
        }

        $aeOnly = array_values(array_filter(
            $variants,
            static fn ($v) => ($v['source'] ?? '') !== 'shopify'
        ));
        if (count($aeOnly) === 1 && isset($aeOnly[0]['stock']) && $aeOnly[0]['stock'] !== null && $aeOnly[0]['stock'] !== '') {
            return (int) $aeOnly[0]['stock'];
        }

        return null;
    }

    /**
     * Latest of link-map sync and inventory/price sync for this listing.
     */
    protected function resolveListingLastSyncedAt(?ReverbMetric $metric, string $shopifySku): ?\Carbon\Carbon
    {
        $candidates = array_filter([
            $metric?->updated_at,
            $this->pricingSyncedAt($shopifySku),
        ]);

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->sortByDesc(fn ($dt) => $dt->getTimestamp())->first();
    }

    protected function pricingSyncedAt(string $shopifySku): ?\Carbon\Carbon
    {
        $sku = trim($shopifySku);
        if ($sku === '' || ! Schema::hasTable('reverb_pricing_prices')) {
            return null;
        }

        $row = ReverbPricingPrice::query()
            ->where(function ($q) use ($sku) {
                $q->where('sku', $sku)->orWhere('sku', strtoupper($sku));
            })
            ->orderByDesc('updated_at')
            ->first(['updated_at']);

        return $row?->updated_at;
    }

    protected function localAeStockForSku(string $sku): ?int
    {
        $sku = strtoupper(trim($sku));
        if ($sku === '' || ! Schema::hasTable('reverb_pricing_prices')) {
            return null;
        }

        $row = ReverbPricingPrice::query()
            ->where(function ($q) use ($sku) {
                $q->where('sku', $sku)->orWhere('sku', trim($sku));
            })
            ->first();

        if (! $row || $row->rv_stock === null) {
            return null;
        }

        return (int) $row->rv_stock;
    }

    /**
     * @param  array<string, mixed>  $ae
     * @return array<int, array{name: string, value: string}>
     */
    protected function extractProductProperties(array $ae): array
    {
        $out = [];
        $condition = is_array($ae['condition'] ?? null) ? $ae['condition'] : [];
        $category = $this->list($ae['categories'] ?? [])[0] ?? null;
        $category = is_array($category) ? $category : [];
        $location = is_array($ae['location'] ?? null) ? $ae['location'] : [];
        $stats = is_array($ae['stats'] ?? null) ? $ae['stats'] : [];
        $returnPolicy = is_array($ae['return_policy'] ?? null) ? $ae['return_policy'] : [];

        $pairs = [
            'Make' => $ae['make'] ?? null,
            'Model' => $ae['model'] ?? null,
            'Finish' => $ae['finish'] ?? null,
            'Year' => $ae['year'] ?? null,
            'Condition' => $condition['display_name'] ?? $condition['slug'] ?? null,
            'Category' => $category['full_name'] ?? null,
            'Shop' => $ae['shop_name'] ?? null,
            'Location' => $location['display_location'] ?? null,
            'Handmade' => array_key_exists('handmade', $ae) ? ((bool) $ae['handmade'] ? 'Yes' : 'No') : null,
            'Offers enabled' => array_key_exists('offers_enabled', $ae) ? ((bool) $ae['offers_enabled'] ? 'Yes' : 'No') : null,
            'Local pickup only' => array_key_exists('local_pickup_only', $ae) ? ((bool) $ae['local_pickup_only'] ? 'Yes' : 'No') : null,
            'Origin country' => $ae['origin_country_code'] ?? null,
            'Views' => $stats['views'] ?? null,
            'Watches' => $stats['watches'] ?? null,
            'Return policy' => $returnPolicy['description'] ?? null,
            'Shipping rate' => $this->extractReverbShippingDisplay($ae),
        ];

        foreach ($pairs as $name => $value) {
            $value = $this->str(is_bool($value) ? ($value ? 'Yes' : 'No') : $value);
            if ($value !== null && $value !== '') {
                $out[] = ['name' => $name, 'value' => $value];
            }
        }

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
     * @param  Collection<int, ReverbOrderMetric>  $lines
     * @return array<string, mixed>
     */
    protected function extractOrderAmounts(array $order, Collection $lines): array
    {
        return [
            'order_total' => $this->money($order['total'] ?? $order['order_amount'] ?? $order['total_amount'] ?? null)
                ?? $this->sumLineTotals($lines),
            'pay_amount' => $this->money($order['total'] ?? $order['pay_amount'] ?? null),
            'shipping_cost' => $this->money($order['shipping'] ?? $order['logistics_amount'] ?? $order['shipping_cost'] ?? null),
            'discount' => $this->money($order['discount_amount'] ?? $order['promotion_amount'] ?? null),
            'tax' => $this->money($order['amount_tax'] ?? $order['tax_amount'] ?? null),
            'currency' => $this->moneyCurrency($order['total'] ?? $order['amount_product'] ?? $order['order_amount'] ?? $order['pay_amount'] ?? null)
                ?? 'USD',
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<int, array<string, mixed>>
     */
    protected function extractLogisticsList(array $order): array
    {
        $tracking = $this->str($order['shipping_code'] ?? $order['logistics_no'] ?? null);
        $provider = $this->str($order['shipping_provider'] ?? $order['shipping_method'] ?? $order['logistics_type'] ?? null);
        if ($tracking || $provider || ! empty($order['shipped_at'])) {
            return [[
                'service' => $provider,
                'tracking' => $tracking,
                'status' => $this->str($order['shipment_status'] ?? $order['logistics_status'] ?? null),
                'status_message' => null,
                'send_type' => $this->str($order['shipping_method'] ?? null),
                'receive_status' => null,
                'shipped_at' => $this->str($order['shipped_at'] ?? $order['shipping_date'] ?? null),
                'received_at' => null,
            ]];
        }

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
     * @param  Collection<int, ReverbOrderMetric>  $lines
     * @return array<int, array<string, mixed>>
     */
    protected function extractRichOrderLines(array $order, Collection $lines): array
    {
        // Reverb selling orders are usually one listing per order row.
        if (! empty($order['sku']) || ! empty($order['product_id']) || ! empty($order['title'])) {
            $sku = $this->str($order['sku'] ?? null) ?: '__unknown__';
            $db = $lines->first(fn ($line) => (string) $line->sku === $sku) ?? $lines->first();
            $unit = $this->money($order['amount_product'] ?? null)
                ?? (is_numeric($db?->amount) ? (float) $db->amount : null);
            $qty = max(1, (int) ($order['quantity'] ?? $db?->quantity ?? 1));
            $photo = null;
            foreach ($this->list($order['photos'] ?? []) as $p) {
                $p = $this->arr($p);
                $photo = $this->str($p['_links']['thumbnail']['href'] ?? $p['_links']['small_crop']['href'] ?? $p['_links']['full']['href'] ?? null);
                if ($photo) {
                    break;
                }
            }
            if (! $photo) {
                $photo = $this->str($order['_links']['photo']['href'] ?? null);
            }

            return [[
                'sku' => $sku,
                'product_id' => $this->str($order['product_id'] ?? $db?->product_id),
                'title' => $this->str($order['title'] ?? $db?->display_title),
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $unit !== null ? $unit * $qty : null,
                'image' => $photo ?: $this->resolveOrderLineImage($order, $db),
                'child_order_id' => $this->str($order['order_bundle_id'] ?? null),
                'status' => $this->str($order['status'] ?? $db?->status),
                'import_status' => $db?->import_status,
                'shopify_order_id' => $db?->shopify_order_id,
            ]];
        }

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
    protected function resolveOrderLineImage(array $product, ?ReverbOrderMetric $db = null): ?string
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
            && ! str_contains($url, 'reverb-media.com')
            && ! str_contains($url, 'reverb.com')) {
            return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
        }

        $filename = $this->extractOrderImageFilename($url);
        if ($filename) {
            return $this->buildReverbMediaImageUrl($filename);
        }

        if (preg_match_all('#https?://[^\s"\'<>]+#i', $url, $matches) && $matches[0] !== []) {
            $url = (string) end($matches[0]);
            $filename = $this->extractOrderImageFilename($url);
            if ($filename) {
                return $this->buildReverbMediaImageUrl($filename);
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

    protected function buildReverbMediaImageUrl(string $filename): string
    {
        return 'https://ae-pic-a1.reverb-media.com/kf/'.ltrim($filename, '/');
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
            $metric = ReverbMetric::query()->where('product_id', $productId)->first();
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
     * @param  Collection<int, ReverbOrderMetric>  $lines
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
