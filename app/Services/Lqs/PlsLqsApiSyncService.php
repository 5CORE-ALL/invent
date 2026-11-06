<?php

namespace App\Services\Lqs;

use App\Models\ProductMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scores PLS (Pro Light Sounds Shopify store) listings (LQS 0-100) from the synced Shopify catalog
 * (shopify_catalog_products / shopify_catalog_variants where store = 'pls'). No live API calls; refresh the catalog
 * with `php artisan shopify-pls:sync`.
 *
 * @see PlsListingQualityScorer
 */
class PlsLqsApiSyncService extends StoredLqsSyncService
{
    public const MARKETPLACE = 'pls';

    public const LOCK_KEY = 'lqs-sync-pls-lock';

    public const STATUS_KEY = 'lqs-sync-pls-status';

    public function __construct(private readonly PlsListingQualityScorer $scorer)
    {
    }

    protected function scorer(): StoredListingScorer
    {
        return $this->scorer;
    }

    public function isConfigured(): bool
    {
        return Schema::hasTable('shopify_catalog_products') && Schema::hasTable('shopify_catalog_variants')
            && Schema::hasTable((new ProductMaster)->getTable());
    }

    protected function listedKeys(array $master): array
    {
        $keys = [];
        foreach (DB::table('shopify_catalog_variants')->select('sku')->where('store', 'pls')
            ->whereNotNull('sku')->where('sku', '!=', '')->cursor() as $row) {
            $key = self::normalizeSku((string) $row->sku);
            if (isset($master[$key])) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    protected function buildContexts(?array $wanted): array
    {
        $rank = ['active' => 0, 'draft' => 1];
        $best = [];

        foreach (DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
            ->where('v.store', 'pls')
            ->whereNotNull('v.sku')->where('v.sku', '!=', '')
            ->select('v.id as vid', 'v.sku', 'v.shopify_variant_id', 'v.price', 'v.inventory_quantity',
                'p.status', 'p.title', 'p.body_html', 'p.image_urls', 'p.vendor', 'p.product_type')
            ->orderBy('v.id')
            ->cursor() as $row) {
            $key = self::normalizeSku((string) $row->sku);
            if (! isset($wanted[$key])) {
                continue;
            }
            // Prefer an active product, then a draft, then anything else; first variant wins ties.
            $r = $rank[strtolower((string) $row->status)] ?? 2;
            if (! isset($best[$key]) || $r < $best[$key][0]) {
                $best[$key] = [$r, $row];
            }
        }

        $out = [];
        foreach ($best as $key => [, $row]) {
            $out[$key] = [
                'sku' => $wanted[$key],
                'listing_id' => trim((string) $row->shopify_variant_id),
                'title' => (string) $row->title,
                'description' => (string) $row->body_html,
                'images' => (string) $row->image_urls,
                'price' => (float) $row->price,
                'stock' => (int) $row->inventory_quantity,
                'status' => (string) $row->status,
                'vendor' => (string) $row->vendor,
                'product_type' => (string) $row->product_type,
            ];
        }

        return $out;
    }
}
