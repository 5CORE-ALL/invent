<?php

namespace App\Http\Controllers\Channels;

use App\Models\AmazonOrder;
use App\Models\ChannelMaster;
use App\Models\Inventory;
use App\Models\OrderFulfillmentManualOrder;
use App\Models\OrderFulfillmentTracking;
use App\Models\ProductMaster;
use App\Services\FourSellerApiService;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\MarketplaceOrderPaidFilter;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use App\Services\OrderFulfillment\ChannelBatchTrackingLookup;
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
            'ofCreateOrders' => false,
            'ofManualMarketplaces' => $this->manualMarketplaceNames(),
            'ofTimezone' => $this->sofTimezone(),
        ]);
    }

    /**
     * Manual orders for marketplaces without an API. Same grid; rows come from
     * order_fulfillment_manual_orders only.
     */
    public function createOrders(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        return $this->index($gofo, $veeqo)->with([
            'ofPageTitle' => 'Create Orders',
            'ofCreateOrders' => true,
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

    public function unpaid(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        return $this->index($gofo, $veeqo)->with([
            'ofPageTitle' => 'Unpaid',
            'ofDeliveredOnly' => false,
            'ofTransitOnly' => false,
            'ofScanPendingOnly' => false,
            'ofUnpaidOnly' => true,
        ]);
    }

    public function pending(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        return $this->index($gofo, $veeqo)->with([
            'ofPageTitle' => 'Pending',
            'ofDeliveredOnly' => false,
            'ofTransitOnly' => false,
            'ofScanPendingOnly' => false,
            'ofUnpaidOnly' => false,
            'ofPendingOnly' => true,
        ]);
    }

    public function data(): JsonResponse
    {
        try {
            @set_time_limit(120);

            $manualOnly = request()->boolean('manual');
            $rows = $manualOnly ? $this->manualFulfillmentRows() : $this->collectFulfillmentRows();
            $rows = $this->attachCpMasterInventory($rows);
            $rows = $this->attachSavedTracking($rows);
            $rows = $this->attachCarrierAndTrackingStatus($rows);
            [$navCounts, $navStatusCounts] = $manualOnly
                ? [$this->emptyFulfillmentNavCounts(), []]
                : $this->fulfillmentNavCounts($rows);
            if ($manualOnly) {
                $navCounts['create_orders'] = count($rows);
            }
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
            } elseif (request()->boolean('unpaid')) {
                $rows = array_values(array_filter(
                    $rows,
                    fn (array $row) => empty($row['paid'])
                ));
            } elseif (request()->boolean('pending')) {
                $rows = array_values(array_filter(
                    $rows,
                    fn (array $row) => $this->fulfillmentRowIsPending($row)
                ));
            }

            $channels = [];
            $paid = 0;
            $unpaid = 0;
            foreach ($rows as $row) {
                $slug = (string) ($row['mm_slug'] ?? '');
                if ($slug === 'manual') {
                    $slug = 'manual:'.strtolower(trim((string) ($row['channel'] ?? '')));
                }
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
                'nav_counts' => $navCounts,
                'nav_status_counts' => $navStatusCounts,
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
                'nav_counts' => $this->emptyFulfillmentNavCounts(),
                'nav_status_counts' => [],
            ], 500);
        }
    }

    /**
     * Row totals for the Order Fulfillment sidebar, before the current page filter.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: array{orders: int, pending: int, unpaid: int, scan_pending: int, transit: int, delivered: int, create_orders: int}, 1: array<string, array<string, int>>}
     */
    protected function fulfillmentNavCounts(array $rows): array
    {
        $counts = $this->emptyFulfillmentNavCounts();
        $counts['orders'] = count($rows);
        $byStatus = [
            'orders' => [],
            'pending' => [],
            'unpaid' => [],
            'scan_pending' => [],
            'transit' => [],
            'delivered' => [],
        ];
        foreach ($rows as $row) {
            $status = trim((string) ($row['status'] ?? ''));
            if ($status === '') {
                $status = '—';
            }
            $buckets = ['orders'];
            if (($row['mm_slug'] ?? '') === self::MANUAL_SLUG) {
                $counts['create_orders']++;
            }
            if (empty($row['paid'])) {
                $counts['unpaid']++;
                $buckets[] = 'unpaid';
            }
            if ($this->fulfillmentRowIsDelivered($row)) {
                $counts['delivered']++;
                $buckets[] = 'delivered';
            }
            if ($this->fulfillmentRowIsInTransit($row)) {
                $counts['transit']++;
                $buckets[] = 'transit';
            }
            if ($this->fulfillmentRowIsScanPending($row)) {
                $counts['scan_pending']++;
                $buckets[] = 'scan_pending';
            }
            if ($this->fulfillmentRowIsPending($row)) {
                $counts['pending']++;
                $buckets[] = 'pending';
            }
            foreach ($buckets as $bucket) {
                $byStatus[$bucket][$status] = ($byStatus[$bucket][$status] ?? 0) + 1;
            }
        }

        return [$counts, $byStatus];
    }

    /**
     * @return array{orders: int, pending: int, unpaid: int, scan_pending: int, transit: int, delivered: int, create_orders: int}
     */
    protected function emptyFulfillmentNavCounts(): array
    {
        return [
            'orders' => 0,
            'pending' => 0,
            'unpaid' => 0,
            'scan_pending' => 0,
            'transit' => 0,
            'delivered' => 0,
            'create_orders' => 0,
        ];
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
     * Still waiting to ship. Uses each marketplace's pending status and leaves
     * delivered, in-transit, and scan-pending rows on those pages.
     *
     * @param  array<string, mixed>  $row
     */
    protected function fulfillmentRowIsPending(array $row): bool
    {
        if ($this->fulfillmentRowIsDelivered($row)
            || $this->fulfillmentRowIsInTransit($row)
            || $this->fulfillmentRowIsScanPending($row)
        ) {
            return false;
        }

        $slug = strtolower(trim((string) ($row['mm_slug'] ?? '')));
        $raw = trim((string) ($row['status'] ?? ''));
        if ($raw === '' || $raw === '—') {
            return false;
        }

        $upper = strtoupper($raw);
        if (str_contains($upper, 'CANCEL')) {
            return false;
        }

        $lower = strtolower($raw);
        $compact = str_replace([' ', '-'], '_', $upper);

        return match (true) {
            in_array($slug, ['ebay1', 'ebay2', 'ebay3'], true) => $upper === 'NOT_STARTED',
            $slug === 'amazon' => $upper === 'UNSHIPPED',
            $slug === 'newegg' => $raw === '0',
            $slug === 'reverb' => $lower === 'paid',
            $slug === 'shein' => in_array($lower, ['pending', 'to be shipped'], true),
            in_array($slug, ['temu', 'temu2'], true) => in_array($upper, ['UN_SHIPPING', 'PENDING'], true),
            in_array($slug, ['aliexpress', 'alibaba'], true) => $compact === 'WAIT_SELLER_SEND_GOODS',
            $slug === 'faire' => in_array($upper, ['PROCESSING', 'NEW'], true),
            in_array($slug, ['purchasingpower', 'bestbuy', 'macy'], true) => in_array($compact, ['SHIPPING', 'TO_COLLECT', 'AWAITING_SHIPMENT'], true)
                || str_contains($lower, 'awaiting shipment'),
            $slug === 'wayfair' => $lower === 'open',
            $slug === 'doba' => $compact === 'UNSHIPPED',
            in_array($slug, ['tiktok', 'tiktok2'], true) => $upper === 'AWAITING_SHIPMENT',
            $slug === 'topdawg' => in_array($lower, ['pending', 'processing', 'saved'], true),
            $slug === 'manual' => $compact === 'ORDER_CREATED',
            default => in_array($compact, ['PENDING', 'UNSHIPPED', 'AWAITING_SHIPMENT', 'NOT_STARTED'], true),
        };
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

        try {
            array_push($rows, ...$this->manualFulfillmentRows());
        } catch (\Throwable $e) {
            report($e);
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

    /* ------------------------------------------------------------------
     | Manual orders (marketplaces without an API)
     |------------------------------------------------------------------*/

    public const MANUAL_SLUG = 'manual';

    /**
     * Grid rows for manual orders inside the current date range.
     *
     * @return list<array<string, mixed>>
     */
    protected function manualFulfillmentRows(): array
    {
        if (! Schema::hasTable('order_fulfillment_manual_orders')) {
            return [];
        }
        [$from, $to] = $this->resolveOrderDateRange();
        $tz = $this->sofTimezone();

        // Hand-typed dates can run a few hours ahead of the Pacific "today" the
        // range ends on, so allow one extra day at the top end.
        $lines = OrderFulfillmentManualOrder::query()
            ->whereBetween('order_date', [
                $from->copy()->format('Y-m-d H:i:s'),
                $to->copy()->addDay()->endOfDay()->format('Y-m-d H:i:s'),
            ])
            ->orderBy('order_date')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($lines->groupBy(fn ($line) => $this->manualOrderKey($line)) as $orderLines) {
            $rows[] = $this->manualOrderRow($orderLines, $tz);
        }

        return $this->attachManualLineInventory($rows);
    }

    protected function manualOrderKey(OrderFulfillmentManualOrder $line): string
    {
        return strtolower(trim((string) $line->marketplace)).'|'.trim((string) $line->order_id);
    }

    /**
     * All SKU lines stored for the same marketplace + order id.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, OrderFulfillmentManualOrder>
     */
    protected function manualOrderLines(OrderFulfillmentManualOrder $order)
    {
        return OrderFulfillmentManualOrder::query()
            ->whereRaw('LOWER(marketplace) = ?', [strtolower(trim((string) $order->marketplace))])
            ->where('order_id', trim((string) $order->order_id))
            ->orderBy('id')
            ->get();
    }

    /**
     * One grid row per order: the header comes from the first line, every SKU
     * line is listed under `lines`. The row id is the first line's id, which is
     * also the key the tracking table and the fulfil endpoint use.
     *
     * @param  iterable<OrderFulfillmentManualOrder>  $lines
     * @return array<string, mixed>
     */
    protected function manualOrderRow(iterable $lines, ?string $tz = null): array
    {
        $tz ??= $this->sofTimezone();
        $lines = collect($lines)->sortBy('id')->values();
        /** @var OrderFulfillmentManualOrder $order */
        $order = $lines->first();
        $marketplace = trim((string) $order->marketplace);
        $orderDate = $order->order_date ? $order->order_date->format('Y-m-d H:i:s') : null;

        $lineRows = [];
        $units = 0;
        $linesTotal = 0.0;
        $priced = false;
        $allFulfilled = true;
        $fulfilledAt = null;
        foreach ($lines as $line) {
            $qty = (int) ($line->qty ?: 1);
            $price = $line->unit_price !== null ? (float) $line->unit_price : null;
            $units += $qty;
            if ($price !== null) {
                $priced = true;
                $linesTotal += $price * $qty;
            }
            if (strcasecmp((string) $line->status, OrderFulfillmentManualOrder::STATUS_FULFILLED) !== 0) {
                $allFulfilled = false;
            }
            if ($line->fulfilled_at && ($fulfilledAt === null || $line->fulfilled_at->gt($fulfilledAt))) {
                $fulfilledAt = $line->fulfilled_at;
            }
            $lineRows[] = [
                'id' => (int) $line->id,
                'row_key' => self::MANUAL_SLUG.'-'.$line->id,
                'sku' => $this->inventoryLookupSku((string) $line->sku),
                'qty' => $qty,
                'unit_price' => $price,
                'line_total' => $price !== null ? round($price * $qty, 2) : null,
                'inv' => null,
            ];
        }

        $amount = $order->amount !== null ? (float) $order->amount : ($priced ? round($linesTotal, 2) : null);
        $status = $allFulfilled
            ? OrderFulfillmentManualOrder::STATUS_FULFILLED
            : (string) ($order->status ?: OrderFulfillmentManualOrder::STATUS_CREATED);

        return [
            'id' => self::MANUAL_SLUG.'-'.$order->id,
            'channel' => $marketplace !== '' ? $marketplace : 'Manual',
            'mm_slug' => self::MANUAL_SLUG,
            'manual' => true,
            'manual_id' => (int) $order->id,
            'line_ids' => array_map(fn ($l) => $l['id'], $lineRows),
            'order_id' => trim((string) $order->order_id),
            // Stored as the Pacific wall clock the user typed; shown unchanged.
            'order_date' => $this->formatOrderDate($orderDate, $tz),
            'paid' => (bool) $order->paid,
            'paid_label' => $order->paid ? 'Paid' : 'Unpaid',
            'status' => $status,
            // First SKU keeps the single-SKU columns/filters working; all SKUs are in `lines`.
            'sku' => $lineRows[0]['sku'] ?? '',
            'qty' => $units,
            'unit_price' => $lineRows[0]['unit_price'] ?? null,
            'lines' => $lineRows,
            'sku_count' => count($lineRows),
            'inv' => null,
            'source_id' => (int) $order->id,
            'reference' => trim((string) ($order->reference ?? '')),
            'amount' => $amount,
            'lines_total' => $priced ? round($linesTotal, 2) : null,
            'customer_name' => (string) ($order->customer_name ?? ''),
            'customer_email' => (string) ($order->customer_email ?? ''),
            'customer_phone' => (string) ($order->customer_phone ?? ''),
            'address1' => (string) ($order->address1 ?? ''),
            'address2' => (string) ($order->address2 ?? ''),
            'city' => (string) ($order->city ?? ''),
            'state' => (string) ($order->state ?? ''),
            'zip' => (string) ($order->zip ?? ''),
            'country' => (string) ($order->country ?? ''),
            'notes' => (string) ($order->notes ?? ''),
            'fulfilled_at' => $fulfilledAt ? $fulfilledAt->format('Y-m-d H:i:s') : null,
            'created_at' => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * CP Master stock for every SKU line of grouped manual rows (the row-level
     * `inv` is the first SKU's stock, like other channels).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachManualLineInventory(array $rows): array
    {
        $skus = [];
        foreach ($rows as $row) {
            foreach ((array) ($row['lines'] ?? []) as $line) {
                $sku = $this->inventoryLookupSku((string) ($line['sku'] ?? ''));
                if ($sku !== '') {
                    $skus[$sku] = true;
                }
            }
        }
        if ($skus === []) {
            return $rows;
        }
        $inv = $this->cpMasterInventoryByCompactSku(array_keys($skus));
        foreach ($rows as &$row) {
            if (empty($row['lines'])) {
                continue;
            }
            foreach ($row['lines'] as &$line) {
                $key = ProductMaster::skuCompact($this->inventoryLookupSku((string) ($line['sku'] ?? '')));
                $line['inv'] = $key !== '' && array_key_exists($key, $inv) ? $inv[$key] : null;
            }
            unset($line);
            $row['inv'] = $row['lines'][0]['inv'] ?? null;
        }
        unset($row);

        return $rows;
    }

    /**
     * Fully decorated grid row for one manual order (used by the write endpoints).
     *
     * @return list<array<string, mixed>>
     */
    protected function manualOrderRowsForResponse(OrderFulfillmentManualOrder $order, bool $withStatus = false): array
    {
        $lines = $this->manualOrderLines($order);
        if ($lines->isEmpty()) {
            return [];
        }
        $rows = [$this->manualOrderRow($lines)];
        $rows = $this->attachManualLineInventory($rows);
        $rows = $this->attachSavedTracking($rows);
        if ($withStatus) {
            $rows = $this->attachCarrierAndTrackingStatus($rows);
        }

        return $rows;
    }

    /**
     * Every marketplace we sell on, for the create form's dropdown: API channels
     * from the Marketplace Manager registry, active rows of channel_master, and
     * names already used on manual orders. De-duplicated case-insensitively.
     *
     * @return list<string>
     */
    protected function manualMarketplaceNames(): array
    {
        $names = [];
        $add = function ($value) use (&$names): void {
            $value = trim((string) $value);
            if ($value === '') {
                return;
            }
            $key = strtolower(preg_replace('/\s+/', ' ', $value));
            if (! isset($names[$key])) {
                $names[$key] = $value;
            }
        };

        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            $add($channel['label'] ?? ($channel['slug'] ?? ''));
        }

        try {
            if (Schema::hasTable('channel_master')) {
                ChannelMaster::query()
                    ->whereRaw('LOWER(TRIM(COALESCE(status, ""))) = ?', ['active'])
                    ->orderBy('channel')
                    ->pluck('channel')
                    ->each($add);
            }
        } catch (\Throwable) {
            // channel_master unavailable: fall back to the other sources
        }

        try {
            if (Schema::hasTable('order_fulfillment_manual_orders')) {
                OrderFulfillmentManualOrder::query()
                    ->select('marketplace')
                    ->distinct()
                    ->pluck('marketplace')
                    ->each($add);
            }
        } catch (\Throwable) {
            // manual table unavailable
        }

        $list = array_values($names);
        usort($list, fn (string $a, string $b) => strcasecmp($a, $b));

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedManualOrderHeader(Request $request): array
    {
        $data = $request->validate([
            'marketplace' => 'required|string|max:128',
            'order_id' => 'required|string|max:128',
            'order_date' => 'required|date',
            'paid' => 'nullable|boolean',
            'amount' => 'nullable|numeric|min:0|max:999999999',
            'reference' => 'nullable|string|max:128',
            'customer_name' => 'nullable|string|max:191',
            'customer_email' => 'nullable|string|max:191',
            'customer_phone' => 'nullable|string|max:64',
            'address1' => 'nullable|string|max:191',
            'address2' => 'nullable|string|max:191',
            'city' => 'nullable|string|max:128',
            'state' => 'nullable|string|max:128',
            'zip' => 'nullable|string|max:32',
            'country' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:5000',
        ]);

        $tz = $this->sofTimezone();
        $orderDate = \Carbon\Carbon::parse((string) $data['order_date'], $tz);
        $earliest = $this->earliestOrderDate();
        if ($orderDate->lt($earliest)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'order_date' => 'Order date cannot be before '.$earliest->format('d M Y').'.',
            ]);
        }

        $clean = static fn ($v) => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);

        $header = [
            'marketplace' => trim((string) $data['marketplace']),
            'order_id' => trim((string) $data['order_id']),
            'order_date' => $orderDate->format('Y-m-d H:i:s'),
            'paid' => (bool) ($data['paid'] ?? true),
            'amount' => isset($data['amount']) && $data['amount'] !== '' ? round((float) $data['amount'], 2) : null,
            'customer_name' => $clean($data['customer_name'] ?? null),
            'customer_email' => $clean($data['customer_email'] ?? null),
            'customer_phone' => $clean($data['customer_phone'] ?? null),
            'address1' => $clean($data['address1'] ?? null),
            'address2' => $clean($data['address2'] ?? null),
            'city' => $clean($data['city'] ?? null),
            'state' => $clean($data['state'] ?? null),
            'zip' => $clean($data['zip'] ?? null),
            'country' => $clean($data['country'] ?? null),
            'notes' => $clean($data['notes'] ?? null),
        ];
        // The label is found by the marketplace order id; a Shopify/Veeqo reference is
        // not asked for on the form and is only kept when a caller sends one.
        if ($request->has('reference')) {
            $header['reference'] = $clean($data['reference'] ?? null);
        }

        return $header;
    }

    /**
     * @return list<array{sku: string, qty: int, unit_price: float|null}>
     */
    protected function validatedManualOrderLines(Request $request): array
    {
        $request->validate([
            'lines' => 'required|array|min:1|max:50',
            'lines.*.sku' => 'required|string|max:191',
            'lines.*.qty' => 'nullable|integer|min:1|max:100000',
            'lines.*.price' => 'nullable|numeric|min:0|max:999999999',
        ]);

        $lines = [];
        foreach ((array) $request->input('lines', []) as $line) {
            $sku = trim((string) ($line['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $lines[] = [
                'sku' => $sku,
                'qty' => max(1, (int) ($line['qty'] ?? 1)),
                'unit_price' => $this->priceOrNull($line['price'] ?? null),
            ];
        }
        if ($lines === []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['lines' => 'Add at least one SKU.']);
        }

        return $lines;
    }

    protected function priceOrNull(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * Order total = sum of unit price × qty over the given lines; null when no
     * line has a price.
     *
     * @param  iterable<array{qty: int, unit_price: float|null}|OrderFulfillmentManualOrder>  $lines
     */
    protected function manualOrderTotal(iterable $lines): ?float
    {
        $total = 0.0;
        $priced = false;
        foreach ($lines as $line) {
            $price = $line instanceof OrderFulfillmentManualOrder ? $line->unit_price : ($line['unit_price'] ?? null);
            $qty = $line instanceof OrderFulfillmentManualOrder ? $line->qty : ($line['qty'] ?? 1);
            if ($price === null) {
                continue;
            }
            $priced = true;
            $total += (float) $price * max(1, (int) $qty);
        }

        return $priced ? round($total, 2) : null;
    }

    /**
     * Selling price for one unit from CP Master (Values.msrp, else Values.map).
     */
    protected function productUnitPrice(?ProductMaster $product): ?float
    {
        if ($product === null) {
            return null;
        }
        $values = is_array($product->Values)
            ? $product->Values
            : (is_string($product->Values) ? (json_decode($product->Values, true) ?: []) : []);
        foreach (['msrp', 'map'] as $key) {
            if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > 0) {
                return round((float) $values[$key], 2);
            }
        }

        return null;
    }

    public function storeManualOrder(Request $request): JsonResponse
    {
        $this->ensureManualOrdersTable();
        $header = $this->validatedManualOrderHeader($request);
        $lines = $this->validatedManualOrderLines($request);

        $duplicate = OrderFulfillmentManualOrder::query()
            ->whereRaw('LOWER(marketplace) = ?', [strtolower($header['marketplace'])])
            ->where('order_id', $header['order_id'])
            ->exists();
        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Order '.$header['order_id'].' for '.$header['marketplace'].' already exists.',
            ], 422);
        }

        // The order amount is the sum of the item prices unless the user typed one.
        if ($header['amount'] === null) {
            $header['amount'] = $this->manualOrderTotal($lines);
        }

        $first = null;
        DB::transaction(function () use ($header, $lines, &$first): void {
            foreach ($lines as $line) {
                $order = OrderFulfillmentManualOrder::query()->create($header + [
                    'sku' => $line['sku'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'status' => OrderFulfillmentManualOrder::STATUS_CREATED,
                    'created_by' => auth()->id(),
                ]);
                $first ??= $order;
            }
        });

        return response()->json(['success' => true, 'rows' => $first ? $this->manualOrderRowsForResponse($first) : []]);
    }

    /**
     * Edit the whole order: header fields plus the full SKU line list. Lines
     * carrying an id are updated, new ones are created, missing ones removed.
     */
    public function updateManualOrder(Request $request, int $id): JsonResponse
    {
        $this->ensureManualOrdersTable();
        $order = OrderFulfillmentManualOrder::query()->find($id);
        if ($order === null) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }
        $header = $this->validatedManualOrderHeader($request);

        $request->validate([
            'lines' => 'required|array|min:1|max:50',
            'lines.*.id' => 'nullable|integer|min:1',
            'lines.*.sku' => 'required|string|max:191',
            'lines.*.qty' => 'nullable|integer|min:1|max:100000',
            'lines.*.price' => 'nullable|numeric|min:0|max:999999999',
        ]);

        $siblings = $this->manualOrderLines($order);
        $siblingIds = $siblings->pluck('id')->map(fn ($v) => (int) $v)->all();

        $wanted = [];
        foreach ((array) $request->input('lines', []) as $line) {
            $sku = trim((string) ($line['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $lineId = (int) ($line['id'] ?? 0);
            $wanted[] = [
                'id' => in_array($lineId, $siblingIds, true) ? $lineId : 0,
                'sku' => $sku,
                'qty' => max(1, (int) ($line['qty'] ?? 1)),
                'unit_price' => $this->priceOrNull($line['price'] ?? null),
            ];
        }
        if ($wanted === []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['lines' => 'Add at least one SKU.']);
        }

        // Another order already using the new marketplace + order id?
        $clash = OrderFulfillmentManualOrder::query()
            ->whereRaw('LOWER(marketplace) = ?', [strtolower($header['marketplace'])])
            ->where('order_id', $header['order_id'])
            ->whereNotIn('id', $siblingIds)
            ->exists();
        if ($clash) {
            return response()->json([
                'success' => false,
                'message' => 'Order '.$header['order_id'].' for '.$header['marketplace'].' already exists.',
            ], 422);
        }

        if ($header['amount'] === null) {
            $header['amount'] = $this->manualOrderTotal($wanted);
        }

        $keep = $order;
        $removedKeys = [];
        DB::transaction(function () use ($siblings, $header, $wanted, &$keep, &$removedKeys): void {
            $byId = $siblings->keyBy(fn ($s) => (int) $s->id);
            $seen = [];
            $template = $siblings->first();
            foreach ($wanted as $line) {
                $model = $line['id'] > 0 ? ($byId[$line['id']] ?? null) : null;
                if ($model === null) {
                    $model = new OrderFulfillmentManualOrder([
                        'status' => (string) ($template->status ?: OrderFulfillmentManualOrder::STATUS_CREATED),
                        'created_by' => auth()->id(),
                    ]);
                    $model->fulfilled_at = $template->fulfilled_at;
                    $model->fulfilled_by = $template->fulfilled_by;
                }
                $model->fill($header);
                $model->sku = $line['sku'];
                $model->qty = $line['qty'];
                $model->unit_price = $line['unit_price'];
                $model->save();
                $seen[(int) $model->id] = true;
            }
            foreach ($siblings as $sibling) {
                if (! isset($seen[(int) $sibling->id])) {
                    $removedKeys[] = self::MANUAL_SLUG.'-'.$sibling->id;
                    $sibling->delete();
                }
            }
            $keep = OrderFulfillmentManualOrder::query()->whereIn('id', array_keys($seen))->orderBy('id')->first() ?? $keep;
        });

        if ($removedKeys !== [] && Schema::hasTable('order_fulfillment_trackings')) {
            OrderFulfillmentTracking::query()->whereIn('row_key', $removedKeys)->delete();
        }

        return response()->json([
            'success' => true,
            'rows' => $this->manualOrderRowsForResponse($keep),
            'removed_ids' => array_values(array_diff([self::MANUAL_SLUG.'-'.$order->id], [self::MANUAL_SLUG.'-'.$keep->id])),
        ]);
    }

    /**
     * Deletes the whole order (every SKU line) and its saved tracking.
     */
    public function deleteManualOrder(int $id): JsonResponse
    {
        $this->ensureManualOrdersTable();
        $order = OrderFulfillmentManualOrder::query()->find($id);
        if ($order === null) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }
        $lines = $this->manualOrderLines($order);
        $rowKeys = $lines->map(fn ($l) => self::MANUAL_SLUG.'-'.$l->id)->all();
        OrderFulfillmentManualOrder::query()->whereIn('id', $lines->pluck('id')->all())->delete();
        if (Schema::hasTable('order_fulfillment_trackings')) {
            OrderFulfillmentTracking::query()->whereIn('row_key', $rowKeys)->delete();
        }

        return response()->json(['success' => true, 'id' => self::MANUAL_SLUG.'-'.$order->id, 'ids' => $rowKeys]);
    }

    /**
     * Fulfil = the user's decision. Tracking is fetched now (Veeqo → 4Seller) if
     * none is saved; without a number the order stays "Order Created".
     */
    public function fulfillManualOrder(Request $request, int $id): JsonResponse
    {
        @set_time_limit(60);
        $this->ensureManualOrdersTable();
        $this->ensureTrackingTable();

        $order = OrderFulfillmentManualOrder::query()->find($id);
        if ($order === null) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }
        $request->validate(['tracking_number' => 'nullable|string|max:128']);
        $typed = trim((string) $request->input('tracking_number', ''));

        $siblings = $this->manualOrderLines($order);

        $group = [
            'mm_slug' => self::MANUAL_SLUG,
            'order_id' => trim((string) $order->order_id),
            'sku' => (string) $order->sku,
            'source_id' => (int) $order->id,
            'reference' => trim((string) ($order->reference ?? '')),
            'rows' => $siblings->map(fn ($s) => ['id' => self::MANUAL_SLUG.'-'.$s->id, 'sku' => (string) $s->sku])->all(),
        ];

        if ($typed !== '') {
            foreach ($group['rows'] as $line) {
                $this->rememberTracking($line['id'], self::MANUAL_SLUG, $group['order_id'], $line['sku'], $typed, null, 'manual', true);
            }
            $updates = array_map(fn ($line) => [
                'id' => $line['id'],
                'tracking' => $typed,
                'tracking_source' => 'manual',
                'tracking_checked' => true,
            ], $group['rows']);
        } else {
            $updates = $this->resolveTrackingGroup($group, microtime(true) + 40.0, $this->labelTrackingLookup());
        }

        $number = '';
        foreach ($updates as $update) {
            if (trim((string) ($update['tracking'] ?? '')) !== '') {
                $number = trim((string) $update['tracking']);
                break;
            }
        }
        if ($number === '') {
            return response()->json([
                'success' => false,
                'no_tracking' => true,
                'message' => 'No tracking number found yet in Veeqo or 4Seller for order '.$group['order_id'].'. Enter it below, or try again after the label is purchased.',
            ], 422);
        }

        $now = now();
        foreach ($siblings as $sibling) {
            $sibling->status = OrderFulfillmentManualOrder::STATUS_FULFILLED;
            $sibling->fulfilled_at = $now;
            $sibling->fulfilled_by = auth()->id();
            $sibling->save();
        }

        return response()->json([
            'success' => true,
            'rows' => $this->manualOrderRowsForResponse($order, true),
            'updates' => $this->withCarrierOnUpdates($updates),
        ]);
    }

    /**
     * SKU search for the manual order form: CP Master SKUs containing the typed
     * text, prefix matches first, with their inventory.
     */
    public function suggestSkus(Request $request): JsonResponse
    {
        $q = trim(str_replace("\u{00a0}", ' ', (string) $request->input('q', '')));
        $q = preg_replace('/\s+/', ' ', $q) ?? $q;
        if ($q === '' || ! Schema::hasTable('product_master')) {
            return response()->json(['success' => true, 'items' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $prefix = str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $normalized = "REPLACE(REPLACE(sku, CHAR(194,160), ' '), '  ', ' ')";

        try {
            $products = ProductMaster::query()
                ->whereRaw("{$normalized} LIKE ?", [$like])
                ->where('sku', 'NOT LIKE', 'PARENT%')
                ->orderByRaw("CASE WHEN {$normalized} LIKE ? THEN 0 ELSE 1 END, LENGTH(sku), sku", [$prefix])
                ->limit(20)
                ->get(['sku', 'parent', 'Values']);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => true, 'items' => []]);
        }

        $skus = $products->map(fn ($p) => $this->inventoryLookupSku((string) $p->sku))->filter()->unique()->values()->all();
        $inv = $this->cpMasterInventoryByCompactSku($skus);

        $items = [];
        foreach ($products as $product) {
            $sku = $this->inventoryLookupSku((string) $product->sku);
            if ($sku === '') {
                continue;
            }
            $key = ProductMaster::skuCompact($sku);
            $items[] = [
                'sku' => $sku,
                'parent' => trim((string) ($product->parent ?? '')),
                'inv' => $key !== '' && array_key_exists($key, $inv) ? $inv[$key] : null,
                'price' => $this->productUnitPrice($product),
            ];
        }

        return response()->json(['success' => true, 'items' => $items]);
    }

    protected function ensureManualOrdersTable(): void
    {
        if (Schema::hasTable('order_fulfillment_manual_orders')) {
            if (! Schema::hasColumn('order_fulfillment_manual_orders', 'unit_price')) {
                Schema::table('order_fulfillment_manual_orders', function ($table) {
                    $table->decimal('unit_price', 12, 2)->nullable()->after('qty');
                });
            }

            return;
        }

        Schema::create('order_fulfillment_manual_orders', function ($table) {
            $table->id();
            $table->string('marketplace', 128);
            $table->string('order_id', 128);
            $table->dateTime('order_date');
            $table->string('sku', 191);
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->boolean('paid')->default(true);
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('reference', 128)->nullable();
            $table->string('customer_name', 191)->nullable();
            $table->string('customer_email', 191)->nullable();
            $table->string('customer_phone', 64)->nullable();
            $table->string('address1', 191)->nullable();
            $table->string('address2', 191)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('state', 128)->nullable();
            $table->string('zip', 32)->nullable();
            $table->string('country', 64)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('Order Created');
            $table->timestamp('fulfilled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('fulfilled_by')->nullable();
            $table->timestamps();
            $table->index(['marketplace', 'order_id'], 'of_manual_mp_order_idx');
            $table->index('order_date', 'of_manual_order_date_idx');
        });
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
            $row['tracking_checked_at'] = null;
        }
        unset($row);

        if ($rows === [] || ! Schema::hasTable('order_fulfillment_trackings')) {
            return $rows;
        }
        $missCooldown = now()->subMinutes(self::TRACKING_MISS_COOLDOWN_MINUTES);

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
            $checkedAt = $record->checked_at ?? null;
            $row['tracking'] = $number;
            $row['tracking_source'] = (string) ($record->source ?? '');
            $row['tracking_checked_at'] = $checkedAt ? \Carbon\Carbon::parse($checkedAt)->toDateTimeString() : null;
            // A saved number is final. A recent miss waits for the scheduled retry
            // instead of being re-searched on every page load; older misses are retried.
            $row['tracking_checked'] = $number !== ''
                || ($checkedAt !== null && \Carbon\Carbon::parse($checkedAt)->gt($missCooldown));
        }
        unset($row);

        return $rows;
    }

    /** Rows the page may send in one lookup request. */
    public const TRACKING_ROWS_PER_REQUEST = 4;

    /** Seconds one lookup request may spend before returning what it has. */
    private const TRACKING_REQUEST_BUDGET = 25.0;

    /** Do not start another order unless at least this much of the budget is left. */
    private const TRACKING_MIN_GROUP_SECONDS = 7.0;

    /** A miss this recent is not looked up again by the page; the scheduler retries it. */
    public const TRACKING_MISS_COOLDOWN_MINUTES = 60;

    public function lookupTracking(Request $request): JsonResponse
    {
        @set_time_limit(40);
        $this->ensureTrackingTable();

        $validated = $request->validate([
            'rows' => 'required|array|max:'.self::TRACKING_ROWS_PER_REQUEST,
            'rows.*.id' => 'required|string|max:191',
            'rows.*.mm_slug' => 'required|string|max:64',
            'rows.*.order_id' => 'nullable|string|max:128',
            'rows.*.sku' => 'nullable|string|max:191',
            'rows.*.source_id' => 'nullable|integer',
            'rows.*.reference' => 'nullable|string|max:128',
        ]);

        $groups = $this->trackingGroupsFromRows($validated['rows']);
        $updates = [];
        $deadline = microtime(true) + self::TRACKING_REQUEST_BUDGET;
        $lookup = $this->labelTrackingLookup();

        foreach (array_values($groups) as $i => $group) {
            // Always finish the first order; later ones wait for the next request
            // when the budget is nearly spent (the page re-sends anything not returned).
            if ($i > 0 && microtime(true) + self::TRACKING_MIN_GROUP_SECONDS >= $deadline) {
                break;
            }
            array_push($updates, ...$this->resolveTrackingGroup($group, $deadline, $lookup));
        }

        return response()->json([
            'success' => true,
            'updates' => $this->withCarrierOnUpdates($updates),
        ]);
    }

    /** Share of the backfill budget the batch channel sweep may use. */
    private const TRACKING_SWEEP_BUDGET_SHARE = 0.45;

    /**
     * Scheduler entry point: resolve orders in the default range that still have
     * no tracking number, so the page shows numbers without waiting on the browser.
     * A batch sweep (synced marketplace rows + TikTok/Shein bulk detail APIs) runs
     * first for every missing order; the per-order resolver handles the rest.
     *
     * @return array{groups: int, found: int, missed: int, pending: int, swept: int, seconds: float}
     */
    public function backfillTracking(int $maxGroups = 60, int $budgetSeconds = 540): array
    {
        $startedAt = microtime(true);
        $deadline = $startedAt + max(30, $budgetSeconds);
        $this->ensureTrackingTable();

        $rows = $this->attachSavedTracking($this->collectFulfillmentRows());
        $cooldown = now()->subMinutes(self::TRACKING_MISS_COOLDOWN_MINUTES);

        $missing = [];
        foreach ($rows as $row) {
            if (trim((string) ($row['tracking'] ?? '')) !== '' || ! empty($row['manual'])) {
                continue;
            }
            $slug = (string) ($row['mm_slug'] ?? '');
            $orderId = trim((string) ($row['order_id'] ?? ''));
            if ($slug === '' || $orderId === '') {
                continue;
            }
            $missing[$slug.'|'.$orderId] ??= ['mm_slug' => $slug, 'order_id' => $orderId, 'rows' => []];
            $missing[$slug.'|'.$orderId]['rows'][] = ['id' => (string) $row['id'], 'sku' => (string) ($row['sku'] ?? '')];
        }
        $sweepDeadline = min($deadline, $startedAt + max(20, $budgetSeconds * self::TRACKING_SWEEP_BUDGET_SHARE));
        $sweptKeys = $this->sweepChannelTracking($missing, $sweepDeadline);

        $groups = [];
        foreach ($rows as $row) {
            if (trim((string) ($row['tracking'] ?? '')) !== '') {
                continue;
            }
            $checkedAt = $row['tracking_checked_at'] ?? null;
            if ($checkedAt !== null && \Carbon\Carbon::parse($checkedAt)->gt($cooldown)) {
                continue;
            }
            $slug = (string) ($row['mm_slug'] ?? '');
            $orderId = trim((string) ($row['order_id'] ?? ''));
            if ($slug === '' || $orderId === '') {
                continue;
            }
            $key = $slug.'|'.$orderId;
            if (isset($sweptKeys[$key])) {
                continue;
            }
            $groups[$key] ??= [
                'mm_slug' => $slug,
                'order_id' => $orderId,
                'sku' => (string) ($row['sku'] ?? ''),
                'source_id' => (int) ($row['source_id'] ?? 0),
                'reference' => (string) ($row['reference'] ?? ''),
                'rows' => [],
                'checked_at' => $checkedAt,
            ];
            $groups[$key]['rows'][] = ['id' => (string) $row['id'], 'sku' => (string) ($row['sku'] ?? '')];
            if ($checkedAt === null) {
                $groups[$key]['checked_at'] = null;
            }
        }

        // Never-checked orders first, then the ones checked longest ago.
        uasort($groups, static function (array $a, array $b): int {
            if ($a['checked_at'] === null || $b['checked_at'] === null) {
                return ($a['checked_at'] === null ? 0 : 1) <=> ($b['checked_at'] === null ? 0 : 1);
            }

            return strcmp((string) $a['checked_at'], (string) $b['checked_at']);
        });

        $lookup = $this->labelTrackingLookup();
        $done = 0;
        $found = 0;
        foreach ($groups as $group) {
            if ($done >= $maxGroups || microtime(true) + self::TRACKING_MIN_GROUP_SECONDS >= $deadline) {
                break;
            }
            unset($group['checked_at']);
            $updates = $this->resolveTrackingGroup($group, min($deadline, microtime(true) + self::TRACKING_REQUEST_BUDGET), $lookup);
            $done++;
            foreach ($updates as $update) {
                if (trim((string) ($update['tracking'] ?? '')) !== '') {
                    $found++;
                    break;
                }
            }
        }

        return [
            'groups' => $done,
            'found' => $found,
            'missed' => $done - $found,
            'pending' => max(0, count($groups) - $done),
            'swept' => count($sweptKeys),
            'seconds' => round(microtime(true) - $startedAt, 1),
        ];
    }

    /**
     * Batch tracking for every order still missing a number. Hits are saved as
     * source "channel" (order-fulfillment:push-tracking then fulfils Shopify).
     *
     * @param  array<string, array{mm_slug: string, order_id: string, rows: list<array{id: string, sku: string}>}>  $missing
     * @return array<string, true> group keys that now have tracking
     */
    protected function sweepChannelTracking(array $missing, float $deadline): array
    {
        if ($missing === []) {
            return [];
        }
        $bySlug = [];
        foreach ($missing as $key => $group) {
            $bySlug[$group['mm_slug']][$group['order_id']] = $key;
        }

        $lookup = app(ChannelBatchTrackingLookup::class);
        $found = [];
        foreach ($bySlug as $slug => $keysByOrder) {
            if (microtime(true) >= $deadline) {
                break;
            }
            try {
                $hits = $lookup->lookup((string) $slug, array_map('strval', array_keys($keysByOrder)), $deadline);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            foreach ($hits as $orderId => $hit) {
                $key = $keysByOrder[(string) $orderId] ?? null;
                $number = trim((string) ($hit['tracking'] ?? ''));
                if ($key === null || $number === '') {
                    continue;
                }
                $group = $missing[$key];
                foreach ($group['rows'] as $line) {
                    $this->rememberTracking(
                        (string) $line['id'],
                        (string) $group['mm_slug'],
                        (string) $group['order_id'],
                        (string) ($line['sku'] ?? ''),
                        $number,
                        trim((string) ($hit['carrier'] ?? '')) ?: null,
                        'channel',
                        false
                    );
                }
                $found[$key] = true;
            }
        }

        return $found;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{mm_slug: string, order_id: string, sku: string, source_id: int, rows: list<array{id: string, sku: string}>}>
     */
    protected function trackingGroupsFromRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
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
                'reference' => trim((string) ($row['reference'] ?? '')),
                'rows' => [],
            ];
            $groups[$groupKey]['rows'][] = [
                'id' => $rowKey,
                'sku' => trim((string) ($row['sku'] ?? '')),
            ];
        }

        return $groups;
    }

    /**
     * One order: saved number → synced marketplace row → Veeqo → marketplace API → 4Seller.
     * Shopify fulfillment tracking is never used.
     *
     * @param  array{mm_slug: string, order_id: string, sku: string, source_id: int, rows: list<array{id: string, sku: string}>}  $group
     * @return list<array{id: string, tracking: string, tracking_source: string, tracking_checked: bool}>
     */
    protected function resolveTrackingGroup(array $group, float $deadline, ?VeeqoShopifyFulfillmentService $lookup): array
    {
        $copied = $this->copyKnownLabelTracking($group);
        if ($copied !== null) {
            return $copied;
        }

        $hit = null;
        $orderId = (string) $group['order_id'];
        if ($orderId !== '' && $lookup !== null) {
            $slug = (string) $group['mm_slug'];
            $plain = ltrim($orderId, '#');
            $veeqoRef = $this->veeqoOrderRef($slug, $plain);
            $veeqoQueries = [$veeqoRef];
            if ($plain !== '' && $plain !== $veeqoRef) {
                $veeqoQueries[] = $plain;
            }
            // Manual orders may carry the Shopify/Veeqo order number the label was bought under.
            $reference = ltrim(trim((string) ($group['reference'] ?? '')), '#');
            if ($reference !== '' && ! in_array($reference, $veeqoQueries, true)) {
                $veeqoQueries[] = $reference;
            }
            $fourSellerRefs = $reference !== '' && $reference !== $plain ? [$plain, $reference] : [$plain];

            try {
                $local = $lookup->localMarketplaceTracking($slug, array_values(array_unique([$plain, $orderId])));
                if (is_array($local) && trim((string) ($local['tracking'] ?? '')) !== '') {
                    $hit = [
                        'tracking' => (string) $local['tracking'],
                        'carrier' => (string) ($local['carrier'] ?? ''),
                        'source' => 'channel',
                    ];
                }
            } catch (\Throwable $e) {
                report($e);
            }

            if ($hit === null) {
                try {
                    $veeqo = $lookup->findVeeqoShipment($veeqoQueries, true, '', [], count($veeqoQueries));
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
            }
            if ($hit === null && microtime(true) < $deadline) {
                try {
                    $channel = $lookup->lookupLiveChannelTracking($slug, [$plain, $veeqoRef]);
                } catch (\Throwable $e) {
                    report($e);
                    $channel = null;
                }
                if (is_array($channel) && empty($channel['retry']) && trim((string) ($channel['tracking'] ?? '')) !== '') {
                    $hit = [
                        'tracking' => (string) $channel['tracking'],
                        'carrier' => (string) ($channel['carrier'] ?? ''),
                        'source' => 'channel',
                    ];
                }
            }
            if ($hit === null && microtime(true) + 6 < $deadline) {
                try {
                    $fourSeller = app(FourSellerApiService::class);
                    $fourSeller->setTimeout(4);
                    $fs = $fourSeller->findShipment($fourSellerRefs, count($fourSellerRefs));
                    if (is_array($fs) && trim((string) ($fs['tracking'] ?? '')) !== '') {
                        $hit = [
                            'tracking' => (string) $fs['tracking'],
                            'carrier' => (string) ($fs['carrier'] ?? 'GOFO'),
                            'source' => '4seller',
                        ];
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $source = strtolower(trim((string) ($hit['source'] ?? '')));
        $number = trim((string) ($hit['tracking'] ?? ''));
        $allowed = in_array($source, ['gofo', '4seller', 'veeqo', 'channel'], true) && $number !== '';
        if (! $allowed) {
            $source = null;
            $number = '';
        }

        $updates = [];
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

        return $updates;
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
            ->whereIn('source', ['gofo', '4seller', 'veeqo', 'channel'])
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
     * Order number Veeqo stores. TikTok copies are #TT-{id} / #TT2-{id}, not the raw id.
     */
    protected function veeqoOrderRef(string $slug, string $orderId): string
    {
        $plain = ltrim(trim($orderId), '#');
        if ($plain === '') {
            return $orderId;
        }

        return match ($slug) {
            'tiktok' => 'TT-'.$plain,
            'tiktok2' => 'TT2-'.$plain,
            'amazon' => str_starts_with(strtolower($plain), 'amz') ? $plain : 'Amz'.$plain,
            default => $plain,
        };
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
            if (! Schema::hasColumn('order_fulfillment_trackings', 'shopify_fulfilled_at')) {
                Schema::table('order_fulfillment_trackings', function ($table) {
                    $table->string('shopify_order_id', 64)->nullable()->after('checked_at');
                    $table->timestamp('shopify_fulfilled_at')->nullable()->after('shopify_order_id');
                    $table->unsignedTinyInteger('shopify_push_attempts')->default(0)->after('shopify_fulfilled_at');
                    $table->timestamp('shopify_push_checked_at')->nullable()->after('shopify_push_attempts');
                    $table->string('shopify_push_message', 255)->nullable()->after('shopify_push_checked_at');
                    $table->timestamp('channel_pushed_at')->nullable()->after('shopify_push_message');
                    $table->unsignedTinyInteger('channel_push_attempts')->default(0)->after('channel_pushed_at');
                    $table->string('channel_push_message', 255)->nullable()->after('channel_push_attempts');
                });
            }

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
            $table->string('shopify_order_id', 64)->nullable();
            $table->timestamp('shopify_fulfilled_at')->nullable();
            $table->unsignedTinyInteger('shopify_push_attempts')->default(0);
            $table->timestamp('shopify_push_checked_at')->nullable();
            $table->string('shopify_push_message', 255)->nullable();
            $table->timestamp('channel_pushed_at')->nullable();
            $table->unsignedTinyInteger('channel_push_attempts')->default(0);
            $table->string('channel_push_message', 255)->nullable();
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
                'veeqo' => [VeeqoApiService::class, 6],
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

        $number = $tracking !== null && trim($tracking) !== '' ? trim($tracking) : null;
        $values = [
            'mm_slug' => $slug,
            'order_id' => $orderId,
            'sku' => $sku !== '' ? $sku : null,
            'tracking_number' => $number,
            'carrier' => $this->carrierNameForTracking($tracking, $carrier),
            'source' => $source,
            'checked_at' => now(),
        ];
        // A different number than the one already copied to Shopify / the
        // marketplace must be pushed again by order-fulfillment:push-tracking.
        if ($existing && $number !== null && $existing->shopify_fulfilled_at !== null
            && strcasecmp((string) preg_replace('/\s+/', '', (string) $existing->tracking_number), (string) preg_replace('/\s+/', '', $number)) !== 0) {
            $values += [
                'shopify_fulfilled_at' => null,
                'shopify_push_attempts' => 0,
                'shopify_push_checked_at' => null,
                'shopify_push_message' => null,
                'channel_pushed_at' => null,
                'channel_push_attempts' => 0,
                'channel_push_message' => null,
            ];
        }

        OrderFulfillmentTracking::query()->updateOrCreate(['row_key' => $rowKey], $values);
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
