<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\AlibabaOrderMetric;
use App\Models\AliexpressOrderMetric;
use App\Models\AmazonOrderItem;
use App\Models\BestBuyOrderMetric;
use App\Models\ChannelMaster;
use App\Models\ChannelMasterCalculatedData;
use App\Models\DobaDailyData;
use App\Models\Ebay1OrderMetric;
use App\Models\Ebay2OrderMetric;
use App\Models\Ebay3OrderMetric;
use App\Models\FaireOrderMetric;
use App\Models\MacyOrderMetric;
use App\Models\NeweggOrderMetric;
use App\Models\ProductMaster;
use App\Models\PurchasingPowerSale;
use App\Models\ReverbOrderMetric;
use App\Models\SalesOrderFulfillmentBadgeLink;
use App\Models\SalesOrderFulfillmentDailySummary;
use App\Models\SheinOrderMetric;
use App\Models\ShopifySku;
use App\Models\TemuOrder;
use App\Models\TopDawgOrderMetric;
use App\Models\WayfairDailyData;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\ShipmentTrackingService;
use App\Services\Support\MarketplaceApiConfigService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Sales Order Fulfillment — Tabulator of active channels from channel_master
 * (same source as /all-marketplace-master Active Channels Master).
 */
class SalesOrderFulfillmentController extends Controller
{
    /** @var list<string> */
    public const TOP_BADGE_KEYS = ['gofo', 'veeqo', 'shopify', 'others'];

    /** @var list<array<string, mixed>>|null */
    protected ?array $cachedLabelCreatedRows = null;

