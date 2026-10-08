<?php

namespace App\Console\Commands;

use App\Models\ChannelMasterSummary;
use App\Models\Temu2Order;
use App\Models\Temu3Order;
use App\Models\TemuOrder;
use App\Services\Support\YesterdayMarketplaceMetricsService;
use App\Services\TemuShopifySalesService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Re-computes the closed-day Temu / Temu 2 / Temu 3 history in channel_master_daily_data with
 * the current sales-page formulas, so the Active Channel charts show one consistent series.
 *
 * Snapshot D stores the 30 Pacific days ending D-1 (same convention as the nightly job and
 * healClosedTemu2L30Snapshots), so each snapshot is recomputed from orders over that window.
 */
class RebuildTemuChannelHistory extends Command
{
    protected $signature = 'channel-master:rebuild-temu-history
        {--from= : First snapshot date YYYY-MM-DD (default: earliest day with a full order window)}
        {--to= : Last snapshot date YYYY-MM-DD (default: yesterday, Pacific; today is rewritten by channel:calculate-data)}
        {--channels=temu,temu2,temu3 : Comma list of channel keys}
        {--with-y : Also recompute Y Sales / L7 Sales per day (slow: 8 single-day order scans per snapshot)}
        {--allow-partial : Also rebuild days whose 30-day window starts before the first stored order}
        {--dry-run : Show old vs new without writing}';

    protected $description = 'Rebuild Temu / Temu 2 / Temu 3 L30 sales, GPFT%, GROI%, NPFT% history from orders with the current formulas';

