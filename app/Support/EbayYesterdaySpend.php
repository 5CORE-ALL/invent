<?php

namespace App\Support;

use App\Models\ShopifySku;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yesterday (L1) eBay ad spend for the Y Spend column.
 *
 * Promoted listings: ebay_general_reports.ad_fees.
 * Keyword CPC: ebay_priority_reports.cpc_ad_fees_payout_currency.
 * A SKU total is both, matching the nightly ad_spend_l30 snapshot.
 * Dated report_range rows (Y-m-d) are the history the dot and chart read.
 */
class EbayYesterdaySpend
{
    /**
     * @param  iterable<int, object>  $rows
     */
    public static function attachToCampaignRows(iterable $rows): void
    {
        $maps = self::maps();
        foreach ($rows as $row) {
            $vals = self::forAdRow(
                (string) ($row->listing_id ?? ''),
                (string) ($row->campaign_id ?? ''),
                (string) ($row->funding_strategy ?? ''),
                $maps
            );
            $row->y_spend = $vals['y_spend'];
            $row->y_spend_prev = $vals['y_spend_prev'];
            $row->y_spend_prev_date = $vals['y_spend_prev_date'];
            $row->y_spend_source = $vals['source'];
        }
    }

    /**
     * @param  array<string, mixed>  $maps
     * @return array{y_spend: float, y_spend_prev: float|null, y_spend_prev_date: string|null, source: string}
     */
    public static function forAdRow(string $listingId, string $campaignId, string $funding, array $maps): array
    {
        $listingId = trim($listingId);
        $campaignId = trim($campaignId);
        if (self::isCpc($funding)) {
            $hasPrev = $campaignId !== '' && array_key_exists($campaignId, $maps['prev_campaign'] ?? []);

            return [
                'y_spend' => round((float) (($maps['l1_campaign'] ?? [])[$campaignId] ?? 0), 2),
                'y_spend_prev' => $hasPrev ? round((float) $maps['prev_campaign'][$campaignId], 2) : null,
                'y_spend_prev_date' => $hasPrev ? (($maps['prev_campaign_date'] ?? [])[$campaignId] ?? null) : null,
                'source' => 'campaign',
            ];
        }

        $hasPrev = $listingId !== '' && array_key_exists($listingId, $maps['prev_listing'] ?? []);

        return [
            'y_spend' => round((float) (($maps['l1_listing'] ?? [])[$listingId] ?? 0), 2),
            'y_spend_prev' => $hasPrev ? round((float) $maps['prev_listing'][$listingId], 2) : null,
            'y_spend_prev_date' => $hasPrev ? (($maps['prev_listing_date'] ?? [])[$listingId] ?? null) : null,
            'source' => 'listing',
        ];
    }

    /**
     * Keyword L1 (campaign name = SKU) plus promoted L1 for the listing.
     *
     * @param  array<string, mixed>  $maps
     * @return array{y_spend: float, y_spend_prev: float|null, y_spend_prev_date: string|null}
     */
    public static function forSku(string $sku, string $listingId, array $maps): array
    {
        $skuKey = ShopifySku::normalizeSkuForShopifyLookup($sku);
        $listingId = trim($listingId);
        if ($listingId === '0') {
            $listingId = '';
        }

        $kw = (float) (($maps['l1_sku'] ?? [])[$skuKey] ?? 0);
        $pmt = $listingId !== '' ? (float) (($maps['l1_listing'] ?? [])[$listingId] ?? 0) : 0.0;
        $kwPrev = ($skuKey !== '' && array_key_exists($skuKey, $maps['prev_sku'] ?? []))
            ? (float) $maps['prev_sku'][$skuKey] : null;
        $pmtPrev = ($listingId !== '' && array_key_exists($listingId, $maps['prev_listing'] ?? []))
            ? (float) $maps['prev_listing'][$listingId] : null;
        $kwDate = ($maps['prev_sku_date'] ?? [])[$skuKey] ?? null;
        $pmtDate = $listingId !== '' ? (($maps['prev_listing_date'] ?? [])[$listingId] ?? null) : null;
        $hasDateMaps = array_key_exists('prev_sku_date', $maps) || array_key_exists('prev_listing_date', $maps);

        if (! $hasDateMaps && ($kwPrev !== null || $pmtPrev !== null)) {
            return [
                'y_spend' => round($kw + $pmt, 2),
                'y_spend_prev' => round(($kwPrev ?? 0) + ($pmtPrev ?? 0), 2),
                'y_spend_prev_date' => null,
            ];
        }

        $dates = array_values(array_filter([$kwDate, $pmtDate]));
        if ($dates === []) {
            return [
                'y_spend' => round($kw + $pmt, 2),
                'y_spend_prev' => null,
                'y_spend_prev_date' => null,
            ];
        }

        $date = max($dates);
        $prev = 0.0;
        if ($kwDate === $date && $kwPrev !== null) {
            $prev += $kwPrev;
        }
        if ($pmtDate === $date && $pmtPrev !== null) {
            $prev += $pmtPrev;
        }

        return [
            'y_spend' => round($kw + $pmt, 2),
            'y_spend_prev' => round($prev, 2),
            'y_spend_prev_date' => $date,
        ];
    }

