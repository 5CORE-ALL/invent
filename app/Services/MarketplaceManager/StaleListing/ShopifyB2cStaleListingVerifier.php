<?php

namespace App\Services\MarketplaceManager\StaleListing;

use App\Models\ShopifySku;
use App\Services\ShopifyCatalogSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Shopify B2C is Listed when shopify_skus.variant_id is set and the variant's product is not archived / draft
 * (ChannelListingRegistry::listedShopify). The product catalog sync only adds and updates, so a variant that was
 * deleted on Shopify kept its variant_id forever and an archived product kept showing as Listed.
 *
 * Every stored variant is compared with the full Shopify product list (read page by page; a failed, short or
 * suspicious list changes nothing):
 *  - variant missing from the list AND variants/{id}.json answers 404: variant_id is blanked on the shopify_skus
 *    row (the row itself, its inventory and sold counts stay) and the dead shopify_catalog_variants row is removed.
 *  - variant present on an archived / draft product: shopify_catalog_products.status is brought up to date, which
 *    is what the Listed rule reads to leave the SKU out. variant_id is NOT touched (the DC archive / restore, price
 *    and inventory pushes and order matching still need it, and the catalog sync would write it back anyway).
 */
class ShopifyB2cStaleListingVerifier implements StaleListingVerifier
{
    private const NOT_LIVE_STATUSES = ['archived', 'draft'];

    private const MIN_LIVE_VARIANTS = 20;

    private const MAX_CLEAR_SHARE = 0.8;

    private const MAX_CONSECUTIVE_ERRORS = 5;

    /**
     * @return array{ok: bool, message: string, checked: int, cleared: int, kept: int, errors: int}
     */
    public function verify(): array
    {
        $out = ['ok' => false, 'message' => '', 'checked' => 0, 'cleared' => 0, 'kept' => 0, 'errors' => 0];

        if (! Schema::hasTable('shopify_skus') || ! Schema::hasColumn('shopify_skus', 'variant_id')) {
            $out['message'] = 'Shopify B2C: shopify_skus table not found.';

            return $out;
        }

        // Stored rows, grouped by variant id (one variant can sit on more than one row).
        $byVariant = [];
        $unreadable = 0;
        $rows = ShopifySku::query()
            ->whereNotNull('variant_id')->where('variant_id', '!=', '')->where('variant_id', '!=', '0')
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->get(['id', 'sku', 'variant_id']);
        foreach ($rows as $row) {
            if (stripos((string) $row->sku, 'PARENT') !== false) {
                continue;
            }
            $out['checked']++;
            $vid = trim((string) $row->variant_id);
            if (! ctype_digit($vid)) {
                $unreadable++;

                continue;
            }
            $byVariant[$vid][] = $row;
        }
        $out['kept'] = $unreadable;
        if ($byVariant === []) {
            $out['ok'] = true;
            $out['message'] = 'Shopify B2C: no listed SKUs to check.';

            return $out;
        }

        $index = app(ShopifyCatalogSyncService::class)->liveMainVariantIndex();
        if (! $index['complete']) {
            $out['errors'] = 1;
            $out['kept'] = $out['checked'];
            $out['message'] = 'Shopify B2C: could not read the Shopify product list ('.$index['message'].'). Nothing changed.';

            return $out;
        }
        $live = $index['variants'];
        if (count($live) < self::MIN_LIVE_VARIANTS) {
            $out['errors'] = 1;
            $out['kept'] = $out['checked'];
            $out['message'] = 'Shopify B2C: Shopify returned only '.count($live).' variant(s); list looks incomplete, nothing changed.';

            return $out;
        }

        $missing = [];
        foreach (array_keys($byVariant) as $vid) {
            if (! isset($live[(int) $vid])) {
                $missing[] = (string) $vid;
            }
        }
        if (count($missing) > self::MAX_CLEAR_SHARE * count($byVariant)) {
            $out['errors'] = 1;
            $out['kept'] = $out['checked'];
            $out['message'] = 'Shopify B2C: '.count($missing).' of '.count($byVariant).' stored variants are not in the Shopify list; too many to be right, nothing changed.';

            return $out;
        }

        // 1. Archived / draft products: refresh the status the Listed rule reads.
        $flagged = $this->syncProductStatus($byVariant, $live, $index['products']);
        $out['cleared'] += $flagged['newly_not_live'];

        // 2. Variants gone from the list: confirm each with a single 404 lookup.
        $liveSkus = [];
        foreach ($live as $v) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($v['sku']);
            if ($norm !== '') {
                $liveSkus[$norm] = true;
            }
        }

