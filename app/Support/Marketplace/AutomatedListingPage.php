<?php

namespace App\Support\Marketplace;

use App\Models\ProductMaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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

        $productMasters = ListingCountsEngine::productMasters();
        $skus = ListingCountsEngine::productSkus();
        $shopifyData = ListingCountsEngine::requestShopifyMap();

        $statusClass = $cfg['status'] ?? null;
        $statusData = collect();
        if ($statusClass && class_exists($statusClass) && Schema::hasTable((new $statusClass)->getTable())) {
            // Load all status SKUs — marketplace rows often differ by spaces/hyphens from CP Master.
            $statusClass::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get()
                ->each(function ($row) use ($statusData) {
                    $raw = trim((string) $row->sku);
                    if ($raw === '') {
                        return;
                    }
                    $keys = array_merge(
                        [strtolower($raw)],
                        ListingCountsEngine::skuIndexKeys($raw),
                        ListingCountsEngine::skuLookupKeys($raw)
                    );
                    foreach (array_unique(array_filter($keys)) as $key) {
                        if (! $statusData->has($key)) {
                            $statusData[$key] = $row;
                        }
                    }
                });
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
            $statusRow = $statusData[$skuLower] ?? null;
            if ($statusRow === null) {
                foreach (array_merge(
                    ListingCountsEngine::skuIndexKeys($childSku),
                    ListingCountsEngine::skuLookupKeys($childSku)
                ) as $key) {
                    if ($statusData->has($key)) {
                        $statusRow = $statusData->get($key);
                        break;
                    }
                }
            }
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
