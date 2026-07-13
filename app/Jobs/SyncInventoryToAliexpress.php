<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SyncInventoryToAliexpress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::QUEUE);
    }

    public function handle(): void
    {
        Log::info('SyncInventoryToAliexpress: starting');
        Artisan::call('aliexpress:sync-inventory-from-shopify');
        Log::info('SyncInventoryToAliexpress: completed', ['output' => trim(Artisan::output())]);
    }
}
