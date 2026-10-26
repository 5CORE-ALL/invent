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

        // eBay 1: the seller-portal sync decides what is live (same as the Missing L total).
        // A saved buyer / seller link must not turn an ended, deleted or missing listing back to Listed.
        $trustLinks = empty(((array) ($cfg['listed'] ?? []))['hide_statuses']);

        $rows = $productMasters->map(function ($item) use ($shopifyData, $statusData, $nrValues, $listedMap, $idField, $trustLinks) {
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

            $nrRaw = ListingCountsEngine::lookupNrValue($nrValues, $childSku);
            $nrArray = is_array($nrRaw) ? $nrRaw : (json_decode((string) $nrRaw, true) ?: []);
            if (! is_array($nrArray)) {
                $nrArray = [];
            }
            if (! $item->buyer_link && ! empty($nrArray['buyer_link'])) {
                $item->buyer_link = $nrArray['buyer_link'];
            }
            if (! $item->seller_link && ! empty($nrArray['seller_link'])) {
                $item->seller_link = $nrArray['seller_link'];
            }

            $item->nr_req = ListingCountsEngine::nrReqFromDataView($nrRaw);
            if ($item->nr_req === 'REQ' && $status !== []) {
                $item->nr_req = ListingCountsEngine::nrReqFromDataView($status);
            }

            $listingId = ListingCountsEngine::listingIdFromMap($listedMap, $childSku);
            $item->page_created = ListingCountsEngine::isPageCreatedId($listingId);
            if ($item->page_created) {
                $listingId = '';
            }
            if ($listingId === '' && ! $item->page_created && $trustLinks) {
                $listingId = ListingCountsEngine::listingIdFromLinks(
                    $item->buyer_link,
                    $item->seller_link,
                    $status['buyer_link'] ?? null,
                    $status['seller_link'] ?? null,
                    $nrArray['buyer_link'] ?? null,
                    $nrArray['seller_link'] ?? null
                );
            }
            $idOrNull = $listingId !== '' ? $listingId : null;
            $item->{$idField} = $idOrNull;
            $item->listing_id = $idOrNull;
            // EbayTwo-style blades historically read eBay_item_id for Missing L / links
            $item->eBay_item_id = $idOrNull;
            $item->listed = $item->page_created ? 'Created' : ($listingId !== '' ? 'Listed' : 'Pending');
            if (! $item->buyer_link && $idOrNull && str_starts_with($idOrNull, 'http')) {
                $item->buyer_link = $idOrNull;
            }

            return $item;
        })->values();

        return LmpStdPrice::attach($rows);
    }
}
