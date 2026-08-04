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

class SyncInventoryToTemu2 implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1700;

    public int $uniqueFor = 1800;

    public function __construct()
    {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('temu2'));
    }

    public function uniqueId(): string
    {
        return 'mm-full-inv-temu2';
    }

    public function handle(): void
    {
        Log::info('SyncInventoryToTemu2: starting');
        try {
            Artisan::call('temu2:sync-inventory-from-shopify');
            Log::info('SyncInventoryToTemu2: completed', ['output' => trim(Artisan::output())]);
        } catch (\Throwable $e) {
            Log::error('SyncInventoryToTemu2: failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
