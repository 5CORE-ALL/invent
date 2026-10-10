<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\DirectStoreListingPublishService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Picture upload and Missing L recount for a Business 5 Core publish.
 * Kept off the web request so the gateway does not time out after the listing is saved.
 */
class FinishB5cB2bPublish implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    /**
     * @param  list<string>  $images
     */
    public function __construct(
        public ?string $sku = null,
        public array $images = [],
        public bool $refreshCounts = false,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::listingsQueueFor('b5cb2b'));
    }

    public function handle(DirectStoreListingPublishService $publisher): void
    {
        if ($this->sku !== null && $this->sku !== '' && $this->images !== []) {
            try {
                $publisher->updateB2bListing($this->sku, ['image_urls' => $this->images], 90);
            } catch (\Throwable $e) {
                Log::warning('B2B listing image attach failed', ['sku' => $this->sku, 'error' => $e->getMessage()]);
            }
        }

        if ($this->refreshCounts) {
            try {
                ListingChannelCounts::refreshChannelOnMissingListingPage('b5cb2b');
            } catch (\Throwable $e) {
                Log::warning('Missing Listing refresh after B2B publish failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
