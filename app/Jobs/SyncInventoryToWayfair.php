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

class SyncInventoryToWayfair implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1500;

    public int $uniqueFor = 1740;

    public bool $failOnTimeout = false;

    public function __construct()
    {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('wayfair'));
    }

    public function uniqueId(): string
    {
        return 'mm-full-inv-wayfair';
    }

    public function handle(): void
    {
        Log::info('SyncInventoryToWayfair: starting');
        try {
            Artisan::call('wayfair:sync-inventory-from-shopify');
            Log::info('SyncInventoryToWayfair: completed', ['output' => trim(Artisan::output())]);
        } catch (\Throwable $e) {
            Log::error('SyncInventoryToWayfair: failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
