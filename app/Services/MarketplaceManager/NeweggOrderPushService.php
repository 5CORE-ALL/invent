<?php

namespace App\Services\MarketplaceManager;

use App\Models\NeweggOrderMetric;
use App\Models\ShopifySku;
use App\Services\ShopifyApiService;
use Illuminate\Support\Facades\Log;

/**
 * Push a Newegg order line into Shopify (draft order / order create).
 * Initial implementation: mark import + attach notes; extend with full draft create like AE.
 */
class NeweggOrderPushService
{
    public function __construct(
        protected ShopifyApiService $shopifyApi
    ) {}

    public function importToShopify(NeweggOrderMetric $order): ?string
    {
        if ($order->shopify_order_id) {
            return (string) $order->shopify_order_id;
        }

        $sku = trim((string) $order->sku);
        if ($sku === '' || in_array($sku, ['__order__', '__unknown__'], true)) {
            Log::warning('NeweggOrderPushService: cannot import order without SKU', ['id' => $order->id]);

            return null;
        }

        $settings = \App\Models\MarketplaceSyncSettings::getFor('newegg');
        $tags = $settings['order']['shopify_order_tags'] ?? [];
        if (! is_array($tags)) {
            $tags = [];
        }
        $tags[] = 'newegg';
        $tags[] = 'newegg-order-'.$order->order_id;

        try {
            // Prefer existing AE/Reverb-style draft create if ShopifyApiService supports marketplace imports.
            if (method_exists($this->shopifyApi, 'createOrderFromMarketplaceLines')) {
                $shopifyId = $this->shopifyApi->createOrderFromMarketplaceLines([
                    'name' => 'NE-'.$order->order_id,
                    'note' => 'Imported from Newegg order '.$order->order_id,
                    'tags' => implode(', ', array_unique($tags)),
                    'source_name' => $settings['order']['shopify_source_name'] ?? 'newegg',
                    'lines' => [[
                        'sku' => $sku,
                        'quantity' => max(1, (int) $order->quantity),
                        'price' => $order->amount,
                        'title' => $order->display_title ?: $sku,
                    ]],
                ]);

                return $shopifyId ? (string) $shopifyId : null;
            }

            Log::warning('NeweggOrderPushService: Shopify create helper missing; leave order ready for manual import', [
                'id' => $order->id,
                'order_id' => $order->order_id,
                'sku' => $sku,
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('NeweggOrderPushService: import failed', [
                'id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
