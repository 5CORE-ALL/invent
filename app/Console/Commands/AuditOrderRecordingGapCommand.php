<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * READ-ONLY. For one Pacific day, compares the table each sales page reads with the
 * marketplace-manager / raw order table that the sync job writes, so a day that
 * exists in one table but not in the other shows up as "MISSING IN SALES TABLE".
 *
 *   php artisan channels:audit-recording-gap --date=2026-10-16
 *
 * Marketplaces with only ONE order table cannot be cross-checked from the database;
 * for those, the only proof of real orders is the marketplace itself (seller portal / API).
 */
class AuditOrderRecordingGapCommand extends Command
{
    protected $signature = 'channels:audit-recording-gap
                            {--date=2026-10-16 : Pacific calendar day to compare}';

    protected $description = 'Read-only: find days that one order table has but the sales-page table does not.';

    private const PT = 'America/Los_Angeles';

    /** Each side: [table, date column, sum expression, optional join, optional where]. */
    private function pairs(): array
    {
        return [
            'Newegg' => [
                'sales' => ['newegg_orders as o', 'o.order_date', 'i.unit_price * i.ordered_qty',
                    ['newegg_order_items as i', 'o.order_number', '=', 'i.order_number'],
                    fn ($q) => $q->where(fn ($w) => $w->whereNull('o.order_status')->orWhere('o.order_status', '!=', 4))],
                'other' => ['newegg_order_metrics', 'order_date', 'amount'],
            ],
            'Shopify B2B' => [
                'sales' => ['shopify_b2b_daily_data', 'order_date', 'price * quantity', null,
                    fn ($q) => $q->whereNotIn('financial_status', ['refunded', 'cancelled', 'canceled'])],
                'other' => ['b5c_b2b_orders', 'ordered_at', 'total'],
            ],
            'AliExpress' => [
                'sales' => ['aliexpress_daily_data', 'order_date', 'COALESCE(product_total, supply_price, order_amount, 0)'],
                'other' => ['aliexpress_order_metrics', 'order_date', 'amount'],
            ],
            'TopDawg' => [
                'sales' => ['topdawg_order_metrics', 'order_date', 'amount'],
                'other' => null,
            ],
            'Faire' => [
                'sales' => ['faire_order_metrics', 'order_date', 'amount * quantity'],
                'other' => null,
            ],
            'TikTok 2' => [
                'sales' => ['tiktok2_orders', 'order_created_at', 'COALESCE(sale_price,0) * quantity', null, null, 'utc'],
                'other' => null,
            ],
            'PLS' => [
                'sales' => ['pls_sales', 'order_date', 'total_amount'],
                'other' => null,
            ],
            'Shopify Wholesale' => [
                'sales' => ['shopify_raw_orders', 'order_date', 'price * quantity', null,
                    fn ($q) => $q->where('source_name', 'shopify_draft_order'), 'date'],
                'other' => null,
            ],
        ];
    }

    public function handle(): int
    {
        $day = Carbon::parse($this->option('date'), self::PT)->startOfDay();
        $this->info('Order recording gap for Pacific day '.$day->toDateString().' (read-only)');

        $out = [];
        foreach ($this->pairs() as $label => $pair) {
            try {
                $s = $this->measure($pair['sales'], $day);
                $o = $pair['other'] ? $this->measure($pair['other'], $day) : null;
            } catch (\Throwable $e) {
                $out[] = [$label, '-', '-', '-', '-', 'ERROR: '.mb_substr($e->getMessage(), 0, 70)];
                continue;
            }

            if ($s === null) {
                $verdict = 'SALES TABLE MISSING';
            } elseif ($o !== null && $o[0] > $s[0]) {
                $verdict = 'MISSING IN SALES TABLE ('.($o[0] - $s[0]).' rows, $'.number_format($o[1] - $s[1], 2).')';
            } elseif ($o !== null && $o[0] === 0 && $s[0] === 0) {
                $verdict = 'BOTH EMPTY - sync not receiving orders OR real no sales';
            } elseif ($o === null && $s[0] === 0) {
                $verdict = 'ONE TABLE ONLY, EMPTY - verify in seller portal';
            } else {
                $verdict = 'OK / has rows';
            }

            $out[] = [
                $label,
                $s ? $s[0] : '-', $s ? number_format($s[1], 2) : '-',
                $o ? $o[0] : 'n/a', $o ? number_format($o[1], 2) : 'n/a',
                $verdict,
            ];
        }

        $this->table(['Marketplace', 'Sales tbl rows', 'Sales tbl $', 'Other tbl rows', 'Other tbl $', 'Verdict'], $out);

        return self::SUCCESS;
    }

    /** @return array{0:int,1:float}|null */
    private function measure(array $src, Carbon $day): ?array
    {
        [$table, $col, $sum, $join, $where] = array_pad($src, 5, null);
        $tz = $src[5] ?? 'pacific';
        $bare = trim(explode(' as ', $table)[0]);
        if (! Schema::hasTable($bare)) {
            return null;
        }

        $q = DB::table($table);
        if ($join) {
            $q->join(...$join);
        }
        if ($where) {
            $where($q);
        }

        if ($tz === 'date') {
            $q->whereDate($col, $day->toDateString());
        } else {
            $zone = $tz === 'utc' ? 'UTC' : self::PT;
            $q->where($col, '>=', $day->copy()->startOfDay()->timezone($zone)->format('Y-m-d H:i:s'))
                ->where($col, '<=', $day->copy()->endOfDay()->timezone($zone)->format('Y-m-d H:i:s'));
        }

        $rows = (int) (clone $q)->count();
        $total = (float) (clone $q)->selectRaw('COALESCE(SUM('.$sum.'),0) as v')->value('v');

        return [$rows, $total];
    }
}