    public function __construct(
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function index(GofoExpressService $gofo): View
    {
        return view('channels.sales_order_fulfillment', [
            'topBadges' => $this->topBadgePayload(),
            'gofoApiConfigured' => $gofo->isConfigured(),
            'gofoApiBase' => (string) config('services.gofo.api_base', ''),
        ]);
    }

    public function data(): JsonResponse
    {
        try {
            if (! Schema::hasTable('channel_master')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                ]);
            }

            $hasLogo = Schema::hasColumn('channel_master', 'logo');
            $hasSellerLink = Schema::hasColumn('channel_master', 'seller_link');
            $hasAlias = Schema::hasColumn('channel_master', 'alias');
            $hasMissingLink = Schema::hasColumn('channel_master', 'missing_link');
            $hasChOrdersLink = Schema::hasColumn('channel_master', 'ch_orders_link');

            $columns = ['id', 'channel', 'status'];
            if ($hasLogo) {
                $columns[] = 'logo';
            }
            if ($hasSellerLink) {
                $columns[] = 'seller_link';
            }
            if ($hasAlias) {
                $columns[] = 'alias';
            }
            if ($hasMissingLink) {
                $columns[] = 'missing_link';
            }
            if ($hasChOrdersLink) {
                $columns[] = 'ch_orders_link';
            }

            $rows = ChannelMaster::query()
                ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                ->whereNotNull('channel')
                ->where('channel', '!=', '')
                ->orderBy('channel')
                ->get($columns);

            $managerByMpKey = $this->managerChannelsByMpKey();
            $pendingBySlug = $this->pendingOrderCountsBySlug();

            $data = $rows->map(function ($row) use ($hasLogo, $hasSellerLink, $hasAlias, $hasMissingLink, $hasChOrdersLink, $managerByMpKey, $pendingBySlug) {
                $channel = trim((string) $row->channel);
                $manager = $managerByMpKey[strtolower($channel)] ?? null;
                $slug = $manager['slug'] ?? null;
                $hasManager = $slug !== null;
                $connected = $hasManager ? $this->apiConfig->isConfigured($slug) : false;
                $pending = $hasManager ? (int) ($pendingBySlug[$slug] ?? 0) : null;
                $chOrdersLink = $hasChOrdersLink ? trim((string) ($row->ch_orders_link ?? '')) : '';

                return [
                    'id' => $row->id,
                    'logo' => $hasLogo ? ($row->logo ?? null) : null,
                    'channel' => $channel,
                    'alias' => $hasAlias ? ($row->alias ?? null) : null,
                    'seller_link' => $hasSellerLink ? ($row->seller_link ?? null) : null,
                    'missing_link' => $hasMissingLink ? ($row->missing_link ?? null) : null,
                    'ch_orders_link' => $chOrdersLink !== '' ? $chOrdersLink : null,
                    'mm_slug' => $slug,
                    'has_manager' => $hasManager,
                    'oc_connected' => $connected,
                    'pending_count' => $pending,
                    'orders_url' => $slug ? route('marketplace.orders', $slug) : null,
                ];
            })->values();

            $totals = $this->collectSummaryTotals($data->count(), $data);

            return response()->json(array_merge([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
            ], $totals));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load active channels.',
                'data' => [],
                'count' => 0,
                'channel_count' => 0,
                'pending_total' => 0,
                'fulfilled_24h' => 0,
                'scan_done_24h' => 0,
                'in_transit_total' => 0,
                'in_received_total' => 0,
                'invoiced_total' => 0,
                'delivered_total' => 0,
                'all_order_total' => 0,
            ], 500);
        }
    }

    /**
     * All pending orders across Marketplace Manager channels (for Pending tab).
     * Last 30 days only.
     */
    public function pendingData(): JsonResponse
    {
        try {
            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast30Days($this->pendingOrdersQuery($slug), $slug),
                false
            );

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load pending orders.',
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * Label Created / No Scan (fulfilled) orders — last 30 days.
     * Excludes rows whose carrier tracking has already progressed (In Transit / Delivered).
     */
    public function fulfilledData(): JsonResponse
    {
        try {
            $rows = array_values(array_filter(
                $this->labelCreatedOrderRows(),
                fn (array $r) => ! $this->carrierStatusHasLeftLabelCreated($r['shipment_status'] ?? null)
            ));

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load fulfilled orders.',
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * Shipped/Received — status Shipped / Received, last 30 days.
     */
    public function scanDoneData(): JsonResponse
    {
        try {
            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast30Days($this->scanDoneOrdersQuery($slug), $slug),
                true
            );

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load Shipped/Received orders.',
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * In Transit — last 30 days (marketplace In Transit + carrier In Transit from Label Created).
     */
    public function inTransitData(): JsonResponse
    {
        try {
            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast30Days($this->inTransitOrdersQuery($slug), $slug),
                true
            );
            $fromCarrier = array_values(array_filter(
                $this->labelCreatedOrderRows(),
                function (array $r) {
                    $s = (string) ($r['shipment_status'] ?? '');

                    return in_array($s, [
                        ShipmentTrackingService::STATUS_IN_TRANSIT,
                        ShipmentTrackingService::STATUS_OUT_FOR_DELIV,
                        ShipmentTrackingService::STATUS_PICKUP,
                    ], true);
                }
            ));
            $rows = $this->mergeOrderRowsById($rows, $fromCarrier);

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load In Transit orders.',
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * In Received — last 30 days.
     */
    public function inReceivedData(): JsonResponse
    {
        try {
            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast30Days($this->inReceivedOrdersQuery($slug), $slug),
                true
            );

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load In Received orders.',
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * Invoiced — last 30 days.
     */
    public function invoicedData(): JsonResponse
    {
        try {
            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast30Days($this->invoicedOrdersQuery($slug), $slug),
                true
            );

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load Invoiced orders.',
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * Delivered — marketplace delivered + carrier Delivered from Label Created.
     */
    public function deliveredData(): JsonResponse
    {
        try {
            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast30Days($this->deliveredOrdersQuery($slug), $slug),
                true
            );
            $fromCarrier = array_values(array_filter(
                $this->labelCreatedOrderRows(),
                fn (array $r) => ($r['shipment_status'] ?? null) === ShipmentTrackingService::STATUS_DELIVERED
            ));
            $rows = $this->mergeOrderRowsById($rows, $fromCarrier);

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load Delivered orders.',
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * All Order — marketplace orders from the last 30 days (original status values).
     */
    public function allOrderData(): JsonResponse
    {
        try {
            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->scopedToLast30Days($this->allOrdersQuery($slug), $slug),
                true,
                true
            );

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load all orders.',
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * @param  callable(string): (?Builder)  $queryForSlug
     * @return list<array<string, mixed>>
     */
    protected function collectOrderRows(callable $queryForSlug, bool $sortByUpdatedAt, bool $useOriginalStatus = false): array
    {
        $rows = [];
        $channelMetaBySlug = $this->channelMasterMetaBySlug();
        $profitPctBySlug = $this->channelProfitPctBySlug();

        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            if (! ($channel['enabled'] ?? false)) {
                continue;
            }

            $slug = (string) ($channel['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $query = $queryForSlug($slug);
            if ($query === null) {
                continue;
            }

            $ordersUrl = route('marketplace.orders', $slug);
            $meta = $channelMetaBySlug[$slug] ?? ['channel_id' => null, 'ch_orders_link' => null];

            try {
                $dateCol = $this->orderDateColumn($slug);
                $updCol = $this->orderUpdatedColumn($slug);
                $ordered = clone $query;
                if ($sortByUpdatedAt && $updCol !== null) {
                    $ordered->orderByDesc($updCol);
                }
                if ($dateCol !== null) {
                    $ordered->orderByDesc($dateCol);
                }
                $ordered->orderByDesc('id');
                $orders = $ordered->get();
            } catch (\Throwable) {
                try {
                    $orders = $query->orderByDesc('id')->get();
                } catch (\Throwable) {
                    continue;
                }
            }

            foreach ($orders as $order) {
                $n = $this->normalizeOrderFields($slug, $order);
                $statusRaw = trim((string) ($n['status'] ?? ''));
                $tracking = $this->extractTrackingNumber($slug, $n['raw_payload'] ?? null);
                $apiOrderId = trim((string) ($n['order_id'] ?? ''));
                $orderNumber = trim((string) ($n['order_number'] ?? ''));
                // Prefer human-readable order number (e.g. Faire display_id N8PA3FG3F8)
                // over internal API ids (e.g. bo_n8pa3fg3f8).
                $displayOrderId = $orderNumber !== '' ? $orderNumber : $apiOrderId;
                $showId = (int) ($n['show_id'] ?? $order->id);

                $rows[] = [
                    'id' => $slug.'-'.$order->id,
                    'row_id' => (int) $order->id,
                    'channel_id' => $meta['channel_id'],
                    'mm_slug' => $slug,
                    'channel_label' => (string) ($channel['label'] ?? $slug),
                    'channel_short' => (string) ($channel['short'] ?? strtoupper(substr($slug, 0, 2))),
                    'order_id' => $displayOrderId,
                    'order_id_api' => $apiOrderId,
                    'order_number' => $orderNumber !== '' ? $orderNumber : null,
                    'order_date' => $this->formatOrderDate($n['order_date'] ?? null),
                    'updated_at' => $this->formatOrderDate($n['updated_at'] ?? null),
                    'tracking_number' => $tracking !== null ? $tracking : ($n['tracking_number'] ?? null),
                    'tracking_company' => null,
                    'tracking_url' => null,
                    'shipment_status' => null,
                    'shipment_status_detail' => null,
                    'status' => $statusRaw,
                    'status_label' => $useOriginalStatus
                        ? ($statusRaw !== '' ? $statusRaw : '—')
                        : $this->orderStatusLabel($slug, $statusRaw),
                    'sku' => (string) ($n['sku'] ?? ''),
                    'display_title' => (string) ($n['display_title'] ?? ''),
                    'INV' => 0,
                    'label' => null,
                    'quantity' => (int) ($n['quantity'] ?? 1),
                    'amount' => is_numeric($n['amount'] ?? null) ? (float) $n['amount'] : null,
                    'groi_pct' => $profitPctBySlug[$slug]['groi_pct'] ?? null,
                    'gpft_pct' => $profitPctBySlug[$slug]['gpft_pct'] ?? null,
                    'import_status' => (string) ($n['import_status'] ?? ''),
                    'shopify_order_id' => (string) ($n['shopify_order_id'] ?? ''),
                    'ch_orders_link' => $meta['ch_orders_link'],
                    'orders_url' => $ordersUrl,
                    'order_url' => route('marketplace.orders.show', [
                        'marketplace' => $slug,
                        'order' => $showId,
                    ]),
                ];
            }
        }

        usort($rows, static function (array $a, array $b) use ($sortByUpdatedAt): int {
            $ak = $sortByUpdatedAt
                ? ((string) ($a['updated_at'] ?? $a['order_date'] ?? ''))
                : ((string) ($a['order_date'] ?? ''));
            $bk = $sortByUpdatedAt
                ? ((string) ($b['updated_at'] ?? $b['order_date'] ?? ''))
                : ((string) ($b['order_date'] ?? ''));

            return strcmp($bk, $ak);
        });

        $rows = $this->attachInvToOrderRows(array_values($rows));
        $rows = $this->attachShippingMasterLabelToOrderRows($rows);
        $rows = $this->attachShopifyTrackingToOrderRows($rows);

        return $this->attachShipmentStatusToOrderRows($rows);
    }

    /**
     * Fill tracking_number / tracking_company / shopify_order_id from shopify_raw_orders
     * (match by Shopify id or Amazon Amz{order} order_number) even before carrier status exists.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachShopifyTrackingToOrderRows(array $rows): array
    {
        if ($rows === [] || ! Schema::hasTable('shopify_raw_orders')) {
            return $rows;
        }

        $shopifyIds = [];
        $orderNumbers = [];
        foreach ($rows as $row) {
            $sid = trim((string) ($row['shopify_order_id'] ?? ''));
            if ($sid !== '' && ctype_digit($sid)) {
                $shopifyIds[$sid] = true;
            }
            foreach ([(string) ($row['order_number'] ?? ''), (string) ($row['order_id'] ?? ''), (string) ($row['order_id_api'] ?? '')] as $on) {
                $on = trim($on);
                if ($on === '') {
                    continue;
                }
                $orderNumbers[$on] = true;
                if (! str_starts_with($on, 'Amz') && preg_match('/^\d{3}-\d+-\d+$/', $on) === 1) {
                    $orderNumbers['Amz'.$on] = true;
                }
            }
        }

        if ($shopifyIds === [] && $orderNumbers === []) {
            return $rows;
        }

        $byShopifyId = [];
        $byOrderNumber = [];

        try {
            $query = DB::table('shopify_raw_orders')
                ->select(['order_id', 'order_number', 'tracking_number', 'tracking_company', 'tracking_url', 'shipment_status', 'shipment_status_detail', 'fulfillment_status']);

            $query->where(function ($q) use ($shopifyIds, $orderNumbers) {
                if ($shopifyIds !== []) {
                    $q->orWhereIn('order_id', array_map('intval', array_keys($shopifyIds)));
                }
                if ($orderNumbers !== []) {
                    $q->orWhereIn('order_number', array_keys($orderNumbers));
                }
            });

            foreach ($query->get() as $srow) {
                $payload = [
                    'shopify_order_id' => (string) ($srow->order_id ?? ''),
                    'tracking_number' => trim((string) ($srow->tracking_number ?? '')),
                    'tracking_company' => trim((string) ($srow->tracking_company ?? '')),
                    'tracking_url' => trim((string) ($srow->tracking_url ?? '')),
                    'shipment_status' => trim((string) ($srow->shipment_status ?? '')),
                    'shipment_status_detail' => $srow->shipment_status_detail ?? null,
                    'fulfillment_status' => $srow->fulfillment_status ?? null,
                ];
                $oid = trim((string) ($srow->order_id ?? ''));
                if ($oid !== '') {
                    $byShopifyId[$oid] = $payload;
                }
                $onum = trim((string) ($srow->order_number ?? ''));
                if ($onum !== '') {
                    $byOrderNumber[$onum] = $payload;
                }
            }
        } catch (\Throwable) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $match = null;
            $sid = trim((string) ($row['shopify_order_id'] ?? ''));
            if ($sid !== '' && isset($byShopifyId[$sid])) {
                $match = $byShopifyId[$sid];
            }
            if ($match === null) {
                foreach ([(string) ($row['order_number'] ?? ''), (string) ($row['order_id'] ?? ''), (string) ($row['order_id_api'] ?? '')] as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate === '') {
                        continue;
                    }
                    if (isset($byOrderNumber[$candidate])) {
                        $match = $byOrderNumber[$candidate];
                        break;
                    }
                    $amz = str_starts_with($candidate, 'Amz') ? $candidate : 'Amz'.$candidate;
                    if (isset($byOrderNumber[$amz])) {
                        $match = $byOrderNumber[$amz];
                        break;
                    }
                }
            }
            if ($match === null) {
                continue;
            }

            if (empty($row['shopify_order_id']) && $match['shopify_order_id'] !== '') {
                $row['shopify_order_id'] = $match['shopify_order_id'];
            }
            if (empty($row['tracking_number']) && $match['tracking_number'] !== '') {
                $row['tracking_number'] = $match['tracking_number'];
            }
            if ($match['tracking_company'] !== '') {
                $row['tracking_company'] = $match['tracking_company'];
            }
            if ($match['tracking_url'] !== '') {
                $row['tracking_url'] = $match['tracking_url'];
            }
            if (! empty($match['shipment_status']) && empty($row['shipment_status'])) {
                $row['shipment_status'] = $match['shipment_status'];
                $row['shipment_status_detail'] = $match['shipment_status_detail'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Label Created / No Scan rows (cached per request) with carrier shipment_status attached.
     *
     * @return list<array<string, mixed>>
     */
    protected function labelCreatedOrderRows(): array
    {
        if ($this->cachedLabelCreatedRows !== null) {
            return $this->cachedLabelCreatedRows;
        }

        $this->cachedLabelCreatedRows = $this->collectOrderRows(
            fn (string $slug) => $this->scopedToLast30Days($this->fulfilledOrdersQuery($slug), $slug),
            true
        );

        return $this->cachedLabelCreatedRows;
    }

    protected function carrierStatusHasLeftLabelCreated(?string $shipmentStatus): bool
    {
        return in_array((string) $shipmentStatus, [
            ShipmentTrackingService::STATUS_IN_TRANSIT,
            ShipmentTrackingService::STATUS_OUT_FOR_DELIV,
            ShipmentTrackingService::STATUS_PICKUP,
            ShipmentTrackingService::STATUS_DELIVERED,
        ], true);
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  list<array<string, mixed>>  $extra
     * @return list<array<string, mixed>>
     */
    protected function mergeOrderRowsById(array $base, array $extra): array
    {
        $byId = [];
        foreach ($base as $row) {
            $byId[(string) ($row['id'] ?? '')] = $row;
        }
        foreach ($extra as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '' || isset($byId[$id])) {
                continue;
            }
            $byId[$id] = $row;
        }

        return array_values($byId);
    }

    /**
     * Overlay carrier shipment_status from shopify_raw_orders onto order rows
     * (match by tracking number, Shopify order id, and/or Shopify order_number) and update status_label.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachShipmentStatusToOrderRows(array $rows): array
    {
        if ($rows === [] || ! Schema::hasTable('shopify_raw_orders')) {
            return $rows;
        }

        $trackingKeys = [];
        $shopifyIds = [];
        $orderNumbers = [];
        foreach ($rows as $row) {
            $tn = strtoupper(preg_replace('/\s+/', '', (string) ($row['tracking_number'] ?? '')) ?? '');
            // Ignore non-carrier values (e.g. eBay fulfillment href ids).
            if ($tn !== '' && $this->looksLikeCarrierTrackingNumber($tn)) {
                $trackingKeys[$tn] = true;
            }
            $sid = trim((string) ($row['shopify_order_id'] ?? ''));
            if ($sid !== '' && ctype_digit($sid)) {
                $shopifyIds[$sid] = true;
            }
            // Also try numeric marketplace order id when it looks like a Shopify id.
            $oid = trim((string) ($row['order_id_api'] ?? $row['order_id'] ?? ''));
            if ($oid !== '' && ctype_digit($oid) && strlen($oid) >= 10) {
                $shopifyIds[$oid] = true;
            }
            foreach ([(string) ($row['order_number'] ?? ''), (string) ($row['order_id'] ?? ''), (string) ($row['order_id_api'] ?? '')] as $on) {
                $on = trim($on);
                if ($on === '') {
                    continue;
                }
                $orderNumbers[$on] = true;
                // Amazon → Shopify order names are stored as Amz{amazon_order_id}.
                if (! str_starts_with($on, 'Amz') && preg_match('/^\d{3}-\d+-\d+$/', $on) === 1) {
                    $orderNumbers['Amz'.$on] = true;
                }
            }
        }

        if ($trackingKeys === [] && $shopifyIds === [] && $orderNumbers === []) {
            return $rows;
        }

        $byTracking = [];
        $byShopifyId = [];
        $byOrderNumber = [];

        try {
            $query = DB::table('shopify_raw_orders')
                ->select(['tracking_number', 'order_id', 'order_number', 'shipment_status', 'shipment_status_detail', 'tracking_company'])
                ->whereNotNull('shipment_status')
                ->where('shipment_status', '!=', '');

            $query->where(function ($q) use ($trackingKeys, $shopifyIds, $orderNumbers) {
                if ($trackingKeys !== []) {
                    $q->orWhereIn('tracking_number', array_keys($trackingKeys));
                }
                if ($shopifyIds !== []) {
                    $q->orWhereIn('order_id', array_map('intval', array_keys($shopifyIds)));
                }
                if ($orderNumbers !== []) {
                    $q->orWhereIn('order_number', array_keys($orderNumbers));
                }
            });

            foreach ($query->get() as $srow) {
                $status = trim((string) ($srow->shipment_status ?? ''));
                if ($status === '') {
                    continue;
                }
                $payload = [
                    'shipment_status' => $status,
                    'shipment_status_detail' => $srow->shipment_status_detail ?? null,
                    'tracking_company' => $srow->tracking_company ?? null,
                    'tracking_number' => $srow->tracking_number ?? null,
                    'shopify_order_id' => $srow->order_id ?? null,
                ];
                $tn = strtoupper(preg_replace('/\s+/', '', (string) ($srow->tracking_number ?? '')) ?? '');
                if ($tn !== '') {
                    $byTracking[$tn] = $payload;
                }
                $oid = trim((string) ($srow->order_id ?? ''));
                if ($oid !== '') {
                    $byShopifyId[$oid] = $payload;
                }
                $onum = trim((string) ($srow->order_number ?? ''));
                if ($onum !== '') {
                    $byOrderNumber[$onum] = $payload;
                }
            }
        } catch (\Throwable) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $match = null;
            $tn = strtoupper(preg_replace('/\s+/', '', (string) ($row['tracking_number'] ?? '')) ?? '');
            if ($tn !== '' && $this->looksLikeCarrierTrackingNumber($tn) && isset($byTracking[$tn])) {
                $match = $byTracking[$tn];
            }
            if ($match === null) {
                foreach ([(string) ($row['shopify_order_id'] ?? ''), (string) ($row['order_id_api'] ?? ''), (string) ($row['order_id'] ?? '')] as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate !== '' && isset($byShopifyId[$candidate])) {
                        $match = $byShopifyId[$candidate];
                        break;
                    }
                }
            }
            if ($match === null) {
                foreach ([(string) ($row['order_number'] ?? ''), (string) ($row['order_id'] ?? ''), (string) ($row['order_id_api'] ?? '')] as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate === '') {
                        continue;
                    }
                    if (isset($byOrderNumber[$candidate])) {
                        $match = $byOrderNumber[$candidate];
                        break;
                    }
                    $amz = str_starts_with($candidate, 'Amz') ? $candidate : 'Amz'.$candidate;
                    if (isset($byOrderNumber[$amz])) {
                        $match = $byOrderNumber[$amz];
                        break;
                    }
                }
            }
            if ($match === null) {
                $row['shipment_status'] = null;
                $row['shipment_status_detail'] = null;

                continue;
            }

            $row['shipment_status'] = $match['shipment_status'];
            $row['shipment_status_detail'] = $match['shipment_status_detail'];
            if (empty($row['tracking_number']) && ! empty($match['tracking_number'])) {
                $row['tracking_number'] = $match['tracking_number'];
            }
            if (empty($row['shopify_order_id']) && ! empty($match['shopify_order_id'])) {
                $row['shopify_order_id'] = (string) $match['shopify_order_id'];
            }

            $carrierLabel = $this->carrierShipmentStatusLabel((string) $match['shipment_status']);
            if ($carrierLabel !== null) {
                $row['status_label'] = $carrierLabel;
            }
        }
        unset($row);

        return $rows;
    }

    protected function looksLikeCarrierTrackingNumber(string $value): bool
    {
        $v = strtoupper(preg_replace('/\s+/', '', $value) ?? '');
        if ($v === '' || strlen($v) < 8) {
            return false;
        }

        // UPS
        if (preg_match('/^1Z[A-Z0-9]{16}$/', $v) === 1) {
            return true;
        }
        // USPS / common numeric express
        if (preg_match('/^\d{12,22}$/', $v) === 1) {
            return true;
        }
        // FedEx-ish
        if (preg_match('/^\d{12,15}$/', $v) === 1) {
            return true;
        }
        // International / other alphanumeric tracking (exclude pure short numeric ids)
        if (preg_match('/^[A-Z0-9]{10,30}$/', $v) === 1 && preg_match('/[A-Z]/', $v) === 1) {
            return true;
        }

        return false;
    }

    protected function carrierShipmentStatusLabel(string $shipmentStatus): ?string
    {
        return match ($shipmentStatus) {
            ShipmentTrackingService::STATUS_DELIVERED => 'Delivered',
            ShipmentTrackingService::STATUS_IN_TRANSIT => 'In Transit',
            ShipmentTrackingService::STATUS_OUT_FOR_DELIV => 'Out for Delivery',
            ShipmentTrackingService::STATUS_PICKUP => 'Available for Pickup',
            ShipmentTrackingService::STATUS_INFO_RECEIVED => 'Label Created / No Scan',
            ShipmentTrackingService::STATUS_EXCEPTION => 'Exception',
            ShipmentTrackingService::STATUS_FAILED => 'Delivery Failure',
            default => null,
        };
    }

    /**
     * Attach Shopify on-hand INV (shopify_skus.inv) by SKU — same source as marketplace INV columns.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachInvToOrderRows(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $skus[$sku] = true;
            }
        }

        if ($skus === []) {
            return $rows;
        }

        $shopifyBySku = ShopifySku::mapByProductSkus(array_keys($skus));

        foreach ($rows as &$row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                $row['INV'] = 0;
                $row['sku_image'] = $row['sku_image'] ?? null;
                continue;
            }
            $shopify = $shopifyBySku->get($sku);
            $row['INV'] = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            if (empty($row['sku_image']) && $shopify) {
                $row['sku_image'] = $this->resolveSkuImageUrl($shopify->image_src ?? null);
            }
        }
        unset($row);

        return $rows;
    }

    protected function resolveSkuImageUrl(mixed $url): ?string
    {
        $url = trim((string) ($url ?? ''));
        if ($url === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }
        if (str_starts_with($url, '/')) {
            return url($url);
        }

        return asset($url);
    }

    /**
     * Attach Shipping Master label type + qty + dimensions (product_master.Values) by SKU.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachShippingMasterLabelToOrderRows(array $rows): array
    {
        if ($rows === [] || ! Schema::hasTable('product_master')) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $skus[$sku] = true;
            }
        }
        if ($skus === []) {
            return $rows;
        }

        $shippingByNorm = [];
        $allowed = ['ENV', 'STD', 'O-Size', 'Pallet'];

        $scalar = static function (array $values, string $key) {
            if (! array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
                return null;
            }
            $v = $values[$key];
            if (is_numeric($v)) {
                return 0 + $v;
            }

            return trim((string) $v);
        };

        try {
            ProductMaster::query()
                ->whereIn('sku', array_keys($skus))
                ->get(['sku', 'Values', 'main_image', 'main_image_brand', 'image1'])
                ->each(function ($product) use (&$shippingByNorm, $allowed, $scalar) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($product->sku ?? ''));
                    if ($norm === '' || isset($shippingByNorm[$norm])) {
                        return;
                    }
                    $values = is_array($product->Values)
                        ? $product->Values
                        : (is_string($product->Values) ? (json_decode($product->Values, true) ?: []) : []);
                    $labelType = isset($values['label_type']) ? trim((string) $values['label_type']) : '';
                    if ($labelType === '') {
                        $labelType = 'STD';
                    }
                    if (! in_array($labelType, $allowed, true)) {
                        // keep custom value as-is (matches previous behavior)
                    }
                    $image = $this->resolveSkuImageUrl($product->main_image ?? null)
                        ?? $this->resolveSkuImageUrl($product->main_image_brand ?? null)
                        ?? $this->resolveSkuImageUrl($product->image1 ?? null);
                    $shippingByNorm[$norm] = [
                        'label' => $labelType,
                        'label_qty' => $scalar($values, 'label_qty'),
                        'wt_act' => $scalar($values, 'wt_act'),
                        'l' => $scalar($values, 'l'),
                        'w' => $scalar($values, 'w'),
                        'h' => $scalar($values, 'h'),
                        'wt_decl' => $scalar($values, 'wt_decl'),
                        'l_decl' => $scalar($values, 'l_decl'),
                        'w_decl' => $scalar($values, 'w_decl'),
                        'h_decl' => $scalar($values, 'h_decl'),
                        'sku_image' => $image,
                    ];
                });
        } catch (\Throwable) {
            // leave shipping fields null on failure
        }

        foreach ($rows as &$row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $empty = [
                'label' => null,
                'label_qty' => null,
                'wt_act' => null,
                'l' => null,
                'w' => null,
                'h' => null,
                'wt_decl' => null,
                'l_decl' => null,
                'w_decl' => null,
                'h_decl' => null,
                'sku_image' => null,
            ];
            if ($sku === '') {
                foreach ($empty as $k => $v) {
                    if ($k === 'sku_image' && ! empty($row['sku_image'])) {
                        continue;
                    }
                    $row[$k] = $v;
                }
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            $ship = ($norm !== '' && isset($shippingByNorm[$norm])) ? $shippingByNorm[$norm] : $empty;
            foreach ($ship as $k => $v) {
                if ($k === 'sku_image') {
                    // Prefer Shopify image already attached; fall back to product_master.
                    if (empty($row['sku_image'])) {
                        $row['sku_image'] = $v;
                    }
                    continue;
                }
                $row[$k] = $v;
            }
        }
        unset($row);

        return $rows;
    }

    protected function formatOrderDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return trim((string) $value) !== '' ? trim((string) $value) : null;
        }
    }

    /**
     * Best-effort tracking number from stored marketplace order payload.
     */
    protected function extractTrackingNumber(string $slug, mixed $rawPayload): ?string
    {
        if (is_string($rawPayload)) {
            $decoded = json_decode($rawPayload, true);
            $rawPayload = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($rawPayload)) {
            return null;
        }

        $order = $rawPayload['order'] ?? $rawPayload;
        if (! is_array($order)) {
            return null;
        }

        $scalarKeys = [
            'shipping_code',
            'tracking_number',
            'trackingNumber',
            'TrackingNumber',
            'logistics_no',
            'tracking',
            'waybillNo',
            'waybill_no',
            'expressNo',
            'express_no',
            'billNo',
        ];

        foreach ($scalarKeys as $key) {
            if (! empty($order[$key]) && is_scalar($order[$key])) {
                $val = trim((string) $order[$key]);
                if ($val !== '') {
                    return $val;
                }
            }
        }

        foreach (['shipment', 'shipping', 'fulfillment', 'packageInfo'] as $nestedKey) {
            $nested = $order[$nestedKey] ?? null;
            if (! is_array($nested)) {
                continue;
            }
            foreach (['tracking_number', 'trackingNumber', 'TrackingNumber', 'tracking', 'shipping_code', 'logistics_no', 'number'] as $key) {
                if (! empty($nested[$key]) && is_scalar($nested[$key])) {
                    $val = trim((string) $nested[$key]);
                    if ($val !== '') {
                        return $val;
                    }
                }
            }
        }

        // Newegg package list
        foreach (['PackageInfoList', 'packageInfoList'] as $pkgKey) {
            $packages = $order[$pkgKey] ?? null;
            if (! is_array($packages)) {
                continue;
            }
            foreach ($packages as $pkg) {
                if (! is_array($pkg)) {
                    continue;
                }
                foreach (['TrackingNumber', 'tracking_number', 'tracking'] as $key) {
                    if (! empty($pkg[$key]) && is_scalar($pkg[$key])) {
                        $val = trim((string) $pkg[$key]);
                        if ($val !== '') {
                            return $val;
                        }
                    }
                }
            }
        }

        // Reverb / carrier tracking URLs
        $href = $order['_links']['web_tracking']['href'] ?? null;
        if (is_string($href) && $href !== '') {
            foreach ([
                '/tracknumbers=([A-Za-z0-9]+)/i',
                '/qtc_tLabels1=([A-Za-z0-9]+)/i',
                '/tracknum=([A-Za-z0-9]+)/i',
                '/tracking_numbers?=([A-Za-z0-9]+)/i',
            ] as $pattern) {
                if (preg_match($pattern, $href, $m) === 1) {
                    return $m[1];
                }
            }
        }

        return null;
    }

    protected function orderStatusLabel(string $slug, string $status): string
    {
        $upper = strtoupper($status);
        $lower = strtolower($status);

        return match (true) {
            in_array($slug, ['ebay1', 'ebay2', 'ebay3'], true) && $upper === 'NOT_STARTED' => 'Pending',
            in_array($slug, ['ebay1', 'ebay2', 'ebay3'], true) && $upper === 'FULFILLED' => 'Label Created',
            $slug === 'amazon' && $upper === 'UNSHIPPED' => 'Pending',
            $slug === 'amazon' && in_array($upper, ['SHIPPED', 'PARTIALLYSHIPPED'], true) => 'Label Created',
            $slug === 'newegg' && ((string) $status === '0') => 'Pending',
            $slug === 'newegg' && ((string) $status === '3') => 'Invoiced',
            $slug === 'reverb' && $lower === 'paid' => 'Pending',
            $slug === 'reverb' && $lower === 'shipped' => 'Shipped',
            $slug === 'reverb' && $lower === 'received' => 'Received',
            $slug === 'shein' && (
                $lower === 'to be shipped'
                || $lower === 'to be shipped by shein'
                || $lower === 'pending'
            ) => 'Pending',
            $slug === 'shein' && $lower === 'received' => 'Received',
            $slug === 'temu' && $upper === 'UN_SHIPPING' => 'Pending',
            $slug === 'temu' && in_array($upper, ['SHIPPED', 'PARTIALLY_SHIPPED'], true) => 'Label Created',
            $slug === 'temu' && in_array($upper, ['DELIVERED', 'PARTIALLY_DELIVERED'], true) => 'Delivered',
            in_array($slug, ['aliexpress', 'alibaba'], true)
                && str_replace([' ', '-'], '_', $upper) === 'WAIT_SELLER_SEND_GOODS' => 'Pending',
            $slug === 'aliexpress' && $upper === 'WAIT_BUYER_ACCEPT_GOODS' => 'Shipped',
            $slug === 'alibaba' && $upper === 'WAIT_BUYER_ACCEPT_GOODS' => 'Shipped',
            $slug === 'faire' && $upper === 'DELIVERED' => 'Delivered',
            $slug === 'wayfair' && $lower === 'open' => 'Pending',
            in_array($slug, ['bestbuy', 'macy'], true)
                && in_array(str_replace([' ', '-'], '_', $upper), ['AWAITING_SHIPMENT', 'SHIPPING'], true) => 'Pending',
            $slug === 'purchasingpower' && str_replace([' ', '-'], '_', $upper) === 'SHIPPING' => 'In Transit',
            $slug === 'purchasingpower'
                && (
                    str_replace([' ', '-'], '_', $upper) === 'TO_COLLECT'
                    || str_contains($lower, 'awaiting shipment')
                ) => 'Pending',
            $slug === 'doba' && str_replace([' ', '-'], '_', $upper) === 'UNSHIPPED' => 'Pending',
            $slug === 'doba' && in_array(str_replace([' ', '-'], '_', $upper), ['IN_TRANSIT', 'INTRANSIT'], true) => 'In Transit',
            default => $status !== '' ? str_replace('_', ' ', $status) : '—',
        };
    }

    /**
     * Save / clear a top badge link (GOFO, VEEQO, Shopify, Others).
     */
    public function saveBadgeLink(Request $request): JsonResponse
    {
        if (! Schema::hasTable('sales_order_fulfillment_badge_links')) {
            return response()->json([
                'success' => false,
                'message' => 'Run migrations: php artisan migrate',
            ], 422);
        }

        $validated = $request->validate([
            'badge_key' => 'required|string|in:'.implode(',', self::TOP_BADGE_KEYS),
            'link' => 'nullable|string|max:1000',
        ]);

        $link = trim((string) ($validated['link'] ?? ''));
        if ($link !== '') {
            if (! preg_match('#^(https?://|/|#)#i', $link)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enter a valid URL (https://… or a site path starting with /).',
                ], 422);
            }
        } else {
            $link = null;
        }

        $row = SalesOrderFulfillmentBadgeLink::query()->updateOrCreate(
            ['badge_key' => $validated['badge_key']],
            ['link' => $link]
        );

        return response()->json([
            'success' => true,
            'message' => $link ? 'Link saved.' : 'Link cleared.',
            'badge_key' => $row->badge_key,
            'link' => $row->link,
        ]);
    }

    /**
     * Pull tracking numbers from Shopify fulfillments into shopify_raw_orders and return the data.
     */
    public function pullTrackingNumbers(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->input('limit', 40)));

        try {
            $result = $this->backfillShopifyTrackingForLabelCreated($limit, true);

            $withTracking = (int) ($result['with_tracking'] ?? 0);
            $updated = (int) ($result['updated'] ?? 0);
            $checked = (int) ($result['checked'] ?? 0);
            $empty = max(0, $checked - $withTracking);

            $message = "Checked {$checked} Shopify order(s). Found tracking on {$withTracking}, saved {$updated}.";
            if ($withTracking === 0 && $checked > 0) {
                $message .= ' Shopify fulfillments had empty tracking (common for Amazon “Other” fulfills). Add the number in Shopify, then pull again.';
            } elseif ($empty > 0) {
                $message .= " {$empty} order(s) still had no tracking number in Shopify.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'summary' => [
                    'checked' => $checked,
                    'with_tracking' => $withTracking,
                    'updated' => $updated,
                    'empty' => $empty,
                ],
                'data' => $result['rows'] ?? [],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to pull tracking numbers: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Manually refresh open shipment statuses via USPS / UPS / 17TRACK (sync, no queue).
     * Preferentially backfills + syncs tracking for Label Created / No Scan marketplace orders.
     */
    public function refreshShipmentStatus(Request $request, ShipmentTrackingService $tracking): JsonResponse
    {
        if (! $tracking->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'No tracking provider configured. Add USPS / UPS credentials or TRACKING_API_KEY in .env.',
            ], 422);
        }

        // Keep manual refresh modest so it cannot burn the whole USPS hourly quota.
        $limit = max(1, min(120, (int) $request->input('limit', 80)));
        $stale = max(0, (int) $request->input('stale', 30));
        $carrier = strtoupper(trim((string) $request->input('carrier', '')));
        $repairQuota = filter_var($request->input('repair_quota', true), FILTER_VALIDATE_BOOL);

        try {
            $backfill = $this->backfillShopifyTrackingForLabelCreated($limit);
            $targeted = $this->syncLabelCreatedShipmentStatuses($tracking, $limit);

            $params = [
                '--only-open' => true,
                '--stale' => $stale,
                '--limit' => $limit,
            ];
            if ($carrier !== '') {
                $params['--carrier'] = $carrier;
            }
            if ($repairQuota) {
                $params['--repair-quota'] = true;
            }

            $exit = Artisan::call('tracking:sync-status', $params);
            $output = trim(Artisan::output());

            $movedHint = (int) ($targeted['updated'] ?? 0);
            $message = $exit === 0
                ? "Shipment status updated. Label Created packages synced: {$movedHint}."
                    .' Shopify tracking backfilled: '.(int) ($backfill['updated'] ?? 0).'.'
                    .' Open trackings continue on the paced schedule (~1–2×/day until Delivered).'
                : 'Shipment status refresh finished with errors.';

            if ($exit === 0 && $movedHint === 0 && (int) ($backfill['with_tracking'] ?? 0) === 0) {
                $message .= ' Most Label Created rows have no carrier tracking number in Shopify yet (Amazon fulfillments are often marked shipped with empty tracking), so their status cannot change until tracking is added.';
            }

            return response()->json([
                'success' => $exit === 0,
                'message' => $message,
                'output' => $output,
                'label_created' => [
                    'backfill' => $backfill,
                    'targeted_sync' => $targeted,
                ],
                'providers' => [
                    'usps' => $tracking->hasUsps(),
                    'ups' => $tracking->hasUps(),
                    'fedex' => $tracking->hasFedex(),
                    'gofo' => $tracking->hasGofo(),
                    '17track' => $tracking->has17Track(),
                ],
            ], $exit === 0 ? 200 : 500);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh shipment status: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pull latest fulfillment tracking from Shopify Admin API into shopify_raw_orders
     * for Label Created / No Scan linked orders (and Amazon Amz* Shopify orders).
     *
     * @return array{checked:int,updated:int,with_tracking:int,rows:list<array<string,mixed>>}
     */
    protected function backfillShopifyTrackingForLabelCreated(int $limit, bool $includeSamples = false): array
    {
        $orderIds = $this->labelCreatedShopifyOrderIdsForPull($limit);
        if ($orderIds === [] || ! Schema::hasTable('shopify_raw_orders')) {
            return ['checked' => 0, 'updated' => 0, 'with_tracking' => 0, 'rows' => []];
        }

        $store = preg_replace('#^https?://#i', '', trim((string) config('shopify.store_url', env('SHOPIFY_STORE_URL', ''))));
        $store = rtrim((string) $store, '/');
        $token = trim((string) config('shopify.access_token', env('SHOPIFY_ACCESS_TOKEN', '')));
        $apiVersion = trim((string) (config('shopify.api_version') ?: '2024-10'));
        if ($store === '' || $token === '') {
            return ['checked' => 0, 'updated' => 0, 'with_tracking' => 0, 'rows' => []];
        }

        $checked = 0;
        $updated = 0;
        $withTracking = 0;
        $samples = [];

        foreach ($orderIds as $orderId) {
            $checked++;
            try {
                $resp = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(25)->get("https://{$store}/admin/api/{$apiVersion}/orders/{$orderId}.json");
                if (! $resp->successful()) {
                    if ($includeSamples) {
                        $samples[] = [
                            'shopify_order_id' => (string) $orderId,
                            'order_number' => '',
                            'tracking_number' => null,
                            'tracking_company' => null,
                            'fulfillment_status' => null,
                            'shipment_status' => null,
                            'updated' => false,
                            'note' => 'Shopify API HTTP '.$resp->status(),
                        ];
                    }
                    continue;
                }
                $order = $resp->json('order') ?? [];
                $fulfillments = is_array($order['fulfillments'] ?? null) ? $order['fulfillments'] : [];
                $fulfillment = [];
                foreach ($fulfillments as $f) {
                    if (! is_array($f)) {
                        continue;
                    }
                    $tnProbe = '';
                    if (! empty($f['tracking_numbers']) && is_array($f['tracking_numbers'])) {
                        $tnProbe = trim((string) ($f['tracking_numbers'][0] ?? ''));
                    } elseif (! empty($f['tracking_number'])) {
                        $tnProbe = trim((string) $f['tracking_number']);
                    }
                    if ($tnProbe !== '') {
                        $fulfillment = $f;
                        break;
                    }
                    if ($fulfillment === []) {
                        $fulfillment = $f;
                    }
                }
                $trackingNumber = null;
                if (! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
                    $parts = array_values(array_filter(array_map(
                        static fn ($v) => trim((string) $v),
                        $fulfillment['tracking_numbers']
                    )));
                    $trackingNumber = $parts !== [] ? implode(', ', $parts) : null;
                } elseif (! empty($fulfillment['tracking_number'])) {
                    $trackingNumber = trim((string) $fulfillment['tracking_number']) ?: null;
                }
                $trackingCompany = isset($fulfillment['tracking_company'])
                    ? trim((string) $fulfillment['tracking_company'])
                    : null;
                if ($trackingCompany === '') {
                    $trackingCompany = null;
                }
                $trackingUrl = null;
                if (! empty($fulfillment['tracking_urls']) && is_array($fulfillment['tracking_urls'])) {
                    $trackingUrl = $fulfillment['tracking_urls'][0] ?? null;
                } elseif (! empty($fulfillment['tracking_url'])) {
                    $trackingUrl = $fulfillment['tracking_url'];
                }

                $payload = [
                    'fulfillment_status' => $order['fulfillment_status'] ?? null,
                    'updated_at' => now(),
                ];
                if ($trackingNumber !== null) {
                    $payload['tracking_number'] = $trackingNumber;
                    $withTracking++;
                }
                if ($trackingCompany !== null) {
                    $payload['tracking_company'] = $trackingCompany;
                }
                if (is_string($trackingUrl) && $trackingUrl !== '') {
                    $payload['tracking_url'] = $trackingUrl;
                }

                $affected = 0;
                if ($trackingNumber !== null) {
                    $affected = DB::table('shopify_raw_orders')
                        ->where('order_id', $orderId)
                        ->update($payload);
                    if ($affected > 0) {
                        $updated += $affected;
                    }
                } else {
                    // Still refresh fulfillment_status / carrier label when empty.
                    DB::table('shopify_raw_orders')
                        ->where('order_id', $orderId)
                        ->update($payload);
                }

                $localStatus = DB::table('shopify_raw_orders')
                    ->where('order_id', $orderId)
                    ->value('shipment_status');

                if ($includeSamples) {
                    $samples[] = [
                        'shopify_order_id' => (string) $orderId,
                        'order_number' => (string) ($order['name'] ?? ''),
                        'tracking_number' => $trackingNumber,
                        'tracking_company' => $trackingCompany,
                        'fulfillment_status' => $order['fulfillment_status'] ?? null,
                        'shipment_status' => $localStatus ? (string) $localStatus : null,
                        'updated' => $affected > 0 && $trackingNumber !== null,
                        'note' => $trackingNumber !== null
                            ? ($affected > 0 ? 'Saved' : 'Unchanged')
                            : 'No tracking on Shopify fulfillment',
                    ];
                }
            } catch (\Throwable $e) {
                if ($includeSamples) {
                    $samples[] = [
                        'shopify_order_id' => (string) $orderId,
                        'order_number' => '',
                        'tracking_number' => null,
                        'tracking_company' => null,
                        'fulfillment_status' => null,
                        'shipment_status' => null,
                        'updated' => false,
                        'note' => 'Error: '.$e->getMessage(),
                    ];
                }
                continue;
            }
        }

        return [
            'checked' => $checked,
            'updated' => $updated,
            'with_tracking' => $withTracking,
            'rows' => $samples,
        ];
    }

    /**
     * Shopify order ids to pull for Label Created (eBay/Temu + Amazon Amz*), prefer missing tracking.
     *
     * @return list<int>
     */
    protected function labelCreatedShopifyOrderIdsForPull(int $limit): array
    {
        $ids = $this->labelCreatedShopifyOrderIds($limit);

        // Add Amazon-linked Shopify orders missing tracking.
        if (Schema::hasTable('shopify_raw_orders') && Schema::hasTable('amazon_orders')) {
            try {
                $amazonNumbers = $this->labelCreatedAmazonShopifyOrderNumbers(max($limit * 3, 60));
                if ($amazonNumbers !== []) {
                    $amzIds = DB::table('shopify_raw_orders')
                        ->whereIn('order_number', $amazonNumbers)
                        ->where(function ($q) {
                            $q->whereNull('tracking_number')->orWhere('tracking_number', '');
                        })
                        ->orderByDesc('order_id')
                        ->limit($limit)
                        ->pluck('order_id')
                        ->map(fn ($v) => (int) $v)
                        ->filter(fn ($v) => $v > 0)
                        ->all();
                    foreach ($amzIds as $id) {
                        $ids[] = $id;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        return array_slice($ids, 0, $limit);
    }

    /**
     * Sync carrier status for tracking numbers on Label Created-linked Shopify orders.
     *
     * @return array{checked:int,updated:int}
     */
    protected function syncLabelCreatedShipmentStatuses(ShipmentTrackingService $tracking, int $limit): array
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return ['checked' => 0, 'updated' => 0];
        }

        $orderIds = $this->labelCreatedShopifyOrderIds(max($limit * 5, 200));
        $amazonNumbers = $this->labelCreatedAmazonShopifyOrderNumbers($limit);

        if ($orderIds === [] && $amazonNumbers === []) {
            return ['checked' => 0, 'updated' => 0];
        }

        $query = DB::table('shopify_raw_orders')
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->where(function ($q) {
                $q->whereNull('shipment_status')
                    ->orWhereNotIn('shipment_status', [
                        ShipmentTrackingService::STATUS_DELIVERED,
                        ShipmentTrackingService::STATUS_EXPIRED,
                    ]);
            });

        $query->where(function ($q) use ($orderIds, $amazonNumbers) {
            if ($orderIds !== []) {
                $q->orWhereIn('order_id', $orderIds);
            }
            if ($amazonNumbers !== []) {
                $q->orWhereIn('order_number', $amazonNumbers);
            }
        });

        $rows = $query->select('tracking_number', DB::raw('MAX(tracking_company) as carrier'))
            ->groupBy('tracking_number')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return ['checked' => 0, 'updated' => 0];
        }

        $shipments = $rows->map(fn ($r) => [
            'number' => $r->tracking_number,
            'carrier' => $r->carrier,
        ])->all();

        try {
            $results = $tracking->track($shipments);
        } catch (\Throwable) {
            return ['checked' => 0, 'updated' => 0];
        }

        $updated = 0;
        $now = now();
        foreach ($rows as $r) {
            $num = $r->tracking_number;
            $res = $results[$num] ?? null;
            // Never persist quota/rate-limit noise as NotFound over a real status.
            if (! ShipmentTrackingService::isPersistableResult($res)) {
                continue;
            }
            $affected = DB::table('shopify_raw_orders')
                ->where('tracking_number', $num)
                ->update([
                    'shipment_status' => $res['status'],
                    'shipment_status_detail' => $res['detail'] ?? null,
                    'shipment_checked_at' => $now,
                    'updated_at' => $now,
                ]);
            $updated += $affected;
        }

        return ['checked' => $rows->count(), 'updated' => $updated];
    }

    /**
     * @return list<int>
     */
    protected function labelCreatedShopifyOrderIds(int $limit): array
    {
        $ids = [];
        $sources = [
            [Ebay1OrderMetric::class, "UPPER(TRIM(COALESCE(status, ''))) = ?", ['FULFILLED']],
            [Ebay2OrderMetric::class, "UPPER(TRIM(COALESCE(status, ''))) = ?", ['FULFILLED']],
            [Ebay3OrderMetric::class, "UPPER(TRIM(COALESCE(status, ''))) = ?", ['FULFILLED']],
        ];

        if (class_exists(TemuOrder::class) && Schema::hasTable((new TemuOrder)->getTable()) && Schema::hasColumn((new TemuOrder)->getTable(), 'shopify_order_id')) {
            $sources[] = [
                TemuOrder::class,
                "UPPER(TRIM(COALESCE(parent_order_status_text, order_status_text, ''))) IN (?, ?)",
                ['SHIPPED', 'PARTIALLY_SHIPPED'],
            ];
        }

        foreach ($sources as [$model, $sql, $bindings]) {
            if (! class_exists($model) || ! Schema::hasTable((new $model)->getTable())) {
                continue;
            }
            try {
                $chunk = $model::query()
                    ->whereRaw($sql, $bindings)
                    ->whereNotNull('shopify_order_id')
                    ->where('shopify_order_id', '!=', '')
                    ->orderByDesc('id')
                    ->limit($limit)
                    ->pluck('shopify_order_id')
                    ->map(fn ($v) => (int) $v)
                    ->filter(fn ($v) => $v > 0)
                    ->all();
                foreach ($chunk as $id) {
                    $ids[$id] = true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $idList = array_map('intval', array_keys($ids));
        if ($idList === []) {
            return [];
        }

        // Prefer orders still missing tracking (backfill targets).
        try {
            $missing = DB::table('shopify_raw_orders')
                ->whereIn('order_id', $idList)
                ->where(function ($q) {
                    $q->whereNull('tracking_number')->orWhere('tracking_number', '');
                })
                ->orderByDesc('order_id')
                ->limit($limit)
                ->pluck('order_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            if ($missing !== []) {
                return $missing;
            }
        } catch (\Throwable) {
            // fall through
        }

        return array_slice($idList, 0, $limit);
    }

    /**
     * @return list<string>
     */
    protected function labelCreatedAmazonShopifyOrderNumbers(int $limit): array
    {
        if (! Schema::hasTable('amazon_orders')) {
            return [];
        }

        try {
            $since = now()->subDays(30)->toDateTimeString();
            $amazonIds = DB::table('amazon_orders')
                ->whereRaw("UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)", ['SHIPPED', 'PARTIALLYSHIPPED'])
                ->where('order_date', '>=', $since)
                ->orderByDesc('id')
                ->limit(max($limit * 10, 200))
                ->pluck('amazon_order_id')
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }

        $numbers = [];
        foreach ($amazonIds as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $numbers[] = $id;
            $numbers[] = 'Amz'.$id;
        }

        return array_values(array_unique($numbers));
    }

    /**
     * @return list<array{key: string, label: string, link: ?string}>
     */
    protected function topBadgePayload(): array
    {
        $labels = [
            'gofo' => 'GOFO',
            'veeqo' => 'VEEQO',
            'shopify' => 'Shopify',
            'others' => 'Others',
        ];

        $links = [];
        if (Schema::hasTable('sales_order_fulfillment_badge_links')) {
            $links = SalesOrderFulfillmentBadgeLink::query()
                ->whereIn('badge_key', self::TOP_BADGE_KEYS)
                ->pluck('link', 'badge_key')
                ->all();
        }

        $out = [];
        foreach (self::TOP_BADGE_KEYS as $key) {
            $link = isset($links[$key]) ? trim((string) $links[$key]) : '';
            $out[] = [
                'key' => $key,
                'label' => $labels[$key],
                'link' => $link !== '' ? $link : null,
            ];
        }

        return $out;
    }

    /**
     * Save / clear the Ch Orders link for a channel_master row.
     */
    public function saveChOrdersLink(Request $request): JsonResponse
    {
        if (! Schema::hasTable('channel_master') || ! Schema::hasColumn('channel_master', 'ch_orders_link')) {
            return response()->json([
                'success' => false,
                'message' => 'Run migrations: php artisan migrate',
            ], 422);
        }

        $validated = $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'ch_orders_link' => 'nullable|string|max:1000',
        ]);

        $link = trim((string) ($validated['ch_orders_link'] ?? ''));
        if ($link !== '') {
            if (! preg_match('#^(https?://|/|#)#i', $link)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enter a valid URL (https://… or a site path starting with /).',
                ], 422);
            }
        } else {
            $link = null;
        }

        $channel = ChannelMaster::query()->findOrFail((int) $validated['channel_id']);
        $channel->ch_orders_link = $link;
        $channel->save();

        return response()->json([
            'success' => true,
            'message' => $link ? 'Ch Orders link saved.' : 'Ch Orders link cleared.',
            'ch_orders_link' => $link,
        ]);
    }

    /**
     * Pending (unfulfilled) order counts — same semantics as marketplace orders badges
     * (e.g. ebay3 NOT_STARTED → "Pending").
     *
     * @return array<string, int>
     */
    protected function pendingOrderCountsBySlug(): array
    {
        $counts = [];

        foreach (MarketplaceManagerRegistry::slugs() as $slug) {
            $counts[$slug] = $this->pendingOrderCountForSlug($slug);
        }

        return $counts;
    }

    protected function pendingOrderCountForSlug(string $slug): int
    {
        $query = $this->scopedToLast30Days($this->pendingOrdersQuery($slug), $slug);
        if ($query === null) {
            return 0;
        }

        try {
            return (int) $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Count of Label Created / No Scan orders in the last 30 days (all MM channels).
     * JSON key remains fulfilled_24h for frontend compatibility.
     */
    protected function fulfilledLast24HoursCount(): int
    {
        return $this->countAllOrders(
            fn (string $slug) => $this->scopedToLast30Days($this->fulfilledOrdersQuery($slug), $slug)
        );
    }

    /**
     * Count of Shipped/Received orders in the last 30 days.
     * JSON key remains scan_done_24h for frontend compatibility.
     */
    protected function scanDoneLast24HoursCount(): int
    {
        return $this->countAllOrders(
            fn (string $slug) => $this->scopedToLast30Days($this->scanDoneOrdersQuery($slug), $slug)
        );
    }

    /**
     * Count of In Transit orders in the last 30 days.
     */
    protected function inTransitOrdersCount(): int
    {
        return $this->countAllOrders(
            fn (string $slug) => $this->scopedToLast30Days($this->inTransitOrdersQuery($slug), $slug)
        );
    }

    /**
     * Count of In Received orders in the last 30 days.
     */
    protected function inReceivedOrdersCount(): int
    {
        return $this->countAllOrders(
            fn (string $slug) => $this->scopedToLast30Days($this->inReceivedOrdersQuery($slug), $slug)
        );
    }

    /**
     * Count of Invoiced orders in the last 30 days.
     */
    protected function invoicedOrdersCount(): int
    {
        return $this->countAllOrders(
            fn (string $slug) => $this->scopedToLast30Days($this->invoicedOrdersQuery($slug), $slug)
        );
    }

    /**
     * Count of Delivered orders in the last 30 days.
     */
    protected function deliveredOrdersCount(): int
    {
        return $this->countAllOrders(
            fn (string $slug) => $this->scopedToLast30Days($this->deliveredOrdersQuery($slug), $slug)
        );
    }

    /**
     * Count of marketplace orders in the last 30 days (All Order tab).
     */
    protected function allOrdersCount(): int
    {
        return $this->countAllOrders(
            fn (string $slug) => $this->scopedToLast30Days($this->allOrdersQuery($slug), $slug)
        );
    }

    /**
     * Apply shared order-date range filter (request date_from/date_to, default last 30 days).
     */
    protected function scopedToLast30Days(?Builder $query, string $slug): ?Builder
    {
        if ($query === null) {
            return null;
        }

        [$from, $to] = $this->resolveOrderDateRange();

        return $this->applyOrderDateRangeFilter($query, $from, $to, $slug);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveOrderDateRange(): array
    {
        $tz = 'America/Los_Angeles';
        $fromRaw = trim((string) request()->input('date_from', ''));
        $toRaw = trim((string) request()->input('date_to', ''));

        try {
            $from = $fromRaw !== ''
                ? Carbon::parse($fromRaw, $tz)->startOfDay()
                : now($tz)->subDays(30)->startOfDay();
        } catch (\Throwable) {
            $from = now($tz)->subDays(30)->startOfDay();
        }

        try {
            $to = $toRaw !== ''
                ? Carbon::parse($toRaw, $tz)->endOfDay()
                : now($tz)->endOfDay();
        } catch (\Throwable) {
            $to = now($tz)->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * Restrict by channel order-date column to [from, to] inclusive.
     */
    protected function applyOrderDateRangeFilter(Builder $query, Carbon $from, Carbon $to, string $slug): Builder
    {
        return match ($slug) {
            'amazon' => $query->whereHas('order', function (Builder $q) use ($from, $to) {
                $q->where('order_date', '>=', $from->toDateString())
                    ->where('order_date', '<=', $to->toDateString());
            }),
            'temu' => $query->where(function (Builder $q) use ($from, $to) {
                $q->where(function (Builder $q2) use ($from, $to) {
                    $q2->where('parent_order_time', '>=', $from)
                        ->where('parent_order_time', '<=', $to);
                })->orWhere(function (Builder $q2) use ($from, $to) {
                    $q2->whereNull('parent_order_time')
                        ->where('order_update_time', '>=', $from)
                        ->where('order_update_time', '<=', $to);
                });
            }),
            'bestbuy', 'macy' => $query->where(function (Builder $q) use ($from, $to) {
                $q->where(function (Builder $q2) use ($from, $to) {
                    $q2->where('order_created_at', '>=', $from)
                        ->where('order_created_at', '<=', $to);
                })->orWhere(function (Builder $q2) use ($from, $to) {
                    $q2->whereNull('order_created_at')
                        ->where('order_updated_at', '>=', $from)
                        ->where('order_updated_at', '<=', $to);
                });
            }),
            'purchasingpower' => $query->where('date_created', '>=', $from)->where('date_created', '<=', $to),
            'wayfair' => $query->where('po_date', '>=', $from)->where('po_date', '<=', $to),
            'doba' => $query->where('order_time', '>=', $from)->where('order_time', '<=', $to),
            default => $query->where(function (Builder $q) use ($from, $to) {
                $q->where(function (Builder $q2) use ($from, $to) {
                    $q2->where('order_date', '>=', $from)
                        ->where('order_date', '<=', $to);
                })->orWhere(function (Builder $q2) use ($from, $to) {
                    $q2->whereNull('order_date')
                        ->where('updated_at', '>=', $from)
                        ->where('updated_at', '<=', $to);
                });
            }),
        };
    }

    /**
     * Restrict to orders on/after $since by channel order-date column.
     */
    protected function applyLast30DaysFilter(Builder $query, mixed $since, string $slug): Builder
    {
        $from = $since instanceof Carbon
            ? $since->copy()->startOfDay()
            : Carbon::parse((string) $since)->startOfDay();
        $to = now('America/Los_Angeles')->endOfDay();

        return $this->applyOrderDateRangeFilter($query, $from, $to, $slug);
    }

    /**
     * Restrict to rows updated/touched since $since (Label Created / No Scan / Shipped-Received windows).
     */
    protected function applyUpdatedSinceFilter(Builder $query, mixed $since, string $slug): Builder
    {
        return match ($slug) {
            'amazon' => $query->whereHas('order', fn (Builder $q) => $q->where('updated_at', '>=', $since)),
            'temu' => $query->where(function (Builder $q) use ($since) {
                $q->where('order_update_time', '>=', $since)
                    ->orWhere('parent_order_time', '>=', $since);
            }),
            'bestbuy', 'macy' => $query->where(function (Builder $q) use ($since) {
                $q->where('order_updated_at', '>=', $since)
                    ->orWhere('order_created_at', '>=', $since);
            }),
            'purchasingpower' => $query->where(function (Builder $q) use ($since) {
                $q->where('updated_at', '>=', $since)->orWhere('date_created', '>=', $since);
            }),
            'wayfair' => $query->where(function (Builder $q) use ($since) {
                $q->where('updated_at', '>=', $since)->orWhere('po_date', '>=', $since);
            }),
            'doba' => $query->where(function (Builder $q) use ($since) {
                $q->where('updated_at', '>=', $since)->orWhere('order_time', '>=', $since);
            }),
            default => $query->where('updated_at', '>=', $since),
        };
    }

    protected function orderDateColumn(string $slug): ?string
    {
        return match ($slug) {
            'amazon' => null, // on related order
            'temu' => 'parent_order_time',
            'bestbuy', 'macy' => 'order_created_at',
            'purchasingpower' => 'date_created',
            'wayfair' => 'po_date',
            'doba' => 'order_time',
            default => 'order_date',
        };
    }

    protected function orderUpdatedColumn(string $slug): ?string
    {
        return match ($slug) {
            'amazon' => null,
            'temu' => 'order_update_time',
            'bestbuy', 'macy' => 'order_updated_at',
            'purchasingpower', 'wayfair', 'doba' => 'updated_at',
            default => 'updated_at',
        };
    }

    /**
     * @param  callable(string): (?Builder)  $queryForSlug
     */
    protected function countAllOrders(callable $queryForSlug): int
    {
        $total = 0;

        foreach (MarketplaceManagerRegistry::slugs() as $slug) {
            $query = $queryForSlug($slug);
            if ($query === null) {
                continue;
            }

            try {
                $total += (int) $query->count();
            } catch (\Throwable) {
                // skip channel on query failure
            }
        }

        return $total;
    }

    /**
     * @param  callable(string): (?Builder)  $queryForSlug
     */
    protected function countOrdersLast24Hours(callable $queryForSlug): int
    {
        $since = now()->subDay();
        $total = 0;

        foreach (MarketplaceManagerRegistry::slugs() as $slug) {
            $query = $queryForSlug($slug);
            if ($query === null) {
                continue;
            }

            try {
                $total += (int) $this->applyUpdatedSinceFilter($query, $since, $slug)->count();
            } catch (\Throwable) {
                // skip channel on query failure
            }
        }

        return $total;
    }

    /**
     * Label Created / No Scan rows for a marketplace
     * (excludes Shipped / Received → Shipped/Received tab, Invoiced → Invoiced, Delivered → Delivered).
     */
    protected function fulfilledOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'ebay1', 'ebay2', 'ebay3' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['FULFILLED']),
            // Shein / Ali / TopDawg / Mirakl "Shipped" → Shipped/Received tab
            'shein', 'aliexpress', 'alibaba', 'topdawg', 'bestbuy', 'macy' => null,
            'reverb' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['picked_up']),
            'amazon' => $base->whereHas('order', fn (Builder $q) => $q->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)",
                ['SHIPPED', 'PARTIALLYSHIPPED']
            )),
            'temu' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(parent_order_status_text, order_status_text, ''))) IN (?, ?)",
                ['SHIPPED', 'PARTIALLY_SHIPPED']
            ),
            // Purchasing Power SHIPPING / Doba In Transit → In Transit tab
            'purchasingpower', 'doba', 'wayfair' => null,
            default => null,
        };
    }

    /**
     * In Transit — carrier / marketplace in-transit statuses.
     */
    protected function inTransitOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'doba' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(order_status, ''))) IN (?, ?)",
                ['IN TRANSIT', 'IN_TRANSIT']
            ),
            'purchasingpower' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) = ?",
                ['SHIPPING']
            ),
            default => null,
        };
    }

    /**
     * Shipped/Received tab — status Shipped (Received → In Received tab).
     */
    protected function scanDoneOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'shein' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['shipped']),
            'reverb' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['shipped']),
            'aliexpress', 'alibaba' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) = ?",
                ['WAIT_BUYER_ACCEPT_GOODS']
            ),
            'topdawg' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['shipped']),
            'purchasingpower' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) = ?",
                ['SHIPPED']
            ),
            'bestbuy', 'macy' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['SHIPPED']),
            default => null,
        };
    }

    /**
     * In Received — status Received across MM channels.
     */
    protected function inReceivedOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'shein', 'reverb' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['received']),
            'purchasingpower' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['RECEIVED']),
            'bestbuy', 'macy' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['RECEIVED']),
            'ebay1', 'ebay2', 'ebay3', 'newegg', 'wayfair', 'amazon', 'temu', 'faire',
            'aliexpress', 'alibaba', 'topdawg', 'doba' => null,
            default => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['received']),
        };
    }

    /**
     * Invoiced — Newegg status 3 (and any literal "Invoiced" status).
     */
    protected function invoicedOrdersQuery(string $slug): ?Builder
    {
        return match ($slug) {
            // Newegg: 3 = Invoiced.
            'newegg' => Schema::hasTable('newegg_order_metrics')
                ? NeweggOrderMetric::query()->whereIn('status', ['3', 3])
                : null,
            default => null,
        };
    }

    /**
     * Delivered — delivered / completed across MM channels (Received → In Received tab).
     */
    protected function deliveredOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'faire' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['DELIVERED']),
            // Shein / Reverb / Purchasing Power Received → In Received tab
            'shein', 'reverb', 'purchasingpower' => null,
            'ebay1', 'ebay2', 'ebay3', 'newegg', 'wayfair', 'amazon' => null,
            'aliexpress', 'alibaba' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) IN (?, ?, ?)",
                ['FINISH', 'BUYER_ACCEPT_GOODS', 'TRADE_FINISHED']
            ),
            'topdawg' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['delivered']),
            'temu' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(parent_order_status_text, order_status_text, ''))) IN (?, ?)",
                ['DELIVERED', 'PARTIALLY_DELIVERED']
            ),
            'bestbuy', 'macy' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) = ?",
                ['DELIVERED']
            ),
            'doba' => $base->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) = ?", ['COMPLETED']),
            default => $base->whereRaw(
                "LOWER(TRIM(COALESCE(status, ''))) = ?",
                ['delivered']
            ),
        };
    }

    /**
     * All orders for a marketplace (no status filter).
     */
    protected function allOrdersQuery(string $slug): ?Builder
    {
        return match ($slug) {
            'ebay1' => Schema::hasTable('ebay1_order_metrics') ? Ebay1OrderMetric::query() : null,
            'ebay2' => Schema::hasTable('ebay2_order_metrics') ? Ebay2OrderMetric::query() : null,
            'ebay3' => Schema::hasTable('ebay3_order_metrics') ? Ebay3OrderMetric::query() : null,
            'shein' => Schema::hasTable('shein_order_metrics') ? SheinOrderMetric::query() : null,
            'reverb' => Schema::hasTable('reverb_order_metrics') ? ReverbOrderMetric::query() : null,
            'aliexpress' => Schema::hasTable('aliexpress_order_metrics') ? AliexpressOrderMetric::query() : null,
            'alibaba' => Schema::hasTable('alibaba_order_metrics') ? AlibabaOrderMetric::query() : null,
            'newegg' => Schema::hasTable('newegg_order_metrics') ? NeweggOrderMetric::query() : null,
            'faire' => Schema::hasTable('faire_order_metrics') ? FaireOrderMetric::query() : null,
            'topdawg' => Schema::hasTable('topdawg_order_metrics') ? TopDawgOrderMetric::query() : null,
            'amazon' => Schema::hasTable('amazon_order_items')
                ? AmazonOrderItem::query()->with('order')
                : null,
            'temu' => Schema::hasTable('temu_orders') ? TemuOrder::query() : null,
            'purchasingpower' => Schema::hasTable('purchasing_power_sales') ? PurchasingPowerSale::query() : null,
            'wayfair' => Schema::hasTable('wayfair_daily_data') ? WayfairDailyData::query() : null,
            'bestbuy' => Schema::hasTable('mirakl_daily_data') ? BestBuyOrderMetric::query() : null,
            'macy' => Schema::hasTable('mirakl_daily_data') ? MacyOrderMetric::query() : null,
            'doba' => Schema::hasTable('doba_daily_data') ? DobaDailyData::query() : null,
            default => null,
        };
    }

    /**
     * Fulfillment-pending rows for a marketplace (mirrors orders-page Pending badge).
     */
    protected function pendingOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'ebay1', 'ebay2', 'ebay3' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['NOT_STARTED']),
            'shein' => $base->whereIn('status', [
                'Pending',
                'To Be Shipped',
                'To Be Shipped by SHEIN',
            ]),
            'reverb' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['paid']),
            'aliexpress', 'alibaba' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['WAIT_SELLER_SEND_GOODS']),
            'newegg' => $base->whereIn('status', ['0', 0]),
            'faire' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)", ['PROCESSING', 'NEW']),
            'amazon' => $base->whereHas('order', fn (Builder $q) => $q->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) = ?",
                ['UNSHIPPED']
            )),
            'topdawg' => $base->whereRaw(
                "LOWER(TRIM(COALESCE(status, ''))) IN (?, ?, ?)",
                ['pending', 'processing', 'saved']
            ),
            'temu' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(parent_order_status_text, order_status_text, ''))) IN (?, ?)",
                ['UN_SHIPPING', 'PENDING']
            ),
            'purchasingpower' => $base->where(function (Builder $q) {
                // SHIPPING → In Transit tab
                $q->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['TO_COLLECT'])
                    ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) LIKE ?", ['%awaiting shipment%']);
            }),
            'wayfair' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['open']),
            'bestbuy', 'macy' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)",
                ['AWAITING_SHIPMENT', 'SHIPPING']
            ),
            'doba' => $base->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) = ?", ['UNSHIPPED']),
            default => null,
        };
    }

    /**
     * Normalize heterogeneous marketplace order models into a common field bag.
     *
     * @return array{
     *   status: string,
     *   order_date: mixed,
     *   updated_at: mixed,
     *   sku: string,
     *   display_title: string,
     *   quantity: int,
     *   amount: mixed,
     *   order_id: string,
     *   order_number: string,
     *   import_status: string,
     *   shopify_order_id: string,
     *   raw_payload: mixed,
     *   tracking_number: ?string,
     *   show_id: int
     * }
     */
    protected function normalizeOrderFields(string $slug, object $order): array
    {
        return match ($slug) {
            'amazon' => (function () use ($order) {
                $parent = $order->order ?? null;

                return [
                    'status' => (string) ($parent->status ?? ''),
                    'order_date' => $parent->order_date ?? null,
                    'updated_at' => $parent->updated_at ?? $order->updated_at ?? null,
                    'sku' => (string) ($order->sku ?? ''),
                    'display_title' => (string) ($order->title ?? ''),
                    'quantity' => (int) ($order->quantity ?? 1),
                    'amount' => is_numeric($order->price ?? null) ? (float) $order->price : ($parent->total_amount ?? null),
                    'order_id' => (string) ($parent->amazon_order_id ?? ''),
                    'order_number' => (string) ($parent->amazon_order_id ?? ''),
                    'import_status' => '',
                    'shopify_order_id' => '',
                    'raw_payload' => $parent->raw_data ?? $order->raw_data ?? null,
                    'tracking_number' => null,
                    'show_id' => (int) ($parent->id ?? $order->id),
                ];
            })(),
            'temu' => [
                'status' => (string) ($order->parent_order_status_text ?: $order->order_status_text ?: ''),
                'order_date' => $order->parent_order_time ?? null,
                'updated_at' => $order->order_update_time ?? $order->updated_at ?? null,
                'sku' => (string) ($order->display_sku ?: $order->ext_code ?: $order->product_sku_id ?: ''),
                'display_title' => (string) ($order->goods_name ?? ''),
                'quantity' => (int) ($order->quantity ?? 1),
                'amount' => $order->order_total_amount ?? $order->order_base_amount ?? null,
                'order_id' => (string) ($order->parent_order_sn ?: $order->order_sn ?: ''),
                'order_number' => (string) ($order->order_sn ?: $order->parent_order_sn ?: ''),
                'import_status' => (string) ($order->import_status ?? ''),
                'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                'raw_payload' => $order->raw_json ?? null,
                'tracking_number' => null,
                'show_id' => (int) $order->id,
            ],
            'bestbuy', 'macy' => [
                'status' => (string) ($order->status ?? ''),
                'order_date' => $order->order_created_at ?? null,
                'updated_at' => $order->order_updated_at ?? $order->updated_at ?? null,
                'sku' => (string) ($order->sku ?? ''),
                'display_title' => (string) ($order->product_title ?? ''),
                'quantity' => (int) ($order->quantity ?? 1),
                'amount' => method_exists($order, 'lineAmount') ? $order->lineAmount() : null,
                'order_id' => (string) ($order->order_id ?? ''),
                'order_number' => (string) ($order->channel_order_id ?? $order->order_id ?? ''),
                'import_status' => (string) ($order->import_status ?? ''),
                'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                'raw_payload' => $order->raw_payload ?? null,
                'tracking_number' => null,
                'show_id' => (int) $order->id,
            ],
            'purchasingpower' => [
                'status' => (string) ($order->status ?? ''),
                'order_date' => $order->date_created ?? null,
                'updated_at' => $order->updated_at ?? null,
                'sku' => (string) ($order->offer_sku ?: $order->product_sku ?: ''),
                'display_title' => (string) ($order->product_name ?? ''),
                'quantity' => (int) ($order->quantity ?? 1),
                'amount' => $order->amount ?? null,
                'order_id' => (string) ($order->order_id ?? ''),
                'order_number' => (string) ($order->order_number ?? ''),
                'import_status' => (string) ($order->import_status ?? ''),
                'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                'raw_payload' => $order->raw_payload ?? null,
                'tracking_number' => isset($order->tracking_number) ? trim((string) $order->tracking_number) : null,
                'show_id' => (int) $order->id,
            ],
            'wayfair' => [
                'status' => (string) ($order->status ?? ''),
                'order_date' => $order->po_date ?? null,
                'updated_at' => $order->updated_at ?? null,
                'sku' => (string) ($order->sku ?? ''),
                'display_title' => (string) ($order->sku ?? ''),
                'quantity' => (int) ($order->quantity ?? 1),
                'amount' => $order->total_price ?? $order->unit_price ?? null,
                'order_id' => (string) ($order->po_number ?? ''),
                'order_number' => (string) ($order->po_number ?? ''),
                'import_status' => (string) ($order->import_status ?? ''),
                'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                'raw_payload' => $order->raw_payload ?? null,
                'tracking_number' => null,
                'show_id' => (int) $order->id,
            ],
            'doba' => [
                'status' => (string) ($order->order_status ?? ''),
                'order_date' => $order->order_time ?? null,
                'updated_at' => $order->updated_at ?? null,
                'sku' => (string) ($order->sku ?? ''),
                'display_title' => (string) ($order->product_name ?? ''),
                'quantity' => (int) ($order->quantity ?? 1),
                'amount' => $order->total_price ?? $order->item_price ?? null,
                'order_id' => (string) ($order->order_no ?? ''),
                'order_number' => (string) ($order->platform_order_no ?: $order->order_no ?: ''),
                'import_status' => (string) ($order->import_status ?? ''),
                'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                'raw_payload' => $order->raw_payload ?? null,
                'tracking_number' => isset($order->tracking_number) ? trim((string) $order->tracking_number) : null,
                'show_id' => (int) $order->id,
            ],
            default => [
                'status' => (string) ($order->status ?? ''),
                'order_date' => $order->order_date ?? null,
                'updated_at' => $order->updated_at ?? null,
                'sku' => (string) ($order->sku ?? ''),
                'display_title' => (string) ($order->display_title ?? ''),
                'quantity' => (int) ($order->quantity ?? 1),
                'amount' => $order->amount ?? null,
                'order_id' => (string) ($order->order_id ?? ''),
                'order_number' => (string) ($order->order_number ?? ''),
                'import_status' => (string) ($order->import_status ?? ''),
                'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                'raw_payload' => $order->raw_payload ?? null,
                'tracking_number' => null,
                'show_id' => (int) $order->id,
            ],
        };
    }

    /**
     * Map lowercased channel_master name → enabled Marketplace Manager registry channel.
     * Same matching used on /marketplace.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function managerChannelsByMpKey(): array
    {
        $map = [];
        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            if (! ($channel['enabled'] ?? false)) {
                continue;
            }
            foreach (($channel['mp_channel_keys'] ?? []) as $candidate) {
                $key = strtolower(trim((string) $candidate));
                if ($key === '' || isset($map[$key])) {
                    continue;
                }
                $map[$key] = $channel;
            }
        }

        return $map;
    }

    /**
     * Channel GROI% / GPFT% from channel_master_calculated_data, keyed by MM slug.
     *
     * @return array<string, array{groi_pct: ?float, gpft_pct: ?float}>
     */
    protected function channelProfitPctBySlug(): array
    {
        $out = [];
        if (! Schema::hasTable('channel_master_calculated_data')) {
            return $out;
        }

        $byChannel = [];
        try {
            foreach (ChannelMasterCalculatedData::query()->get(['channel', 'g_roi', 'gprofit_pct']) as $row) {
                $key = strtolower(trim((string) ($row->channel ?? '')));
                if ($key === '' || array_key_exists($key, $byChannel)) {
                    continue;
                }
                $byChannel[$key] = [
                    'groi_pct' => is_numeric($row->g_roi) ? round((float) $row->g_roi, 2) : null,
                    'gpft_pct' => is_numeric($row->gprofit_pct) ? round((float) $row->gprofit_pct, 2) : null,
                ];
            }
        } catch (\Throwable) {
            return $out;
        }

        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            if (! ($channel['enabled'] ?? false)) {
                continue;
            }
            $slug = (string) ($channel['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $metrics = ['groi_pct' => null, 'gpft_pct' => null];
            foreach (($channel['mp_channel_keys'] ?? []) as $candidate) {
                $key = strtolower(trim((string) $candidate));
                if ($key !== '' && array_key_exists($key, $byChannel)) {
                    $metrics = $byChannel[$key];
                    break;
                }
            }
            $out[$slug] = $metrics;
        }

        return $out;
    }

    /**
     * Resolve channel_master id + Ch Orders link for each Marketplace Manager slug.
     *
     * @return array<string, array{channel_id: ?int, ch_orders_link: ?string}>
     */
    protected function channelMasterMetaBySlug(): array
    {
        $out = [];
        if (! Schema::hasTable('channel_master')) {
            return $out;
        }

        $hasChOrdersLink = Schema::hasColumn('channel_master', 'ch_orders_link');
        $select = ['id', 'channel'];
        if ($hasChOrdersLink) {
            $select[] = 'ch_orders_link';
        }

        $byName = [];
        foreach (ChannelMaster::query()->get($select) as $row) {
            $key = strtolower(trim((string) $row->channel));
            if ($key === '' || isset($byName[$key])) {
                continue;
            }
            $link = $hasChOrdersLink ? trim((string) ($row->ch_orders_link ?? '')) : '';
            $byName[$key] = [
                'channel_id' => (int) $row->id,
                'ch_orders_link' => $link !== '' ? $link : null,
            ];
        }

        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            if (! ($channel['enabled'] ?? false)) {
                continue;
            }
            $slug = (string) ($channel['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $meta = ['channel_id' => null, 'ch_orders_link' => null];
            foreach (($channel['mp_channel_keys'] ?? []) as $candidate) {
                $key = strtolower(trim((string) $candidate));
                if ($key !== '' && isset($byName[$key])) {
                    $meta = $byName[$key];
                    break;
                }
            }
            $out[$slug] = $meta;
        }

        return $out;
    }

    /**
     * Summary-bar metrics (same keys shown on the SOF page badges).
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>|null  $channelRows
     * @return array<string, int|string>
     */
    public function collectSummaryTotals(?int $channelCount = null, $channelRows = null): array
    {
        if ($channelCount === null) {
            $channelCount = Schema::hasTable('channel_master')
                ? (int) ChannelMaster::query()
                    ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                    ->whereNotNull('channel')
                    ->where('channel', '!=', '')
                    ->count()
                : 0;
        }

        $pendingTotal = 0;
        if ($channelRows !== null) {
            $pendingTotal = (int) collect($channelRows)->sum(function ($row) {
                return ($row['pending_count'] ?? null) !== null ? (int) $row['pending_count'] : 0;
            });
        } else {
            $pendingTotal = (int) array_sum($this->pendingOrderCountsBySlug());
        }

        return [
            'channel_count' => (int) $channelCount,
            'pending_total' => $pendingTotal,
            'fulfilled_24h' => $this->fulfilledLast24HoursCount(),
            'scan_done_24h' => $this->scanDoneLast24HoursCount(),
            'in_transit_total' => $this->inTransitOrdersCount(),
            'in_received_total' => $this->inReceivedOrdersCount(),
            'invoiced_total' => $this->invoicedOrdersCount(),
            'delivered_total' => $this->deliveredOrdersCount(),
            'all_order_total' => $this->allOrdersCount(),
            'calculated_at' => now('America/Los_Angeles')->toDateTimeString(),
        ];
    }

    /**
     * Upsert one Pacific-day history row (used by sof:snapshot-daily at 00:00 PST).
     * Always writes a row for that date — even when every metric is unchanged vs the prior day.
     *
     * @param  bool  $onlyIfMissing  When true, skip update if the day already has a row (catch-up safe).
     */
    public function saveDailySnapshot(?string $snapshotDate = null, bool $onlyIfMissing = false): SalesOrderFulfillmentDailySummary
    {
        if (! Schema::hasTable('sales_order_fulfillment_daily_data')) {
            throw new \RuntimeException('sales_order_fulfillment_daily_data table missing — run migrations.');
        }

        $date = $snapshotDate ?: now('America/Los_Angeles')->subDay()->toDateString();

        $existing = SalesOrderFulfillmentDailySummary::query()
            ->whereDate('snapshot_date', $date)
            ->first();

        if ($onlyIfMissing && $existing) {
            return $existing;
        }

        $summary = $this->collectSummaryTotals();
        // Stamp every write so identical counts still produce a fresh daily record.
        $summary['recorded_at'] = now('America/Los_Angeles')->toIso8601String();
        $summary['unchanged_ok'] = true;

        $prev = SalesOrderFulfillmentDailySummary::query()
            ->whereDate('snapshot_date', '<', $date)
            ->orderByDesc('snapshot_date')
            ->first();
        $prevData = $prev?->summary_data ?? [];
        $metricKeys = array_keys(self::historyMetricKeys());
        $sameAsPrev = $prev !== null;
        foreach ($metricKeys as $key) {
            if ((int) ($summary[$key] ?? 0) !== (int) ($prevData[$key] ?? -1)) {
                $sameAsPrev = false;
                break;
            }
        }
        $summary['same_as_previous_day'] = $sameAsPrev;

        $row = $existing ?? new SalesOrderFulfillmentDailySummary(['snapshot_date' => $date]);
        $row->snapshot_date = $date;
        $row->summary_data = $summary;
        $row->notes = $sameAsPrev
            ? 'Daily SOF snapshot (no metric change vs prior day — still recorded)'
            : 'Daily SOF snapshot (Pacific day)';
        $row->updated_at = now();
        $row->save();

        return $row->fresh();
    }

    /** Metric keys used by history dots / charts (badge → summary_data key). */
    public static function historyMetricKeys(): array
    {
        return [
            'channel_count' => 'Channels',
            'pending_total' => 'Pending',
            'fulfilled_24h' => 'Label Created / No Scan',
            'scan_done_24h' => 'Shipped/Received',
            'in_transit_total' => 'In Transit',
            'in_received_total' => 'In Received',
            'invoiced_total' => 'Invoiced',
            'delivered_total' => 'Delivered',
            'all_order_total' => 'All Order',
        ];
    }

    /**
     * Trend dots for summary badges — same idea as Active Channel Master.
     * Returns [older, newer] per metric for green/red/gray coloring.
     */
    public function historyDotTrends(): JsonResponse
    {
        try {
            if (! Schema::hasTable('sales_order_fulfillment_daily_data')) {
                return response()->json(['success' => true, 'metrics' => (object) []]);
            }

            $rows = SalesOrderFulfillmentDailySummary::query()
                ->orderBy('snapshot_date', 'desc')
                ->take(30)
                ->get();

            $metrics = [];
            foreach (array_keys(self::historyMetricKeys()) as $key) {
                $metrics[$key] = [null, null];
            }

            // Compare latest day vs the immediately previous recorded day (even if values match → gray).
            if ($rows->count() >= 2) {
                $newer = $rows->get(0)->summary_data ?? [];
                $older = $rows->get(1)->summary_data ?? [];
                foreach (array_keys(self::historyMetricKeys()) as $key) {
                    $v2 = array_key_exists($key, $newer) ? (float) $newer[$key] : null;
                    $v1 = array_key_exists($key, $older) ? (float) $older[$key] : null;
                    $metrics[$key] = [$v1, $v2];
                }
            }

            return response()->json([
                'success' => true,
                'metrics' => $metrics,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'metrics' => (object) [],
            ], 500);
        }
    }

    /**
     * Chart.js series for one summary metric (Active Channel Master style).
     */
    public function historyChartData(Request $request): JsonResponse
    {
        try {
            $metric = trim((string) $request->input('metric', 'pending_total'));
            $days = (int) $request->input('days', 30);
            $labels = self::historyMetricKeys();
            if (! array_key_exists($metric, $labels)) {
                return response()->json(['success' => false, 'message' => 'Unknown metric'], 400);
            }

            if (! Schema::hasTable('sales_order_fulfillment_daily_data')) {
                return response()->json(['success' => true, 'data' => [], 'label' => $labels[$metric]]);
            }

            $query = SalesOrderFulfillmentDailySummary::query()->orderBy('snapshot_date', 'asc');
            if ($days > 0) {
                $start = now('America/Los_Angeles')->subDays($days)->toDateString();
                $query->where('snapshot_date', '>=', $start);
            }

            $chartData = [];
            foreach ($query->get() as $row) {
                $sd = $row->summary_data ?? [];
                if (! array_key_exists($metric, $sd)) {
                    continue;
                }
                $chartData[] = [
                    'date' => Carbon::parse($row->snapshot_date, 'America/Los_Angeles')->format('M d'),
                    'value' => (float) $sd[$metric],
                    'snapshot_date' => Carbon::parse($row->snapshot_date)->toDateString(),
                ];
            }

            return response()->json([
                'success' => true,
                'label' => $labels[$metric],
                'metric' => $metric,
                'data' => $chartData,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Partial bulk update of shipment fields on shopify_raw_orders.
     * Only keys present in `fields` are written (dirty-field patch).
     */
    public function bulkUpdateShipment(Request $request): JsonResponse
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return response()->json(['success' => false, 'message' => 'shopify_raw_orders table missing.'], 422);
        }

        $validated = $request->validate([
            'rows' => 'required|array|min:1|max:200',
            'rows.*.shopify_order_id' => 'nullable|string|max:64',
            'rows.*.order_number' => 'nullable|string|max:128',
            'rows.*.order_id' => 'nullable|string|max:128',
            'rows.*.order_id_api' => 'nullable|string|max:128',
            'rows.*.tracking_number' => 'nullable|string|max:128',
            'fields' => 'required|array|min:1',
        ]);

        $allowed = [
            'tracking_number',
            'tracking_company',
            'tracking_url',
            'shipment_status',
            'shipment_status_detail',
        ];

        $fields = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $validated['fields'])) {
                continue;
            }
            $val = $validated['fields'][$key];
            if ($val === null) {
                $fields[$key] = null;
                continue;
            }
            $fields[$key] = is_string($val) ? trim($val) : $val;
        }

        if ($fields === []) {
            return response()->json([
                'success' => false,
                'message' => 'No editable fields provided. Only changed fields are saved.',
            ], 422);
        }

        if (array_key_exists('shipment_status', $fields) && $fields['shipment_status'] !== null && $fields['shipment_status'] !== '') {
            $allowedStatuses = [
                ShipmentTrackingService::STATUS_PENDING,
                ShipmentTrackingService::STATUS_INFO_RECEIVED,
                ShipmentTrackingService::STATUS_IN_TRANSIT,
                ShipmentTrackingService::STATUS_OUT_FOR_DELIV,
                ShipmentTrackingService::STATUS_PICKUP,
                ShipmentTrackingService::STATUS_DELIVERED,
                ShipmentTrackingService::STATUS_EXCEPTION,
                ShipmentTrackingService::STATUS_FAILED,
                ShipmentTrackingService::STATUS_EXPIRED,
                ShipmentTrackingService::STATUS_NOT_FOUND,
            ];
            if (! in_array((string) $fields['shipment_status'], $allowedStatuses, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid shipment_status value.',
                ], 422);
            }
        }

        $updated = 0;
        $skipped = 0;
        $errors = [];
        $now = now();

        foreach ($validated['rows'] as $idx => $row) {
            try {
                $query = DB::table('shopify_raw_orders');
                $matched = false;

                $sid = trim((string) ($row['shopify_order_id'] ?? ''));
                if ($sid !== '' && ctype_digit($sid)) {
                    $query->where('order_id', (int) $sid);
                    $matched = true;
                } else {
                    $candidates = [];
                    foreach (['order_number', 'order_id', 'order_id_api'] as $k) {
                        $v = trim((string) ($row[$k] ?? ''));
                        if ($v !== '') {
                            $candidates[] = $v;
                            if (! str_starts_with($v, 'Amz')) {
                                $candidates[] = 'Amz'.$v;
                            }
                        }
                    }
                    $candidates = array_values(array_unique($candidates));
                    if ($candidates !== []) {
                        $query->whereIn('order_number', $candidates);
                        $matched = true;
                    } else {
                        $tn = trim((string) ($row['tracking_number'] ?? ''));
                        if ($tn !== '') {
                            $query->where('tracking_number', $tn);
                            $matched = true;
                        }
                    }
                }

                if (! $matched) {
                    $skipped++;
                    $errors[] = 'Row '.($idx + 1).': no Shopify match key.';
                    continue;
                }

                $payload = array_merge($fields, [
                    'shipment_checked_at' => $now,
                    'updated_at' => $now,
                ]);

                $affected = $query->update($payload);
                if ($affected > 0) {
                    $updated += $affected;
                } else {
                    $skipped++;
                    $errors[] = 'Row '.($idx + 1).': no matching shopify_raw_orders row.';
                }
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = 'Row '.($idx + 1).': '.$e->getMessage();
            }
        }

        return response()->json([
            'success' => $updated > 0,
            'message' => $updated > 0
                ? "Updated {$updated} Shopify order row(s)."
                    .($skipped > 0 ? " Skipped {$skipped}." : '')
                : 'No rows updated.'.($errors !== [] ? ' '.implode(' ', array_slice($errors, 0, 3)) : ''),
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10),
            'fields' => array_keys($fields),
        ], $updated > 0 ? 200 : 422);
    }

    /**
     * GOFO Open API connectivity / auth check for Sales Order Fulfillment tools.
     */
    public function gofoStatus(GofoExpressService $gofo): JsonResponse
    {
        if (! $gofo->isConfigured()) {
            return response()->json([
                'success' => false,
                'configured' => false,
                'message' => 'GOFO credentials missing. Set GOFO_USERNAME / GOFO_PASSWORD / GOFO_API_BASE in .env.',
            ], 422);
        }

        $ping = $gofo->ping();

        return response()->json([
            'success' => (bool) ($ping['ok'] ?? false),
            'configured' => true,
            'api_base' => (string) config('services.gofo.api_base', ''),
            'code' => $ping['code'] ?? null,
            'message' => $ping['msg'] ?? '',
            'data' => $ping['data'] ?? null,
        ], ($ping['ok'] ?? false) ? 200 : 502);
    }

    /**
     * Verify whether a consignee ZIP is in GOFO delivery range.
     */
    public function gofoVerifyDelivery(Request $request, GofoExpressService $gofo): JsonResponse
    {
        if (! $gofo->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'GOFO not configured.'], 422);
        }

        $validated = $request->validate([
            'consigneeCountry' => 'required|string|max:40',
            'consigneeCode' => 'required|string|max:20',
            'consigneeState' => 'nullable|string|max:80',
            'consigneeCity' => 'nullable|string|max:80',
            'consigneeArea' => 'nullable|string|max:80',
            'consigneeStreet' => 'nullable|string|max:200',
        ]);

        $res = $gofo->verifyDelivery($validated);

        return response()->json([
            'success' => (bool) ($res['ok'] ?? false),
            'code' => $res['code'] ?? null,
            'message' => $res['msgEn'] ?? $res['msg'] ?? '',
            'data' => $res['data'] ?? null,
        ], ($res['ok'] ?? false) ? 200 : 200);
    }

    /**
     * Track a GOFO waybill / customer order number.
     */
    public function gofoTrack(Request $request, GofoExpressService $gofo): JsonResponse
    {
        if (! $gofo->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'GOFO not configured.'], 422);
        }

        $validated = $request->validate([
            'orderNo' => 'required|string|max:80',
        ]);

        $res = $gofo->track($validated['orderNo']);
        $events = is_array($res['data'] ?? null) ? $res['data'] : [];
        $latest = is_array($events) && array_is_list($events) ? ($events[0] ?? []) : (is_array($events) ? $events : []);
        $status = null;
        if (is_array($latest) && $latest !== []) {
            $status = $gofo->normalizeTrackStatus(
                (string) ($latest['operationMove'] ?? ''),
                (string) ($latest['enContext'] ?? $latest['pubEsContext'] ?? '')
            );
        }

        return response()->json([
            'success' => (bool) ($res['ok'] ?? false),
            'code' => $res['code'] ?? null,
            'message' => $res['msgEn'] ?? $res['msg'] ?? '',
            'status' => $status,
            'data' => $events,
        ]);
    }

    /**
     * Refresh shipment status for open GOFO-tracked orders via native GOFO API.
     */
    public function gofoRefreshStatuses(Request $request, ShipmentTrackingService $tracking): JsonResponse
    {
        if (! $tracking->hasGofo()) {
            return response()->json([
                'success' => false,
                'message' => 'GOFO not configured.',
            ], 422);
        }

        $limit = max(1, min(80, (int) $request->input('limit', 40)));

        try {
            $params = [
                '--only-open' => true,
                '--stale' => 0,
                '--limit' => $limit,
                '--carrier' => 'GOFO',
            ];

            $exit = Artisan::call('tracking:sync-status', $params);
            $output = trim(Artisan::output());

            return response()->json([
                'success' => $exit === 0,
                'message' => $exit === 0
                    ? "GOFO shipment statuses refreshed (up to {$limit} open packages)."
                    : 'GOFO status refresh finished with errors.',
                'output' => $output,
            ], $exit === 0 ? 200 : 500);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh GOFO statuses: '.$e->getMessage(),
            ], 500);
        }
    }
}
