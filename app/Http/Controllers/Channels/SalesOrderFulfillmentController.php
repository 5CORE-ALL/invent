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
use App\Models\SheinOrderMetric;
use App\Models\ShopifySku;
use App\Models\TemuOrder;
use App\Models\TopDawgOrderMetric;
use App\Models\WayfairDailyData;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function __construct(
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function index(): View
    {
        return view('channels.sales_order_fulfillment', [
            'topBadges' => $this->topBadgePayload(),
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

            $pendingTotal = (int) $data->sum(function ($row) {
                return $row['pending_count'] !== null ? (int) $row['pending_count'] : 0;
            });
            $fulfilled24h = $this->fulfilledLast24HoursCount();
            $scanDone24h = $this->scanDoneLast24HoursCount();
            $invoicedTotal = $this->invoicedOrdersCount();
            $deliveredTotal = $this->deliveredOrdersCount();
            $allOrderTotal = $this->allOrdersCount();

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'channel_count' => $data->count(),
                'pending_total' => $pendingTotal,
                'fulfilled_24h' => $fulfilled24h,
                'scan_done_24h' => $scanDone24h,
                'invoiced_total' => $invoicedTotal,
                'delivered_total' => $deliveredTotal,
                'all_order_total' => $allOrderTotal,
            ]);
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
                'invoiced_total' => 0,
                'delivered_total' => 0,
                'all_order_total' => 0,
            ], 500);
        }
    }

    /**
     * All pending orders across Marketplace Manager channels (for Pending tab).
     */
    public function pendingData(): JsonResponse
    {
        try {
            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->pendingOrdersQuery($slug),
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
     * Not Scan (shipped / fulfilled) orders in the last 24 hours.
     */
    public function fulfilledData(): JsonResponse
    {
        try {
            $since = now()->subDay();
            $rows = $this->collectOrderRows(
                function (string $slug) use ($since) {
                    $query = $this->fulfilledOrdersQuery($slug);
                    if ($query === null) {
                        return null;
                    }

                    return $this->applyUpdatedSinceFilter($query, $since, $slug);
                },
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
                'message' => 'Failed to load fulfilled orders.',
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * Scan Done — orders with status Received only (last 24 hours).
     */
    public function scanDoneData(): JsonResponse
    {
        try {
            $since = now()->subDay();
            $rows = $this->collectOrderRows(
                function (string $slug) use ($since) {
                    $query = $this->scanDoneOrdersQuery($slug);
                    if ($query === null) {
                        return null;
                    }

                    return $this->applyUpdatedSinceFilter($query, $since, $slug);
                },
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
                'message' => 'Failed to load Scan Done orders.',
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * Invoiced — all orders with Invoiced status (no time window).
     */
    public function invoicedData(): JsonResponse
    {
        try {
            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->invoicedOrdersQuery($slug),
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
     * Delivered — all orders with Delivered status (no time window).
     */
    public function deliveredData(): JsonResponse
    {
        try {
            $rows = $this->collectOrderRows(
                fn (string $slug) => $this->deliveredOrdersQuery($slug),
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
            $since = now()->subDays(30)->startOfDay();
            $rows = $this->collectOrderRows(
                function (string $slug) use ($since) {
                    $query = $this->allOrdersQuery($slug);
                    if ($query === null) {
                        return null;
                    }

                    return $this->applyLast30DaysFilter($query, $since, $slug);
                },
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

        return $this->attachShippingMasterLabelToOrderRows($rows);
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

        // eBay shipping fulfillment href ends with fulfillment id (best available local signal)
        if ($slug === 'ebay3') {
            $hrefs = $order['fulfillmentHrefs'] ?? null;
            if (is_array($hrefs) && ! empty($hrefs[0]) && is_string($hrefs[0])) {
                $parts = explode('/', rtrim($hrefs[0], '/'));
                $last = trim((string) end($parts));
                if ($last !== '' && ! str_contains($last, 'http')) {
                    return $last;
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
            $slug === 'purchasingpower'
                && (
                    in_array(str_replace([' ', '-'], '_', $upper), ['SHIPPING', 'TO_COLLECT'], true)
                    || str_contains($lower, 'awaiting shipment')
                ) => 'Pending',
            $slug === 'doba' && str_replace([' ', '-'], '_', $upper) === 'UNSHIPPED' => 'Pending',
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
        $query = $this->pendingOrdersQuery($slug);
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
     * Count of Not Scan orders updated in the last 24 hours (all MM channels).
     */
    protected function fulfilledLast24HoursCount(): int
    {
        return $this->countOrdersLast24Hours(fn (string $slug) => $this->fulfilledOrdersQuery($slug));
    }

    /**
     * Count of Scan Done (Received) orders updated in the last 24 hours.
     */
    protected function scanDoneLast24HoursCount(): int
    {
        return $this->countOrdersLast24Hours(fn (string $slug) => $this->scanDoneOrdersQuery($slug));
    }

    /**
     * Count of all Invoiced orders (no time window).
     */
    protected function invoicedOrdersCount(): int
    {
        return $this->countAllOrders(fn (string $slug) => $this->invoicedOrdersQuery($slug));
    }

    /**
     * Count of all Delivered orders (no time window).
     */
    protected function deliveredOrdersCount(): int
    {
        return $this->countAllOrders(fn (string $slug) => $this->deliveredOrdersQuery($slug));
    }

    /**
     * Count of marketplace orders in the last 30 days (All Order tab).
     */
    protected function allOrdersCount(): int
    {
        $since = now()->subDays(30)->startOfDay();

        return $this->countAllOrders(function (string $slug) use ($since) {
            $query = $this->allOrdersQuery($slug);
            if ($query === null) {
                return null;
            }

            return $this->applyLast30DaysFilter($query, $since, $slug);
        });
    }

    /**
     * Restrict to last 30 days by channel order-date column.
     */
    protected function applyLast30DaysFilter(Builder $query, mixed $since, string $slug): Builder
    {
        return match ($slug) {
            'amazon' => $query->whereHas('order', fn (Builder $q) => $q->where('order_date', '>=', $since)),
            'temu' => $query->where(function (Builder $q) use ($since) {
                $q->where('parent_order_time', '>=', $since)
                    ->orWhere(function (Builder $q2) use ($since) {
                        $q2->whereNull('parent_order_time')->where('order_update_time', '>=', $since);
                    });
            }),
            'bestbuy', 'macy' => $query->where(function (Builder $q) use ($since) {
                $q->where('order_created_at', '>=', $since)
                    ->orWhere(function (Builder $q2) use ($since) {
                        $q2->whereNull('order_created_at')->where('order_updated_at', '>=', $since);
                    });
            }),
            'purchasingpower' => $query->where('date_created', '>=', $since),
            'wayfair' => $query->where('po_date', '>=', $since),
            'doba' => $query->where('order_time', '>=', $since),
            default => $query->where(function (Builder $q) use ($since) {
                $q->where('order_date', '>=', $since)
                    ->orWhere(function (Builder $q2) use ($since) {
                        $q2->whereNull('order_date')->where('updated_at', '>=', $since);
                    });
            }),
        };
    }

    /**
     * Restrict to rows updated/touched since $since (Label Created / Scan Done windows).
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
     * Not Scan / shipped rows for a marketplace
     * (excludes Received → Scan Done, Invoiced → Invoiced, Delivered → Delivered).
     */
    protected function fulfilledOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'ebay1', 'ebay2', 'ebay3' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['FULFILLED']),
            'shein' => $base->where('status', 'Shipped'),
            'reverb' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN (?, ?)", ['shipped', 'picked_up']),
            'aliexpress', 'alibaba' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['WAIT_BUYER_ACCEPT_GOODS']),
            'amazon' => $base->whereHas('order', fn (Builder $q) => $q->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)",
                ['SHIPPED', 'PARTIALLYSHIPPED']
            )),
            'topdawg' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['shipped']),
            'temu' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(parent_order_status_text, order_status_text, ''))) IN (?, ?)",
                ['SHIPPED', 'PARTIALLY_SHIPPED']
            ),
            'purchasingpower' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)",
                ['SHIPPED', 'SHIPPING']
            ),
            'wayfair' => null, // all open POs stay Pending
            'bestbuy', 'macy' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['SHIPPED']),
            'doba' => $base->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) IN (?, ?)", ['IN TRANSIT', 'IN_TRANSIT']),
            default => null,
        };
    }

    /**
     * Scan Done — status Received only (Shein / Reverb / Mirakl Received).
     */
    protected function scanDoneOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'shein', 'reverb' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['received']),
            'purchasingpower' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['RECEIVED']),
            'bestbuy', 'macy' => null, // Received folded into Delivered for Mirakl
            default => null,
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
     * Delivered — delivered / received / completed across MM channels.
     */
    protected function deliveredOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'faire' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['DELIVERED']),
            'shein', 'reverb' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['received']),
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
            'purchasingpower' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['RECEIVED']),
            'bestbuy', 'macy' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)",
                ['DELIVERED', 'RECEIVED']
            ),
            'doba' => $base->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) = ?", ['COMPLETED']),
            default => $base->whereRaw(
                "LOWER(TRIM(COALESCE(status, ''))) IN (?, ?)",
                ['delivered', 'received']
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
                $q->whereRaw("UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)", ['SHIPPING', 'TO_COLLECT'])
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
}
