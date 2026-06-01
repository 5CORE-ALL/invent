<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only: maps amazon_daily_syncs + amazon_orders by Pacific calendar day
 * so you can see which dates need sync or have bad data before running fetch.
 */
class DiagnoseAmazonSyncGaps extends Command
{
    protected $signature = 'amazon:diagnose-sync-gaps
        {--days=45 : Pacific calendar days to inspect (ends today)}
        {--issues-only : Only print dates that need attention}
        {--limit-problems=30 : Max problem lines in the summary list}';

    protected $description = 'Show per-day sync status vs order data (Pacific) — find missing/failed syncs and orders without items';

    public function handle(): int
    {
        $days = max(1, min(366, (int) $this->option('days')));
        $issuesOnly = (bool) $this->option('issues-only');
        $problemLimit = max(1, (int) $this->option('limit-problems'));

        $todayPt = Carbon::now('America/Los_Angeles');
        $windowStart = $todayPt->copy()->subDays($days - 1)->startOfDay();
        $windowEnd = $todayPt->copy()->endOfDay();
        $startStr = $windowStart->format('Y-m-d');
        $endStr = $todayPt->format('Y-m-d');

        $this->info(str_repeat('=', 76));
        $this->info('Amazon sync / orders gap report (America/Los_Angeles)');
        $this->line("Window: {$startStr} → {$endStr} ({$days} day(s), through end of today PT)");
        $this->line('Note: /amazon/daily-sales uses ~32 days ending **yesterday** PT — align mentally when comparing.');
        $this->newLine();

        $syncRows = DB::table('amazon_daily_syncs')
            ->where('sync_date', '>=', $startStr)
            ->where('sync_date', '<=', $endStr)
            ->orderBy('sync_date')
            ->get()
            ->keyBy(fn ($r) => (string) $r->sync_date);

        $orders = DB::table('amazon_orders')
            ->where('order_date', '>=', $windowStart)
            ->where('order_date', '<=', $windowEnd)
            ->select('id', 'order_date', 'status')
            ->get();

        $activeByDay = [];
        $canceledByDay = [];
        foreach ($orders as $o) {
            $dk = Carbon::parse($o->order_date)->timezone('America/Los_Angeles')->format('Y-m-d');
            if (($o->status ?? '') === 'Canceled') {
                $canceledByDay[$dk] = ($canceledByDay[$dk] ?? 0) + 1;
            } else {
                $activeByDay[$dk] = ($activeByDay[$dk] ?? 0) + 1;
            }
        }

        $itemlessRows = DB::table('amazon_orders as o')
            ->where('o.order_date', '>=', $windowStart)
            ->where('o.order_date', '<=', $windowEnd)
            ->where(function ($w) {
                $w->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('amazon_order_items as i')
                    ->whereColumn('i.amazon_order_id', 'o.id');
            })
            ->select('o.order_date')
            ->get();

        $itemlessByDay = [];
        foreach ($itemlessRows as $row) {
            $dk = Carbon::parse($row->order_date)->timezone('America/Los_Angeles')->format('Y-m-d');
            $itemlessByDay[$dk] = ($itemlessByDay[$dk] ?? 0) + 1;
        }

        $table = [];
        $problems = [];

        for ($i = 0; $i < $days; $i++) {
            $d = $todayPt->copy()->subDays($i)->format('Y-m-d');
            $sync = $syncRows->get($d);

            $syncStatus = $sync ? $sync->status : '— no row —';
            $syncOrders = $sync ? (int) $sync->orders_fetched : 0;
            $syncItems = $sync ? (int) ($sync->items_fetched ?? 0) : 0;
            $act = $activeByDay[$d] ?? 0;
            $can = $canceledByDay[$d] ?? 0;
            $il = $itemlessByDay[$d] ?? 0;

            $flags = [];
            if (!$sync) {
                $flags[] = 'no_sync_row';
            } elseif (in_array($sync->status, ['pending', 'failed', 'in_progress'], true)) {
                $flags[] = 'sync_' . $sync->status;
            }
            if ($il > 0) {
                $flags[] = 'itemless_orders';
            }

            $issue = $flags !== [];
            if ($issuesOnly && !$issue) {
                continue;
            }

            $row = [
                $d,
                $syncStatus,
                $syncOrders,
                $syncItems,
                $act,
                $can,
                $il,
                $issue ? implode(', ', $flags) : '',
            ];
            $table[] = $row;

            if ($issue) {
                $problems[] = [
                    'date' => $d,
                    'flags' => implode(', ', $flags),
                    'sync' => $syncStatus,
                    'active_orders' => $act,
                    'itemless' => $il,
                    'err' => $sync && $sync->error_message ? substr((string) $sync->error_message, 0, 80) : '',
                ];
            }
        }

        $this->table(
            ['Date PT', 'sync status', 'sync ord', 'sync items', 'active ord', 'canceled', 'itemless', 'flags'],
            $table
        );

        $this->newLine();
        $this->info('Problem summary (action hints)');
        if ($problems === []) {
            $this->line('No flags in this window (or use smaller --days / drop --issues-only).');
        } else {
            foreach (array_slice($problems, 0, $problemLimit) as $p) {
                $line = "  {$p['date']}: {$p['flags']} | active={$p['active_orders']} itemless={$p['itemless']}";
                if ($p['err'] !== '') {
                    $line .= ' | last err: ' . $p['err'];
                }
                $this->line($line);
            }
            if (count($problems) > $problemLimit) {
                $this->comment('  … +' . (count($problems) - $problemLimit) . ' more (raise --limit-problems)');
            }
        }

        $this->newLine();
        $this->comment('Hints: sync pending/failed → run app:fetch-amazon-orders for those dates or --auto-sync.');
        $this->comment('itemless_orders + active ord → run app:fetch-amazon-orders --fetch-missing-items (often with --with-items on sync).');

        $global = [
            ['amazon_orders min order_date', DB::table('amazon_orders')->min('order_date')],
            ['amazon_orders max order_date', DB::table('amazon_orders')->max('order_date')],
            ['amazon_daily_syncs rows (total)', DB::table('amazon_daily_syncs')->count()],
            ['sync: pending', DB::table('amazon_daily_syncs')->where('status', 'pending')->count()],
            ['sync: failed', DB::table('amazon_daily_syncs')->where('status', 'failed')->count()],
            ['sync: in_progress', DB::table('amazon_daily_syncs')->where('status', 'in_progress')->count()],
        ];
        $this->newLine();
        $this->table(['Global', 'Value'], $global);

        return self::SUCCESS;
    }
}
