<?php

namespace App\Jobs;

use App\Models\MmWebhookEvent;
use App\Services\MarketplaceManager\InventoryLedgerService;
use App\Services\MarketplaceManager\ShopifyInventoryWebhookResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResolveInventoryItemSkuJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [15, 45, 90, 180, 300];

    public function __construct(
        public int $eventId,
        public string $inventoryItemId,
        public ?int $available = null,
        public ?string $locationId = null,
    ) {
        $this->onQueue((string) config('marketplace_manager.webhook_queue', 'mm-ingress'));
        $this->inventoryItemId = preg_replace('/\D+/', '', $this->inventoryItemId) ?: $this->inventoryItemId;
    }

    public function handle(InventoryLedgerService $ledger): void
    {
        $event = MmWebhookEvent::query()->find($this->eventId);

        // Prefer API resolve (also touches shopify_skus cache). Force live path by
        // skipping ledger-only: call GraphQL helpers via empty local miss path.
        $skus = ShopifyInventoryWebhookResolver::skusForInventoryItemId($this->inventoryItemId, true);

        if ($skus === []) {
            Log::warning('ResolveInventoryItemSkuJob: still unresolved', [
                'event_id' => $this->eventId,
                'inventory_item_id' => $this->inventoryItemId,
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                if ($event) {
                    $event->status = MmWebhookEvent::STATUS_FAILED;
                    $event->error = 'sku_unresolved_after_retries';
                    $event->processed_at = now();
                    $event->save();
                }
            }

            throw new \RuntimeException(
                'Could not resolve SKU for inventory_item_id '.$this->inventoryItemId
            );
        }

        foreach ($skus as $sku) {
            $ledger->upsertMapping(
                $sku,
                $this->inventoryItemId,
                null,
                $this->available,
                'resolve',
                $this->locationId,
            );
        }

        if ($this->available !== null) {
            $ledger->applyWebhook(
                $this->inventoryItemId,
                $this->available,
                $this->locationId,
                $skus,
            );
        }

        $dispatched = PushLinkedSkuInventoryFromShopify::dispatchToEnabled(
            $skus,
            $this->available,
            $this->inventoryItemId,
        );

        if ($event) {
            $event->status = MmWebhookEvent::STATUS_PROCESSED;
            $event->processed_at = now();
            $event->error = null;
            $event->inventory_item_id = $this->inventoryItemId;
            $event->save();
        }

        Log::info('ResolveInventoryItemSkuJob: resolved and queued push', [
            'event_id' => $this->eventId,
            'inventory_item_id' => $this->inventoryItemId,
            'skus' => $skus,
            'jobs_dispatched' => $dispatched,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        $event = MmWebhookEvent::query()->find($this->eventId);
        if ($event && $event->status !== MmWebhookEvent::STATUS_PROCESSED) {
            $event->status = MmWebhookEvent::STATUS_FAILED;
            $event->error = $e?->getMessage() ?? 'resolve_failed';
            $event->processed_at = now();
            $event->save();
        }
    }
}
