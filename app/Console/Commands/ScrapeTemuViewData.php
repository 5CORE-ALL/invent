<?php

namespace App\Console\Commands;

use App\Services\TemuSellerViewScraperService;
use Illuminate\Console\Command;

class ScrapeTemuViewData extends Command
{
    protected $signature = 'temu:scrape-view-data
                            {--days=30 : Lookback days for Seller Center analytics}
                            {--cookie= : Override TEMU_SELLER_COOKIE for this run}
                            {--probe : Only probe endpoints (no DB write)}
                            {--keep : Do not wipe temu_view_data before insert}';

    protected $description = 'Scrape Temu Seller Center product clicks/impressions into temu_view_data (cookie session)';

    public function handle(TemuSellerViewScraperService $scraper): int
    {
        $days = (int) $this->option('days');
        $cookie = $this->option('cookie') ?: null;

        if ($this->option('probe')) {
            $this->info('Probing Seller Center view endpoints...');
            $results = $scraper->probe($cookie, $days);
            foreach ($results as $r) {
                $flag = $r['ok'] ? 'OK' : 'FAIL';
                $this->line("[{$flag}] HTTP ".($r['status'] ?? '-')." rows={$r['row_count']} {$r['url']}");
                if (! empty($r['error'])) {
                    $this->line('  '.$r['error']);
                }
            }

            return collect($results)->contains(fn ($r) => $r['ok']) ? 0 : 1;
        }

        $this->info("Scraping Temu Seller Center Views (L{$days})...");
        $result = $scraper->scrape($cookie, $days, ! $this->option('keep'));

        if ($result['ok']) {
            $this->info($result['message']);
            if (! empty($result['endpoint'])) {
                $this->line('Endpoint: '.$result['endpoint']);
            }
            $this->line("Imported={$result['imported']} skipped={$result['skipped']} deleted={$result['deleted']}");

            return 0;
        }

        $this->error($result['message']);

        return 1;
    }
}