    private const TZ = 'America/Los_Angeles';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $channels = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('channels')))));
        $today = Carbon::now(self::TZ)->startOfDay();
        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'), self::TZ)->startOfDay()
            : $today->copy()->subDay();
        $withY = (bool) $this->option('with-y');
        $ySvc = $withY ? app(YesterdayMarketplaceMetricsService::class) : null;

        foreach ($channels as $key) {
            if (! in_array($key, ['temu', 'temu2', 'temu3'], true)) {
                $this->warn("skip unknown channel {$key}");

                continue;
            }

            $firstOrder = $this->firstOrderDate($key);
            if ($firstOrder === null) {
                $this->warn("{$key}: no orders stored, skipped");

                continue;
            }

            $rows = ChannelMasterSummary::query()
                ->where('channel', $key)
                ->whereDate('snapshot_date', '<=', $to->toDateString())
                ->when($this->option('from'), fn ($q) => $q->whereDate('snapshot_date', '>=', Carbon::parse((string) $this->option('from'))->toDateString()))
                ->orderBy('snapshot_date')
                ->get();

            $this->info("== {$key}: first stored order {$firstOrder->toDateString()}, {$rows->count()} snapshot(s)");
            $done = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $snapshot = Carbon::parse($row->snapshot_date, self::TZ)->startOfDay();
                $asOf = $snapshot->copy()->subDay();
                $start = $asOf->copy()->subDays(29)->startOfDay();
                $end = $asOf->copy()->endOfDay();

                if (! $this->option('allow-partial') && $start->lt($firstOrder)) {
                    $skipped++;

                    continue;
                }

                $m = $this->metrics($key, $start, $end);
                $sales = round((float) ($m['sales'] ?? 0), 2);
                if ($sales <= 0) {
                    $skipped++;

                    continue;
                }

                $pft = (float) ($m['pft'] ?? 0);
                $cogs = (float) ($m['cogs'] ?? 0);
                // /temu2-tabulator divides GPFT$ by Temu Full Price Sales; Temu 1 / Temu 3 by sales.
                $gpftBase = (float) ($m['full_sales'] ?? 0);
                if ($gpftBase <= 0) {
                    $gpftBase = $sales;
                }
                $gpft = round(($pft / $gpftBase) * 100, 2);
                $groi = $cogs > 0 ? round(($pft / $cogs) * 100, 2) : 0.0;

                $sd = $row->summaryArray();
                $old = [
                    'l30' => $sd['l30_sales'] ?? null,
                    'g' => $sd['gprofit_percent'] ?? null,
                    'n' => $sd['npft_percent'] ?? null,
                ];

                $adSpend = $key === 'temu3' ? 0.0 : (float) ($sd['total_ad_spend'] ?? 0);
                $tcos = $key === 'temu3' || $sales <= 0 ? 0.0 : round(($adSpend / $sales) * 100, 2);
                $l7 = (float) ($sd['l7_sales'] ?? 0);
                $pSales = round(($l7 / 7) * 30, 2);

                $sd['l30_sales'] = $sales;
                $sd['l30_orders'] = (float) ($m['orders'] ?? 0);
                $sd['total_quantity'] = (float) ($m['qty'] ?? 0);
                $sd['gprofit_percent'] = $gpft;
                $sd['groi_percent'] = $groi;
                $sd['tcos_percent'] = $tcos;
                $sd['npft_percent'] = round($gpft - $tcos, 2);
                $sd['nroi_percent'] = round($groi - $tcos, 2);
                $sd['p_npft_percent'] = $pSales > 0 ? round($gpft - ($adSpend / $pSales) * 100, 2) : null;
                $sd['p_npft_amt'] = $pSales > 0 ? round($pSales * ($gpft / 100) - $adSpend, 2) : null;
                $sd['cogs'] = round($cogs, 2);
                $sd['total_pft'] = round($pft, 2);

                if ($withY && $ySvc !== null) {
                    $ySales = $ySvc->salesForPacificDate($key, $asOf->toDateString());
                    if ($ySales !== null && ($ySales > 0 || (float) ($sd['y_sales'] ?? 0) <= 0)) {
                        $sd['y_sales'] = $ySales;
                    }
                    $l7Sales = $ySvc->salesForPacificWindow($key, $asOf->toDateString(), 7);
                    if ($l7Sales !== null && ($l7Sales > 0 || (float) ($sd['l7_sales'] ?? 0) <= 0)) {
                        $sd['l7_sales'] = $l7Sales;
                    }
                }

                $sd['temu_history_rebuilt_at'] = now()->toDateTimeString();

                $this->line(sprintf(
                    '%s %s %s  l30 %s → %s   gpft %s → %s   npft %s → %s   groi %s',
                    $dry ? 'DRY' : 'UPD',
                    str_pad($key, 6),
                    $snapshot->toDateString(),
                    $old['l30'] ?? '—',
                    $sales,
                    $old['g'] ?? '—',
                    $gpft,
                    $old['n'] ?? '—',
                    $sd['npft_percent'],
                    $groi
                ));

                if (! $dry) {
                    $row->summary_data = $sd;
                    $row->notes = 'Temu history rebuilt from orders ending '.$asOf->toDateString();
                    $row->save();
                }
                $done++;
            }

            $this->info(($dry ? 'Would update' : 'Updated')." {$done} {$key} snapshot(s), skipped {$skipped}");
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(string $key, Carbon $start, Carbon $end): array
    {
        return match ($key) {
            'temu2' => TemuShopifySalesService::computeTemu2TabulatorMetrics($start, $end),
            'temu3' => TemuShopifySalesService::computeMetricsFromTemu3Orders($start, $end),
            default => TemuShopifySalesService::computeMetricsFromOrders($start, $end),
        };
    }

    private function firstOrderDate(string $key): ?Carbon
    {
        try {
            $min = match ($key) {
                'temu2' => Schema::hasTable('temu2_orders') ? Temu2Order::min('parent_order_time') : null,
                'temu3' => Schema::hasTable('temu3_orders') ? Temu3Order::min('purchase_date') : null,
                default => Schema::hasTable('temu_orders') ? TemuOrder::min('parent_order_time') : null,
            };
        } catch (\Throwable $e) {
            return null;
        }

        return $min ? Carbon::parse((string) $min, self::TZ)->startOfDay() : null;
    }
}
