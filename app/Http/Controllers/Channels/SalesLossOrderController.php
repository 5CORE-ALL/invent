<?php

namespace App\Http\Controllers\Channels;

use App\Models\B5cB2bOrder;
use App\Models\ChannelMasterCalculatedData;
use App\Models\MarketplacePercentage;
use App\Models\ShopifySku;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\VeeqoApiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Sales Loss Order — all marketplace orders.
 * Same row shape as /sales-order-fulfillment All Order, without the 30-day default.
 */
class SalesLossOrderController extends SalesOrderFulfillmentController
{
    /**
     * Channels that are not Marketplace Manager channels (no orders table in the registry) but have
     * their own sales data. slug => label, short code, names used to find margin / ads %.
     *
     * @var array<string, array{label: string, short: string, names: list<string>}>
     */
    protected const EXTRA_CHANNELS = [
        'depop' => ['label' => 'Depop', 'short' => 'DP', 'names' => ['depop']],
        'vinted' => ['label' => 'Vinted', 'short' => 'VT', 'names' => ['vinted']],
        'mercari' => ['label' => 'Mercari', 'short' => 'MC', 'names' => ['mercari', 'mercari w ship', 'mercari wo ship', 'mercariwship', 'mercariwoship']],
        'business5core' => ['label' => 'Business5Core', 'short' => 'B5', 'names' => ['business5core', 'business 5core', 'business 5 core (b2b)', 'business5coreb2b', 'b5cb2b']],
    ];

    public function index(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        $sloChannels = collect(MarketplaceManagerRegistry::channels())
            ->filter(fn ($c) => ($c['enabled'] ?? false) === true)
            ->map(fn ($c) => [
                'slug' => (string) ($c['slug'] ?? ''),
                'label' => (string) ($c['label'] ?? ($c['slug'] ?? '')),
            ])
            ->filter(fn ($c) => ($c['slug'] ?? '') !== '')
            ->values()
            ->all();
        foreach (self::EXTRA_CHANNELS as $slug => $meta) {
            $sloChannels[] = ['slug' => $slug, 'label' => $meta['label']];
        }

        $tz = self::SOF_TIMEZONE;

        return view('channels.sales_loss_order', [
            'sloChannels' => $sloChannels,
            'sloDateFrom' => now($tz)->subDays(30)->toDateString(),
            'sloDateTo' => now($tz)->toDateString(),
        ]);
    }

    /**
     * All marketplace orders (every status).
     * Same 30-day default as /sales-order-fulfillment All Order.
     */
    public function data(): JsonResponse
    {
        try {
            @set_time_limit(120);

            $rows = $this->collectSloRows();

            $amountTotal = 0.0;
            $channels = [];
            foreach ($rows as $row) {
                if (is_numeric($row['amount'] ?? null)) {
                    $amountTotal += (float) $row['amount'];
                }
                $slug = (string) ($row['mm_slug'] ?? '');
                if ($slug !== '') {
                    $channels[$slug] = true;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
                'channel_count' => count($channels),
                'amount_total' => round($amountTotal, 2),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load sales loss orders.',
                'data' => [],
                'count' => 0,
                'channel_count' => 0,
                'amount_total' => 0,
            ], 500);
        }
    }

