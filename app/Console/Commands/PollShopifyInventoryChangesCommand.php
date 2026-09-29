<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\ShopifyInventoryChangePoller;
use Illuminate\Console\Command;

class PollShopifyInventoryChangesCommand extends Command
{
    protected $signature = 'mm:poll-shopify-inventory-changes';

    protected $description = 'Push only the 5Core Shopify SKUs whose inventory changed since the last poll to every enabled marketplace (webhook-independent fallback).';

    public function handle(ShopifyInventoryChangePoller $poller): int
    {
        $result = $poller->run();
        $line = ($result['ok'] ? 'OK: ' : 'FAILED: ').$result['message'];
        $result['ok'] ? $this->info($line) : $this->error($line);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
