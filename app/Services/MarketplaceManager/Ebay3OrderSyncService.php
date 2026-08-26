<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay3OrderMetric;
use App\Models\MarketplaceSyncSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch eBay 3 orders (Sell Fulfillment API) into ebay3_order_metrics.
 */
class Ebay3OrderSyncService
{
    use PreservesMarketplaceImportStatus;

    protected string $baseUrl = 'https://api.ebay.com';

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        if (! Schema::hasTable('ebay3_order_metrics')) {
            return ['success' => false, 'message' => 'ebay3_order_metrics table missing.', 'upserted' => 0, 'pages' => 0];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('ebay3')) {
            return ['success' => true, 'message' => 'Order fetch disabled in settings.', 'upserted' => 0, 'pages' => 0];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'eBay 3 API credentials missing or token failed.', 'upserted' => 0, 'pages' => 0];
        }

        $from = Carbon::parse($fromDate)->startOfDay()->setTimezone('UTC');
        $startDate = $from->format('Y-m-d\TH:i:s.000\Z');

        $upserted = 0;
        $fetched = 0;
        $pages = 0;
        $offset = 0;
        $limit = 50;

        try {
            do {
                $pages++;
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => "Bearer {$token}",
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(60)
                    ->get($this->baseUrl.'/sell/fulfillment/v1/order', [
                        'filter' => "creationdate:[{$startDate}..]",
                        'limit' => $limit,
                        'offset' => $offset,
                    ]);

                if (! $response->successful()) {
                    return [
                        'success' => false,
                        'message' => 'eBay 3 order fetch failed: '.$response->body(),
                        'upserted' => $upserted,
                        'pages' => $pages,
                    ];
                }

                $data = $response->json() ?? [];
                $orders = is_array($data['orders'] ?? null) ? $data['orders'] : [];
                $totalCount = (int) ($data['total'] ?? 0);

                if ($orders === []) {
                    break;
                }

                $fetched += count($orders);
                foreach ($orders as $order) {
                    if (is_array($order)) {
                        $upserted += $this->upsertOrder($order);
                    }
                }

                $offset += $limit;
            } while ($offset < $totalCount && $pages < 200);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'eBay 3 order fetch failed: '.$e->getMessage(),
                'upserted' => $upserted,
                'pages' => $pages,
            ];
        }

        if ($import || MarketplaceShopifyImportQueue::shouldDispatchImports('ebay3')) {
            $this->dispatchImportsForNewOrders();
        }

        if (Ebay3OrderPushService::canAutoSyncAddress()) {
            try {
                \App\Jobs\SyncEbay3AddressJob::dispatch(false, 25);
            } catch (\Throwable $e) {
                Log::warning('Ebay3OrderSyncService: could not queue address sync', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'message' => "Synced {$upserted} eBay 3 order line(s) from {$fetched} order(s) ({$pages} page(s)).",
            'upserted' => $upserted,
            'pages' => $pages,
            'fetched' => $fetched,
            'stored' => $upserted,
        ];
    }

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function fetchAndStoreFromDate(string $fromDate): array
    {
        return $this->sync($fromDate, false);
    }

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function fetchAndStore(int $days = 7): array
    {
        $from = Carbon::now()->subDays(max(0, $days))->toDateString();

        return $this->sync($from, false);
    }

    public function dispatchImportsForNewOrders(): int
    {
        $settings = MarketplaceSyncSettings::getFor('ebay3');
        if (! ($settings['order']['auto_import_to_shopify'] ?? false)) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('ebay3', $settings);
        $queue = MarketplaceManagerRegistry::queueFor('ebay3');
        MarketplaceShopifyImportQueue::prepareForDispatch(Ebay3OrderMetric::class, $queue);

        $orders = Ebay3OrderMetric::query()
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', MarketplaceShopifyImportQueue::DISPATCHABLE_IMPORT_STATUSES);
            })
            ->orderBy('id')
            ->limit(200)
            ->get();

        $dispatched = 0;
        foreach ($orders as $order) {
            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('ebay3', $order)) {
                continue;
            }

            try {
                MarketplaceShopifyImportQueue::push(
                    new \App\Jobs\ImportEbay3OrderToShopify((int) $order->id),
                    $queue
                );
                $order->update(['import_status' => 'queued']);
                $dispatched++;
            } catch (\Throwable $e) {
                Log::warning('Ebay3OrderSyncService: failed to queue import', [
                    'id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function upsertOrder(array $order): int
    {
        $orderId = trim((string) ($order['orderId'] ?? ''));
        if ($orderId === '') {
            return 0;
        }

        $existingPayload = Ebay3OrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNotNull('raw_payload')
            ->orderBy('id')
            ->value('raw_payload');
        if (is_array($existingPayload)) {
            $order = $this->mergeAddressFields($existingPayload, $order);
        }

        $status = trim((string) ($order['orderFulfillmentStatus'] ?? $order['orderPaymentStatus'] ?? ''));
        $orderDate = null;
        if (! empty($order['creationDate'])) {
            try {
                $orderDate = Carbon::parse($order['creationDate']);
            } catch (\Throwable $e) {
                $orderDate = null;
            }
        }

        $lineItems = is_array($order['lineItems'] ?? null) ? $order['lineItems'] : [];
        if ($lineItems === []) {
            $existing = Ebay3OrderMetric::query()
                ->where('order_id', $orderId)
                ->where('sku', '__order__')
                ->first();
            Ebay3OrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => '__order__'],
                array_merge([
                    'order_number' => trim((string) ($order['legacyOrderId'] ?? $orderId)),
                    'order_date' => $orderDate,
                    'status' => $status,
                    'quantity' => 1,
                    'raw_payload' => $order,
                ], $this->importStatusForUpsert($existing))
            );

            return 1;
        }

        $count = 0;
        foreach ($lineItems as $line) {
            if (! is_array($line)) {
                continue;
            }
            $sku = trim((string) ($line['sku'] ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($line['legacyItemId'] ?? '__unknown__'));
            }
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $amount = isset($line['lineItemCost']['value']) ? (float) $line['lineItemCost']['value'] : null;

            Ebay3OrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => $sku],
                array_merge([
                    'order_number' => trim((string) ($order['legacyOrderId'] ?? $orderId)),
                    'order_date' => $orderDate,
                    'status' => $status !== '' ? $status : trim((string) ($line['lineItemFulfillmentStatus'] ?? '')),
                    'product_id' => trim((string) ($line['legacyItemId'] ?? '')),
                    'display_title' => trim((string) ($line['title'] ?? '')),
                    'quantity' => $qty,
                    'amount' => $amount,
                    'raw_payload' => $order,
                ], $this->importStatusForUpsert(
                    Ebay3OrderMetric::query()->where('order_id', $orderId)->where('sku', $sku)->first()
                ))
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    protected function mergeAddressFields(array $existing, array $incoming): array
    {
        $existingInstr = $existing['fulfillmentStartInstructions'] ?? null;
        $incomingInstr = $incoming['fulfillmentStartInstructions'] ?? null;
        $newEmpty = $incomingInstr === null || $incomingInstr === [] ;
        $oldFilled = is_array($existingInstr) && $existingInstr !== [];
        if ($newEmpty && $oldFilled) {
            $incoming['fulfillmentStartInstructions'] = $existingInstr;
        }

        $existingBuyer = $existing['buyer'] ?? null;
        $incomingBuyer = $incoming['buyer'] ?? null;
        if (($incomingBuyer === null || $incomingBuyer === []) && is_array($existingBuyer) && $existingBuyer !== []) {
            $incoming['buyer'] = $existingBuyer;
        }

        return $incoming;
    }

    protected function getAccessToken(): ?string
    {
        $clientId = config('services.ebay3.app_id');
        $clientSecret = config('services.ebay3.cert_id');
        $refreshToken = config('services.ebay3.refresh_token');

        if (! $clientId || ! $clientSecret || ! $refreshToken) {
            return null;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->timeout(30)
                ->post($this->baseUrl.'/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'scope' => 'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
                ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            // Retry without explicit scope (same as ebay3:daily).
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->timeout(30)
                ->post($this->baseUrl.'/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            return $response->successful() ? $response->json('access_token') : null;
        } catch (\Throwable $e) {
            Log::error('Ebay3OrderSyncService: token failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
