<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve Shopify fulfillment tracking only after both checks pass:
 *  1) the Shopify order contains the full marketplace order id
 *  2) a fulfillment line item SKU matches the marketplace SKU
 */
class ShopifyFulfillmentTrackingMatcher
{
    /**
     * @param  array{store_url?: string, token?: string}  $config
     * @param  list<string>  $extraOrderIds
     * @return array{
     *   tracking: ?string,
     *   carrier: ?string,
     *   tracking_url: ?string,
     *   matched_order_id: ?string,
     *   matched_sku: ?string,
     *   error: ?string
     * }
     */
    public function match(
        array $config,
        string $shopifyOrderId,
        string $marketplaceOrderId,
        string $sku,
        array $extraOrderIds = [],
        string $logContext = 'ShopifyFulfillmentTrackingMatcher'
    ): array {
        $empty = [
            'tracking' => null,
            'carrier' => null,
            'tracking_url' => null,
            'matched_order_id' => null,
            'matched_sku' => null,
            'error' => null,
        ];

        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        $shopifyOrderId = trim($shopifyOrderId);
        if ($storeUrl === '' || $token === '' || $shopifyOrderId === '') {
            $empty['error'] = 'Shopify store credentials or order id missing.';

            return $empty;
        }

        $orderIds = $this->uniqueIds(array_merge([$marketplaceOrderId], $extraOrderIds));
        $sku = $this->normalizeSku($sku);
        if ($orderIds === []) {
            $empty['error'] = 'Marketplace order id missing — tracking not attached.';

            return $empty;
        }
        if ($sku === '' || in_array($sku, ['__order__', '__unknown__'], true)) {
            $empty['error'] = 'Marketplace SKU missing — tracking not attached.';

            return $empty;
        }

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])
                ->timeout(30)
                ->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}.json", [
                    'fields' => 'id,name,tags,note,note_attributes,source_identifier,fulfillments,line_items,fulfillment_status',
                ]);

            if (! $response->successful()) {
                $empty['error'] = 'Shopify order fetch failed: HTTP '.$response->status();

                return $empty;
            }

            $order = $response->json('order');
            if (! is_array($order)) {
                $empty['error'] = 'Shopify order payload missing.';

                return $empty;
            }

            $matchedOrderId = $this->matchFullOrderId($order, $orderIds);
            if ($matchedOrderId === null) {
                $empty['error'] = 'Shopify order does not contain the full marketplace order id.';
                Log::info($logContext.': full order id mismatch — tracking skipped', [
                    'shopify_order_id' => $shopifyOrderId,
                    'wanted' => $orderIds,
                    'name' => $order['name'] ?? null,
                    'tags' => $order['tags'] ?? null,
                ]);

                return $empty;
            }

            $orderLines = is_array($order['line_items'] ?? null) ? $order['line_items'] : [];
            $fulfillments = is_array($order['fulfillments'] ?? null) ? $order['fulfillments'] : [];
            foreach ($fulfillments as $fulfillment) {
                if (! is_array($fulfillment)) {
                    continue;
                }
                $status = strtolower((string) ($fulfillment['status'] ?? ''));
                if (in_array($status, ['cancelled', 'error', 'failure'], true)) {
                    continue;
                }
                if (! $this->fulfillmentMatchesSku($fulfillment, $sku, $orderLines)) {
                    continue;
                }

                $number = $this->trackingFromFulfillment($fulfillment);
                if ($number === null) {
                    continue;
                }

                $url = $this->trackingUrlFromFulfillment($fulfillment);

                return [
                    'tracking' => $number,
                    'carrier' => trim((string) ($fulfillment['tracking_company'] ?? '')) ?: null,
                    'tracking_url' => $url,
                    'matched_order_id' => $matchedOrderId,
                    'matched_sku' => $sku,
                    'error' => null,
                ];
            }

            $empty['matched_order_id'] = $matchedOrderId;
            $empty['error'] = 'No Shopify fulfillment tracking for this full order id + SKU.';

            return $empty;
        } catch (\Throwable $e) {
            Log::warning($logContext.': Shopify tracking match failed', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
            $empty['error'] = $e->getMessage();

            return $empty;
        }
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  list<string>  $orderIds
     */
    public function matchFullOrderId(array $order, array $orderIds): ?string
    {
        $haystacks = $this->orderIdHaystacks($order);

        foreach ($orderIds as $id) {
            foreach ($haystacks as $haystack) {
                if ($this->containsFullOrderId($haystack, $id)) {
                    return $id;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<string>
     */
    public function uniqueIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '' && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    public function normalizeSku(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/\s+/', ' ', $sku) ?? $sku;

        return $sku;
    }

    public function skusEqual(string $a, string $b): bool
    {
        return $this->normalizeSku($a) !== ''
            && $this->normalizeSku($a) === $this->normalizeSku($b);
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    protected function orderIdHaystacks(array $order): array
    {
        $out = [
            ltrim(trim((string) ($order['name'] ?? '')), '#'),
            (string) ($order['note'] ?? ''),
            (string) ($order['source_identifier'] ?? ''),
            is_array($order['tags'] ?? null)
                ? implode(',', $order['tags'])
                : (string) ($order['tags'] ?? ''),
        ];

        foreach ($order['note_attributes'] ?? [] as $attr) {
            if (is_array($attr)) {
                $out[] = (string) ($attr['value'] ?? '');
            }
        }

        return array_values(array_filter($out, static fn ($v) => trim((string) $v) !== ''));
    }

    protected function containsFullOrderId(string $haystack, string $orderId): bool
    {
        $orderId = trim($orderId);
        $haystack = trim($haystack);
        if ($orderId === '' || $haystack === '') {
            return false;
        }

        if (strcasecmp(ltrim($haystack, '#'), ltrim($orderId, '#')) === 0) {
            return true;
        }

        $quoted = preg_quote($orderId, '/');

        return (bool) preg_match('/(?<![A-Za-z0-9])'.$quoted.'(?![A-Za-z0-9])/', $haystack);
    }

    /**
     * @param  array<string, mixed>  $fulfillment
     * @param  list<array<string, mixed>>  $orderLines
     */
    protected function fulfillmentMatchesSku(array $fulfillment, string $sku, array $orderLines): bool
    {
        $lines = is_array($fulfillment['line_items'] ?? null) ? $fulfillment['line_items'] : [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            if ($this->lineSkuEquals($line, $sku)) {
                return true;
            }

            $lineId = (string) ($line['id'] ?? '');
            if ($lineId === '') {
                continue;
            }
            foreach ($orderLines as $orderLine) {
                if (! is_array($orderLine)) {
                    continue;
                }
                if ((string) ($orderLine['id'] ?? '') !== $lineId) {
                    continue;
                }
                if ($this->lineSkuEquals($orderLine, $sku)) {
                    return true;
                }
            }
        }

        // Single-line Shopify orders: fulfillment may omit nested SKUs.
        if ($lines === [] && count($orderLines) === 1 && is_array($orderLines[0])) {
            return $this->lineSkuEquals($orderLines[0], $sku);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function lineSkuEquals(array $line, string $sku): bool
    {
        $candidates = [
            $line['sku'] ?? '',
        ];
        foreach ($line['properties'] ?? [] as $prop) {
            if (! is_array($prop)) {
                continue;
            }
            $name = strtolower(trim((string) ($prop['name'] ?? '')));
            if (in_array($name, ['sku', 'seller sku', 'seller_sku'], true)) {
                $candidates[] = $prop['value'] ?? '';
            }
        }

        foreach ($candidates as $candidate) {
            if ($this->skusEqual((string) $candidate, $sku)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $fulfillment
     */
    protected function trackingFromFulfillment(array $fulfillment): ?string
    {
        $number = '';
        if (! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
            $number = trim((string) ($fulfillment['tracking_numbers'][0] ?? ''));
        }
        if ($number === '' && ! empty($fulfillment['tracking_number'])) {
            $number = trim((string) $fulfillment['tracking_number']);
        }

        return $number !== '' ? $number : null;
    }

    /**
     * @param  array<string, mixed>  $fulfillment
     */
    protected function trackingUrlFromFulfillment(array $fulfillment): ?string
    {
        $url = '';
        if (! empty($fulfillment['tracking_urls']) && is_array($fulfillment['tracking_urls'])) {
            $url = trim((string) ($fulfillment['tracking_urls'][0] ?? ''));
        }
        if ($url === '' && ! empty($fulfillment['tracking_url'])) {
            $url = trim((string) $fulfillment['tracking_url']);
        }

        return $url !== '' ? $url : null;
    }
}
