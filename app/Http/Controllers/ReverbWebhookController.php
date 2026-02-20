<?php

namespace App\Http\Controllers;

use App\Jobs\ImportReverbOrderToShopify;
use App\Models\ReverbOrderMetric;
use App\Models\ReverbSyncSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReverbWebhookController extends Controller
{
    /**
     * Handle incoming Reverb webhook events (order.placed, order.shipped, listing.updated).
     * Configure webhook URL in Reverb seller settings if available.
     */
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.reverb.webhook_secret');
        if ($secret) {
            $signature = $request->header('X-Reverb-Signature') ?? $request->header('X-Webhook-Signature') ?? '';
            $payload = $request->getContent();
            if (!$this->verifySignature($payload, $signature, $secret)) {
                Log::warning('ReverbWebhookController: invalid signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->all();
        $event = $payload['event'] ?? $payload['type'] ?? $payload['action'] ?? null;

        if (!$event) {
            Log::warning('ReverbWebhookController: missing event type', ['payload_keys' => array_keys($payload)]);
            return response()->json(['error' => 'Missing event type'], 400);
        }

        try {
            if (in_array($event, ['order.placed', 'order.paid', 'order.created'])) {
                $this->handleOrderPlaced($payload);
            } elseif (in_array($event, ['order.shipped', 'order.delivered'])) {
                $this->handleOrderShipped($payload);
            } elseif (in_array($event, ['listing.updated', 'listing.inventory_updated'])) {
                $this->handleListingUpdated($payload);
            } else {
                Log::info('ReverbWebhookController: unhandled event', ['event' => $event]);
            }
        } catch (\Throwable $e) {
            Log::error('ReverbWebhookController: processing failed', ['event' => $event, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Processing failed'], 500);
        }

        return response()->json(['ok' => true]);
    }

    protected function verifySignature(string $payload, string $signature, string $secret): bool
    {
        if (empty($signature)) {
            return false;
        }
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    protected function handleOrderPlaced(array $payload): void
    {
        $settings = ReverbSyncSettings::getForReverb();
        if (!($settings['order']['import_orders_to_main_store'] ?? false)) {
            return;
        }

        $orderNumber = $payload['order_number'] ?? $payload['order']['order_number'] ?? $payload['id'] ?? null;
        if (!$orderNumber) {
            Log::warning('ReverbWebhookController: order.placed missing order_number');
            return;
        }

        $metric = ReverbOrderMetric::where('order_number', (string) $orderNumber)->first();
        if (!$metric) {
            Log::info('ReverbWebhookController: order not in metrics yet, will be imported on next fetch', ['order_number' => $orderNumber]);
            return;
        }

        if ($metric->shopify_order_id) {
            return;
        }

        ImportReverbOrderToShopify::dispatch($metric->id);
        Log::info('ReverbWebhookController: dispatched ImportReverbOrderToShopify', ['order_number' => $orderNumber]);
    }

    protected function handleOrderShipped(array $payload): void
    {
        $orderNumber = $payload['order_number'] ?? $payload['order']['order_number'] ?? $payload['id'] ?? null;
        if (!$orderNumber) {
            return;
        }

        $metric = ReverbOrderMetric::where('order_number', (string) $orderNumber)->first();
        if (!$metric || !$metric->shopify_order_id) {
            return;
        }

        // Fulfillment with tracking is added when order is pushed; if shipped later, next fetch will pick it up
        Log::info('ReverbWebhookController: order.shipped received', ['order_number' => $orderNumber]);
    }

    protected function handleListingUpdated(array $payload): void
    {
        Log::info('ReverbWebhookController: listing.updated received', ['payload' => array_keys($payload)]);
        // Trigger inventory sync - can dispatch job: SyncReverbInventoryFromShopify
        // For now, rely on scheduled sync; webhook can be extended to dispatch job
    }
}
