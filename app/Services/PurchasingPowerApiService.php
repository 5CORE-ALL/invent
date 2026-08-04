<?php

namespace App\Services;

use App\Models\PurchasingPowerProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Purchasing Power — Mirakl Connect channel + MCM seller APIs (OF21 / OR11 / PRI01).
 */
class PurchasingPowerApiService extends BestBuyApiService
{
    protected function miraklChannelCode(): string
    {
        return 'purchasingpower';
    }

    protected function miraklMcmConfigKey(): string
    {
        return 'purchasingpower';
    }

    protected function miraklMcmMarketplaceLabel(): string
    {
        return 'Purchasing Power';
    }

    protected function miraklMcmHierarchyTable(): ?string
    {
        return null;
    }

    /**
     * OR11 — list Purchasing Power MCM orders (paginated).
     *
     * @return array{orders: list<array<string, mixed>>, total_count: int}
     */
    public function fetchOrders(?Carbon $startDate = null, ?Carbon $endDate = null, int $max = 100): array
    {
        $apiKey = trim((string) config('services.purchasingpower.mcm_api_key', ''));
        $baseUrl = rtrim((string) config('services.purchasingpower.mcm_base_url', ''), '/');

        if ($apiKey === '' || $baseUrl === '') {
            throw new \RuntimeException('Purchasing Power MCM API key/base URL not configured (PURCHASING_POWER_MCM_API_KEY).');
        }

        $allOrders = [];
        $offset = 0;
        $totalCount = 0;
        $page = 0;
        $maxPages = 50;

        do {
            $page++;
            $params = [
                'max' => $max,
                'offset' => $offset,
            ];
            if ($startDate) {
                $params['start_date'] = $startDate->copy()->utc()->format('Y-m-d\TH:i:s\Z');
            }
            if ($endDate) {
                $params['end_date'] = $endDate->copy()->utc()->format('Y-m-d\TH:i:s\Z');
            }

            $shopId = config('services.purchasingpower.shop_id');
            if ($shopId !== null && $shopId !== '') {
                $params['shop_id'] = (int) $shopId;
            }

            $response = null;
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(60)
                    ->get($baseUrl.'/api/orders', $params);

                if ($response->status() !== 429) {
                    break;
                }
                sleep(min(30, 3 * $attempt));
            }

            if (! $response || ! $response->successful()) {
                $status = $response ? $response->status() : 0;
                $body = $response ? substr($response->body(), 0, 500) : 'no response';
                Log::error('Purchasing Power MCM OR11 failed', [
                    'status' => $status,
                    'body' => $body,
                    'offset' => $offset,
                ]);
                throw new \RuntimeException("Purchasing Power MCM OR11 failed: HTTP {$status}");
            }

            $json = $response->json() ?? [];
            $orders = $json['orders'] ?? [];
            $totalCount = (int) ($json['total_count'] ?? 0);
            $fetched = is_array($orders) ? count($orders) : 0;

            if ($fetched > 0) {
                foreach ($orders as $order) {
                    if (is_array($order)) {
                        $allOrders[] = $order;
                    }
                }
            }

            $offset += $max;
            $hasMore = $fetched >= $max && ($totalCount === 0 || $offset < $totalCount);

            if ($hasMore) {
                usleep(250000); // avoid Mirakl rate limits
            }
        } while ($hasMore && $page < $maxPages);

