<?php

namespace App\Console\Commands;

use App\Http\Controllers\Sales\AmazonSalesController;
use App\Models\AmazonOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Compares amazon_orders.total_amount vs amazon_order_items.price sums
 * using Pacific window and status rules (optionally identical to /amazon/daily-sales).
 */
class CheckAmazonOrdersTables extends Command
{
    protected $signature = 'amazon:check-order-tables
        {--days=29 : Rolling calendar days (used when not matching daily-sales page)}
        {--all-time : No date filter (whole table)}
        {--match-daily-sales-page : Use same 29-day window as /amazon/daily-sales (through yesterday PT)}';

    protected $description = 'Compare amazon_orders vs order_items; use --match-daily-sales-page to match the sales page badge vs Seller Central';

    public function handle(): int
    {
        $allTime = (bool) $this->option('all-time');
        $matchPage = (bool) $this->option('match-daily-sales-page');
        $days = max(1, (int) $this->option('days'));

        $start = null;
        $endDate = null;

        $this->info(str_repeat('=', 72));
        $this->info('Amazon orders / order_items table check');
        $this->info(str_repeat('=', 72));

        if ($allTime) {
            $this->line('Scope: ALL rows (no date filter)');
        } elseif ($matchPage) {
            $yesterdayPacific = Carbon::yesterday('America/Los_Angeles');
            $endDate = $yesterdayPacific->copy()->endOfDay();
            $start = $yesterdayPacific->copy()->subDays(AmazonSalesController::DAILY_SALES_WINDOW_DAYS - 1)->startOfDay();
            $this->line(sprintf(
                'Scope: **SAME as /amazon/daily-sales badge** — %d inclusive days (Pacific): %s → %s (through yesterday only)',
                AmazonSalesController::DAILY_SALES_WINDOW_DAYS,
                $start->format('Y-m-d H:i:s T'),
                $endDate->format('Y-m-d H:i:s T')
            ));
        } else {
            $todayPacific = Carbon::now('America/Los_Angeles');
            $endDate = $todayPacific->copy()->endOfDay();
            $start = $todayPacific->copy()->subDays($days - 1)->startOfDay();
            $this->line(sprintf(
                'Scope: last %d days (Pacific), through **end of today**: %s → %s',
                $days,
                $start->format('Y-m-d H:i:s T'),
                $endDate->format('Y-m-d H:i:s T')
            ));
            $this->comment('Tip: Seller Central often uses a slightly different range; use --match-daily-sales-page to match your app badge.');
        }
        $this->newLine();

        $applyActiveOrders = function ($q) {
            $q->where(function ($w) {
                $w->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            });
        };

        $ordersBase = function () use ($allTime, $start, $endDate, $applyActiveOrders) {
            $q = DB::table('amazon_orders as o');
            if (!$allTime) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endDate);
            }
            $applyActiveOrders($q);

            return $q;
        };

        $joinedBase = function () use ($allTime, $start, $endDate, $applyActiveOrders) {
            $q = DB::table('amazon_orders as o')
                ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id');
            if (!$allTime) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endDate);
            }
            $applyActiveOrders($q);

            return $q;
        };

        $sumOrderTotals = (float) $ordersBase()->sum('o.total_amount');
        $sumLinePrices = (float) $joinedBase()->sum('i.price');

        $effectiveExpr = AmazonOrder::effectiveOrderTotalSql('o');
        $sumEffectiveBadge = (float) ($ordersBase()
            ->selectRaw("SUM({$effectiveExpr}) as revenue")
            ->value('revenue') ?? 0);

        $orderCountActive = (int) $ordersBase()->count(DB::raw('DISTINCT o.id'));
        $itemRowCount = (int) $joinedBase()->count('i.id');

        $canceledInWindow = DB::table('amazon_orders as o')
            ->when(!$allTime, function ($q) use ($start, $endDate) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endDate);
            })
            ->where('o.status', 'Canceled')
            ->count();

        $ordersWithoutItems = (int) DB::table('amazon_orders as o')
            ->when(!$allTime, function ($q) use ($start, $endDate) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endDate);
            })
            ->where(function ($w) {
                $w->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('amazon_order_items as i')
                    ->whereColumn('i.amazon_order_id', 'o.id');
            })
            ->count();

        $sumOrderTotalsNoItems = (float) DB::table('amazon_orders as o')
            ->when(!$allTime, function ($q) use ($start, $endDate) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endDate);
            })
            ->where(function ($w) {
                $w->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('amazon_order_items as i')
                    ->whereColumn('i.amazon_order_id', 'o.id');
            })
            ->sum('o.total_amount');

        $zeroEffectiveCount = (int) DB::table('amazon_orders as o')
            ->when(!$allTime, function ($q) use ($start, $endDate) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endDate);
            })
            ->where(function ($w) {
                $w->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->whereRaw("({$effectiveExpr}) <= 0")
            ->count();

        $pendingZeroTotal = (int) DB::table('amazon_orders as o')
            ->when(!$allTime, function ($q) use ($start, $endDate) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endDate);
            })
            ->where(function ($w) {
                $w->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->where('o.status', 'Pending')
            ->where(function ($w) {
                $w->whereNull('o.total_amount')->orWhere('o.total_amount', '<=', 0);
            })
            ->count();

        $diff = $sumLinePrices - $sumOrderTotals;

        $rows = [
            ['Metric', 'Value'],
            ['SUM(per-order effective total) = app daily-sales badge', $this->money($sumEffectiveBadge)],
            ['SUM(amazon_orders.total_amount) active orders', $this->money($sumOrderTotals)],
            ['SUM(amazon_order_items.price) all line rows', $this->money($sumLinePrices)],
            ['Difference (lines − order totals)', $this->money($diff)],
            ['Active order count (non-canceled)', number_format($orderCountActive)],
            ['Item row count', number_format($itemRowCount)],
            ['Canceled orders in window', number_format($canceledInWindow)],
            ['Active orders with NO item rows', number_format($ordersWithoutItems)],
            ['SUM(total_amount) for itemless orders', $this->money($sumOrderTotalsNoItems)],
            ['Active orders with effective revenue ≤ $0', number_format($zeroEffectiveCount)],
            ['Pending + total_amount ≤ 0 (often undercount vs SC until sync)', number_format($pendingZeroTotal)],
        ];

        $this->table($rows[0], array_slice($rows, 1));

        $this->newLine();
        $this->comment('Seller Central “sales” can still differ: they may include today, tax/shipping rules, B2B, or another marketplace. Compare only after matching **date range** and **metric** (ordered vs shipped).');
        $this->comment('If Pending/zero-effective count is high: run `php artisan app:fetch-amazon-orders --incremental-only --with-items` (or full sync with --with-items).');

        return self::SUCCESS;
    }

    private function money(float $n): string
    {
        return '$' . number_format($n, 2);
    }
}
