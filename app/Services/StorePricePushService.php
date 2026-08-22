<?php

namespace App\Services;

use App\Models\ShopifySku;
use App\Models\StoreListingPrice;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Push S PRC to business5core.com as the live special / selling price
 * (GET https://business5core.com/api/listings/prices).
 */
class StorePricePushService
{
    public function __construct(protected StoreListingApiClient $client)
    {
    }

    /**
     * @return array{success:bool,message:string,sku:string,price?:float,store_product_id?:int}
     */
    public function pushSprice(string $sku, float $sprice): array
    {
        $sku = trim($sku);
        $sprice = round($sprice, 2);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required', 'sku' => $sku];
        }
        if (! ($sprice > 0)) {
            return ['success' => false, 'message' => 'S PRC must be greater than 0', 'sku' => $sku];
        }

        $row = $this->findListing($sku);
        if (! $row) {
            $row = $this->resolveFromApi($sku);
        }
        if (! $row || (int) $row->store_product_id <= 0) {
            return [
                'success' => false,
                'message' => 'SKU '.$sku.' was not found on business5core.com',
                'sku' => $sku,
            ];
        }

        try {
            $result = $this->client->updateListingSpecialPrice(
                (int) $row->store_product_id,
                (string) $row->sku,
                $sprice,
                [
                    'variant_id' => $row->store_variant_id,
                    'slug' => $row->slug,
                    'special_price_type' => $row->special_price_type ?: 'fixed',
                ]
            );
        } catch (RuntimeException $e) {
            Log::error('Store S PRC push failed', [
                'sku' => $sku,
                'sprice' => $sprice,
                'store_product_id' => $row->store_product_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'sku' => $sku,
                'store_product_id' => (int) $row->store_product_id,
            ];
        }

        $live = $this->extractLivePrice($result['json'] ?? null, $sprice);
        $row->special_price = $live;
        $row->selling_price = $live;
        $row->special_price_type = $row->special_price_type ?: 'fixed';
        $row->synced_at = now();
        $row->save();

        Log::info('Store S PRC pushed', [
            'sku' => $sku,
            'sprice' => $live,
            'store_product_id' => $row->store_product_id,
            'path' => $result['path'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'Pushed $'.number_format($live, 2).' to business5core.com for SKU '.$sku,
            'sku' => $sku,
            'price' => $live,
            'store_product_id' => (int) $row->store_product_id,
        ];
    }

    protected function findListing(string $sku): ?StoreListingPrice
    {
        $exact = StoreListingPrice::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
            ->where('store_product_id', '>', 0)
            ->orderByDesc('is_variant')
            ->first();
        if ($exact) {
            return $exact;
        }

        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm === '') {
            return null;
        }

        $candidates = StoreListingPrice::query()
            ->where('store_product_id', '>', 0)
            ->whereRaw('REPLACE(REPLACE(UPPER(TRIM(sku)), "-", " "), "_", " ") LIKE ?', ['%'.$norm.'%'])
            ->limit(50)
            ->get();

        foreach ($candidates as $row) {
            if (ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku) === $norm) {
                return $row;
            }
        }

        return null;
    }

    protected function resolveFromApi(string $sku): ?StoreListingPrice
    {
        try {
            $items = $this->client->fetchAllPrices($sku);
        } catch (\Throwable $e) {
            Log::warning('Store S PRC lookup from API failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemSku = trim((string) ($item['sku'] ?? ''));
            if ($itemSku === '' || ShopifySku::normalizeSkuForShopifyLookup($itemSku) !== $norm) {
                continue;
            }
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            return StoreListingPrice::updateOrCreate(
                ['listing_key' => $id.':0'],
                [
                    'store_product_id' => $id,
                    'sku' => $itemSku,
                    'slug' => $item['slug'] ?? null,
                    'name' => $item['name'] ?? null,
                    'special_price_type' => $item['special_price_type'] ?? 'fixed',
                    'synced_at' => now(),
                ]
            );
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function extractLivePrice(?array $json, float $fallback): float
    {
        if (! is_array($json)) {
            return $fallback;
        }
        $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
        foreach (['selling_price', 'special_price', 'price'] as $key) {
            $raw = $data[$key] ?? null;
            if (is_array($raw) && isset($raw['amount']) && is_numeric($raw['amount'])) {
                $n = round((float) $raw['amount'], 2);
                if ($n > 0) {
                    return $n;
                }
            }
            if (is_numeric($raw)) {
                $n = round((float) $raw, 2);
                if ($n > 0) {
                    return $n;
                }
            }
        }

        return $fallback;
    }
}
