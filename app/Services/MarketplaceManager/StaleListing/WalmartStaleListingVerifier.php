<?php

namespace App\Services\MarketplaceManager\StaleListing;

use App\Models\ShopifySku;
use App\Models\WalmartMetrics;
use App\Models\WalmartPriceData;
use App\Services\WalmartApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Walmart "Listed" (ChannelListingRegistry::listedWalmart) is:
 *   - walmart_price_data.item_id (non-empty and not just the SKU), and only when no SKU has one,
 *   - walmart_metrics.price > 0.
 * The "Fetch listings" step `walmart:fetch-listed-prices` only upserts walmart_pricing, which this rule does
 * not read, and nothing in the app writes walmart_price_data (sheet upload tables). So both sources keep
 * their values after Walmart drops an item.
 *
 * Walmart has only the paged GET /v3/items list in this app. The whole list is read and every stored SKU is
 * compared with it; a SKU that is absent from a complete list (by SKU or item id) is gone. Then its
 * walmart_price_data.item_id is blanked and its walmart_metrics.price is set to 0, so it shows on Missing L.
 * A failed, short or partial list changes nothing.
 */
class WalmartStaleListingVerifier implements StaleListingVerifier
{
    private const MIN_LIVE_ITEMS = 20;

    private const MIN_FOR_GUARDS = 20;

    private const MAX_GONE_SHARE = 0.8;

    public function __construct(
        protected WalmartApiService $walmart
    ) {}

    public function verify(): array
    {
        $out = ['ok' => false, 'message' => '', 'checked' => 0, 'cleared' => 0, 'kept' => 0, 'errors' => 0];

        $stored = $this->storedListings();
        if ($stored === []) {
            $out['ok'] = true;
            $out['message'] = 'No Walmart SKU is marked Listed; nothing to check.';

            return $out;
        }

        $reportedTotal = null;
        try {
            $items = $this->walmart->fetchAllItems(function (array $page, int $pageNum, ?int $totalItems) use (&$reportedTotal) {
                if ($totalItems !== null) {
                    $reportedTotal = $totalItems;
                }
            });
        } catch (\Throwable $e) {
            Log::warning('WalmartStaleListingVerifier: item list failed', ['error' => $e->getMessage()]);
            $out['errors'] = 1;
            $out['message'] = 'Walmart item list could not be read, nothing was changed: '.mb_substr($e->getMessage(), 0, 200);

            return $out;
        }

        if ($reportedTotal === null) {
            $out['errors'] = 1;
            $out['message'] = 'Walmart did not say how many items it has, so the list cannot be confirmed complete; nothing was changed.';

            return $out;
        }
        if (count($items) < $reportedTotal) {
            $out['errors'] = 1;
            $out['message'] = 'Walmart item list is incomplete ('.count($items)." of {$reportedTotal}); nothing was changed.";

            return $out;
        }
        if (count($items) < self::MIN_LIVE_ITEMS) {
            $out['errors'] = 1;
            $out['message'] = 'Walmart item list is too short ('.count($items).' item(s)) to trust; nothing was changed.';

            return $out;
        }

        $liveSkus = [];
        $liveCompact = [];
        $liveIds = [];
        foreach ($items as $item) {
            $sku = strtoupper(trim((string) ($item['sku'] ?? '')));
            if ($sku !== '') {
                $liveSkus[$sku] = true;
                $compact = ShopifySku::compactSkuForLookup($sku);
                if ($compact !== '') {
                    $liveCompact[$compact] = true;
                }
            }
            foreach (['wpid', 'itemId', 'item_id'] as $field) {
                $id = strtoupper(trim((string) ($item[$field] ?? '')));
                if ($id !== '') {
                    $liveIds[$id] = true;
                }
            }
        }

        $gone = [];
        foreach ($stored as $key => $row) {
            $out['checked']++;
            $compact = ShopifySku::compactSkuForLookup($key);
            $onWalmart = isset($liveSkus[$key]) || ($compact !== '' && isset($liveCompact[$compact]));
            foreach ($row['item_ids'] as $id) {
                if (isset($liveIds[$id])) {
                    $onWalmart = true;
                }
            }
            if ($onWalmart) {
                $out['kept']++;
            } else {
                $gone[] = $key;
            }
        }

        if ($gone !== [] && $out['checked'] >= self::MIN_FOR_GUARDS && count($gone) / $out['checked'] > self::MAX_GONE_SHARE) {
            $out['errors'] = 1;
            $out['message'] = 'Over '.(int) (self::MAX_GONE_SHARE * 100).'% of the stored Walmart SKUs are missing from the item list ('
                .count($gone).' of '.$out['checked'].'); treated as unreliable and nothing was changed.';

            return $out;
        }

        foreach ($gone as $key) {
            try {
                if ($stored[$key]['price_data'] !== []) {
                    WalmartPriceData::query()->whereIn('id', $stored[$key]['price_data'])->update(['item_id' => null]);
                }
                if ($stored[$key]['metrics'] !== []) {
                    WalmartMetrics::query()->whereIn('id', $stored[$key]['metrics'])->update(['price' => 0]);
                }
                $out['cleared']++;
                Log::info('WalmartStaleListingVerifier: SKU no longer on Walmart, listed flag cleared', ['sku' => $stored[$key]['sku']]);
            } catch (\Throwable $e) {
                $out['errors']++;
                Log::warning('WalmartStaleListingVerifier: update failed', ['sku' => $stored[$key]['sku'], 'error' => $e->getMessage()]);
            }
        }

        $out['ok'] = true;
        $out['message'] = "Checked {$out['checked']} Walmart SKU(s) against ".count($items).' items on Walmart: '
            ."{$out['cleared']} no longer on Walmart (now Missing L), {$out['kept']} still there, {$out['errors']} could not be updated.";

        return $out;
    }

    /**
     * SKUs the Listed rule reads as listed, by upper-cased SKU, with the row ids that make them listed.
     *
     * @return array<string, array{sku: string, item_ids: list<string>, price_data: list<int>, metrics: list<int>}>
     */
    protected function storedListings(): array
    {
        $stored = [];
        $touch = function (string $sku) use (&$stored): ?string {
            $sku = trim($sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                return null;
            }
            $key = strtoupper($sku);
            $stored[$key] ??= ['sku' => $sku, 'item_ids' => [], 'price_data' => [], 'metrics' => []];

            return $key;
        };

        if (Schema::hasTable('walmart_price_data')) {
            WalmartPriceData::query()
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->whereNotNull('item_id')->where('item_id', '!=', '')
                ->select(['id', 'sku', 'item_id'])
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use ($touch, &$stored) {
                    foreach ($rows as $row) {
                        $itemId = trim((string) $row->item_id);
                        if ($itemId === '' || strcasecmp($itemId, trim((string) $row->sku)) === 0) {
                            continue;
                        }
                        $key = $touch((string) $row->sku);
                        if ($key === null) {
                            continue;
                        }
                        $stored[$key]['item_ids'][] = strtoupper($itemId);
                        $stored[$key]['price_data'][] = (int) $row->id;
                    }
                });
        }

        if (Schema::hasTable('walmart_metrics')) {
            WalmartMetrics::query()
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->where('price', '>', 0)
                ->select(['id', 'sku', 'price'])
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use ($touch, &$stored) {
                    foreach ($rows as $row) {
                        $key = $touch((string) $row->sku);
                        if ($key !== null) {
                            $stored[$key]['metrics'][] = (int) $row->id;
                        }
                    }
                });
        }

        return $stored;
    }
}
