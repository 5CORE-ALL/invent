<?php

namespace App\Services;

use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adapts Reverb REST API into the same method shapes used by AliExpress Marketplace Manager services.
 */
class ReverbManagerApiService
{
    public function getAccessToken(): ?string
    {
        return ReverbApiService::getReverbBearerToken();
    }

    /**
     * @return array{success?: bool, message?: string, data?: array, network_error?: bool}
     */
    public function getInventory(int $page = 1, int $pageSize = 20, array $extraListParams = []): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'REVERB token missing. Set REVERB_CLIENT_ID/SECRET or REVERB_TOKEN.'];
        }

        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');

        try {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withHeaders($this->headers($token))
                ->get($apiBase.'/my/listings', [
                    'state' => 'all',
                    'page' => $page,
                    'per_page' => $pageSize,
                ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'network_error' => true, 'message' => $e->getMessage()];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => 'Reverb listings HTTP '.$response->status().': '.mb_substr($response->body(), 0, 240),
            ];
        }

        $json = $response->json() ?? [];
        $listings = $json['listings'] ?? [];
        $total = (int) ($json['total'] ?? 0);
        $totalPage = $pageSize > 0 ? (int) max(1, ceil($total / $pageSize)) : 1;

        $products = [];
        foreach ($listings as $item) {
            if (! is_array($item)) {
                continue;
            }
            $products[] = $this->normalizeListingAsProduct($item);
        }

        return [
            'success' => true,
            'data' => [
                'products' => $products,
                'total_page' => $totalPage,
                'total_count' => $total,
                'total' => $total,
            ],
        ];
    }

    /**
     * @return array{success?: bool, message?: string, data?: array}
     */
    public function getProductInfo(string $productId): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'REVERB token missing.'];
        }

        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $response = Http::withoutVerifying()
            ->timeout(45)
            ->withHeaders($this->headers($token))
            ->get($apiBase.'/listings/'.rawurlencode($productId));

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => 'Reverb product HTTP '.$response->status().': '.mb_substr($response->body(), 0, 240),
            ];
        }

        $item = $response->json() ?? [];
        if (! is_array($item) || $item === []) {
            return ['success' => false, 'message' => 'Reverb listing payload was empty.'];
        }

        // Keep the full Reverb listing for the product-show formatter, plus Manager aliases.
        $normalized = $this->normalizeListingAsProduct($item);

        return [
            'success' => true,
            'data' => array_merge($item, [
                'product_id' => $normalized['product_id'],
                'sku' => $normalized['sku'],
                'product_name' => $normalized['product_name'],
                'price' => $normalized['price'],
                'inventory' => $normalized['inventory'],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{success?: bool, message?: string, data?: array}
     */
    public function getOrders(int $page = 1, int $pageSize = 20, array $query = []): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'REVERB token missing.'];
        }

        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');

        // Reverb docs: /my/orders/selling/all filters by updated_* in ISO8601
        // (not created_* / Y-m-d H:i:s — those are ignored and latest orders are missed).
        $start = $query['updated_start_date'] ?? $query['create_date_start'] ?? $query['created_start_date'] ?? null;
        $end = $query['updated_end_date'] ?? $query['create_date_end'] ?? $query['created_end_date'] ?? null;

        $params = array_filter([
            'page' => max(1, $page),
            'per_page' => max(1, min(50, $pageSize)),
            'updated_start_date' => $this->toReverbIso8601($start),
            'updated_end_date' => $this->toReverbIso8601($end),
        ], static fn ($v) => $v !== null && $v !== '');

        $response = Http::withoutVerifying()
            ->timeout(60)
            ->withHeaders($this->headers($token))
            ->get($apiBase.'/my/orders/selling/all', $params);

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => 'Reverb orders HTTP '.$response->status().': '.mb_substr($response->body(), 0, 240),
            ];
        }

        $json = $response->json() ?? [];
        $orders = $json['orders'] ?? $json['_embedded']['orders'] ?? [];

        return [
            'success' => true,
            'data' => [
                'orders' => is_array($orders) ? array_values($orders) : [],
                'total_page' => (int) ($json['total_pages'] ?? $json['total_page'] ?? 1),
            ],
        ];
    }

    protected function toReverbIso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{success?: bool, message?: string, data?: array}
     */
    public function getOrderInfo(string $orderId): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'REVERB token missing.'];
        }

        $apiBase = rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
        $response = Http::withoutVerifying()
            ->timeout(45)
            ->withHeaders($this->headers($token))
            ->get($apiBase.'/my/orders/selling/'.$orderId);

        if (! $response->successful()) {
            // Fallback: some accounts use /orders/{id}
            $response = Http::withoutVerifying()
                ->timeout(45)
                ->withHeaders($this->headers($token))
                ->get($apiBase.'/orders/'.$orderId);
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => 'Reverb order HTTP '.$response->status().': '.mb_substr($response->body(), 0, 240),
            ];
        }

        return ['success' => true, 'data' => $response->json() ?? []];
    }

    public function getOrderReceiptInfo(string $orderId): array
    {
        return ['success' => false, 'message' => 'Not applicable for Reverb.'];
    }

    public function getOrderTradeDetail(string $orderId): array
    {
        return ['success' => false, 'message' => 'Not applicable for Reverb.'];
    }

    public function getOrderLoanFundList(string $orderId): array
    {
        return ['success' => false, 'message' => 'Not applicable for Reverb.'];
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int}>  $rows
     * @return array{success?: bool, message?: string}
     */
    public function batchUpdateInventory(array $rows): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'REVERB token missing.'];
        }

        $listingService = app(\App\Services\ReverbListingService::class);
        $ok = 0;
        $fail = 0;
        $messages = [];

        foreach ($rows as $row) {
            $sku = (string) ($row['sku_code'] ?? '');
            $qty = (int) ($row['inventory'] ?? 0);
            $listingId = trim((string) ($row['product_id'] ?? ''));

            if ($listingId === '' && $sku !== '') {
                $listingId = (string) (app(ReverbApiService::class)->getListingIdBySku($sku) ?? '');
            }

            // Rule 1: never update unlinked SKUs.
            if ($listingId === '' || ! MarketplaceLiveInventoryRules::isLinked($listingId, $sku)) {
                $fail++;
                $messages[] = ($sku !== '' ? $sku : 'unknown').': skipped unlinked / missing listing id';
                continue;
            }

            // Rule 4: if caller passed shopify_qty <= 0, force marketplace qty to 0.
            $shopifyQty = array_key_exists('shopify_qty', $row) ? (int) $row['shopify_qty'] : null;
            $qty = MarketplaceLiveInventoryRules::clampPushQty($qty, $shopifyQty);

            try {
                if ($listingService->updateListingInventory($listingId, $qty)) {
                    $ok++;
                } else {
                    $fail++;
                    $messages[] = $sku.': updateListingInventory returned false';
                }
            } catch (\Throwable $e) {
                $fail++;
                $messages[] = $sku.': '.$e->getMessage();
                Log::warning('ReverbManagerApiService: inventory update failed', [
                    'sku' => $sku,
                    'listing_id' => $listingId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($ok === 0 && $fail > 0) {
            return ['success' => false, 'message' => 'Reverb inventory update failed. '.implode('; ', array_slice($messages, 0, 3))];
        }

        return [
            'success' => true,
            'message' => "Updated {$ok} listing(s)".($fail ? ", {$fail} failed" : '').'.',
        ];
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, price: float|string}>  $rows
     * @return array{success?: bool, message?: string}
     */
    public function batchUpdatePrice(array $rows): array
    {
        $api = app(ReverbApiService::class);
        $ok = 0;
        $fail = 0;

        foreach ($rows as $row) {
            $sku = (string) ($row['sku_code'] ?? '');
            $price = (float) ($row['price'] ?? 0);
            if ($sku === '' || $price <= 0) {
                $fail++;
                continue;
            }
            $result = $api->updatePrice($sku, $price);
            if (! empty($result['success'])) {
                $ok++;
            } else {
                $fail++;
            }
        }

        return [
            'success' => $ok > 0 || $fail === 0,
            'message' => "Price updated {$ok}, failed {$fail}.",
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, array{product_id: string, sku: string, product_name: ?string, price: float|int|null}>
     */
    public function extractSkuRowsFromListItem(array $item, bool $fetchDetail = false): array
    {
        $product = $this->normalizeListingAsProduct($item);
        $productId = (string) ($product['product_id'] ?? '');
        $sku = trim((string) ($product['sku'] ?? ''));
        if ($productId === '' || $sku === '') {
            return [];
        }

        return [[
            'product_id' => $productId,
            'sku' => $sku,
            'product_name' => $product['product_name'] ?? null,
            'price' => $product['price'] ?? null,
        ]];
    }

    /**
     * @param  array<string, mixed>  $info
     * @return array<int, array{product_id: string, sku: string, product_name: ?string, price: float|int|null, inventory?: int|null}>
     */
    public function extractSkuRowsFromProductInfo(array $info, string $productId, ?string $productName = null): array
    {
        $data = $info['data'] ?? $info;
        if (! is_array($data)) {
            return [];
        }
        $product = $this->normalizeListingAsProduct($data);
        $sku = trim((string) ($product['sku'] ?? ''));
        $pid = (string) ($product['product_id'] ?? $productId);
        if ($sku === '' || $pid === '') {
            return [];
        }

        return [[
            'product_id' => $pid,
            'sku' => $sku,
            'product_name' => $productName ?: ($product['product_name'] ?? null),
            'price' => $product['price'] ?? null,
            'inventory' => $product['inventory'] ?? null,
        ]];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<int, array<string, mixed>>
     */
    public function extractOrderProductLines(array $order): array
    {
        $lines = [];
        $items = $order['order_items'] ?? $order['items'] ?? $order['product_list'] ?? [];
        if (! is_array($items) || $items === []) {
            $sku = trim((string) ($order['sku'] ?? $order['product_sku'] ?? ''));
            $amount = $order['amount_product'] ?? $order['total'] ?? $order['amount'] ?? $order['buyer_amount'] ?? 0;
            if (is_array($amount)) {
                $amount = $amount['amount'] ?? 0;
            }
            $lines[] = [
                'sku' => $sku !== '' ? $sku : '__order__',
                'product_id' => (string) ($order['product_id'] ?? $order['listing_id'] ?? ''),
                'display_title' => (string) ($order['title'] ?? $order['product_title'] ?? $order['display_sku'] ?? ''),
                'quantity' => (int) ($order['quantity'] ?? 1),
                'amount' => is_numeric($amount) ? (float) $amount : 0.0,
            ];

            return $lines;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? $item['product_sku'] ?? $item['seller_sku'] ?? ''));
            $amount = $item['amount'] ?? $item['price'] ?? $item['total'] ?? 0;
            if (is_array($amount)) {
                $amount = $amount['amount'] ?? 0;
            }
            $lines[] = [
                'sku' => $sku !== '' ? $sku : '__unknown__',
                'product_id' => (string) ($item['product_id'] ?? $item['listing_id'] ?? ''),
                'display_title' => (string) ($item['title'] ?? $item['product_title'] ?? $item['name'] ?? ''),
                'quantity' => (int) ($item['quantity'] ?? $item['quantity_sold'] ?? 1),
                'amount' => is_numeric($amount) ? (float) $amount : 0.0,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function normalizeListingAsProduct(array $item): array
    {
        // Accept both raw Reverb listing payloads and already-normalized Manager rows.
        $listingId = (string) ($item['id'] ?? $item['listing_id'] ?? $item['product_id'] ?? '');
        $sku = trim((string) ($item['sku'] ?? $item['seller_sku'] ?? ''));
        $price = is_array($item['price'] ?? null)
            ? ($item['price']['amount'] ?? null)
            : ($item['price'] ?? $item['buyer_price'] ?? null);
        $qty = $item['inventory'] ?? $item['quantity'] ?? $item['stock'] ?? null;

        return [
            'product_id' => $listingId,
            'sku' => $sku,
            'product_name' => $item['title'] ?? $item['make'] ?? $item['product_name'] ?? null,
            'price' => is_numeric($price) ? (float) $price : null,
            'inventory' => is_numeric($qty) ? (int) $qty : null,
            'raw' => $item['raw'] ?? $item,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function headers(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/hal+json',
            'Accept-Version' => '3.0',
            'Content-Type' => 'application/hal+json',
        ];
    }
}
