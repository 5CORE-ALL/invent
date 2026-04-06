<?php

namespace App\Console\Commands;

use App\Models\AmazonOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Compares amazon_orders.total_amount vs amazon_order_items.price sums
 * using the same Pacific window and status rules as AmazonSalesController.
 */
class CheckAmazonOrdersTables extends Command
{
    protected $signature = 'amazon:check-order-tables
        {--days=29 : Rolling calendar days ending today (Pacific)}
        {--all-time : No date filter (whole table)}';

    protected $description = 'Compare amazon_orders vs amazon_order_items totals (matches daily sales page logic)';

    public function handle(): int
    {
        $allTime = (bool) $this->option('all-time');
        $days = max(1, (int) $this->option('days'));

        $todayPacific = Carbon::now('America/Los_Angeles');
        $endToday = $todayPacific->copy()->endOfDay();
        $start = $allTime
            ? null
            : $todayPacific->copy()->subDays($days - 1)->startOfDay();

        $this->info(str_repeat('=', 72));
        $this->info('Amazon orders / order_items table check');
        $this->info(str_repeat('=', 72));
        if ($allTime) {
            $this->line('Scope: ALL rows (no date filter)');
        } else {
            $this->line(sprintf(
                'Scope: last %d days (Pacific): %s → %s',
                $days,
                $start->format('Y-m-d H:i:s T'),
                $endToday->format('Y-m-d H:i:s T')
            ));
        }
        $this->newLine();

        $applyActiveOrders = function ($q) {
            $q->where(function ($w) {
                $w->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            });
        };

        $ordersBase = function () use ($allTime, $start, $endToday, $applyActiveOrders) {
            $q = DB::table('amazon_orders as o');
            if (!$allTime) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endToday);
            }
            $applyActiveOrders($q);

            return $q;
        };

        $joinedBase = function () use ($allTime, $start, $endToday, $applyActiveOrders) {
            $q = DB::table('amazon_orders as o')
                ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id');
            if (!$allTime) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endToday);
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
            ->when(!$allTime, function ($q) use ($start, $endToday) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endToday);
            })
            ->where('o.status', 'Canceled')
            ->count();

        $ordersWithoutItems = (int) DB::table('amazon_orders as o')
            ->when(!$allTime, function ($q) use ($start, $endToday) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endToday);
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
            ->when(!$allTime, function ($q) use ($start, $endToday) {
                $q->where('o.order_date', '>=', $start)
                    ->where('o.order_date', '<=', $endToday);
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

        $diff = $sumLinePrices - $sumOrderTotals;

        $rows = [
            ['Metric', 'Value'],
            ['SUM(amazon_orders.total_amount) active orders', $this->money($sumOrderTotals)],
            ['SUM(per-order effective total) = daily sales badge logic', $this->money($sumEffectiveBadge)],
            ['SUM(amazon_order_items.price) joined to same orders', $this->money($sumLinePrices)],
            ['Difference (lines − orders)', $this->money($diff)],
            ['Active order count', number_format($orderCountActive)],
            ['Item row count (joined)', number_format($itemRowCount)],
            ['Canceled orders in window', number_format($canceledInWindow)],
            ['Active orders with ZERO item rows', number_format($ordersWithoutItems)],
            ['SUM(total_amount) for those itemless orders', $this->money($sumOrderTotalsNoItems)],
        ];

        $this->table($rows[0], array_slice($rows, 1));

        $this->newLine();
        $this->comment('Daily sales badge = SUM(per order): use total_amount if > 0, else sum of that order\'s line items (AmazonOrder::effectiveOrderTotalSql).');
        $this->comment('This command ends the window at **today** (Pacific); /amazon/daily-sales uses **29 days through yesterday** — match --days and end date when comparing.');
        $this->comment('Grid/API rows use line sale_amount; filtered "Total Sales" sums distinct order effective totals. Large line−order gap → tax/shipping on order only, or sync issues.');

        return self::SUCCESS;
    }

    private function money(float $n): string
    {
        return '$' . number_format($n, 2);
    }
}
