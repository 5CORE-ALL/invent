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

class SyncInventoryToDoba implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1700;

    public int $uniqueFor = 1800;

    public function __construct()
    {
        // Own queue (mm-doba-listings): the full catalog push can run ~30 min and must not block
        // order fetch / Shopify imports on mm-doba.
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::listingsQueueFor('doba'));
    }

    public function uniqueId(): string
    {
        return 'mm-full-inv-doba';
    }

    public function handle(): void
    {
        Log::info('SyncInventoryToDoba: starting');
        try {
            Artisan::call('doba:sync-inventory-from-shopify');
            Log::info('SyncInventoryToDoba: completed', ['output' => trim(Artisan::output())]);
        } catch (\Throwable $e) {
            Log::error('SyncInventoryToDoba: failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
