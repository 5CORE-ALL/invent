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

    /** An order sold below this net profit % counts as a loss sell. */
    public const LOSS_NPFT_BELOW = 10.0;

    /** An order sold below this net ROI % counts as a loss sell. */
    public const LOSS_NROI_BELOW = 30.0;

    /** Cache of the /price-increase table rows (LMP, SPRICE $, INV, L30, Dil%) — filled whenever that table is built. */
    private const PI_ROWS_CACHE_KEY = 'slo_price_increase_rows_v1';

    /** Per SKU and day: orders / units / amount-weighted NROI% and NPFT% (for the sibling rows of the loss modal). */
    private const SKU_DAYS_CACHE_KEY = 'slo_sku_days_v1';

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
        // NPRFT% below 10 or NROI% below 30 = a loss sell (same limits as the NPRFT / NROI alert badges on this page).
        // Net profit % falls back to gross profit % when the net one is missing.
        $npft = is_numeric($row['npft_pct'] ?? null)
            ? (float) $row['npft_pct']
            : (is_numeric($row['gpft_pct'] ?? null) ? (float) $row['gpft_pct'] : null);
        $nroi = is_numeric($row['nroi_pct'] ?? null) ? (float) $row['nroi_pct'] : null;

        return ($npft !== null && $npft < self::LOSS_NPFT_BELOW)
            || ($nroi !== null && $nroi < self::LOSS_NROI_BELOW);
    }

    /**
     * Loss summary badges: how many orders (and which products) sold at a loss for
     * L30 / L7 / Yesterday / Today (Eastern days).
     *
     * A row is a loss sell when its NPRFT% (net, else gross, profit %) is below 10 or its NROI% is below 30.
     * "Est. loss" only adds up the money really lost (rows with a negative profit).
     * Cancelled orders are not counted: nothing was sold.
     * Cached for 3 minutes because it walks every channel; pass ?refresh=1 to rebuild.
     */
    public function lossSummary(): JsonResponse
    {
        try {
            @set_time_limit(120);

            $payload = request()->boolean('refresh')
                ? null
                : \Illuminate\Support\Facades\Cache::get('slo_loss_summary_v3');
            if (! is_array($payload)) {
                $payload = $this->buildLossSummary();
                \Illuminate\Support\Facades\Cache::put('slo_loss_summary_v3', $payload, 180);
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
            $p += ['orders' => 0, 'loss_orders' => 0, 'loss_amount' => 0.0, 'skus' => 0, 'nroi_pct' => null, 'npft_pct' => null];
        }
        unset($p);

        // Amount-weighted NROI% / NPFT% sums per period: [weighted pct sum, amount sum].
        $agg = [];

        $seen = [];
        $lossSeen = [];
        $lossSkus = [];
        $lossRows = [];
        $skuDays = [];
        $skuDaySeen = [];

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
            // Est. loss = money really lost (negative profit); a thin positive margin is a loss sell but loses no money
            $loss = ($isLoss && $pct !== null && $pct < 0 && $amount > 0) ? round($amount * $pct / 100, 2) : 0.0;
            $sku = trim((string) ($row['sku'] ?? ''));

            // Every sold SKU, per day — the sibling rows of the loss modal show their own sales from this
            if ($sku !== '') {
                $sdKey = strtoupper($sku);
                $day = &$skuDays[$sdKey][$date];
                $day ??= ['o' => 0, 'q' => 0, 'w' => 0.0, 'nr' => 0.0, 'np' => 0.0, 'l' => 0.0, 'ls' => 0];
                if (! isset($skuDaySeen[$sdKey.'|'.$date.'|'.$orderKey])) {
                    $skuDaySeen[$sdKey.'|'.$date.'|'.$orderKey] = true;
                    $day['o']++;
                }
                $day['q'] += max(1, (int) ($row['quantity'] ?? 1));
                if ($amount > 0) {
                    $day['w'] += $amount;
                    $day['nr'] += (is_numeric($row['nroi_pct'] ?? null) ? (float) $row['nroi_pct'] : 0.0) * $amount;
                    $day['np'] += ($pct ?? 0.0) * $amount;
                }
                $day['l'] += $loss;
                if ($isLoss) {
                    $day['ls']++;
                }
                unset($day);
            }

            foreach ($periods as $key => &$p) {
                if ($date < $p['from'] || $date > $p['to']) {
                    continue;
                }
                if (! isset($seen[$key][$orderKey])) {
                    $seen[$key][$orderKey] = true;
                    $p['orders']++;
                }
                if ($amount > 0) {
                    foreach (['nroi', 'npft'] as $metric) {
                        $v = $row[$metric.'_pct'] ?? null;
                        if (is_numeric($v)) {
                            $agg[$key][$metric][0] = ($agg[$key][$metric][0] ?? 0.0) + ((float) $v * $amount);
                            $agg[$key][$metric][1] = ($agg[$key][$metric][1] ?? 0.0) + $amount;
                        }
                    }
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
                    'nroi' => is_numeric($row['nroi_pct'] ?? null) ? round((float) $row['nroi_pct'], 1) : null,
                    'npft' => is_numeric($row['npft_pct'] ?? null) ? round((float) $row['npft_pct'], 1) : null,
                    'loss' => $loss,
                ];
            }
        }

        foreach ($periods as $key => &$p) {
            $p['skus'] = count($lossSkus[$key] ?? []);
            $p['loss_amount'] = round($p['loss_amount'], 2);
            foreach (['nroi', 'npft'] as $metric) {
                $pair = $agg[$key][$metric] ?? null;
                $p[$metric.'_pct'] = ($pair && $pair[1] > 0) ? round($pair[0] / $pair[1], 1) : null;
            }
        }
        unset($p);

        // Kept for the modal's sibling rows (loaded by /sales-loss-order/loss-products), not sent with the summary
        \Illuminate\Support\Facades\Cache::put(self::SKU_DAYS_CACHE_KEY, $skuDays, 900);

        return [
            'generated_at' => now($tz)->format('Y-m-d H:i'),
            'periods' => $periods,
            'rows' => $lossRows,
        ];
    }

    // ───────── Fixed / Ignore actions of the loss modal (history kept per SKU) ─────────

    protected function lossActionTablesReady(): bool
    {
        return Schema::hasTable('slo_loss_actions') && Schema::hasTable('slo_ignore_reasons');
    }

    /** @return list<string> */
    protected function ignoreReasons(): array
    {
        return DB::table('slo_ignore_reasons')->orderBy('sort')->orderBy('id')->pluck('name')->map(fn ($n) => (string) $n)->all();
    }

    /**
     * Current state of every SKU that has an action (newest history row) + the ignore reasons.
     * GET /sales-loss-order/loss-actions
     */
    public function lossActions(): JsonResponse
    {
        if (! $this->lossActionTablesReady()) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate (slo_loss_actions).', 'actions' => (object) [], 'reasons' => ['Clearance']], 503);
        }

        $latestIds = DB::table('slo_loss_actions')->selectRaw('MAX(id) as id')->groupBy('sku_key')->pluck('id');
        $actions = [];
        foreach (DB::table('slo_loss_actions')->whereIn('id', $latestIds)->get() as $row) {
            if ($row->action === 'open') {
                continue;
            }
            $actions[$row->sku_key] = [
                'action' => $row->action,
                'reason' => $row->reason,
                'user' => $row->user_name,
                'at' => (string) $row->created_at,
            ];
        }

        return response()->json(['success' => true, 'actions' => (object) $actions, 'reasons' => $this->ignoreReasons()]);
    }

    /**
     * POST /sales-loss-order/loss-actions  { sku, action: fixed|ignored|open, reason? }
     * Adds a history row; "open" puts the SKU back to no decision.
     */
    public function saveLossAction(\Illuminate\Http\Request $request): JsonResponse
    {
        if (! $this->lossActionTablesReady()) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate (slo_loss_actions).'], 503);
        }

        $sku = trim((string) $request->input('sku', ''));
        $action = strtolower(trim((string) $request->input('action', '')));
        $reason = trim((string) $request->input('reason', ''));
        if ($sku === '' || ! in_array($action, ['fixed', 'ignored', 'open'], true)) {
            return response()->json(['success' => false, 'message' => 'A SKU and an action (fixed / ignored) are required.'], 422);
        }
        if ($action === 'ignored') {
            if ($reason === '') {
                return response()->json(['success' => false, 'message' => 'Pick a reason for ignoring this SKU.'], 422);
            }
            // Only reasons from the list (case-insensitive) are accepted
            $known = collect($this->ignoreReasons())->first(fn ($r) => strcasecmp($r, $reason) === 0);
            if ($known === null) {
                return response()->json(['success' => false, 'message' => 'Unknown reason — add it first.'], 422);
            }
            $reason = $known;
        } else {
            $reason = '';
        }

        $user = $request->user();
        DB::table('slo_loss_actions')->insert([
            'sku_key' => strtoupper($sku),
            'sku' => mb_substr($sku, 0, 191),
            'action' => $action,
            'reason' => $reason !== '' ? $reason : null,
            'user_id' => $user?->id,
            'user_name' => $user ? (string) ($user->name ?: $user->email) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'sku' => strtoupper($sku),
            'state' => $action === 'open' ? null : [
                'action' => $action,
                'reason' => $reason !== '' ? $reason : null,
                'user' => $user ? (string) ($user->name ?: $user->email) : null,
                'at' => now()->toDateTimeString(),
            ],
        ]);
    }

    /** GET /sales-loss-order/loss-actions/history?sku=…  — who did what for this SKU, newest first. */
    public function lossActionHistory(\Illuminate\Http\Request $request): JsonResponse
    {
        if (! $this->lossActionTablesReady()) {
            return response()->json(['success' => true, 'history' => []]);
        }
        // With ?sku= the history of that SKU, without it the latest actions of every SKU
        $key = strtoupper(trim((string) $request->query('sku', '')));

        $q = DB::table('slo_loss_actions')->orderByDesc('id');
        if ($key !== '') {
            $q->where('sku_key', $key)->limit(100);
        } else {
            $q->limit(500);
        }
        $rows = $q->get(['sku', 'action', 'reason', 'user_name', 'created_at'])
            ->map(fn ($r) => ['sku' => $r->sku, 'action' => $r->action, 'reason' => $r->reason, 'user' => $r->user_name, 'at' => (string) $r->created_at])
            ->all();

        return response()->json(['success' => true, 'history' => $rows]);
    }

    /** POST /sales-loss-order/loss-reasons { name } — a new reason for the Ignore dropdown. */
    public function addLossReason(\Illuminate\Http\Request $request): JsonResponse
    {
        if (! $this->lossActionTablesReady()) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate (slo_loss_actions).'], 503);
        }

        $name = trim((string) preg_replace('/\s+/', ' ', (string) $request->input('name', '')));
        if ($name === '' || mb_strlen($name) > 100) {
            return response()->json(['success' => false, 'message' => 'Enter a reason (up to 100 characters).'], 422);
        }
        $existing = collect($this->ignoreReasons())->first(fn ($r) => strcasecmp($r, $name) === 0);
        if ($existing === null) {
            DB::table('slo_ignore_reasons')->insert([
                'name' => $name,
                'sort' => (int) DB::table('slo_ignore_reasons')->max('sort') + 1,
                'created_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $existing = $name;
        }

        return response()->json(['success' => true, 'reason' => $existing, 'reasons' => $this->ignoreReasons()]);
    }

    /**
     * Keep the /price-increase table rows (per SKU: parent, image, INV, L30, Dil%, LMP by channel, SPRICE $ ...) for
     * the loss modal. Called every time /cvr-master-data-json is built, so the modal shows exactly what that table shows.
     *
     * @param  list<array<string, mixed>|object>  $rows
     */
    public static function rememberPriceIncreaseRows(array $rows): void
    {
        $lmpFields = ['amazon', 'ebay', 'google', 'bestbuy', 'macy', 'reverb', 'temu', 'aliexpress', 'tiktok'];
        $num = static fn ($v): ?float => (is_numeric($v) && (float) $v > 0) ? round((float) $v, 2) : null;

        $map = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            if (! empty($row['is_parent_summary'])) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                continue;
            }
            $lmp = [];
            foreach ($lmpFields as $ch) {
                $v = $num($row[$ch.'_lmp_price'] ?? null);
                if ($v !== null) {
                    $lmp[$ch] = $v;
                }
            }
            $inv = (int) ($row['inventory'] ?? $row['inv'] ?? 0);
            $l30 = (int) ($row['overall_l30'] ?? 0);
            $ads = $row['amazon_ads'] ?? null;
            $map[strtoupper($sku)] = [
                'sku' => $sku,
                'parent' => trim((string) ($row['parent'] ?? '')),
                'image' => trim((string) ($row['image_path'] ?? $row['sku_image'] ?? '')),
                'inv' => max(0, $inv),
                'l30' => max(0, $l30),
                'dil' => is_numeric($row['dil_percent'] ?? null) ? round((float) $row['dil_percent'], 1) : ($inv > 0 ? round($l30 / $inv * 100, 1) : 0),
                'lmp' => $lmp,
                'box_sprice' => $num($row['box_sprice'] ?? null),
                'amazon_sprice' => $num($row['amazon_sprice'] ?? null),
                'amazon_price' => $num($row['amazon_price'] ?? null),
                'lp' => $num($row['amazon_lp'] ?? $row['lp'] ?? null),
                'ship' => is_numeric($row['amazon_ship'] ?? null) ? round((float) $row['amazon_ship'], 2) : 0.0,
                'ads' => is_numeric($ads) ? round((float) $ads, 2) : (is_numeric($row['amazon_ad'] ?? null) ? round((float) $row['amazon_ad'], 2) : 0.0),
                'margin' => is_numeric($row['amazon_margin'] ?? null) && (float) $row['amazon_margin'] > 0 ? (float) $row['amazon_margin'] : 0.8,
            ];
        }

        if ($map !== []) {
            \Illuminate\Support\Facades\Cache::put(self::PI_ROWS_CACHE_KEY, $map, now()->addMinutes(30));
        }
    }

    /**
     * The cached /price-increase rows; builds the table once (like /lmp-overall does) when nobody opened it lately.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function priceIncreaseRows(): array
    {
        $cached = \Illuminate\Support\Facades\Cache::get(self::PI_ROWS_CACHE_KEY);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        try {
            @ini_set('memory_limit', '1024M');
            @set_time_limit(240);
            // The build calls rememberPriceIncreaseRows() itself
            app(\App\Http\Controllers\MarketPlace\CvrMasterController::class)
                ->getCvrDataJson(\Illuminate\Http\Request::create('/cvr-master-data-json', 'GET'));
        } catch (\Throwable $e) {
            report($e);
        }

        $cached = \Illuminate\Support\Facades\Cache::get(self::PI_ROWS_CACHE_KEY);

        return is_array($cached) ? $cached : [];
    }

    /**
     * Data of the loss modal's extra columns for the given SKUs AND every sibling (same parent), taken from the
     * /price-increase table: image, INV, L30, Dil%, LMP by channel, SPRICE $ (+ what its profit chips need) and the
     * SKU's own sales per day (so the siblings show their orders / NROI% / NPRFT% too).
     *
     * GET /sales-loss-order/loss-products?skus[]=A&skus[]=B  (or skus=A,B)
     */
    public function lossProducts(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            @set_time_limit(240);

            $raw = $request->input('skus', []);
            $raw = is_array($raw) ? $raw : explode(',', (string) $raw);
            $wanted = [];
            foreach ($raw as $s) {
                $k = strtoupper(trim((string) $s));
                if ($k !== '' && $k !== '(NO SKU)') {
                    $wanted[$k] = true;
                }
            }
            if ($wanted === []) {
                return response()->json(['success' => true, 'rows' => []]);
            }

            $pi = $this->priceIncreaseRows();
            if ($pi === []) {
                return response()->json(['success' => false, 'message' => 'The /price-increase data could not be loaded.', 'rows' => []], 503);
            }

            $byParent = [];
            foreach ($pi as $k => $r) {
                if ($r['parent'] !== '') {
                    $byParent[strtoupper($r['parent'])][] = $k;
                }
            }

            $include = [];
            $requested = [];
            foreach (array_keys($wanted) as $k) {
                $r = $pi[$k] ?? null;
                if ($r === null) {
                    // SKU spelled differently in the orders (spaces / PCS vs PC)
                    $compact = str_replace(' ', '', $k);
                    foreach ($pi as $pk => $pr) {
                        if (str_replace(' ', '', $pk) === $compact) {
                            $k = $pk;
                            $r = $pr;
                            break;
                        }
                    }
                }
                if ($r === null) {
                    continue;
                }
                $include[$k] = true;
                $requested[$k] = true;
                if ($r['parent'] !== '') {
                    foreach ($byParent[strtoupper($r['parent'])] ?? [] as $sib) {
                        $include[$sib] = true;
                    }
                }
            }

            $skuDays = \Illuminate\Support\Facades\Cache::get(self::SKU_DAYS_CACHE_KEY);
            if (! is_array($skuDays)) {
                $this->buildLossSummary();
                $skuDays = \Illuminate\Support\Facades\Cache::get(self::SKU_DAYS_CACHE_KEY);
                $skuDays = is_array($skuDays) ? $skuDays : [];
            }

            $out = [];
            foreach (array_keys($include) as $k) {
                $r = $pi[$k];
                $days = [];
                foreach (($skuDays[$k] ?? []) as $date => $d) {
                    $days[$date] = [$d['o'], $d['q'], round($d['w'], 2), round($d['nr'], 2), round($d['np'], 2), round($d['l'], 2), $d['ls']];
                }
                $out[] = $r + ['requested' => isset($requested[$k]), 'days' => (object) $days];
            }

            return response()->json(['success' => true, 'rows' => $out, 'generated_at' => now()->toDateTimeString()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to load the loss products.', 'rows' => []], 500);
        }
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
            'ship_rule' => $fields['ship_rule'] ?? null,
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
                // Seller paid the label -> default ship; buyer paid / prepaid label -> no ship.
                'ship_rule' => (float) ($r->seller_shipping_fee ?? 0) > 0 ? 'seller' : 'prepaid',
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
