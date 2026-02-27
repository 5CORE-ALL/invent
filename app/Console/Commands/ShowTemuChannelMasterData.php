<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ChannelMasterSummary;

/**
 * Test command: print Temu's latest 3 dates from channel_master_daily_data
 * with extracted summary_data values (table values used by graph).
 *
 * Run: php artisan temu:show-latest-data
 */
class ShowTemuChannelMasterData extends Command
{
    protected $signature = 'temu:show-latest-data';
    protected $description = 'Print Temu latest 3 snapshot dates and extracted table values from channel_master_daily_data';

    public function handle()
    {
        $this->info('=== TEMU DATA FROM channel_master_daily_data (ChannelMasterSummary) ===');
        $this->line('');

        // Case-insensitive: match "temu" or "Temu"
        $rows = ChannelMasterSummary::whereRaw('LOWER(channel) = ?', ['temu'])
            ->orderBy('snapshot_date', 'desc')
            ->take(3)
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No rows found for channel Temu (or temu).');
            return 0;
        }

        $this->info('Latest 3 dates:');
        $this->line('');

        foreach ($rows as $index => $row) {
            $n = $index + 1;
            $this->line("--- Record #{$n} ---");
            $this->line("  id:            " . $row->id);
            $this->line("  channel:       " . $row->channel);
            $this->line("  snapshot_date: " . $row->snapshot_date);

            $sd = $row->summary_data ?? [];
            if (empty($sd)) {
                $this->line("  summary_data:  (empty)");
            } else {
                $this->line("  summary_data (extracted table values):");

                // Keys used by graph/table (support both l and I typo)
                $sales  = $sd['l30_sales'] ?? $sd['I30_sales'] ?? null;
                $sales60 = $sd['l60_sales'] ?? $sd['I60_sales'] ?? null;
                $orders = $sd['l30_orders'] ?? $sd['I30_orders'] ?? null;
                $orders60 = $sd['l60_orders'] ?? $sd['I60_orders'] ?? null;
                $qty    = $sd['total_quantity'] ?? null;
                $gprofit = $sd['gprofit_percent'] ?? null;
                $groi   = $sd['groi_percent'] ?? null;
                $tcos   = $sd['tcos_percent'] ?? null;
                $npft   = $sd['npft_percent'] ?? null;
                $adSpend = $sd['total_ad_spend'] ?? null;
                $clicks = $sd['clicks'] ?? null;
                $adSales = $sd['ad_sales'] ?? null;
                $adSold  = $sd['ad_sold'] ?? null;
                $cogs    = $sd['cogs'] ?? null;
                $miss    = $sd['miss_count'] ?? null;
                $nmap    = $sd['nmap_count'] ?? null;

                $this->line("    l30_sales (or I30_sales): " . ($sales !== null ? $sales : 'null'));
                $this->line("    l60_sales (or I60_sales): " . ($sales60 !== null ? $sales60 : 'null'));
                $this->line("    l30_orders (or I30_orders): " . ($orders !== null ? $orders : 'null'));
                $this->line("    l60_orders (or I60_orders): " . ($orders60 !== null ? $orders60 : 'null'));
                $this->line("    total_quantity: " . ($qty !== null ? $qty : 'null'));
                $this->line("    gprofit_percent: " . ($gprofit !== null ? $gprofit : 'null'));
                $this->line("    groi_percent: " . ($groi !== null ? $groi : 'null'));
                $this->line("    tcos_percent: " . ($tcos !== null ? $tcos : 'null'));
                $this->line("    npft_percent: " . ($npft !== null ? $npft : 'null'));
                $this->line("    total_ad_spend: " . ($adSpend !== null ? $adSpend : 'null'));
                $this->line("    clicks: " . ($clicks !== null ? $clicks : 'null'));
                $this->line("    ad_sales: " . ($adSales !== null ? $adSales : 'null'));
                $this->line("    ad_sold: " . ($adSold !== null ? $adSold : 'null'));
                $this->line("    cogs: " . ($cogs !== null ? $cogs : 'null'));
                $this->line("    miss_count: " . ($miss !== null ? $miss : 'null'));
                $this->line("    nmap_count: " . ($nmap !== null ? $nmap : 'null'));

                // Show raw keys present (to spot I30 vs l30)
                $keys = array_keys($sd);
                $salesKeys = array_filter($keys, fn($k) => stripos($k, '30_sales') !== false || stripos($k, 'quantity') !== false);
                if (!empty($salesKeys)) {
                    $this->line("    (keys in summary_data containing 'sales' or 'quantity': " . implode(', ', $salesKeys) . ")");
                }
            }
            $this->line('');
        }

        $this->info('Done.');
        return 0;
    }
}
