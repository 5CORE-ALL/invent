<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdvanceForecastMonth extends Command
{
    protected $signature = 'forecast:advance-month';

    protected $description = 'On the 1st, snapshot the closed Shopify month and roll the forecast month window forward';

    public function handle(): int
    {
        $now = Carbon::now('America/Los_Angeles')->startOfMonth();
        $closed = $now->copy()->subMonth();
        $windowStart = $now->copy()->subMonths(11);

        $this->info('Forecast month window '.$windowStart->format('M Y').' → '.$now->format('M Y').' (closed '.$closed->format('M Y').').');

        $saved = $this->snapshotShopifyMonths($windowStart, $now->copy()->addMonth());
        $this->forgetForecastMonthCache($now, $closed);

        $this->info('Saved '.$saved.' sku-month rows. Forecast month cache cleared.');

        return 0;
    }

    private function snapshotShopifyMonths(Carbon $from, Carbon $until): int
    {
        if (! Schema::hasTable('shopify_raw_orders') || ! Schema::hasTable('sku_monthly_orders')) {
            $this->warn('shopify_raw_orders or sku_monthly_orders is missing; skipped snapshot.');

            return 0;
        }

        $rows = DB::table('shopify_raw_orders')
            ->selectRaw('UPPER(TRIM(sku)) as sku, YEAR(order_date) as y, MONTH(order_date) as m, SUM(quantity) as qty')
            ->where('order_date', '>=', $from->toDateString())
            ->where('order_date', '<', $until->toDateString())
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupByRaw('UPPER(TRIM(sku)), YEAR(order_date), MONTH(order_date)')
            ->get();

        $needsId = $this->needsExplicitId('sku_monthly_orders');
        $nextId = $needsId ? ((int) DB::table('sku_monthly_orders')->max('id') + 1) : null;
        $saved = 0;

        foreach ($rows as $row) {
            $sku = (string) ($row->sku ?? '');
            if ($sku === '' || strlen($sku) > 100) {
                continue;
            }

            $keys = [
                'sku' => $sku,
                'year' => (int) $row->y,
                'month' => (int) $row->m,
            ];
            $values = [
                'order_count' => max(0, (int) $row->qty),
                'updated_at' => now(),
            ];
            $updated = DB::table('sku_monthly_orders')->where($keys)->update($values);
            if ($updated === 0 && ! DB::table('sku_monthly_orders')->where($keys)->exists()) {
                $insert = array_merge($keys, $values, ['created_at' => now()]);
                if ($needsId) {
                    $insert['id'] = $nextId++;
                }
                DB::table('sku_monthly_orders')->insert($insert);
            }
            $saved++;
        }

        return $saved;
    }

    private function forgetForecastMonthCache(Carbon $current, Carbon $closed): void
    {
        $bases = [
            'fa_shopify_raw_min_month',
            'fa_shopify_raw_months',
            'fa_sku_monthly_orders',
            'fa_inv_available_months',
            'fa_movement_analysis',
            'fa_shopify_skus',
        ];

        foreach ([$current->format('Y-m'), $closed->format('Y-m')] as $ym) {
            foreach ($bases as $base) {
                Cache::forget($base);
                Cache::forget($base.':'.$ym);
            }
        }
    }

    private function needsExplicitId(string $table): bool
    {
        if (! Schema::hasColumn($table, 'id')) {
            return false;
        }

        try {
            $col = collect(DB::select('SHOW COLUMNS FROM '.$table.' WHERE Field = ?', ['id']))->first();
            $extra = strtolower((string) ($col->Extra ?? ''));

            return ! str_contains($extra, 'auto_increment');
        } catch (\Throwable $e) {
            return true;
        }
    }
}
