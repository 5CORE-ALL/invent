<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckGoogleAdsMissingDates extends Command
{
    protected $signature = 'google-ads:check-missing-dates
                            {--start= : Start date (Y-m-d). Default: 31 days ago}
                            {--end= : End date (Y-m-d). Default: yesterday}';

    protected $description = 'Check google_ads_campaigns for missing or incomplete date data (SHOPPING campaigns)';

    public function handle()
    {
        $endDate = $this->option('end')
            ? Carbon::parse($this->option('end'))
            : Carbon::now()->subDay();
        $startDate = $this->option('start')
            ? Carbon::parse($this->option('start'))
            : Carbon::now()->subDays(31);

        if ($startDate->gt($endDate)) {
            $this->error('Start date must be before or equal to end date.');
            return 1;
        }

        $this->info("Checking SHOPPING campaign data from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        $this->newLine();

        // Per-date: count of rows (all SHOPPING, and ENABLED SHOPPING), and total spend
        $daily = DB::table('google_ads_campaigns')
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereDate('date', '>=', $startDate->format('Y-m-d'))
            ->whereDate('date', '<=', $endDate->format('Y-m-d'))
            ->selectRaw("
                date,
                COUNT(*) as campaign_count,
                SUM(CASE WHEN campaign_status = 'ENABLED' THEN 1 ELSE 0 END) as enabled_count,
                SUM(metrics_cost_micros) / 1000000 as total_spend,
                SUM(CASE WHEN campaign_status = 'ENABLED' THEN metrics_cost_micros ELSE 0 END) / 1000000 as enabled_spend
            ")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $expectedDays = $startDate->copy()->diffInDays($endDate) + 1;
        $datesWithData = $daily->count();
        $missingDays = $expectedDays - $datesWithData;

        // Build list of all dates in range
        $allDates = [];
        for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
            $allDates[] = $d->format('Y-m-d');
        }

        $missingDates = array_diff($allDates, $daily->keys()->all());
        sort($missingDates);

        if (count($missingDates) > 0) {
            $this->warn('Missing dates (no SHOPPING rows): ' . count($missingDates));
            $this->line(implode(', ', $missingDates));
            $this->newLine();
        } else {
            $this->info('No missing dates: every date in range has at least one SHOPPING row.');
        }

        // Incomplete days: campaign count much lower than median/max
        $counts = $daily->pluck('campaign_count')->filter()->values();
        $medianCount = $counts->isEmpty() ? 0 : (int) $counts->sort()->values()->get((int) floor($counts->count() / 2));
        $maxCount = $counts->isEmpty() ? 0 : (int) $counts->max();
        $threshold = $medianCount > 0 ? (int) floor($medianCount * 0.5) : 0;

        $incomplete = [];
        foreach ($daily as $date => $row) {
            if ($maxCount > 0 && $row->campaign_count < $threshold && $row->campaign_count > 0) {
                $incomplete[] = "{$date}: {$row->campaign_count} campaigns (expected ~{$medianCount}+)";
            }
        }

        if (count($incomplete) > 0) {
            $this->warn('Possibly incomplete dates (low campaign count):');
            foreach ($incomplete as $line) {
                $this->line('  ' . $line);
            }
            $this->newLine();
        }

        // Summary table: first/last date, total spend (ENABLED) for range
        $totalEnabledSpend = $daily->sum('enabled_spend');
        $totalSpend = $daily->sum('total_spend');
        $this->info('Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Dates in range', $expectedDays],
                ['Dates with data', $datesWithData],
                ['Missing dates', count($missingDates)],
                ['Max campaigns (any day)', $maxCount],
                ['Total spend (all SHOPPING)', round($totalSpend, 2)],
                ['Total spend (ENABLED only)', round($totalEnabledSpend, 2)],
            ]
        );

        if (count($missingDates) > 0) {
            $daysBack = (int) ceil($startDate->diffInDays(Carbon::now()));
            $this->newLine();
            $this->comment("To backfill missing dates, run:");
            $this->line("  php artisan app:fetch-google-ads-campaigns --days={$daysBack}");
        }

        return 0;
    }
}
