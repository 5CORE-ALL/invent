<?php

namespace App\Http\Controllers\Channels;

use App\Console\Commands\SyncTikTokChargebacksCommand;
use App\Http\Controllers\Controller;
use App\Models\VeeqoChargeback;
use App\Services\Veeqo\ChargebackOsizeSync;
use App\Services\Veeqo\OrderSkuLookup;
use App\Services\Veeqo\PlatformChargebackCsvImport;
use App\Services\Veeqo\TikTokChargebackSync;
use App\Services\Veeqo\VeeqoChargebackCsvImport;
use App\Services\Veeqo\VeeqoOrderSkuResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class VeeqoChargebackController extends Controller
{
    private const COLUMNS = [
        'transaction_id', 'source', 'transaction_type', 'description', 'reason', 'transaction_status', 'posted_at',
        'amazon_order_id', 'order_number', 'settlement_id', 'financial_event_group_id', 'marketplace_name',
        'total_amount', 'currency', 'direction', 'item_types', 'carrier', 'service', 'payment_status',
        'payment_method', 'customer_charge', 'carrier_charge',
    ];

    private const SEARCHABLE = ['order_number', 'settlement_id', 'transaction_id', 'item_types', 'reason', 'service'];

    public const CHARGEBACK_TYPES = [
        'LabmanLabelChargeBack', 'Chargeback Debit - Outbound', 'Chargeback Credit - Outbound',
        'TikTok Shipping Fee Adjustment', 'TikTok Shipping Fee Compensation',
        'Shopify Label Price Adjustment', 'Temu Label Purchase Adjustment',
    ];

    public function index()
    {
        return view('channels.veeqo-chargebacks');
    }

    public function data(Request $request)
    {
        if (! Schema::hasTable('veeqo_chargebacks')) {
            return response()->json(['last_page' => 1, 'data' => [], 'total' => 0]);
        }

        $size = (int) $request->input('size', 100);
        $size = $size > 0 ? min($size, 5000) : 100;
        $query = $this->filtered($request);

        foreach ((array) $request->input('sort', []) as $sort) {
            $field = (string) ($sort['field'] ?? '');
            if (in_array($field, self::COLUMNS, true)) {
                $query->orderBy($field, strtolower((string) ($sort['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc');
            }
        }
        $query->orderByDesc('posted_at')->orderByDesc('id');

        $page = $query->paginate($size, ['*'], 'page', max(1, (int) $request->input('page', 1)));
        $shipments = $this->shipmentsFor(collect($page->items())->pluck('order_number')->filter()->unique()->values()->all());

        $rows = collect($page->items())->map(function (VeeqoChargeback $row) use ($shipments) {
            $out = ['id' => $row->id] + $row->only(self::COLUMNS);
            $out['posted_at'] = $row->posted_at?->format('Y-m-d H:i');
            foreach ((array) ($row->components ?? []) as $type => $amount) {
                $out['c_'.$type] = $amount;
            }

            return $out + ($shipments[$row->order_number] ?? []);
        });

        $noSku = $rows->filter(fn ($r) => ($r['v_skus'] ?? '') === '' && ! empty($r['order_number']))->pluck('order_number')->unique()->values()->all();
        if ($noSku !== []) {
            $skus = app(OrderSkuLookup::class)->forOrders($noSku);
            $rows = $rows->map(function ($r) use ($skus) {
                if (($r['v_skus'] ?? '') === '' && isset($skus[$r['order_number'] ?? ''])) {
                    $r['v_skus'] = $skus[$r['order_number']];
                }

                return $r;
            });
        }

        return response()->json([
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'data' => $rows,
        ]);
    }

    public function summary(Request $request)
    {
        if (! Schema::hasTable('veeqo_chargebacks')) {
            return response()->json(['totals' => [], 'components' => [], 'filters' => [], 'sync' => []]);
        }

        $totals = $this->filtered($request)
            ->selectRaw('COUNT(*) as rows_count')
            ->selectRaw('COUNT(DISTINCT order_number) as orders')
            ->selectRaw("COALESCE(SUM(CASE WHEN source = 'veeqo_csv' THEN total_amount END), 0) as veeqo_net")
            ->selectRaw("COALESCE(SUM(CASE WHEN source = 'amazon_api' THEN total_amount END), 0) as amazon_net")
            ->selectRaw("COALESCE(SUM(CASE WHEN source IN ('tiktok_api', 'tiktok2_api') THEN total_amount END), 0) as tiktok_net")
            ->selectRaw("COALESCE(SUM(CASE WHEN source = 'shopify_csv' THEN total_amount END), 0) as shopify_net")
            ->selectRaw("COALESCE(SUM(CASE WHEN source IN ('temu1_csv', 'temu2_csv', 'temu3_csv') THEN total_amount END), 0) as temu_net")
            ->selectRaw("SUM(CASE WHEN direction = 'debit' THEN 1 ELSE 0 END) as debits")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN total_amount END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN total_amount END), 0) as credit_total")
            ->selectRaw('COALESCE(SUM(total_amount), 0) as net_total')
            ->selectRaw('COALESCE(SUM(customer_charge), 0) as customer_total')
            ->selectRaw('COALESCE(SUM(carrier_charge), 0) as carrier_total')
            ->toBase()
            ->first();

        return response()->json([
            'totals' => $totals,
            'components' => $this->componentTypes(),
            'filters' => [
                'types' => $this->distinct('description'),
                'reasons' => $this->distinct('reason'),
                'statuses' => $this->distinct('transaction_status'),
            ],
            'sync' => [
                'rows' => VeeqoChargeback::query()->count(),
                'api_first' => VeeqoChargeback::query()->where('source', 'amazon_api')->min('posted_at'),
                'api_last' => VeeqoChargeback::query()->where('source', 'amazon_api')->max('posted_at'),
                'api_synced_at' => VeeqoChargeback::query()->where('source', 'amazon_api')->max('updated_at'),
                'csv_first' => VeeqoChargeback::query()->where('source', 'veeqo_csv')->min('posted_at'),
                'csv_last' => VeeqoChargeback::query()->where('source', 'veeqo_csv')->max('posted_at'),
                'csv_imported_at' => VeeqoChargeback::query()->where('source', 'veeqo_csv')->max('updated_at'),
                'tiktok_last' => VeeqoChargeback::query()->whereIn('source', ['tiktok_api', 'tiktok2_api'])->max('posted_at'),
                'tiktok_synced_at' => VeeqoChargeback::query()->whereIn('source', ['tiktok_api', 'tiktok2_api'])->max('updated_at'),
                'imports' => VeeqoChargeback::query()
                    ->whereIn('source', array_column(PlatformChargebackCsvImport::PLATFORMS, 'source'))
                    ->groupBy('source')
                    ->selectRaw('source, MIN(posted_at) as first, MAX(posted_at) as last, MAX(updated_at) as imported_at')
                    ->get(),
            ],
            'source_types' => VeeqoChargeback::query()
                ->groupBy('source', 'description')
                ->selectRaw('source, description, COUNT(*) as n')
                ->get()
                ->groupBy('source')
                ->map(fn ($rows) => $rows->pluck('n', 'description')),
        ]);
    }

    public function show(int $id)
    {
        $row = VeeqoChargeback::query()->findOrFail($id);

        return response()->json(['transaction' => $row->raw]);
    }

    public function syncTikTok()
    {
        $probe = Cache::lock(SyncTikTokChargebacksCommand::LOCK, 1);
        if (! $probe->get()) {
            return response()->json(['success' => false, 'message' => 'A TikTok sync is already running.'], 409);
        }
        $probe->release();

        $needsBackfill = collect(array_keys(TikTokChargebackSync::CHANNELS))->contains(fn ($ch) => ! TikTokChargebackSync::hasRows($ch));
        if ($needsBackfill) {
            if (! $this->runInBackground('tiktok:sync-chargebacks', 'tiktok-chargebacks.log')) {
                return response()->json(['success' => false, 'message' => 'Could not start the TikTok backfill.'], 500);
            }

            return response()->json(['success' => true, 'message' => 'Loading the last 12 months of TikTok shipping charges in the background. This takes a while; refresh the page later.']);
        }

        @set_time_limit(900);
        $exit = Artisan::call('tiktok:sync-chargebacks', ['--days' => 30]);
        $lines = array_values(array_filter(explode("\n", trim(Artisan::output())), fn ($l) => ! str_starts_with($l, 'Transaction types seen') && ! str_contains($l, ' → ')));

        return response()->json(['success' => $exit === 0, 'message' => implode("\n", $lines) ?: 'Sync finished.'], $exit === 0 ? 200 : 502);
    }

    private function runInBackground(string $command, string $logFile): bool
    {
        try {
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            if (PHP_OS_FAMILY === 'Windows') {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' '.$command, 'r'));
            } else {
                pclose(popen('nohup '.escapeshellarg($php).' '.escapeshellarg($artisan).' '.$command
                    .' >> '.escapeshellarg(storage_path('logs/'.$logFile)).' 2>&1 &', 'r'));
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Background command failed to start', ['command' => $command, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function syncNow()
    {
        $lock = Cache::lock('veeqo_chargebacks_sync_now', 900);
        if (! $lock->get()) {
            return response()->json(['success' => false, 'message' => 'A chargeback sync is already running.'], 409);
        }
        try {
            @set_time_limit(900);
            $exit = Artisan::call('veeqo:sync-chargebacks', ['--days' => 30]);
            $lines = array_values(array_filter(explode("\n", trim(Artisan::output()))));

            return response()->json(['success' => $exit === 0, 'message' => end($lines) ?: 'Sync finished.'], $exit === 0 ? 200 : 502);
        } finally {
            $lock->release();
        }
    }

    /**
     * SKU-wise chargebacks for the last 12 months. A chargeback on a multi-SKU order counts once for
     * each SKU and its amount is split evenly between them. Units sold come from sku_monthly_orders.
     */
    public function skuReport(Request $request)
    {
        return response()->json($this->skuReportData($request));
    }

    public function skuReportData(Request $request): array
    {
        if (! Schema::hasTable('veeqo_chargebacks')) {
            return ['rows' => [], 'totals' => []];
        }

        $from = now()->subMonths(12)->startOfDay();
        $params = $request->except(['from', 'to', 'search', 'filter', 'sort', 'page', 'size']);
        $chargebacks = $this->filtered($request->duplicate($params))
            ->where('posted_at', '>=', $from)
            ->get(['id', 'order_number', 'total_amount', 'carrier']);

        $skusByOrder = app(OrderSkuLookup::class)->forOrders($chargebacks->pluck('order_number')->filter()->unique()->values()->all());
        $carrierById = $this->resolveCarriers($chargebacks);
        $carriers = $this->carrierBreakdown($chargebacks, $carrierById);

        $bySku = [];
        foreach ($chargebacks as $cb) {
            $skus = isset($skusByOrder[$cb->order_number]) ? array_map('trim', explode(',', $skusByOrder[$cb->order_number])) : [];
            $skus = array_values(array_unique(array_filter($skus, fn ($s) => $s !== '')));
            if ($skus === []) {
                $skus = ['(no SKU found)'];
            }
            $share = round((float) $cb->total_amount / count($skus), 4);
            foreach ($skus as $sku) {
                $key = $this->skuKey($sku);
                $bySku[$key] ??= ['sku' => $sku, 'count' => 0, 'total' => 0.0, 'orders' => [], 'carriers' => []];
                $bySku[$key]['count']++;
                $bySku[$key]['total'] += $share;
                $carrier = $carrierById[$cb->id] ?? 'Unknown carrier';
                $orderKey = $cb->order_number ?: 'line:'.$cb->id;
                $bySku[$key]['carriers'][$carrier][$orderKey] = true;
                if ($cb->order_number) {
                    $bySku[$key]['orders'][$cb->order_number] = true;
                }
            }
        }

        $sold = $this->unitsSoldLast12Months();
        $rows = [];
        // Veeqo splits one chargeback into a line per reason, so "count" is orders charged back.
        foreach ($bySku as $key => $s) {
            $units = $sold[$key] ?? null;
            $count = max(1, count($s['orders']));
            $skuCarriers = array_map('count', $s['carriers']);
            arsort($skuCarriers);
            $rows[] = [
                'sku' => $s['sku'],
                'carriers' => $skuCarriers,
                'carrier_text' => implode(', ', array_map(fn ($name, $n) => $name.' ('.$n.')', array_keys($skuCarriers), $skuCarriers)),
                'count' => $count,
                'lines' => $s['count'],
                'total' => round($s['total'], 2),
                'average' => round($s['total'] / $count, 2),
                'sold' => $units,
                'per_unit' => $units ? round($s['total'] / $units, 2) : null,
                'pct' => $units ? round($count / $units * 100, 2) : null,
            ];
        }
        usort($rows, fn ($a, $b) => abs($b['total']) <=> abs($a['total']));
        $skuCountTotal = array_sum(array_column($rows, 'count'));
        foreach ($rows as &$row) {
            $row['pct_tc'] = $skuCountTotal ? round($row['count'] / $skuCountTotal * 100, 4) : 0;
        }
        unset($row);

        $withSales = array_filter($rows, fn ($r) => $r['sold'] !== null);
        $soldMatched = array_sum(array_column($withSales, 'sold'));
        $countMatched = array_sum(array_column($withSales, 'count'));
        $orders = $chargebacks->pluck('order_number')->filter()->unique()->count();

        return [
            'from' => $from->toDateString(),
            'to' => now()->toDateString(),
            'rows' => $rows,
            'carriers' => $carriers,
            'totals' => [
                'skus' => count($rows),
                'chargebacks' => $orders,
                'lines' => $chargebacks->count(),
                'amount' => round((float) $chargebacks->sum('total_amount'), 2),
                'average' => $orders ? round((float) $chargebacks->sum('total_amount') / $orders, 2) : 0,
                'sold' => $soldMatched,
                'pct' => $soldMatched ? round($countMatched / $soldMatched * 100, 2) : null,
            ],
        ];
    }

    /**
     * Orders charged back per carrier. Veeqo CSV rows carry the carrier; Amazon API rows borrow it from a CSV
     * chargeback on the same order, else from the Veeqo shipment.
     *
     * @param  \Illuminate\Support\Collection<int, VeeqoChargeback>  $chargebacks
     * @return list<array{carrier: string, count: int, lines: int, amount: float, pct: float}>
     */
    private function carrierBreakdown($chargebacks, array $carrierById): array
    {
        $by = [];
        foreach ($chargebacks as $c) {
            $name = $carrierById[$c->id] ?? 'Unknown carrier';
            $by[$name] ??= ['carrier' => $name, 'orders' => [], 'blank' => 0, 'lines' => 0, 'amount' => 0.0];
            $by[$name]['lines']++;
            $by[$name]['amount'] += (float) $c->total_amount;
            if ($c->order_number) {
                $by[$name]['orders'][$c->order_number] = true;
            } else {
                $by[$name]['blank']++;
            }
        }

        $total = array_sum(array_map(fn ($b) => count($b['orders']) + $b['blank'], $by));
        $totalAmount = array_sum(array_column($by, 'amount'));
        $out = [];
        foreach ($by as $b) {
            $count = count($b['orders']) + $b['blank'];
            $out[] = [
                'carrier' => $b['carrier'],
                'count' => $count,
                'lines' => $b['lines'],
                'amount' => round($b['amount'], 2),
                'pct' => $total ? round($count / $total * 100, 2) : 0,
                'value_pct' => abs($totalAmount) > 0 ? round($b['amount'] / $totalAmount * 100, 2) : 0,
            ];
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, VeeqoChargeback>  $chargebacks
     * @return array<int, string> chargeback id => carrier name
     */
    private function resolveCarriers($chargebacks): array
    {
        $needShipment = $chargebacks->filter(fn ($c) => ! $c->carrier && $c->order_number)->pluck('order_number')->unique()->values()->all();
        $byOrder = [];
        foreach (array_chunk($needShipment, 1000) as $chunk) {
            DB::table('veeqo_chargebacks')->whereIn('order_number', $chunk)->whereNotNull('carrier')
                ->get(['order_number', 'carrier'])
                ->each(function ($s) use (&$byOrder) {
                    $byOrder[$s->order_number] ??= $s->carrier;
                });
            DB::table('veeqo_billing_shipments')->whereIn('order_number', $chunk)->whereNotNull('sub_carrier_id')
                ->get(['order_number', 'sub_carrier_id'])
                ->each(function ($s) use (&$byOrder) {
                    $byOrder[$s->order_number] ??= $s->sub_carrier_id;
                });
        }

        $out = [];
        foreach ($chargebacks as $c) {
            $out[$c->id] = $this->carrierName((string) ($c->carrier ?: ($byOrder[$c->order_number] ?? '')));
        }

        return $out;
    }

    private function carrierName(string $raw): string
    {
        $key = strtolower(preg_replace('/[^a-z]/i', '', $raw));

        return match (true) {
            $key === '' => 'Unknown carrier',
            str_contains($key, 'fedex') => 'FedEx',
            str_contains($key, 'usps') => 'USPS',
            str_contains($key, 'ups') => 'UPS',
            str_contains($key, 'dhl') => 'DHL',
            str_contains($key, 'amazon') => 'Amazon Shipping',
            default => strtoupper($raw),
        };
    }

    /**
     * @return array<string, int> normalized SKU => units sold in the last 12 calendar months
     */
    private function unitsSoldLast12Months(): array
    {
        if (! Schema::hasTable('sku_monthly_orders')) {
            return [];
        }
        $start = now()->subMonths(11)->startOfMonth();
        $sold = [];
        DB::table('sku_monthly_orders')
            ->whereRaw('(year * 100 + month) >= ?', [(int) $start->format('Ym')])
            ->selectRaw('sku, SUM(order_count) as units')
            ->groupBy('sku')
            ->get()
            ->each(function ($r) use (&$sold) {
                $key = $this->skuKey((string) $r->sku);
                $sold[$key] = ($sold[$key] ?? 0) + (int) $r->units;
            });

        return $sold;
    }

    private function skuKey(string $sku): string
    {
        return strtoupper((string) preg_replace('/\s+/', ' ', trim($sku)));
    }

    public function fillOsize(ChargebackOsizeSync $sync)
    {
        $lock = Cache::lock('veeqo_chargeback_osize', 600);
        if (! $lock->get()) {
            return response()->json(['success' => false, 'message' => 'An O-Size fill is already running. Try again in a minute.'], 409);
        }
        try {
            set_time_limit(600);
            $r = $sync->run();
        } finally {
            $lock->release();
        }

        return response()->json([
            'success' => $r['failed'] === 0,
            'message' => "Shipping Master O-Size Charge: updated {$r['updated']} SKUs, {$r['unchanged']} already up to date"
                .($r['missing'] ? ", {$r['missing']} not found in Shipping Master" : '')
                .($r['failed'] ? ", {$r['failed']} failed (see log)" : '').'.',
            'result' => $r,
        ]);
    }

    public function findMissingSkus(VeeqoOrderSkuResolver $resolver)
    {
        $lock = Cache::lock('veeqo_fill_order_skus', 300);
        if (! $lock->get()) {
            return response()->json(['success' => false, 'message' => 'A Veeqo SKU lookup is already running.'], 409);
        }
        try {
            @set_time_limit(300);
            $missing = $resolver->missingChargebackOrders();
            $result = $resolver->resolve($missing, 150);
            $left = max(0, count($missing) - $result['found'] - $result['skipped']);
            $message = 'Checked '.$result['checked'].' orders in Veeqo; found SKUs for '.$result['found'].'.';
            if ($result['skipped']) {
                $message .= ' '.$result['skipped'].' were already checked recently (not in Veeqo or no SKU).';
            }
            if ($result['checked'] >= 150 && $left > 0) {
                $message .= ' More remain; click again or wait for the hourly lookup.';
            }
            if ($result['error']) {
                $message .= ' Last error: '.$result['error'];
            }

            return response()->json(['success' => true, 'message' => $message] + $result);
        } finally {
            $lock->release();
        }
    }

    public function import(Request $request, VeeqoChargebackCsvImport $import, PlatformChargebackCsvImport $platformImport)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'platform' => ['nullable', 'in:veeqo,'.implode(',', array_keys(PlatformChargebackCsvImport::PLATFORMS))],
        ]);
        $file = $request->file('file');
        $platform = (string) $request->input('platform', 'veeqo');
        @set_time_limit(600);
        $result = $platform === 'veeqo'
            ? $import->import($file->getRealPath(), $file->getClientOriginalName())
            : $platformImport->import($platform, $file->getRealPath());

        return response()->json(['success' => $result['ok']] + $result, $result['ok'] ? 200 : 422);
    }

    private function filtered(Request $request): Builder
    {
        $query = VeeqoChargeback::query();

        $from = trim((string) $request->input('from', ''));
        $to = trim((string) $request->input('to', ''));
        if ($from !== '') {
            $query->where('posted_at', '>=', $from.' 00:00:00');
        }
        if ($to !== '') {
            $query->where('posted_at', '<=', $to.' 23:59:59');
        }
        if ($request->input('type') === 'chargebacks') {
            $query->whereIn('description', self::CHARGEBACK_TYPES);
        }
        $types = array_values(array_filter(array_map('trim', explode('|', (string) $request->input('types', '')))));
        if ($types !== []) {
            $query->whereIn('description', $types);
        }
        foreach (['description' => 'type', 'direction' => 'direction', 'transaction_status' => 'status', 'source' => 'source', 'reason' => 'reason'] as $column => $param) {
            $value = trim((string) $request->input($param, ''));
            if ($value !== '' && $value !== 'chargebacks') {
                $query->where($column, $value);
            }
        }
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                foreach (self::SEARCHABLE as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }
        foreach ((array) $request->input('filter', []) as $filter) {
            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? null;
            if (in_array($field, self::COLUMNS, true) && $value !== null && $value !== '') {
                $query->where($field, 'like', '%'.$value.'%');
            }
        }

        return $query;
    }

    /**
     * Veeqo shipment details for each Amazon order (an order can have several labels).
     *
     * @param  list<string>  $orderIds
     * @return array<string, array<string, mixed>>
     */
    private function shipmentsFor(array $orderIds): array
    {
        if ($orderIds === [] || ! Schema::hasTable('veeqo_billing_shipments')) {
            return [];
        }

        $out = [];
        DB::table('veeqo_billing_shipments')
            ->whereIn('order_number', $orderIds)
            ->orderBy('shipment_created_at')
            ->get(['order_number', 'shipment_created_at', 'tracking_number', 'sub_carrier_id', 'service_name', 'label_cost', 'shipment_weight', 'weight_unit', 'skus', 'channel_name', 'tracking_status'])
            ->each(function ($s) use (&$out) {
                $key = (string) $s->order_number;
                $row = $out[$key] ?? ['v_labels' => 0, 'v_label_cost' => 0.0, 'v_tracking' => [], 'v_carrier' => [], 'v_service' => [], 'v_skus' => [], 'v_status' => []];
                $row['v_labels']++;
                $row['v_shipped_at'] ??= $s->shipment_created_at ? substr((string) $s->shipment_created_at, 0, 16) : null;
                $row['v_label_cost'] = round($row['v_label_cost'] + (float) $s->label_cost, 2);
                $row['v_weight'] ??= $s->shipment_weight !== null ? rtrim(rtrim((string) $s->shipment_weight, '0'), '.').' '.$s->weight_unit : null;
                $row['v_channel'] ??= $s->channel_name;
                foreach (['v_tracking' => $s->tracking_number, 'v_carrier' => $s->sub_carrier_id, 'v_service' => $s->service_name, 'v_skus' => $s->skus, 'v_status' => $s->tracking_status] as $k => $v) {
                    if ($v !== null && $v !== '' && ! in_array($v, $row[$k], true)) {
                        $row[$k][] = $v;
                    }
                }
                $out[$key] = $row;
            });

        foreach ($out as $key => $row) {
            foreach (['v_tracking', 'v_carrier', 'v_service', 'v_skus', 'v_status'] as $k) {
                $out[$key][$k] = implode(', ', $row[$k]);
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function componentTypes(): array
    {
        return Cache::remember('veeqo_chargeback_component_types', 600, function () {
            $types = [];
            VeeqoChargeback::query()->select(['id', 'components'])->orderByDesc('id')->limit(20000)->get()
                ->each(function ($row) use (&$types) {
                    foreach (array_keys((array) $row->components) as $type) {
                        $types[$type] = true;
                    }
                });
            $types = array_keys($types);
            sort($types);

            return $types;
        });
    }

    /**
     * @return list<string>
     */
    private function distinct(string $column): array
    {
        return DB::table('veeqo_chargebacks')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn ($v) => (string) $v)
            ->all();
    }
}
