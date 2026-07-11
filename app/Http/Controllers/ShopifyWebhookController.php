<?php

namespace App\Http\Controllers;

use App\Jobs\SyncInventoryToAlibaba;
use App\Jobs\SyncInventoryToAliexpress;
use App\Jobs\SyncInventoryToReverb;
use App\Jobs\SyncInventoryToReverbManager;
use App\Models\MarketplaceSyncSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ShopifyWebhookController extends Controller
{
    /**
     * Handle Shopify inventory_levels/update webhook.
     * When inventory changes in Shopify (e.g. after Reverb order deduction), sync back to Reverb.
     * Shopify is inventory authority - do NOT push scheduled inventory blindly.
     */
    public function inventoryUpdate(Request $request): JsonResponse
    {
        $topic = $request->header('X-Shopify-Topic');
        if (!in_array($topic, ['inventory_levels/update', 'inventory_levels/connect'], true)) {
            Log::warning('ShopifyWebhookController: unexpected topic', ['topic' => $topic]);
        }

        $secret = config('services.shopify.webhook_secret') ?? env('SHOPIFY_WEBHOOK_SECRET');
        if ($secret) {
            $hmac = $request->header('X-Shopify-Hmac-Sha256') ?? '';
            $payload = $request->getContent();
            if (!$this->verifyShopifyHmac($payload, $hmac, $secret)) {
                Log::warning('ShopifyWebhookController: invalid HMAC');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        Log::info('ShopifyWebhookController: inventory update received, dispatching marketplace sync jobs');

        if (Schema::hasTable('marketplace_sync_settings')) {
            $aliexpressSettings = MarketplaceSyncSettings::getFor('aliexpress');
            if ($aliexpressSettings['inventory']['inventory_sync'] ?? false) {
                SyncInventoryToAliexpress::dispatch();
            }

            $alibabaSettings = MarketplaceSyncSettings::getFor('alibaba');
            if ($alibabaSettings['inventory']['inventory_sync'] ?? false) {
                SyncInventoryToAlibaba::dispatch();
            }

            $reverbMmSettings = MarketplaceSyncSettings::getFor('reverb');
            if ($reverbMmSettings['inventory']['inventory_sync'] ?? false) {
                // Marketplace Manager path (shared marketplace-manager queue)
                SyncInventoryToReverbManager::dispatch();
            } else {
                // Legacy Reverb inventory sync (pre–Marketplace Manager, queue: reverb)
                SyncInventoryToReverb::dispatch()->onQueue('reverb');
            }
        } else {
            SyncInventoryToReverb::dispatch()->onQueue('reverb');
        }

        return response()->json(['ok' => true]);
    }

    protected function verifyShopifyHmac(string $payload, string $hmac, string $secret): bool
    {
        if (empty($hmac)) {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return hash_equals($expected, $hmac);
    }
}
