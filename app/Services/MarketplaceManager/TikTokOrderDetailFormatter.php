<?php

namespace App\Services\MarketplaceManager;

use App\Models\TiktokOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Build rich order-detail payload for TikTok1 / TikTok2 show pages from DB + raw_json.
 */
class TikTokOrderDetailFormatter
{
    use ResolvesTikTokOrderRawJson;

    /**
     * @param  Collection<int, TiktokOrder>  $lines
     * @return array<string, mixed>
     */
    public function format(TiktokOrder $line, Collection $lines): array
    {
        $raw = $this->normalizeTikTokRawJson($line->raw_json);
        $payment = is_array($raw['payment'] ?? null) ? $raw['payment'] : [];
        $address = $this->tikTokAddressFromRaw($raw);
        $currency = (string) ($payment['currency'] ?? $line->currency ?? $raw['currency'] ?? '');

        $city = trim((string) ($address['city'] ?? ''));
        $province = trim((string) ($address['state'] ?? $address['region'] ?? $address['province'] ?? $address['province_code'] ?? ''));
        $districtInfo = $address['district_info'] ?? null;
        if (is_array($districtInfo)) {
            if ($city === '') {
                $city = trim((string) ($districtInfo[1]['address_name'] ?? ''));
            }
            if ($province === '') {
                $province = trim((string) ($districtInfo[0]['address_name'] ?? ''));
            }
        }

        $packages = is_array($raw['packages'] ?? null) ? $raw['packages'] : [];
        $logistics = [];
        foreach ($packages as $pkg) {
            if (! is_array($pkg)) {
                continue;
            }
            $logistics[] = [
                'service' => $pkg['shipping_provider_name'] ?? $pkg['shipping_provider'] ?? $line->shipping_provider ?? null,
                'tracking' => $pkg['tracking_number'] ?? $pkg['tracking_number_list'][0] ?? null,
                'shipped_at' => $this->ts($pkg['shipping_time'] ?? $line->rts_time ?? null),
                'status' => $pkg['package_status'] ?? $pkg['status'] ?? null,
                'status_message' => $pkg['package_freeze_status'] ?? null,
            ];
        }

        $lineItems = [];
        foreach ($lines as $row) {
            $sku = trim((string) ($row->seller_sku ?? ''));
            if ($sku === '__order__') {
                continue;
            }
            $qty = max(1, (int) ($row->quantity ?? 1));
            $unit = (float) ($row->sale_price ?? $row->original_price ?? 0);
            $lineItems[] = [
                'sku' => $sku !== '' ? $sku : null,
                'product_id' => $row->product_id,
                'sku_id' => $row->sku_id,
                'title' => $row->product_name,
                'quantity' => $qty,
                'unit_price' => $unit > 0 ? $unit : null,
                'line_total' => $unit > 0 ? $unit * $qty : null,
                'status' => $row->line_status ?: $row->order_status,
                'shopify_order_id' => $row->shopify_order_id,
                'import_status' => $row->import_status,
            ];
        }

        // Prefer API line_items for image / richer status when DB lines are thin.
        $apiLines = $raw['line_items'] ?? $raw['order_line_list'] ?? [];
        if (is_array($apiLines) && $apiLines !== [] && $lineItems !== []) {
            $bySku = [];
            foreach ($apiLines as $apiLine) {
                if (! is_array($apiLine)) {
                    continue;
                }
                $sku = trim((string) ($apiLine['seller_sku'] ?? $apiLine['sku'] ?? ''));
                if ($sku !== '') {
                    $bySku[$sku] = $apiLine;
                }
            }
            foreach ($lineItems as &$item) {
                $sku = (string) ($item['sku'] ?? '');
                if ($sku === '' || ! isset($bySku[$sku])) {
                    continue;
                }
                $api = $bySku[$sku];
                $item['image'] = $api['sku_image'] ?? $api['product_image'] ?? null;
                if (empty($item['status']) || $item['status'] === 'UNKNOWN') {
                    $item['status'] = $api['display_status'] ?? $api['item_status'] ?? $item['status'];
                }
                if (empty($item['product_id'])) {
                    $item['product_id'] = $api['product_id'] ?? null;
                }
                if (empty($item['sku_id'])) {
                    $item['sku_id'] = $api['sku_id'] ?? null;
                }
            }
            unset($item);
        }

        return [
            'summary' => [
                'order_id' => $line->order_id,
                'status' => $line->order_status ?? ($raw['status'] ?? null),
                'buyer_user_id' => $raw['user_id'] ?? null,
                'created' => $this->ts($raw['create_time'] ?? $line->order_created_at),
                'paid' => $this->ts($raw['paid_time'] ?? null),
                'updated' => $this->ts($raw['update_time'] ?? $line->order_updated_at),
                'rts_time' => $this->ts($line->rts_time),
                'delivery_time' => $this->ts($line->delivery_time),
                'fulfillment_type' => $line->fulfillment_type ?? ($raw['fulfillment_type'] ?? null),
                'delivery_type' => $line->delivery_type ?? ($raw['delivery_type'] ?? null),
                'shipping_type' => $raw['shipping_type'] ?? null,
                'delivery_option' => $raw['delivery_option_name'] ?? null,
                'buyer_message' => $raw['buyer_message'] ?? null,
                'is_on_hold' => ! empty($raw['is_on_hold_order']),
                'payment_method' => $raw['payment_method_name'] ?? null,
            ],
            'payment' => [
                'total_paid' => $payment['total_amount'] ?? $line->order_amount,
                'paid_at' => $this->ts($raw['paid_time'] ?? null),
                'method' => $raw['payment_method_name'] ?? null,
                'currency' => $currency,
            ],
            'funds' => [
                'currency' => $currency,
                'product_total' => $payment['original_total_product_price'] ?? $payment['sub_total'] ?? null,
                'sub_total' => $payment['sub_total'] ?? null,
                'shipping_fee' => $payment['shipping_fee'] ?? $payment['original_shipping_fee'] ?? null,
                'tax' => $payment['tax'] ?? $payment['product_tax'] ?? null,
                'platform_discount' => $payment['platform_discount'] ?? null,
                'seller_discount' => $payment['seller_discount'] ?? null,
                'shipping_fee_platform_discount' => $payment['shipping_fee_platform_discount'] ?? null,
                'order_amount' => $payment['total_amount'] ?? $line->order_amount,
            ],
            'buyer' => [
                'name' => $address['name'] ?? $address['full_name'] ?? $line->buyer_nickname,
                'email' => $raw['buyer_email'] ?? null,
                'phone' => $address['phone_number'] ?? $address['phone'] ?? null,
                'user_id' => $raw['user_id'] ?? null,
                'nickname' => $line->buyer_nickname,
                'country' => $address['region_code'] ?? $line->shop_region ?? null,
            ],
            'shipping' => [
                'recipient' => $address['name'] ?? $address['full_name'] ?? null,
                'detail_address' => $address['full_address'] ?? $address['address_detail'] ?? null,
                'address_line_1' => $address['address_line1'] ?? null,
                'address_line_2' => $address['address_line2'] ?? null,
                'city' => $city !== '' ? $city : null,
                'province' => $province !== '' ? $province : null,
                'zip' => $address['postal_code'] ?? $address['zipcode'] ?? null,
                'country' => $address['region_code'] ?? null,
                'phone' => $address['phone_number'] ?? $address['phone'] ?? null,
                'email' => $raw['buyer_email'] ?? null,
            ],
            'shipment' => [
                'service' => $line->shipping_provider ?? ($raw['delivery_option_name'] ?? null),
                'tracking' => $logistics[0]['tracking'] ?? null,
                'shipped_at' => $logistics[0]['shipped_at'] ?? $this->ts($line->rts_time),
                'status' => $logistics[0]['status'] ?? $line->order_status,
            ],
            'logistics' => $logistics,
            'line_items' => $lineItems,
            'shopify' => [
                'shopify_order_id' => $line->shopify_order_id,
                'import_status' => $line->import_status,
                'pushed_to_shopify_at' => $line->pushed_to_shopify_at,
                'tracking_pushed_at' => $line->tracking_pushed_at,
            ],
            'raw_available' => $raw !== [],
        ];
    }

    protected function ts(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }
        if (is_numeric($value)) {
            $n = (int) $value;
            // TikTok often uses seconds; sometimes ms.
            if ($n > 9999999999) {
                $n = (int) floor($n / 1000);
            }

            return Carbon::createFromTimestamp($n)->toDateTimeString();
        }
        try {
            return Carbon::parse((string) $value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
