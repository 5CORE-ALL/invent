<?php

namespace App\Http\Controllers;

use App\Jobs\PushLinkedSkuInventoryFromShopify;
use App\Services\MarketplaceManager\ShopifyInventoryWebhookResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    /**
     * Shopify inventory_levels/update (and connect).
     * Fast path: resolve SKU(s) → push live Shopify qty to all linked marketplaces
     * (Reverb + AliExpress + Alibaba) where inventory_sync is enabled.
     */
    public function inventoryUpdate(Request $request): JsonResponse
    {
        $topic = (string) $request->header('X-Shopify-Topic', '');
        if ($topic !== '' && ! in_array($topic, ['inventory_levels/update', 'inventory_levels/connect'], true)) {
            Log::warning('ShopifyWebhookController: unexpected topic', ['topic' => $topic]);
        }

        $secret = config('services.shopify.webhook_secret') ?? env('SHOPIFY_WEBHOOK_SECRET');
        if ($secret) {
            $hmac = $request->header('X-Shopify-Hmac-Sha256') ?? '';
            $payloadRaw = $request->getContent();
            if (! $this->verifyShopifyHmac($payloadRaw, $hmac, $secret)) {
                Log::warning('ShopifyWebhookController: invalid HMAC');

                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->all();
        $resolved = ShopifyInventoryWebhookResolver::resolve($payload);
        $skus = $resolved['skus'];
        $available = $resolved['available'];
        $inventoryItemId = $resolved['inventory_item_id'];

        if ($skus === []) {
            Log::warning('ShopifyWebhookController: inventory webhook missing resolvable SKU', [
                'inventory_item_id' => $inventoryItemId,
                'available' => $available,
                'topic' => $topic,
            ]);

            // Acknowledge so Shopify does not retry forever; worker/cron still covers full sync.
            return response()->json([
                'ok' => true,
                'queued' => false,
                'reason' => 'sku_unresolved',
                'inventory_item_id' => $inventoryItemId,
            ]);
        }

        // Optional write-through of available hint onto shopify_skus so listings UI is instant.
        if ($available !== null) {
            foreach ($skus as $sku) {
                try {
                    $row = \App\Models\ShopifySku::firstForProductSku($sku);
                    if ($row) {
                        $qty = max(0, (int) $available);
                        $row->available_to_sell = $qty;
                        $row->inv = $qty;
                        $row->save();
                    }
                } catch (\Throwable $e) {
                    // non-fatal
                }
            }
        }

        $dispatched = PushLinkedSkuInventoryFromShopify::dispatchToEnabled($skus, $available, $inventoryItemId);

        Log::info('ShopifyWebhookController: queued linked marketplace inventory push', [
            'skus' => $skus,
            'available' => $available,
            'inventory_item_id' => $inventoryItemId,
            'topic' => $topic,
            'jobs_dispatched' => $dispatched,
        ]);

        return response()->json([
            'ok' => true,
            'queued' => true,
            'skus' => $skus,
            'available' => $available,
        ]);
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
