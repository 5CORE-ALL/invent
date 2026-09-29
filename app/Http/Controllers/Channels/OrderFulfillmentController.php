<?php

namespace App\Http\Controllers\Channels;

use App\Models\AmazonOrder;
use App\Models\Inventory;
use App\Models\OrderFulfillmentTracking;
use App\Models\ProductMaster;
use App\Services\FourSellerApiService;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\MarketplaceOrderPaidFilter;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use App\Services\SheinApiService;
use App\Services\ShipmentTrackingService;
use App\Services\VeeqoApiService;
use App\Support\TrackingCarrierGuesser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Order Fulfillment — marketplace API order tables only.
 * Inventory is the CP Master (product_master) stock for the order SKU.
 */
class OrderFulfillmentController extends SalesOrderFulfillmentController
{
    /** Orders and tracking updates begin on this Eastern calendar date. */
    public const EARLIEST_ORDER_DATE = '2026-09-15';

    public function index(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        $channels = collect(MarketplaceManagerRegistry::channels())
            ->filter(fn ($c) => ($c['enabled'] ?? false) === true)
            ->map(fn ($c) => [
                'slug' => (string) ($c['slug'] ?? ''),
                'label' => (string) ($c['label'] ?? ($c['slug'] ?? '')),
            ])
            ->filter(fn ($c) => ($c['slug'] ?? '') !== '')
            ->values()
            ->all();

        [$from, $to] = $this->defaultOrderDateRange();

        return view('channels.order_fulfillment', [
            'ofChannels' => $channels,
            'ofDateFrom' => $from->toDateString(),
            'ofDateTo' => $to->toDateString(),
            'ofDateEarliest' => self::EARLIEST_ORDER_DATE,
            'ofPageTitle' => 'Order Fulfillment',
            'ofDeliveredOnly' => false,
            'ofTransitOnly' => false,
            'ofScanPendingOnly' => false,
        ]);
    }

    public function delivered(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        return $this->index($gofo, $veeqo)->with([
            'ofPageTitle' => 'Delivered',
            'ofDeliveredOnly' => true,
            'ofTransitOnly' => false,
            'ofScanPendingOnly' => false,
        ]);
    }

    public function transit(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        return $this->index($gofo, $veeqo)->with([
            'ofPageTitle' => 'Transit',
            'ofDeliveredOnly' => false,
            'ofTransitOnly' => true,
            'ofScanPendingOnly' => false,
        ]);
    }

    public function scanPending(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        return $this->index($gofo, $veeqo)->with([
            'ofPageTitle' => 'Scan Pending',
            'ofDeliveredOnly' => false,
            'ofTransitOnly' => false,
            'ofScanPendingOnly' => true,
        ]);
    }

