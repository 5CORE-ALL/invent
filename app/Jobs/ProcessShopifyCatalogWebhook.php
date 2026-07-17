<?php

namespace App\Jobs;

use App\Models\MmWebhookEvent;
use App\Services\MarketplaceManager\ShopifyCatalogWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessShopifyCatalogWebhook implements ShouldQueue
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

    public function handle(ShopifyCatalogWebhookService $catalog): void
    {
        $event = MmWebhookEvent::query()->find($this->eventId);
        if (! $event) {
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
            $payload = is_array($event->payload) ? $event->payload : [];

            if (in_array($topic, ['products/create', 'products/update'], true)) {
                $result = $catalog->upsertFromProductPayload($payload);
                Log::info('ProcessShopifyCatalogWebhook: upserted', [
                    'event_id' => $this->eventId,
                    'topic' => $topic,
                    'result' => $result,
                ]);
            } elseif ($topic === 'products/delete') {
                $result = $catalog->deleteFromProductPayload($payload);
                Log::info('ProcessShopifyCatalogWebhook: deleted', [
                    'event_id' => $this->eventId,
                    'result' => $result,
                ]);
            } else {
                $event->status = MmWebhookEvent::STATUS_SKIPPED;
                $event->error = 'unsupported_catalog_topic:'.$topic;
                $event->processed_at = now();
                $event->save();

                return;
            }

            $event->status = MmWebhookEvent::STATUS_PROCESSED;
            $event->processed_at = now();
            $event->error = null;
            $event->save();
        } catch (\Throwable $e) {
            $event->status = MmWebhookEvent::STATUS_FAILED;
            $event->error = $e->getMessage();
            $event->save();
            Log::error('ProcessShopifyCatalogWebhook: failed', [
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
