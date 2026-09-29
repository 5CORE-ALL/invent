<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Delayed re-queue of SKUs whose marketplace push failed.
 *
 * Deliberately NOT unique: the unique PushLinkedSkuInventoryFromShopify lock
 * must stay free so brand-new webhook SKUs are never blocked behind a
 * 5–45 minute retry delay.
 */
class RetryLinkedSkuInventoryPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    /**
     * @param  list<string>  $skus
     */
    public function __construct(
        public string $marketplace,
        public array $skus,
        public int $attempt,
    ) {
        $this->marketplace = strtolower(trim($this->marketplace));
        $this->onQueue(MarketplaceManagerRegistry::queueFor($this->marketplace));
    }

    public function handle(): void
    {
        if ($this->skus === []) {
            return;
        }

        Log::info('RetryLinkedSkuInventoryPush: re-queueing failed SKUs', [
            'marketplace' => $this->marketplace,
            'attempt' => $this->attempt,
            'sku_count' => count($this->skus),
        ]);

        PushLinkedSkuInventoryFromShopify::enqueue($this->marketplace, $this->skus);
    }
}
