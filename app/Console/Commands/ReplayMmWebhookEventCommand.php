<?php

namespace App\Console\Commands;

use App\Jobs\ProcessShopifyInventoryWebhook;
use App\Models\MmWebhookEvent;
use Illuminate\Console\Command;

class ReplayMmWebhookEventCommand extends Command
{
    protected $signature = 'mm:replay-webhook-event {id : mm_webhook_events.id}';

    protected $description = 'Reset a Marketplace Manager webhook event and re-dispatch ProcessShopifyInventoryWebhook';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $event = MmWebhookEvent::query()->find($id);
        if (! $event) {
            $this->error("Event {$id} not found.");

            return self::FAILURE;
        }

        $event->status = MmWebhookEvent::STATUS_RECEIVED;
        $event->error = null;
        $event->processed_at = null;
        $event->save();

        ProcessShopifyInventoryWebhook::dispatch($event->id);

        $this->info("Re-queued event {$id} on queue ".config('marketplace_manager.webhook_queue', 'mm-ingress'));

        return self::SUCCESS;
    }
}
