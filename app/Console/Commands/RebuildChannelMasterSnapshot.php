<?php

namespace App\Console\Commands;

use App\Models\ChannelMasterSummary;
use App\Models\MarketplaceDailyMetric;
use App\Services\Support\YesterdayMarketplaceMetricsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RebuildChannelMasterSnapshot extends Command
{
    protected $signature = 'channel-master:rebuild-snapshot
        {date : Snapshot date YYYY-MM-DD (chart label is this date minus 1 Pacific day)}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Rebuild channel_master_daily_data for one day from marketplace_daily_metrics';

    /** @var array<string, string> */
    private array $metricsNameToKey = [
        'Amazon' => 'amazon',
        'eBay' => 'ebay',
        'eBay 2' => 'ebaytwo',
        'eBay 3' => 'ebaythree',
        'Temu' => 'temu',
        'Temu 2' => 'temu2',
        'Shein' => 'shein',
        'Mercari With Ship' => 'mercariwship',
        'Mercari Without Ship' => 'mercariwoship',
        'Purchasing Power' => 'purchasingpower',
        'AliExpress' => 'aliexpress',
        'TikTok' => 'tiktokshop',
        'TikTok 2' => 'tiktok2',
        'Best Buy USA' => 'bestbuyusa',
        'Macys' => 'macys',
        'Doba' => 'doba',
        'Wayfair' => 'wayfair',
        'TopDawg' => 'topdawg',
        'Depop' => 'depop',
        'Faire' => 'faire',
        'Walmart' => 'walmart',
        'Vinted' => 'vinted',
    ];

    public function handle(): int
    {
        $date = Carbon::parse($this->argument('date'))->toDateString();
        $dry = (bool) $this->option('dry-run');
        // Snapshot D stores y_sales for D−1 (chart label is also D−1).
        $ySalesDate = Carbon::parse($date, 'America/Los_Angeles')->subDay()->toDateString();
        $ySalesSvc = app(YesterdayMarketplaceMetricsService::class);

        $metrics = MarketplaceDailyMetric::whereDate('date', $date)->get();
        if ($metrics->isEmpty()) {
            $this->error("No marketplace_daily_metrics rows for {$date}.");

            return 1;
        }

        $updated = 0;
        $skipped = 0;
        foreach ($metrics as $m) {
            $key = $this->metricsNameToKey[$m->channel] ?? null;
            if (! $key) {
                $this->line("skip unmapped {$m->channel}");
                $skipped++;
                continue;
            }

            $existing = ChannelMasterSummary::where('channel', $key)
                ->whereDate('snapshot_date', $date)
                ->first();
            $sd = $existing ? $existing->summaryArray() : [];
            if (! $existing) {
                $prior = ChannelMasterSummary::query()
                    ->where('channel', $key)
                    ->where('snapshot_date', '<', $date)
                    ->orderByDesc('snapshot_date')
                    ->first();
                if ($prior) {
                    $sd = $prior->summaryArray();
                }
            }

            $extra = is_array($m->extra_data)
                ? $m->extra_data
                : (json_decode((string) $m->extra_data, true) ?: []);

            $adSpend = (float) $m->kw_spent + (float) $m->pmt_spent + (float) $m->hl_spent;
            $clicks = (int) ($extra['kw_clicks'] ?? 0)
                + (int) ($extra['pt_clicks'] ?? 0)
                + (int) ($extra['pmt_clicks'] ?? 0)
                + (int) ($extra['hl_clicks'] ?? 0);
            $adSales = (float) ($extra['kw_sales'] ?? 0)
                + (float) ($extra['pt_sales'] ?? 0)
                + (float) ($extra['pmt_sales'] ?? 0)
                + (float) ($extra['hl_sales'] ?? 0);
            $adSold = (int) ($extra['kw_sold'] ?? 0)
                + (int) ($extra['pt_sold'] ?? 0)
                + (int) ($extra['pmt_sold'] ?? 0)
                + (int) ($extra['hl_sold'] ?? 0);

            $oldL30 = $sd['l30_sales'] ?? null;
            $oldY = $sd['y_sales'] ?? null;
            $ySales = $ySalesSvc->salesForPacificDate($key, $ySalesDate);
            $sd['l30_sales'] = (float) ($m->l30_sales ?? $m->total_sales ?? 0);
            if ($ySales !== null) {
                $sd['y_sales'] = $ySales;
            }
            $sd['l30_orders'] = (float) ($m->total_orders ?? 0);
            $sd['total_quantity'] = (float) ($m->total_quantity ?? 0);
            $sd['gprofit_percent'] = (float) ($m->pft_percentage ?? 0);
            $sd['groi_percent'] = (float) ($m->roi_percentage ?? 0);
            $sd['npft_percent'] = round((float) ($m->n_pft ?? 0), 2);
            $sd['nroi_percent'] = (float) ($m->n_roi ?? 0);
            $sd['tcos_percent'] = round((float) ($m->tacos_percentage ?? $m->ads_percentage ?? 0), 2);
            $sd['total_ad_spend'] = $adSpend;
            $sd['cogs'] = (float) ($m->total_cogs ?? 0);
            $sd['total_pft'] = (float) ($m->total_pft ?? 0);
            if ($clicks > 0) {
                $sd['clicks'] = $clicks;
            }
            if ($adSales > 0) {
                $sd['ad_sales'] = $adSales;
            }
            if ($adSold > 0) {
                $sd['ad_sold'] = $adSold;
            }
            $sd['rebuilt_from_metrics'] = $date;
            $sd['rebuilt_at'] = now()->toDateTimeString();

            $this->line(sprintf(
                '%s %s  l30 %s → %s  y %s → %s  gpft %s',
                $dry ? 'DRY' : 'UPD',
                str_pad($key, 16),
                $oldL30 ?? '—',
                $sd['l30_sales'],
                $oldY ?? '—',
                $sd['y_sales'] ?? '—',
                $sd['gprofit_percent']
            ));

            if (! $dry) {
                $notes = 'Rebuilt from marketplace_daily_metrics '.$date;
                if ($existing) {
                    $existing->summary_data = $sd;
                    $existing->notes = $notes;
                    $existing->save();
                } else {
                    // Local `id` is often NOT auto-increment (imported schema).
                    $nextId = ((int) ChannelMasterSummary::max('id')) + 1;
                    ChannelMasterSummary::create([
                        'id' => $nextId,
                        'channel' => $key,
                        'snapshot_date' => $date,
                        'summary_data' => $sd,
                        'notes' => $notes,
                    ]);
                }
            }
            $updated++;
        }

        foreach (['shopify', 'reverb'] as $extraKey) {
            $existing = ChannelMasterSummary::where('channel', $extraKey)
                ->whereDate('snapshot_date', $date)
                ->first();
            if (! $existing) {
                continue;
            }
            $ySales = $ySalesSvc->salesForPacificDate($extraKey, $ySalesDate);
            if ($ySales === null) {
                continue;
            }
            $sd = $existing->summaryArray();
            $oldY = $sd['y_sales'] ?? null;
            $sd['y_sales'] = $ySales;
            $this->line(sprintf(
                '%s %s  y %s → %s',
                $dry ? 'DRY' : 'UPD',
                str_pad($extraKey, 16),
                $oldY ?? '—',
                $ySales
            ));
            if (! $dry) {
                $existing->summary_data = $sd;
                $existing->notes = 'Rebuilt y_sales from orders '.$ySalesDate;
                $existing->save();
            }
            $updated++;
        }

        $label = Carbon::parse($date, 'America/Los_Angeles')->subDay()->toDateString();
        $this->info(($dry ? 'Would update' : 'Updated')." {$updated} channels (skipped {$skipped}) for snapshot {$date} → chart day {$label}");

        return 0;
    }
}
