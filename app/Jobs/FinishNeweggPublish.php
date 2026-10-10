<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\NeweggListingPublishService;
use App\Support\Marketplace\ListingChannelCounts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-checks a Newegg item feed that was still processing when the user published,
 * and connects the 9SI item number once Newegg finishes it.
 * The publish service schedules the next check while the feed stays open.
 */
class FinishNeweggPublish implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function __construct(
        public string $sku,
        public string $channel,
        public ?int $categoryId = null,
        public array $overrides = [],
    ) {
        $this->onQueue(MarketplaceManagerRegistry::listingsQueueFor('newegg'));
    }

    public function handle(NeweggListingPublishService $publisher): void
    {
        $publisher->releaseFeedFollowUp($this->sku, $this->channel);
        if ($publisher->isConnectedLocally($this->sku, $this->channel)) {
            return;
        }

        try {
            $result = $publisher->publishSkus(
                [$this->sku],
                $this->channel,
                false,
                'single',
                '',
                $this->categoryId,
                $this->overrides
            );
        } catch (\Throwable $e) {
            Log::warning('Newegg feed follow-up failed', ['sku' => $this->sku, 'error' => $e->getMessage()]);

            return;
        }

        if (! empty($result['submitted'])) {
            return;
        }

        if (empty($result['success'])) {
            Log::warning('Newegg feed follow-up: listing not created', [
                'sku' => $this->sku,
                'channel' => $this->channel,
                'message' => $result['message'] ?? '',
            ]);

            return;
        }

        try {
            ListingChannelCounts::refreshChannelOnMissingListingPage($this->channel);
        } catch (\Throwable $e) {
            Log::warning('Missing Listing refresh after Newegg publish failed', ['error' => $e->getMessage()]);
        }
    }
}
