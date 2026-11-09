<?php

namespace App\Services\Support;

use App\Models\AmazonOrder;
use App\Models\ShopifySku;
use App\Services\TemuShopifySalesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Our own marketplace order data (the API-synced tables the Order Fulfillment page reads),
 * rolled up per SKU for Today / Yesterday / L30 (Pacific days). Shopify is left out on purpose:
 * it no longer holds every sale.
 *
 * Result keys are ShopifySku::compactSkuForLookup(sku).
 */
class SkuSalesByWindowService
{
    private const TZ = 'America/Los_Angeles';

    /**
     * Marketplace-manager order tables: [channel label => [table, how order_date is stored, amount meaning]].
     * "utc"  = order_date stored in UTC, "naive" = stored in the app timezone.
     * "line" = amount is the line total, "unit" = amount is a unit price (x quantity).
     */
    private const METRIC_TABLES = [
        'eBay' => ['ebay1_order_metrics', 'utc', 'line'],
        'eBay 2' => ['ebay2_order_metrics', 'utc', 'line'],
        'eBay 3' => ['ebay3_order_metrics', 'utc', 'line'],
        'Newegg' => ['newegg_order_metrics', 'naive', 'line'],
        'TopDawg' => ['topdawg_order_metrics', 'naive', 'line'],
        'Shein' => ['shein_order_metrics', 'naive', 'unit'],
        'AliExpress' => ['aliexpress_order_metrics', 'naive', 'unit'],
        'Alibaba' => ['alibaba_order_metrics', 'naive', 'unit'],
        'Faire' => ['faire_order_metrics', 'naive', 'unit'],
        'Reverb' => ['reverb_order_metrics', 'naive', 'unit'],
    ];

    /** @var array<string, array{today: float, yesterday: float, l30: float}> */
    private array $skus = [];

    /** @var array<string, array{today: float, yesterday: float, l30: float}> */
    private array $channels = [];

    /** @var list<string> */
    private array $errors = [];

    private string $today;

    private string $yesterday;

    private string $l30From;

    private Carbon $start;

    private Carbon $end;

    private string $storageTz;

