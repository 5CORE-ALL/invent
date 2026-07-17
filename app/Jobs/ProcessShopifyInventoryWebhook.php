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

class ProcessShopifyInventoryWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public function __construct(public int $eventId)
    {
        $this->onQueue((string) config('marketplace_manager.webhook_queue', 'mm-ingress'));
    }

    public function handle(InventoryLedgerService $ledger): void
    {
        $event = MmWebhookEvent::query()->find($this->eventId);
        if (! $event) {
            Log::warning('ProcessShopifyInventoryWebhook: event missing', ['event_id' => $this->eventId]);

            return;
        }

        if ($event->status === MmWebhookEvent::STATUS_PROCESSED) {
            return;
        }

        $event->status = MmWebhookEvent::STATUS_PROCESSING;
        $event->error = null;
        $event->save();

        try {
            $topic = (string) ($event->topic ?? '');
            if (in_array($topic, ['products/create', 'products/update', 'products/delete'], true)) {
                ProcessShopifyCatalogWebhook::dispatch($this->eventId);

                return;
            }

            $payload = is_array($event->payload) ? $event->payload : [];
            $resolved = ShopifyInventoryWebhookResolver::resolve($payload);
            $skus = $resolved['skus'];
            $available = $resolved['available'];
            $inventoryItemId = $resolved['inventory_item_id']
                ?? $event->inventory_item_id;

            $locationId = isset($payload['location_id'])
                ? (string) $payload['location_id']
                : null;

            if ($inventoryItemId && $event->inventory_item_id !== $inventoryItemId) {
                $event->inventory_item_id = $inventoryItemId;
                $event->save();
            }

            if ($skus === [] && $inventoryItemId) {
                ResolveInventoryItemSkuJob::dispatch(
                    $this->eventId,
                    (string) $inventoryItemId,
                    $available,
                    $locationId,
                );

                Log::info('ProcessShopifyInventoryWebhook: SKU unresolved — resolve job queued', [
                    'event_id' => $this->eventId,
                    'inventory_item_id' => $inventoryItemId,
                ]);

                return;
            }

            if ($skus === []) {
                $event->status = MmWebhookEvent::STATUS_SKIPPED;
                $event->error = 'missing_inventory_item_id_and_sku';
                $event->processed_at = now();
                $event->save();

                return;
            }

            if ($inventoryItemId) {
                $ledger->applyWebhook((string) $inventoryItemId, $available, $locationId, $skus);
            } else {
                foreach ($skus as $sku) {
                    $ledger->upsertMapping($sku, null, null, $available, 'webhook');
                }
            }

            $dispatched = PushLinkedSkuInventoryFromShopify::dispatchToEnabled(
                $skus,
                $available,
                $inventoryItemId ? (string) $inventoryItemId : null,
            );

            $event->status = MmWebhookEvent::STATUS_PROCESSED;
            $event->processed_at = now();
            $event->error = null;
            $event->save();

            Log::info('ProcessShopifyInventoryWebhook: processed', [
                'event_id' => $this->eventId,
                'skus' => $skus,
                'available' => $available,
                'jobs_dispatched' => $dispatched,
            ]);
        } catch (\Throwable $e) {
            $event->status = MmWebhookEvent::STATUS_FAILED;
            $event->error = $e->getMessage();
            $event->save();

            Log::error('ProcessShopifyInventoryWebhook: failed', [
                'event_id' => $this->eventId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?\Throwable $e): void
    {
        $event = MmWebhookEvent::query()->find($this->eventId);
        if ($event && $event->status !== MmWebhookEvent::STATUS_PROCESSED) {
            $event->status = MmWebhookEvent::STATUS_FAILED;
            $event->error = $e?->getMessage() ?? 'job_failed';
            $event->save();
        }
    }
}
