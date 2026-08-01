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

class SyncInventoryToAmazon implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1700;

    public int $uniqueFor = 1800;

    public function __construct()
    {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('amazon'));
    }

    public function uniqueId(): string
    {
        return 'mm-full-inv-amazon';
    }

    public function handle(): void
    {
        Log::info('SyncInventoryToAmazon: starting');
        try {
            Artisan::call('amazon:sync-inventory-from-shopify');
            Log::info('SyncInventoryToAmazon: completed', ['output' => trim(Artisan::output())]);
        } catch (\Throwable $e) {
            Log::error('SyncInventoryToAmazon: failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
