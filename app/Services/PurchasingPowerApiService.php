<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Purchasing Power — Mirakl Connect channel + MCM seller APIs (OF21 / OR11).
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

                $qty = max(0, (int) ($line['quantity'] ?? 0));
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
                    'quantity' => $qty,
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
}
