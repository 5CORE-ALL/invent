<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessShopifyCatalogWebhook;
use App\Jobs\ProcessShopifyInventoryWebhook;
use App\Models\MmWebhookEvent;
use App\Services\MarketplaceManager\ShopifyInventoryWebhookResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    private const INVENTORY_TOPICS = [
        'inventory_levels/update',
        'inventory_levels/connect',
    ];

    private const PRODUCT_TOPICS = [
        'products/create',
        'products/update',
        'products/delete',
    ];

    /**
     * Shopify webhook ingress (inventory + product topics share this URL).
     * Verify HMAC → store event → enqueue → 200.
     */
    public function inventoryUpdate(Request $request): JsonResponse
    {
        $topic = (string) $request->header('X-Shopify-Topic', '');
        $allowed = array_merge(self::INVENTORY_TOPICS, self::PRODUCT_TOPICS);
        if ($topic !== '' && ! in_array($topic, $allowed, true)) {
            Log::warning('ShopifyWebhookController: unexpected topic', ['topic' => $topic]);
        }

        $secret = config('services.shopify.webhook_secret') ?? env('SHOPIFY_WEBHOOK_SECRET');
        if (! $secret) {
            Log::error('ShopifyWebhookController: SHOPIFY_WEBHOOK_SECRET missing — rejecting');

            return response()->json(['error' => 'Webhook secret not configured'], 503);
        }

        $hmac = $request->header('X-Shopify-Hmac-Sha256') ?? '';
        $payloadRaw = $request->getContent();
        if (! $this->verifyShopifyHmac($payloadRaw, $hmac, (string) $secret)) {
            Log::warning('ShopifyWebhookController: invalid HMAC');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $webhookId = trim((string) $request->header('X-Shopify-Webhook-Id', ''));
        $inventoryItemId = in_array($topic, self::INVENTORY_TOPICS, true)
            ? ShopifyInventoryWebhookResolver::extractInventoryItemId($payload)
            : null;

        if ($webhookId !== '') {
            $existing = MmWebhookEvent::query()
                ->where('source', 'shopify')
                ->where('webhook_id', $webhookId)
                ->first();

            if ($existing) {
                if ($existing->status === MmWebhookEvent::STATUS_PROCESSED) {
                    return response()->json([
                        'ok' => true,
                        'queued' => false,
                        'duplicate' => true,
                        'event_id' => $existing->id,
                    ]);
                }

                if (in_array($existing->status, [
                    MmWebhookEvent::STATUS_RECEIVED,
                    MmWebhookEvent::STATUS_FAILED,
                    MmWebhookEvent::STATUS_PROCESSING,
                ], true)) {
                    $this->dispatchForTopic((string) ($existing->topic ?: $topic), $existing->id);
                }

                return response()->json([
                    'ok' => true,
                    'queued' => true,
                    'duplicate' => true,
                    'event_id' => $existing->id,
                ]);
            }
        }

        try {
            $event = MmWebhookEvent::query()->create([
                'source' => 'shopify',
                'webhook_id' => $webhookId !== '' ? $webhookId : null,
                'topic' => $topic !== '' ? $topic : null,
                'inventory_item_id' => $inventoryItemId,
                'payload' => $payload,
                'status' => MmWebhookEvent::STATUS_RECEIVED,
            ]);
        } catch (QueryException $e) {
            if ($webhookId !== '') {
                $existing = MmWebhookEvent::query()
                    ->where('source', 'shopify')
                    ->where('webhook_id', $webhookId)
                    ->first();
                if ($existing) {
                    $this->dispatchForTopic((string) ($existing->topic ?: $topic), $existing->id);

                    return response()->json([
                        'ok' => true,
                        'queued' => true,
                        'duplicate' => true,
                        'event_id' => $existing->id,
                    ]);
                }
            }
            throw $e;
        }

        $this->dispatchForTopic($topic, $event->id);

        Log::info('ShopifyWebhookController: webhook enqueued', [
            'event_id' => $event->id,
            'webhook_id' => $webhookId,
            'topic' => $topic,
            'inventory_item_id' => $inventoryItemId,
        ]);

        return response()->json([
            'ok' => true,
            'queued' => true,
            'event_id' => $event->id,
            'topic' => $topic,
        ]);
    }

    protected function dispatchForTopic(string $topic, int $eventId): void
    {
        if (in_array($topic, self::PRODUCT_TOPICS, true)) {
            ProcessShopifyCatalogWebhook::dispatch($eventId);

            return;
        }

        // Default / inventory topics
        ProcessShopifyInventoryWebhook::dispatch($eventId);
    }

    protected function verifyShopifyHmac(string $payload, string $hmac, string $secret): bool
    {
        if ($hmac === '') {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return hash_equals($expected, $hmac);
    }
}
