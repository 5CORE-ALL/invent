<?php

namespace App\Console\Commands;

use App\Services\Lqs\LqsShopifySeoService;
use Illuminate\Console\Command;

class SyncLqsShopifySeoCommand extends Command
{
    protected $signature = 'lqs:sync-shopify-seo
                            {--local : Score from shopify_catalog_products only, skip live Shopify}';

    protected $description = 'Pull Shopify/Yoast listing fields and score SEO + readability for LQS Shopify.';

    public function handle(LqsShopifySeoService $service): int
    {
        $live = ! $this->option('local');
        $this->info($live
            ? 'Syncing Yoast/Shopify product SEO fields…'
            : 'Scoring local Shopify catalog products…');

        try {
            $result = $service->sync($live);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Scored {$result['products']} product(s) from {$result['source']}.");
        $this->line('SEO: good '.$result['seo']['good']
            .', ok '.$result['seo']['ok']
            .', needs improvement '.$result['seo']['bad']
            .', not analyzed '.$result['seo']['na']);
        $this->line('Readability: good '.$result['readability']['good']
            .', ok '.$result['readability']['ok']
            .', needs improvement '.$result['readability']['bad']
            .', not analyzed '.$result['readability']['na']);

        return self::SUCCESS;
    }
}
