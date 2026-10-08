<?php

namespace App\Services;

use App\Models\EbaySkuCompetitor;
use App\Models\ProductMaster;
use App\Support\Marketplace\EbayCompetitorVariationMatcher;
use App\Support\Marketplace\EbayShippingCostParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EbayCompetitorVariationFamilySync
{
    public function __construct(private EbayLivePriceFetcher $fetcher)
    {
    }

    /**
     * Opened SKU + LMP-linked SKUs + product_master siblings (PS WHLS WH / BLK, MS DBL 2PCS / 4PCS).
     *
     * @param  list<string>  $extraSkus
     * @return list<string>
     */
    public function resolveFamilySkus(string $sku, array $extraSkus = []): array
    {
        $skus = [];
        $add = function (?string $value) use (&$skus): void {
            $value = trim((string) $value);
            if ($value === '' || str_starts_with(strtoupper($value), 'PARENT ')) {
                return;
            }
            $norm = strtoupper(preg_replace('/\s+/', ' ', $value) ?? $value);
            foreach ($skus as $existing) {
                if (strtoupper(preg_replace('/\s+/', ' ', $existing) ?? $existing) === $norm) {
                    return;
                }
            }
            $skus[] = $value;
        };

        $add($sku);
        foreach ($extraSkus as $extra) {
            $add($extra);
        }

        if (! Schema::hasTable('product_master')) {
            return $skus;
        }

        try {
            $parent = ProductMaster::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                ->value('parent');
            $parent = trim((string) $parent);
            if ($parent === '') {
                return $skus;
            }

            $siblings = ProductMaster::query()
                ->whereRaw('UPPER(TRIM(parent)) = ?', [strtoupper($parent)])
                ->pluck('sku');
            foreach ($siblings as $sibling) {
                $add($sibling);
            }
        } catch (\Throwable $e) {
            Log::warning('EbayCompetitorVariationFamilySync: sibling lookup failed', [
                'sku' => $sku,
                'message' => $e->getMessage(),
            ]);
        }

        return $skus;
    }

    /**
     * Pull every variation BIN.
     * Product-type listings (Tripod / Floor) become one LMP row per option on $anchorSku.
     * Pack/color matches are also written onto sibling SKUs.
     *
     * @param  list<string>  $familySkus
     * @return array{assigned: array<string, array<string, mixed>>, variation_count: int}
     */
    public function syncListing(
        string $listingId,
        array $familySkus,
        ?string $productLink = null,
        string $marketplace = 'ebay',
        bool $dryRun = false,
        ?string $anchorSku = null
    ): array {
        $variations = $this->fetcher->fetchVariations($listingId);
        $priced = array_values(array_filter(
            $variations,
            fn ($variation) => (float) ($variation['price'] ?? 0) > 0
        ));

        $assigned = EbayCompetitorVariationMatcher::assignToSkus($priced, $familySkus);
        $anchorSku = trim((string) ($anchorSku ?: ($familySkus[0] ?? '')));

        $upserted = [];
        $variationCount = 0;

        if ($priced === []) {
            return ['assigned' => [], 'variation_count' => 0];
        }

        // Product-type listings (Tripod / Floor) keep every option on the opened SKU.
        // Pack listings (1PCS / 4PCS) do not: the 1-piece price must not be saved on the 4-piece SKU.
        $isPackListing = self::listingHasPackVariations($priced);
        if (! $isPackListing && count($priced) >= 2 && $anchorSku !== '') {
            $keepIds = [];
            foreach ($priced as $variation) {
                $live = $this->fetcher->liveFromVariation($listingId, $variation, $anchorSku);
                if (! $live || (float) ($live['total_price'] ?? 0) <= 0) {
                    continue;
                }
                $itemId = EbayCompetitorVariationMatcher::variationItemId($variation, $listingId);
                $keepIds[] = $itemId;
                if (! $dryRun) {
                    $this->upsertCompetitor($anchorSku, $itemId, $live, $marketplace, $productLink);
                }
                $variationCount++;
            }

            if (! $dryRun && $keepIds !== []) {
                $this->removeExpandedParentRows($listingId, $familySkus, $marketplace, $keepIds);
            }
        }

        foreach ($assigned as $familySku => $variation) {
            $live = $this->fetcher->liveFromVariation($listingId, $variation, $familySku);
            if (! $live || (float) ($live['total_price'] ?? 0) <= 0) {
                continue;
            }
            $itemId = count($priced) >= 2
                ? EbayCompetitorVariationMatcher::variationItemId($variation, $listingId)
                : $listingId;
            if (! $dryRun) {
                $this->upsertCompetitor($familySku, $itemId, $live, $marketplace, $productLink);
            }
            $upserted[$familySku] = $live;
            if ($variationCount === 0) {
                $variationCount = 1;
            }
        }

        if ($isPackListing && ! $dryRun) {
            $this->removeMismatchedPackRows($listingId, $familySkus, $priced, $marketplace);
        }

        if ($variationCount > 0) {
            Log::info('EbayCompetitorVariationFamilySync: pulled listing variations', [
                'listing_id' => $listingId,
                'anchor' => $anchorSku,
                'variation_count' => $variationCount,
                'skus' => array_keys($upserted),
            ]);
        }

        return [
            'assigned' => $upserted,
            'variation_count' => $variationCount,
        ];
    }

    /**
     * @param  list<string>  $familySkus
     * @param  list<string>  $keepItemIds
     */
    private function removeExpandedParentRows(
        string $listingId,
        array $familySkus,
        string $marketplace,
        array $keepItemIds
    ): void {
        $norms = array_values(array_unique(array_map(
            fn ($sku) => strtoupper(trim((string) $sku)),
            $familySkus
        )));
        if ($norms === []) {
            return;
        }

        EbaySkuCompetitor::query()
            ->where('marketplace', $marketplace)
            ->where('item_id', $listingId)
            ->whereRaw('UPPER(TRIM(sku)) in ('.implode(',', array_fill(0, count($norms), '?')).')', $norms)
            ->whereNotIn('item_id', $keepItemIds)
            ->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $priced
     */
    public static function listingHasPackVariations(array $priced): bool
    {
        foreach ($priced as $variation) {
            if (EbayCompetitorVariationMatcher::extractPackQty((string) ($variation['label'] ?? '')) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Item ids that must not stay on this SKU: other pack sizes, and the parent
     * listing row once the matching pack variation has its own id.
     *
     * @param  list<array<string, mixed>>  $priced
     * @return list<string>
     */
    public static function mismatchedPackItemIds(string $sku, string $listingId, array $priced): array
    {
        $pack = EbayCompetitorVariationMatcher::extractPackQty($sku);
        if ($pack === null) {
            return [];
        }

        $dropIds = [];
        $keepIds = [];
        foreach ($priced as $variation) {
            $qty = EbayCompetitorVariationMatcher::extractPackQty((string) ($variation['label'] ?? ''));
            $itemId = EbayCompetitorVariationMatcher::variationItemId($variation, $listingId);
            if ($qty === $pack) {
                $keepIds[] = $itemId;
                continue;
            }
            if ($qty !== null) {
                $dropIds[] = $itemId;
            }
        }

        if ($keepIds !== [] && ! in_array($listingId, $keepIds, true)) {
            $dropIds[] = $listingId;
        }

        return array_values(array_unique(array_filter($dropIds, fn ($id) => $id !== '')));
    }

    /**
     * @param  list<string>  $familySkus
     * @param  list<array<string, mixed>>  $priced
     */
    private function removeMismatchedPackRows(
        string $listingId,
        array $familySkus,
        array $priced,
        string $marketplace
    ): void {
        foreach ($familySkus as $familySku) {
            $dropIds = self::mismatchedPackItemIds((string) $familySku, $listingId, $priced);
            if ($dropIds === []) {
                continue;
            }

            EbaySkuCompetitor::query()
                ->where('marketplace', $marketplace)
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim((string) $familySku))])
                ->whereIn('item_id', $dropIds)
                ->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $live
     */
    private function upsertCompetitor(
        string $sku,
        string $itemId,
        array $live,
        string $marketplace,
        ?string $productLink
    ): void {
        $existing = EbaySkuCompetitor::query()
            ->where('marketplace', $marketplace)
            ->where('item_id', $itemId)
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
            ->first();

        $live = EbayShippingCostParser::preferExisting($live, $existing?->shipping_cost);

        $payload = [
            'sku' => $existing->sku ?? $sku,
            'item_id' => $itemId,
            'marketplace' => $marketplace,
            'price' => $live['price'],
            'shipping_cost' => $live['shipping_cost'],
            'total_price' => $live['total_price'],
            'product_title' => $live['title'] ?? $existing->product_title ?? null,
            'product_link' => $live['link'] ?? $existing->product_link ?? $productLink,
            'image' => $live['image'] ?? $existing->image ?? null,
        ];

        if ($existing) {
            $existing->update($payload);

            return;
        }

        $siblingIgnored = EbaySkuCompetitor::query()
            ->where('marketplace', $marketplace)
            ->where('item_id', $itemId)
            ->where(function ($q) {
                $q->where('ignored', 1)->orWhere('ignored', true);
            })
            ->exists();
        $payload['ignored'] = $siblingIgnored;

        EbaySkuCompetitor::create($payload);
    }
}
