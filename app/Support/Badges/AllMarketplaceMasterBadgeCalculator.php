<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Models\BadgeData;
use Illuminate\Support\Facades\Cache;

class AllMarketplaceMasterBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'all-marketplace-master';

    public const NMAP_CACHE_KEY = 'amm_sidebar_nmap';

    public const MISSING_L_CACHE_KEY = 'amm_sidebar_missing_l';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        // Uses pre-calculated channel_master_calculated_data when available.
    }

    /**
     * @return array<string, int|float|string|null>
     */
    public static function calculate(): array
    {
        $totals = app(ChannelMasterController::class)->getAllMarketplaceMasterBadgeTotals();
        if (isset($totals['nmap'])) {
            Cache::put(self::NMAP_CACHE_KEY, (int) $totals['nmap'], now()->addDay());
        }
        if (isset($totals['missing_l'])) {
            Cache::put(self::MISSING_L_CACHE_KEY, (int) $totals['missing_l'], now()->addDay());
        }

        return $totals;
    }

    /**
     * Persist Missing L + N Map from the same channel rows the active channel page sums.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{missing_l: int, nmap: int}
     */
    public static function syncNmapFromChannelRows(array $rows): array
    {
        return self::syncMissingLAndNmapFromChannelRows($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{missing_l: int, nmap: int}
     */
    public static function syncMissingLAndNmapFromChannelRows(array $rows): array
    {
        $missTotal = 0.0;
        $nmapTotal = 0.0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $missTotal += self::rowNumber($row, 'Miss');
            $nmapTotal += self::rowNumber($row, 'NMap');
        }

        $missingL = (int) round($missTotal);
        $nmap = (int) round($nmapTotal);

        Cache::put(self::MISSING_L_CACHE_KEY, $missingL, now()->addDay());
        Cache::put(self::NMAP_CACHE_KEY, $nmap, now()->addDay());

        $existing = BadgeData::dataForPage(self::PAGE_NAME, []);
        $existing['missing_l'] = $missingL;
        $existing['nmap'] = $nmap;
        BadgeData::saveForPage(self::PAGE_NAME, $existing);

        return [
            'missing_l' => $missingL,
            'nmap' => $nmap,
        ];
    }

    /**
     * Sidebar Missing Listing badge — same Missing L total as /missing-listing
     * (sum of each channel listing page Pending / Missing L).
     */
    public static function missingLCountForSidebar(): int
    {
        try {
            return \App\Support\Marketplace\ListingChannelCounts::totalMissingL(true);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Sidebar Missing Mapping badge — same N Map total as /all-marketplace-master. */
    public static function nmapCountForSidebar(): int
    {
        try {
            $cached = Cache::get(self::NMAP_CACHE_KEY);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // File cache dirs may be missing mid-request after optimize:clear.
        }

        try {
            return (int) round((float) (BadgeData::dataForPage(self::PAGE_NAME, ['nmap' => 0])['nmap'] ?? 0));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowNumber(array $row, string $key): float
    {
        if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
            return 0.0;
        }

        $raw = $row[$key];
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $raw);
        if ($cleaned === '' || $cleaned === '-') {
            return 0.0;
        }

        return (float) $cleaned;
    }
}
