<?php

namespace App\Console\Commands;

use App\Http\Controllers\Sales\DepopSalesController;
use App\Http\Controllers\Sales\VintedSalesController;
use App\Models\ChannelMasterSummary;
use App\Models\MarketplaceDailyMetric;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Repairs the saved L30 history of the sheet-based channels (Vinted, Depop).
 *
 * Old snapshots computed L30 from every sheet row up to "today", so a snapshot
 * dated D (chart label D-1) already contained D's own sales. The correct value
 * is the 30 days ending D-1. Only the L30 sales / orders / profit fields are
 * rewritten; Y and L7 sales are left alone.
 */
class RebuildSheetChannelHistory extends Command
{
    protected $signature = 'channel-master:rebuild-sheet-history
        {channel : vinted or depop}
        {--from= : first snapshot date YYYY-MM-DD (default: first saved snapshot)}
        {--to= : last snapshot date YYYY-MM-DD (default: today)}
        {--dry-run : show what would change without writing}';

    protected $description = 'Rebuild saved L30 history for Vinted / Depop so each snapshot covers the 30 days ending the day before it';

    private const CHANNELS = [
        'vinted' => ['label' => 'Vinted', 'controller' => VintedSalesController::class],
        'depop' => ['label' => 'Depop', 'controller' => DepopSalesController::class],
    ];

    public function handle(): int
    {
        $key = strtolower((string) $this->argument('channel'));
        $cfg = self::CHANNELS[$key] ?? null;
        if ($cfg === null) {
            $this->error('Channel must be vinted or depop.');

            return 1;
        }
        $dry = (bool) $this->option('dry-run');
        $controller = app($cfg['controller']);

        $summaries = ChannelMasterSummary::where('channel', $key)
            ->when($this->option('from'), fn ($q, $d) => $q->whereDate('snapshot_date', '>=', $d))
            ->when($this->option('to'), fn ($q, $d) => $q->whereDate('snapshot_date', '<=', $d))
            ->orderBy('snapshot_date')
            ->get();

        $changed = 0;
        foreach ($summaries as $summary) {
            $date = Carbon::parse($summary->snapshot_date)->toDateString();
            $end = Carbon::parse($date)->subDay()->toDateString();
            $start = Carbon::parse($end)->subDays(29)->toDateString();

            $badge = $controller->dailySalesBadgeTotals($start, $end);
            if ($badge === null) {
                continue;
            }

            $sd = $summary->summaryArray();
            $old = (float) ($sd['l30_sales'] ?? 0);
            $new = (float) $badge['l30'];

            $sd['l30_sales'] = $new;
            $sd['l30_orders'] = (float) $badge['orders'];
            $sd['total_quantity'] = (float) $badge['qty'];
            $sd['gprofit_percent'] = (float) $badge['gpft'];
            $sd['groi_percent'] = (float) $badge['groi'];
            $sd['npft_percent'] = (float) $badge['gpft'];
            $sd['nroi_percent'] = (float) $badge['groi'];
            $sd['cogs'] = (float) $badge['cogs'];
            $sd['total_pft'] = (float) $badge['pft'];

            $this->line(sprintf('%s %s  %s  l30 %s → %s (%d orders, %s..%s)',
                $dry ? 'DRY' : 'UPD', $cfg['label'], $date, $old, $new, $badge['orders'], $start, $end));

            if (! $dry) {
                $summary->summary_data = $sd;
                $summary->notes = 'L30 rebuilt for window '.$start.'..'.$end;
                $summary->save();

                $metric = MarketplaceDailyMetric::where('channel', $cfg['label'])->whereDate('date', $date)->first();
                if ($metric) {
                    $qty = (int) $badge['qty'];
                    $metric->fill([
                        'total_orders' => (int) $badge['orders'],
                        'total_quantity' => $qty,
                        'total_revenue' => $new,
                        'total_sales' => $new,
                        'l30_sales' => $new,
                        'total_cogs' => (float) $badge['cogs'],
                        'total_pft' => (float) $badge['pft'],
                        'pft_percentage' => (float) $badge['gpft'],
                        'roi_percentage' => (float) $badge['groi'],
                        'avg_price' => $qty > 0 ? $new / $qty : 0,
                        'n_pft' => (float) $badge['pft'],
                        'n_roi' => (float) $badge['groi'],
                    ])->save();
                }
            }
            $changed++;
        }

        $this->info(($dry ? 'Would rebuild ' : 'Rebuilt ').$changed.' '.$cfg['label'].' snapshot(s).');

        return 0;
    }
}
