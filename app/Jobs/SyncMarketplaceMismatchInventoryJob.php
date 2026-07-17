<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\MarketplaceMismatchInventoryPass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight safety net: push only Shopify↔marketplace qty-mismatch SKUs.
 * Runs often so drift is corrected ASAP without a full catalog crawl.
 */
class SyncMarketplaceMismatchInventoryJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public function __construct(public string $marketplace)
    {
        $this->marketplace = strtolower(trim($this->marketplace));
        $this->onQueue(MarketplaceManagerRegistry::queueFor($this->marketplace));
    }

    public function uniqueId(): string
    {
        return 'mm-mismatch-inv-'.$this->marketplace;
    }

    public function handle(MarketplaceMismatchInventoryPass $pass): void
    {
        Log::info('SyncMarketplaceMismatchInventoryJob: starting', [
            'marketplace' => $this->marketplace,
        ]);

        $result = $pass->run($this->marketplace);

        Log::info('SyncMarketplaceMismatchInventoryJob: done', [
            'marketplace' => $this->marketplace,
            'result' => $result,
        ]);
    }
}
