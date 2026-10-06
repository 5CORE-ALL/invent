<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Models\BadgeData;
use App\Models\ChannelMaster;
use App\Models\ChannelMasterCalculatedData;
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
    /**
     * COGS-weighted NROI of Active channel_master rows.
     * Same basis as the Active Channel N ROI column (Temu is GROI% − Ads%).
     */
    public static function activeChannelNroiPercent(): ?float
    {
        $activeKeys = [];
        foreach (ChannelMaster::query()->whereRaw('LOWER(TRIM(status)) = ?', ['active'])->pluck('channel') as $name) {
            $key = self::snapshotKey((string) $name);
            if ($key !== '') {
                $activeKeys[$key] = true;
            }
        }
        if ($activeKeys === []) {
            return null;
        }

        $weighted = 0.0;
        $weight = 0.0;
        foreach (ChannelMasterCalculatedData::query()->get(['channel', 'n_roi', 'cogs']) as $row) {
            $key = self::snapshotKey((string) $row->channel);
            if ($key === '' || ! isset($activeKeys[$key])) {
                continue;
            }
            $cogs = (float) $row->cogs;
            if ($cogs <= 0) {
                continue;
            }
            $weighted += (float) $row->n_roi * $cogs;
            $weight += $cogs;
        }

        return $weight > 0 ? round($weighted / $weight, 2) : null;
    }

    /**
     * Same channel identity as Active Channel (allMarketplaceSnapshotKey).
     */
    private static function snapshotKey(string $name): string
    {
        $key = strtolower(str_replace([' ', '-', '&', '/', '(', ')'], '', trim($name)));

        return match ($key) {
            'ebay2', 'ebaytwo' => 'ebaytwo',
            'ebay3', 'ebaythree' => 'ebaythree',
            'shopify', 'shopifyb2c' => 'shopifyb2c',
            'tiktok', 'tiktokshop' => 'tiktokshop',
            'tiktok2', 'tiktokshop2' => 'tiktokshop2',
            'bestbuy', 'bestbuyusa' => 'bestbuyusa',
            'facebookmarketplace', 'fbmarketplace' => 'fbmarketplace',
            'temu3', 'temuthree' => 'temu3',
            'temu2', 'temutwo' => 'temu2',
            'business5coreb2b', 'b5cb2b' => 'business5coreb2b',
            default => $key,
        };
    }

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
     * Sidebar Missing Listing badge — cached total only.
     * Never recomputes listing pages during HTML render.
     */
    public static function missingLCountForSidebar(): int
    {
        foreach ([
            \App\Support\Marketplace\ListingChannelCounts::TOTAL_CACHE_KEY,
            self::MISSING_L_CACHE_KEY,
        ] as $key) {
            try {
                $cached = Cache::get($key);
                if ($cached !== null) {
                    return (int) $cached;
                }
            } catch (\Throwable $e) {
                // File cache dirs may be missing mid-request after optimize:clear.
            }
        }

        try {
            return (int) round((float) (BadgeData::dataForPage(self::PAGE_NAME, ['missing_l' => 0])['missing_l'] ?? 0));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * All Marketplace Master N Map total. The Missing Mapping sidebar uses
     * MappingChannelCounts::cachedTotalOrZero() so the two numbers stay separate.
     */
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