    public static function money(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        if (is_int($raw) || is_float($raw)) {
            return round((float) $raw, 2);
        }
        $n = preg_replace('/[^0-9.\-]/', '', (string) $raw);

        return round((float) ($n === '' || $n === '-' || $n === '.' ? 0 : $n), 2);
    }

    public static function dayBefore(): string
    {
        return Carbon::yesterday('America/Los_Angeles')->subDay()->toDateString();
    }

    /**
     * @return array<int, array{date: string, date_formatted: string, y_spend: float}>
     */
    public static function history(string $sku, string $listingId, string $campaignId, string $funding, string $mode, int $days): array
    {
        $end = Carbon::yesterday('America/Los_Angeles')->startOfDay();
        $start = $days > 0 ? $end->copy()->subDays($days - 1) : null;
        $byDate = [];
        $live = 0.0;

        if ($mode === 'row' && self::isCpc($funding)) {
            $byDate = self::datedCampaign(trim($campaignId), $start, $end);
            $live = self::liveCampaign(trim($campaignId));
        } elseif ($mode === 'row') {
            $byDate = self::datedListing(trim($listingId), $start, $end);
            $live = self::liveListing(trim($listingId));
        } else {
            $listingId = trim($listingId);
            if ($listingId === '' || $listingId === '0') {
                $listingId = self::listingIdForSku($sku);
            }
            $byDate = self::datedListing($listingId, $start, $end);
            foreach (self::datedSku($sku, $start, $end) as $date => $amount) {
                $byDate[$date] = round(($byDate[$date] ?? 0) + $amount, 2);
            }
            $live = round(self::liveListing($listingId) + self::liveSku($sku), 2);
        }

        $byDate[$end->toDateString()] = round($live, 2);
        if ($start) {
            $startKey = $start->toDateString();
            $endKey = $end->toDateString();
            $byDate = array_filter(
                $byDate,
                static fn ($amount, $date) => $date >= $startKey && $date <= $endKey,
                ARRAY_FILTER_USE_BOTH
            );
        }
        ksort($byDate);

        $out = [];
        foreach ($byDate as $key => $amount) {
            $out[] = [
                'date' => $key,
                'date_formatted' => Carbon::parse($key, 'America/Los_Angeles')->format('M d'),
                'y_spend' => round((float) $amount, 2),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function maps(): array
    {
        return Cache::remember('ebay1_y_spend_column_v1', 900, function () {
            $before = Carbon::yesterday('America/Los_Angeles')->toDateString();
            [$l1Listing] = self::splitListing(self::generalRows(['L1']), 'L1');
            [$l1Campaign, , $l1Sku] = self::splitPriority(self::priorityRows(['L1']), 'L1');
            [$prevListing, $prevListingDate] = self::latestListingBefore($before);
            [$prevCampaign, $prevCampaignDate, $prevSku, $prevSkuDate] = self::latestPriorityBefore($before);

            return [
                'l1_listing' => $l1Listing,
                'prev_listing' => $prevListing,
                'prev_listing_date' => $prevListingDate,
                'l1_campaign' => $l1Campaign,
                'prev_campaign' => $prevCampaign,
                'prev_campaign_date' => $prevCampaignDate,
                'l1_sku' => $l1Sku,
                'prev_sku' => $prevSku,
                'prev_sku_date' => $prevSkuDate,
                'prev_date' => $before,
            ];
        });
    }

    private static function isCpc(string $funding): bool
    {
        $funding = strtoupper(trim($funding));

        return $funding === 'COST_PER_CLICK' || $funding === 'CPC';
    }

    /**
     * @param  array<int, string>  $ranges
     * @return array<int, object>
     */
    private static function generalRows(array $ranges): array
    {
        if (! Schema::hasTable('ebay_general_reports') || $ranges === []) {
            return [];
        }

        return DB::table('ebay_general_reports')
            ->whereIn('report_range', $ranges)
            ->get(['report_range', 'listing_id', 'ad_fees'])
            ->all();
    }

    /**
     * @param  array<int, string>  $ranges
     * @return array<int, object>
     */
    private static function priorityRows(array $ranges): array
    {
        if (! Schema::hasTable('ebay_priority_reports') || $ranges === []) {
            return [];
        }

        return DB::table('ebay_priority_reports')
            ->whereIn('report_range', $ranges)
            ->get(['report_range', 'campaign_id', 'campaign_name', 'cpc_ad_fees_payout_currency'])
            ->all();
    }

    /**
     * Latest stored day before yesterday, per listing.
     *
     * @return array{0: array<string, float>, 1: array<string, string>}
     */
    private static function latestListingBefore(string $before): array
    {
        if (! Schema::hasTable('ebay_general_reports')) {
            return [[], []];
        }

        $from = Carbon::parse($before)->subDays(180)->toDateString();
        $best = [];
        DB::table('ebay_general_reports')
            ->where('report_range', '>=', $from)
            ->where('report_range', '<', $before)
            ->select(['listing_id', 'report_range', 'ad_fees'])
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$best) {
                foreach ($rows as $row) {
                    $id = trim((string) ($row->listing_id ?? ''));
                    $date = (string) ($row->report_range ?? '');
                    if ($id === '' || strlen($date) !== 10) {
                        continue;
                    }
                    $amount = self::money($row->ad_fees ?? 0);
                    if (! isset($best[$id]) || $date > $best[$id]['date']) {
                        $best[$id] = ['date' => $date, 'amount' => $amount];
                    } elseif ($date === $best[$id]['date']) {
                        $best[$id]['amount'] = round($best[$id]['amount'] + $amount, 2);
                    }
                }
            });

        $amounts = [];
        $dates = [];
        foreach ($best as $id => $row) {
            $amounts[$id] = $row['amount'];
            $dates[$id] = $row['date'];
        }

        return [$amounts, $dates];
    }

    /**
     * Latest stored day before yesterday, per CPC campaign and per SKU name.
     *
     * @return array{0: array<string, float>, 1: array<string, string>, 2: array<string, float>, 3: array<string, string>}
     */
    private static function latestPriorityBefore(string $before): array
    {
        if (! Schema::hasTable('ebay_priority_reports')) {
            return [[], [], [], []];
        }

        $from = Carbon::parse($before)->subDays(180)->toDateString();
        $campaignBest = [];
        $skuBest = [];
        DB::table('ebay_priority_reports')
            ->where('report_range', '>=', $from)
            ->where('report_range', '<', $before)
            ->select(['campaign_id', 'campaign_name', 'report_range', 'cpc_ad_fees_payout_currency'])
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$campaignBest, &$skuBest) {
                foreach ($rows as $row) {
                    $date = (string) ($row->report_range ?? '');
                    if (strlen($date) !== 10) {
                        continue;
                    }
                    $amount = self::money($row->cpc_ad_fees_payout_currency ?? 0);
                    self::keepLatest($campaignBest, trim((string) ($row->campaign_id ?? '')), $date, $amount);
                    self::keepLatest($skuBest, ShopifySku::normalizeSkuForShopifyLookup((string) ($row->campaign_name ?? '')), $date, $amount);
                }
            });

