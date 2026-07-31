<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ADVMastersData;
use App\Models\AmazonDatasheet;
use App\Models\AmazonListingRaw;
use App\Models\AmazonSpCampaignReport;
use App\Models\ChannelMasterSummary;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmzVariationVerifyController extends Controller
{
    private const TZ = 'America/Los_Angeles';

    public const CHANNEL_KEY = 'amzvariationverify';

    public function index()
    {
        return view('market-places.amz_variation_verify');
    }

    /**
     * Tabulator data for Amazon Ads Variation Verification.
     * Listings: amazon_listings_raw / amazon_datsheets
     * Ads KW/PT: amazon_sp_campaign_reports (L30 name match — same as Ad Running)
     *
     * Ads existing = in campaign AND inv >= 0
     * Over = in campaign but NOT listed in active records
     */
    public function data(Request $request)
    {
        $listedSkuSet = $this->buildListedSkuLookup();
        $adLookup = $this->buildAdCampaignLookupFromReports();

        $productMasters = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('parent')
            ->where('parent', '!=', '')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->orderBy('parent')
            ->orderBy('sku')
            ->get(['parent', 'sku']);

        $shopifyBySku = ShopifySku::mapByProductSkus(
            $productMasters->pluck('sku')->filter()->unique()->values()->all()
        );

        $childRows = $productMasters->map(function ($pm) use ($listedSkuSet, $adLookup, $shopifyBySku) {
            $parent = trim((string) ($pm->parent ?? ''));
            $sku = trim((string) ($pm->sku ?? ''));
            $available = $this->isSkuListed($sku, $listedSkuSet);
            $match = $available === null ? null : ($available === true);

            $inv = (float) ($shopifyBySku[$sku]->inv ?? 0);
            $invEligible = $inv >= 0;

            $hasKw = $this->skuHasCampaignType($sku, $adLookup, 'kw');
            $hasPt = $this->skuHasCampaignType($sku, $adLookup, 'pt');

            $kwFields = $this->buildSiblingAdFields($hasKw, $invEligible, $available, $adLookup['empty']);
            $ptFields = $this->buildSiblingAdFields($hasPt, $invEligible, $available, $adLookup['empty']);

            return array_merge([
                'parent' => $parent,
                'sku' => $sku,
                'inv' => $inv,
                'is_parent' => false,
                'child_sku_required' => true,
                'child_sku_required_label' => 'Yes',
                'child_sku_available' => $available,
                'child_sku_available_label' => $available === null ? '—' : ($available ? 'Yes' : 'No'),
                'match_status' => $match,
                'match_label' => $match === null ? '—' : ($match ? 'match' : 'mismatch'),
            ], $this->prefixAdFields('kw', $kwFields), $this->prefixAdFields('pt', $ptFields));
        })->values()->all();

        $parentGroups = [];
        foreach ($childRows as $row) {
            $parentKey = $row['parent'] !== '' ? $row['parent'] : $row['sku'];
            $parentGroups[$parentKey][] = $row;
        }

        $formattedData = [];
        foreach ($parentGroups as $parentKey => $children) {
            $known = array_filter($children, fn ($c) => $c['child_sku_available'] !== null);
            $availableCount = count(array_filter($known, fn ($c) => $c['child_sku_available'] === true));
            $requiredCount = count($children);
            $knownCount = count($known);
            $parentMatch = $knownCount > 0 ? ($availableCount === $requiredCount) : null;

            $kwRollup = $this->rollupSiblingAds($children, 'kw', $adLookup['empty']);
            $ptRollup = $this->rollupSiblingAds($children, 'pt', $adLookup['empty']);

            $formattedData[] = array_merge([
                'parent' => $parentKey,
                'sku' => 'PARENT ' . $parentKey,
                'inv' => array_sum(array_column($children, 'inv')),
                'is_parent' => true,
                'child_sku_required' => $requiredCount,
                'child_sku_required_label' => (string) $requiredCount,
                'child_sku_available' => $parentMatch,
                'child_sku_available_label' => $knownCount > 0
                    ? ($availableCount . '/' . $requiredCount)
                    : '—',
                'child_sku_available_count' => $availableCount,
                'child_sku_total' => $requiredCount,
                'match_status' => $parentMatch,
                'match_label' => $parentMatch === null
                    ? '—'
                    : ($parentMatch ? 'match' : (($requiredCount - $availableCount) . ' missing')),
            ], $this->prefixAdFields('kw', $kwRollup), $this->prefixAdFields('pt', $ptRollup));
        }

        $lastPulledAt = AmazonListingRaw::query()->max('report_imported_at');
        $listingsCount = AmazonListingRaw::query()->count();
        $campaignCount = $adLookup['campaign_count'] ?? 0;

        // Variations Issues = parents with KW and/or PT ads missing (eligible inv, not in campaign)
        $variationsIssuesCount = count(array_filter(
            $formattedData,
            fn ($r) => ((int) ($r['kw_missing'] ?? 0) > 0) || ((int) ($r['pt_missing'] ?? 0) > 0)
        ));

        $this->persistVariationsIssuesSnapshot($variationsIssuesCount);
        $prevDayCount = $this->previousDayVariationsIssuesCount();

        return response()->json([
            'data' => $formattedData,
            'meta' => [
                'listings_count' => (int) $listingsCount,
                'last_pulled_at' => $lastPulledAt,
                'has_listings_cache' => $listingsCount > 0,
                'required_parent_count' => count($parentGroups),
                'required_child_count' => count($childRows),
                'variations_issues_count' => $variationsIssuesCount,
                'variations_issues_prev_day' => $prevDayCount,
                'required_refreshed_at' => now()->toDateTimeString(),
                'ads_count' => $campaignCount,
                'ads_pulled_at' => null,
                'has_ads_cache' => ! $adLookup['empty'],
                'ads_source' => 'amazon_sp_campaign_reports (L30 KW/PT)',
            ],
        ]);
    }

    /**
     * Rolling history for VARIATIONS ISSUES badge (California dates).
     * Lower is better → chart dots: up=red, down=green, flat=gray.
     */
    public function chartData(Request $request)
    {
        try {
            $days = (int) $request->input('days', 30);
            $badgeValue = $request->input('badge_value');
            $live = ($badgeValue !== null && $badgeValue !== '' && is_numeric($badgeValue))
                ? (float) $badgeValue
                : null;

            if ($live !== null) {
                $this->persistVariationsIssuesSnapshot((int) $live);
            }

            if (! Schema::hasTable('channel_master_daily_data')) {
                $todayVal = $live ?? 0;

                return response()->json([
                    'success' => true,
                    'data' => [[
                        'date' => now(self::TZ)->format('M d'),
                        'full_date' => now(self::TZ)->toDateString(),
                        'value' => round($todayVal, 2),
                    ]],
                ]);
            }

            $query = ChannelMasterSummary::query()
                ->where('channel', self::CHANNEL_KEY)
                ->orderBy('snapshot_date', 'asc');

            if ($days > 0) {
                $start = now(self::TZ)->subDays(max($days - 1, 0))->toDateString();
                $query->whereDate('snapshot_date', '>=', $start);
            }

            $rows = $query->get(['snapshot_date', 'summary_data']);
            $chartData = [];
            foreach ($rows as $row) {
                $dateKey = Carbon::parse($row->snapshot_date)->timezone(self::TZ)->toDateString();
                $sd = is_array($row->summary_data) ? $row->summary_data : [];
                $chartData[] = [
                    'date' => Carbon::parse($dateKey, self::TZ)->format('M d'),
                    'full_date' => $dateKey,
                    'date_key' => $dateKey,
                    'value' => round((float) ($sd['variations_issues_count'] ?? 0), 2),
                ];
            }

            if ($live !== null) {
                $todayKey = now(self::TZ)->toDateString();
                $todayLabel = now(self::TZ)->format('M d');
                $replaced = false;
                foreach ($chartData as &$point) {
                    if (($point['date_key'] ?? '') === $todayKey) {
                        $point['value'] = round($live, 2);
                        $replaced = true;
                    }
                }
                unset($point);

                if (! $replaced) {
                    $chartData[] = [
                        'date' => $todayLabel,
                        'full_date' => $todayKey,
                        'date_key' => $todayKey,
                        'value' => round($live, 2),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => array_values(array_map(fn ($p) => [
                    'date' => $p['date'],
                    'full_date' => $p['full_date'] ?? $p['date_key'] ?? '',
                    'value' => $p['value'],
                ], $chartData)),
            ]);
        } catch (\Throwable $e) {
            Log::error('AmzVariationVerify chartData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Persist today's California VARIATIONS ISSUES count.
     */
    private function persistVariationsIssuesSnapshot(int $count): void
    {
        if (! Schema::hasTable('channel_master_daily_data')) {
            return;
        }

        try {
            $today = now(self::TZ)->toDateString();
            $existing = ChannelMasterSummary::where('channel', self::CHANNEL_KEY)
                ->whereDate('snapshot_date', $today)
                ->first();
            $sd = is_array($existing?->summary_data) ? $existing->summary_data : [];
            $sd['variations_issues_count'] = $count;
            $sd['captured_at'] = now(self::TZ)->toDateTimeString();

            ChannelMasterSummary::updateOrCreate(
                ['channel' => self::CHANNEL_KEY, 'snapshot_date' => $today],
                ['summary_data' => $sd, 'notes' => 'Amz Ads Variation Verify — Variations Issues (California)']
            );
        } catch (\Throwable $e) {
            Log::warning('AmzVariationVerify persistVariationsIssuesSnapshot failed: '.$e->getMessage());
        }
    }

    /**
     * Prior California-day snapshot count (for red/green/gray trend dot).
     */
    private function previousDayVariationsIssuesCount(): ?float
    {
        if (! Schema::hasTable('channel_master_daily_data')) {
            return null;
        }

        try {
            $today = now(self::TZ)->toDateString();
            $row = ChannelMasterSummary::where('channel', self::CHANNEL_KEY)
                ->where('snapshot_date', '<', $today)
                ->orderBy('snapshot_date', 'desc')
                ->first();

            if (! $row) {
                return null;
            }

            $sd = is_array($row->summary_data) ? $row->summary_data : [];

            return isset($sd['variations_issues_count'])
                ? (float) $sd['variations_issues_count']
                : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Pull Amazon merchant listings (GET_MERCHANT_LISTINGS_ALL_DATA) via SP-API.
     */
    public function pullListings(Request $request)
    {
        try {
            set_time_limit(3600);

            $service = new AmazonSpApiService();
            $result = $service->fetchAndStoreListingsReport();

            if (!($result['success'] ?? false)) {
                return response()->json([
                    'status' => 422,
                    'message' => $result['message'] ?? 'Failed to pull Amazon listings.',
                ], 422);
            }

            $count = (int) ($result['count'] ?? 0);

            return response()->json([
                'status' => 200,
                'message' => "Pulled {$count} Amazon listings. Parent Vs Listed SKU updated.",
                'count' => $count,
                'last_pulled_at' => AmazonListingRaw::query()->max('report_imported_at'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Amazon Ads Variation Verification: pull listings failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Pull failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array{set: array<string, true>, empty: bool}
     */
    private function buildListedSkuLookup(): array
    {
        $set = [];

        $listings = AmazonListingRaw::query()
            ->whereNotNull('seller_sku')
            ->where('seller_sku', '!=', '')
            ->pluck('seller_sku');

        foreach ($listings as $sellerSku) {
            $norm = AmazonDatasheet::normalizeSkuForLookup($sellerSku);
            if ($norm !== '') {
                $set[$norm] = true;
            }
        }

        if (empty($set)) {
            $datasheetSkus = AmazonDatasheet::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->pluck('sku');

            foreach ($datasheetSkus as $sku) {
                $norm = AmazonDatasheet::normalizeSkuForLookup($sku);
                if ($norm !== '') {
                    $set[$norm] = true;
                }
            }
        }

        return [
            'set' => $set,
            'empty' => empty($set),
        ];
    }

    /**
     * @return array{
     *   empty: bool,
     *   campaign_count: int,
     *   kw_keys: array<string, true>,
     *   pt_keys: array<string, true>
     * }
     */
    private function buildAdCampaignLookupFromReports(): array
    {
        $empty = [
            'empty' => true,
            'campaign_count' => 0,
            'kw_keys' => [],
            'pt_keys' => [],
        ];

        if (! Schema::hasTable('amazon_sp_campaign_reports')) {
            return $empty;
        }

        $campaigns = AmazonSpCampaignReport::query()
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->get(['campaignName', 'campaignStatus']);

        if ($campaigns->isEmpty()) {
            return $empty;
        }

        $kwCampaigns = $campaigns->filter(function ($c) {
            $cn = strtoupper(trim((string) ($c->campaignName ?? '')));

            return ! str_ends_with($cn, ' PT') && ! str_ends_with($cn, ' PT.');
        })->values();

        $ptCampaigns = $campaigns->filter(function ($c) {
            $cn = strtoupper(trim((string) ($c->campaignName ?? '')));

            return str_ends_with($cn, ' PT') || str_ends_with($cn, ' PT.');
        })->values();

        $pmRows = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('parent')
            ->where('parent', '!=', '')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->get(['parent', 'sku']);

        $kwKeys = [];
        $ptKeys = [];

        foreach ($pmRows as $pm) {
            $parent = trim((string) $pm->parent);
            $sku = trim((string) $pm->sku);
            $nameKey = strtoupper(trim(rtrim($sku, '.')));
            $norm = AmazonDatasheet::normalizeSkuForLookup($sku);

            $hasKw = ADVMastersData::matchKwCampaign($kwCampaigns, $sku, $parent, false) !== null;
            $hasPt = ADVMastersData::matchPtCampaign($ptCampaigns, $sku, $parent, false, false) !== null;

            if ($hasKw) {
                if ($norm !== '') {
                    $kwKeys[$norm] = true;
                }
                if ($nameKey !== '') {
                    $kwKeys[$nameKey] = true;
                }
            }
            if ($hasPt) {
                if ($norm !== '') {
                    $ptKeys[$norm] = true;
                }
                if ($nameKey !== '') {
                    $ptKeys[$nameKey] = true;
                }
            }
        }

        return [
            'empty' => false,
            'campaign_count' => $campaigns->count(),
            'kw_keys' => $kwKeys,
            'pt_keys' => $ptKeys,
        ];
    }

    /**
     * @param  array{empty: bool, kw_keys: array<string, true>, pt_keys: array<string, true>}  $lookup
     */
    private function skuHasCampaignType(string $sku, array $lookup, string $type): ?bool
    {
        if ($lookup['empty']) {
            return null;
        }

        $keys = $type === 'pt' ? ($lookup['pt_keys'] ?? []) : ($lookup['kw_keys'] ?? []);
        $norm = AmazonDatasheet::normalizeSkuForLookup($sku);
        $nameKey = strtoupper(trim(rtrim($sku, '.')));

        if ($norm !== '' && isset($keys[$norm])) {
            return true;
        }
        if ($nameKey !== '' && isset($keys[$nameKey])) {
            return true;
        }

        return false;
    }

    /**
     * Child-level KW/PT status fields.
     *
     * @return array{status: ?string, label: string, existing: bool, missing: bool, over: bool}
     */
    private function buildSiblingAdFields(?bool $inCampaign, bool $invEligible, ?bool $available, bool $adsEmpty): array
    {
        if ($adsEmpty || $inCampaign === null) {
            return [
                'status' => null,
                'label' => '—',
                'existing' => false,
                'missing' => false,
                'over' => false,
            ];
        }

        // Over: in campaign but not available in active listed records
        $over = $inCampaign === true && $available === false;

        // Ads existing: in campaign AND inv >= 0
        $existing = $inCampaign === true && $invEligible;

        // Missing: eligible inv but not in campaign
        $missing = $invEligible && $inCampaign === false;

        if ($over) {
            $status = 'over';
            $label = 'Over';
        } elseif ($existing) {
            $status = 'added';
            $label = 'Added';
        } elseif ($missing) {
            $status = 'missing';
            $label = 'Missing';
        } else {
            $status = null;
            $label = '—';
        }

        return [
            'status' => $status,
            'label' => $label,
            'existing' => $existing,
            'missing' => $missing,
            'over' => $over,
        ];
    }

    /**
     * Parent rollup for KW or PT siblings.
     *
     * @param  array<int, array>  $children
     * @return array{status: ?string, label: string, existing: int, missing: int, over: int, required: int}
     */
    private function rollupSiblingAds(array $children, string $type, bool $adsEmpty): array
    {
        $prefix = $type . '_';
        if ($adsEmpty) {
            return [
                'status' => null,
                'label' => '—',
                'existing' => 0,
                'missing' => 0,
                'over' => 0,
                'required' => 0,
            ];
        }

        $eligible = array_values(array_filter($children, fn ($c) => (($c['inv'] ?? 0) >= 0)));
        $required = count($eligible);
        $existing = count(array_filter($children, fn ($c) => ! empty($c[$prefix . 'existing'])));
        $missing = count(array_filter($children, fn ($c) => ! empty($c[$prefix . 'missing'])));
        $over = count(array_filter($children, fn ($c) => ! empty($c[$prefix . 'over'])));

        $ok = $required > 0 && $missing === 0 && $over === 0 && $existing === $required;
        $parts = [];
        if ($missing > 0) {
            $parts[] = $missing . ' missing';
        }
        if ($over > 0) {
            $parts[] = $over . ' over';
        }

        $label = ($existing . '/' . $required)
            . ($parts !== [] ? ' · ' . implode(' · ', $parts) : '');

        $status = 'ok';
        if ($missing > 0) {
            $status = 'missing';
        } elseif ($over > 0) {
            $status = 'over';
        }

        if ($required === 0) {
            $status = null;
            $label = '—';
        }

        return [
            'status' => $ok ? 'ok' : $status,
            'label' => $label,
            'existing' => $existing,
            'missing' => $missing,
            'over' => $over,
            'required' => $required,
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function prefixAdFields(string $type, array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            $out[$type . '_' . $key] = $value;
        }
        // Convenience aliases used by the blade
        $out[$type . '_ad_status'] = $fields['status'] ?? null;
        $out[$type . '_ad_label'] = $fields['label'] ?? '—';

        return $out;
    }

    /**
     * @param  array{set: array<string, true>, empty: bool}  $lookup
     */
    private function isSkuListed(string $sku, array $lookup): ?bool
    {
        if ($lookup['empty']) {
            return null;
        }

        $norm = AmazonDatasheet::normalizeSkuForLookup($sku);
        if ($norm === '') {
            return false;
        }

        return isset($lookup['set'][$norm]);
    }
}
