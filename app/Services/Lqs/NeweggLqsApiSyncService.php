<?php

namespace App\Services\Lqs;

use App\Models\ProductMaster;
use App\Support\Marketplace\ChannelListingRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scores Newegg listings (LQS 0-100) from the synced Newegg catalog: newegg_items.raw_json (website title, bullet
 * description, images, size, weight, UPC) plus newegg_pricing (price, MSRP, stock, active). No live API calls.
 *
 * @see NeweggListingQualityScorer
 */
class NeweggLqsApiSyncService extends StoredLqsSyncService
{
    public const MARKETPLACE = 'newegg';

    public const LOCK_KEY = 'lqs-sync-newegg-lock';

    public const STATUS_KEY = 'lqs-sync-newegg-status';

    public function __construct(private readonly NeweggListingQualityScorer $scorer)
    {
    }

    protected function scorer(): StoredListingScorer
    {
        return $this->scorer;
    }

    public function isConfigured(): bool
    {
        return Schema::hasTable('newegg_items') && Schema::hasTable((new ProductMaster)->getTable());
    }

    protected function listedKeys(array $master): array
    {
        $keys = [];
        foreach (DB::table('newegg_items')->select('seller_part_number', 'newegg_item_number')
            ->whereNotNull('seller_part_number')->where('seller_part_number', '!=', '')->cursor() as $row) {
            $key = self::normalizeSku((string) $row->seller_part_number);
            if (isset($master[$key]) && ChannelListingRegistry::isLiveNeweggListingId((string) $row->newegg_item_number, (string) $row->seller_part_number)) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    protected function buildContexts(?array $wanted): array
    {
        $pricing = [];
        if (Schema::hasTable('newegg_pricing')) {
            foreach (DB::table('newegg_pricing')->select('seller_part_number', 'selling_price', 'msrp', 'available_quantity', 'active')
                ->whereNotNull('seller_part_number')->orderBy('id')->cursor() as $r) {
                $k = self::normalizeSku((string) $r->seller_part_number);
                if (isset($wanted[$k]) && (! isset($pricing[$k]) || (float) $r->selling_price > 0)) {
                    $pricing[$k] = $r;
                }
            }
        }
        $fallback = [];
        if (Schema::hasTable('newegg_pricing_prices')) {
            foreach (DB::table('newegg_pricing_prices')->select('sku', 'price', 'ne_stock')->whereNotNull('sku')->cursor() as $r) {
                $k = self::normalizeSku((string) $r->sku);
                if (isset($wanted[$k])) {
                    $fallback[$k] = $r;
                }
            }
        }

        $out = [];
        foreach (DB::table('newegg_items')
            ->select('seller_part_number', 'newegg_item_number', 'title', 'upc', 'status', 'item_weight', 'raw_json')
            ->whereNotNull('seller_part_number')->where('seller_part_number', '!=', '')
            ->orderBy('id')->cursor() as $item) {
            $key = self::normalizeSku((string) $item->seller_part_number);
            if (! isset($wanted[$key]) || ! ChannelListingRegistry::isLiveNeweggListingId((string) $item->newegg_item_number, (string) $item->seller_part_number)) {
                continue;
            }
            $raw = json_decode((string) $item->raw_json, true);
            $raw = is_array($raw) ? $raw : [];
            $p = $pricing[$key] ?? null;
            $f = $fallback[$key] ?? null;

            $price = (float) ($p->selling_price ?? 0);
            $price = $price > 0 ? $price : (float) ($f->price ?? 0);
            $stock = $p !== null && $p->available_quantity !== null ? (int) $p->available_quantity : (int) ($f->ne_stock ?? 0);

            $out[$key] = [
                'sku' => $wanted[$key],
                'listing_id' => trim((string) $item->newegg_item_number),
                'title' => trim((string) ($raw['wesiteshorttitle'] ?? $raw['websiteshorttitle'] ?? '')) ?: trim((string) $item->title),
                'bullets' => (string) ($raw['bulletdescription'] ?? ''),
                'images' => (string) ($raw['itemimage'] ?? ''),
                'price' => $price,
                'msrp' => (float) ($p->msrp ?? 0),
                'stock' => $stock,
                'status' => (string) $item->status,
                'active' => $p !== null && (bool) $p->active,
                'upc' => trim((string) ($raw['upc'] ?? '')) ?: trim((string) $item->upc),
                'length' => $raw['itemlength'] ?? 0,
                'width' => $raw['itemwidth'] ?? 0,
                'height' => $raw['itemheight'] ?? 0,
                'weight' => ($raw['itemweight'] ?? 0) ?: $item->item_weight,
                'category' => (string) ($raw['subcategory'] ?? ''),
            ];
        }

        return $out;
    }
}
