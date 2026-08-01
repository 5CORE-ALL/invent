<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SyncInventoryToBestBuy implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1700;

    public int $uniqueFor = 1800;

    public function __construct()
    {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('bestbuy'));
    }

    public function uniqueId(): string
    {
        return 'mm-full-inv-bestbuy';
    }

    public function handle(): void
    {
        Log::info('SyncInventoryToBestBuy: starting');
        try {
            Artisan::call('bestbuy:sync-inventory-from-shopify');
            Log::info('SyncInventoryToBestBuy: completed', ['output' => trim(Artisan::output())]);
        } catch (\Throwable $e) {
            Log::error('SyncInventoryToBestBuy: failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
