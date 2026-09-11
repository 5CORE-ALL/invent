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
     *
     * @param  class-string  $metricClass
     */
    public static function liveItemIdForSku(
        ?string $sku,
        string $fallback = '',
        ?string $token = null,
        string $metricClass = EbayMetric::class,
    ): string {
        $sku = trim((string) $sku);
        $fallback = trim($fallback);
        if ($sku === '') {
            return $fallback;
        }

        $live = EbayListingEnded::preferredRow($metricClass, $sku);
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
     *
     * @param  class-string  $metricClass
     */
    public static function remapEndedRows(string $adsTable, string $metricClass = EbayMetric::class): int
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
        $metrics = $metricClass::query()
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

    public static function isEndedListingError(string $msg): bool
    {
        $m = strtolower($msg);

        return str_contains($m, 'has ended')
            || str_contains($m, 'is invalid')
            || str_contains($m, 'invalid or has ended')
            || str_contains($m, 'no longer active')
            || EbayListingEnded::looksEndedError($msg);
    }

    /**
     * Skip RUNNING ads; remap ENDED SKUs to the current live listing id.
     *
     * @param  class-string  $metricClass
     * @return array{listing_id: string, ad: ?object, metric: ?object, sku: string, skip: ?string}
     */
    public static function resolveEnrollListing(
        string $adsTable,
        string $metricClass,
        string $requestedId,
        ?object $adRow,
        ?object $metric,
        ?string $token,
    ): array {
        $lid = (string) $requestedId;
        $sku = trim((string) ($adRow?->sku ?? $metric?->sku ?? ''));
        $status = strtoupper(trim((string) ($adRow?->campaign_status ?? '')));
        if (in_array($status, ['RUNNING', 'PAUSED', 'SYSTEM_PAUSED'], true)) {
            return [
                'listing_id' => $lid,
                'ad' => $adRow,
                'metric' => $metric,
                'sku' => $sku,
                'skip' => 'Already in a '.$status.' campaign',
            ];
        }

        $needsLive = in_array($status, ['ENDED', 'INACTIVE'], true)
            || ! $metric
            || EbayListingEnded::isEnded($metric->listing_status ?? null);
        if ($needsLive && $sku !== '') {
            $liveId = self::liveItemIdForSku($sku, $lid, $token, $metricClass);
            if ($liveId !== '' && $liveId !== $lid) {
                $lid = self::remapAdsRow($adsTable, (string) $requestedId, $liveId, $sku);
                $adRow = DB::table($adsTable)->where('listing_id', $lid)->first();
                $metric = $metricClass::query()->where('item_id', $lid)->first()
                    ?: EbayListingEnded::preferredRow($metricClass, $sku);
                $status = strtoupper(trim((string) ($adRow?->campaign_status ?? '')));
                if (in_array($status, ['RUNNING', 'PAUSED', 'SYSTEM_PAUSED'], true)) {
                    return [
                        'listing_id' => $lid,
                        'ad' => $adRow,
                        'metric' => $metric,
                        'sku' => $sku,
                        'skip' => 'SKU already '.$status.' on listing '.$lid,
                    ];
                }
            }
        }

        return [
            'listing_id' => $lid,
            'ad' => $adRow,
            'metric' => $metric,
            'sku' => $sku,
            'skip' => null,
        ];
    }
}
