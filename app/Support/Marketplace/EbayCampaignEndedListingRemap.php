<?php

namespace App\Support\Marketplace;

use App\Models\EbayMetric;
use App\Services\Support\EbaySellInventoryListingResolver;
use Illuminate\Support\Facades\DB;

/**
 * When a Promoted Listing ends and the SKU is relisted, ebay_metrics keeps the
 * new item_id but ebay_campaign_ads still points at the ended one.
 */
final class EbayCampaignEndedListingRemap
{
    /**
     * Live eBay item_id for a SKU (prefer a non-ended metrics row, then Inventory API).
     */
    public static function liveItemIdForSku(?string $sku, string $fallback = '', ?string $token = null): string
    {
        $sku = trim((string) $sku);
        $fallback = trim($fallback);
        if ($sku === '') {
            return $fallback;
        }

        $live = EbayListingEnded::preferredRow(EbayMetric::class, $sku);
        $fromMetrics = trim((string) ($live?->item_id ?? ''));
        if ($fromMetrics !== '' && ! EbayListingEnded::isEnded($live->listing_status ?? null)) {
            return $fromMetrics;
        }

        if ($token) {
            $fromApi = EbaySellInventoryListingResolver::resolveListingIdBySku($token, $sku);
            if (is_string($fromApi) && $fromApi !== '') {
                return $fromApi;
            }
        }

        return $fromMetrics !== '' ? $fromMetrics : $fallback;
    }

    /**
     * Point the campaign-ads row at the new listing id. Drops the stale ENDED
     * row when the live listing already has its own row.
     *
     * @return string listing_id to use after remap
     */
    public static function remapAdsRow(string $adsTable, string $oldId, string $newId, ?string $sku = null): string
    {
        $oldId = trim($oldId);
        $newId = trim($newId);
        if ($oldId === '' || $newId === '' || $oldId === $newId) {
            return $oldId !== '' ? $oldId : $newId;
        }

        $existingNew = DB::table($adsTable)->where('listing_id', $newId)->first();
        if ($existingNew) {
            DB::table($adsTable)->where('listing_id', $oldId)->delete();

            return $newId;
        }

        $payload = [
            'listing_id' => $newId,
            'campaign_id' => null,
            'campaign_name' => null,
            'funding_strategy' => null,
            'campaign_status' => null,
            'ad_id' => null,
            'bid_percentage' => null,
            'updated_at' => now(),
        ];
        if ($sku !== null && trim($sku) !== '') {
            $payload['sku'] = trim($sku);
        }

        DB::table($adsTable)->where('listing_id', $oldId)->update($payload);

        return $newId;
    }

    /**
     * Remap every ENDED campaign-ads row whose SKU has a newer live item_id.
     */
    public static function remapEndedRows(string $adsTable): int
    {
        $ended = DB::table($adsTable)
            ->whereRaw("UPPER(TRIM(COALESCE(campaign_status, ''))) = 'ENDED'")
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['listing_id', 'sku']);

        if ($ended->isEmpty()) {
            return 0;
        }

        $normSkus = $ended->pluck('sku')
            ->map(fn ($s) => strtoupper(trim((string) $s)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $placeholders = implode(',', array_fill(0, count($normSkus), '?'));
        $metrics = EbayMetric::query()
            ->whereNotNull('item_id')
            ->whereRaw('UPPER(TRIM(sku)) IN ('.$placeholders.')', $normSkus)
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($m) => strtoupper(trim((string) $m->sku)));

        $changed = 0;
        foreach ($ended as $row) {
            $oldId = trim((string) $row->listing_id);
            $sku = trim((string) $row->sku);
            if ($oldId === '' || $sku === '') {
                continue;
            }
            $live = EbayListingEnded::preferLiveMetric($metrics[strtoupper($sku)] ?? []);
            $liveId = trim((string) ($live?->item_id ?? ''));
            if ($liveId === '' || $liveId === $oldId || EbayListingEnded::isEnded($live->listing_status ?? null)) {
                continue;
            }
            self::remapAdsRow($adsTable, $oldId, $liveId, $sku);
            $changed++;
        }

        return $changed;
    }
}
