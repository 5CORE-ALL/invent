<?php

namespace App\Services;

use App\Models\ShopifySku;
use App\Models\StoreListingPrice;
use Illuminate\Support\Facades\Log;

/**
 * After an S PRC auto-push, pull live listing Price for only the SKUs that were pushed.
 */
class ChannelPushedPricePullService
{
    /**
     * @param  list<string>  $skus
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string,skipped?:bool}>
     */
    public function pullSkus(string $channel, array $skus): array
    {
        $channel = strtolower(trim($channel));
        $skus = array_values(array_unique(array_filter(array_map(static function ($s) {
            return strtoupper(trim((string) $s));
        }, $skus), static fn ($s) => $s !== '')));
        $skus = array_slice($skus, 0, 100);
        if ($skus === []) {
            return [];
        }

        if (in_array($channel, ['ebay1', 'ebay2', 'ebay2op', 'ebay3'], true)) {
            $mp = $channel === 'ebay2op' ? 'ebay2' : $channel;
            $items = array_map(static fn ($sku) => ['sku' => $sku, 'marketplace' => $mp], $skus);

            return app(PefEbayPricePullService::class)->pullItems($items);
        }

        if (in_array($channel, ['shopify_b2b', 'shopify_b2c'], true)) {
            return $this->pullShopifyStore($skus, $channel);
        }

        return array_map(static fn ($sku) => [
            'success' => false,
            'sku' => $sku,
            'marketplace' => $channel,
            'price' => null,
            'sprice' => null,
            'message' => 'live pull not available for this channel',
            'skipped' => true,
        ], $skus);
    }

    /**
     * @param  list<string>  $skus
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string}>
     */
    private function pullShopifyStore(array $skus, string $channel): array
    {
        $sync = app(StorePriceSyncService::class);
        $out = [];
        foreach ($skus as $sku) {
            try {
                $sync->sync($sku);
                $price = $this->shopifyLivePrice($sku, $channel);
                $ok = $price !== null && $price > 0;
                $out[] = [
                    'success' => $ok,
                    'sku' => $sku,
                    'marketplace' => $channel,
                    'price' => $ok ? round($price, 2) : null,
                    'sprice' => null,
                    'message' => $ok ? ('Pulled store price $'.number_format($price, 2)) : 'Store price not found',
                ];
            } catch (\Throwable $e) {
                Log::warning('Channel pushed-price Shopify pull failed', [
                    'sku' => $sku,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
                $out[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => $channel,
                    'price' => null,
                    'sprice' => null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $out;
    }

    private function shopifyLivePrice(string $sku, string $channel): ?float
    {
        $row = StoreListingPrice::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->orderByDesc('is_variant')
            ->first();
        if ($row && $row->selling_price !== null && (float) $row->selling_price > 0) {
            return (float) $row->selling_price;
        }

        $ss = ShopifySku::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->first();
        if (! $ss) {
            return null;
        }
        $price = $channel === 'shopify_b2b'
            ? (float) ($ss->b2b_price ?: $ss->price)
            : (float) ($ss->b2c_price ?: $ss->price);

        return $price > 0 ? $price : null;
    }
}