        $consecutiveErrors = 0;
        $aborted = false;
        $goneRows = 0;
        foreach ($missing as $vid) {
            $skuRows = $byVariant[$vid];
            try {
                // Same SKU on another live variant: the catalog sync repoints it, this is not a deleted listing.
                $moved = false;
                foreach ($skuRows as $row) {
                    if (isset($liveSkus[ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku)])) {
                        $moved = true;
                    }
                }
                if ($moved) {
                    $consecutiveErrors = 0;
                    $out['kept'] += count($skuRows);

                    continue;
                }

                $state = app(ShopifyCatalogSyncService::class)->mainVariantState($vid);
                if ($state['state'] === 'error') {
                    $out['errors'] += count($skuRows);
                    $out['kept'] += count($skuRows);
                    $consecutiveErrors++;
                    Log::warning('ShopifyB2cStaleListingVerifier: could not verify variant', ['variant_id' => $vid, 'message' => $state['message']]);
                    if ($consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                        $aborted = true;

                        break;
                    }

                    continue;
                }
                $consecutiveErrors = 0;

                if ($state['state'] === 'live') {
                    $out['kept'] += count($skuRows);

                    continue;
                }

                // 404: the variant no longer exists on Shopify.
                ShopifySku::query()->whereIn('id', array_map(static fn ($r) => $r->id, $skuRows))->where('variant_id', $vid)->update(['variant_id' => null]);
                if (Schema::hasTable('shopify_catalog_variants')) {
                    DB::table('shopify_catalog_variants')->where('store', 'main')->where('shopify_variant_id', $vid)->delete();
                }
                $out['cleared'] += count($skuRows);
                $goneRows += count($skuRows);
                Log::info('ShopifyB2cStaleListingVerifier: variant deleted on Shopify, variant_id cleared', [
                    'variant_id' => $vid,
                    'skus' => array_map(static fn ($r) => (string) $r->sku, $skuRows),
                ]);
            } catch (\Throwable $e) {
                $out['errors'] += count($skuRows);
                $out['kept'] += count($skuRows);
                Log::warning('ShopifyB2cStaleListingVerifier: update failed', ['variant_id' => $vid, 'error' => $e->getMessage()]);
            }
        }

        $out['ok'] = ! $aborted;
        $out['kept'] = max(0, $out['checked'] - $out['cleared']);
        $out['message'] = "Shopify B2C: checked {$out['checked']} listed SKU row(s): {$goneRows} deleted on Shopify (now Missing L), "
            ."{$flagged['newly_not_live']} newly archived/draft (now Missing L), {$flagged['restored']} back to active, "
            ."{$flagged['not_live']} archived/draft in total, {$out['errors']} could not be verified"
            .($flagged['unflaggable'] > 0 ? ", {$flagged['unflaggable']} archived/draft not in the synced catalog (run the Shopify B2C catalog step)" : '')
            .($aborted ? '. Stopped after 5 failures in a row; run again later.' : '.');

        return $out;
    }

    /**
     * Write the live product status onto shopify_catalog_products for every product that holds a stored variant.
     *
     * @param  array<string, list<object>>  $byVariant
     * @param  array<int, array{product_id: int, sku: string}>  $live
     * @param  array<int, string>  $liveProducts  product id => status
     * @return array{newly_not_live: int, restored: int, not_live: int, unflaggable: int}
     */
    private function syncProductStatus(array $byVariant, array $live, array $liveProducts): array
    {
        $res = ['newly_not_live' => 0, 'restored' => 0, 'not_live' => 0, 'unflaggable' => 0];
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return $res;
        }

        $vids = array_values(array_filter(array_keys($byVariant), static fn ($vid) => isset($live[(int) $vid])));
        foreach (array_chunk($vids, 500) as $chunk) {
            try {
                $catalog = DB::table('shopify_catalog_variants as v')
                    ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
                    ->where('v.store', 'main')
                    ->where('p.store', 'main')
                    ->whereIn('v.shopify_variant_id', $chunk)
                    ->get(['v.shopify_variant_id as vid', 'p.id as product_row_id', 'p.status as status'])
                    ->keyBy(static fn ($r) => (string) $r->vid);

                $updates = [];
                foreach ($chunk as $vid) {
                    $liveStatus = strtolower(trim((string) ($liveProducts[$live[(int) $vid]['product_id']] ?? '')));
                    if ($liveStatus === '') {
                        continue; // product status not in the response: leave it
                    }
                    $notLive = in_array($liveStatus, self::NOT_LIVE_STATUSES, true);
                    $row = $catalog->get($vid);
                    if ($notLive) {
                        $res['not_live'] += count($byVariant[$vid]);
                    }
                    if ($row === null) {
                        if ($notLive) {
                            $res['unflaggable'] += count($byVariant[$vid]);
                        }

                        continue;
                    }
                    $stored = strtolower(trim((string) $row->status));
                    if ($stored === $liveStatus) {
                        continue;
                    }
                    $updates[(int) $row->product_row_id] = $liveStatus;
                    $wasNotLive = in_array($stored, self::NOT_LIVE_STATUSES, true);
                    if ($notLive && ! $wasNotLive) {
                        $res['newly_not_live'] += count($byVariant[$vid]);
                        Log::info('ShopifyB2cStaleListingVerifier: product is not live on Shopify', [
                            'variant_id' => $vid,
                            'status' => $liveStatus,
                            'skus' => array_map(static fn ($r) => (string) $r->sku, $byVariant[$vid]),
                        ]);
                    } elseif (! $notLive && $wasNotLive) {
                        $res['restored'] += count($byVariant[$vid]);
                    }
                }
                foreach ($updates as $productRowId => $status) {
                    DB::table('shopify_catalog_products')->where('id', $productRowId)->update(['status' => $status]);
                }
            } catch (\Throwable $e) {
                Log::warning('ShopifyB2cStaleListingVerifier: status refresh failed', ['error' => $e->getMessage()]);
            }
        }

        return $res;
    }
}