        return [
            'orders' => $allOrders,
            'total_count' => $totalCount > 0 ? $totalCount : count($allOrders),
        ];
    }

    /**
     * Flatten OR11 orders into one row per order line for the sales grid.
     *
     * @param  list<array<string, mixed>>  $orders
     * @return list<object>
     */
    public function flattenOrdersToLineRows(array $orders): array
    {
        $rows = [];

        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }

            $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];
            $shipAddr = is_array($customer['shipping_address'] ?? null) ? $customer['shipping_address'] : [];
            $customerName = trim(
                trim((string) ($customer['firstname'] ?? '')).' '.trim((string) ($customer['lastname'] ?? ''))
            );

            $lines = $order['order_lines'] ?? [];
            if (! is_array($lines) || $lines === []) {
                continue;
            }

            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $qty = max(0, (int) ($line['stock'] ?? 0));
                $linePrice = (float) ($line['price'] ?? 0);
                $unitPrice = isset($line['price_unit']) && is_numeric($line['price_unit'])
                    ? (float) $line['price_unit']
                    : ($qty > 0 ? $linePrice / $qty : $linePrice);
                $totalPrice = isset($line['total_price']) && is_numeric($line['total_price'])
                    ? (float) $line['total_price']
                    : $linePrice;
                $commission = (float) ($line['total_commission'] ?? $line['commission_fee'] ?? 0);

                $sku = trim((string) ($line['offer_sku'] ?? $line['product_shop_sku'] ?? ''));
                if ($sku === '') {
                    $sku = trim((string) ($line['product_sku'] ?? ''));
                }

                $rows[] = (object) [
                    'id' => $line['order_line_id'] ?? ($order['order_id'] ?? null),
                    'order_date' => $order['created_date'] ?? ($line['created_date'] ?? null),
                    'order_number' => $order['commercial_id'] ?? ($order['order_id'] ?? null),
                    'order_id' => $order['order_id'] ?? null,
                    'status' => $line['order_line_state'] ?? ($order['order_state'] ?? ''),
                    'sku' => $sku,
                    'product_name' => $line['product_title'] ?? null,
                    'stock' => $qty,
                    'unit_price' => $unitPrice,
                    'amount' => $totalPrice,
                    'commission' => $commission,
                    'commission_rule' => null,
                    'amount_transferred' => round($totalPrice - $commission, 2),
                    'shipping_company' => $order['shipping_company'] ?? null,
                    'tracking_number' => $order['shipping_tracking'] ?? null,
                    'tracking_url' => $order['shipping_tracking_url'] ?? null,
                    'customer' => $customerName,
                    'city' => $shipAddr['city'] ?? null,
                    'state' => $shipAddr['state'] ?? null,
                    'country' => $shipAddr['country_iso_code'] ?? ($shipAddr['country'] ?? null),
                    'category_label' => $line['category_label'] ?? null,
                    'mirakl_product_sku' => $line['product_sku'] ?? null,
                ];
            }
        }

        return $rows;
    }

    public function isConfigured(): bool
    {
        $mcmKey = trim((string) config('services.purchasingpower.mcm_api_key', ''));
        $apiKey = trim((string) config('services.purchasingpower.api_key', ''));

        return $mcmKey !== '' || $apiKey !== '';
    }

    /**
     * @return array{success: bool, message: string, sample_count?: int}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Purchasing Power MCM/API credentials missing (PURCHASING_POWER_MCM_API_KEY or PURCHASING_POWER_API_KEY).',
            ];
        }

        try {
            $result = $this->fetchOrders(now()->subDays(7), now(), 1);
            $count = count($result['orders'] ?? []);

            return [
                'success' => true,
                'message' => "Mirakl MCM OR11 reachable (sample: {$count} order(s) in last 7 days).",
                'sample_count' => $count,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Push listed price via Mirakl MCM PRI01 (uses PURCHASING_POWER_MCM_API_KEY).
     * Overrides BestBuy Connect updatePrice — PP seller price lives on MCM offers, not Connect catalog.
     *
     * @return array{success: bool, message: string, status_code?: int|null, import_id?: string|null}
     */
    public function updatePrice(string $sku, float $price): array
    {
        $sku = trim($sku);
        $price = round((float) $price, 2);

        if ($sku === '' || $price <= 0) {
            return ['success' => false, 'message' => 'Valid SKU and price are required.', 'status_code' => 422];
        }

        $apiKey = trim((string) config('services.purchasingpower.mcm_api_key', ''));
        $baseUrl = rtrim((string) config('services.purchasingpower.mcm_base_url', ''), '/');
        if ($apiKey === '' || $baseUrl === '') {
            return [
                'success' => false,
                'message' => 'Purchasing Power MCM API key/base URL not configured (PURCHASING_POWER_MCM_API_KEY).',
                'status_code' => 401,
            ];
        }

        // Confirm offer exists (OF21) before pricing import
        $offerSku = $this->resolveMcmOfferSku($sku, $apiKey, $baseUrl);
        if ($offerSku === null) {
            return [
                'success' => false,
                'message' => "No Purchasing Power MCM offer found for SKU: {$sku}",
                'status_code' => 404,
            ];
        }

        $csv = "offer-sku;price\n"
            .'"'.str_replace('"', '""', $offerSku).'";'
            .number_format($price, 2, '.', '')."\n";

        $query = [];
        $shopId = config('services.purchasingpower.shop_id');
        if ($shopId !== null && $shopId !== '') {
            $query['shop_id'] = (int) $shopId;
        }

        try {
            $url = $baseUrl.'/api/offers/pricing/imports';
            if ($query !== []) {
                $url .= '?'.http_build_query($query);
            }

            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(60)
                ->attach('file', $csv, 'pp-price-'.preg_replace('/[^A-Za-z0-9_-]+/', '_', $offerSku).'.csv')
                ->post($url);

            if (! $response->successful()) {
                Log::warning('Purchasing Power MCM PRI01 price push failed', [
                    'sku' => $sku,
                    'offer_sku' => $offerSku,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 800),
                ]);

                return [
                    'success' => false,
                    'message' => 'Purchasing Power price push failed: HTTP '.$response->status().' '.substr($response->body(), 0, 300),
                    'status_code' => $response->status(),
                ];
            }

            $json = $response->json() ?? [];
            $importId = $json['import_id'] ?? $json['importId'] ?? null;
            if ($importId === null || $importId === '') {
                return [
                    'success' => false,
                    'message' => 'Purchasing Power price push accepted no import_id.',
                    'status_code' => $response->status(),
                ];
            }

            $import = $this->waitForPricingImport((string) $importId, $apiKey, $baseUrl);
            $linesOk = (int) ($import['lines_in_success'] ?? 0);
            $linesErr = (int) ($import['lines_in_error'] ?? 0);
            $offersUpdated = (int) ($import['offers_updated'] ?? 0);
            $status = strtoupper((string) ($import['status'] ?? ''));

            if ($linesErr > 0 || $linesOk < 1 || $offersUpdated < 1) {
                $errMsg = $this->fetchPricingImportErrorSummary((string) $importId, $apiKey, $baseUrl);
                Log::warning('Purchasing Power MCM PRI01 completed with errors', [
                    'sku' => $sku,
                    'offer_sku' => $offerSku,
                    'import_id' => $importId,
                    'status' => $status,
                    'lines_in_success' => $linesOk,
                    'lines_in_error' => $linesErr,
                    'error' => $errMsg,
                ]);

                return [
                    'success' => false,
                    'message' => $errMsg !== ''
                        ? ('Purchasing Power price push failed: '.$errMsg)
                        : ('Purchasing Power price push failed (import '.$importId.' status '.$status.')'),
                    'status_code' => 400,
                    'import_id' => (string) $importId,
                ];
            }

            // Keep local listed price in sync only after MCM confirms update
            try {
                PurchasingPowerProduct::updateOrCreate(
                    ['sku' => $offerSku],
                    ['price' => $price]
                );
            } catch (\Throwable $e) {
                Log::warning('Purchasing Power local price sync after PRI01 failed', [
                    'sku' => $offerSku,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('Purchasing Power MCM PRI01 price push complete', [
                'sku' => $sku,
                'offer_sku' => $offerSku,
                'price' => $price,
                'import_id' => $importId,
            ]);

            return [
                'success' => true,
                'message' => 'Price $'.number_format($price, 2).' pushed to Purchasing Power for SKU: '.$offerSku
                    .' (import '.$importId.')',
                'status_code' => $response->status(),
                'import_id' => (string) $importId,
            ];
        } catch (\Throwable $e) {
            Log::error('Purchasing Power MCM PRI01 exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Purchasing Power API error: '.$e->getMessage(),
                'status_code' => null,
            ];
        }
    }

    /**
     * Resolve live MCM shop_sku via OF21 only (no stale local fallback).
     */
    private function resolveMcmOfferSku(string $sku, string $apiKey, string $baseUrl): ?string
    {
        $candidates = array_values(array_unique(array_filter([
            $sku,
            strtoupper($sku),
        ])));

        foreach ($candidates as $candidate) {
            $params = ['sku' => $candidate, 'max' => 20];
            $shopId = config('services.purchasingpower.shop_id');
            if ($shopId !== null && $shopId !== '') {
                $params['shop_id'] = (int) $shopId;
            }

            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(30)
                    ->get($baseUrl.'/api/offers', $params);

                if (! $response->successful()) {
                    continue;
                }

                $offers = $response->json('offers') ?? [];
                if (! is_array($offers) || $offers === []) {
                    continue;
                }

                $skuUpper = strtoupper(trim($candidate));
                foreach ($offers as $offer) {
                    if (! is_array($offer)) {
                        continue;
                    }
                    $shopSku = trim((string) ($offer['shop_sku'] ?? ''));
                    if ($shopSku !== '' && strtoupper($shopSku) === $skuUpper) {
                        return $shopSku;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Purchasing Power OF21 lookup failed', [
                    'sku' => $candidate,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForPricingImport(string $importId, string $apiKey, string $baseUrl): array
    {
        for ($i = 0; $i < 15; $i++) {
            if ($i > 0) {
                usleep(1500000);
            }
            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(30)
                    ->get($baseUrl.'/api/offers/pricing/imports', ['import_id' => $importId]);

                if (! $response->successful()) {
                    continue;
                }

                $row = ($response->json('data') ?? [])[0] ?? null;
                if (! is_array($row)) {
                    continue;
                }

                $status = strtoupper((string) ($row['status'] ?? ''));
                if (in_array($status, ['COMPLETE', 'FAILED', 'CANCELLED'], true)) {
                    return $row;
                }
            } catch (\Throwable $e) {
                Log::warning('Purchasing Power PRI01 status poll failed', [
                    'import_id' => $importId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    private function fetchPricingImportErrorSummary(string $importId, string $apiKey, string $baseUrl): string
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->get($baseUrl.'/api/offers/pricing/imports/'.$importId.'/error_report');

            if (! $response->successful()) {
                return '';
            }

            $body = trim((string) $response->body());
            $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
            // Skip CSV header; return first error line condensed
            foreach ($lines as $idx => $line) {
                if ($idx === 0) {
                    continue;
                }
                $line = trim($line);
                if ($line !== '') {
                    return substr($line, 0, 300);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return '';
    }

    /**
     * Push stock updates to Mirakl MCM offers (stub until OF24 wired).
     *
     * @param  array<int, array{sku: string, quantity: int}>  $items
     * @return array{pushed: int, failed: int, message: string}
     */
    public function updateItemInventoryBulk(array $items): array
    {
        if ($items === []) {
            return ['pushed' => 0, 'failed' => 0, 'message' => 'No items to push.'];
        }

        Log::info('PurchasingPowerApiService: updateItemInventoryBulk stub — local stock persisted only', [
            'count' => count($items),
        ]);

        return [
            'pushed' => count($items),
            'failed' => 0,
            'message' => 'Mirakl offer stock push is not wired yet — updated local purchasing_power_products.stock only.',
        ];
    }
}
