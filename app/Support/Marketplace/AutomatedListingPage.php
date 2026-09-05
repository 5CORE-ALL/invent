<?php

namespace App\Support\Marketplace;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;

/**
 * Build listing-page row payloads (EbayTwo pattern) for a registry channel key.
 */
class AutomatedListingPage
{
    /**
     * @return Collection<int, ProductMaster>
     */
    public static function rows(string $channelKey): Collection
    {
        $cfg = ChannelListingRegistry::get($channelKey);
        if ($cfg === null) {
            return collect();
        }

        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->filter()->values()->all();
        $shopifyData = ListingCountsEngine::shopifyMap($skus);

        $statusClass = $cfg['status'] ?? null;
        $statusData = collect();
        if ($statusClass && class_exists($statusClass)) {
            $statusQuery = $statusClass::query()->whereNotNull('sku')->where('sku', '!=', '');
            // Wayfair status SKUs often differ by spaces/hyphens from CP Master — load all and match normalized.
            if ($statusClass !== \App\Models\WayfairListingStatus::class) {
                $statusQuery->whereIn('sku', $skus);
            }
            foreach ($statusQuery->get() as $row) {
                $lower = strtolower(trim((string) $row->sku));
                $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                if ($lower !== '' && ! $statusData->has($lower)) {
                    $statusData[$lower] = $row;
                }
                if ($norm !== '' && ! $statusData->has($norm)) {
                    $statusData[$norm] = $row;
                }
            }
        }

        $dataView = $cfg['dataView'] ?? null;
        $nrValues = ($dataView && class_exists($dataView))
            ? ListingCountsEngine::loadNrValues($dataView, $skus)
            : collect();

        $listedMap = ChannelListingRegistry::loadListedIds($cfg, $skus);
        $idField = (string) ($cfg['id_field'] ?? 'listing_id');

        return $productMasters->map(function ($item) use ($shopifyData, $statusData, $nrValues, $listedMap, $idField) {
            $childSku = (string) $item->sku;
            $skuLower = strtolower(trim($childSku));

            $shopify = ListingCountsEngine::shopifyRow($shopifyData, $childSku);
            $item->INV = ListingCountsEngine::shopifyInv($shopify);
            $item->L30 = $shopify?->quantity ?? 0;

            $item->buyer_link = null;
            $item->seller_link = null;
            $status = [];
            $statusRow = $statusData[$skuLower]
                ?? $statusData[ShopifySku::normalizeSkuForShopifyLookup($childSku)]
                ?? null;
            if ($statusRow) {
                $statusValue = $statusRow->value;
                $status = is_array($statusValue)
                    ? $statusValue
                    : (json_decode($statusValue, true) ?? []);
                $item->buyer_link = $status['buyer_link'] ?? null;
                $item->seller_link = $status['seller_link'] ?? null;
            }

            $item->nr_req = ListingCountsEngine::nrReqFromDataView(
                ListingCountsEngine::lookupNrValue($nrValues, $childSku)
            );
            if ($item->nr_req === 'REQ' && $status !== []) {
                $item->nr_req = ListingCountsEngine::nrReqFromDataView($status);
            }

            $listingId = ListingCountsEngine::listingIdFromMap($listedMap, $childSku);
            $idOrNull = $listingId !== '' ? $listingId : null;
            $item->{$idField} = $idOrNull;
            $item->listing_id = $idOrNull;
            // EbayTwo-style blades historically read eBay_item_id for Missing L / links
            $item->eBay_item_id = $idOrNull;
            $item->listed = $listingId !== '' ? 'Listed' : 'Pending';
            if (! $item->buyer_link && $idOrNull && str_starts_with($idOrNull, 'http')) {
                $item->buyer_link = $idOrNull;
            }

            return $item;
        })->values();
    }
}
