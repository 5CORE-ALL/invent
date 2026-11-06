<?php

namespace App\Services\Lqs;

use App\Models\ProductMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scores Business 5 Core (B2B store) listings (LQS 0-100) from the synced store catalog (b5c_b2b_products and its
 * payload from business5core.com/api/listings). No live API calls.
 *
 * @see B5cb2bListingQualityScorer
 */
class B5cb2bLqsApiSyncService extends StoredLqsSyncService
{
    public const MARKETPLACE = 'b5cb2b';

    public const LOCK_KEY = 'lqs-sync-b5cb2b-lock';

    public const STATUS_KEY = 'lqs-sync-b5cb2b-status';

    public function __construct(private readonly B5cb2bListingQualityScorer $scorer)
    {
    }

    protected function scorer(): StoredListingScorer
    {
        return $this->scorer;
    }

    public function isConfigured(): bool
    {
        return Schema::hasTable('b5c_b2b_products') && Schema::hasTable((new ProductMaster)->getTable());
    }

    protected function listedKeys(array $master): array
    {
        $keys = [];
        foreach (DB::table('b5c_b2b_products')->select('sku')->whereNotNull('sku')->where('sku', '!=', '')->cursor() as $row) {
            $key = self::normalizeSku((string) $row->sku);
            if (isset($master[$key])) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    protected function buildContexts(?array $wanted): array
    {
        $out = [];
        foreach (DB::table('b5c_b2b_products')
            ->select('sku', 'listing_id', 'title', 'qty', 'price', 'special_price', 'in_stock', 'is_active', 'payload')
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->orderBy('id')->cursor() as $row) {
            $key = self::normalizeSku((string) $row->sku);
            if (! isset($wanted[$key])) {
                continue;
            }
            $p = json_decode((string) $row->payload, true);
            $p = is_array($p) ? $p : [];

            $images = [];
            if (! empty($p['base_image']['path'])) {
                $images[] = $p['base_image']['path'];
            }
            foreach ((array) ($p['additional_images'] ?? []) as $img) {
                $images[] = is_array($img) ? ($img['path'] ?? '') : $img;
            }

            $dims = is_array($p['dimensions'] ?? null) ? $p['dimensions'] : [];
            $price = (float) $row->price;
            $selling = (float) ($p['selling_price']['amount'] ?? 0) ?: ((float) $row->special_price > 0 ? (float) $row->special_price : $price);

            $out[$key] = [
                'sku' => $wanted[$key],
                'listing_id' => trim((string) $row->listing_id),
                'title' => trim((string) ($row->title ?: ($p['name'] ?? ''))),
                'short_description' => (string) ($p['short_description'] ?? ''),
                'images' => $images,
                'category_count' => count((array) ($p['categories'] ?? [])),
                'has_brand' => ! empty($p['brand']),
                'price' => $price,
                'selling_price' => $selling,
                'stock' => (int) $row->qty,
                'in_stock' => (bool) $row->in_stock,
                'active' => (bool) $row->is_active,
                'length' => $p['length'] ?? ($dims['length'] ?? 0),
                'width' => $p['width'] ?? ($dims['width'] ?? 0),
                'height' => $p['height'] ?? ($dims['height'] ?? 0),
                'weight' => $p['weight'] ?? 0,
            ];
        }

        return $out;
    }
}
