<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\AlibabaOrderMetric;
use App\Models\AliexpressOrderMetric;
use App\Models\ChannelMaster;
use App\Models\Ebay2OrderMetric;
use App\Models\Ebay3OrderMetric;
use App\Models\FaireOrderMetric;
use App\Models\NeweggOrderMetric;
use App\Models\ReverbOrderMetric;
use App\Models\SalesOrderFulfillmentBadgeLink;
use App\Models\SheinOrderMetric;
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

                    return $query->where('updated_at', '>=', $since);
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

                    return $query->where('updated_at', '>=', $since);
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

                    return $this->applyLast30DaysFilter($query, $since);
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

            $orders = $query
                ->when($sortByUpdatedAt, fn (Builder $q) => $q->orderByDesc('updated_at'))
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->get();

            foreach ($orders as $order) {
                $statusRaw = trim((string) ($order->status ?? ''));
                $tracking = $this->extractTrackingNumber($slug, $order->raw_payload ?? null);
                $apiOrderId = trim((string) ($order->order_id ?? ''));
                $orderNumber = trim((string) ($order->order_number ?? ''));
                // Prefer human-readable order number (e.g. Faire display_id N8PA3FG3F8)
                // over internal API ids (e.g. bo_n8pa3fg3f8).
                $displayOrderId = $orderNumber !== '' ? $orderNumber : $apiOrderId;

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
                    'order_date' => $this->formatOrderDate($order->order_date ?? null),
                    'updated_at' => $this->formatOrderDate($order->updated_at ?? null),
                    'tracking_number' => $tracking,
                    'status' => $statusRaw,
                    'status_label' => $useOriginalStatus
                        ? ($statusRaw !== '' ? $statusRaw : '—')
                        : $this->orderStatusLabel($slug, $statusRaw),
                    'sku' => (string) ($order->sku ?? ''),
                    'display_title' => (string) ($order->display_title ?? ''),
                    'quantity' => (int) ($order->quantity ?? 1),
                    'amount' => is_numeric($order->amount ?? null) ? (float) $order->amount : null,
                    'import_status' => (string) ($order->import_status ?? ''),
                    'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                    'ch_orders_link' => $meta['ch_orders_link'],
                    'orders_url' => $ordersUrl,
                    'order_url' => route('marketplace.orders.show', [
                        'marketplace' => $slug,
                        'order' => $order->id,
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

        return array_values($rows);
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
            $slug === 'ebay3' && $upper === 'NOT_STARTED' => 'Pending',
            $slug === 'ebay3' && $upper === 'FULFILLED' => 'Label Created',
            $slug === 'newegg' && ((string) $status === '0') => 'Unshipped',
            $slug === 'newegg' && ((string) $status === '3') => 'Invoiced',
            $slug === 'reverb' && $lower === 'paid' => 'Paid',
            $slug === 'reverb' && $lower === 'shipped' => 'Shipped',
            $slug === 'reverb' && $lower === 'received' => 'Received',
            $slug === 'shein' && $lower === 'received' => 'Received',
            $slug === 'aliexpress' && $upper === 'WAIT_BUYER_ACCEPT_GOODS' => 'Shipped',
            $slug === 'alibaba' && $upper === 'WAIT_BUYER_ACCEPT_GOODS' => 'Shipped',
            $slug === 'faire' && $upper === 'DELIVERED' => 'Delivered',
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

            return $this->applyLast30DaysFilter($query, $since);
        });
    }

    /**
     * Restrict to last 30 days by order_date (fallback: updated_at when order_date is null).
     */
    protected function applyLast30DaysFilter(Builder $query, mixed $since): Builder
    {
        return $query->where(function (Builder $q) use ($since) {
            $q->where('order_date', '>=', $since)
                ->orWhere(function (Builder $q2) use ($since) {
                    $q2->whereNull('order_date')
                        ->where('updated_at', '>=', $since);
                });
        });
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
                $total += (int) $query->where('updated_at', '>=', $since)->count();
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
        return match ($slug) {
            'ebay3' => Schema::hasTable('ebay3_order_metrics')
                ? Ebay3OrderMetric::query()->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['FULFILLED'])
                : null,
            'shein' => Schema::hasTable('shein_order_metrics')
                ? SheinOrderMetric::query()->where('status', 'Shipped')
                : null,
            'reverb' => Schema::hasTable('reverb_order_metrics')
                ? ReverbOrderMetric::query()->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN (?, ?)", ['shipped', 'picked_up'])
                : null,
            'aliexpress' => Schema::hasTable('aliexpress_order_metrics')
                ? AliexpressOrderMetric::query()->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['WAIT_BUYER_ACCEPT_GOODS'])
                : null,
            'alibaba' => Schema::hasTable('alibaba_order_metrics')
                ? AlibabaOrderMetric::query()->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['WAIT_BUYER_ACCEPT_GOODS'])
                : null,
            default => null,
        };
    }

    /**
     * Scan Done — status Received only (Shein / Reverb).
     */
    protected function scanDoneOrdersQuery(string $slug): ?Builder
    {
        return match ($slug) {
            'shein' => Schema::hasTable('shein_order_metrics')
                ? SheinOrderMetric::query()->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['received'])
                : null,
            'reverb' => Schema::hasTable('reverb_order_metrics')
                ? ReverbOrderMetric::query()->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['received'])
                : null,
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
     * Delivered — delivered / received / buyer-accepted across all MM channels.
     */
    protected function deliveredOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        // Per-channel delivered equivalents (from live status values + marketplace docs).
        return match ($slug) {
            'faire' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['DELIVERED']),
            'shein' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['received']),
            'reverb' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['received']),
            // eBay has no separate Delivered status in metrics (FULFILLED = Label Created).
            'ebay2', 'ebay3' => null,
            // Newegg 3 = Invoiced (own tab); no Delivered code in current data.
            'newegg' => null,
            // AliExpress / Alibaba: buyer accepted / finished (when present).
            'aliexpress', 'alibaba' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) IN (?, ?, ?)",
                ['FINISH', 'BUYER_ACCEPT_GOODS', 'TRADE_FINISHED']
            ),
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
            'ebay3' => Schema::hasTable('ebay3_order_metrics')
                ? Ebay3OrderMetric::query()
                : null,
            'ebay2' => Schema::hasTable('ebay2_order_metrics')
                ? Ebay2OrderMetric::query()
                : null,
            'shein' => Schema::hasTable('shein_order_metrics')
                ? SheinOrderMetric::query()
                : null,
            'reverb' => Schema::hasTable('reverb_order_metrics')
                ? ReverbOrderMetric::query()
                : null,
            'aliexpress' => Schema::hasTable('aliexpress_order_metrics')
                ? AliexpressOrderMetric::query()
                : null,
            'alibaba' => Schema::hasTable('alibaba_order_metrics')
                ? AlibabaOrderMetric::query()
                : null,
            'newegg' => Schema::hasTable('newegg_order_metrics')
                ? NeweggOrderMetric::query()
                : null,
            'faire' => Schema::hasTable('faire_order_metrics')
                ? FaireOrderMetric::query()
                : null,
            default => null,
        };
    }

    /**
     * Fulfillment-pending rows for a marketplace (mirrors orders-page Pending badge).
     */
    protected function pendingOrdersQuery(string $slug): ?Builder
    {
        return match ($slug) {
            'ebay3' => Schema::hasTable('ebay3_order_metrics')
                ? Ebay3OrderMetric::query()->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['NOT_STARTED'])
                : null,
            'shein' => Schema::hasTable('shein_order_metrics')
                ? SheinOrderMetric::query()->whereIn('status', [
                    'Pending',
                    'To Be Shipped',
                    'To Be Shipped by SHEIN',
                ])
                : null,
            'reverb' => Schema::hasTable('reverb_order_metrics')
                ? ReverbOrderMetric::query()->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['paid'])
                : null,
            'aliexpress' => Schema::hasTable('aliexpress_order_metrics')
                ? AliexpressOrderMetric::query()->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['WAIT_SELLER_SEND_GOODS'])
                : null,
            'alibaba' => Schema::hasTable('alibaba_order_metrics')
                ? AlibabaOrderMetric::query()->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['WAIT_SELLER_SEND_GOODS'])
                : null,
            // Newegg: 0 = Unshipped (needs fulfillment). 3=Invoiced, 4=Void.
            'newegg' => Schema::hasTable('newegg_order_metrics')
                ? NeweggOrderMetric::query()->whereIn('status', ['0', 0])
                : null,
            'faire' => Schema::hasTable('faire_order_metrics')
                ? FaireOrderMetric::query()->whereRaw("UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)", ['PROCESSING', 'NEW'])
                : null,
            default => null,
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
