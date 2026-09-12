<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifySku;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve Shopify fulfillment tracking only after both checks pass:
 *  1) the Shopify order contains the full marketplace order id
 *  2) a fulfillment line item SKU matches the marketplace SKU
 */
class ShopifyFulfillmentTrackingMatcher
{
    private const SHOPIFY_API_VERSIONS = ['2025-01', '2024-10', '2024-01'];

    private ?string $workingApiVersion = null;

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
        $shopifyOrderId = $this->numericShopifyId($shopifyOrderId);
        if ($storeUrl === '' || $token === '' || $shopifyOrderId === '') {
            $empty['error'] = 'Shopify store credentials or order id missing.';

            return $empty;
        }

        $orderIds = $this->uniqueIds(array_merge([$marketplaceOrderId], $extraOrderIds));
        $sku = $this->normalizeSku($sku);
        if (in_array($sku, ['__ORDER__', '__UNKNOWN__'], true)) {
            $sku = '';
        }
        if ($orderIds === []) {
            $empty['error'] = 'Marketplace order id missing — tracking not attached.';

            return $empty;
        }
        $expectedSlug = $this->slugFromOrderIds($orderIds);

        try {
            $order = $this->fetchShopifyOrder($storeUrl, $token, $shopifyOrderId);
            if ($order === null) {
                $empty['error'] = 'Shopify order fetch failed.';

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

            $primarySlug = $this->primaryMarketplaceSlug($order);
            if ($expectedSlug !== '' && $primarySlug !== '' && $primarySlug !== $expectedSlug) {
                $empty['error'] = 'Shopify order belongs to '.$primarySlug
                    .' — will not attach that tracking to '.$expectedSlug.'.';
                Log::info($logContext.': marketplace slug mismatch — tracking skipped', [
                    'shopify_order_id' => $shopifyOrderId,
                    'wanted' => $orderIds,
                    'expected_slug' => $expectedSlug,
                    'primary_slug' => $primarySlug,
                    'tags' => $order['tags'] ?? null,
                ]);

                return $empty;
            }

            $orderLines = is_array($order['line_items'] ?? null) ? $order['line_items'] : [];
            if ($sku === '') {
                $sku = $this->normalizeSku((string) ($this->firstOrderLineSku($orderLines) ?? ''));
                if ($sku === '' || ! $this->isSingleSkuOrder($orderLines)) {
                    $empty['matched_order_id'] = $matchedOrderId;
                    $empty['error'] = 'Marketplace SKU missing — tracking not attached.';

                    return $empty;
                }
            }

            $fulfillments = $this->fulfillmentsForOrder($storeUrl, $token, $shopifyOrderId, $order);
            foreach ($fulfillments as $fulfillment) {
                if (! is_array($fulfillment)) {
                    continue;
                }
                $status = strtolower((string) ($fulfillment['status'] ?? ''));
                if (in_array($status, ['cancelled', 'error', 'failure'], true)) {
                    continue;
                }
                $number = $this->trackingFromFulfillment($fulfillment);
                if ($number === null) {
                    continue;
                }
                if (! $this->fulfillmentMatchesSku($fulfillment, $sku, $orderLines)) {
                    continue;
                }

                $url = $this->trackingUrlFromFulfillment($fulfillment);

                return [
                    'tracking' => $number,
                    'carrier' => $this->carrierFromFulfillment($fulfillment),
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

    /**
     * @param  array<string, mixed>  $order
     */
    public function orderHasSku(array $order, string $sku): bool
    {
        $sku = trim($sku);
        if ($sku === '' || in_array($sku, ['__ORDER__', '__UNKNOWN__'], true)) {
            return false;
        }
        foreach ($order['line_items'] ?? [] as $line) {
            if (is_array($line) && $this->lineSkuEquals($line, $sku)) {
                return true;
            }
        }

        return false;
    }

    public function skusEqual(string $a, string $b): bool
    {
        $left = $this->normalizeSku($a);
        $right = $this->normalizeSku($b);
        if ($left !== '' && $left === $right) {
            return true;
        }

        $normLeft = ShopifySku::normalizeSkuForShopifyLookup($a);
        $normRight = ShopifySku::normalizeSkuForShopifyLookup($b);
        if ($normLeft !== '' && $normLeft === $normRight) {
            return true;
        }

        $compactLeft = ShopifySku::compactSkuForLookup($a);
        $compactRight = ShopifySku::compactSkuForLookup($b);

        return $compactLeft !== '' && $compactLeft === $compactRight;
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
            (string) ($order['source_name'] ?? ''),
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

        return (bool) preg_match('/(?<![A-Za-z0-9])'.$quoted.'(?![A-Za-z0-9])/i', $haystack);
    }

    public function numericShopifyId(string $shopifyOrderId): string
    {
        $shopifyOrderId = trim($shopifyOrderId);
        if (preg_match('/(\d{5,})$/', $shopifyOrderId, $m)) {
            return $m[1];
        }

        return $shopifyOrderId;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchShopifyOrder(string $storeUrl, string $token, string $shopifyOrderId): ?array
    {
        $shopifyOrderId = $this->numericShopifyId($shopifyOrderId);
        foreach ($this->shopifyApiVersions() as $version) {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])
                ->timeout(30)
                ->get("https://{$storeUrl}/admin/api/{$version}/orders/{$shopifyOrderId}.json");
            if (! $response->successful()) {
                continue;
            }
            $order = $response->json('order');
            if (is_array($order)) {
                $this->workingApiVersion = $version;

                return $order;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function shopifyApiVersions(): array
    {
        if ($this->workingApiVersion !== null) {
            return array_values(array_unique(array_merge(
                [$this->workingApiVersion],
                self::SHOPIFY_API_VERSIONS
            )));
        }

        return self::SHOPIFY_API_VERSIONS;
    }

    protected function shopifyGet(string $storeUrl, string $token, string $path): ?array
    {
        foreach ($this->shopifyApiVersions() as $version) {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])
                ->timeout(30)
                ->get("https://{$storeUrl}/admin/api/{$version}/{$path}");
            if (! $response->successful()) {
                continue;
            }
            $this->workingApiVersion = $version;
            $json = $response->json();

            return is_array($json) ? $json : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<array<string, mixed>>
     */
    protected function fulfillmentsForOrder(string $storeUrl, string $token, string $shopifyOrderId, array $order): array
    {
        $shopifyOrderId = $this->numericShopifyId($shopifyOrderId);
        $fromOrder = is_array($order['fulfillments'] ?? null) ? $order['fulfillments'] : [];
        $out = [];
        foreach ($fromOrder as $fulfillment) {
            if (is_array($fulfillment)) {
                $out[] = $fulfillment;
            }
        }

        $extra = $this->shopifyGet($storeUrl, $token, "orders/{$shopifyOrderId}/fulfillments.json") ?? [];
        foreach ($extra['fulfillments'] ?? [] as $fulfillment) {
            if (is_array($fulfillment)) {
                $out[] = $fulfillment;
            }
        }

        foreach ($this->fulfillmentsFromFulfillmentOrders($storeUrl, $token, $shopifyOrderId) as $fulfillment) {
            $out[] = $fulfillment;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fulfillmentsFromFulfillmentOrders(string $storeUrl, string $token, string $shopifyOrderId): array
    {
        $fos = $this->shopifyGet($storeUrl, $token, "orders/{$shopifyOrderId}/fulfillment_orders.json") ?? [];
        $orders = is_array($fos['fulfillment_orders'] ?? null) ? $fos['fulfillment_orders'] : [];
        $out = [];
        foreach ($orders as $fo) {
            if (! is_array($fo)) {
                continue;
            }
            $foId = $this->numericShopifyId((string) ($fo['id'] ?? ''));
            if ($foId === '') {
                continue;
            }
            $rows = $this->shopifyGet($storeUrl, $token, "fulfillment_orders/{$foId}/fulfillments.json") ?? [];
            foreach ($rows['fulfillments'] ?? [] as $fulfillment) {
                if (is_array($fulfillment)) {
                    $out[] = $fulfillment;
                }
            }
            // Some Shopify payloads put tracking on the FO itself.
            if ($this->trackingFromFulfillment($fo) !== null) {
                $out[] = $fo;
            }
        }

        return $out;
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

            foreach ($this->fulfillmentLineItemIds($line) as $lineId) {
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
        }

        // Shopify often omits nested SKUs on FO-created fulfillments.
        // Only then may we use the order line SKU — never a different channel's SKU.
        if ($this->fulfillmentLinesLackSkus($lines) && $this->orderHasSku(['line_items' => $orderLines], $sku)) {
            if (count($orderLines) === 1 || $lines === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $orderLines
     */
    public function isSingleSkuOrder(array $orderLines): bool
    {
        $skus = [];
        foreach ($orderLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $sku = $this->normalizeSku((string) ($line['sku'] ?? ''));
            if ($sku === '' || in_array($sku, ['__ORDER__', '__UNKNOWN__'], true)) {
                continue;
            }
            if (! in_array($sku, $skus, true)) {
                $skus[] = $sku;
            }
        }

        return count($skus) === 1;
    }

    /**
     * @param  list<string>  $orderIds
     */
    public function slugFromOrderIds(array $orderIds): string
    {
        foreach ($orderIds as $id) {
            $slug = $this->slugFromOrderId((string) $id);
            if ($slug !== '') {
                return $slug;
            }
        }

        return '';
    }

    public function slugFromOrderId(string $orderId): string
    {
        $id = trim($orderId);
        if ($id === '') {
            return '';
        }
        if (preg_match('/^\d{3}-\d{7}-\d{7}$/', $id)) {
            return 'amazon';
        }
        if (preg_match('/^GSU[A-Z0-9]+$/i', $id)) {
            return 'shein';
        }
        if (preg_match('/^PO-\d/i', $id)) {
            return 'temu';
        }
        if (preg_match('/^BBY\d{2}-/i', $id)) {
            return 'bestbuy';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $order
     */
    public function primaryMarketplaceSlug(array $order): string
    {
        $hay = trim((string) ($order['tags'] ?? '')).' '.trim((string) ($order['note'] ?? ''));
        $slugs = MarketplaceManagerRegistry::slugs();
        usort($slugs, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
        foreach ($slugs as $slug) {
            $slug = strtolower((string) $slug);
            if ($slug === '') {
                continue;
            }
            if (preg_match('/(?:^|[\s,])'.preg_quote($slug, '/').'[-_][^\s,]+/i', $hay)) {
                return $slug;
            }
        }
        if (preg_match('/\d{3}-\d{7}-\d{7}/', $hay)) {
            return 'amazon';
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $orderLines
     */
    protected function firstOrderLineSku(array $orderLines): ?string
    {
        foreach ($orderLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $sku = trim((string) ($line['sku'] ?? ''));
            if ($sku !== '') {
                return $sku;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return list<string>
     */
    protected function fulfillmentLineItemIds(array $line): array
    {
        $out = [];
        foreach (['line_item_id', 'id'] as $key) {
            $id = trim((string) ($line[$key] ?? ''));
            if ($id !== '' && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $lines
     */
    protected function fulfillmentLinesLackSkus(array $lines): bool
    {
        if ($lines === []) {
            return true;
        }
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            if (trim((string) ($line['sku'] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function lineSkuEquals(array $line, string $sku): bool
    {
        $candidates = [
            $line['sku'] ?? '',
        ];
        $variantId = trim((string) ($line['variant_id'] ?? ''));
        if ($variantId !== '') {
            try {
                $catalog = ShopifySku::query()->where('variant_id', $variantId)->value('sku');
                if (is_string($catalog) && trim($catalog) !== '') {
                    $candidates[] = $catalog;
                }
            } catch (\Throwable) {
                // ignore
            }
        }
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
    /**
     * @param  array<string, mixed>  $fulfillment
     * @return array<string, mixed>
     */
    protected function trackingInfo(array $fulfillment): array
    {
        $info = $fulfillment['tracking_info'] ?? [];
        if (isset($info[0]) && is_array($info[0])) {
            $info = $info[0];
        }

        return is_array($info) ? $info : [];
    }

    /**
     * @param  array<string, mixed>  $fulfillment
     */
    protected function trackingFromFulfillment(array $fulfillment): ?string
    {
        $info = $this->trackingInfo($fulfillment);
        $number = trim((string) ($info['number'] ?? ''));
        if ($number === '' && ! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
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
    protected function carrierFromFulfillment(array $fulfillment): ?string
    {
        $info = $this->trackingInfo($fulfillment);
        $carrier = trim((string) ($fulfillment['tracking_company'] ?? ''));
        if ($carrier === '') {
            $carrier = trim((string) ($info['company'] ?? ''));
        }

        return $carrier !== '' ? $carrier : null;
    }

    /**
     * @param  array<string, mixed>  $fulfillment
     */
    protected function trackingUrlFromFulfillment(array $fulfillment): ?string
    {
        $info = $this->trackingInfo($fulfillment);
        $url = trim((string) ($info['url'] ?? ''));
        if ($url === '' && ! empty($fulfillment['tracking_urls']) && is_array($fulfillment['tracking_urls'])) {
            $url = trim((string) ($fulfillment['tracking_urls'][0] ?? ''));
        }
        if ($url === '' && ! empty($fulfillment['tracking_url'])) {
            $url = trim((string) $fulfillment['tracking_url']);
        }

        return $url !== '' ? $url : null;
    }
}