    public function data(): JsonResponse
    {
        try {
            @set_time_limit(120);

            $rows = $this->collectFulfillmentRows();
            $rows = $this->attachCpMasterInventory($rows);
            $rows = $this->attachSavedTracking($rows);
            $rows = $this->attachCarrierAndTrackingStatus($rows);
            if (request()->boolean('delivered')) {
                $rows = array_values(array_filter(
                    $rows,
                    fn (array $row) => $this->fulfillmentRowIsDelivered($row)
                ));
            } elseif (request()->boolean('transit')) {
                $rows = array_values(array_filter(
                    $rows,
                    fn (array $row) => $this->fulfillmentRowIsInTransit($row)
                ));
            } elseif (request()->boolean('scan_pending')) {
                $rows = array_values(array_filter(
                    $rows,
                    fn (array $row) => $this->fulfillmentRowIsScanPending($row)
                ));
            }

            $channels = [];
            $paid = 0;
            $unpaid = 0;
            foreach ($rows as $row) {
                $slug = (string) ($row['mm_slug'] ?? '');
                if ($slug !== '') {
                    $channels[$slug] = true;
                }
                if (! empty($row['paid'])) {
                    $paid++;
                } else {
                    $unpaid++;
                }
            }

            [$from, $to] = $this->resolveOrderDateRange();

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
                'channel_count' => count($channels),
                'paid_count' => $paid,
                'unpaid_count' => $unpaid,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load order fulfillment.',
                'data' => [],
                'count' => 0,
                'channel_count' => 0,
                'paid_count' => 0,
                'unpaid_count' => 0,
            ], 500);
        }
    }

    /**
     * Delivered by carrier tracking status, or by the marketplace order status.
     *
     * @param  array<string, mixed>  $row
     */
    protected function fulfillmentRowIsDelivered(array $row): bool
    {
        $tracking = strtolower(trim((string) ($row['tracking_status'] ?? '')));
        if ($tracking === 'delivered') {
            return true;
        }

        $status = strtolower(str_replace([' ', '-', '_'], '', trim((string) ($row['status'] ?? ''))));

        return in_array($status, [
            'delivered',
            'completed',
            'received',
            'finish',
            'buyeracceptgoods',
            'tradefinished',
            'partiallydelivered',
        ], true);
    }

    /**
     * In transit by carrier tracking, or by a shipped marketplace status.
     * Delivered rows stay on the Delivered page.
     *
     * @param  array<string, mixed>  $row
     */
    protected function fulfillmentRowIsInTransit(array $row): bool
    {
        if ($this->fulfillmentRowIsDelivered($row)) {
            return false;
        }

        $tracking = strtolower(trim((string) ($row['tracking_status'] ?? '')));
        if (in_array($tracking, ['in transit', 'out for delivery'], true)) {
            return true;
        }

        $status = strtolower(str_replace([' ', '-', '_'], '', trim((string) ($row['status'] ?? ''))));

        return in_array($status, [
            'intransit',
            'shipped',
            'partiallyshipped',
            'outfordelivery',
            'ontheway',
        ], true);
    }

    /**
     * A tracking number exists and the carrier has not scanned the package yet.
     *
     * @param  array<string, mixed>  $row
     */
    protected function fulfillmentRowIsScanPending(array $row): bool
    {
        if ($this->fulfillmentRowIsDelivered($row) || $this->fulfillmentRowIsInTransit($row)) {
            return false;
        }
        if (trim((string) ($row['tracking'] ?? '')) === '') {
            return false;
        }

        $tracking = strtolower(trim((string) ($row['tracking_status'] ?? '')));

        return $tracking === ''
            || in_array($tracking, ['label created / no scan', 'pending scan'], true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collectFulfillmentRows(): array
    {
        $rows = [];

        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            if (! ($channel['enabled'] ?? false)) {
                continue;
            }

            $slug = (string) ($channel['slug'] ?? '');
            if ($slug === '' || $slug === 'shopify') {
                continue;
            }

            $query = $this->allOrdersQuery($slug);
            if ($query === null) {
                continue;
            }
            $query = $this->applyFastOrderDateFilter($query, $slug);

            try {
                $orders = $this->fulfillmentOrdersForChannel($slug, $query);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            foreach ($orders as $order) {
                try {
                    foreach ($this->rowsFromMarketplaceOrder($slug, $channel, $order) as $row) {
                        $rows[] = $row;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $ak = (string) ($a['order_date'] ?? '');
            $bk = (string) ($b['order_date'] ?? '');
            if ($ak === '' && $bk === '') {
                return 0;
            }
            if ($ak === '') {
                return 1;
            }
            if ($bk === '') {
                return -1;
            }

            return strcmp($ak, $bk);
        });

        return array_values($rows);
    }

    /**
     * Default view is the last 30 days, and never earlier than 15 Sep 2026.
     * A chosen From / To range is applied as-is inside that same floor.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function resolveOrderDateRange(): array
    {
        $tz = $this->sofTimezone();
        [$defaultFrom, $defaultTo] = $this->defaultOrderDateRange();
        $earliest = $this->earliestOrderDate();

        $from = $this->parseCaliforniaDateInput(trim((string) request()->input('date_from', '')), $defaultFrom);
        $to = $this->parseCaliforniaDateInput(trim((string) request()->input('date_to', '')), $defaultTo, true);

        if ($from->lt($earliest)) {
            $from = $earliest->copy();
        }
        $latest = now($tz)->endOfDay();
        if ($to->gt($latest)) {
            $to = $latest->copy();
        }
        if ($from->gt($to)) {
            $from = $to->copy()->startOfDay();
            if ($from->lt($earliest)) {
                $from = $earliest->copy();
            }
        }

        return [$from, $to];
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function defaultOrderDateRange(): array
    {
        $tz = $this->sofTimezone();
        $earliest = $this->earliestOrderDate();
        $from = now($tz)->subDays(30)->startOfDay();
        if ($from->lt($earliest)) {
            $from = $earliest->copy();
        }

        return [$from, now($tz)->endOfDay()];
    }

    protected function earliestOrderDate(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse(self::EARLIEST_ORDER_DATE, $this->sofTimezone())->startOfDay();
    }

    protected function fulfillmentOrdersForChannel(string $slug, Builder $query)
    {
        $this->selectLightOrderColumns($query, $slug);

        return $query->get();
    }

    /**
     * Range on the channel date column only. Avoids whereDate() and JSON payloads.
     */
    protected function applyFastOrderDateFilter(Builder $query, string $slug): Builder
    {
        [$from, $to] = $this->resolveOrderDateRange();
        $bounds = $this->californiaSqlBounds($from, $to);
        $column = $this->orderDateColumn($slug) ?? 'order_date';
        $table = $query->getModel()->getTable();
        if (! Schema::hasColumn($table, $column)) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array($slug, ['amazon', 'ebay1', 'ebay2', 'ebay3'], true)) {
            return $query->whereBetween($table.'.'.$column, [
                $from->copy()->utc()->format('Y-m-d H:i:s'),
                $to->copy()->utc()->format('Y-m-d H:i:s'),
            ]);
        }

        if ($slug === 'wayfair') {
            return $query->whereBetween($table.'.'.$column, [$bounds['from_date'], $bounds['to_date']]);
        }

        return $query->whereBetween($table.'.'.$column, [$bounds['from_dt'], $bounds['to_dt']]);
    }

    protected function selectLightOrderColumns(Builder $query, string $slug): void
    {
        $table = $query->getModel()->getTable();
        $wanted = match ($slug) {
            'amazon' => ['id', 'amazon_order_id', 'order_date', 'status'],
            'temu', 'temu2' => ['id', 'parent_order_status_text', 'order_status_text', 'parent_order_time', 'parent_order_sn', 'order_sn', 'display_sku', 'ext_code', 'product_sku_id'],
            'tiktok', 'tiktok2' => ['id', 'order_id', 'order_status', 'line_status', 'order_created_at', 'seller_sku', 'sku_id'],
            'bestbuy', 'macy' => ['id', 'status', 'order_created_at', 'sku', 'order_id', 'channel_order_id'],
            'purchasingpower' => ['id', 'status', 'date_created', 'offer_sku', 'product_sku', 'order_id', 'order_number'],
            'wayfair' => ['id', 'status', 'po_date', 'sku', 'po_number'],
            'doba' => ['id', 'order_status', 'order_time', 'sku', 'order_no', 'platform_order_no'],
            default => ['id', 'status', 'order_date', 'sku', 'order_id', 'order_number'],
        };

        try {
            $have = array_flip(Schema::getColumnListing($table));
        } catch (\Throwable) {
            return;
        }

        $keep = array_values(array_filter($wanted, static fn (string $column) => isset($have[$column])));
        if ($keep === []) {
            return;
        }

        $query->select(array_map(static fn (string $column) => $table.'.'.$column, $keep));

        if ($slug === 'amazon') {
            $query->with(['items' => function ($items): void {
                $itemTable = $items->getModel()->getTable();
                $items->select([$itemTable.'.id', $itemTable.'.amazon_order_id', $itemTable.'.sku']);
            }]);
        }
    }

    /**
     * @param  array<string, mixed>  $channel
     * @return list<array<string, mixed>>
     */
    protected function rowsFromMarketplaceOrder(string $slug, array $channel, object $order): array
    {
        $n = $this->lightOrderFields($slug, $order);
        $paid = MarketplaceOrderPaidFilter::isPaid($slug, $order);
        $status = trim((string) ($n['status'] ?? ''));
        $apiOrderId = trim((string) ($n['order_id'] ?? ''));
        $orderNumber = trim((string) ($n['order_number'] ?? ''));
        $displayOrderId = $orderNumber !== '' ? $orderNumber : $apiOrderId;

        $dateSource = $n['order_date'] ?? null;
        $dateTz = $slug === 'shein' ? SheinApiService::API_TIMEZONE : null;
        if (in_array($slug, ['ebay1', 'ebay2', 'ebay3'], true)) {
            $dateSource = $this->ebayDisplayedOrderDate($order, $n);
            $dateTz = null;
        } elseif ($slug === 'amazon') {
            $dateSource = $this->utcWallClockDisplayedDate(
                $order,
                'order_date',
                $n['raw_payload'] ?? null,
                ['PurchaseDate', 'purchaseDate']
            );
            $dateTz = null;
        } elseif (in_array($slug, ['bestbuy', 'macy'], true)) {
            $dateSource = $this->utcWallClockDisplayedDate($order, 'order_created_at', null, []);
            $dateTz = null;
        }

        $base = [
            'channel' => (string) ($channel['label'] ?? $slug),
            'mm_slug' => $slug,
            'order_id' => $displayOrderId,
            'order_date' => $this->formatOrderDate($dateSource, $dateTz),
            'paid' => $paid,
            'paid_label' => $paid ? 'Paid' : 'Unpaid',
            'status' => $status !== '' ? $status : '—',
            'sku' => '',
            'inv' => null,
            'source_id' => (int) ($order->id ?? 0),
        ];

        if ($slug === 'amazon' && $order instanceof AmazonOrder) {
            $items = $order->relationLoaded('items') ? $order->items : collect();
            $lines = [];
            foreach ($items as $item) {
                $sku = $this->inventoryLookupSku((string) ($item->sku ?? ''));
                if ($sku === '') {
                    continue;
                }
                $row = $base;
                $row['id'] = $slug.'-'.$order->id.'-'.(int) ($item->id ?? count($lines));
                $row['sku'] = $sku;
                $lines[] = $row;
            }
            if ($lines !== []) {
                return $lines;
            }
        }

        $sku = trim((string) ($n['sku'] ?? ''));
        if ($sku === '') {
            $sku = trim((string) ($n['catalog_sku'] ?? ''));
        }
        $base['id'] = $slug.'-'.$order->id;
        $base['sku'] = $this->inventoryLookupSku($sku);

        return [$base];
    }

    /**
     * Scalar marketplace columns only. Does not read JSON payloads or Shopify.
     *
     * @return array{status: string, order_date: mixed, order_id: string, order_number: string, sku: string}
     */
    protected function lightOrderFields(string $slug, object $order): array
    {
        return match ($slug) {
            'amazon' => [
                'status' => (string) ($order->status ?? ''),
                'order_date' => $order->order_date ?? null,
                'order_id' => (string) ($order->amazon_order_id ?? ''),
                'order_number' => (string) ($order->amazon_order_id ?? ''),
                'sku' => '',
            ],
            'temu', 'temu2' => [
                'status' => (string) ($order->parent_order_status_text ?: $order->order_status_text ?: ''),
                'order_date' => $order->parent_order_time ?? null,
                'order_id' => (string) ($order->parent_order_sn ?: $order->order_sn ?: ''),
                'order_number' => (string) ($order->parent_order_sn ?: $order->order_sn ?: ''),
                'sku' => (string) ($order->display_sku ?: $order->ext_code ?: $order->product_sku_id ?: ''),
            ],
            'tiktok', 'tiktok2' => [
                'status' => (string) ($order->order_status ?: $order->line_status ?: ''),
                'order_date' => $order->order_created_at ?? null,
                'order_id' => (string) ($order->order_id ?? ''),
                'order_number' => (string) ($order->order_id ?? ''),
                'sku' => (string) ($order->seller_sku ?: $order->sku_id ?: ''),
            ],
            'bestbuy', 'macy' => [
                'status' => (string) ($order->status ?? ''),
                'order_date' => $order->order_created_at ?? null,
                'order_id' => (string) ($order->channel_order_id ?: $order->order_id ?: ''),
                'order_number' => (string) ($order->channel_order_id ?: $order->order_id ?: ''),
                'sku' => (string) ($order->sku ?? ''),
            ],
            'purchasingpower' => [
                'status' => (string) ($order->status ?? ''),
                'order_date' => $order->date_created ?? null,
                'order_id' => (string) ($order->order_number ?: $order->order_id ?: ''),
                'order_number' => (string) ($order->order_number ?: $order->order_id ?: ''),
                'sku' => (string) ($order->offer_sku ?: $order->product_sku ?: ''),
            ],
            'wayfair' => [
                'status' => (string) ($order->status ?? ''),
                'order_date' => $order->po_date ?? null,
                'order_id' => (string) ($order->po_number ?? ''),
                'order_number' => (string) ($order->po_number ?? ''),
                'sku' => (string) ($order->sku ?? ''),
            ],
            'doba' => [
                'status' => (string) ($order->order_status ?? ''),
                'order_date' => $order->order_time ?? null,
                'order_id' => (string) ($order->platform_order_no ?: $order->order_no ?: ''),
                'order_number' => (string) ($order->platform_order_no ?: $order->order_no ?: ''),
                'sku' => (string) ($order->sku ?? ''),
            ],
            default => [
                'status' => (string) ($order->status ?? ''),
                'order_date' => $order->order_date ?? null,
                'order_id' => (string) ($order->order_number ?: $order->order_id ?: ''),
                'order_number' => (string) ($order->order_number ?: $order->order_id ?: ''),
                'sku' => (string) ($order->sku ?? ''),
            ],
        };
    }

    /**
     * Drop the " +2" extra-item suffix used when several SKUs were collapsed onto one order.
     */
    protected function inventoryLookupSku(string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', trim($sku));
        $sku = preg_replace('/\s+/', ' ', $sku) ?? $sku;
        $sku = preg_replace('/\s+\+\d+$/', '', $sku) ?? $sku;

        return trim($sku);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachCpMasterInventory(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = $this->inventoryLookupSku((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $skus[$sku] = true;
            }
        }

        $invByCompact = $this->cpMasterInventoryByCompactSku(array_keys($skus));

        foreach ($rows as &$row) {
            $sku = $this->inventoryLookupSku((string) ($row['sku'] ?? ''));
            $key = $sku === '' ? '' : ProductMaster::skuCompact($sku);
            $row['inv'] = ($key !== '' && array_key_exists($key, $invByCompact))
                ? $invByCompact[$key]
                : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * Stock for SKUs that exist on CP Master (product_master).
     * Uses a quantity stored on the product when present, otherwise warehouse
     * available quantity for that same SKU. Shopify tables are not read.
     *
     * @param  list<string>  $skus
     * @return array<string, int> compact SKU => quantity
     */
    protected function cpMasterInventoryByCompactSku(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('product_master')) {
            return [];
        }

        $needles = [];
        $compacts = [];
        foreach ($skus as $sku) {
            $sku = $this->inventoryLookupSku($sku);
            if ($sku === '') {
                continue;
            }
            $needles[$sku] = true;
            $needles[str_replace(' ', "\u{00a0}", $sku)] = true;
            $compact = ProductMaster::skuCompact($sku);
            if ($compact !== '') {
                $compacts[$compact] = true;
            }
        }
        if ($needles === [] && $compacts === []) {
            return [];
        }

        $query = ProductMaster::query();
        $query->whereIn('sku', array_keys($needles));

        $matched = [];
        $query->get(['sku', 'Values'])->each(function ($product) use (&$matched) {
            $key = ProductMaster::skuCompact((string) ($product->sku ?? ''));
            if ($key === '' || array_key_exists($key, $matched)) {
                return;
            }
            $matched[$key] = $this->invFromProductValues($product->Values);
        });

        if ($matched === []) {
            return [];
        }

        $warehouse = $this->warehouseQtyByCompactSku(array_keys($needles));
        $out = [];
        foreach ($matched as $key => $fromProduct) {
            if ($fromProduct !== null) {
                $out[$key] = $fromProduct;
            } elseif (array_key_exists($key, $warehouse)) {
                $out[$key] = $warehouse[$key];
            }
        }

        return $out;
    }

    protected function invFromProductValues(mixed $values): ?int
    {
        if (is_string($values)) {
            $values = json_decode($values, true) ?: [];
        }
        if (! is_array($values)) {
            return null;
        }

        foreach (['inv', 'INV', 'inventory', 'Inventory', 'on_hand', 'available_qty'] as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $raw = $values[$key];
            if ($raw === null || $raw === '') {
                continue;
            }
            if (is_numeric($raw)) {
                return (int) round((float) $raw);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $compacts
     * @return array<string, int>
     */
    protected function warehouseQtyByCompactSku(array $compacts): array
    {
        if ($compacts === [] || ! Schema::hasTable('inventories')) {
            return [];
        }

        $qtyColumn = null;
        if (Schema::hasColumn('inventories', 'available_qty')) {
            $qtyColumn = 'available_qty';
        } elseif (Schema::hasColumn('inventories', 'on_hand')) {
            $qtyColumn = 'on_hand';
        }
        if ($qtyColumn === null || ! Schema::hasColumn('inventories', 'sku')) {
            return [];
        }

        $fallbackColumn = $qtyColumn === 'available_qty' && Schema::hasColumn('inventories', 'on_hand')
            ? 'on_hand'
            : null;

        $query = Inventory::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereIn('sku', array_values($compacts));
        if (Schema::hasColumn('inventories', 'is_archived')) {
            $query->where(function (Builder $q) {
                $q->where('is_archived', 0)->orWhereNull('is_archived');
            });
        }

        $select = ['sku', $qtyColumn];
        if ($fallbackColumn !== null) {
            $select[] = $fallbackColumn;
        }

        $totals = [];
        foreach ($query->get($select) as $row) {
            $key = ProductMaster::skuCompact((string) ($row->sku ?? ''));
            if ($key === '') {
                continue;
            }
            $qty = $row->{$qtyColumn};
            if (($qty === null || $qty === '') && $fallbackColumn !== null) {
                $qty = $row->{$fallbackColumn};
            }
            if (! is_numeric($qty)) {
                continue;
            }
            $totals[$key] = ($totals[$key] ?? 0) + (int) round((float) $qty);
        }

        return $totals;
    }

    /**
     * Saved 4Seller / GOFO / Veeqo numbers, plus any tracking typed in on this page.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachSavedTracking(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['tracking'] = '';
            $row['tracking_source'] = '';
            $row['tracking_checked'] = false;
        }
        unset($row);

        if ($rows === [] || ! Schema::hasTable('order_fulfillment_trackings')) {
            return $rows;
        }

        $keys = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['id'] ?? ''));
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
        if ($keys === []) {
            return $rows;
        }

        $saved = [];
        foreach (array_chunk(array_keys($keys), 500) as $chunk) {
            foreach (OrderFulfillmentTracking::query()->whereIn('row_key', $chunk)->get() as $record) {
                $saved[(string) $record->row_key] = $record;
            }
        }

        foreach ($rows as &$row) {
            $record = $saved[(string) ($row['id'] ?? '')] ?? null;
            if (! $record) {
                continue;
            }
            $number = trim((string) ($record->tracking_number ?? ''));
            $row['tracking'] = $number;
            $row['tracking_source'] = (string) ($record->source ?? '');
            // A saved number is final. An empty miss is looked up again on the next load.
            $row['tracking_checked'] = $number !== '';
        }
        unset($row);

        return $rows;
    }

    public function lookupTracking(Request $request): JsonResponse
    {
        @set_time_limit(50);
        $this->ensureTrackingTable();

        $validated = $request->validate([
            'rows' => 'required|array|max:1',
            'rows.*.id' => 'required|string|max:191',
            'rows.*.mm_slug' => 'required|string|max:64',
            'rows.*.order_id' => 'nullable|string|max:128',
            'rows.*.sku' => 'nullable|string|max:191',
            'rows.*.source_id' => 'nullable|integer',
        ]);

        $groups = [];
        foreach ($validated['rows'] as $row) {
            $slug = strtolower(trim((string) $row['mm_slug']));
            $rowKey = trim((string) $row['id']);
            if ($slug === '' || $rowKey === '' || ! str_starts_with($rowKey, $slug.'-')) {
                continue;
            }
            $orderId = trim((string) ($row['order_id'] ?? ''));
            $groupKey = $slug.'|'.$orderId;
            $groups[$groupKey] ??= [
                'mm_slug' => $slug,
                'order_id' => $orderId,
                'sku' => trim((string) ($row['sku'] ?? '')),
                'source_id' => (int) ($row['source_id'] ?? 0),
                'rows' => [],
            ];
            $groups[$groupKey]['rows'][] = [
                'id' => $rowKey,
                'sku' => trim((string) ($row['sku'] ?? '')),
            ];
        }

        $updates = [];
        $deadline = microtime(true) + 42.0;
        $lookup = $this->labelTrackingLookup();

        foreach ($groups as $group) {
            if (microtime(true) >= $deadline) {
                break;
            }

            $copied = $this->copyKnownLabelTracking($group);
            if ($copied !== null) {
                array_push($updates, ...$copied);

                continue;
            }

            $hit = null;
            $orderId = (string) $group['order_id'];
            if ($orderId !== '' && $lookup !== null) {
                $refs = $this->trackingSearchRefs($lookup, $group);
                try {
                    $veeqo = $lookup->findVeeqoShipment($refs, false, '', []);
                    if (is_array($veeqo) && trim((string) ($veeqo['tracking'] ?? '')) !== '') {
                        $hit = [
                            'tracking' => (string) $veeqo['tracking'],
                            'carrier' => (string) ($veeqo['carrier'] ?? 'Veeqo'),
                            'source' => 'veeqo',
                        ];
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
                if (($hit === null || trim((string) ($hit['tracking'] ?? '')) === '') && microtime(true) < $deadline) {
                    try {
                        $hit = $lookup->lookupLabelTracking($refs, null, true, '');
                    } catch (\Throwable $e) {
                        report($e);
                        $hit = null;
                    }
                }
            }

            $source = strtolower(trim((string) ($hit['source'] ?? '')));
            $number = trim((string) ($hit['tracking'] ?? ''));
            $allowed = in_array($source, ['gofo', '4seller', 'veeqo'], true) && $number !== '';
            if (! $allowed) {
                $source = null;
                $number = '';
            }

            foreach ($group['rows'] as $line) {
                $this->rememberTracking(
                    (string) $line['id'],
                    (string) $group['mm_slug'],
                    $orderId !== '' ? $orderId : null,
                    (string) ($line['sku'] ?? ''),
                    $number !== '' ? $number : null,
                    $allowed ? trim((string) ($hit['carrier'] ?? '')) : null,
                    $source,
                    false
                );
                $updates[] = [
                    'id' => (string) $line['id'],
                    'tracking' => $number,
                    'tracking_source' => (string) ($source ?? ''),
                    'tracking_checked' => true,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'updates' => $this->withCarrierOnUpdates($updates),
        ]);
    }

    public function saveTracking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|string|max:191',
            'mm_slug' => 'required|string|max:64',
            'order_id' => 'nullable|string|max:128',
            'sku' => 'nullable|string|max:191',
            'tracking_number' => 'required|string|max:128',
        ]);

        $this->ensureTrackingTable();

        $slug = strtolower(trim((string) $validated['mm_slug']));
        $rowKey = trim((string) $validated['id']);
        $number = trim((string) $validated['tracking_number']);
        if ($slug === '' || $rowKey === '' || ! str_starts_with($rowKey, $slug.'-') || $number === '') {
            return response()->json([
                'success' => false,
                'message' => 'Enter a tracking number for this order.',
            ], 422);
        }

        $this->rememberTracking(
            $rowKey,
            $slug,
            trim((string) ($validated['order_id'] ?? '')) ?: null,
            trim((string) ($validated['sku'] ?? '')),
            $number,
            null,
            'manual',
            true
        );

        $carrier = TrackingCarrierGuesser::labelFromNumber($number) ?? '';

        return response()->json([
            'success' => true,
            'id' => $rowKey,
            'tracking' => $number,
            'tracking_source' => 'manual',
            'tracking_checked' => true,
            'carrier' => $carrier,
            'tracking_status' => '',
            'tracking_status_checked' => false,
        ]);
    }

    public function refreshTrackingStatus(Request $request, ShipmentTrackingService $tracking): JsonResponse
    {
        $validated = $request->validate([
            'numbers' => 'required|array|max:10',
            'numbers.*' => 'required|string|max:128',
        ]);

        $wanted = [];
        foreach ($validated['numbers'] as $number) {
            $raw = trim((string) $number);
            $key = strtoupper((string) preg_replace('/\s+/', '', $raw));
            if ($key === '' || strlen($key) < 8) {
                continue;
            }
            $wanted[$key] = $raw;
        }

        $updates = [];
        $toFetch = [];
        $cached = $this->cachedCarrierStatuses(array_keys($wanted));
        $freshAfter = now()->subHours(6);

        foreach ($wanted as $key => $raw) {
            $carrier = TrackingCarrierGuesser::labelFromNumber($raw) ?? '';
            $slug = TrackingCarrierGuesser::slugFromNumber($raw);
            $row = $cached[$key] ?? null;
            $status = trim((string) ($row->shipment_status ?? ''));
            $checkedAt = $row && $row->shipment_checked_at ? \Carbon\Carbon::parse($row->shipment_checked_at) : null;
            $fresh = $status !== '' && $checkedAt !== null && $checkedAt->greaterThan($freshAfter);
            if ($fresh || ! in_array($slug, ['usps', 'ups', 'fedex', 'gofo'], true)) {
                $updates[] = $this->trackingStatusPayload($raw, $carrier, $status);
                continue;
            }
            $toFetch[$key] = ['number' => $raw, 'carrier' => $carrier !== '' ? $carrier : null];
        }

        if ($toFetch !== [] && $tracking->isConfigured() && Schema::hasTable('carrier_tracking_statuses')) {
            try {
                $results = $tracking->track(array_values($toFetch), ['prefer_native' => true]);
            } catch (\Throwable $e) {
                report($e);
                $results = [];
            }

            foreach ($toFetch as $key => $shipment) {
                $res = $this->matchTrackingResult($results, $key, (string) $shipment['number']);
                $carrier = (string) ($shipment['carrier'] ?? '');
                $status = '';
                if (ShipmentTrackingService::isPersistableResult(is_array($res) ? $res : null)) {
                    $status = (string) $res['status'];
                    $this->storeCarrierStatus((string) $shipment['number'], $carrier, $status, trim((string) ($res['detail'] ?? '')));
                }
                $updates[] = $this->trackingStatusPayload((string) $shipment['number'], $carrier, $status);
            }
        } else {
            foreach ($toFetch as $shipment) {
                $updates[] = $this->trackingStatusPayload(
                    (string) $shipment['number'],
                    (string) ($shipment['carrier'] ?? ''),
                    ''
                );
            }
        }

        return response()->json([
            'success' => true,
            'updates' => $updates,
        ]);
    }

    /**
     * @param  array{mm_slug: string, order_id: string, sku: string, rows: list<array{id: string, sku: string}>}  $group
     * @return list<array<string, mixed>>|null
     */
    protected function copyKnownLabelTracking(array $group): ?array
    {
        $orderId = trim((string) $group['order_id']);
        if ($orderId === '') {
            return null;
        }

        $known = OrderFulfillmentTracking::query()
            ->where('mm_slug', $group['mm_slug'])
            ->where('order_id', $orderId)
            ->whereIn('source', ['gofo', '4seller', 'veeqo'])
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->first();

        if ($known === null) {
            return null;
        } else {
            $knownNumber = trim((string) $known->tracking_number);
            $knownSource = (string) $known->source;
            $knownCarrier = $known->carrier;
        }

        $updates = [];
        foreach ($group['rows'] as $line) {
            $existing = OrderFulfillmentTracking::query()->where('row_key', $line['id'])->first();
            if ($existing && $existing->source === 'manual' && trim((string) $existing->tracking_number) !== '') {
                $updates[] = [
                    'id' => (string) $line['id'],
                    'tracking' => trim((string) $existing->tracking_number),
                    'tracking_source' => 'manual',
                    'tracking_checked' => true,
                ];

                continue;
            }
            $this->rememberTracking(
                (string) $line['id'],
                (string) $group['mm_slug'],
                $orderId,
                (string) ($line['sku'] ?? ''),
                $knownNumber !== '' ? $knownNumber : null,
                $knownCarrier ? (string) $knownCarrier : null,
                $knownSource !== '' ? $knownSource : null,
                false
            );
            $updates[] = [
                'id' => (string) $line['id'],
                'tracking' => $knownNumber,
                'tracking_source' => $knownSource,
                'tracking_checked' => true,
            ];
        }

        return $updates;
    }

    /**
     * Marketplace order ids 4Seller, GOFO, and Veeqo store on the label.
     * Shopify fulfillment tracking is not used.
     *
     * @param  array{mm_slug: string, order_id: string, sku: string, source_id?: int, rows: list<array{id: string, sku: string}>}  $group
     * @return list<string>
     */
    protected function trackingSearchRefs(VeeqoShopifyFulfillmentService $lookup, array $group): array
    {
        $slug = (string) $group['mm_slug'];
        $orderId = trim((string) $group['order_id']);
        $refs = [];
        $push = static function (string $ref) use (&$refs): void {
            $ref = trim($ref);
            if ($ref === '' || strlen($ref) < 6 || in_array($ref, $refs, true)) {
                return;
            }
            $refs[] = $ref;
        };

        $sourceId = (int) ($group['source_id'] ?? 0);
        if ($sourceId > 0) {
            try {
                $ctx = $lookup->contextForMarketplaceOrder($slug, $sourceId);
            } catch (\Throwable $e) {
                report($e);
                $ctx = null;
            }
            foreach ((array) ($ctx['refs'] ?? []) as $ref) {
                $push((string) $ref);
            }
        }

        if ($slug === 'amazon' || preg_match('/^\d{3}-\d{7}-\d{7}$/', ltrim($orderId, '#')) === 1) {
            foreach (AmazonOrder::warehouseOrderRefs($orderId) as $ref) {
                $push($ref);
            }
        } else {
            $push($orderId);
        }

        return array_slice($refs, 0, 3);
    }

    protected function ensureTrackingTable(): void
    {
        if (Schema::hasTable('order_fulfillment_trackings')) {
            return;
        }

        Schema::create('order_fulfillment_trackings', function ($table) {
            $table->id();
            $table->string('row_key', 191);
            $table->string('mm_slug', 64);
            $table->string('order_id', 128)->nullable();
            $table->string('sku', 191)->nullable();
            $table->string('tracking_number', 128)->nullable();
            $table->string('carrier', 64)->nullable();
            $table->string('source', 32)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique('row_key', 'of_tracking_row_key_uq');
            $table->index(['mm_slug', 'order_id'], 'of_tracking_slug_order_idx');
        });
    }

    protected function labelTrackingLookup(): ?VeeqoShopifyFulfillmentService
    {
        try {
            $lookup = app(VeeqoShopifyFulfillmentService::class);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        try {
            $ref = new \ReflectionClass($lookup);
            foreach ([
                'gofo' => [GofoExpressService::class, 6],
                'fourSeller' => [FourSellerApiService::class, 6],
                'veeqo' => [VeeqoApiService::class, 12],
            ] as $property => [$class, $seconds]) {
                if (! $ref->hasProperty($property)) {
                    continue;
                }
                $prop = $ref->getProperty($property);
                $prop->setAccessible(true);
                $client = $prop->getValue($lookup);
                if ($client instanceof $class && method_exists($client, 'setTimeout')) {
                    $client->setTimeout($seconds);
                }
            }
        } catch (\Throwable) {
            // Keep the default client timeouts if reflection is unavailable.
        }

        return $lookup;
    }

    protected function rememberTracking(
        string $rowKey,
        string $slug,
        ?string $orderId,
        ?string $sku,
        ?string $tracking,
        ?string $carrier,
        ?string $source,
        bool $manual
    ): void {
        $existing = OrderFulfillmentTracking::query()->where('row_key', $rowKey)->first();
        if ($existing && $existing->source === 'manual' && ! $manual) {
            return;
        }

        OrderFulfillmentTracking::query()->updateOrCreate(
            ['row_key' => $rowKey],
            [
                'mm_slug' => $slug,
                'order_id' => $orderId,
                'sku' => $sku !== '' ? $sku : null,
                'tracking_number' => $tracking !== null && trim($tracking) !== '' ? trim($tracking) : null,
                'carrier' => $this->carrierNameForTracking($tracking, $carrier),
                'source' => $source,
                'checked_at' => now(),
            ]
        );
    }

    /**
     * Carrier name is taken from the tracking number. Status comes from the carrier cache.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachCarrierAndTrackingStatus(array $rows): array
    {
        $keys = [];
        foreach ($rows as &$row) {
            $tracking = trim((string) ($row['tracking'] ?? ''));
            $row['carrier'] = TrackingCarrierGuesser::labelFromNumber($tracking) ?? '';
            $row['tracking_status'] = '';
            $row['tracking_status_checked'] = $tracking === '';
            $key = strtoupper((string) preg_replace('/\s+/', '', $tracking));
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
        unset($row);

        if ($keys === [] || ! Schema::hasTable('carrier_tracking_statuses')) {
            return $rows;
        }

        $cached = $this->cachedCarrierStatuses(array_keys($keys));
        $freshAfter = now()->subHours(6);
        foreach ($rows as &$row) {
            $tracking = trim((string) ($row['tracking'] ?? ''));
            $key = strtoupper((string) preg_replace('/\s+/', '', $tracking));
            $hit = $key !== '' ? ($cached[$key] ?? null) : null;
            if ($hit === null) {
                continue;
            }
            $status = trim((string) ($hit->shipment_status ?? ''));
            $row['tracking_status'] = $this->carrierShipmentStatusLabel($status) ?? ($status !== '' ? $status : '');
            $checkedAt = $hit->shipment_checked_at ? \Carbon\Carbon::parse($hit->shipment_checked_at) : null;
            $slug = TrackingCarrierGuesser::slugFromNumber($tracking);
            $supported = in_array($slug, ['usps', 'ups', 'fedex', 'gofo'], true);
            $row['tracking_status_checked'] = ! $supported
                || ($status !== '' && $checkedAt !== null && $checkedAt->greaterThan($freshAfter));
            if ($row['carrier'] === '' && trim((string) ($hit->carrier ?? '')) !== '') {
                $row['carrier'] = TrackingCarrierGuesser::fill((string) $hit->carrier, $tracking) ?? (string) $hit->carrier;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  list<string>  $keys  uppercase tracking numbers without spaces
     * @return array<string, object>
     */
    protected function cachedCarrierStatuses(array $keys): array
    {
        if ($keys === [] || ! Schema::hasTable('carrier_tracking_statuses')) {
            return [];
        }

        $out = [];
        foreach (array_chunk($keys, 500) as $chunk) {
            $rows = DB::table('carrier_tracking_statuses')
                ->whereIn('tracking_number', $chunk)
                ->get(['tracking_number', 'carrier', 'shipment_status', 'shipment_checked_at']);
            foreach ($rows as $row) {
                $key = strtoupper((string) preg_replace('/\s+/', '', (string) ($row->tracking_number ?? '')));
                if ($key !== '') {
                    $out[$key] = $row;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $updates
     * @return list<array<string, mixed>>
     */
    protected function withCarrierOnUpdates(array $updates): array
    {
        foreach ($updates as &$update) {
            $tracking = trim((string) ($update['tracking'] ?? ''));
            $update['carrier'] = TrackingCarrierGuesser::labelFromNumber($tracking) ?? '';
            $update['tracking_status'] = (string) ($update['tracking_status'] ?? '');
            $update['tracking_status_checked'] = $tracking === '';
        }
        unset($update);

        return $updates;
    }

    protected function carrierNameForTracking(?string $tracking, ?string $carrier): ?string
    {
        $fromNumber = TrackingCarrierGuesser::labelFromNumber((string) $tracking);
        if ($fromNumber !== null && $fromNumber !== '') {
            return $fromNumber;
        }
        $known = TrackingCarrierGuesser::knownLabel($carrier);

        return $known !== null && $known !== '' ? $known : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $results
     * @return array<string, mixed>|null
     */
    protected function matchTrackingResult(array $results, string $key, string $raw): ?array
    {
        if (isset($results[$raw]) && is_array($results[$raw])) {
            return $results[$raw];
        }
        if (isset($results[$key]) && is_array($results[$key])) {
            return $results[$key];
        }
        foreach ($results as $resultKey => $value) {
            $norm = strtoupper((string) preg_replace('/\s+/', '', (string) $resultKey));
            if ($norm === $key && is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    protected function storeCarrierStatus(string $number, string $carrier, string $status, string $detail): void
    {
        if (! Schema::hasTable('carrier_tracking_statuses') || $status === '') {
            return;
        }

        $now = now();
        $stored = mb_substr(trim($number), 0, 128);
        $existing = DB::table('carrier_tracking_statuses')->where('tracking_number', $stored)->first();
        DB::table('carrier_tracking_statuses')->updateOrInsert(
            ['tracking_number' => $stored],
            [
                'carrier' => $carrier !== '' ? mb_substr($carrier, 0, 128) : ($existing->carrier ?? null),
                'shipment_status' => $status,
                'shipment_status_detail' => $detail !== '' ? mb_substr($detail, 0, 512) : null,
                'shipment_checked_at' => $now,
                'updated_at' => $now,
                'created_at' => $existing->created_at ?? $now,
            ]
        );
    }

    /**
     * @return array{tracking: string, carrier: string, tracking_status: string, tracking_status_checked: bool}
     */
    protected function trackingStatusPayload(string $number, string $carrier, string $status): array
    {
        $label = $status !== '' ? ($this->carrierShipmentStatusLabel($status) ?? $status) : '';

        return [
            'tracking' => $number,
            'carrier' => $carrier,
            'tracking_status' => $label,
            'tracking_status_checked' => true,
        ];
    }
}