        return [
            ...self::splitBest($campaignBest),
            ...self::splitBest($skuBest),
        ];
    }

    /**
     * @param  array<string, array{date: string, amount: float}>  $best
     */
    private static function keepLatest(array &$best, string $key, string $date, float $amount): void
    {
        if ($key === '') {
            return;
        }
        if (! isset($best[$key]) || $date > $best[$key]['date']) {
            $best[$key] = ['date' => $date, 'amount' => $amount];
        } elseif ($date === $best[$key]['date']) {
            $best[$key]['amount'] = round($best[$key]['amount'] + $amount, 2);
        }
    }

    /**
     * @param  array<string, array{date: string, amount: float}>  $best
     * @return array{0: array<string, float>, 1: array<string, string>}
     */
    private static function splitBest(array $best): array
    {
        $amounts = [];
        $dates = [];
        foreach ($best as $key => $row) {
            $amounts[$key] = $row['amount'];
            $dates[$key] = $row['date'];
        }

        return [$amounts, $dates];
    }

    /**
     * @param  array<int, object>  $rows
     * @return array{0: array<string, float>, 1: array<string, float>}
     */
    private static function splitListing(array $rows, string $prevDate): array
    {
        $l1 = [];
        $prev = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row->listing_id ?? ''));
            if ($id === '') {
                continue;
            }
            $bucket = (string) ($row->report_range ?? '') === 'L1' ? 'l1' : 'prev';
            if ($bucket === 'prev' && (string) ($row->report_range ?? '') !== $prevDate) {
                continue;
            }
            $amount = self::money($row->ad_fees ?? 0);
            if ($bucket === 'l1') {
                $l1[$id] = round(($l1[$id] ?? 0) + $amount, 2);
            } else {
                $prev[$id] = round(($prev[$id] ?? 0) + $amount, 2);
            }
        }

        return [$l1, $prev];
    }

    /**
     * @param  array<int, object>  $rows
     * @return array{0: array<string, float>, 1: array<string, float>, 2: array<string, float>, 3: array<string, float>}
     */
    private static function splitPriority(array $rows, string $prevDate): array
    {
        $l1Campaign = [];
        $prevCampaign = [];
        $l1Sku = [];
        $prevSku = [];
        foreach ($rows as $row) {
            $isL1 = (string) ($row->report_range ?? '') === 'L1';
            if (! $isL1 && (string) ($row->report_range ?? '') !== $prevDate) {
                continue;
            }
            $amount = self::money($row->cpc_ad_fees_payout_currency ?? 0);
            $campaignId = trim((string) ($row->campaign_id ?? ''));
            if ($campaignId !== '') {
                if ($isL1) {
                    $l1Campaign[$campaignId] = round(($l1Campaign[$campaignId] ?? 0) + $amount, 2);
                } else {
                    $prevCampaign[$campaignId] = round(($prevCampaign[$campaignId] ?? 0) + $amount, 2);
                }
            }
            $sku = ShopifySku::normalizeSkuForShopifyLookup((string) ($row->campaign_name ?? ''));
            if ($sku === '') {
                continue;
            }
            if ($isL1) {
                $l1Sku[$sku] = round(($l1Sku[$sku] ?? 0) + $amount, 2);
            } else {
                $prevSku[$sku] = round(($prevSku[$sku] ?? 0) + $amount, 2);
            }
        }

        return [$l1Campaign, $prevCampaign, $l1Sku, $prevSku];
    }

    /**
     * @return array<string, float>
     */
    private static function datedListing(string $listingId, ?Carbon $start, Carbon $end): array
    {
        if ($listingId === '' || ! Schema::hasTable('ebay_general_reports')) {
            return [];
        }

        $query = DB::table('ebay_general_reports')
            ->where('listing_id', $listingId)
            ->whereRaw('CHAR_LENGTH(report_range) = 10')
            ->where('report_range', '<=', $end->toDateString());
        if ($start) {
            $query->where('report_range', '>=', $start->toDateString());
        }

        $out = [];
        foreach ($query->get(['report_range', 'ad_fees']) as $row) {
            $date = (string) $row->report_range;
            $out[$date] = round(($out[$date] ?? 0) + self::money($row->ad_fees), 2);
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    private static function datedCampaign(string $campaignId, ?Carbon $start, Carbon $end): array
    {
        if ($campaignId === '' || ! Schema::hasTable('ebay_priority_reports')) {
            return [];
        }

        $query = DB::table('ebay_priority_reports')
            ->where('campaign_id', $campaignId)
            ->whereRaw('CHAR_LENGTH(report_range) = 10')
            ->where('report_range', '<=', $end->toDateString());
        if ($start) {
            $query->where('report_range', '>=', $start->toDateString());
        }

        $out = [];
        foreach ($query->get(['report_range', 'cpc_ad_fees_payout_currency']) as $row) {
            $date = (string) $row->report_range;
            $out[$date] = round(($out[$date] ?? 0) + self::money($row->cpc_ad_fees_payout_currency), 2);
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    private static function datedSku(string $sku, ?Carbon $start, Carbon $end): array
    {
        $skuKey = ShopifySku::normalizeSkuForShopifyLookup($sku);
        $raw = strtoupper(trim($sku));
        if ($skuKey === '' || ! Schema::hasTable('ebay_priority_reports')) {
            return [];
        }

        $query = DB::table('ebay_priority_reports')
            ->where(function ($q) use ($raw, $skuKey) {
                $q->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$raw])
                    ->orWhereRaw('UPPER(TRIM(campaign_name)) = ?', [$skuKey]);
            })
            ->whereRaw('CHAR_LENGTH(report_range) = 10')
            ->where('report_range', '<=', $end->toDateString());
        if ($start) {
            $query->where('report_range', '>=', $start->toDateString());
        }

        $out = [];
        foreach ($query->get(['report_range', 'campaign_name', 'cpc_ad_fees_payout_currency']) as $row) {
            if (ShopifySku::normalizeSkuForShopifyLookup((string) $row->campaign_name) !== $skuKey
                && strtoupper(trim((string) $row->campaign_name)) !== $raw) {
                continue;
            }
            $date = (string) $row->report_range;
            $out[$date] = round(($out[$date] ?? 0) + self::money($row->cpc_ad_fees_payout_currency), 2);
        }

        return $out;
    }

    private static function liveListing(string $listingId): float
    {
        if ($listingId === '' || ! Schema::hasTable('ebay_general_reports')) {
            return 0.0;
        }

        $sum = 0.0;
        $rows = DB::table('ebay_general_reports')
            ->where('report_range', 'L1')
            ->where('listing_id', $listingId)
            ->get(['ad_fees']);
        foreach ($rows as $row) {
            $sum += self::money($row->ad_fees);
        }

        return round($sum, 2);
    }

    private static function liveCampaign(string $campaignId): float
    {
        if ($campaignId === '' || ! Schema::hasTable('ebay_priority_reports')) {
            return 0.0;
        }

        $sum = 0.0;
        $rows = DB::table('ebay_priority_reports')
            ->where('report_range', 'L1')
            ->where('campaign_id', $campaignId)
            ->get(['cpc_ad_fees_payout_currency']);
        foreach ($rows as $row) {
            $sum += self::money($row->cpc_ad_fees_payout_currency);
        }

        return round($sum, 2);
    }

    private static function liveSku(string $sku): float
    {
        $skuKey = ShopifySku::normalizeSkuForShopifyLookup($sku);
        $raw = strtoupper(trim($sku));
        if ($skuKey === '' || ! Schema::hasTable('ebay_priority_reports')) {
            return 0.0;
        }

        $sum = 0.0;
        $rows = DB::table('ebay_priority_reports')
            ->where('report_range', 'L1')
            ->where(function ($q) use ($raw, $skuKey) {
                $q->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$raw])
                    ->orWhereRaw('UPPER(TRIM(campaign_name)) = ?', [$skuKey]);
            })
            ->get(['campaign_name', 'cpc_ad_fees_payout_currency']);
        foreach ($rows as $row) {
            if (ShopifySku::normalizeSkuForShopifyLookup((string) $row->campaign_name) !== $skuKey
                && strtoupper(trim((string) $row->campaign_name)) !== $raw) {
                continue;
            }
            $sum += self::money($row->cpc_ad_fees_payout_currency);
        }

        return round($sum, 2);
    }

    private static function listingIdForSku(string $sku): string
    {
        $raw = strtoupper(trim($sku));
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($raw === '' || ! Schema::hasTable('ebay_metrics')) {
            return '';
        }

        $itemId = DB::table('ebay_metrics')
            ->where(function ($q) use ($raw, $norm) {
                $q->whereRaw('UPPER(TRIM(sku)) = ?', [$raw]);
                if ($norm !== '' && $norm !== $raw) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$norm]);
                }
            })
            ->value('item_id');

        $itemId = trim((string) $itemId);

        return $itemId === '0' ? '' : $itemId;
    }
}
