<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\AlibabaOrderMetric;
use App\Models\AliexpressOrderMetric;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
use App\Models\BestBuyOrderMetric;
use App\Models\ChannelMaster;
use App\Models\ChannelMasterCalculatedData;
use App\Models\DobaDailyData;
use App\Models\DobaWarehouseShip;
use App\Models\Ebay1OrderMetric;
use App\Models\Ebay2OrderMetric;
use App\Models\Ebay3OrderMetric;
use App\Models\FaireOrderMetric;
use App\Models\MacyOrderMetric;
use App\Models\MarketplacePercentage;
use App\Models\NeweggOrderMetric;
use App\Models\ProductMaster;
use App\Models\PurchasingPowerSale;
use App\Models\ReverbOrderMetric;
use App\Models\SalesOrderFulfillmentBadgeLink;
use App\Models\SalesOrderFulfillmentDailySummary;
use App\Models\SheinOrderMetric;
use App\Models\ShopifySku;
use App\Models\Temu2Order;
use App\Models\TemuOrder;
use App\Models\Tiktok2Order;
use App\Models\TiktokOrder;
use App\Models\TopDawgOrderMetric;
use App\Models\WayfairDailyData;
use App\Jobs\SyncShipmentTrackingStatusJob;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\ChannelTrackingApiFallbackService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\Temu2OrderTrackingPullService;
use App\Services\MarketplaceManager\TemuOrderAmountParser;
use App\Services\MarketplaceManager\TemuOrderTrackingPullService;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use App\Services\ShipmentTrackingService;
use App\Services\Support\MarketplaceApiConfigService;
use App\Support\TrackingCarrierGuesser;
use App\Services\FourSellerApiService;
use App\Services\TemuShopifySalesService;
use App\Services\VeeqoApiService;
use App\Support\ProductMasterTemuShip;
use App\Support\ProductMasterShipBb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales Order Fulfillment — Tabulator of active channels from channel_master
 * (same source as /all-marketplace-master Active Channels Master).
 */
class SalesOrderFulfillmentController extends Controller
{
    /** @var list<string> */
    public const TOP_BADGE_KEYS = ['gofo', 'veeqo', 'shopify', 'others'];

    /** Max orders per HTTP Pull Tracking request (keep under nginx/proxy gateway timeout). */
    protected const HTTP_PULL_MAX = 4;

    /** Stop HTTP Pull Tracking after this many seconds and return partial results. */
    protected const HTTP_PULL_DEADLINE_SECONDS = 16.0;

    /** Calendar and display timezone for this page (EST/EDT). */
    public const SOF_TIMEZONE = 'America/New_York';

    protected function sofTimezone(): string
    {
        return self::SOF_TIMEZONE;
    }

    /** Naive DB datetimes follow the app timezone (Pacific). */
    protected function sofStorageTimezone(): string
    {
        return (string) (config('app.timezone') ?: 'America/Los_Angeles');
    }

    /** @var list<array<string, mixed>>|null */
    protected ?array $cachedLabelCreatedRows = null;

    /** @var list<array<string, mixed>>|null */
    protected ?array $cachedPendingRows = null;

