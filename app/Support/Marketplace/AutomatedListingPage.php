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
    public static function rows(string $channelKey, bool $withPrice = true, bool $syncCounts = true): Collection
    {
        $rows = self::buildRows($channelKey);

        if ($syncCounts && $rows->isNotEmpty()) {
            // The listing page and /missing-listing must show the same numbers, so every
            // time a listing page loads its rows, the outer counts are brought in line.
            try {
                ListingChannelCounts::syncChannelCounts($channelKey, self::countsFromRows($rows));
            } catch (\Throwable $e) {
                // counts sync must never break a listing page
            }
        }

        return $withPrice ? LmpStdPrice::attach($rows) : $rows;
    }

    /**
     * REQ / NRL / Listed / Missing L using the exact rules the listing pages use
     * (CP Master SKUs with stock, PARENT rows left out, NRL never counts as Listed or Missing L,
     * pages created but not yet live are neither Listed nor Missing L).
     *
     * @param  iterable<int, object>  $rows
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int, MissingL: int}
     */
    public static function countsFromRows(iterable $rows, bool $requirePositiveInv = true): array
    {
        $req = 0;
        $nrl = 0;
        $listed = 0;
        $missing = 0;

        foreach ($rows as $item) {
            $sku = trim((string) ($item->sku ?? ''));
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            if ($requirePositiveInv && (float) ($item->INV ?? 0) <= 0) {
                continue;
            }
            if (($item->nr_req ?? 'REQ') === 'NR') {
                $nrl++;

                continue;
            }
            $req++;
            if (! empty($item->page_created)) {
                continue;
            }
            if ((string) ($item->listed ?? '') === 'Listed') {
                $listed++;
            } else {
                $missing++;
            }
        }

        return ['REQ' => $req, 'NRL' => $nrl, 'Listed' => $listed, 'Pending' => $missing, 'MissingL' => $missing];
    }

    /**
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int, MissingL: int}
     */
    public static function counts(string $channelKey, bool $requirePositiveInv = true): array
    {
        return self::countsFromRows(self::rows($channelKey, false, false), $requirePositiveInv);
    }

    /**
     * @return Collection<int, ProductMaster>
     */
    private static function buildRows(string $channelKey): Collection
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

        return $rows;
    }
}
