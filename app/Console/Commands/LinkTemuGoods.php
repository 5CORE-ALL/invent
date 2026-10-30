<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\TemuListingPublishService;
use Illuminate\Console\Command;

class LinkTemuGoods extends Command
{
    protected $signature = 'temu:link-goods {goods : Temu 1 goods ID} {--dry-run}';

    protected $description = 'Temu 1: link all SKUs of a goods that Temu holds (e.g. a listing created by a publish whose answer was lost).';

    public function handle(TemuListingPublishService $publisher): int
    {
        $result = $publisher->linkGoodsFromTemu((string) $this->argument('goods'), (bool) $this->option('dry-run'));
        $this->line($result['message']);
        foreach ($result['linked'] as $sku) {
            $this->line('  '.$sku);
        }

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
