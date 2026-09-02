<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportNeweggOrderToShopify;
use App\Models\MarketplaceSyncSettings;
use App\Models\NeweggOrderMetric;
use App\Services\NeweggApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch Newegg orders into newegg_order_metrics (Shopify import queue).
 */
class NeweggOrderSyncService
{
    use PreservesMarketplaceImportStatus;

    public function __construct(
        protected NeweggApiService $neweggApi
    ) {}

    /**
     * @return array{success: bool, message: string, upserted: int, pages: int, fetched?: int, stored?: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        if (! $this->neweggApi->isConfigured()) {
            return ['success' => false, 'message' => 'Newegg API credentials missing.', 'upserted' => 0, 'pages' => 0];
        }

        if (! Schema::hasTable('newegg_order_metrics')) {
            return ['success' => false, 'message' => 'newegg_order_metrics table missing.', 'upserted' => 0, 'pages' => 0];
        }

        if (! MarketplaceSyncSettings::canFetchOrders('newegg')) {
            return ['success' => true, 'message' => 'Order fetch disabled in settings.', 'upserted' => 0, 'pages' => 0];
        }

        $tz = 'America/Los_Angeles';
        $from = Carbon::parse($fromDate, $tz)->startOfDay();
        $to = Carbon::now($tz);
        $latestStored = NeweggOrderMetric::query()->max('order_date');
        if ($latestStored) {
            $gapFrom = Carbon::parse($latestStored, $tz)->subDay()->startOfDay();
            if ($gapFrom->lt($from)) {
                $from = $gapFrom;
            }
        }
        $earliest = Carbon::now($tz)->subDays(30)->startOfDay();
        if ($from->lt($earliest)) {
            $from = $earliest;
        }

        $upserted = 0;
        $pages = 0;

        for ($page = 1; $page <= 50; $page++) {
            $res = $this->neweggApi->getOrders([
                'Type' => 0,
                'OrderDateFrom' => $from->format('Y-m-d H:i:s'),
                'OrderDateTo' => $to->format('Y-m-d H:i:s'),
            ], $page, 100);

            if (! empty($res['blocked_by_cloudflare'])) {
                return [
                    'success' => false,
                    'message' => 'Blocked by Cloudflare. Whitelist server IP in Newegg Seller Portal.',
                    'upserted' => $upserted,
                    'pages' => $pages,
                    'fetched' => $upserted,
                    'stored' => $upserted,
                ];
            }

            if (empty($res['ok']) && empty($res['json'])) {
                $httpStatus = (int) ($res['status'] ?? 0);
                $message = $res['error'] ?? ('Order fetch failed HTTP '.$httpStatus);
                if ($httpStatus === 504) {
                    $message = 'Newegg order API timed out (HTTP 504). Retry from a Newegg-whitelisted server.';
                }

                return [
                    'success' => $upserted > 0,
                    'message' => $message,
                    'upserted' => $upserted,
                    'pages' => $pages,
                    'fetched' => $upserted,
                    'stored' => $upserted,
                ];
            }

            $json = is_array($res['json'] ?? null) ? $res['json'] : [];
            if (isset($json['NeweggAPIResponse']) && is_array($json['NeweggAPIResponse'])) {
                $json = $json['NeweggAPIResponse'];
            }
            if ($json !== [] && array_is_list($json)) {
                $code = (string) (data_get($json, '0.Code') ?? '');
                $msg = (string) (data_get($json, '0.Message') ?? 'Newegg order API error');

                return [
                    'success' => $upserted > 0,
                    'message' => trim($code.($msg !== '' ? ': '.$msg : '')),
                    'upserted' => $upserted,
                    'pages' => $pages,
                    'fetched' => $upserted,
                    'stored' => $upserted,
                ];
            }
            $flag = $json['IsSuccess'] ?? null;
            if ($flag === false || $flag === 'false' || $flag === 0 || $flag === '0') {
                $msg = (string) (data_get($json, 'ResponseBody.ResponseList.Response.Message')
                    ?? data_get($json, 'Memo')
                    ?? 'Newegg IsSuccess=false');

                return [
                    'success' => $upserted > 0,
                    'message' => $msg,
                    'upserted' => $upserted,
                    'pages' => $pages,
                    'fetched' => $upserted,
                    'stored' => $upserted,
                ];
            }

            $pages++;
            $orders = app(NeweggOrderDetailService::class)->extractOrders($json);
            if ($orders === []) {
                break;
            }

            foreach ($orders as $order) {
                $upserted += $this->upsertOrder($order);
            }

            if (count($orders) < 100) {
                break;
            }
        }

        $autoImport = (bool) $import || MarketplaceShopifyImportQueue::shouldDispatchImports('newegg');
        if ($autoImport) {
            $this->dispatchImportsForNewOrders();
        }

        // Address sync is queued by SyncMarketplaceOrdersJob (same as AliExpress / Reverb).

        return [
            'success' => true,
            'message' => "Synced {$upserted} Newegg order line(s) across {$pages} page(s).",
            'upserted' => $upserted,
            'pages' => $pages,
            'fetched' => $upserted,
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
        return MarketplaceShopifyImportQueue::dispatchLatestUnpushed(
            'newegg',
            NeweggOrderMetric::class,
            static fn (int $id) => new ImportNeweggOrderToShopify($id)
        );
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function upsertOrder(array $order): int
    {
        $detailService = app(NeweggOrderDetailService::class);
        $unwrapped = $detailService->extractOrders(['OrderInfoList' => [$order]]);
        $order = $unwrapped[0] ?? $order;

        $orderId = (string) ($order['OrderNumber'] ?? $order['SellerOrderNumber'] ?? '');
        if ($orderId === '') {
            return 0;
        }

        // Preserve ship-to / customer PII if this page payload is blank.
        $existingPayload = NeweggOrderMetric::query()
            ->where('order_id', $orderId)
            ->whereNotNull('raw_payload')
            ->orderBy('id')
            ->value('raw_payload');
        if (is_array($existingPayload)) {
            $order = $this->mergeShipToFields($existingPayload, $order);
        }

        $orderDate = $order['OrderDate'] ?? $order['OrderDownloadedOn'] ?? null;
        $status = (string) ($order['OrderStatus'] ?? $order['OrderStatusDescription'] ?? '');
        $items = data_get($order, 'ItemInfoList') ?? data_get($order, 'ItemList') ?? [];
        if (isset($items['SellerPartNumber']) || isset($items['NeweggItemNumber'])) {
            $items = [$items];
        }
        if (! is_array($items) || $items === []) {
            $existing = NeweggOrderMetric::query()
                ->where('order_id', $orderId)
                ->where('sku', '__order__')
                ->first();
            NeweggOrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => '__order__'],
                array_merge([
                    'order_number' => $orderId,
                    'order_date' => $orderDate ? Carbon::parse($orderDate) : null,
                    'status' => $status,
                    'quantity' => 1,
                    // Match AliExpress / Reverb wrapper shape.
                    'raw_payload' => ['order' => $order],
                ], $this->importStatusForUpsert($existing))
            );

            return 1;
        }

        $count = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['SellerPartNumber'] ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($item['NeweggItemNumber'] ?? '__unknown__'));
            }
            $qty = (int) ($item['OrderedQty'] ?? $item['Quantity'] ?? 1);
            $amount = isset($item['ExtendUnitPrice']) ? (float) $item['ExtendUnitPrice'] : null;

            NeweggOrderMetric::updateOrCreate(
                ['order_id' => $orderId, 'sku' => $sku],
                array_merge([
                    'order_number' => $orderId,
                    'order_date' => $orderDate ? Carbon::parse($orderDate) : null,
                    'status' => $status,
                    'product_id' => (string) ($item['NeweggItemNumber'] ?? ''),
                    'display_title' => (string) ($item['Description'] ?? $item['Title'] ?? ''),
                    'quantity' => max(1, $qty),
                    'amount' => $amount,
                    // Match AliExpress / Reverb: raw['order'] + raw['line'].
                    'raw_payload' => ['order' => $order, 'line' => $item],
                ], $this->importStatusForUpsert(
                    NeweggOrderMetric::query()->where('order_id', $orderId)->where('sku', $sku)->first()
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
    protected function mergeShipToFields(array $existing, array $incoming): array
    {
        // Cached payload may be wrapped (OrderInfo / order) — unwrap before comparing ShipTo*.
        if (is_array($existing['order'] ?? null)) {
            $existing = $existing['order'];
        }
        if (isset($existing['OrderInfo']) && is_array($existing['OrderInfo'])) {
            $inner = $existing['OrderInfo'];
            if (isset($inner[0]) && is_array($inner[0])) {
                $inner = $inner[0];
            }
            if (is_array($inner)) {
                $existing = $inner;
            }
        }

        $keys = [
            'ShipToAddress1', 'ShipToAddress2', 'ShipToCityName', 'ShipToStateCode',
            'ShipToZipCode', 'ShipToCountryCode', 'ShipToFirstName', 'ShipToLastName',
            'ShipToCompany', 'ShipToPhoneNumber', 'CustomerName', 'CustomerEmailAddress',
            'CustomerPhoneNumber',
        ];

        foreach ($keys as $key) {
            $newVal = trim((string) ($incoming[$key] ?? ''));
            $oldVal = trim((string) ($existing[$key] ?? ''));
            if ($newVal === '' && $oldVal !== '') {
                $incoming[$key] = $existing[$key];
            }
        }

        return $incoming;
    }

    protected function queueReadyImports(): void
    {
        $this->dispatchImportsForNewOrders();
    }
}