    /**
     * Last 30 Eastern days of marketplace orders, lowest SKU-site profit first.
     */
    public function lossMakingData(): JsonResponse
    {
        try {
            @set_time_limit(120);

            $rows = $this->collectSloRows();
            $rows = $this->sortRowsByLowestProfit($rows);

            $lossCount = 0;
            foreach ($rows as $row) {
                if ($this->rowIsLossMaking($row)) {
                    $lossCount++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
                'loss_count' => $lossCount,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load loss-making orders.',
                'data' => [],
                'count' => 0,
                'loss_count' => 0,
            ], 500);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function sortRowsByLowestProfit(array $rows): array
    {
        usort($rows, function (array $a, array $b) {
            $an = $this->sortableProfitValue($a);
            $bn = $this->sortableProfitValue($b);
            if ($an === null && $bn === null) {
                return 0;
            }
            if ($an === null) {
                return 1;
            }
            if ($bn === null) {
                return -1;
            }

            return $an <=> $bn;
        });

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function sortableProfitValue(array $row): ?float
    {
        if (is_numeric($row['npft_pct'] ?? null)) {
            return (float) $row['npft_pct'];
        }
        if (is_numeric($row['gpft_pct'] ?? null)) {
            return (float) $row['gpft_pct'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function rowIsLossMaking(array $row): bool
    {
        $npft = $row['npft_pct'] ?? null;
        $gpft = $row['gpft_pct'] ?? null;

        return (is_numeric($npft) && (float) $npft < 0)
            || (is_numeric($gpft) && (float) $gpft < 0);
    }

    /**
     * Loss summary badges: how many orders (and which products) sold at a loss for
     * L30 / L7 / Yesterday / Today (Eastern days).
     *
     * A row is a loss when its net (or, without net, gross) profit % is negative.
     * Cancelled orders are not counted: nothing was sold.
     * Cached for 3 minutes because it walks every channel; pass ?refresh=1 to rebuild.
     */
    public function lossSummary(): JsonResponse
    {
        try {
            @set_time_limit(120);

            $payload = request()->boolean('refresh')
                ? null
                : \Illuminate\Support\Facades\Cache::get('slo_loss_summary_v1');
            if (! is_array($payload)) {
                $payload = $this->buildLossSummary();
                \Illuminate\Support\Facades\Cache::put('slo_loss_summary_v1', $payload, 180);
            }

            return response()->json(['success' => true] + $payload);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to build the loss summary.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLossSummary(): array
    {
        $tz = $this->sofTimezone();
        $today = now($tz)->startOfDay();
        $periods = [
            'l30' => ['label' => 'L30', 'from' => $today->copy()->subDays(30)->toDateString(), 'to' => $today->toDateString()],
            'l7' => ['label' => 'L7', 'from' => $today->copy()->subDays(7)->toDateString(), 'to' => $today->toDateString()],
            'yesterday' => ['label' => 'Yesterday', 'from' => $today->copy()->subDay()->toDateString(), 'to' => $today->copy()->subDay()->toDateString()],
            'today' => ['label' => 'Today', 'from' => $today->toDateString(), 'to' => $today->toDateString()],
        ];
        foreach ($periods as &$p) {
            $p += ['orders' => 0, 'loss_orders' => 0, 'loss_amount' => 0.0, 'skus' => 0];
        }
        unset($p);

        $seen = [];
        $lossSeen = [];
        $lossSkus = [];
        $lossRows = [];

        foreach ($this->collectSloRows() as $row) {
            if (! empty($row['is_cancelled'])) {
                continue;
            }
            $date = substr((string) ($row['order_date'] ?? ''), 0, 10);
            if ($date === '') {
                continue;
            }
            $orderKey = ($row['mm_slug'] ?? '').'|'.(trim((string) ($row['order_id'] ?? '')) !== '' ? $row['order_id'] : 'row'.($row['row_id'] ?? ''));
            $isLoss = $this->rowIsLossMaking($row);

            $pct = is_numeric($row['npft_pct'] ?? null) ? (float) $row['npft_pct'] : (is_numeric($row['gpft_pct'] ?? null) ? (float) $row['gpft_pct'] : null);
            $amount = is_numeric($row['amount'] ?? null) ? (float) $row['amount'] : 0.0;
            $loss = ($isLoss && $pct !== null && $amount > 0) ? round($amount * $pct / 100, 2) : 0.0;
            $sku = trim((string) ($row['sku'] ?? ''));

            foreach ($periods as $key => &$p) {
                if ($date < $p['from'] || $date > $p['to']) {
                    continue;
                }
                if (! isset($seen[$key][$orderKey])) {
                    $seen[$key][$orderKey] = true;
                    $p['orders']++;
                }
                if ($isLoss) {
                    if (! isset($lossSeen[$key][$orderKey])) {
                        $lossSeen[$key][$orderKey] = true;
                        $p['loss_orders']++;
                    }
                    $p['loss_amount'] += $loss;
                    $lossSkus[$key][$sku !== '' ? $sku : '(no sku)'] = true;
                }
            }
            unset($p);

            if ($isLoss && $date >= $periods['l30']['from']) {
                $lossRows[] = [
                    'sku' => $sku,
                    'title' => trim((string) ($row['display_title'] ?? '')),
                    'channel' => (string) ($row['channel_label'] ?? ''),
                    'order_id' => (string) ($row['order_id'] ?? ''),
                    'date' => substr((string) ($row['order_date'] ?? ''), 0, 16),
                    'qty' => max(1, (int) ($row['quantity'] ?? 1)),
                    'amount' => $amount > 0 ? round($amount, 2) : null,
                    'pct' => $pct,
                    'loss' => $loss,
                ];
            }
        }

        foreach ($periods as $key => &$p) {
            $p['skus'] = count($lossSkus[$key] ?? []);
            $p['loss_amount'] = round($p['loss_amount'], 2);
        }
        unset($p);

        return [
            'generated_at' => now($tz)->format('Y-m-d H:i'),
            'periods' => $periods,
            'rows' => $lossRows,
        ];
    }

    /**
     * Marketplace Manager channels plus Depop, Vinted, Mercari and Business5Core, newest first.
     *
     * @return list<array<string, mixed>>
     */
    protected function collectSloRows(): array
    {
        $rows = $this->collectOrderRows(
            fn (string $slug) => $this->scopedToLast30Days($this->allOrdersQuery($slug), $slug),
            false,
            true
        );

        $extra = $this->extraChannelRows();
        if ($extra === []) {
            return $rows;
        }

        // Same enrichment the main rows get: INV, label type, cost / ship, site profit %.
        foreach (['attachInvToOrderRows', 'attachShippingMasterLabelToOrderRows', 'attachCostShipDetailsToRows', 'attachSkuSiteProfitPctToRows'] as $step) {
            try {
                $extra = $this->{$step}($extra);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $rows = array_merge($rows, $extra);
        usort($rows, static fn (array $a, array $b): int => strcmp((string) ($b['order_date'] ?? ''), (string) ($a['order_date'] ?? '')));

        return array_values($rows);
    }

    /**
     * Rows for the channels that keep their sales outside the Marketplace Manager order tables.
     * Same date window as every other channel on this page.
     *
     * @return list<array<string, mixed>>
     */
    protected function extraChannelRows(): array
    {
        [$from, $to] = $this->resolveOrderDateRange();
        $bounds = $this->californiaSqlBounds($from, $to);

        $rows = [];
        foreach (['depop' => 'depop_sales_data', 'vinted' => 'vinted_sales_data'] as $slug => $table) {
            try {
                $rows = array_merge($rows, $this->sheetSalesRows($slug, $table, $bounds));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        try {
            $rows = array_merge($rows, $this->mercariRows($bounds));
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $rows = array_merge($rows, $this->business5coreRows($bounds));
        } catch (\Throwable $e) {
            report($e);
        }

        return $rows;
    }

    /**
     * One row in the same shape collectOrderRows() produces.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function extraRow(string $slug, int|string $rowKey, array $fields): array
    {
        $meta = self::EXTRA_CHANNELS[$slug];
        $status = trim((string) ($fields['status'] ?? ''));
        $orderId = trim((string) ($fields['order_id'] ?? ''));
        $sku = trim((string) ($fields['sku'] ?? ''));
        $sourceId = (int) ($fields['source_id'] ?? 0);

        return [
            'id' => $slug.'-'.$rowKey,
            'row_id' => $sourceId,
            'channel_id' => null,
            'mm_slug' => $slug,
            'channel_label' => $meta['label'],
            'channel_short' => $meta['short'],
            'order_id' => $orderId,
            'order_id_api' => $orderId,
            'order_number' => $orderId !== '' ? $orderId : null,
            'order_date' => $this->formatOrderDate($fields['order_date'] ?? null, $fields['order_date_tz'] ?? null),
            'updated_at' => null,
            'tracking_number' => null,
            'tracking_company' => '',
            'tracking_url' => null,
            'shipment_status' => null,
            'shipment_status_detail' => null,
            'has_shipping_label' => false,
            'shopify_fulfillment_status' => null,
            'status' => $status,
            'status_label' => $status !== '' ? $status : '—',
            'sku' => $sku,
            'catalog_sku' => '',
            'display_sku' => '',
            'display_title' => trim((string) ($fields['title'] ?? '')),
            'INV' => 0,
            'label' => null,
            'quantity' => max(1, (int) ($fields['quantity'] ?? 1)),
            'amount' => is_numeric($fields['amount'] ?? null) && (float) $fields['amount'] > 0 ? round((float) $fields['amount'], 2) : null,
            'groi_pct' => null,
            'gpft_pct' => null,
            'nroi_pct' => null,
            'npft_pct' => null,
            'import_status' => '',
            'shopify_order_id' => trim((string) ($fields['shopify_order_id'] ?? '')),
            'is_cancelled' => (bool) ($fields['cancelled'] ?? false),
            'ch_orders_link' => null,
            'orders_url' => null,
            'show_id' => $sourceId,
            'order_url' => null,
        ];
    }

    /**
     * Depop / Vinted sales sheet (uploaded CSV or synced from the Shopify orders the Fluf app creates).
     *
     * @param  array<string, mixed>  $bounds
     * @return list<array<string, mixed>>
     */
    protected function sheetSalesRows(string $slug, string $table, array $bounds): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $hasNumber = Schema::hasColumn($table, 'shopify_order_number');
        $hasShopifyId = Schema::hasColumn($table, 'shopify_order_id');
        $prefix = strtoupper(substr($slug, 0, 3));
        $out = [];

        $query = DB::table($table)
            ->whereNotNull('sale_date')
            ->whereBetween('sale_date', [$bounds['from_date'], $bounds['to_date']])
            ->orderByDesc('sale_date')
            ->orderByDesc('id');

        foreach ($query->get() as $r) {
            $qty = max(1, (int) ($r->quantity ?? 1));
            $total = (float) ($r->total ?? 0);
            $unit = (float) ($r->item_price ?? 0);
            $number = $hasNumber ? trim((string) ($r->shopify_order_number ?? '')) : '';

            $out[] = $this->extraRow($slug, (int) $r->id, [
                'source_id' => (int) $r->id,
                'order_id' => $number !== '' ? $number : $prefix.'-'.$r->id,
                'order_date' => (string) $r->sale_date,
                'status' => 'Sold',
                'sku' => (string) ($r->sku_code ?? ''),
                'title' => (string) ($r->description ?? ''),
                'quantity' => $qty,
                'amount' => $total > 0 ? $total : $unit * $qty,
                'shopify_order_id' => $hasShopifyId ? (string) ($r->shopify_order_id ?? '') : '',
            ]);
        }

        return $out;
    }

    /**
     * Mercari sales (mercari_daily_data). The sheet has no SKU column, so profit % stays blank.
     *
     * @param  array<string, mixed>  $bounds
     * @return list<array<string, mixed>>
     */
    protected function mercariRows(array $bounds): array
    {
        if (! Schema::hasTable('mercari_daily_data')) {
            return [];
        }

        $out = [];
        $query = DB::table('mercari_daily_data')
            ->whereNotNull('sold_date')
            ->where('sold_date', '>=', $bounds['from_dt'])
            ->where('sold_date', '<=', $bounds['to_dt'])
            ->orderByDesc('sold_date')
            ->orderByDesc('id');

        foreach ($query->get() as $r) {
            $status = trim((string) ($r->order_status ?? ''));
            $out[] = $this->extraRow('mercari', (int) $r->id, [
                'source_id' => (int) $r->id,
                'order_id' => trim((string) ($r->item_id ?? '')) ?: 'MC-'.$r->id,
                'order_date' => (string) $r->sold_date,
                'status' => $status,
                'title' => (string) ($r->item_title ?? ''),
                'quantity' => 1,
                'amount' => $r->item_price ?? null,
                'cancelled' => $r->canceled_date !== null || stripos($status, 'cancel') !== false,
            ]);
        }

        return $out;
    }

    /**
     * Business5Core orders from business5core.com (b5c_b2b_orders): one row per order line.
     *
     * @param  array<string, mixed>  $bounds
     * @return list<array<string, mixed>>
     */
    protected function business5coreRows(array $bounds): array
    {
        if (! Schema::hasTable('b5c_b2b_orders')) {
            return [];
        }

        $out = [];
        $orders = B5cB2bOrder::query()
            ->whereNotNull('ordered_at')
            ->where('ordered_at', '>=', $bounds['from_dt'])
            ->where('ordered_at', '<=', $bounds['to_dt'])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->get();

        foreach ($orders as $order) {
            $status = trim((string) ($order->status ?? ''));
            $common = [
                'source_id' => (int) $order->id,
                'order_id' => $order->channelOrderNumber(),
                'order_date' => $order->ordered_at,
                'status' => $status,
                'cancelled' => stripos($status, 'cancel') !== false || stripos($status, 'refund') !== false,
                'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
            ];

            $lines = $order->displayLines();
            if ($lines === []) {
                $out[] = $this->extraRow('business5core', $order->id, $common + ['amount' => $order->total]);

                continue;
            }

            foreach ($lines as $i => $line) {
                $qty = max(1, (int) $line['qty']);
                $lineAmount = (float) $line['price'] > 0 ? (float) $line['price'] * $qty : null;
                if ($lineAmount === null && count($lines) === 1) {
                    $lineAmount = (float) ($order->total ?? 0);
                }
                $out[] = $this->extraRow('business5core', $order->id.'-'.$i, $common + [
                    'sku' => (string) $line['sku'],
                    'title' => (string) $line['name'],
                    'quantity' => $qty,
                    'amount' => $lineAmount,
                ]);
            }
        }

        return $out;
    }

    /**
     * The parent builds margin / ads % per Marketplace Manager channel; add the extra channels.
     */
    protected function siteProfitContextBySlug(): array
    {
        $out = parent::siteProfitContextBySlug();

        $pctByName = [];
        $adsByName = [];
        try {
            if (Schema::hasTable('marketplace_percentages')) {
                foreach (MarketplacePercentage::query()->get(['marketplace', 'percentage']) as $row) {
                    $key = strtolower(trim((string) ($row->marketplace ?? '')));
                    if ($key !== '' && ! isset($pctByName[$key])) {
                        $pct = (float) $row->percentage;
                        $pctByName[$key] = $pct > 1 ? $pct / 100 : $pct;
                    }
                }
            }
            if (Schema::hasTable('channel_master_calculated_data')) {
                foreach (ChannelMasterCalculatedData::query()->get(['channel', 'ads_percentage', 'tacos_percentage']) as $row) {
                    $key = strtolower(trim((string) ($row->channel ?? '')));
                    if ($key !== '' && ! isset($adsByName[$key])) {
                        $ads = $row->ads_percentage !== null && $row->ads_percentage !== '' ? $row->ads_percentage : $row->tacos_percentage;
                        $adsByName[$key] = is_numeric($ads) ? (float) $ads : 0.0;
                    }
                }
            }
        } catch (\Throwable) {
            // keep the defaults below
        }

        foreach (self::EXTRA_CHANNELS as $slug => $meta) {
            $margin = 0.80;
            $ads = 0.0;
            foreach (array_merge([$slug, $meta['label']], $meta['names']) as $name) {
                $key = strtolower(trim($name));
                if ($margin === 0.80 && isset($pctByName[$key]) && $pctByName[$key] > 0) {
                    $margin = $pctByName[$key];
                }
                if ($ads === 0.0 && isset($adsByName[$key])) {
                    $ads = $adsByName[$key];
                }
            }
            $out[$slug] = [
                'margin' => $margin,
                'ads' => $ads,
                'ship_key' => 'ship',
                'is_temu' => false,
                'is_temu2' => false,
                'no_ads' => false,
            ];
        }

        return $out;
    }

    /**
     * Cancelled / refunded / voided / lost orders for a marketplace.
     */
    protected function lossOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            // Newegg: 4 = Voided
            'newegg' => $base->where(function (Builder $q) {
                $q->whereIn('status', ['4', 4])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%CANCEL%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%VOID%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%REFUND%']);
            }),
            'temu', 'temu2' => $base->where(function (Builder $q) {
                foreach (['parent_order_status_text', 'order_status_text'] as $col) {
                    foreach (['%CANCEL%', '%REFUND%', '%VOID%', '%LOST%'] as $needle) {
                        $q->orWhereRaw("UPPER(TRIM(COALESCE({$col}, ''))) LIKE ?", [$needle]);
                    }
                }
            }),
            'doba' => $this->applyLossLikeFilter($base, 'order_status'),
            'aliexpress', 'alibaba' => $this->applyLossLikeFilter($base, 'status', ['%CLOSE%']),
            default => $this->applyLossLikeFilter($base, 'status'),
        };
    }

    /**
     * @param  list<string>  $extraNeedles
     */
    protected function applyLossLikeFilter(Builder $query, string $column, array $extraNeedles = []): Builder
    {
        $needles = array_merge(['%CANCEL%', '%REFUND%', '%VOID%', '%LOST%'], $extraNeedles);

        return $query->where(function (Builder $q) use ($column, $needles) {
            foreach ($needles as $i => $needle) {
                $sql = "UPPER(TRIM(COALESCE({$column}, ''))) LIKE ?";
                if ($i === 0) {
                    $q->whereRaw($sql, [$needle]);
                } else {
                    $q->orWhereRaw($sql, [$needle]);
                }
            }
        });
    }

    /**
     * Apply date_from / date_to only when the request sends them.
     * Unlike SOF, empty dates mean all historical rows.
     */
    protected function maybeScopeByRequestDates(?Builder $query, string $slug): ?Builder
    {
        if ($query === null) {
            return null;
        }

        $fromRaw = trim((string) request()->input('date_from', ''));
        $toRaw = trim((string) request()->input('date_to', ''));
        if ($fromRaw === '' && $toRaw === '') {
            return $query;
        }

        $tz = self::SOF_TIMEZONE;
        $from = $fromRaw !== ''
            ? $this->parseCaliforniaDateInput($fromRaw, now($tz)->subDays(30)->startOfDay())
            : now($tz)->subYears(20)->startOfDay();
        $to = $toRaw !== ''
            ? $this->parseCaliforniaDateInput($toRaw, now($tz)->endOfDay(), true)
            : now($tz)->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return $this->applyOrderDateRangeFilter($query, $from, $to, $slug);
    }

    /**
     * INV from Shopify plus DIL% = (shopify L30 / INV) × 100.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachInvToOrderRows(array $rows): array
    {
        $rows = parent::attachInvToOrderRows($rows);
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
            $shopify = $sku !== '' ? $shopifyBySku->get($sku) : null;
            $inv = (int) ($row['INV'] ?? 0);
            // Same as /amazon-tabulator-view: OV L30 = shopify_skus.quantity
            $l30 = $shopify ? (int) ($shopify->quantity ?? 0) : 0;
            $row['l30'] = $l30;
            $row['dil'] = $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0.0;
        }
        unset($row);

        return $rows;
    }
}
