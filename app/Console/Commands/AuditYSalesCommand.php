<?php

namespace App\Console\Commands;

use App\Services\ReverbDaySales;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * READ-ONLY. For each channel, shows whether a $0 Y Sales is real (no orders yesterday,
 * newer orders exist) or a data gap (orders stop before yesterday / sync is stale).
 *
 *   php artisan channels:audit-y-sales
 *   php artisan channels:audit-y-sales --date=2026-10-16
 *   php artisan channels:audit-y-sales --only=walmart,faire
 */
class AuditYSalesCommand extends Command
{
    protected $signature = 'channels:audit-y-sales
                            {--date= : Pacific calendar day to audit (default: yesterday)}
                            {--only= : Comma separated channel keys}';

    protected $description = 'Read-only: tell real "no sales yesterday" apart from "orders not recorded" per channel.';

    private const PT = 'America/Los_Angeles';

    /**
     * tz = how the date column is stored: pacific (app wall clock), utc, shanghai, date (no time).
     *
     * @return array<string, array<string, mixed>>
     */
    private function sources(): array
    {
        return [
            'walmart' => ['label' => 'Walmart', 'table' => 'walmart_daily_data', 'col' => 'order_date', 'tz' => 'pacific',
                'sum' => 'unit_price * quantity',
                'where' => fn (Builder $q) => $q->where('fulfillment_option', 'DELIVERY')->where('status', '!=', 'Cancelled')],
            'doba' => ['label' => 'Doba', 'table' => 'doba_daily_data', 'col' => 'order_time', 'tz' => 'pacific',
                'sum' => 'item_price * quantity'],
            'bestbuy' => ['label' => 'Best Buy USA', 'table' => 'mirakl_daily_data', 'col' => 'order_created_at', 'tz' => 'utc',
                'sum' => 'unit_price * quantity',
                'where' => fn (Builder $q) => $q->where('channel_name', 'Best Buy USA')->where('status', '!=', 'CLOSED')],
            'macys' => ['label' => "Macy's", 'table' => 'mirakl_daily_data', 'col' => 'order_created_at', 'tz' => 'utc',
                'sum' => 'unit_price * quantity',
                'where' => fn (Builder $q) => $q->where('channel_name', "Macy's, Inc.")->whereNotIn('status', ['CLOSED', 'CHANNEL_SPECIFIC'])],
            'newegg' => ['label' => 'Newegg', 'table' => 'newegg_orders as o', 'col' => 'o.order_date', 'tz' => 'pacific',
                'sum' => 'i.unit_price * i.ordered_qty',
                'join' => ['newegg_order_items as i', 'o.order_number', '=', 'i.order_number'],
                'where' => fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('o.order_status')->orWhere('o.order_status', '!=', 4))],
            'shopify_b2c' => ['label' => 'Shopify B2C', 'table' => 'shopify_b2c_daily_data', 'col' => 'order_date', 'tz' => 'pacific',
                'sum' => 'price * quantity',
                'where' => fn (Builder $q) => $q->where('financial_status', '!=', 'refunded')],
            'shopify_b2b' => ['label' => 'Shopify B2B', 'table' => 'shopify_b2b_daily_data', 'col' => 'order_date', 'tz' => 'pacific',
                'sum' => 'price * quantity',
                'where' => fn (Builder $q) => $q->whereNotIn('financial_status', ['refunded', 'cancelled', 'canceled'])],
            'shopify_wholesale' => ['label' => 'Shopify Wholesale', 'table' => 'shopify_raw_orders', 'col' => 'order_date', 'tz' => 'date',
                'sum' => 'price * quantity',
                'where' => fn (Builder $q) => $q->where('source_name', 'shopify_draft_order')],
            'tiktok' => ['label' => 'TikTok', 'table' => 'tiktok_orders', 'col' => 'order_created_at', 'tz' => 'utc',
                'sum' => 'COALESCE(sale_price,0) * quantity'],
            'tiktok2' => ['label' => 'TikTok 2', 'table' => 'tiktok2_orders', 'col' => 'order_created_at', 'tz' => 'utc',
                'sum' => 'COALESCE(sale_price,0) * quantity'],
            'shein' => ['label' => 'Shein', 'table' => 'shein_daily_data', 'col' => 'order_processed_on', 'tz' => 'shanghai',
                'sum' => 'COALESCE(product_price,0) * GREATEST(COALESCE(quantity,0),1)',
                'where' => fn (Builder $q) => $q->whereRaw("LOWER(COALESCE(order_status,'')) NOT REGEXP 'refund|return|cancel|closed|exchange'")],
            'mercari' => ['label' => 'Mercari (all)', 'table' => 'mercari_daily_data', 'col' => 'sold_date', 'tz' => 'pacific',
                'sum' => 'item_price',
                'where' => fn (Builder $q) => $q->whereNull('canceled_date')
                    ->whereRaw("LOWER(COALESCE(order_status,'')) NOT LIKE '%cancel%'")],
            'aliexpress' => ['label' => 'AliExpress', 'table' => 'aliexpress_order_metrics', 'col' => 'order_date', 'tz' => 'pacific',
                'sum' => 'amount',
                'where' => fn (Builder $q) => $q->whereRaw("LOWER(COALESCE(status,'')) NOT REGEXP 'refund|return|cancel|closed'")],
            'faire' => ['label' => 'Faire', 'table' => 'faire_order_metrics', 'col' => 'order_date', 'tz' => 'pacific',
                'sum' => 'amount * quantity',
                'where' => fn (Builder $q) => $q->where('quantity', '>', 0)],
            'topdawg' => ['label' => 'TopDawg', 'table' => 'topdawg_order_metrics', 'col' => 'order_date', 'tz' => 'date',
                'sum' => 'amount'],
            'pls' => ['label' => 'PLS', 'table' => 'pls_sales', 'col' => 'order_date', 'tz' => 'pacific',
                'sum' => 'total_amount'],
            'wayfair' => ['label' => 'Wayfair', 'table' => 'wayfair_daily_data', 'col' => 'po_date', 'tz' => 'date',
                'sum' => 'unit_price * quantity',
                'where' => fn (Builder $q) => $q->where('quantity', '>', 0)],
            'fb' => ['label' => 'FB Marketplace', 'table' => 'facebook_marketplace_sales', 'col' => 'order_date', 'tz' => 'date',
                'sum' => 'sold_price * qty_sold'],
            'depop' => ['label' => 'Depop', 'table' => 'depop_sales_data', 'col' => 'sale_date', 'tz' => 'date',
                'sum' => 'item_price * GREATEST(COALESCE(NULLIF(quantity,0),1),1)'],
            'vinted' => ['label' => 'Vinted', 'table' => 'vinted_sales_data', 'col' => 'sale_date', 'tz' => 'date',
                'sum' => 'item_price * GREATEST(COALESCE(NULLIF(quantity,0),1),1)'],
        ];
    }

    public function handle(): int
    {
        $day = $this->option('date')
            ? Carbon::parse($this->option('date'), self::PT)->startOfDay()
            : Carbon::yesterday(self::PT);
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        $this->info('Auditing Y Sales for Pacific day '.$day->toDateString().' (read-only)');

        $rows = [];
        foreach ($this->sources() as $key => $cfg) {
            if ($only && ! in_array($key, $only, true)) {
                continue;
            }
            try {
                $rows[] = $this->auditSource($cfg, $day);
            } catch (\Throwable $e) {
                $rows[] = [$cfg['label'], '-', '-', '-', '-', '-', 'ERROR: '.mb_substr($e->getMessage(), 0, 60)];
            }
        }

        if (! $only || in_array('reverb', $only, true)) {
            try {
                $sum = app(ReverbDaySales::class)->sumForDate($day->toDateString());
                $rows[] = ['Reverb', '-', number_format($sum, 2), '-', '-', '-', $sum > 0 ? 'HAS SALES' : 'ZERO (check reverb_daily_data / reverb_order_metrics sync)'];
            } catch (\Throwable $e) {
                $rows[] = ['Reverb', '-', '-', '-', '-', '-', 'ERROR: '.mb_substr($e->getMessage(), 0, 60)];
            }
        }

        $this->table(
            ['Channel', 'Yday rows', 'Yday $', 'Newest order (PT)', 'Orders 7d', 'Active days /14', 'Verdict'],
            $rows
        );

        $this->line('REAL NO SALES  = newer orders exist, none on that day.');
        $this->line('NOT RECORDED   = newest order is OLDER than that day (sync stale / missing).');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return list<string>
     */
    private function auditSource(array $cfg, Carbon $day): array
    {
        $table = (string) $cfg['table'];
        $bare = trim(explode(' as ', $table)[0]);
        if (! Schema::hasTable($bare)) {
            return [$cfg['label'], '-', '-', '-', '-', '-', 'TABLE MISSING ('.$bare.')'];
        }

        $col = (string) $cfg['col'];
        $tz = (string) $cfg['tz'];
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $base = function () use ($cfg, $table) {
            $q = DB::table($table);
            if (! empty($cfg['join'])) {
                $q->join(...$cfg['join']);
            }
            if (! empty($cfg['where'])) {
                ($cfg['where'])($q);
            }

            return $q;
        };

        $between = function (Builder $q, Carbon $from, Carbon $to) use ($col, $tz) {
            if ($tz === 'date') {
                return $q->whereDate($col, '>=', $from->toDateString())->whereDate($col, '<=', $to->toDateString());
            }
            $zone = ['utc' => 'UTC', 'shanghai' => 'Asia/Shanghai', 'pacific' => self::PT][$tz];

            return $q->where($col, '>=', $from->copy()->timezone($zone)->format('Y-m-d H:i:s'))
                ->where($col, '<=', $to->copy()->timezone($zone)->format('Y-m-d H:i:s'));
        };

        $ydayRows = (int) $between($base(), $start, $end)->count();
        $ydaySales = (float) $between($base(), $start, $end)->selectRaw('COALESCE(SUM('.$cfg['sum'].'),0) as v')->value('v');
        $orders7d = (int) $between($base(), $day->copy()->subDays(6)->startOfDay(), $end)->count();
        $activeDays = (int) $between($base(), $day->copy()->subDays(13)->startOfDay(), $end)
            ->selectRaw('COUNT(DISTINCT DATE('.$col.')) as d')->value('d');

        $newestRaw = $base()->max($col);
        $newestPt = null;
        if ($newestRaw) {
            $zone = ['utc' => 'UTC', 'shanghai' => 'Asia/Shanghai', 'pacific' => self::PT, 'date' => self::PT][$tz];
            $newestPt = $tz === 'date'
                ? Carbon::parse($newestRaw)->startOfDay()
                : Carbon::parse($newestRaw, $zone)->timezone(self::PT);
        }

        if ($ydayRows > 0) {
            $verdict = $ydaySales > 0 ? 'HAS SALES' : 'ROWS EXIST BUT $0 (price/qty empty?)';
        } elseif ($newestPt === null) {
            $verdict = 'NO DATA AT ALL';
        } elseif ($newestPt->lt($start)) {
            $verdict = 'NOT RECORDED (newest order is '.$newestPt->diffInDays($start).'d before that day)';
        } elseif ($newestPt->gt($end)) {
            $verdict = $activeDays <= 3 && $orders7d > 0
                ? 'REAL NO SALES (low-volume channel)'
                : 'REAL NO SALES? (normally has orders - verify sync gap)';
        } else {
            $verdict = 'UNKNOWN';
        }

        return [
            $cfg['label'],
            (string) $ydayRows,
            number_format($ydaySales, 2),
            $newestPt ? $newestPt->format('Y-m-d H:i') : '-',
            (string) $orders7d,
            (string) $activeDays,
            $verdict,
        ];
    }
}