    public function __construct(
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function index(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        $veeqoConfigured = $veeqo->isConfigured();
        $veeqoCarriers = [];
        if ($veeqoConfigured) {
            try {
                $veeqoCarriers = $veeqo->carrierOptions();
            } catch (\Throwable $e) {
                $veeqoCarriers = [];
            }
        }

        $sofChannels = collect(MarketplaceManagerRegistry::channels())
            ->filter(fn ($c) => ($c['enabled'] ?? false) === true)
            ->map(fn ($c) => [
                'slug' => (string) ($c['slug'] ?? ''),
                'label' => (string) ($c['label'] ?? ($c['slug'] ?? '')),
            ])
            ->filter(fn ($c) => ($c['slug'] ?? '') !== '')
            ->values()
            ->all();

        return view('channels.sales_order_fulfillment', [
            'topBadges' => $this->topBadgePayload(),
            'gofoApiConfigured' => $gofo->isConfigured(),
            'gofoApiBase' => (string) config('services.gofo.api_base', ''),
            'veeqoApiConfigured' => $veeqoConfigured,
            'veeqoApiBase' => (string) config('services.veeqo.api_base', ''),
            'veeqoCarriers' => $veeqoCarriers,
            'sofChannels' => $sofChannels,
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

            $this->autoFillMissingChOrdersLinks();
            $managerByMpKey = $this->managerChannelsByMpKey();
            $pendingBySlug = $this->pendingOrderCountsBySlug();

            $data = $rows->map(function ($row) use ($hasLogo, $hasSellerLink, $hasAlias, $hasMissingLink, $hasChOrdersLink, $managerByMpKey, $pendingBySlug) {
                $channel = trim((string) $row->channel);
                $manager = $managerByMpKey[strtolower($channel)] ?? null;
                $slug = $manager['slug'] ?? null;
                $hasManager = $slug !== null;
                $connected = $hasManager ? $this->apiConfig->isConfigured($slug) : false;
                $pending = $hasManager ? (int) ($pendingBySlug[$slug] ?? 0) : null;
                $ordersUrl = $slug ? route('marketplace.orders', $slug) : null;
                $chOrdersLink = $hasChOrdersLink ? trim((string) ($row->ch_orders_link ?? '')) : '';
                if ($chOrdersLink === '' && $ordersUrl) {
                    $chOrdersLink = $ordersUrl;
                }

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
                    'orders_url' => $ordersUrl,
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
                'received_by_carrier_total' => 0,
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
    /** Doba Analytics / API value for prepaid pickup labels. */
    protected const DOBA_PREPAID_ORDER_TYPE = 'pickup with a prepaid label';

    public function pendingData(): JsonResponse
    {
        try {
            $rows = $this->warehousePendingOrderRows();

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
     * Label Created / No Scan — last 24 hours only.
     * Older labeled rows (already scanned in the warehouse) go to In Transit
     * until carrier tracking reports Delivered.
     */
    public function fulfilledData(): JsonResponse
    {
        try {
            @set_time_limit(90);
            // Return rows immediately. Live Veeqo/GOFO pulls belong on Pull Tracking —
            // blocking this endpoint left the tab empty while the badge still showed a count.
            $rows = $this->labelCreatedNoScanRows();

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
     * Received by carrier — Shipped/Received + In Received, last 30 days.
     */
    public function scanDoneData(): JsonResponse
    {
        try {
            $rows = $this->receivedByCarrierOrderRows();

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load Received by carrier orders.',
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
            $rows = $this->annotateInTransitScanPendingAlerts(
                $this->excludeCarrierDeliveredRows($this->inTransitOrderRows())
            );

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
     * Alias of scanDoneData() — In Received is merged into Received by carrier.
     */
    public function inReceivedData(): JsonResponse
    {
        return $this->scanDoneData();
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
            $rows = $this->mergeOrderRowsById(
                $rows,
                $this->onlyCarrierDeliveredRows($this->inTransitOrderRows())
            );

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
     * Warehouse Doba queue — prepaid vs non-prepaid, including rows that already
     * have auto-synced tracking (those leave the global Pending tab).
     */
    public function dobaOrdersData(): JsonResponse
    {
        try {
            @set_time_limit(90);
            $grouped = $this->buildDobaWarehouseOrderRows(true);

            return response()->json([
                'success' => true,
                'non_prepaid' => $grouped['non_prepaid'],
                'prepaid' => $grouped['prepaid'],
                'done' => $grouped['done'],
                'non_prepaid_count' => count($grouped['non_prepaid']),
                'prepaid_count' => count($grouped['prepaid']),
                'done_count' => count($grouped['done']),
                'open_count' => $grouped['open_count'],
                'count' => count($grouped['non_prepaid']) + count($grouped['prepaid']),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load Doba orders.',
                'non_prepaid' => [],
                'prepaid' => [],
                'done' => [],
                'non_prepaid_count' => 0,
                'prepaid_count' => 0,
                'done_count' => 0,
                'open_count' => 0,
                'count' => 0,
            ], 500);
        }
    }

    /**
     * Export Doba warehouse orders (prepaid or non-prepaid) as CSV or XLSX
     * for every matching line in the selected Eastern date range.
     */
    public function dobaOrdersExport(Request $request): StreamedResponse|JsonResponse
    {
        $type = strtolower(trim((string) $request->input('type', 'non_prepaid')));
        if (! in_array($type, ['non_prepaid', 'prepaid', 'done'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid Doba export type.'], 422);
        }

        $format = strtolower(trim((string) $request->input('format', 'csv')));
        if (in_array($format, ['xls', 'excel'], true)) {
            $format = 'xlsx';
        }
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid export format.'], 422);
        }

        $rows = $this->buildDobaWarehouseExportRows($type);
        [$from, $to] = $this->resolveOrderDateRange();
        $fromYmd = $from->timezone($this->sofTimezone())->format('Y-m-d');
        $toYmd = $to->timezone($this->sofTimezone())->format('Y-m-d');
        $label = match ($type) {
            'prepaid' => 'prepaid',
            'done' => 'done',
            default => 'non-prepaid',
        };
        $filename = 'doba-'.$label.'-orders-'.$fromYmd.'-to-'.$toYmd.'.'.($format === 'xlsx' ? 'xlsx' : 'csv');
        $headers = ['Order ID', 'Order Date', 'Customer Name', 'Shipping Address', 'SKU', 'Quantity'];

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows) {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
                fputcsv($out, $headers);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['order_id'],
                        $row['order_date'],
                        $row['customer_name'],
                        $row['shipping_address'],
                        $row['sku'],
                        $row['quantity'],
                    ]);
                }
                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Doba Orders');
            $sheet->fromArray($headers, null, 'A1');
            $r = 2;
            foreach ($rows as $row) {
                $sheet->setCellValueExplicit('A'.$r, $row['order_id'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$r, $row['order_date']);
                $sheet->setCellValue('C'.$r, $row['customer_name']);
                $sheet->setCellValue('D'.$r, $row['shipping_address']);
                $sheet->setCellValueExplicit('E'.$r, $row['sku'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('F'.$r, $row['quantity']);
                $r++;
            }
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->getStyle('A1:F1')->getFont()->setBold(true);
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Local warehouse "mark shipped" — not overwritten by Doba order sync.
     */
    public function markDobaOrderShipped(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_no' => ['required', 'string', 'max:191'],
            'shipped' => ['required'],
        ]);

        $orderNo = trim((string) $validated['order_no']);
        $shipped = filter_var($validated['shipped'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($orderNo === '' || $shipped === null) {
            return response()->json([
                'success' => false,
                'message' => 'order_no and shipped are required.',
            ], 422);
        }

        if (! Schema::hasTable('doba_warehouse_ships')) {
            return response()->json([
                'success' => false,
                'message' => 'doba_warehouse_ships table missing — run migrations.',
            ], 500);
        }

        try {
            if ($shipped) {
                DobaWarehouseShip::query()->updateOrCreate(
                    ['order_no' => $orderNo],
                    [
                        'shipped' => true,
                        'shipped_at' => now(),
                        'shipped_by' => $request->user()?->id,
                    ]
                );
            } else {
                DobaWarehouseShip::query()->where('order_no', $orderNo)->delete();
            }

            return response()->json([
                'success' => true,
                'order_no' => $orderNo,
                'shipped' => $shipped,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update Doba shipped flag.',
            ], 500);
        }
    }

    protected function requestWantsShippedDobaOrders(): bool
    {
        $raw = request()->input('include_shipped', false);

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{non_prepaid: list<array<string, mixed>>, prepaid: list<array<string, mixed>>, done: list<array<string, mixed>>, open_count: int}
     */
    protected function buildDobaWarehouseOrderRows(bool $includeShipped): array
    {
        $empty = ['non_prepaid' => [], 'prepaid' => [], 'done' => [], 'open_count' => 0];
        if (! Schema::hasTable('doba_daily_data')) {
            return $empty;
        }

        $query = $this->scopedToLast30Days(DobaDailyData::query(), 'doba');
        if ($query === null) {
            return $empty;
        }

        $query->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) NOT LIKE ?", ['%CANCEL%'])
            ->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) NOT LIKE ?", ['%REFUND%'])
            ->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) NOT LIKE ?", ['%VOID%'])
            ->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) NOT IN (?, ?)", ['COMPLETED', 'DELIVERED']);

        $select = [
            'id', 'order_no', 'platform_order_no', 'order_time', 'updated_at',
            'order_status', 'order_type', 'sku', 'product_name', 'quantity',
            'total_price', 'item_price', 'tracking_number', 'carrier_name',
        ];
        if (Schema::hasColumn('doba_daily_data', 'order_json')) {
            $select[] = 'order_json';
        }
        if (Schema::hasColumn('doba_daily_data', 'shopify_order_id')) {
            $select[] = 'shopify_order_id';
        }
        $lines = $query->orderByDesc('order_time')->get($select);

        $shippedByOrder = [];
        if (Schema::hasTable('doba_warehouse_ships')) {
            foreach (DobaWarehouseShip::query()->where('shipped', true)->get(['order_no', 'shipped_at']) as $ship) {
                $key = trim((string) ($ship->order_no ?? ''));
                if ($key !== '') {
                    $shippedByOrder[$key] = $ship->shipped_at
                        ? $this->formatOrderDate($ship->shipped_at)
                        : true;
                }
            }
        }

        $byOrder = [];
        foreach ($lines as $line) {
            $orderNo = trim((string) ($line->order_no ?? ''));
            if ($orderNo === '') {
                continue;
            }
            if (! isset($byOrder[$orderNo])) {
                $byOrder[$orderNo] = [
                    'id' => 'doba-wh-'.$orderNo,
                    'row_id' => (int) $line->id,
                    'show_id' => (int) $line->id,
                    'mm_slug' => 'doba',
                    'channel_label' => 'Doba',
                    'order_id' => $orderNo,
                    'order_id_api' => $orderNo,
                    'order_number' => trim((string) ($line->platform_order_no ?: $orderNo)),
                    'order_date' => $this->formatOrderDate($line->order_time ?? null),
                    'updated_at' => $this->formatOrderDate($line->updated_at ?? null),
                    'status' => trim((string) ($line->order_status ?? '')),
                    'status_label' => trim((string) ($line->order_status ?? '')) ?: '—',
                    'order_type' => trim((string) ($line->order_type ?? '')),
                    'is_prepaid' => $this->dobaOrderTypeIsPrepaid((string) ($line->order_type ?? '')),
                    'sku' => '',
                    'skus' => [],
                    'display_title' => '',
                    'titles' => [],
                    'quantity' => 0,
                    'amount' => null,
                    'tracking_number' => null,
                    'tracking_company' => null,
                    'prepaid_label_url' => null,
                    'shopify_order_id' => '',
                    'shopify_order_number' => '',
                    'warehouse_shipped' => isset($shippedByOrder[$orderNo]),
                    'warehouse_shipped_at' => is_string($shippedByOrder[$orderNo] ?? null)
                        ? $shippedByOrder[$orderNo]
                        : null,
                    'order_url' => route('marketplace.orders.show', [
                        'marketplace' => 'doba',
                        'order' => (int) $line->id,
                    ]),
                    'orders_url' => route('marketplace.orders', 'doba'),
                ];
            }

            $row = &$byOrder[$orderNo];
            $sku = trim((string) ($line->sku ?? ''));
            if ($sku !== '' && ! in_array($sku, $row['skus'], true)) {
                $row['skus'][] = $sku;
            }
            $title = trim((string) ($line->product_name ?? ''));
            if ($title !== '' && ! in_array($title, $row['titles'], true)) {
                $row['titles'][] = $title;
            }
            $row['quantity'] += max(0, (int) ($line->quantity ?? 0));
            $amt = $line->total_price ?? $line->item_price ?? null;
            if (is_numeric($amt)) {
                $row['amount'] = max((float) ($row['amount'] ?? 0), (float) $amt);
            }
            $tn = trim((string) ($line->tracking_number ?? ''));
            if ($tn !== '' && trim((string) ($row['tracking_number'] ?? '')) === '') {
                $row['tracking_number'] = $tn;
            }
            $carrier = trim((string) ($line->carrier_name ?? ''));
            if ($carrier !== '' && trim((string) ($row['tracking_company'] ?? '')) === '') {
                $row['tracking_company'] = $carrier;
            }
            if (! $row['is_prepaid'] && $this->dobaOrderTypeIsPrepaid((string) ($line->order_type ?? ''))) {
                $row['is_prepaid'] = true;
                $row['order_type'] = trim((string) ($line->order_type ?? ''));
            }
            if (($row['prepaid_label_url'] ?? '') === '') {
                $labelUrl = $this->extractDobaPrepaidLabelUrl($line->order_json ?? null);
                if ($labelUrl !== null) {
                    $row['prepaid_label_url'] = $labelUrl;
                }
            }
            $sid = $this->normalizeShopifyOrderIdDisplay((string) ($line->shopify_order_id ?? ''));
            if ($sid !== '' && $sid !== $orderNo && ($row['shopify_order_id'] ?? '') === '') {
                $row['shopify_order_id'] = $sid;
            }
            unset($row);
        }

        $this->attachDobaShopifyOrderIds($byOrder);

        $nonPrepaid = [];
        $prepaid = [];
        $done = [];
        $openCount = 0;
        foreach ($byOrder as $row) {
            $row['sku'] = implode(', ', $row['skus']);
            $row['display_title'] = implode(' · ', $row['titles']);
            unset($row['skus'], $row['titles']);
            if (! $row['warehouse_shipped']) {
                $openCount++;
            }
            if ($row['warehouse_shipped']) {
                $done[] = $row;
                continue;
            }
            if ($row['is_prepaid']) {
                $prepaid[] = $row;
            } else {
                $nonPrepaid[] = $row;
            }
        }

        return [
            'non_prepaid' => $nonPrepaid,
            'prepaid' => $prepaid,
            'done' => $done,
            'open_count' => $openCount,
        ];
    }

    protected function dobaOrderTypeIsPrepaid(string $orderType): bool
    {
        return strtolower(trim($orderType)) === self::DOBA_PREPAID_ORDER_TYPE;
    }

    /**
     * One export row per Doba line item in the request date range.
     *
     * @return list<array{order_id: string, order_date: string, customer_name: string, shipping_address: string, sku: string, quantity: int}>
     */
    protected function buildDobaWarehouseExportRows(string $type): array
    {
        if (! Schema::hasTable('doba_daily_data')) {
            return [];
        }

        $query = $this->scopedToLast30Days(DobaDailyData::query(), 'doba');
        if ($query === null) {
            return [];
        }

        $query->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) NOT LIKE ?", ['%CANCEL%'])
            ->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) NOT LIKE ?", ['%REFUND%'])
            ->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) NOT LIKE ?", ['%VOID%']);

        $columns = ['order_no', 'order_time', 'order_type', 'sku', 'quantity'];
        foreach ([
            'receiver_name',
            'shipping_address1',
            'shipping_address2',
            'shipping_city',
            'shipping_state',
            'shipping_postal_code',
            'shipping_country',
        ] as $col) {
            if (Schema::hasColumn('doba_daily_data', $col)) {
                $columns[] = $col;
            }
        }

        $shippedNos = [];
        if ($type === 'done' && Schema::hasTable('doba_warehouse_ships')) {
            foreach (DobaWarehouseShip::query()->where('shipped', true)->pluck('order_no') as $orderNo) {
                $key = trim((string) $orderNo);
                if ($key !== '') {
                    $shippedNos[$key] = true;
                }
            }
        }

        $lines = $query->orderByDesc('order_time')->orderBy('order_no')->get($columns);
        $rows = [];
        foreach ($lines as $line) {
            $orderNo = trim((string) ($line->order_no ?? ''));
            if ($orderNo === '') {
                continue;
            }
            $isPrepaid = $this->dobaOrderTypeIsPrepaid((string) ($line->order_type ?? ''));
            if ($type === 'done') {
                if (! isset($shippedNos[$orderNo])) {
                    continue;
                }
            } elseif ($isPrepaid !== ($type === 'prepaid')) {
                continue;
            }

            $rows[] = [
                'order_id' => $orderNo,
                'order_date' => $this->formatOrderDate($line->order_time ?? null) ?: '',
                'customer_name' => trim((string) ($line->receiver_name ?? '')),
                'shipping_address' => $this->formatDobaShippingAddress($line),
                'sku' => trim((string) ($line->sku ?? '')),
                'quantity' => max(0, (int) ($line->quantity ?? 0)),
            ];
        }

        return $rows;
    }

    protected function formatDobaShippingAddress(object $line): string
    {
        $cityStateZip = trim(implode(' ', array_filter([
            trim((string) ($line->shipping_city ?? '')),
            trim((string) ($line->shipping_state ?? '')),
            trim((string) ($line->shipping_postal_code ?? '')),
        ], static fn ($part) => $part !== '')));

        $parts = array_values(array_filter([
            trim((string) ($line->shipping_address1 ?? '')),
            trim((string) ($line->shipping_address2 ?? '')),
            $cityStateZip,
            trim((string) ($line->shipping_country ?? '')),
        ], static fn ($part) => $part !== ''));

        return implode(', ', $parts);
    }

    /**
     * Prepaid label download URL from Doba order_json (buyerPrepaidLabelList / shippingLabels).
     */
    protected function extractDobaPrepaidLabelUrl(mixed $orderJson): ?string
    {
        $data = is_array($orderJson) ? $orderJson : json_decode((string) $orderJson, true);
        if (! is_array($data)) {
            return null;
        }

        foreach (['buyerPrepaidLabelList', 'shippingLabels', 'shippingLabelList', 'shipping'] as $key) {
            if (! isset($data[$key])) {
                continue;
            }
            $found = $this->findDobaDownloadLabelUrl($data[$key]);
            if ($found !== null) {
                return $found;
            }
        }

        return $this->findDobaDownloadLabelUrl($data);
    }

    /**
     * @param  array<string, array<string, mixed>>  $byOrder
     */
    protected function attachDobaShopifyOrderIds(array &$byOrder): void
    {
        foreach ($byOrder as $orderNo => $row) {
            $sid = $this->normalizeShopifyOrderIdDisplay((string) ($row['shopify_order_id'] ?? ''));
            $byOrder[$orderNo]['shopify_order_id'] = ($sid !== '' && $sid !== $orderNo) ? $sid : '';
        }

        $lookup = $this->lookupShopifyIdsForDobaOrderNos(array_keys($byOrder));
        foreach ($this->lookupShopifyIdsFromDobaRawOrders($byOrder) as $orderNo => $info) {
            if (! isset($lookup[$orderNo]['id']) || ($lookup[$orderNo]['id'] ?? '') === '') {
                $lookup[$orderNo] = $info;
            } elseif (($lookup[$orderNo]['number'] ?? '') === '' && ($info['number'] ?? '') !== '') {
                $lookup[$orderNo]['number'] = $info['number'];
            }
        }
        foreach ($lookup as $orderNo => $info) {
            if (! isset($byOrder[$orderNo])) {
                continue;
            }
            $id = $this->normalizeShopifyOrderIdDisplay((string) ($info['id'] ?? ''));
            if ($id !== '' && $id !== $orderNo && ($byOrder[$orderNo]['shopify_order_id'] ?? '') === '') {
                $byOrder[$orderNo]['shopify_order_id'] = $id;
            }
            $number = ltrim(trim((string) ($info['number'] ?? '')), '#');
            if ($number !== '' && $number !== $orderNo) {
                $byOrder[$orderNo]['shopify_order_number'] = $number;
            }
        }

        $needNumbers = [];
        foreach ($byOrder as $row) {
            $id = trim((string) ($row['shopify_order_id'] ?? ''));
            if ($id !== '' && trim((string) ($row['shopify_order_number'] ?? '')) === '') {
                $needNumbers[$id] = true;
            }
        }
        if ($needNumbers !== [] && Schema::hasTable('shopify_raw_orders')) {
            $rows = DB::table('shopify_raw_orders')
                ->whereIn('order_id', array_keys($needNumbers))
                ->get(['order_id', 'order_number']);
            $byShopifyId = [];
            foreach ($rows as $row) {
                $id = $this->normalizeShopifyOrderIdDisplay((string) ($row->order_id ?? ''));
                $number = ltrim(trim((string) ($row->order_number ?? '')), '#');
                if ($id !== '' && $number !== '') {
                    $byShopifyId[$id] = $number;
                }
            }
            foreach ($byOrder as $orderNo => $row) {
                $id = trim((string) ($row['shopify_order_id'] ?? ''));
                if ($id !== '' && trim((string) ($row['shopify_order_number'] ?? '')) === '' && isset($byShopifyId[$id])) {
                    $byOrder[$orderNo]['shopify_order_number'] = $byShopifyId[$id];
                }
            }
        }
    }

    /**
     * @param  list<string>  $orderNos
     * @return array<string, array{id: string, number: string}>
     */
    protected function lookupShopifyIdsForDobaOrderNos(array $orderNos): array
    {
        $found = [];
        $orderNos = array_values(array_filter(array_map(
            static fn ($n) => trim((string) $n),
            $orderNos
        ), static fn ($n) => $n !== ''));
        if ($orderNos === []) {
            return [];
        }
        $wanted = array_fill_keys($orderNos, true);

        if (Schema::hasTable('shopify_orders') && Schema::hasColumn('shopify_orders', 'source_identifier')) {
            $rows = DB::table('shopify_orders')
                ->whereIn('source_identifier', $orderNos)
                ->get(['shopify_order_id', 'source_identifier']);
            foreach ($rows as $row) {
                $key = trim((string) ($row->source_identifier ?? ''));
                $id = $this->normalizeShopifyOrderIdDisplay((string) ($row->shopify_order_id ?? ''));
                if ($key !== '' && $id !== '' && $id !== $key) {
                    $found[$key] = ['id' => $id, 'number' => ''];
                }
            }
        }

        if (! Schema::hasTable('shopify_orders') || ! Schema::hasColumn('shopify_orders', 'raw_payload')) {
            return $found;
        }

        $rows = collect();
        try {
            $rows = DB::table('shopify_orders')
                ->select([
                    'shopify_order_id',
                    DB::raw("JSON_EXTRACT(raw_payload, '$.note_attributes') as note_attributes"),
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.name')) as shopify_name"),
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.id')) as payload_id"),
                ])
                ->where(function ($q) {
                    $q->where('raw_payload', 'like', '%Doba Order%')
                        ->orWhere('raw_payload', 'like', '%doba order%');
                })
                ->get();
        } catch (\Throwable) {
            $rows = DB::table('shopify_orders')
                ->select(['shopify_order_id', 'raw_payload'])
                ->where(function ($q) {
                    $q->where('raw_payload', 'like', '%Doba Order%')
                        ->orWhere('raw_payload', 'like', '%doba order%');
                })
                ->get();
        }

        foreach ($rows as $row) {
            $name = (string) ($row->shopify_name ?? '');
            $payloadId = (string) ($row->payload_id ?? '');
            $attrs = $row->note_attributes ?? null;
            if ($attrs === null && isset($row->raw_payload)) {
                $payload = json_decode((string) $row->raw_payload, true);
                $attrs = is_array($payload) ? ($payload['note_attributes'] ?? null) : null;
                $name = is_array($payload) ? (string) ($payload['name'] ?? $payload['order_number'] ?? '') : $name;
                $payloadId = is_array($payload) ? (string) ($payload['id'] ?? '') : $payloadId;
            }

            $dobaNo = $this->dobaOrderNoFromShopifyNoteAttributes($attrs);
            if ($dobaNo === null || ! isset($wanted[$dobaNo]) || isset($found[$dobaNo]['id'])) {
                continue;
            }

            $id = $this->normalizeShopifyOrderIdDisplay((string) ($row->shopify_order_id ?: $payloadId));
            $number = ltrim(trim($name), '#');
            if ($id !== '' && $id !== $dobaNo) {
                $found[$dobaNo] = [
                    'id' => $id,
                    'number' => ($number !== '' && $number !== $dobaNo) ? $number : '',
                ];
            }
        }

        return $found;
    }

    /**
     * Match open Doba warehouse rows to Shopify via the Doba sales-channel
     * rows in shopify_raw_orders (SKU + calendar date). Used because CRM
     * shopify_orders note-attributes are often stale.
     *
     * @param  array<string, array<string, mixed>>  $byOrder
     * @return array<string, array{id: string, number: string}>
     */
    protected function lookupShopifyIdsFromDobaRawOrders(array $byOrder): array
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return [];
        }

        $skus = [];
        $dates = [];
        $missing = [];
        foreach ($byOrder as $orderNo => $row) {
            if (trim((string) ($row['shopify_order_id'] ?? '')) !== '') {
                continue;
            }
            $missing[] = $orderNo;
            foreach ($this->dobaRowSkus($row) as $sku) {
                $skus[$sku] = true;
            }
            $day = $this->dobaRowCalendarDate($row);
            if ($day !== null) {
                $dates[$day] = true;
            }
        }
        if ($missing === [] || $skus === [] || $dates === []) {
            return [];
        }

        ksort($dates);
        $dayList = array_keys($dates);
        $from = date('Y-m-d', strtotime($dayList[0].' -1 day'));
        $to = date('Y-m-d', strtotime($dayList[array_key_last($dayList)].' +1 day'));

        $raw = DB::table('shopify_raw_orders')
            ->select(['order_id', 'order_number', 'sku', 'order_date'])
            ->where(function ($q) {
                $q->where('source_name', '145019994113')
                    ->orWhere('source_name', 'like', '%doba%')
                    ->orWhere('tags', 'like', '%doba%')
                    ->orWhere('tags', 'like', '%Doba%');
            })
            ->whereIn('sku', array_keys($skus))
            ->whereBetween('order_date', [$from, $to])
            ->get();

        $bySkuDate = [];
        $idNumber = [];
        foreach ($raw as $row) {
            $sku = $this->normalizeDobaSkuKey((string) ($row->sku ?? ''));
            $day = substr((string) ($row->order_date ?? ''), 0, 10);
            $id = $this->normalizeShopifyOrderIdDisplay((string) ($row->order_id ?? ''));
            if ($sku === '' || $day === '' || $id === '') {
                continue;
            }
            $bySkuDate[$sku.'|'.$day][$id] = true;
            $number = ltrim(trim((string) ($row->order_number ?? '')), '#');
            if ($number !== '') {
                $idNumber[$id] = $number;
            }
        }

        $found = [];
        $usedIds = [];
        foreach ($missing as $orderNo) {
            $row = $byOrder[$orderNo] ?? [];
            $day = $this->dobaRowCalendarDate($row);
            $orderSkus = $this->dobaRowSkus($row);
            if ($day === null || $orderSkus === []) {
                continue;
            }
            $candidates = null;
            foreach ($orderSkus as $sku) {
                $ids = array_keys($bySkuDate[$sku.'|'.$day] ?? []);
                if ($ids === []) {
                    $prev = date('Y-m-d', strtotime($day.' -1 day'));
                    $next = date('Y-m-d', strtotime($day.' +1 day'));
                    $ids = array_keys(array_merge(
                        $bySkuDate[$sku.'|'.$prev] ?? [],
                        $bySkuDate[$sku.'|'.$next] ?? []
                    ));
                }
                $candidates = $candidates === null
                    ? $ids
                    : array_values(array_intersect($candidates, $ids));
            }
            $candidates = array_values(array_unique($candidates ?? []));
            $candidates = array_values(array_filter($candidates, static fn ($id) => ! isset($usedIds[$id])));
            if (count($candidates) !== 1) {
                continue;
            }
            $id = $candidates[0];
            $usedIds[$id] = true;
            $found[$orderNo] = [
                'id' => $id,
                'number' => $idNumber[$id] ?? '',
            ];
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    protected function dobaRowSkus(array $row): array
    {
        $skus = [];
        if (! empty($row['skus']) && is_array($row['skus'])) {
            $skus = $row['skus'];
        } elseif (trim((string) ($row['sku'] ?? '')) !== '') {
            $skus = preg_split('/\s*,\s*/', (string) $row['sku']) ?: [];
        }
        $out = [];
        foreach ($skus as $sku) {
            $key = $this->normalizeDobaSkuKey((string) $sku);
            if ($key !== '') {
                $out[] = $key;
            }
        }

        return array_values(array_unique($out));
    }

    protected function dobaRowCalendarDate(array $row): ?string
    {
        $raw = trim((string) ($row['order_date'] ?? ''));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    protected function normalizeDobaSkuKey(string $sku): string
    {
        return strtoupper(trim($sku));
    }

    protected function dobaOrderNoFromShopifyNoteAttributes(mixed $attrs): ?string
    {
        if (is_string($attrs)) {
            $attrs = json_decode($attrs, true);
        }
        if (! is_array($attrs)) {
            return null;
        }

        foreach ($attrs as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $name = strtolower(trim((string) ($attr['name'] ?? $attr['key'] ?? '')));
            $name = rtrim($name, '.');
            if (! in_array($name, ['doba order no', 'doba order number', 'doba_order_no'], true)) {
                continue;
            }
            $value = trim((string) ($attr['value'] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function normalizeShopifyOrderIdDisplay(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('#/Order/(\d+)#', $raw, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/^#?(\d+)$/', $raw, $m) === 1) {
            return $m[1];
        }

        return $raw;
    }

    protected function findDobaDownloadLabelUrl(mixed $node, int $depth = 0): ?string
    {
        if ($depth > 8) {
            return null;
        }
        if (is_string($node)) {
            $url = trim($node);
            if ($url !== '' && preg_match('#https?://(?:[a-z0-9.-]+\.)?doba\.com/\S+#i', $url, $m)) {
                return rtrim($m[0], ')",\'>');
            }

            return null;
        }
        if (! is_array($node)) {
            return null;
        }

        foreach (['url', 'labelUrl', 'label_url', 'prepaidLabelUrl', 'downloadUrl', 'fileUrl', 'link', 'labelLink'] as $key) {
            if (! isset($node[$key])) {
                continue;
            }
            $found = $this->findDobaDownloadLabelUrl($node[$key], $depth + 1);
            if ($found !== null) {
                return $found;
            }
        }

        foreach ($node as $value) {
            $found = $this->findDobaDownloadLabelUrl($value, $depth + 1);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
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
                if ($tracking === null || $tracking === '') {
                    $tracking = isset($n['tracking_number']) ? trim((string) $n['tracking_number']) ?: null : null;
                }
                $company = isset($n['tracking_company']) && trim((string) $n['tracking_company']) !== ''
                    ? trim((string) $n['tracking_company'])
                    : $this->extractCarrierFromPayload($n['raw_payload'] ?? null);
                $company = TrackingCarrierGuesser::fill($company, $tracking);
                $apiOrderId = trim((string) ($n['order_id'] ?? ''));
                $orderNumber = trim((string) ($n['order_number'] ?? ''));
                // Prefer human-readable order number (e.g. Faire display_id N8PA3FG3F8)
                // over internal API ids (e.g. bo_n8pa3fg3f8).
                $displayOrderId = $orderNumber !== '' ? $orderNumber : $apiOrderId;
                $showId = (int) ($n['show_id'] ?? $order->id);
                $hasShippingLabel = ! empty($n['has_shipping_label']);
                if ($slug === 'wayfair') {
                    $slip = trim((string) ($order->packing_slip_url ?? ''));
                    $carrierCode = trim((string) ($order->carrier_code ?? ''));
                    if ($slip !== '' || $carrierCode !== '') {
                        $hasShippingLabel = true;
                    }
                    if (trim((string) $company) === '' && $carrierCode !== '') {
                        $company = $carrierCode;
                    }
                }

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
                    'tracking_number' => $tracking,
                    'tracking_company' => $company,
                    'tracking_url' => null,
                    'shipment_status' => null,
                    'shipment_status_detail' => null,
                    'has_shipping_label' => $hasShippingLabel,
                    'shopify_fulfillment_status' => null,
                    'status' => $statusRaw,
                    'status_label' => $useOriginalStatus
                        ? ($statusRaw !== '' ? $statusRaw : '—')
                        : $this->orderStatusLabel($slug, $statusRaw),
                    'sku' => (string) ($n['sku'] ?? ''),
                    'catalog_sku' => (string) ($n['catalog_sku'] ?? ''),
                    'display_sku' => (string) ($n['display_sku'] ?? ''),
                    'display_title' => (string) ($n['display_title'] ?? ''),
                    'INV' => 0,
                    'label' => null,
                    'quantity' => (int) ($n['quantity'] ?? 1),
                    'amount' => $this->resolveNormalizedAmount($slug, $order, $n['amount'] ?? null),
                    'groi_pct' => null,
                    'gpft_pct' => null,
                    'nroi_pct' => null,
                    'npft_pct' => null,
                    'import_status' => (string) ($n['import_status'] ?? ''),
                    'shopify_order_id' => (string) ($n['shopify_order_id'] ?? ''),
                    'is_cancelled' => $this->normalizedOrderIsCancelled($slug, $n),
                    'ch_orders_link' => $meta['ch_orders_link'],
                    'orders_url' => $ordersUrl,
                    'show_id' => $showId,
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
        // Tracking comes from channel APIs / order tables only — never Shopify fulfillments.
        // Temu OpenAPI tracking on temu*_orders is already on the row; Sites sheets only fill gaps.
        $rows = $this->attachTemuSitesTrackingToOrderRows($rows);
        $rows = $this->attachShopifyTrackingToOrderRows($rows);
        $rows = $this->attachShipmentStatusToOrderRows($rows);
        $rows = $this->attachSofShipmentOverridesToOrderRows($rows);
        $rows = $this->fillCarrierFromTrackingNumbers($rows);

        try {
            $rows = $this->attachCostShipDetailsToRows($rows);
        } catch (\Throwable) {
            // Keep orders even if product-master cost lookup fails.
        }

        return $this->attachSkuSiteProfitPctToRows($rows);
    }

    /**
     * Fallback: fill missing Temu / Temu 2 tracking from Sites daily sheets.
     * Primary source is temu:pull-tracking / temu2:pull-tracking → temu*_orders columns.
     * Does not read or write Shopify. Does not overwrite API-populated tracking.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachTemuSitesTrackingToOrderRows(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $temuKeys = [];
        $temu2Keys = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['mm_slug'] ?? '');
            if ($slug !== 'temu' && $slug !== 'temu2') {
                continue;
            }
            // Skip rows that already have API tracking.
            if (trim((string) ($row['tracking_number'] ?? '')) !== '') {
                continue;
            }
            $candidates = [
                trim((string) ($row['order_id_api'] ?? '')),
                trim((string) ($row['order_id'] ?? '')),
                trim((string) ($row['order_number'] ?? '')),
            ];
            foreach ($candidates as $key) {
                if ($key === '') {
                    continue;
                }
                if ($slug === 'temu2') {
                    $temu2Keys[$key] = true;
                } else {
                    $temuKeys[$key] = true;
                }
            }
        }

        $temuMap = $temuKeys !== []
            ? $this->loadTemuSitesTrackingMap(array_keys($temuKeys), [
                'temu_daily_data_l7',
                'temu_daily_data_l60',
                'temu_daily_data_l70',
            ])
            : ['by_order' => [], 'by_order_sku' => []];
        $temu2Map = $temu2Keys !== []
            ? $this->loadTemuSitesTrackingMap(array_keys($temu2Keys), [
                'temu2_daily_data',
                'temu2_daily_data_l7',
                'temu2_daily_data_l60',
            ])
            : ['by_order' => [], 'by_order_sku' => []];

        foreach ($rows as &$row) {
            $slug = (string) ($row['mm_slug'] ?? '');
            if ($slug !== 'temu' && $slug !== 'temu2') {
                continue;
            }
            if (trim((string) ($row['tracking_number'] ?? '')) !== '') {
                continue;
            }
            $map = $slug === 'temu2' ? $temu2Map : $temuMap;
            $sku = trim((string) ($row['sku'] ?? ''));
            $hit = null;
            foreach ([
                trim((string) ($row['order_id_api'] ?? '')),
                trim((string) ($row['order_id'] ?? '')),
                trim((string) ($row['order_number'] ?? '')),
            ] as $orderKey) {
                if ($orderKey === '') {
                    continue;
                }
                if ($sku !== '' && isset($map['by_order_sku'][$orderKey.'|'.$sku])) {
                    $hit = $map['by_order_sku'][$orderKey.'|'.$sku];
                    break;
                }
                if (isset($map['by_order'][$orderKey])) {
                    $hit = $map['by_order'][$orderKey];
                    break;
                }
            }
            if ($hit === null) {
                continue;
            }
            if (($hit['tracking_number'] ?? '') !== '') {
                $row['tracking_number'] = $hit['tracking_number'];
            }
            if (($hit['carrier'] ?? '') !== '' && trim((string) ($row['tracking_company'] ?? '')) === '') {
                $row['tracking_company'] = $hit['carrier'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  list<string>  $orderIds
     * @param  list<string>  $tables
     * @return array{by_order: array<string, array{tracking_number: string, carrier: string}>, by_order_sku: array<string, array{tracking_number: string, carrier: string}>}
     */
    protected function loadTemuSitesTrackingMap(array $orderIds, array $tables): array
    {
        $byOrder = [];
        $byOrderSku = [];
        if ($orderIds === []) {
            return ['by_order' => $byOrder, 'by_order_sku' => $byOrderSku];
        }

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                $query = DB::table($table)
                    ->select(['order_id', 'contribution_sku', 'tracking_number', 'carrier', 'id'])
                    ->whereIn('order_id', $orderIds)
                    ->whereNotNull('tracking_number')
                    ->where('tracking_number', '!=', '')
                    ->orderByDesc('id');

                foreach ($query->get() as $srow) {
                    $oid = trim((string) ($srow->order_id ?? ''));
                    $tn = trim((string) ($srow->tracking_number ?? ''));
                    if ($oid === '' || $tn === '') {
                        continue;
                    }
                    $carrier = trim((string) ($srow->carrier ?? ''));
                    $payload = ['tracking_number' => $tn, 'carrier' => $carrier];
                    $sku = trim((string) ($srow->contribution_sku ?? ''));
                    if ($sku !== '') {
                        $skuKey = $oid.'|'.$sku;
                        if (! isset($byOrderSku[$skuKey])) {
                            $byOrderSku[$skuKey] = $payload;
                        }
                    }
                    if (! isset($byOrder[$oid])) {
                        $byOrder[$oid] = $payload;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return ['by_order' => $byOrder, 'by_order_sku' => $byOrderSku];
    }

    /**
     * Overlay tracking saved locally (shopify_raw_orders cache and pulled label trackings).
     * Reads the database only — does not call the Shopify API.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachShopifyTrackingToOrderRows(array $rows): array
    {
        if ($rows === [] || ! Schema::hasTable('shopify_raw_orders')) {
            return $rows;
        }

        $needIds = [];
        $needNumbers = [];
        foreach ($rows as $row) {
            if (trim((string) ($row['tracking_number'] ?? '')) !== '') {
                continue;
            }
            $sid = trim((string) ($row['shopify_order_id'] ?? ''));
            $numericId = $this->shopifyNumericOrderId($sid);
            if ($numericId !== null) {
                $needIds[$numericId] = true;
            }
            foreach (['order_number', 'order_id', 'order_id_api'] as $k) {
                $v = trim((string) ($row[$k] ?? ''));
                if ($v === '') {
                    continue;
                }
                foreach ($this->shopifyTrackingLookupKeys($v) as $key) {
                    $needNumbers[$key] = true;
                }
            }
        }

        if ($needIds === [] && $needNumbers === []) {
            return $rows;
        }

        $byShopifyId = [];
        $byNumber = [];
        try {
            $q = DB::table('shopify_raw_orders')
                ->select(['order_id', 'order_number', 'tracking_number', 'tracking_company', 'fulfillment_status'])
                ->where(function ($outer) {
                    $outer->where(function ($t) {
                        $t->whereNotNull('tracking_number')->where('tracking_number', '!=', '');
                    })->orWhereIn('fulfillment_status', ['fulfilled', 'partial', 'partially_fulfilled']);
                });
            $q->where(function ($inner) use ($needIds, $needNumbers) {
                if ($needIds !== []) {
                    $inner->orWhereIn('order_id', array_keys($needIds));
                }
                if ($needNumbers !== []) {
                    $inner->orWhereIn('order_number', array_keys($needNumbers));
                }
            });
            foreach ($q->get() as $srow) {
                $tn = trim((string) ($srow->tracking_number ?? ''));
                $ff = strtolower(trim((string) ($srow->fulfillment_status ?? '')));
                if ($tn === '' && ! in_array($ff, ['fulfilled', 'partial', 'partially_fulfilled'], true)) {
                    continue;
                }
                $payload = [
                    'tracking_number' => $tn,
                    'tracking_company' => trim((string) ($srow->tracking_company ?? '')) ?: null,
                    'fulfillment_status' => $ff !== '' ? $ff : null,
                ];
                $oid = (int) ($srow->order_id ?? 0);
                if ($oid > 0 && ! isset($byShopifyId[$oid])) {
                    $byShopifyId[$oid] = $payload;
                }
                $num = trim((string) ($srow->order_number ?? ''));
                if ($num !== '' && ! isset($byNumber[$num])) {
                    $byNumber[$num] = $payload;
                }
                foreach ($this->shopifyTrackingLookupKeys($num) as $key) {
                    if (! isset($byNumber[$key])) {
                        $byNumber[$key] = $payload;
                    }
                }
            }
        } catch (\Throwable) {
            return $rows;
        }

        foreach ($rows as &$row) {
            if (trim((string) ($row['tracking_number'] ?? '')) !== '') {
                continue;
            }
            $hit = null;
            $sid = trim((string) ($row['shopify_order_id'] ?? ''));
            $numericId = $this->shopifyNumericOrderId($sid);
            if ($numericId !== null && isset($byShopifyId[$numericId])) {
                $hit = $byShopifyId[$numericId];
            }
            if ($hit === null) {
                foreach (['order_number', 'order_id', 'order_id_api'] as $k) {
                    $v = trim((string) ($row[$k] ?? ''));
                    if ($v === '') {
                        continue;
                    }
                    foreach ($this->shopifyTrackingLookupKeys($v) as $key) {
                        if (isset($byNumber[$key])) {
                            $hit = $byNumber[$key];
                            break 2;
                        }
                    }
                }
            }
            if ($hit === null) {
                continue;
            }
            if (trim((string) ($row['tracking_number'] ?? '')) === '' && ($hit['tracking_number'] ?? '') !== '') {
                $row['tracking_number'] = $hit['tracking_number'];
            }
            if (trim((string) ($row['tracking_company'] ?? '')) === '' && ! empty($hit['tracking_company'])) {
                $row['tracking_company'] = $hit['tracking_company'];
            }
            if (! empty($hit['fulfillment_status'])) {
                $row['shopify_fulfillment_status'] = $hit['fulfillment_status'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<string>
     */
    protected function shopifyTrackingLookupKeys(string $value): array
    {
        $v = trim($value);
        if ($v === '') {
            return [];
        }
        $plain = ltrim($v, '#');
        $compact = preg_replace('/\s+/', '', $plain) ?? $plain;
        $keys = [$v, $plain, '#'.$plain, $compact, '#'.$compact];
        $lower = strtolower($plain);
        if ($plain !== '' && ! str_starts_with($lower, 'amz')) {
            $keys[] = 'Amz'.$plain;
            $keys[] = '#Amz'.$plain;
            $keys[] = 'Amz'.$compact;
            $keys[] = '#Amz'.$compact;
        }

        return array_values(array_unique(array_filter($keys, static fn ($k) => $k !== '')));
    }

    protected function shopifyNumericOrderId(string $shopifyOrderId): ?int
    {
        $sid = trim($shopifyOrderId);
        if ($sid === '') {
            return null;
        }
        if (preg_match('/(\d{6,})$/', $sid, $m) === 1) {
            return (int) $m[1];
        }

        return ctype_digit($sid) ? (int) $sid : null;
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

        $fromMarketplace = $this->collectOrderRows(
            fn (string $slug) => $this->scopedToLast30Days($this->fulfilledOrdersQuery($slug), $slug),
            true
        );
        $fromPendingLabeled = [];
        foreach ($this->pendingAlreadyLabeledRows() as $row) {
            $row['status_label'] = 'Label Created';
            $fromPendingLabeled[] = $row;
        }
        $this->cachedLabelCreatedRows = $this->mergeOrderRowsById($fromMarketplace, $fromPendingLabeled);

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
     * True when the order date is within the last 24 hours (Eastern).
     * Date-only values are treated as the start of that EST/EDT day.
     *
     * @param  array<string, mixed>  $row
     */
    protected function rowIsWithinLast24Hours(array $row): bool
    {
        $raw = trim((string) ($row['order_date'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($row['updated_at'] ?? ''));
        }
        if ($raw === '') {
            return true;
        }

        try {
            $tz = $this->sofTimezone();
            $dt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1
                ? Carbon::createFromFormat('Y-m-d', $raw, $tz)->startOfDay()
                : Carbon::parse($raw, $tz)->timezone($tz);

            return $dt->gte(now($tz)->subHours(24));
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * True when the order date (else updated_at) is older than $hours (Eastern).
     *
     * @param  array<string, mixed>  $row
     */
    protected function rowIsOlderThanHours(array $row, int $hours): bool
    {
        $raw = trim((string) ($row['order_date'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($row['updated_at'] ?? ''));
        }
        if ($raw === '') {
            return false;
        }

        try {
            $tz = $this->sofTimezone();
            $dt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1
                ? Carbon::createFromFormat('Y-m-d', $raw, $tz)->startOfDay()
                : Carbon::parse($raw, $tz)->timezone($tz);

            return $dt->lt(now($tz)->subHours($hours));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * In Transit: scan-pending (no carrier scan) for more than 36 hours — flag and pin to top.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function annotateInTransitScanPendingAlerts(array $rows): array
    {
        foreach ($rows as &$row) {
            $scanPending = ! $this->carrierStatusHasLeftLabelCreated($row['shipment_status'] ?? null);
            $row['scan_pending_over_36h'] = ($scanPending && $this->rowIsOlderThanHours($row, 36)) ? 1 : 0;
        }
        unset($row);

        usort($rows, static function (array $a, array $b): int {
            $aLate = (int) ($a['scan_pending_over_36h'] ?? 0);
            $bLate = (int) ($b['scan_pending_over_36h'] ?? 0);
            if ($aLate !== $bLate) {
                return $bLate <=> $aLate;
            }
            $ak = (string) ($a['updated_at'] ?? $a['order_date'] ?? '');
            $bk = (string) ($b['updated_at'] ?? $b['order_date'] ?? '');

            return strcmp($bk, $ak);
        });

        return array_values($rows);
    }

    /**
     * Label Created / No Scan: last 24 hours, carrier has not scanned yet.
     *
     * @return list<array<string, mixed>>
     */
    protected function labelCreatedNoScanRows(): array
    {
        return array_values(array_filter(
            $this->labelCreatedOrderRows(),
            fn (array $r) => ! $this->carrierStatusHasLeftLabelCreated($r['shipment_status'] ?? null)
                && $this->rowIsWithinLast24Hours($r)
        ));
    }

    /**
     * Labeled more than 24 hours ago with no carrier scan yet — warehouse already scanned these.
     *
     * @return list<array<string, mixed>>
     */
    protected function labelCreatedAssumedScannedRows(): array
    {
        $rows = [];
        foreach ($this->labelCreatedOrderRows() as $row) {
            if ($this->carrierStatusHasLeftLabelCreated($row['shipment_status'] ?? null)) {
                continue;
            }
            if ($this->rowIsWithinLast24Hours($row)) {
                continue;
            }
            $row['status_label'] = 'In Transit';
            $rows[] = $row;
        }

        return $rows;
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
     * Overlay carrier shipment_status onto order rows that already have a tracking number.
     * Matches by tracking number from carrier_tracking_statuses (channel trackings — no Shopify API).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachShipmentStatusToOrderRows(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $trackingKeys = [];
        foreach ($rows as $row) {
            $tn = strtoupper(preg_replace('/\s+/', '', (string) ($row['tracking_number'] ?? '')) ?? '');
            if ($tn !== '' && $this->looksLikeCarrierTrackingNumber($tn)) {
                $trackingKeys[$tn] = true;
            }
        }

        if ($trackingKeys === []) {
            return $rows;
        }

        $byTracking = [];
        $keys = array_keys($trackingKeys);

        try {
            if (Schema::hasTable('carrier_tracking_statuses')) {
                foreach (array_chunk($keys, 500) as $chunk) {
                    $query = DB::table('carrier_tracking_statuses')
                        ->select(['tracking_number', 'shipment_status', 'shipment_status_detail', 'carrier'])
                        ->whereNotNull('shipment_status')
                        ->where('shipment_status', '!=', '')
                        ->whereIn('tracking_number', $chunk);
                    foreach ($query->get() as $srow) {
                        $status = trim((string) ($srow->shipment_status ?? ''));
                        if ($status === '') {
                            continue;
                        }
                        $tn = strtoupper(preg_replace('/\s+/', '', (string) ($srow->tracking_number ?? '')) ?? '');
                        if ($tn === '') {
                            continue;
                        }
                        $byTracking[$tn] = [
                            'shipment_status' => $status,
                            'shipment_status_detail' => $srow->shipment_status_detail ?? null,
                            'tracking_company' => $srow->carrier ?? null,
                        ];
                    }
                }
            }

            // Legacy fallback for statuses already on shopify_raw_orders (no Shopify API).
            if (Schema::hasTable('shopify_raw_orders')) {
                $missing = array_values(array_filter($keys, fn ($k) => ! isset($byTracking[$k])));
                foreach (array_chunk($missing, 500) as $chunk) {
                    if ($chunk === []) {
                        break;
                    }
                    $query = DB::table('shopify_raw_orders')
                        ->select(['tracking_number', 'shipment_status', 'shipment_status_detail', 'tracking_company'])
                        ->whereNotNull('shipment_status')
                        ->where('shipment_status', '!=', '')
                        ->whereIn('tracking_number', $chunk);
                    foreach ($query->get() as $srow) {
                        $status = trim((string) ($srow->shipment_status ?? ''));
                        if ($status === '') {
                            continue;
                        }
                        $tn = strtoupper(preg_replace('/\s+/', '', (string) ($srow->tracking_number ?? '')) ?? '');
                        if ($tn === '' || isset($byTracking[$tn])) {
                            continue;
                        }
                        $byTracking[$tn] = [
                            'shipment_status' => $status,
                            'shipment_status_detail' => $srow->shipment_status_detail ?? null,
                            'tracking_company' => $srow->tracking_company ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $tn = strtoupper(preg_replace('/\s+/', '', (string) ($row['tracking_number'] ?? '')) ?? '');
            $match = ($tn !== '' && $this->looksLikeCarrierTrackingNumber($tn) && isset($byTracking[$tn]))
                ? $byTracking[$tn]
                : null;
            if ($match === null) {
                $kept = trim((string) ($row['shipment_status'] ?? ''));
                if ($kept !== '') {
                    $carrierLabel = $this->carrierShipmentStatusLabel($kept);
                    if ($carrierLabel !== null) {
                        $row['status_label'] = $carrierLabel;
                    }
                }
                continue;
            }

            $row['shipment_status'] = $match['shipment_status'];
            $row['shipment_status_detail'] = $match['shipment_status_detail'];
            if (trim((string) ($row['tracking_company'] ?? '')) === '') {
                $fromStatus = trim((string) ($match['tracking_company'] ?? ''));
                if ($fromStatus !== '') {
                    $row['tracking_company'] = $fromStatus;
                }
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

    public const SOF_ONE_TIME_NO_TRACKING_SENTINEL_SLUG = '_system';

    public const SOF_ONE_TIME_NO_TRACKING_SENTINEL_KEY = 'in_transit_no_tracking_v1';

    /**
     * Overlay one-time / manual SOF shipment statuses (including orders with no tracking).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachSofShipmentOverridesToOrderRows(array $rows): array
    {
        if ($rows === [] || ! Schema::hasTable('sof_shipment_status_overrides')) {
            return $rows;
        }

        $keys = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $keys[$id] = true;
            }
        }
        if ($keys === []) {
            return $rows;
        }

        $byKey = [];
        try {
            foreach (array_chunk(array_keys($keys), 500) as $chunk) {
                $query = DB::table('sof_shipment_status_overrides')
                    ->whereIn('order_key', $chunk)
                    ->where('mm_slug', '!=', self::SOF_ONE_TIME_NO_TRACKING_SENTINEL_SLUG);
                foreach ($query->get() as $orow) {
                    $key = trim((string) ($orow->order_key ?? ''));
                    if ($key === '') {
                        continue;
                    }
                    $byKey[$key] = $orow;
                }
            }
        } catch (\Throwable) {
            return $rows;
        }

        if ($byKey === []) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $key = trim((string) ($row['id'] ?? ''));
            $orow = $byKey[$key] ?? null;
            if ($orow === null) {
                continue;
            }
            $status = trim((string) ($orow->shipment_status ?? ''));
            if ($status === '') {
                continue;
            }
            $row['shipment_status'] = $status;
            $detail = trim((string) ($orow->shipment_status_detail ?? ''));
            if ($detail !== '') {
                $row['shipment_status_detail'] = $detail;
            }
            $carrierLabel = $this->carrierShipmentStatusLabel($status);
            if ($carrierLabel !== null) {
                $row['status_label'] = $carrierLabel;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function excludeCarrierDeliveredRows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $r) => ($r['shipment_status'] ?? '') !== ShipmentTrackingService::STATUS_DELIVERED
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function onlyCarrierDeliveredRows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $r) => ($r['shipment_status'] ?? '') === ShipmentTrackingService::STATUS_DELIVERED
        ));
    }

    /**
     * Marketplace In Transit query rows (Doba / PP / Macy / Best Buy), last 30 days.
     *
     * @return list<array<string, mixed>>
     */
    protected function inTransitMarketplaceOrderRows(): array
    {
        return $this->collectOrderRows(
            fn (string $slug) => $this->scopedToLast30Days($this->inTransitOrdersQuery($slug), $slug),
            true
        );
    }

    /**
     * Full In Transit tab membership before Delivered-override filtering.
     *
     * @return list<array<string, mixed>>
     */
    protected function inTransitOrderRows(): array
    {
        $rows = $this->inTransitMarketplaceOrderRows();
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
        $fromOlderLabels = $this->labelCreatedAssumedScannedRows();

        return $this->mergeOrderRowsById(
            $this->mergeOrderRowsById($rows, $fromCarrier),
            $fromOlderLabels
        );
    }

    /**
     * ONE-TIME: persist Delivered for current In Transit rows that have no tracking ID.
     *
     * @return array{success: bool, message: string, matched?: int, written?: int, dry_run?: bool}
     */
    public function markInTransitNoTrackingDeliveredOnce(bool $dryRun = false, bool $force = false): array
    {
        if (! Schema::hasTable('sof_shipment_status_overrides')) {
            return [
                'success' => false,
                'message' => 'sof_shipment_status_overrides table missing — run migrations.',
            ];
        }

        $already = DB::table('sof_shipment_status_overrides')
            ->where('mm_slug', self::SOF_ONE_TIME_NO_TRACKING_SENTINEL_SLUG)
            ->where('order_key', self::SOF_ONE_TIME_NO_TRACKING_SENTINEL_KEY)
            ->exists();
        if ($already && ! $force) {
            return [
                'success' => false,
                'message' => 'Already ran once. In Transit rows without tracking were marked Delivered. Use --force only if you intend to mark the current set again.',
                'matched' => 0,
                'written' => 0,
            ];
        }

        $this->forgetSofOrderRowCaches();
        $rows = $this->inTransitOrderRows();
        $targets = [];
        foreach ($rows as $row) {
            if (trim((string) ($row['tracking_number'] ?? '')) !== '') {
                continue;
            }
            $key = trim((string) ($row['id'] ?? ''));
            $slug = trim((string) ($row['mm_slug'] ?? ''));
            if ($key === '' || $slug === '') {
                continue;
            }
            $targets[] = $row;
        }

        if ($dryRun) {
            return [
                'success' => true,
                'message' => 'Dry run: '.count($targets).' In Transit row(s) with no tracking would be marked Delivered.',
                'matched' => count($targets),
                'written' => 0,
                'dry_run' => true,
            ];
        }

        $now = now();
        $written = 0;
        foreach ($targets as $row) {
            DB::table('sof_shipment_status_overrides')->updateOrInsert(
                [
                    'mm_slug' => (string) $row['mm_slug'],
                    'order_key' => (string) $row['id'],
                ],
                [
                    'order_id' => mb_substr((string) ($row['order_id'] ?? $row['order_id_api'] ?? ''), 0, 128) ?: null,
                    'shipment_status' => ShipmentTrackingService::STATUS_DELIVERED,
                    'shipment_status_detail' => 'One-time: In Transit with no tracking → Delivered',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $written++;
        }

        DB::table('sof_shipment_status_overrides')->updateOrInsert(
            [
                'mm_slug' => self::SOF_ONE_TIME_NO_TRACKING_SENTINEL_SLUG,
                'order_key' => self::SOF_ONE_TIME_NO_TRACKING_SENTINEL_KEY,
            ],
            [
                'order_id' => null,
                'shipment_status' => ShipmentTrackingService::STATUS_DELIVERED,
                'shipment_status_detail' => 'Sentinel: one-time In Transit no-tracking mark completed ('.$written.' rows).',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $this->forgetSofOrderRowCaches();

        return [
            'success' => true,
            'message' => 'Marked '.$written.' In Transit order(s) with no tracking as Delivered. This will not run again.',
            'matched' => count($targets),
            'written' => $written,
            'dry_run' => false,
        ];
    }

    /**
     * Marketplace-status pending rows (UNSHIPPED / NOT_STARTED / …) with tracking overlay.
     *
     * @return list<array<string, mixed>>
     */
    protected function pendingMarketplaceOrderRows(): array
    {
        if ($this->cachedPendingRows !== null) {
            return $this->cachedPendingRows;
        }

        $this->cachedPendingRows = $this->collectOrderRows(
            fn (string $slug) => $this->scopedToLast30Days($this->pendingOrdersQuery($slug), $slug),
            false
        );

        return $this->cachedPendingRows;
    }

    /**
     * True when the warehouse already created a label (Veeqo/GOFO tracking, packing slip, or Shopify fulfilled).
     *
     * @param  array<string, mixed>  $row
     */
    protected function rowHasLabelTracking(array $row): bool
    {
        if (! empty($row['has_shipping_label'])) {
            return true;
        }

        $tn = trim((string) ($row['tracking_number'] ?? ''));
        if ($tn !== '' && $this->looksLikeCarrierTrackingNumber($tn)) {
            return true;
        }

        $ff = strtolower(trim((string) ($row['shopify_fulfillment_status'] ?? '')));
        if (in_array($ff, ['fulfilled', 'partial', 'partially_fulfilled'], true)) {
            return true;
        }

        // Already pushed to Shopify and the Shopify copy is no longer unfulfilled.
        $import = strtolower(trim((string) ($row['import_status'] ?? '')));
        if (
            in_array($import, ['imported', 'success', 'pushed'], true)
            && trim((string) ($row['shopify_order_id'] ?? '')) !== ''
            && $ff !== ''
            && ! in_array($ff, ['unfulfilled', 'null', 'none'], true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * True when marketplace status / payload says the order was cancelled (or voided).
     *
     * @param  array<string, mixed>  $normalized
     */
    protected function normalizedOrderIsCancelled(string $slug, array $normalized): bool
    {
        foreach (['status', 'import_status'] as $key) {
            if ($this->statusTextLooksCancelled((string) ($normalized[$key] ?? ''))) {
                return true;
            }
        }

        return $this->payloadLooksCancelled($normalized['raw_payload'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function orderRowIsCancelled(array $row): bool
    {
        if (! empty($row['is_cancelled'])) {
            return true;
        }

        foreach (['status', 'status_label', 'import_status'] as $key) {
            if ($this->statusTextLooksCancelled((string) ($row[$key] ?? ''))) {
                return true;
            }
        }

        return $this->payloadLooksCancelled($row['raw_payload'] ?? null);
    }

    protected function statusTextLooksCancelled(string $raw): bool
    {
        $u = strtoupper(str_replace(['-', ' '], '_', trim($raw)));
        if ($u === '') {
            return false;
        }
        if (in_array($u, ['CANCELED', 'CANCELLED', 'CANCEL_REQUESTED', 'CANCELLATION_REQUESTED', 'VOID', 'VOIDED', 'INVALID'], true)) {
            return true;
        }

        return str_contains($u, 'CANCEL');
    }

    protected function payloadLooksCancelled(mixed $payload): bool
    {
        $payload = AmazonOrder::decodeRawPayload($payload);
        if ($payload === []) {
            return false;
        }

        $cancelStatus = is_array($payload['cancelStatus'] ?? null) ? $payload['cancelStatus'] : [];
        $cancelStatusAlt = is_array($payload['cancel_status'] ?? null) ? $payload['cancel_status'] : [];
        $cancelState = $cancelStatus['cancelState']
            ?? $cancelStatusAlt['cancel_state']
            ?? $payload['cancelState']
            ?? $payload['cancel_state']
            ?? '';
        if ($this->statusTextLooksCancelled((string) $cancelState)) {
            return true;
        }

        $paymentSummary = is_array($payload['paymentSummary'] ?? null) ? $payload['paymentSummary'] : [];
        $payment = (string) (
            $payload['orderPaymentStatus']
            ?? $paymentSummary['paymentStatus']
            ?? $payload['payment_status']
            ?? ''
        );
        $payU = strtoupper($payment);
        if (str_contains($payU, 'FULLY_REFUNDED') || $payU === 'REFUNDED') {
            return true;
        }

        foreach (['OrderStatus', 'orderStatus', 'order_status', 'orderFulfillmentStatus'] as $key) {
            if (isset($payload[$key]) && $this->statusTextLooksCancelled((string) $payload[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Still waiting on the warehouse — marketplace unfulfilled and no tracking number yet.
     *
     * @return list<array<string, mixed>>
     */
    protected function warehousePendingOrderRows(): array
    {
        return array_values(array_filter(
            $this->pendingMarketplaceOrderRows(),
            fn (array $r) => ! $this->rowHasLabelTracking($r) && ! $this->orderRowIsCancelled($r)
        ));
    }

    /**
     * Marketplace still says pending, but a label/tracking already exists.
     *
     * @return list<array<string, mixed>>
     */
    protected function pendingAlreadyLabeledRows(): array
    {
        return array_values(array_filter(
            $this->pendingMarketplaceOrderRows(),
            fn (array $r) => $this->rowHasLabelTracking($r)
        ));
    }

    /**
     * Pending + Label Created rows that still need a Veeqo/GOFO tracking lookup.
     *
     * @return list<array<string, mixed>>
     */
    protected function missingLabelTrackingRows(): array
    {
        $out = [];
        $seen = [];
        foreach (array_merge($this->warehousePendingOrderRows(), $this->labelCreatedOrderRows()) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id !== '' && isset($seen[$id])) {
                continue;
            }
            if (trim((string) ($row['tracking_number'] ?? '')) !== '') {
                continue;
            }
            if ($id !== '') {
                $seen[$id] = true;
            }
            $out[] = $row;
        }

        return $out;
    }

    protected function forgetSofOrderRowCaches(): void
    {
        $this->cachedLabelCreatedRows = null;
        $this->cachedPendingRows = null;
    }

    protected function carrierShipmentStatusLabel(string $shipmentStatus): ?string
    {
        return match ($shipmentStatus) {
            ShipmentTrackingService::STATUS_DELIVERED => 'Delivered',
            ShipmentTrackingService::STATUS_IN_TRANSIT => 'In Transit',
            ShipmentTrackingService::STATUS_OUT_FOR_DELIV => 'Out for Delivery',
            ShipmentTrackingService::STATUS_PICKUP => 'Available for Pickup',
            ShipmentTrackingService::STATUS_INFO_RECEIVED => 'Label Created / No Scan',
            ShipmentTrackingService::STATUS_PENDING => 'Pending Scan',
            ShipmentTrackingService::STATUS_NOT_FOUND => 'Not Found (carrier)',
            ShipmentTrackingService::STATUS_EXPIRED => 'Expired',
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
            foreach ($this->rowLookupSkus($row) as $sku) {
                $skus[$sku] = true;
            }
        }

        if ($skus === []) {
            return $rows;
        }

        $shopifyBySku = ShopifySku::mapByProductSkus(array_keys($skus));

        foreach ($rows as &$row) {
            $row['INV'] = 0;
            foreach ($this->rowLookupSkus($row) as $sku) {
                $shopify = $shopifyBySku->get($sku);
                if (! $shopify) {
                    continue;
                }
                $row['INV'] = (int) ($shopify->inv ?? 0);
                if (empty($row['sku_image'])) {
                    $row['sku_image'] = $this->resolveSkuImageUrl($shopify->image_src ?? null);
                }
                break;
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
        $allowed = ['ENV', 'STD', 'O-Size', 'Pallet', 'OV-Wt'];

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

        $displayTz = $this->sofTimezone();
        $storageTz = $this->sofStorageTimezone();

        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::parse($value)->timezone($displayTz)->format('Y-m-d H:i:s');
            }

            $raw = trim((string) $value);
            if ($raw === '') {
                return null;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
                return Carbon::createFromFormat('Y-m-d', $raw, $displayTz)->startOfDay()->format('Y-m-d H:i:s');
            }

            $hasOffset = (bool) preg_match('/(?:[zZ]|[+-]\d{2}:?\d{2})$/', $raw);
            $dt = $hasOffset
                ? Carbon::parse($raw)
                : Carbon::parse($raw, $storageTz);

            return $dt->timezone($displayTz)->format('Y-m-d H:i:s');
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
            'PackageTrackingDetails',
            'logistics_no',
            'tracking',
            'waybillNo',
            'waybill_no',
            'expressNo',
            'express_no',
            'billNo',
            'shipmentTrackingNumber',
            'ShipmentTrackingNumber',
            'TrackingID',
            'tracking_id',
            'shipmentTrackingNumber',
            'shipment_tracking_number',
        ];

        foreach ($scalarKeys as $key) {
            if (! empty($order[$key]) && is_scalar($order[$key])) {
                $val = trim((string) $order[$key]);
                if ($val !== '') {
                    return $val;
                }
            }
        }

        foreach (['shipment', 'shipping', 'fulfillment', 'packageInfo', 'PackageTrackingDetails', 'FulfillmentData'] as $nestedKey) {
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
            $pkg = $nested['PackageTrackingDetails'] ?? $nested['packageTrackingDetails'] ?? null;
            if (is_array($pkg)) {
                foreach (['TrackingNumber', 'tracking_number', 'trackingNumber'] as $key) {
                    if (! empty($pkg[$key]) && is_scalar($pkg[$key])) {
                        $val = trim((string) $pkg[$key]);
                        if ($val !== '') {
                            return $val;
                        }
                    }
                }
            }
        }

        foreach (['fulfillments', 'fulfillmentList', 'Fulfillments', 'shippingFulfillments', 'shipping_fulfillments'] as $listKey) {
            $list = $order[$listKey] ?? null;
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (['tracking_number', 'trackingNumber', 'TrackingNumber', 'tracking', 'shipmentTrackingNumber'] as $key) {
                    if (! empty($item[$key]) && is_scalar($item[$key])) {
                        $val = trim((string) $item[$key]);
                        if ($val !== '') {
                            return $val;
                        }
                    }
                }
            }
        }

        // Newegg package list / Faire shipments / AE logistics list
        foreach (['PackageInfoList', 'packageInfoList', 'shipments', 'packages', 'logistic_info_list'] as $pkgKey) {
            $packages = $order[$pkgKey] ?? null;
            if (! is_array($packages)) {
                continue;
            }
            $items = array_is_list($packages) ? $packages : [$packages];
            if (isset($packages['aeop_tp_logistics_info_dto']) && is_array($packages['aeop_tp_logistics_info_dto'])) {
                $nested = $packages['aeop_tp_logistics_info_dto'];
                $items = array_is_list($nested) ? $nested : [$nested];
            }
            foreach ($items as $pkg) {
                if (! is_array($pkg)) {
                    continue;
                }
                foreach ([
                    'TrackingNumber', 'tracking_number', 'tracking', 'tracking_code',
                    'trackingCode', 'logistics_no', 'shipping_code', 'shipmentTrackingNumber',
                ] as $key) {
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

        return $this->extractTrackingNumberDeep($order);
    }

    /**
     * Walk nested marketplace payloads (eBay shippingFulfillments, lineItems, etc.).
     */
    protected function extractTrackingNumberDeep(array $node, int $depth = 0): ?string
    {
        if ($depth > 6) {
            return null;
        }

        foreach ($node as $key => $val) {
            $k = strtolower((string) $key);
            if (is_scalar($val) && $val !== '' && (
                str_contains($k, 'trackingnumber')
                || $k === 'tracking'
                || (str_contains($k, 'tracking') && (str_contains($k, 'number') || str_contains($k, 'code') || str_contains($k, 'id')))
            )) {
                $s = trim((string) $val);
                if (strlen($s) >= 8) {
                    return $s;
                }
            }
            if (is_array($val)) {
                $found = $this->extractTrackingNumberDeep($val, $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Best-effort carrier name from a stored marketplace order payload.
     */
    protected function extractCarrierFromPayload(mixed $rawPayload): ?string
    {
        if (is_string($rawPayload)) {
            $decoded = json_decode($rawPayload, true);
            $rawPayload = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($rawPayload)) {
            return null;
        }

        $order = is_array($rawPayload['order'] ?? null) ? $rawPayload['order'] : $rawPayload;
        if (! is_array($order)) {
            return null;
        }

        foreach ([
            'shipping_carrier', 'carrier', 'carrier_name', 'CarrierName',
            'ShipService', 'logistics_type', 'shipping_company', 'courier',
            'tracking_company',
        ] as $key) {
            if (! empty($order[$key]) && is_scalar($order[$key])) {
                $val = trim((string) $order[$key]);
                if ($val !== '') {
                    return $val;
                }
            }
        }

        foreach (['shipments', 'PackageInfoList', 'packageInfoList', 'shipment', 'shipping', 'packageInfo'] as $listKey) {
            $list = $order[$listKey] ?? null;
            if (! is_array($list) || $list === []) {
                continue;
            }
            $first = array_is_list($list) ? ($list[0] ?? null) : $list;
            if (! is_array($first)) {
                continue;
            }
            foreach (['carrier', 'carrier_name', 'ShipCarrier', 'logistics_service_name', 'service', 'tracking_company'] as $key) {
                if (! empty($first[$key]) && is_scalar($first[$key])) {
                    $val = trim((string) $first[$key]);
                    if ($val !== '') {
                        return $val;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Fill empty Carrier cells from the tracking number (UPS 1Z…, USPS 94…, FedEx, etc.).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function fillCarrierFromTrackingNumbers(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['tracking_company'] = TrackingCarrierGuesser::fill(
                isset($row['tracking_company']) ? (string) $row['tracking_company'] : null,
                isset($row['tracking_number']) ? (string) $row['tracking_number'] : null
            );
        }
        unset($row);

        return $rows;
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
            in_array($slug, ['temu', 'temu2'], true) && $upper === 'UN_SHIPPING' => 'Pending',
            in_array($slug, ['temu', 'temu2'], true) && in_array($upper, ['SHIPPED', 'PARTIALLY_SHIPPED'], true) => 'Label Created',
            in_array($slug, ['temu', 'temu2'], true) && in_array($upper, ['DELIVERED', 'PARTIALLY_DELIVERED'], true) => 'Delivered',
            in_array($slug, ['aliexpress', 'alibaba'], true)
                && str_replace([' ', '-'], '_', $upper) === 'WAIT_SELLER_SEND_GOODS' => 'Pending',
            $slug === 'aliexpress' && $upper === 'WAIT_BUYER_ACCEPT_GOODS' => 'Shipped',
            $slug === 'alibaba' && $upper === 'WAIT_BUYER_ACCEPT_GOODS' => 'Shipped',
            $slug === 'faire' && $upper === 'DELIVERED' => 'Delivered',
            $slug === 'wayfair' && $lower === 'open' => 'Pending',
            in_array($slug, ['bestbuy', 'macy'], true)
                && str_replace([' ', '-'], '_', $upper) === 'AWAITING_SHIPMENT' => 'Pending',
            in_array($slug, ['bestbuy', 'macy'], true)
                && str_replace([' ', '-'], '_', $upper) === 'SHIPPING' => 'In Transit',
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
     * Pull tracking numbers into SOF (no Shopify).
     * 1) Temu / Temu 2 → Temu OpenAPI.
     * 2) Other channels → that channel's own API (Newegg, Reverb, AE, Alibaba, Faire, PP, Doba).
     * When `selected` rows are posted, only those orders are pulled.
     * HTTP requests are capped and time-boxed so nginx/proxy (often 60s) does not 504.
     */
    public function pullTrackingNumbers(
        Request $request,
        TemuOrderTrackingPullService $temuPull,
        Temu2OrderTrackingPullService $temu2Pull,
        ChannelTrackingApiFallbackService $channelApiFallback,
        VeeqoShopifyFulfillmentService $labelTracking,
    ): JsonResponse {
        @set_time_limit(45);
        $deadline = microtime(true) + self::HTTP_PULL_DEADLINE_SECONDS;
        try {
            app(VeeqoApiService::class)->setTimeout(6);
            app(GofoExpressService::class)->setTimeout(6);
            app(FourSellerApiService::class)->setTimeout(5);
        } catch (\Throwable $e) {
            // Timeouts are best-effort; continue with service defaults.
        }

        $limit = max(1, min(self::HTTP_PULL_MAX, (int) $request->input('limit', self::HTTP_PULL_MAX)));
        $channel = strtolower(trim((string) $request->input('channel', '')));
        // Resolve Channels quick-search label → slug when needed.
        if ($channel !== '') {
            foreach (MarketplaceManagerRegistry::channels() as $c) {
                $slug = strtolower((string) ($c['slug'] ?? ''));
                $label = strtolower((string) ($c['label'] ?? ''));
                if ($channel === $slug || $channel === $label) {
                    $channel = $slug;
                    break;
                }
            }
        }

        $selectedRaw = $request->input('selected', []);
        if (! is_array($selectedRaw)) {
            $selectedRaw = [];
        }
        $selectedOnly = filter_var($request->input('selected_only', false), FILTER_VALIDATE_BOOL);
        $selected = [];
        foreach ($selectedRaw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $slug = strtolower(trim((string) ($row['mm_slug'] ?? '')));
            $item = [
                'mm_slug' => $slug,
                'row_id' => (int) ($row['row_id'] ?? 0),
                'show_id' => (int) ($row['show_id'] ?? $row['row_id'] ?? 0),
                'order_id' => trim((string) ($row['order_id'] ?? '')),
                'order_id_api' => trim((string) ($row['order_id_api'] ?? '')),
                'order_number' => trim((string) ($row['order_number'] ?? '')),
                'shopify_order_id' => trim((string) ($row['shopify_order_id'] ?? '')),
            ];
            if (
                $item['mm_slug'] === ''
                && $item['order_id'] === ''
                && $item['order_id_api'] === ''
                && $item['order_number'] === ''
                && $item['shopify_order_id'] === ''
            ) {
                continue;
            }
            $selected[] = $item;
        }
        $hasSelection = $selected !== [];
        if ($selectedOnly && ! $hasSelection) {
            return response()->json([
                'success' => false,
                'message' => 'No valid selected rows received. Check row checkboxes and try again.',
                'data' => [],
                'summary' => ['checked' => 0, 'with_tracking' => 0, 'updated' => 0, 'empty' => 0, 'selected' => 0],
            ], 422);
        }
        if (! $hasSelection) {
            return response()->json([
                'success' => false,
                'message' => 'Select orders (or wait for the table to finish loading), then click Pull Tracking. Unscoped pulls hit the gateway timeout.',
                'data' => [],
                'summary' => ['checked' => 0, 'with_tracking' => 0, 'updated' => 0, 'empty' => 0, 'selected' => 0],
            ], 422);
        }
        $limit = max(1, min(self::HTTP_PULL_MAX, count($selected)));
        $selected = array_slice($selected, 0, $limit);

        $selectedSlugs = [];
        $temuParents = [];
        $temu2Parents = [];
        foreach ($selected as $row) {
            $slug = $row['mm_slug'];
            // When Channels filter is Temu/Temu2, accept selected rows even if mm_slug was blank.
            if ($slug === '' && in_array($channel, ['temu', 'temu2'], true)) {
                $slug = $channel;
            }
            if ($slug !== '') {
                $selectedSlugs[$slug] = true;
            }
            $parent = $row['order_id_api'] !== '' ? $row['order_id_api']
                : ($row['order_id'] !== '' ? $row['order_id'] : $row['order_number']);
            if ($slug === 'temu' && $parent !== '') {
                $temuParents[$parent] = true;
            }
            if ($slug === 'temu2' && $parent !== '') {
                $temu2Parents[$parent] = true;
            }
            // Channel filter Temu + selected rows: still pull even if slug was mis-tagged.
            if ($channel === 'temu' && $parent !== '' && ($slug === '' || $slug === 'temu')) {
                $temuParents[$parent] = true;
            }
            if ($channel === 'temu2' && $parent !== '' && ($slug === '' || $slug === 'temu2')) {
                $temu2Parents[$parent] = true;
            }
        }

        $pullTemu = $hasSelection
            ? ($temuParents !== [])
            : ($channel === '' || $channel === 'temu');
        $pullTemu2 = $hasSelection
            ? ($temu2Parents !== [])
            : ($channel === '' || $channel === 'temu2');
        $pullChannelApi = $hasSelection
            ? (array_diff(array_keys($selectedSlugs), ['temu', 'temu2']) !== [])
            : ($channel === '' || ! in_array($channel, ['temu', 'temu2'], true));

        try {
            $checked = 0;
            $updated = 0;
            $withTracking = 0;
            $rows = [];
            $parts = [];
            $hardFail = false;
            $timedOut = false;
            $processedKeys = [];
            $parts[] = 'Selected rows: '.count($selected).'.';

            $labelCandidates = [];
            foreach ($selected as $row) {
                $slug = strtolower((string) ($row['mm_slug'] ?? ''));
                if ($slug === '' || in_array($slug, ['temu', 'temu2'], true)) {
                    continue;
                }
                $labelCandidates[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'mm_slug' => $slug,
                    'row_id' => (int) ($row['row_id'] ?? 0),
                    'show_id' => (int) ($row['show_id'] ?? $row['row_id'] ?? 0),
                    'order_id' => (string) ($row['order_id'] ?? ''),
                    'order_id_api' => (string) ($row['order_id_api'] ?? ''),
                    'order_number' => (string) ($row['order_number'] ?? ''),
                    'shopify_order_id' => (string) ($row['shopify_order_id'] ?? ''),
                    'tracking_number' => '',
                ];
            }

            if ($labelCandidates !== [] && microtime(true) < $deadline) {
                $labelPull = $this->pullLabelTrackingFromApis(
                    $labelCandidates,
                    $limit,
                    $labelTracking,
                    $deadline,
                    true
                );
                $checked += (int) ($labelPull['checked'] ?? 0);
                $updated += (int) ($labelPull['updated'] ?? 0);
                $withTracking += (int) ($labelPull['with_tracking'] ?? 0);
                if (($labelPull['message'] ?? '') !== '') {
                    $parts[] = (string) $labelPull['message'];
                }
                $rows = array_merge($rows, $labelPull['rows'] ?? []);
                $processedKeys = array_merge($processedKeys, $labelPull['processed_keys'] ?? []);
                if (! empty($labelPull['truncated'])) {
                    $timedOut = true;
                }
            } elseif ($labelCandidates !== []) {
                $timedOut = true;
            }

            if ($pullTemu && microtime(true) < $deadline) {
                $temu = $temuPull->pullForParents(array_keys($temuParents), true);
                $checked += (int) ($temu['checked'] ?? 0);
                $updated += (int) ($temu['updated'] ?? 0);
                $withTracking += (int) ($temu['updated'] ?? 0);
                $parts[] = 'Temu API: '.((string) ($temu['message'] ?? 'done'));
                if (empty($temu['success']) && (int) ($temu['checked'] ?? 0) === 0) {
                    $hardFail = true;
                }
                $rows = array_merge($rows, $temu['rows'] ?? []);
            } elseif ($pullTemu) {
                $timedOut = true;
            }

            if ($pullTemu2 && microtime(true) < $deadline) {
                $temu2 = $temu2Pull->pullForParents(array_keys($temu2Parents), true);
                $checked += (int) ($temu2['checked'] ?? 0);
                $updated += (int) ($temu2['updated'] ?? 0);
                $withTracking += (int) ($temu2['updated'] ?? 0);
                $parts[] = 'Temu 2 API: '.((string) ($temu2['message'] ?? 'done'));
                if (empty($temu2['success']) && (int) ($temu2['checked'] ?? 0) === 0) {
                    $hardFail = true;
                }
                $rows = array_merge($rows, $temu2['rows'] ?? []);
            } elseif ($pullTemu2) {
                $timedOut = true;
            }

            if ($timedOut) {
                $parts[] = 'Stopped early to stay under the gateway timeout; remaining orders continue in the next batch.';
            }

            $empty = max(0, $checked - $withTracking);
            $message = implode(' ', $parts);
            if ($message === '') {
                $message = $hasSelection
                    ? 'Nothing to pull for the selected rows.'
                    : 'Nothing to pull for the selected channel.';
            } elseif ($checked === 0 && ! $hardFail) {
                $message .= $hasSelection
                    ? ' No matching marketplace orders were checked — verify selected rows / channel.'
                    : ' No orders missing tracking were found for this channel (select rows to re-pull existing).';
            }

            $rows = $this->fillCarrierFromTrackingNumbers($rows);

            return response()->json([
                'success' => ! $hardFail,
                'message' => $message,
                'truncated' => $timedOut,
                'processed_keys' => array_values(array_unique($processedKeys)),
                'summary' => [
                    'checked' => $checked,
                    'with_tracking' => $withTracking,
                    'updated' => $updated,
                    'empty' => $empty,
                    'channel' => $channel !== '' ? $channel : 'all',
                    'selected' => $hasSelection ? count($selected) : 0,
                ],
                'data' => $rows,
            ], $hardFail ? 422 : 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to pull tracking numbers: '.$e->getMessage(),
                'data' => [],
                'summary' => ['checked' => 0, 'with_tracking' => 0, 'updated' => 0, 'empty' => 0, 'selected' => 0],
            ], 500);
        }
    }

    /**
     * Recent Temu/Temu2 rows with API-pulled tracking for the Pull Tracking modal.
     *
     * @return list<array<string, mixed>>
     */
    protected function recentTemuPulledTrackingRows(string $slug, int $limit = 40): array
    {
        $limit = max(1, min(100, $limit));
        $model = $slug === 'temu2' ? Temu2Order::class : TemuOrder::class;
        $table = $slug === 'temu2' ? 'temu2_orders' : 'temu_orders';
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tracking_number')) {
            return [];
        }

        $label = $slug === 'temu2' ? 'Temu 2' : 'Temu';

        return $model::query()
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->whereNotNull('tracking_fetched_at')
            ->orderByDesc('tracking_fetched_at')
            ->limit($limit)
            ->get(['parent_order_sn', 'order_sn', 'tracking_number', 'carrier', 'tracking_fetched_at'])
            ->map(function ($row) use ($label) {
                return [
                    'order_number' => (string) ($row->parent_order_sn ?: $row->order_sn ?: ''),
                    'shopify_order_id' => null,
                    'tracking_number' => (string) ($row->tracking_number ?? ''),
                    'tracking_company' => TrackingCarrierGuesser::fill(
                        (string) ($row->carrier ?? ''),
                        (string) ($row->tracking_number ?? '')
                    ) ?? '',
                    'fulfillment_status' => $label.' API',
                    'shipment_status' => '',
                    'note' => 'Pulled from Temu OpenAPI (not Shopify)',
                ];
            })
            ->all();
    }

    /**
     * Every 15 minutes: Label Created / Pending rows still missing a tracking number.
     * Looks up Veeqo / GOFO / 4Seller and writes the number onto the channel order.
     *
     * @return array{checked: int, updated: int, with_tracking: int, message: string, candidates: int}
     */
    public function pullMissingLabelCreatedTracking(int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));
        $candidates = $this->missingLabelTrackingRows();
        $filtered = [];
        foreach ($candidates as $row) {
            if (! is_array($row)) {
                continue;
            }
            $slug = strtolower(trim((string) ($row['mm_slug'] ?? '')));
            if ($slug === '' || in_array($slug, ['temu', 'temu2'], true)) {
                continue;
            }
            $filtered[] = $row;
        }

        $result = $this->pullLabelTrackingFromApis(
            $filtered,
            $limit,
            app(VeeqoShopifyFulfillmentService::class)
        );
        $result['candidates'] = count($filtered);

        return $result;
    }

    /**
     * Pull tracking from Veeqo / GOFO / 4Seller for SOF rows that still have no number.
     *
     * @param  list<array<string, mixed>>  $candidateRows
     * @return array{checked: int, updated: int, with_tracking: int, message: string, rows: list<array<string, mixed>>, truncated: bool, processed_keys: list<string>}
     */
    protected function pullLabelTrackingFromApis(
        array $candidateRows,
        int $limit,
        VeeqoShopifyFulfillmentService $labels,
        ?float $deadline = null,
        bool $fast = false,
    ): array {
        $limit = max(1, min(200, $limit));
        $checked = 0;
        $updated = 0;
        $withTracking = 0;
        $outRows = [];
        $seen = [];
        $truncated = false;
        $processedKeys = [];

        foreach ($candidateRows as $row) {
            if ($checked >= $limit) {
                break;
            }
            if ($deadline !== null && microtime(true) >= $deadline) {
                $truncated = true;
                break;
            }
            if (! is_array($row)) {
                continue;
            }
            $slug = strtolower(trim((string) ($row['mm_slug'] ?? '')));
            if ($slug === '' || in_array($slug, ['temu', 'temu2'], true)) {
                continue;
            }
            $showId = (int) ($row['show_id'] ?? $row['row_id'] ?? 0);
            $orderKey = strtolower(trim((string) (
                $row['order_id_api']
                ?? $row['order_id']
                ?? $row['order_number']
                ?? ''
            )));
            $dedupe = $slug.'|'.$showId.'|'.$orderKey;
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;

            if ($slug === 'amazon') {
                $amazonOrder = AmazonOrder::query()->find($showId);
                if ($amazonOrder === null) {
                    $amazonOid = trim((string) ($row['order_id'] ?? $row['order_id_api'] ?? ''));
                    if ($amazonOid !== '') {
                        $amazonOrder = AmazonOrder::query()->where('amazon_order_id', $amazonOid)->first();
                    }
                }
                if ($amazonOrder && $amazonOrder->isFba()) {
                    $processedKeys[] = $this->sofPullRowKey($row);
                    continue;
                }
            }

            $checked++;
            $processedKeys[] = $this->sofPullRowKey($row);

            $refs = [];
            foreach (['order_id', 'order_id_api', 'order_number', 'shopify_order_id'] as $k) {
                $v = trim((string) ($row[$k] ?? ''));
                if ($v !== '') {
                    $refs[] = $v;
                }
            }

            $local = null;
            if ($showId > 0) {
                $ctx = $labels->contextForMarketplaceOrder($slug, $showId);
                if (is_array($ctx)) {
                    foreach ((array) ($ctx['refs'] ?? []) as $ref) {
                        $refs[] = (string) $ref;
                    }
                    $sid = trim((string) ($ctx['shopify_order_id'] ?? ''));
                    if ($sid !== '') {
                        $refs[] = $sid;
                    }
                    $local = is_array($ctx['local_tracking'] ?? null) ? $ctx['local_tracking'] : null;
                }
            }

            $found = $labels->lookupLabelTracking($refs, $local, $fast);
            if ($found === null) {
                continue;
            }

            $tn = trim((string) ($found['tracking'] ?? ''));
            if ($tn === '') {
                continue;
            }
            $carrier = TrackingCarrierGuesser::fill(
                (string) ($found['carrier'] ?? ''),
                $tn
            ) ?? '';
            $source = (string) ($found['source'] ?? 'label');

            $this->persistPulledChannelTracking($slug, $showId, $row, $tn, $carrier);
            $withTracking++;
            $updated++;
            $outRows[] = [
                'id' => (string) ($row['id'] ?? ''),
                'mm_slug' => $slug,
                'show_id' => $showId,
                'order_number' => (string) ($row['order_number'] ?? $row['order_id'] ?? ''),
                'shopify_order_id' => $row['shopify_order_id'] ?? null,
                'order_id' => (string) ($row['order_id'] ?? ''),
                'order_id_api' => (string) ($row['order_id_api'] ?? ''),
                'tracking_number' => $tn,
                'tracking_company' => $carrier,
                'fulfillment_status' => strtoupper($source),
                'shipment_status' => '',
                'note' => 'Pulled from '.match ($source) {
                    'veeqo' => 'Veeqo',
                    'gofo' => 'GOFO',
                    '4seller' => '4Seller',
                    default => 'marketplace order',
                },
            ];
        }

        $this->forgetSofOrderRowCaches();

        $message = $checked > 0
            ? 'Veeqo/GOFO: checked '.$checked.', found tracking on '.$withTracking.'.'
            : '';

        return [
            'checked' => $checked,
            'updated' => $updated,
            'with_tracking' => $withTracking,
            'message' => $message,
            'rows' => $outRows,
            'truncated' => $truncated,
            'processed_keys' => array_values(array_unique(array_filter($processedKeys))),
        ];
    }

    /**
     * Stable key so the browser can retry only orders this request did not finish.
     *
     * @param  array<string, mixed>  $row
     */
    protected function sofPullRowKey(array $row): string
    {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id !== '') {
            return $id;
        }
        $slug = strtolower(trim((string) ($row['mm_slug'] ?? '')));
        $showId = (int) ($row['show_id'] ?? $row['row_id'] ?? 0);
        $order = trim((string) ($row['order_id_api'] ?? $row['order_id'] ?? $row['order_number'] ?? ''));

        return $slug.'|'.$showId.'|'.$order;
    }

    /**
     * Fill Label Created rows that still have no tracking (Veeqo / GOFO / Temu / channel API),
     * persist onto the marketplace order, then reload so Tracking + Carrier show immediately.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function hydrateMissingLabelTrackingOnRows(array $rows, int $limit = 40): array
    {
        $missing = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['tracking_number'] ?? '')) !== '') {
                continue;
            }
            $missing[] = $row;
        }
        if ($missing === []) {
            return $rows;
        }

        $temuParents = [];
        $temu2Parents = [];
        $others = [];
        foreach ($missing as $row) {
            $slug = strtolower(trim((string) ($row['mm_slug'] ?? '')));
            $parent = trim((string) (
                $row['order_id_api']
                ?? $row['order_id']
                ?? $row['order_number']
                ?? ''
            ));
            if ($slug === 'temu' && $parent !== '') {
                $temuParents[$parent] = true;
            } elseif ($slug === 'temu2' && $parent !== '') {
                $temu2Parents[$parent] = true;
            } elseif ($slug !== '' && ! in_array($slug, ['temu', 'temu2'], true)) {
                $others[] = $row;
            }
        }

        if ($temuParents !== []) {
            try {
                app(TemuOrderTrackingPullService::class)->pullForParents(array_keys($temuParents), true);
            } catch (\Throwable) {
            }
        }
        if ($temu2Parents !== []) {
            try {
                app(Temu2OrderTrackingPullService::class)->pullForParents(array_keys($temu2Parents), true);
            } catch (\Throwable) {
            }
        }
        if ($others !== []) {
            try {
                app(ChannelTrackingApiFallbackService::class)->pullForMissingRows($others, $limit, null);
            } catch (\Throwable) {
            }
            $this->pullLabelTrackingFromApis(
                $others,
                $limit,
                app(VeeqoShopifyFulfillmentService::class)
            );
        }

        $this->forgetSofOrderRowCaches();

        return $this->labelCreatedNoScanRows();
    }

    /**
     * Save pulled tracking onto the channel order payload and the local Shopify order cache.
     *
     * @param  array<string, mixed>  $sofRow
     */
    protected function persistPulledChannelTracking(string $slug, int $showId, array $sofRow, string $tracking, string $carrier): void
    {
        $this->writeShopifyRawOrderTracking($sofRow, $tracking, $carrier);
        $this->enrollCarrierTrackingNumber($tracking, $carrier);

        try {
            if ($slug === 'amazon') {
                $order = AmazonOrder::query()->find($showId);
                if ($order === null && $showId > 0) {
                    $item = AmazonOrderItem::query()->find($showId);
                    $order = $item?->order;
                }
                if ($order === null) {
                    $oid = trim((string) ($sofRow['order_id'] ?? $sofRow['order_id_api'] ?? ''));
                    if ($oid !== '') {
                        $order = AmazonOrder::query()->where('amazon_order_id', $oid)->first();
                    }
                }
                if ($order !== null) {
                    $raw = AmazonOrder::decodeRawPayload($order->raw_data ?? null);
                    $raw['tracking_number'] = $tracking;
                    $raw['carrier'] = $carrier;
                    $order->raw_data = $raw;
                    $order->save();
                }

                return;
            }

            if (in_array($slug, ['temu', 'temu2'], true)) {
                $model = $slug === 'temu2' ? Temu2Order::query()->find($showId) : TemuOrder::query()->find($showId);
                if ($model !== null) {
                    $model->tracking_number = $tracking;
                    if (Schema::hasColumn($model->getTable(), 'carrier')) {
                        $model->carrier = $carrier !== '' ? $carrier : $model->carrier;
                    }
                    $model->save();
                }

                return;
            }

            $class = match ($slug) {
                'ebay1' => Ebay1OrderMetric::class,
                'ebay2' => Ebay2OrderMetric::class,
                'ebay3' => Ebay3OrderMetric::class,
                'newegg' => NeweggOrderMetric::class,
                'shein' => SheinOrderMetric::class,
                'reverb' => ReverbOrderMetric::class,
                'faire' => FaireOrderMetric::class,
                'aliexpress' => AliexpressOrderMetric::class,
                'alibaba' => AlibabaOrderMetric::class,
                'topdawg' => TopDawgOrderMetric::class,
                'bestbuy' => BestBuyOrderMetric::class,
                'macy' => MacyOrderMetric::class,
                'wayfair' => WayfairDailyData::class,
                'purchasingpower' => PurchasingPowerSale::class,
                'doba' => DobaDailyData::class,
                default => null,
            };
            if ($class === null || $showId <= 0) {
                return;
            }
            $model = $class::query()->find($showId);
            if ($model === null) {
                return;
            }
            if (Schema::hasColumn($model->getTable(), 'tracking_number')) {
                $model->tracking_number = $tracking;
                if (Schema::hasColumn($model->getTable(), 'carrier')) {
                    $model->carrier = $carrier !== '' ? $carrier : ($model->carrier ?? null);
                } elseif (Schema::hasColumn($model->getTable(), 'carrier_name')) {
                    $model->carrier_name = $carrier !== '' ? $carrier : ($model->carrier_name ?? null);
                } elseif (Schema::hasColumn($model->getTable(), 'shipping_company')) {
                    $model->shipping_company = $carrier !== '' ? $carrier : ($model->shipping_company ?? null);
                }
            }
            foreach (['raw_payload', 'raw_json', 'raw_data'] as $field) {
                if (! isset($model->{$field})) {
                    continue;
                }
                $raw = $model->{$field};
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $raw = is_array($decoded) ? $decoded : [];
                }
                if (! is_array($raw)) {
                    $raw = [];
                }
                $raw['tracking_number'] = $tracking;
                $raw['carrier'] = $carrier;
                $model->{$field} = $raw;
                break;
            }
            $model->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Put a pulled tracking number onto the carrier-status queue (USPS / UPS / 17TRACK).
     */
    protected function enrollCarrierTrackingNumber(string $tracking, string $carrier): void
    {
        if (! Schema::hasTable('carrier_tracking_statuses')) {
            return;
        }
        $tn = trim($tracking);
        if ($tn === '' || strlen($tn) < 8) {
            return;
        }

        try {
            $now = now();
            $guessed = TrackingCarrierGuesser::fill($carrier !== '' ? $carrier : null, $tn) ?? $carrier;
            DB::table('carrier_tracking_statuses')->upsert(
                [[
                    'tracking_number' => mb_substr($tn, 0, 128),
                    'carrier' => $guessed !== '' ? mb_substr((string) $guessed, 0, 128) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['tracking_number'],
                ['carrier', 'updated_at']
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $sofRow
     */
    protected function writeShopifyRawOrderTracking(array $sofRow, string $tracking, string $carrier): void
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return;
        }

        try {
            $now = now();
            $payload = [
                'tracking_number' => $tracking,
                'tracking_company' => $carrier !== '' ? $carrier : null,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('shopify_raw_orders', 'shipment_checked_at')) {
                $payload['shipment_checked_at'] = $now;
            }

            $sid = trim((string) ($sofRow['shopify_order_id'] ?? ''));
            $numericId = $this->shopifyNumericOrderId($sid);
            if ($numericId !== null) {
                $affected = DB::table('shopify_raw_orders')->where('order_id', $numericId)->update($payload);
                if ($affected > 0) {
                    return;
                }
            }

            $candidates = [];
            foreach (['order_number', 'order_id', 'order_id_api'] as $k) {
                $v = trim((string) ($sofRow[$k] ?? ''));
                if ($v === '') {
                    continue;
                }
                $candidates[] = $v;
                if (! str_starts_with($v, 'Amz')) {
                    $candidates[] = 'Amz'.$v;
                }
            }
            $candidates = array_values(array_unique($candidates));
            if ($candidates === []) {
                return;
            }
            DB::table('shopify_raw_orders')->whereIn('order_number', $candidates)->update($payload);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Queue a catch-up carrier status sync (returns immediately — does not block the browser).
     * Processes thousands of channel tracking numbers via 17TRACK in the background.
     */
    public function refreshShipmentStatus(Request $request, ShipmentTrackingService $tracking): JsonResponse
    {
        if (! $tracking->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'No tracking provider configured. Add USPS / UPS credentials or TRACKING_API_KEY in .env.',
            ], 422);
        }

        // Catch-up defaults: large batch, no stale skip — runs in queue so the UI stays fast.
        $limit = max(1, min(5000, (int) $request->input('limit', 2000)));
        $stale = max(0, (int) $request->input('stale', 0));
        $carrier = strtoupper(trim((string) $request->input('carrier', '')));
        $repairQuota = filter_var($request->input('repair_quota', true), FILTER_VALIDATE_BOOL);
        $catchUp = filter_var($request->input('catch_up', true), FILTER_VALIDATE_BOOL);

        try {
            SyncShipmentTrackingStatusJob::dispatch(
                $limit,
                $stale,
                $repairQuota,
                $catchUp,
                $carrier
            );

            return response()->json([
                'success' => true,
                'queued' => true,
                'queue' => 'shipment-tracking',
                'message' => "Shipment status sync queued for up to {$limit} tracking numbers."
                    .' Running in the background on the shipment-tracking queue.'
                    .' Refresh Label Created / In Transit in a few minutes.',
                'providers' => [
                    'usps' => $tracking->hasUsps(),
                    'ups' => $tracking->hasUps(),
                    'fedex' => $tracking->hasFedex(),
                    'gofo' => $tracking->hasGofo(),
                    '17track' => $tracking->has17Track(),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to queue shipment status sync: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * DISABLED: SOF must not call Shopify Admin API for tracking.
     * Use channel API pull (Temu / ChannelTrackingApiFallbackService) instead.
     *
     * @param  list<string|int>|null  $shopifyOrderIds
     * @return array{checked:int,updated:int,with_tracking:int,rows:list<array<string,mixed>>}
     */
    protected function backfillShopifyTrackingForLabelCreated(int $limit, bool $includeSamples = false, ?array $shopifyOrderIds = null): array
    {
        return ['checked' => 0, 'updated' => 0, 'with_tracking' => 0, 'rows' => []];
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
     * DISABLED: SOF Update Shipment Status must not sync via Shopify order ids / shopify_raw_orders writes.
     * Carrier status cron (tracking:sync-status) remains separate; tracking numbers come from channel APIs.
     *
     * @return array{checked:int,updated:int}
     */
    protected function syncLabelCreatedShipmentStatuses(ShipmentTrackingService $tracking, int $limit): array
    {
        return ['checked' => 0, 'updated' => 0];
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
            $counts[$slug] = 0;
        }
        foreach ($this->warehousePendingOrderRows() as $row) {
            $slug = (string) ($row['mm_slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
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
     * Count of Label Created / No Scan orders in the last 24 hours (all MM channels).
     * JSON key remains fulfilled_24h for frontend compatibility.
     */
    protected function fulfilledLast24HoursCount(): int
    {
        return count($this->labelCreatedNoScanRows());
    }

    /**
     * Shipped/Received marketplace statuses (Received is counted separately).
     * JSON key remains scan_done_24h for snapshot compatibility.
     */
    protected function scanDoneLast24HoursCount(): int
    {
        return $this->countAllOrders(
            fn (string $slug) => $this->scopedToLast30Days($this->scanDoneOrdersQuery($slug), $slug)
        );
    }

    /**
     * Combined Shipped/Received + In Received (one "Received by carrier" tab).
     *
     * @return list<array<string, mixed>>
     */
    protected function receivedByCarrierOrderRows(): array
    {
        $shipped = $this->collectOrderRows(
            fn (string $slug) => $this->scopedToLast30Days($this->scanDoneOrdersQuery($slug), $slug),
            true
        );
        $received = $this->collectOrderRows(
            fn (string $slug) => $this->scopedToLast30Days($this->inReceivedOrdersQuery($slug), $slug),
            true
        );

        return $this->mergeOrderRowsById($shipped, $received);
    }

    protected function receivedByCarrierCount(): int
    {
        return $this->scanDoneLast24HoursCount() + $this->inReceivedOrdersCount();
    }

    /**
     * Count of In Transit orders in the last 30 days.
     * Same membership as inTransitData() — marketplace + carrier progress from Label Created.
     */
    protected function inTransitOrdersCount(): int
    {
        return count($this->excludeCarrierDeliveredRows($this->inTransitOrderRows()));
    }

    protected function deliveredOrdersCount(): int
    {
        $rows = $this->collectOrderRows(
            fn (string $slug) => $this->scopedToLast30Days($this->deliveredOrdersQuery($slug), $slug),
            true
        );
        $fromCarrier = array_values(array_filter(
            $this->labelCreatedOrderRows(),
            fn (array $r) => ($r['shipment_status'] ?? null) === ShipmentTrackingService::STATUS_DELIVERED
        ));
        $rows = $this->mergeOrderRowsById($rows, $fromCarrier);
        $rows = $this->mergeOrderRowsById(
            $rows,
            $this->onlyCarrierDeliveredRows($this->inTransitOrderRows())
        );

        return count($rows);
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
     * Resolve the active order date window in Eastern (America/New_York).
     * Request date_from / date_to are treated as EST/EDT calendar dates (YYYY-MM-DD).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveOrderDateRange(): array
    {
        $tz = $this->sofTimezone();
        $fromRaw = trim((string) request()->input('date_from', ''));
        $toRaw = trim((string) request()->input('date_to', ''));

        $from = $this->parseCaliforniaDateInput($fromRaw, now($tz)->subDays(30)->startOfDay());
        $to = $this->parseCaliforniaDateInput($toRaw, now($tz)->endOfDay(), true);

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * Parse YYYY-MM-DD (or datetime) as an Eastern calendar bound.
     */
    protected function parseCaliforniaDateInput(string $raw, Carbon $fallback, bool $endOfDay = false): Carbon
    {
        $tz = $this->sofTimezone();
        $raw = trim($raw);
        if ($raw === '') {
            return $fallback->copy()->timezone($tz);
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
                $dt = Carbon::createFromFormat('Y-m-d', $raw, $tz);
            } else {
                $dt = Carbon::parse($raw, $tz);
            }

            return $endOfDay ? $dt->endOfDay() : $dt->startOfDay();
        } catch (\Throwable) {
            return $fallback->copy()->timezone($tz);
        }
    }

    /**
     * SQL bounds: date columns use Eastern calendar days; datetime columns use
     * app-storage wall clocks (naive Pacific) covering the same EST/EDT window.
     *
     * @return array{from: Carbon, to: Carbon, from_date: string, to_date: string, from_dt: string, to_dt: string}
     */
    protected function californiaSqlBounds(Carbon $from, Carbon $to): array
    {
        $displayTz = $this->sofTimezone();
        $storageTz = $this->sofStorageTimezone();
        $fromEst = $from->copy()->timezone($displayTz)->startOfDay();
        $toEst = $to->copy()->timezone($displayTz)->endOfDay();
        $fromStored = $fromEst->copy()->timezone($storageTz);
        $toStored = $toEst->copy()->timezone($storageTz);

        return [
            'from' => $fromEst,
            'to' => $toEst,
            'from_date' => $fromEst->toDateString(),
            'to_date' => $toEst->toDateString(),
            'from_dt' => $fromStored->format('Y-m-d H:i:s'),
            'to_dt' => $toStored->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Restrict by channel order-date column to [from, to] inclusive (Eastern days).
     */
    protected function applyOrderDateRangeFilter(Builder $query, Carbon $from, Carbon $to, string $slug): Builder
    {
        $b = $this->californiaSqlBounds($from, $to);
        $fromDate = $b['from_date'];
        $toDate = $b['to_date'];
        $fromDt = $b['from_dt'];
        $toDt = $b['to_dt'];

        return match ($slug) {
            'amazon' => $query->whereDate('order_date', '>=', $fromDate)
                ->whereDate('order_date', '<=', $toDate),
            'temu', 'temu2' => $query->where(function (Builder $q) use ($fromDt, $toDt) {
                $q->where(function (Builder $q2) use ($fromDt, $toDt) {
                    $q2->where('parent_order_time', '>=', $fromDt)
                        ->where('parent_order_time', '<=', $toDt);
                })->orWhere(function (Builder $q2) use ($fromDt, $toDt) {
                    $q2->whereNull('parent_order_time')
                        ->where('order_update_time', '>=', $fromDt)
                        ->where('order_update_time', '<=', $toDt);
                });
            }),
            'bestbuy', 'macy' => $query->where(function (Builder $q) use ($fromDt, $toDt) {
                $q->where(function (Builder $q2) use ($fromDt, $toDt) {
                    $q2->where('order_created_at', '>=', $fromDt)
                        ->where('order_created_at', '<=', $toDt);
                })->orWhere(function (Builder $q2) use ($fromDt, $toDt) {
                    $q2->whereNull('order_created_at')
                        ->where('order_updated_at', '>=', $fromDt)
                        ->where('order_updated_at', '<=', $toDt);
                });
            }),
            'purchasingpower' => $query->where('date_created', '>=', $fromDt)->where('date_created', '<=', $toDt),
            'wayfair' => $query->whereDate('po_date', '>=', $fromDate)->whereDate('po_date', '<=', $toDate),
            'doba' => $query->where('order_time', '>=', $fromDt)->where('order_time', '<=', $toDt),
            'tiktok', 'tiktok2' => $query->where(function (Builder $q) use ($fromDt, $toDt) {
                $q->where(function (Builder $q2) use ($fromDt, $toDt) {
                    $q2->where('order_created_at', '>=', $fromDt)
                        ->where('order_created_at', '<=', $toDt);
                })->orWhere(function (Builder $q2) use ($fromDt, $toDt) {
                    $q2->whereNull('order_created_at')
                        ->where('order_updated_at', '>=', $fromDt)
                        ->where('order_updated_at', '<=', $toDt);
                });
            }),
            default => $query->where(function (Builder $q) use ($fromDate, $toDate, $fromDt, $toDt) {
                $q->where(function (Builder $q2) use ($fromDate, $toDate) {
                    $q2->whereNotNull('order_date')
                        ->whereDate('order_date', '>=', $fromDate)
                        ->whereDate('order_date', '<=', $toDate);
                })->orWhere(function (Builder $q2) use ($fromDt, $toDt) {
                    $q2->whereNull('order_date')
                        ->where('updated_at', '>=', $fromDt)
                        ->where('updated_at', '<=', $toDt);
                });
            }),
        };
    }

    /**
     * Restrict to orders on/after $since by channel order-date column.
     */
    protected function applyLast30DaysFilter(Builder $query, mixed $since, string $slug): Builder
    {
        $tz = $this->sofTimezone();
        $from = $since instanceof Carbon
            ? $since->copy()->timezone($tz)->startOfDay()
            : Carbon::parse((string) $since, $tz)->startOfDay();
        $to = now($tz)->endOfDay();

        return $this->applyOrderDateRangeFilter($query, $from, $to, $slug);
    }

    /**
     * Restrict to rows updated/touched since $since (Label Created / No Scan / Shipped-Received windows).
     */
    protected function applyUpdatedSinceFilter(Builder $query, mixed $since, string $slug): Builder
    {
        return match ($slug) {
            'amazon' => $query->where('updated_at', '>=', $since),
            'temu', 'temu2' => $query->where(function (Builder $q) use ($since) {
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
            'amazon' => 'order_date',
            'temu', 'temu2' => 'parent_order_time',
            'bestbuy', 'macy' => 'order_created_at',
            'purchasingpower' => 'date_created',
            'wayfair' => 'po_date',
            'doba' => 'order_time',
            'tiktok', 'tiktok2' => 'order_created_at',
            default => 'order_date',
        };
    }

    protected function orderUpdatedColumn(string $slug): ?string
    {
        return match ($slug) {
            'amazon' => 'updated_at',
            'temu', 'temu2' => 'order_update_time',
            'bestbuy', 'macy' => 'order_updated_at',
            'purchasingpower', 'wayfair', 'doba' => 'updated_at',
            'tiktok', 'tiktok2' => 'order_updated_at',
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
            'amazon' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)", ['SHIPPED', 'PARTIALLYSHIPPED'])
                ->when(Schema::hasColumn('amazon_orders', 'fulfillment_channel'), function (Builder $q) {
                    $q->where(function (Builder $q2) {
                        $q2->whereNull('fulfillment_channel')
                            ->orWhereRaw("UPPER(TRIM(fulfillment_channel)) != ?", ['AFN']);
                    });
                }),
            'temu', 'temu2' => $base->whereRaw(
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
            'bestbuy', 'macy' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) = ?",
                ['SHIPPING']
            ),
            'tiktok', 'tiktok2' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(order_status, ''))) = ?",
                ['IN_TRANSIT']
            ),
            default => null,
        };
    }

    /**
     * Shipped/Received tab statuses (Received → same Received by carrier tab via inReceivedOrdersQuery).
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
            'ebay1', 'ebay2', 'ebay3', 'newegg', 'wayfair', 'amazon', 'temu', 'temu2', 'faire',
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
     * Delivered — delivered / completed across MM channels (Received → Received by carrier tab).
     */
    protected function deliveredOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            'faire' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['DELIVERED']),
            // Shein / Reverb / Purchasing Power Received → Received by carrier tab
            'shein', 'reverb', 'purchasingpower' => null,
            'ebay1', 'ebay2', 'ebay3', 'newegg', 'wayfair', 'amazon' => null,
            'aliexpress', 'alibaba' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) IN (?, ?, ?)",
                ['FINISH', 'BUYER_ACCEPT_GOODS', 'TRADE_FINISHED']
            ),
            'topdawg' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['delivered']),
            'temu', 'temu2' => $base->whereRaw(
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
            'amazon' => Schema::hasTable('amazon_orders')
                ? AmazonOrder::query()->with('items')
                : null,
            'temu' => Schema::hasTable('temu_orders') ? TemuOrder::query() : null,
            'temu2' => Schema::hasTable('temu2_orders') ? Temu2Order::query() : null,
            'purchasingpower' => Schema::hasTable('purchasing_power_sales') ? PurchasingPowerSale::query() : null,
            'wayfair' => Schema::hasTable('wayfair_daily_data') ? WayfairDailyData::query() : null,
            'bestbuy' => Schema::hasTable('mirakl_daily_data') ? BestBuyOrderMetric::query() : null,
            'macy' => Schema::hasTable('mirakl_daily_data') ? MacyOrderMetric::query() : null,
            'doba' => Schema::hasTable('doba_daily_data') ? DobaDailyData::query() : null,
            'tiktok' => Schema::hasTable('tiktok_orders')
                ? TiktokOrder::query()->where(function (Builder $q) {
                    $q->whereNull('line_item_id')->orWhere('line_item_id', '!=', '__order__');
                })
                : null,
            'tiktok2' => Schema::hasTable('tiktok2_orders')
                ? Tiktok2Order::query()->where(function (Builder $q) {
                    $q->whereNull('line_item_id')->orWhere('line_item_id', '!=', '__order__');
                })
                : null,
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

        $query = match ($slug) {
            'ebay1', 'ebay2', 'ebay3' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['NOT_STARTED']),
            'shein' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN (?, ?)", ['pending', 'to be shipped'])
                // Shein-fulfilled lines are not warehouse pending.
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT LIKE ?", ['%by shein%']),
            'reverb' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['paid']),
            'aliexpress', 'alibaba' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['WAIT_SELLER_SEND_GOODS']),
            'newegg' => $base->whereIn('status', ['0', 0]),
            'faire' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) IN (?, ?)", ['PROCESSING', 'NEW']),
            'amazon' => $base->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['UNSHIPPED'])
                ->when(Schema::hasColumn('amazon_orders', 'fulfillment_channel'), function (Builder $q) {
                    $q->where(function (Builder $q2) {
                        $q2->whereNull('fulfillment_channel')
                            ->orWhereRaw("UPPER(TRIM(fulfillment_channel)) != ?", ['AFN']);
                    });
                }),
            'topdawg' => $base->whereRaw(
                "LOWER(TRIM(COALESCE(status, ''))) IN (?, ?, ?)",
                ['pending', 'processing', 'saved']
            ),
            'temu', 'temu2' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(parent_order_status_text, order_status_text, ''))) IN (?, ?)",
                ['UN_SHIPPING', 'PENDING']
            ),
            'purchasingpower' => $base->where(function (Builder $q) {
                // SHIPPING → In Transit tab
                $q->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = ?", ['TO_COLLECT'])
                    ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) LIKE ?", ['%awaiting shipment%']);
            }),
            'wayfair' => $base->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['open'])
                ->when(
                    Schema::hasColumn('wayfair_daily_data', 'packing_slip_url')
                    || Schema::hasColumn('wayfair_daily_data', 'carrier_code'),
                    function (Builder $q) {
                        // Wayfair keeps status=open after the packing slip / carrier is assigned.
                        $q->where(function (Builder $inner) {
                            if (Schema::hasColumn('wayfair_daily_data', 'packing_slip_url')) {
                                $inner->where(function (Builder $q2) {
                                    $q2->whereNull('packing_slip_url')->orWhere('packing_slip_url', '');
                                });
                            }
                            if (Schema::hasColumn('wayfair_daily_data', 'carrier_code')) {
                                $inner->where(function (Builder $q2) {
                                    $q2->whereNull('carrier_code')->orWhere('carrier_code', '');
                                });
                            }
                        });
                    }
                ),
            'bestbuy', 'macy' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(status, ''))) = ?",
                ['AWAITING_SHIPMENT']
            ),
            'doba' => $base->whereRaw("UPPER(TRIM(COALESCE(order_status, ''))) = ?", ['UNSHIPPED']),
            'tiktok', 'tiktok2' => $base->whereRaw(
                "UPPER(TRIM(COALESCE(order_status, ''))) IN (?, ?, ?)",
                ['AWAITING_SHIPMENT', 'AWAITING_COLLECTION', 'PARTIALLY_SHIPPING']
            ),
            default => null,
        };

        return $this->excludeCancelledOrdersFromPendingQuery($query, $slug);
    }

    /**
     * Drop cancelled / voided marketplace statuses from the Pending query.
     */
    protected function excludeCancelledOrdersFromPendingQuery(?Builder $query, string $slug): ?Builder
    {
        if ($query === null) {
            return null;
        }

        $table = $query->getModel()->getTable();
        $cols = match ($slug) {
            'doba' => ['order_status'],
            'temu', 'temu2' => ['parent_order_status_text', 'order_status_text'],
            'tiktok', 'tiktok2' => ['order_status'],
            default => ['status'],
        };
        foreach ($cols as $col) {
            if (! Schema::hasColumn($table, $col)) {
                continue;
            }
            $query->whereRaw("UPPER(TRIM(COALESCE(`{$col}`, ''))) NOT LIKE ?", ['%CANCEL%']);
        }

        if (in_array($slug, ['ebay1', 'ebay2', 'ebay3'], true) && Schema::hasColumn($table, 'raw_payload')) {
            $query->where(function (Builder $q) {
                $q->whereNull('raw_payload')
                    ->orWhere(function (Builder $inner) {
                        $inner->whereRaw(
                            "UPPER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.cancelStatus.cancelState')), ''))) NOT IN (?, ?, ?, ?)",
                            ['CANCELED', 'CANCELLED', 'CANCEL_REQUESTED', 'CANCELLATION_REQUESTED']
                        )->whereRaw(
                            "UPPER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.orderPaymentStatus')), JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.paymentSummary.paymentStatus')), ''))) NOT IN (?, ?)",
                            ['FULLY_REFUNDED', 'REFUNDED']
                        );
                    });
            });
        }

        if ($slug === 'amazon' && Schema::hasColumn($table, 'raw_data')) {
            $query->where(function (Builder $q) {
                $q->whereNull('raw_data')
                    ->orWhereRaw(
                        "UPPER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.OrderStatus')), ''))) NOT IN (?, ?)",
                        ['CANCELED', 'CANCELLED']
                    );
            });
        }

        return $query;
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
                $items = $order->relationLoaded('items') ? $order->items : collect();
                $skus = $items->pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->unique()->values();
                $titles = $items->pluck('title')->map(fn ($s) => trim((string) $s))->filter()->values();
                $qty = (int) $items->sum(fn ($i) => (int) ($i->quantity ?? 0));
                $sku = $skus->isEmpty() ? '' : (string) $skus->first();
                if ($skus->count() > 1) {
                    $sku .= ' +'.($skus->count() - 1);
                }
                $decoded = AmazonOrder::decodeRawPayload($order->raw_data ?? null);
                $tn = trim((string) ($decoded['tracking_number'] ?? ''));
                $carrier = trim((string) ($decoded['carrier'] ?? ''));

                return [
                    'status' => (string) ($order->status ?? ''),
                    'order_date' => $order->order_date ?? null,
                    'updated_at' => $order->updated_at ?? null,
                    'sku' => $sku,
                    'display_title' => (string) ($titles->first() ?? ''),
                    'quantity' => $qty > 0 ? $qty : 1,
                    'amount' => is_numeric($order->total_amount ?? null) ? (float) $order->total_amount : null,
                    'order_id' => (string) ($order->amazon_order_id ?? ''),
                    'order_number' => (string) ($order->amazon_order_id ?? ''),
                    'import_status' => (string) ($order->import_status ?? ''),
                    'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                    'raw_payload' => $order->raw_data ?? null,
                    'tracking_number' => $tn !== '' ? $tn : null,
                    'tracking_company' => $carrier !== '' ? $carrier : null,
                    'show_id' => (int) $order->id,
                ];
            })(),
            'temu', 'temu2' => [
                'status' => (string) ($order->parent_order_status_text ?: $order->order_status_text ?: ''),
                'order_date' => $order->parent_order_time ?? null,
                'updated_at' => $order->order_update_time ?? $order->updated_at ?? null,
                'sku' => (string) ($order->display_sku ?: $order->ext_code ?: $order->product_sku_id ?: ''),
                'catalog_sku' => (string) ($order->ext_code ?: $order->display_sku ?: $order->product_sku_id ?: ''),
                'display_sku' => (string) ($order->display_sku ?? ''),
                'display_title' => (string) ($order->goods_name ?? ''),
                'quantity' => (int) ($order->quantity ?? 1),
                'amount' => $this->firstPositiveAmount(
                    $order->order_base_amount ?? null,
                    $order->order_total_amount ?? null,
                    TemuOrderAmountParser::amountFromOrder($order)
                ),
                // Prefer parent PO (matches Temu API / Sites order_id).
                'order_id' => (string) ($order->parent_order_sn ?: $order->order_sn ?: ''),
                'order_number' => (string) ($order->parent_order_sn ?: $order->order_sn ?: ''),
                'import_status' => (string) ($order->import_status ?? ''),
                'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                'raw_payload' => $order->raw_json ?? null,
                // Filled by temu:pull-tracking / temu2:pull-tracking (Temu OpenAPI).
                'tracking_number' => isset($order->tracking_number) ? trim((string) $order->tracking_number) ?: null : null,
                'tracking_company' => isset($order->carrier) ? trim((string) $order->carrier) ?: null : null,
                'show_id' => (int) $order->id,
            ],
            'tiktok', 'tiktok2' => [
                'status' => (string) ($order->order_status ?: $order->line_status ?: ''),
                'order_date' => $order->order_created_at ?? null,
                'updated_at' => $order->order_updated_at ?? $order->updated_at ?? null,
                'sku' => (string) ($order->seller_sku ?: $order->sku_id ?: ''),
                'catalog_sku' => (string) ($order->seller_sku ?: ''),
                'display_title' => (string) ($order->product_name ?? ''),
                'quantity' => max(1, (int) ($order->quantity ?? 1)),
                'amount' => $this->firstPositiveAmount(
                    $order->order_amount ?? null,
                    isset($order->sale_price)
                        ? ((float) $order->sale_price) * max(1, (int) ($order->quantity ?? 1))
                        : null,
                    $order->original_price ?? null
                ),
                'order_id' => (string) ($order->order_id ?? ''),
                'order_number' => (string) ($order->order_id ?? ''),
                'import_status' => (string) ($order->import_status ?? ''),
                'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                'raw_payload' => $order->raw_json ?? null,
                'tracking_number' => isset($order->tracking_number) ? trim((string) $order->tracking_number) ?: null : null,
                'tracking_company' => isset($order->shipping_provider) ? trim((string) $order->shipping_provider) ?: null : null,
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
                'tracking_company' => isset($order->shipping_company) ? trim((string) $order->shipping_company) ?: null : null,
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
                'tracking_company' => isset($order->carrier_name) ? trim((string) $order->carrier_name) ?: null : null,
                'show_id' => (int) $order->id,
            ],
            default => (function () use ($order) {
                $tn = null;
                foreach (['tracking_number', 'trackingNumber'] as $field) {
                    if (isset($order->{$field}) && trim((string) $order->{$field}) !== '') {
                        $tn = trim((string) $order->{$field});
                        break;
                    }
                }
                $carrier = null;
                foreach (['tracking_company', 'carrier', 'carrier_name', 'shipping_company'] as $field) {
                    if (isset($order->{$field}) && trim((string) $order->{$field}) !== '') {
                        $carrier = trim((string) $order->{$field});
                        break;
                    }
                }

                return [
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
                    'tracking_number' => $tn,
                    'tracking_company' => $carrier,
                    'show_id' => (int) $order->id,
                ];
            })(),
        };
    }

    /**
     * Use the stored amount when it is &gt; 0; otherwise recover the original
     * sale from line items / payload. Cancelled and refunded orders often store 0.
     */
    protected function resolveNormalizedAmount(string $slug, object $order, mixed $primary): ?float
    {
        $picked = $this->firstPositiveAmount($primary);
        if ($picked !== null) {
            return $picked;
        }

        return match ($slug) {
            'amazon' => $this->amazonEffectiveAmount($order),
            'temu', 'temu2' => $this->firstPositiveAmount(
                $order->order_base_amount ?? null,
                $order->order_total_amount ?? null,
                TemuOrderAmountParser::amountFromOrder($order),
                $this->amountFromPayload($order->amount_raw_json ?? null),
                $this->amountFromPayload($order->raw_json ?? null)
            ),
            'tiktok', 'tiktok2' => $this->firstPositiveAmount(
                $order->order_amount ?? null,
                isset($order->sale_price)
                    ? ((float) $order->sale_price) * max(1, (int) ($order->quantity ?? 1))
                    : null,
                $order->original_price ?? null,
                $this->amountFromPayload($order->raw_json ?? null)
            ),
            'bestbuy', 'macy' => $this->firstPositiveAmount(
                method_exists($order, 'lineAmount') ? $order->lineAmount() : null,
                $order->unit_price ?? null,
                isset($order->unit_price)
                    ? ((float) $order->unit_price) * max(1, (int) ($order->quantity ?? 1))
                    : null
            ),
            'wayfair' => $this->firstPositiveAmount(
                $order->total_price ?? null,
                isset($order->unit_price)
                    ? ((float) $order->unit_price) * max(1, (int) ($order->quantity ?? 1))
                    : null
            ),
            'doba' => $this->firstPositiveAmount(
                $order->total_price ?? null,
                $order->item_price ?? null,
                isset($order->item_price)
                    ? ((float) $order->item_price) * max(1, (int) ($order->quantity ?? 1))
                    : null
            ),
            default => $this->firstPositiveAmount(
                $order->amount ?? null,
                $order->total_amount ?? null,
                $order->total_price ?? null,
                $order->order_total_amount ?? null,
                $order->order_amount ?? null,
                $order->item_price ?? null,
                isset($order->sale_price)
                    ? ((float) $order->sale_price) * max(1, (int) ($order->quantity ?? 1))
                    : null,
                isset($order->unit_price)
                    ? ((float) $order->unit_price) * max(1, (int) ($order->quantity ?? 1))
                    : null,
                $this->amountFromPayload($order->raw_payload ?? $order->raw_json ?? null)
            ),
        };
    }

    protected function amazonEffectiveAmount(object $order): ?float
    {
        $fromItems = 0.0;
        if (method_exists($order, 'relationLoaded') && $order->relationLoaded('items')) {
            foreach ($order->items as $item) {
                $fromItems += (float) ($item->price ?? 0);
            }
        }

        $raw = AmazonOrder::decodeRawPayload($order->raw_data ?? null);
        $fromJson = $this->firstPositiveAmount(
            data_get($raw, 'OrderTotal.Amount'),
            data_get($raw, 'orderTotal.amount'),
            data_get($raw, 'OrderTotal.amount')
        );

        return $this->firstPositiveAmount(
            $order->total_amount ?? null,
            $fromItems > 0 ? $fromItems : null,
            $fromJson
        );
    }

    protected function firstPositiveAmount(mixed ...$values): ?float
    {
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_string($value)) {
                $value = str_replace([',', '$', ' '], '', trim($value));
            }
            if (! is_numeric($value)) {
                continue;
            }
            $n = round((float) $value, 2);
            if ($n > 0) {
                return $n;
            }
        }

        return null;
    }

    protected function amountFromPayload(mixed $payload): ?float
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($payload)) {
            return null;
        }

        $direct = $this->firstPositiveAmount(
            $payload['amount'] ?? null,
            $payload['total'] ?? null,
            $payload['total_amount'] ?? null,
            $payload['order_total'] ?? null,
            $payload['order_total_amount'] ?? null,
            $payload['order_base_amount'] ?? null,
            $payload['grand_total'] ?? null,
            $payload['item_price'] ?? null,
            $payload['itemPrice'] ?? null,
            $payload['sale_price'] ?? null,
            $payload['order_amount'] ?? null,
            $payload['goodsAmount'] ?? null,
            $payload['baseAmount'] ?? null,
            data_get($payload, 'OrderTotal.Amount'),
            data_get($payload, 'orderTotal.amount'),
            data_get($payload, 'pricingSummary.total'),
            data_get($payload, 'pricingSummary.priceSubtotal'),
            data_get($payload, 'paymentSummary.total'),
            data_get($payload, 'lineItemCost.value'),
            data_get($payload, 'totalFee.amount'),
            TemuOrderAmountParser::pickMoney($payload['parentOrderMap'] ?? [], ['basePriceTotal', 'retailPriceTotal', 'estimatedRevenue', 'customerPaid'])
        );
        if ($direct !== null) {
            return $direct;
        }

        $lines = $payload['lineItems'] ?? $payload['line_items'] ?? $payload['items'] ?? null;
        if (! is_array($lines)) {
            return null;
        }
        $sum = 0.0;
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lineAmt = $this->firstPositiveAmount(
                data_get($line, 'lineItemCost.value'),
                $line['amount'] ?? null,
                $line['total'] ?? null,
                $line['item_price'] ?? null,
                $line['price'] ?? null
            );
            if ($lineAmt !== null) {
                $sum += $lineAmt;
            }
        }

        return $sum > 0 ? round($sum, 2) : null;
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
     * When a channel has no saved Ch Orders URL, store the in-app marketplace orders page.
     */
    protected function autoFillMissingChOrdersLinks(): void
    {
        if (! Schema::hasTable('channel_master') || ! Schema::hasColumn('channel_master', 'ch_orders_link')) {
            return;
        }

        try {
            $managerByMpKey = $this->managerChannelsByMpKey();
            $rows = ChannelMaster::query()
                ->where(function ($q) {
                    $q->whereNull('ch_orders_link')->orWhere('ch_orders_link', '');
                })
                ->get(['id', 'channel']);

            foreach ($rows as $row) {
                $manager = $managerByMpKey[strtolower(trim((string) $row->channel))] ?? null;
                $slug = $manager['slug'] ?? null;
                if (! is_string($slug) || $slug === '') {
                    continue;
                }
                ChannelMaster::query()->where('id', $row->id)->update([
                    'ch_orders_link' => route('marketplace.orders', $slug),
                ]);
            }
        } catch (\Throwable) {
            // Display still falls back to orders_url / order_url.
        }
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

        $this->autoFillMissingChOrdersLinks();
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
            if (empty($meta['ch_orders_link'])) {
                $meta['ch_orders_link'] = route('marketplace.orders', $slug);
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

        $scanDone = $this->scanDoneLast24HoursCount();
        $inReceived = $this->inReceivedOrdersCount();

        return [
            'channel_count' => (int) $channelCount,
            'pending_total' => $pendingTotal,
            'fulfilled_24h' => $this->fulfilledLast24HoursCount(),
            'scan_done_24h' => $scanDone,
            'in_transit_total' => $this->inTransitOrdersCount(),
            'in_received_total' => $inReceived,
            'received_by_carrier_total' => $scanDone + $inReceived,
            'invoiced_total' => $this->invoicedOrdersCount(),
            'delivered_total' => $this->deliveredOrdersCount(),
            'all_order_total' => $this->allOrdersCount(),
            'calculated_at' => now($this->sofTimezone())->toDateTimeString(),
        ];
    }

    /**
     * Upsert one Eastern-day history row (used by sof:snapshot-daily at 00:00 EST/EDT).
     * Always writes a row for that date — even when every metric is unchanged vs the prior day.
     *
     * @param  bool  $onlyIfMissing  When true, skip update if the day already has a row (catch-up safe).
     */
    public function saveDailySnapshot(?string $snapshotDate = null, bool $onlyIfMissing = false): SalesOrderFulfillmentDailySummary
    {
        if (! Schema::hasTable('sales_order_fulfillment_daily_data')) {
            throw new \RuntimeException('sales_order_fulfillment_daily_data table missing — run migrations.');
        }

        $date = $snapshotDate ?: now($this->sofTimezone())->subDay()->toDateString();

        $existing = SalesOrderFulfillmentDailySummary::query()
            ->whereDate('snapshot_date', $date)
            ->first();

        if ($onlyIfMissing && $existing) {
            return $existing;
        }

        $summary = $this->collectSummaryTotals();
        // Stamp every write so identical counts still produce a fresh daily record.
        $summary['recorded_at'] = now($this->sofTimezone())->toIso8601String();
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
            : 'Daily SOF snapshot (Eastern day)';
        $row->updated_at = now();
        $row->save();

        return $row->fresh();
    }

    /**
     * Read a history metric from a daily summary_data blob.
     * Received by carrier = stored combined total, else Shipped/Received + In Received.
     */
    protected function historyMetricValue(array $sd, string $metric): ?float
    {
        if ($metric === 'received_by_carrier_total') {
            if (array_key_exists('received_by_carrier_total', $sd)) {
                return (float) $sd['received_by_carrier_total'];
            }
            if (array_key_exists('scan_done_24h', $sd) || array_key_exists('in_received_total', $sd)) {
                return (float) ($sd['scan_done_24h'] ?? 0) + (float) ($sd['in_received_total'] ?? 0);
            }

            return null;
        }

        if (! array_key_exists($metric, $sd)) {
            return null;
        }

        return (float) $sd[$metric];
    }

    /** Metric keys used by history dots / charts (badge → summary_data key). */
    public static function historyMetricKeys(): array
    {
        return [
            'channel_count' => 'Channels',
            'pending_total' => 'Pending',
            'fulfilled_24h' => 'Label Created / No Scan',
            'received_by_carrier_total' => 'Received by carrier',
            'in_transit_total' => 'In Transit',
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
                    $metrics[$key] = [
                        $this->historyMetricValue($older, $key),
                        $this->historyMetricValue($newer, $key),
                    ];
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
                $start = now($this->sofTimezone())->subDays($days)->toDateString();
                $query->where('snapshot_date', '>=', $start);
            }

            $chartData = [];
            foreach ($query->get() as $row) {
                $sd = $row->summary_data ?? [];
                $value = $this->historyMetricValue($sd, $metric);
                if ($value === null) {
                    continue;
                }
                $chartData[] = [
                    'date' => Carbon::parse($row->snapshot_date, $this->sofTimezone())->format('M d'),
                    'value' => $value,
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

    /**
     * SKU × site GROI / GPFT / NROI / NPFT — same formulas as marketplace Analytics.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachSkuSiteProfitPctToRows(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $ctxBySlug = $this->siteProfitContextBySlug();
        $priceBySlugNorm = $this->listingPriceBySlugAndNorm($rows);

        foreach ($rows as &$row) {
            $slug = (string) ($row['mm_slug'] ?? '');
            $ctx = $ctxBySlug[$slug] ?? [
                'margin' => 0.80,
                'ads' => 0.0,
                'ship_key' => 'ship',
                'is_temu' => false,
                'is_temu2' => false,
                'no_ads' => false,
            ];
            $listing = 0.0;
            foreach ($this->rowLookupSkus($row) as $lookupSku) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup($lookupSku);
                if ($norm !== '' && isset($priceBySlugNorm[$slug][$norm])) {
                    $listing = (float) $priceBySlugNorm[$slug][$norm];
                    break;
                }
            }
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $amount = is_numeric($row['amount'] ?? null) ? (float) $row['amount'] : 0.0;
            if ($amount <= 0 && $listing > 0) {
                $amount = round($listing * $qty, 2);
                $row['amount'] = $amount;
            }
            $orderUnit = $amount > 0 ? round($amount / $qty, 2) : 0.0;
            $price = $listing > 0 ? $listing : $orderUnit;

            $lp = is_numeric($row['lp'] ?? null) ? (float) $row['lp'] : 0.0;
            $ship = $this->shipForSiteRow($row, $ctx);

            $metrics = $this->computeSkuSiteProfitPct($slug, $price, $lp, $ship, $ctx);
            $row['groi_pct'] = $metrics['groi_pct'];
            $row['gpft_pct'] = $metrics['gpft_pct'];
            $row['nroi_pct'] = $metrics['nroi_pct'];
            $row['npft_pct'] = $metrics['npft_pct'];
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, array{margin: float, ads: float, ship_key: string, is_temu: bool, is_temu2: bool, no_ads: bool}>
     */
    protected function siteProfitContextBySlug(): array
    {
        $pctByName = [];
        try {
            if (Schema::hasTable('marketplace_percentages')) {
                foreach (MarketplacePercentage::query()->get(['marketplace', 'percentage']) as $row) {
                    $key = strtolower(trim((string) ($row->marketplace ?? '')));
                    if ($key === '' || isset($pctByName[$key])) {
                        continue;
                    }
                    $pct = (float) $row->percentage;
                    $pctByName[$key] = $pct > 1 ? $pct / 100 : $pct;
                }
            }
        } catch (\Throwable) {
            $pctByName = [];
        }

        $adsByName = [];
        try {
            if (Schema::hasTable('channel_master_calculated_data')) {
                foreach (ChannelMasterCalculatedData::query()->get(['channel', 'ads_percentage', 'tacos_percentage']) as $row) {
                    $key = strtolower(trim((string) ($row->channel ?? '')));
                    if ($key === '' || isset($adsByName[$key])) {
                        continue;
                    }
                    $ads = $row->ads_percentage;
                    if ($ads === null || $ads === '') {
                        $ads = $row->tacos_percentage;
                    }
                    $adsByName[$key] = is_numeric($ads) ? (float) $ads : 0.0;
                }
            }
        } catch (\Throwable) {
            $adsByName = [];
        }

        $noAds = ['doba', 'purchasingpower', 'topdawg', 'shein', 'faire', 'temu2', 'alibaba'];
        // Prepaid-label channels: ship is not a seller cost, so GPFT/GROI/NPFT/NROI ignore it.
        $noShip = ['faire', 'topdawg', 'purchasingpower', 'wayfair', 'doba'];
        $out = [];

        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            if (! ($channel['enabled'] ?? false)) {
                continue;
            }
            $slug = (string) ($channel['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $candidates = array_merge(
                [$slug],
                $channel['mp_channel_keys'] ?? [],
                match ($slug) {
                    'ebay1' => ['Ebay', 'eBay', 'ebay'],
                    'ebay2' => ['EbayTwo', 'eBay 2'],
                    'ebay3' => ['EbayThree', 'eBay 3'],
                    'tiktok', 'tiktok2' => ['TiktokShop', 'TikTok Shop'],
                    'purchasingpower' => ['Purchase'],
                    'newegg' => ['Neweggb2c', 'Newegg'],
                    default => [],
                }
            );
            $margin = 0.80;
            foreach ($candidates as $name) {
                $key = strtolower(trim((string) $name));
                if ($key !== '' && isset($pctByName[$key])) {
                    $margin = (float) $pctByName[$key];
                    break;
                }
            }
            $ads = 0.0;
            foreach ($candidates as $name) {
                $key = strtolower(trim((string) $name));
                if ($key !== '' && isset($adsByName[$key])) {
                    $ads = (float) $adsByName[$key];
                    break;
                }
            }
            $out[$slug] = [
                'margin' => $margin > 0 ? $margin : 0.80,
                'ads' => in_array($slug, $noAds, true) ? 0.0 : $ads,
                'ship_key' => match (true) {
                    in_array($slug, $noShip, true) => 'none',
                    in_array($slug, ['temu', 'temu2'], true) => 'ship_temu',
                    $slug === 'bestbuy' => 'ship_bb',
                    default => 'ship',
                },
                'is_temu' => $slug === 'temu',
                'is_temu2' => $slug === 'temu2',
                'no_ads' => in_array($slug, $noAds, true),
            ];
        }

        return $out;
    }

    /**
     * @param  array{margin: float, ads: float, ship_key: string, is_temu: bool, is_temu2: bool, no_ads: bool}  $ctx
     * @return array{groi_pct: ?float, gpft_pct: ?float, nroi_pct: ?float, npft_pct: ?float}
     */
    protected function computeSkuSiteProfitPct(string $slug, float $price, float $lp, float $ship, array $ctx): array
    {
        $empty = ['groi_pct' => null, 'gpft_pct' => null, 'nroi_pct' => null, 'npft_pct' => null];
        if ($price <= 0) {
            return $empty;
        }

        $margin = (float) ($ctx['margin'] ?? 0.80);
        $ads = (float) ($ctx['ads'] ?? 0);
        $isTemu = ! empty($ctx['is_temu']);
        $isTemu2 = ! empty($ctx['is_temu2']);
        $noAds = ! empty($ctx['no_ads']) || $isTemu2;
        $shipKey = (string) ($ctx['ship_key'] ?? 'ship');
        if ($shipKey === 'none' || in_array($slug, ['faire', 'topdawg', 'purchasingpower', 'wayfair', 'doba'], true)) {
            $ship = 0.0;
        }

        if ($isTemu || $isTemu2) {
            $rPrice = TemuShopifySalesService::computeRPrice($price);
            $full = TemuShopifySalesService::computeFullTemuPrice($price);
            $gpft = $full > 0
                ? round(TemuShopifySalesService::computeGpftPercent($full, $margin, $lp, $ship), 2)
                : null;
            $groi = $lp > 0 && $rPrice > 0
                ? round(TemuShopifySalesService::computeGroiPercent($rPrice, $margin, $lp, $ship), 2)
                : null;
        } else {
            $gross = ($price * $margin) - $lp - $ship;
            $gpft = round(($gross / $price) * 100, 2);
            $groi = $lp > 0 ? round(($gross / $lp) * 100, 2) : null;
        }

        if ($gpft === null && $groi === null) {
            return $empty;
        }

        if ($noAds) {
            return [
                'groi_pct' => $groi,
                'gpft_pct' => $gpft,
                'nroi_pct' => $groi,
                'npft_pct' => $gpft,
            ];
        }

        $npft = $gpft !== null
            ? ($ads == 100.0 ? $gpft : round($gpft - $ads, 2))
            : null;
        if ($isTemu) {
            $nroi = $groi !== null
                ? ($ads == 100.0 ? $groi : round($groi - $ads, 2))
                : null;
        } else {
            $gross = ($price * $margin) - $lp - $ship;
            $adsPerUnit = $price * ($ads / 100);
            $nroi = $lp > 0 ? round((($gross - $adsPerUnit) / $lp) * 100, 2) : null;
        }

        return [
            'groi_pct' => $groi,
            'gpft_pct' => $gpft,
            'nroi_pct' => $nroi,
            'npft_pct' => $npft,
        ];
    }

    /**
     * @param  array{ship_key: string}  $ctx
     */
    protected function shipForSiteRow(array $row, array $ctx): float
    {
        $key = (string) ($ctx['ship_key'] ?? 'ship');
        if ($key === 'none') {
            return 0.0;
        }
        $v = $row[$key] ?? $row['ship'] ?? null;

        return is_numeric($v) ? (float) $v : 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, float>>
     */
    protected function listingPriceBySlugAndNorm(array $rows): array
    {
        $skusBySlug = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['mm_slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            foreach ($this->rowLookupSkus($row) as $sku) {
                $skusBySlug[$slug][$sku] = true;
            }
        }

        $out = [];
        $allSkus = [];
        foreach ($skusBySlug as $skuSet) {
            foreach (array_keys($skuSet) as $sku) {
                $allSkus[$sku] = true;
            }
        }
        $shopifyBySku = $allSkus === []
            ? collect()
            : ShopifySku::mapByProductSkus(array_keys($allSkus));

        foreach ($skusBySlug as $slug => $skuSet) {
            $skus = array_keys($skuSet);
            $map = [];
            foreach ($this->listingPriceSourcesForSlug($slug) as $source) {
                $map = $map + $this->priceMapFromTable($source[0], $source[1], $source[2], $skus);
            }
            foreach ($skus as $sku) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm === '' || isset($map[$norm])) {
                    continue;
                }
                $shopify = $shopifyBySku->get($sku);
                $p = $shopify ? (float) ($shopify->price ?? 0) : 0.0;
                if ($p > 0) {
                    $map[$norm] = $p;
                }
            }
            $out[$slug] = $map;
        }

        return $out;
    }

    /**
     * Listing price tables used by each marketplace Analytics page.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    protected function listingPriceSourcesForSlug(string $slug): array
    {
        return match ($slug) {
            'amazon' => [['amazon_datsheets', 'sku', 'price']],
            'ebay1' => [['ebay_metrics', 'sku', 'ebay_price']],
            'ebay2' => [['ebay_2_metrics', 'sku', 'ebay_price']],
            'ebay3' => [['ebay_3_metrics', 'sku', 'ebay_price']],
            'temu' => [['temu_metrics', 'sku', 'base_price'], ['temu_pricing', 'sku', 'base_price']],
            'temu2' => [['temu2_metrics', 'sku', 'base_price'], ['temu2_pricing', 'sku', 'base_price']],
            'reverb' => [['reverb_products', 'sku', 'price']],
            'shein' => [['shein_pricing_prices', 'sku', 'special_offer_price'], ['shein_pricing_prices', 'sku', 'price']],
            'faire' => [['faire_metric', 'sku', 'price']],
            'doba' => [['doba_daily_data', 'sku', 'anticipated_income']],
            'wayfair' => [['wayfair_daily_data', 'sku', 'unit_price']],
            'aliexpress' => [['aliexpress_pricing_prices', 'sku', 'price']],
            'alibaba' => [['alibaba_pricing_prices', 'sku', 'price'], ['alibaba_metrics', 'sku', 'price']],
            'topdawg' => [['topdawg_products', 'sku', 'price']],
            'purchasingpower' => [['purchasing_power_products', 'sku', 'price']],
            'bestbuy' => [['bestbuy_price_data', 'sku', 'price'], ['bestbuy_usa_products', 'sku', 'price']],
            'macy' => [['macys_price_data', 'sku', 'price'], ['macy_products', 'sku', 'price']],
            'newegg' => [['newegg_pricing_prices', 'sku', 'price']],
            'tiktok' => [['tiktok_metrics', 'sku', 'price'], ['tiktok_products', 'sku', 'price']],
            'tiktok2' => [['tiktok_products_two', 'sku', 'price']],
            default => [],
        };
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, float>
     */
    protected function priceMapFromTable(string $table, string $skuCol, string $priceCol, array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable($table)) {
            return [];
        }
        try {
            if (! Schema::hasColumn($table, $skuCol) || ! Schema::hasColumn($table, $priceCol)) {
                return [];
            }
            $map = [];
            $lookup = array_values(array_unique(array_filter(array_merge(
                $skus,
                array_map('strtoupper', $skus)
            ))));
            DB::table($table)
                ->whereIn($skuCol, $lookup)
                ->get([$skuCol, $priceCol])
                ->each(function ($r) use (&$map, $skuCol, $priceCol) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($r->{$skuCol} ?? ''));
                    $p = (float) ($r->{$priceCol} ?? 0);
                    if ($norm !== '' && $p > 0 && ! isset($map[$norm])) {
                        $map[$norm] = $p;
                    }
                });

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    protected function rowLookupSku(array $row): string
    {
        $skus = $this->rowLookupSkus($row);

        return $skus[0] ?? '';
    }

    /**
     * @return list<string>
     */
    protected function rowLookupSkus(array $row): array
    {
        $out = [];
        foreach (['sku', 'catalog_sku', 'display_sku', 'seller_sku', 'ext_code'] as $key) {
            $sku = trim((string) ($row[$key] ?? ''));
            $sku = preg_replace('/\s+\+\d+$/', '', $sku) ?? $sku;
            $sku = trim((string) $sku);
            if ($sku === '' || isset($out[$sku])) {
                continue;
            }
            $out[$sku] = $sku;
        }

        return array_values($out);
    }

    /**
     * CP, freight, LP, ship rates from product_master.Values (same keys as Product Master).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachCostShipDetailsToRows(array $rows): array
    {
        if ($rows === [] || ! Schema::hasTable('product_master')) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            foreach ($this->rowLookupSkus($row) as $sku) {
                $skus[$sku] = true;
            }
        }
        if ($skus === []) {
            return $rows;
        }

        $shopifyBySku = ShopifySku::mapByProductSkus(array_keys($skus));
        foreach ($shopifyBySku as $shopify) {
            $ssku = trim((string) ($shopify->sku ?? ''));
            if ($ssku !== '') {
                $skus[$ssku] = true;
            }
        }

        $scalar = static function (array $values, string ...$keys) {
            $lookup = [];
            foreach ($values as $k => $v) {
                $lookup[strtolower((string) $k)] = $v;
            }
            foreach ($keys as $key) {
                $hit = $lookup[strtolower($key)] ?? null;
                if ($hit === null || $hit === '') {
                    continue;
                }
                if (is_numeric($hit)) {
                    return 0 + $hit;
                }
                $trimmed = trim((string) $hit);
                if ($trimmed === '') {
                    continue;
                }
                $numeric = str_replace(',', '', $trimmed);
                if (is_numeric($numeric)) {
                    return 0 + $numeric;
                }

                return $trimmed;
            }

            return null;
        };

        $byNorm = [];
        try {
            ProductMaster::query()
                ->whereIn('sku', array_keys($skus))
                ->get(['sku', 'Values'])
                ->each(function ($product) use (&$byNorm, $scalar) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($product->sku ?? ''));
                    if ($norm === '' || isset($byNorm[$norm])) {
                        return;
                    }
                    $values = is_array($product->Values)
                        ? $product->Values
                        : (is_string($product->Values) ? (json_decode($product->Values, true) ?: []) : []);

                    $l = $scalar($values, 'l');
                    $w = $scalar($values, 'w');
                    $h = $scalar($values, 'h');
                    $frght = $scalar($values, 'frght', 'freight', 'frg');
                    if (($frght === null || $frght === '') && is_numeric($l) && is_numeric($w) && is_numeric($h)) {
                        $cbm = (((float) $l * 2.54) * ((float) $w * 2.54) * ((float) $h * 2.54)) / 1000000;
                        $frght = round($cbm * 200, 2);
                    }

                    $lp = $scalar($values, 'lp');
                    if (($lp === null || $lp === '' || (is_numeric($lp) && (float) $lp <= 0)) && isset($product->lp) && is_numeric($product->lp)) {
                        $lp = (float) $product->lp;
                    }

                    $ship = $scalar($values, 'ship');
                    if (($ship === null || $ship === '') && isset($product->ship) && is_numeric($product->ship)) {
                        $ship = (float) $product->ship;
                    }

                    $shipBb = ProductMasterShipBb::forPricing($values, $product);
                    if ($shipBb <= 0) {
                        $shipBb = $scalar($values, 'ship_bb');
                        if (($shipBb === null || $shipBb === '') && isset($product->ship_bb) && is_numeric($product->ship_bb)) {
                            $shipBb = (float) $product->ship_bb;
                        }
                    }

                    $storedTemu = ProductMasterTemuShip::stored($values, $product);
                    if ($storedTemu === null) {
                        $storedTemu = $scalar($values, 'ship_temu');
                    }
                    $shipTemu = $storedTemu;
                    if ($shipTemu === null) {
                        $computedTemu = ProductMasterTemuShip::forPricing($values, $product);
                        $shipTemu = $computedTemu > 0 ? $computedTemu : null;
                    } else {
                        $shipTemu = round((float) $shipTemu, 2);
                    }

                    $byNorm[$norm] = [
                        'cp' => $scalar($values, 'cp'),
                        'frght' => is_numeric($frght) ? round((float) $frght, 2) : $frght,
                        'lp' => is_numeric($lp) ? round((float) $lp, 2) : $lp,
                        'ship' => is_numeric($ship) ? round((float) $ship, 2) : $ship,
                        'ship_temu' => $shipTemu,
                        'ship_bb' => is_numeric($shipBb) ? round((float) $shipBb, 2) : $shipBb,
                        'l' => $l,
                        'w' => $w,
                        'h' => $h,
                        'wt_act' => $scalar($values, 'wt_act'),
                        'wt_decl' => $scalar($values, 'wt_decl'),
                    ];
                });
        } catch (\Throwable) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $empty = [
                'cp' => null,
                'frght' => null,
                'lp' => null,
                'ship' => null,
                'ship_temu' => null,
                'ship_bb' => null,
            ];
            $extra = $empty;
            foreach ($this->rowLookupSkus($row) as $sku) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm !== '' && isset($byNorm[$norm])) {
                    $extra = $byNorm[$norm];
                    break;
                }
                $shopify = $shopifyBySku->get($sku);
                if ($shopify) {
                    $sNorm = ShopifySku::normalizeSkuForShopifyLookup((string) ($shopify->sku ?? ''));
                    if ($sNorm !== '' && isset($byNorm[$sNorm])) {
                        $extra = $byNorm[$sNorm];
                        break;
                    }
                }
            }
            foreach ($extra as $k => $v) {
                if (in_array($k, ['l', 'w', 'h', 'wt_act', 'wt_decl'], true)
                    && array_key_exists($k, $row)
                    && $row[$k] !== null
                    && $row[$k] !== '') {
                    continue;
                }
                $row[$k] = $v;
            }
        }
        unset($row);

        return $rows;
    }
}
