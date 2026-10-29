<?php

namespace App\Console\Commands;

use App\Support\Marketplace\ListingManagerAmazonHydrator;
use Illuminate\Console\Command;

class CheckImageMasterSkus extends Command
{
    protected $signature = 'imagemaster:check {skus* : One or more SKUs (quote SKUs that contain spaces)}';

    protected $description = 'Show the Image Master photos found for SKUs and what a marketplace publish would send.';

    public function handle(): int
    {
        foreach ((array) $this->argument('skus') as $sku) {
            $r = ListingManagerAmazonHydrator::imageMasterReport((string) $sku);
            $this->line('');
            $this->info('SKU: ['.$r['sku'].']');
            $this->line("  product_images rows : {$r['product_images']}");
            $this->line("  product master slots: {$r['product_master_slots']}");
            $this->line("  metrics image_master: {$r['metrics']}");
            $this->line('  Image Master photos : '.count($r['image_master_urls']).($r['image_master_urls'] === [] ? '  -> BLOCKED (no photos)' : '  -> OK'));
            foreach ($r['image_master_urls'] as $i => $url) {
                $this->line('     '.($i + 1).'. '.$url);
            }
            $this->line('  Would publish       : '.count($r['publish_urls']).' public https URL(s)');
            foreach ($r['publish_urls'] as $i => $url) {
                $this->line('     '.($i + 1).'. '.$url);
            }
        }

        return self::SUCCESS;
    }
}
