<?php

namespace App\Console\Commands;

use App\Http\Controllers\Sales\FacebookMarketplaceController;
use App\Models\ChannelMasterCalculatedData;
use App\Models\ChannelMasterSummary;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RebuildFbMarketplaceChartHistory extends Command
{
    protected $signature = 'fb-marketplace:rebuild-chart-history
        {--days=90 : How many Pacific as-of days to rewrite}
        {--dry-run : Show values without writing}';

    protected $description = 'Rewrite FB Marketplace Active Channel snapshots from uploaded FB Sales (not sheet)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dry = (bool) $this->option('dry-run');
        $tz = 'America/Los_Angeles';
        $end = Carbon::now($tz)->subDay()->startOfDay();
        $chartStart = $end->copy()->subDays($days - 1);
        $dataStart = $chartStart->copy()->subDays(59);

        $byDay = FacebookMarketplaceController::dailySalesByPacificDate(
            $dataStart->toDateString(),
            $end->toDateString()
        );

        $sumRange = function (Carbon $from, Carbon $to) use ($byDay): float {
            $sum = 0.0;
            $cursor = $from->copy()->startOfDay();
            $last = $to->copy()->startOfDay();
            while ($cursor->lte($last)) {
                $cell = $byDay[$cursor->toDateString()] ?? 0;
                $sum += is_array($cell) ? (float) ($cell['sales'] ?? 0) : (float) $cell;
                $cursor->addDay();
            }

            return round($sum, 2);
        };

        $snapshotStart = $chartStart->copy()->addDay()->toDateString();
        $rows = ChannelMasterSummary::query()
            ->whereIn('channel', ['fbmarketplace', 'facebookmarketplace'])
            ->where('snapshot_date', '>=', $snapshotStart)
            ->orderBy('snapshot_date')
            ->get();

        $updated = 0;
        foreach ($rows as $row) {
            $asOf = Carbon::parse($row->snapshot_date, $tz)->subDay()->startOfDay();
            if ($asOf->lt($chartStart) || $asOf->gt($end)) {
                continue;
            }
            $l30 = $sumRange($asOf->copy()->subDays(29), $asOf);
            $l60 = $sumRange($asOf->copy()->subDays(59), $asOf->copy()->subDays(30));
            $l7 = $sumRange($asOf->copy()->subDays(6), $asOf);
            $y = $sumRange($asOf, $asOf);
            $qty = 0;
            $orders = 0;
            $qCursor = $asOf->copy()->subDays(29);
            while ($qCursor->lte($asOf)) {
                $cell = $byDay[$qCursor->toDateString()] ?? [];
                if (is_array($cell)) {
                    $qty += (int) ($cell['qty'] ?? 0);
                    $orders += (int) ($cell['orders'] ?? 0);
                }
                $qCursor->addDay();
            }

            $sd = $row->summaryArray();
            $oldL30 = $sd['l30_sales'] ?? null;
            $sd['l30_sales'] = $l30;
            $sd['l60_sales'] = $l60;
            $sd['l7_sales'] = $l7;
            $sd['y_sales'] = $y;
            $sd['p_sales'] = round($l7 / 7 * 30, 2);
            $sd['l30_orders'] = $orders;
            $sd['total_quantity'] = $qty;
            $sd['rebuilt_from_fb_sales'] = now()->toDateTimeString();

            $this->line(sprintf(
                '%s %s as-of %s  l30 %s → %s  y %s → %s  l7 %s',
                $dry ? 'DRY' : 'UPD',
                $row->snapshot_date?->toDateString() ?? $row->snapshot_date,
                $asOf->toDateString(),
                $oldL30 ?? '—',
                $l30,
                $sd['y_sales'],
                $y,
                $l7
            ));

            if (! $dry) {
                $row->summary_data = $sd;
                $row->notes = 'Rebuilt from facebook_marketplace_sales L30 window';
                $row->save();
            }
            $updated++;
        }

        $live = FacebookMarketplaceController::computeSalesMetricsForPacificRange(
            FacebookMarketplaceController::l30PacificRange()['start'],
            FacebookMarketplaceController::l30PacificRange()['end']
        );
        $l60Live = FacebookMarketplaceController::computeSalesMetricsForPacificRange(
            FacebookMarketplaceController::l60PacificRange()['start'],
            FacebookMarketplaceController::l60PacificRange()['end']
        );
        $yLive = FacebookMarketplaceController::computeYesterdaySales();

        $calcRows = ChannelMasterCalculatedData::query()->get()->filter(function ($r) {
            $key = strtolower(str_replace([' ', '-', '&', '/'], '', trim((string) $r->channel)));

            return in_array($key, ['fbmarketplace', 'facebookmarketplace'], true);
        });

        foreach ($calcRows as $calc) {
            $this->line(sprintf(
                '%s calculated %s  l30 %s → %s',
                $dry ? 'DRY' : 'UPD',
                $calc->channel,
                $calc->l30_sales,
                $live['total_sales'] ?? 0
            ));
            if (! $dry) {
                $calc->l30_sales = $live['total_sales'] ?? 0;
                $calc->l60_sales = $l60Live['total_sales'] ?? 0;
                $calc->l30_orders = $live['total_orders'] ?? 0;
                $calc->l60_orders = $l60Live['total_orders'] ?? 0;
                $calc->total_quantity = $live['total_quantity'] ?? 0;
                $calc->yesterday_sales = $yLive['sales'] ?? 0;
                $calc->l7_sales = $sumRange($end->copy()->subDays(6), $end);
                $calc->total_profit = $live['total_pft'] ?? 0;
                $calc->cogs = $live['total_cogs'] ?? 0;
                $calc->gprofit_pct = $live['gpft_percent'] ?? 0;
                $calc->g_roi = $live['roi_percent'] ?? 0;
                $calc->n_pft = $live['gpft_percent'] ?? 0;
                $calc->n_roi = $live['roi_percent'] ?? 0;
                $calc->ads_percentage = 0;
                $calc->tacos_percentage = 0;
                $calc->total_ad_spend = 0;
                $l60Sales = (float) ($l60Live['total_sales'] ?? 0);
                $l30Sales = (float) ($live['total_sales'] ?? 0);
                $calc->growth = $l60Sales > 0 ? round((($l30Sales - $l60Sales) / $l60Sales) * 100, 2) : 0;
                $calc->save();
            }
        }

        $this->info(($dry ? 'Would update' : 'Updated')." {$updated} snapshot row(s). Live L30 \$".($live['total_sales'] ?? 0));

        unset($sumRange);

        return 0;
    }
}