    /**
     * @return array{skus: array<string, array{today: float, yesterday: float, l30: float}>, channels: array<string, array{today: float, yesterday: float, l30: float}>, errors: list<string>, as_of: string, today: string, yesterday: string, l30_from: string}
     */
    public function build(bool $fresh = false): array
    {
        $key = 'sku_sales_by_window_v1_'.Carbon::now(self::TZ)->toDateString();
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 600, fn () => $this->compute());
    }

    private function compute(): array
    {
        @set_time_limit(180);
        $now = Carbon::now(self::TZ);
        $this->today = $now->toDateString();
        $this->yesterday = $now->copy()->subDay()->toDateString();
        // Same window as OV L30 (today minus 30 days, through today).
        $this->start = $now->copy()->subDays(30)->startOfDay();
        $this->end = $now->copy()->endOfDay();
        $this->l30From = $this->start->toDateString();
        $this->storageTz = (string) (config('app.timezone') ?: self::TZ);
        $this->skus = [];
        $this->channels = [];
        $this->errors = [];

        $this->run('Amazon', fn () => $this->amazon());
        foreach (self::METRIC_TABLES as $label => [$table, $tzKind, $amountKind]) {
            $this->run($label, fn () => $this->metricTable($label, $table, $tzKind, $amountKind));
        }
        $this->run('TikTok', fn () => $this->tiktok('TikTok', 'tiktok_orders'));
        $this->run('TikTok 2', fn () => $this->tiktok('TikTok 2', 'tiktok2_orders'));
        $this->run('Doba', fn () => $this->doba());
        $this->run('Wayfair', fn () => $this->wayfair());
        $this->run('Best Buy', fn () => $this->mirakl('Best Buy', 'Best Buy USA'));
        $this->run("Macy's", fn () => $this->mirakl("Macy's", "Macy's, Inc."));
        $this->run('Purchasing Power', fn () => $this->purchasingPower());
        $this->run('FB Marketplace', fn () => $this->facebook());
        $this->run('Temu', fn () => $this->temu('Temu', TemuShopifySalesService::getOrdersTableRows($this->start, $this->end)));
        $this->run('Temu 2', fn () => $this->temu('Temu 2', TemuShopifySalesService::getTemu2OrdersTableRows($this->start, $this->end)));
        $this->run('Temu 3', fn () => $this->temu('Temu 3', TemuShopifySalesService::getTemu3OrdersTableRows($this->start, $this->end)));

        return [
            'skus' => $this->skus,
            'channels' => $this->channels,
            'errors' => $this->errors,
            'as_of' => $now->toDateTimeString(),
            'today' => $this->today,
            'yesterday' => $this->yesterday,
            'l30_from' => $this->l30From,
        ];
    }

    private function run(string $label, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->errors[] = $label;
            Log::warning('SkuSalesByWindowService '.$label.' failed: '.$e->getMessage());
        }
    }

    /** One order line into Today / Yesterday / L30 buckets (Pacific date). */
    private function add(string $channel, string $sku, string $ymd, float $sales): void
    {
        if ($sales <= 0 || $ymd < $this->l30From || $ymd > $this->today) {
            return;
        }
        $sku = preg_replace('/\s+\+\d+$/', '', trim($sku)) ?? $sku;
        $key = ShopifySku::compactSkuForLookup($sku);
        if ($key === '' || str_contains(strtoupper($sku), '__ORDER__') || str_contains(strtoupper($sku), 'PARENT')) {
            return;
        }
        $this->bump($this->skus, $key, $ymd, $sales);
        $this->bump($this->channels, $channel, $ymd, $sales);
    }

    /** @param  array<string, array{today: float, yesterday: float, l30: float}>  $bucket */
    private function bump(array &$bucket, string $k, string $ymd, float $sales): void
    {
        if (! isset($bucket[$k])) {
            $bucket[$k] = ['today' => 0.0, 'yesterday' => 0.0, 'l30' => 0.0];
        }
        $bucket[$k]['l30'] += $sales;
        if ($ymd === $this->today) {
            $bucket[$k]['today'] += $sales;
        } elseif ($ymd === $this->yesterday) {
            $bucket[$k]['yesterday'] += $sales;
        }
    }

    private function pacificDate(mixed $value, string $stored): string
    {
        $s = (string) $value;
        if ($s === '') {
            return '';
        }
        // Plain dates (no time) are already a calendar day.
        if (strlen($s) <= 10) {
            return $s;
        }

        return Carbon::parse($s, $stored === 'utc' ? 'UTC' : $this->storageTz)->setTimezone(self::TZ)->toDateString();
    }

    /** @return array{0: string, 1: string} */
    private function range(string $stored): array
    {
        if ($stored === 'utc') {
            return [
                $this->start->copy()->utc()->format('Y-m-d H:i:s'),
                $this->end->copy()->utc()->format('Y-m-d H:i:s'),
            ];
        }

        return [
            $this->start->copy()->timezone($this->storageTz)->format('Y-m-d H:i:s'),
            $this->end->copy()->timezone($this->storageTz)->format('Y-m-d H:i:s'),
        ];
    }

    private function notCancelled($q, string $table, string $column = 'status'): void
    {
        if (Schema::hasColumn($table, $column)) {
            $q->whereRaw("LOWER(COALESCE({$column}, '')) NOT LIKE '%cancel%'")
                ->whereRaw("LOWER(COALESCE({$column}, '')) NOT LIKE '%refund%'");
        }
    }

    private function amazon(): void
    {
        if (! Schema::hasTable('amazon_orders') || ! Schema::hasTable('amazon_order_items')) {
            return;
        }
        [$from, $to] = $this->range('utc');
        $qtyTimesPrice = AmazonOrder::salesTotalMode() === AmazonOrder::SALES_TOTAL_MODE_QTY_TIMES_PRICE;
        $rows = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
            })
            ->whereNotNull('i.sku')
            ->select(['o.order_date', 'i.sku', 'i.quantity', 'i.price'])
            ->cursor();
        foreach ($rows as $r) {
            $qty = (int) $r->quantity;
            $line = $qtyTimesPrice ? $qty * (float) $r->price : (float) $r->price;
            $this->add('Amazon', (string) $r->sku, $this->pacificDate($r->order_date, 'utc'), $line);
        }
    }

    private function metricTable(string $label, string $table, string $tzKind, string $amountKind): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'amount') || ! Schema::hasColumn($table, 'order_date')) {
            return;
        }
        [$from, $to] = $this->range($tzKind);
        $q = DB::table($table)
            ->whereBetween('order_date', [$from, $to])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('sku', '!=', '__order__')
            ->whereNotNull('amount');
        $this->notCancelled($q, $table);
        $cols = ['order_date', 'sku', 'amount'];
        if (Schema::hasColumn($table, 'quantity')) {
            $cols[] = 'quantity';
        }
        foreach ($q->select($cols)->cursor() as $r) {
            $qty = max(1, (int) ($r->quantity ?? 1));
            $amount = (float) $r->amount;
            $line = $amountKind === 'unit' ? $amount * $qty : $amount;
            $this->add($label, (string) $r->sku, $this->pacificDate($r->order_date, $tzKind), $line);
        }
    }

    private function tiktok(string $label, string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        [$from, $to] = $this->range('naive');
        $q = DB::table($table)
            ->whereBetween('order_created_at', [$from, $to])
            ->whereNotNull('seller_sku')
            ->where(function ($w) {
                $w->whereNull('line_item_id')->orWhere('line_item_id', '!=', '__order__');
            });
        $this->notCancelled($q, $table, 'order_status');
        foreach ($q->select(['order_created_at', 'seller_sku', 'quantity', 'sale_price'])->cursor() as $r) {
            $qty = max(1, (int) $r->quantity);
            $this->add($label, (string) $r->seller_sku, $this->pacificDate($r->order_created_at, 'naive'), (float) $r->sale_price * $qty);
        }
    }

    private function doba(): void
    {
        if (! Schema::hasTable('doba_daily_data')) {
            return;
        }
        [$from, $to] = $this->range('naive');
        $q = DB::table('doba_daily_data')
            ->whereBetween('order_time', [$from, $to])
            ->whereNotNull('sku')
            ->where('sku', '!=', '');
        $this->notCancelled($q, 'doba_daily_data', 'order_status');
        foreach ($q->select(['order_time', 'sku', 'total_price'])->cursor() as $r) {
            $this->add('Doba', (string) $r->sku, $this->pacificDate($r->order_time, 'naive'), (float) $r->total_price);
        }
    }

    private function wayfair(): void
    {
        if (! Schema::hasTable('wayfair_daily_data')) {
            return;
        }
        [$from, $to] = $this->range('naive');
        $rows = DB::table('wayfair_daily_data')
            ->whereBetween('po_date', [substr($from, 0, 10), substr($to, 0, 10)])
            ->where('sku', 'not like', '%Parent%')
            ->where('quantity', '>', 0)
            ->select(['po_date', 'sku', 'quantity', 'unit_price'])
            ->cursor();
        foreach ($rows as $r) {
            $this->add('Wayfair', (string) $r->sku, $this->pacificDate($r->po_date, 'naive'), (float) $r->unit_price * (int) $r->quantity);
        }
    }

    private function mirakl(string $label, string $channelName): void
    {
        if (! Schema::hasTable('mirakl_daily_data')) {
            return;
        }
        [$from, $to] = $this->range('utc');
        $rows = DB::table('mirakl_daily_data')
            ->where('channel_name', $channelName)
            ->where('status', '!=', 'CLOSED')
            ->whereBetween('order_created_at', [$from, $to])
            ->whereNotNull('sku')
            ->where('quantity', '>', 0)
            ->select(['order_created_at', 'sku', 'quantity', 'unit_price'])
            ->cursor();
        foreach ($rows as $r) {
            $this->add($label, (string) $r->sku, $this->pacificDate($r->order_created_at, 'utc'), (float) $r->unit_price * (int) $r->quantity);
        }
    }

    private function purchasingPower(): void
    {
        if (! Schema::hasTable('purchasing_power_sales')) {
            return;
        }
        [$from, $to] = $this->range('naive');
        $q = DB::table('purchasing_power_sales')
            ->whereBetween('date_created', [$from, $to])
            ->whereNotNull('offer_sku');
        $this->notCancelled($q, 'purchasing_power_sales');
        foreach ($q->select(['date_created', 'offer_sku', 'quantity', 'unit_price', 'amount'])->cursor() as $r) {
            $qty = max(1, (int) $r->quantity);
            $line = (float) $r->unit_price > 0 ? (float) $r->unit_price * $qty : (float) $r->amount;
            $this->add('Purchasing Power', (string) $r->offer_sku, $this->pacificDate($r->date_created, 'naive'), $line);
        }
    }

    private function facebook(): void
    {
        if (! Schema::hasTable('facebook_marketplace_sales')) {
            return;
        }
        $rows = DB::table('facebook_marketplace_sales')
            ->whereBetween('order_date', [$this->start->toDateString(), $this->today])
            ->whereNotNull('sku')
            ->select(['order_date', 'sku', 'qty_sold', 'sold_price'])
            ->cursor();
        foreach ($rows as $r) {
            $qty = (int) $r->qty_sold;
            if ($qty <= 0) {
                continue;
            }
            $this->add('FB Marketplace', (string) $r->sku, $this->pacificDate($r->order_date, 'naive'), (float) $r->sold_price * $qty);
        }
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function temu(string $label, array $rows): void
    {
        foreach ($rows as $r) {
            $sku = trim((string) ($r['contribution_sku'] ?? ''));
            $qty = (int) ($r['quantity_purchased'] ?? 0);
            if ($sku === '' || $qty <= 0) {
                continue;
            }
            $line = (float) ($r['line_sales'] ?? 0);
            if ($line <= 0) {
                $line = (float) ($r['base_price_total'] ?? 0) * $qty;
            }
            $ymd = substr((string) ($r['created_at'] ?? ''), 0, 10);
            if ($ymd === '') {
                continue;
            }
            $this->add($label, $sku, $ymd, $line);
        }
    }
}
