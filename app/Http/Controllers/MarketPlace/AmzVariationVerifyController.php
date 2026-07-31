<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonListingRaw;
use App\Models\AmazonSpCampaignReport;
use App\Models\ChannelMasterSummary;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\AmazonAdsService;
use App\Services\AmazonSpApiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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
     * Two-way match:
     *  1) CP Master → Ads: missing / over (existing logic)
     *  2) Ads → CP Master: Extra = ad SKU under this parent family that is not a CP Master child
     */
    public function data(Request $request)
    {
        $listedSkuSet = $this->buildListedSkuLookup();
        $adLookup = $this->buildAdCampaignLookupFromReports();
        $pmParentByNorm = $this->buildProductMasterParentLookup();

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

            $hasKw = $this->skuHasCampaignType($sku, $parent, $available, $adLookup, 'kw');
            $hasPt = $this->skuHasCampaignType($sku, $parent, $available, $adLookup, 'pt');

            $kwFields = $this->buildSiblingAdFields($hasKw, $available, $adLookup['empty']);
            $ptFields = $this->buildSiblingAdFields($hasPt, $available, $adLookup['empty']);

            if (! empty($kwFields['existing']) || ! empty($kwFields['over'])) {
                $kwFields['campaign_names'] = $this->findMatchedCampaignNames($sku, $parent, $available, $adLookup, 'kw');
            } else {
                $kwFields['campaign_names'] = [];
            }
            if (! empty($ptFields['existing']) || ! empty($ptFields['over'])) {
                $ptFields['campaign_names'] = $this->findMatchedCampaignNames($sku, $parent, $available, $adLookup, 'pt');
            } else {
                $ptFields['campaign_names'] = [];
            }

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

        // All CP Master parent names (including parents that only have a PARENT sku row)
        // so Ads → CP Master does not flag those PARENT campaigns as Extra elsewhere.
        $allParentKeys = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('parent')
            ->where('parent', '!=', '')
            ->distinct()
            ->pluck('parent')
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $formattedData = [];
        foreach ($parentGroups as $parentKey => $children) {
            $known = array_filter($children, fn ($c) => $c['child_sku_available'] !== null);
            $availableCount = count(array_filter($known, fn ($c) => $c['child_sku_available'] === true));
            $requiredCount = count($children);
            $knownCount = count($known);
            $parentMatch = $knownCount > 0 ? ($availableCount === $requiredCount) : null;

            $kwExtra = $this->findExtraAdSkus(
                $parentKey,
                $children,
                $adLookup['kw_campaign_bases'] ?? [],
                $pmParentByNorm,
                $allParentKeys
            );
            $ptExtra = $this->findExtraAdSkus(
                $parentKey,
                $children,
                $adLookup['pt_campaign_bases'] ?? [],
                $pmParentByNorm,
                $allParentKeys
            );

            $kwExtraCampaigns = $this->resolveCampaignsForExtraBases($kwExtra, 'kw');
            $ptExtraCampaigns = $this->resolveCampaignsForExtraBases($ptExtra, 'pt');

            $kwRollup = $this->rollupSiblingAds($children, 'kw', $adLookup['empty'], $kwExtra, $kwExtraCampaigns);
            $ptRollup = $this->rollupSiblingAds($children, 'pt', $adLookup['empty'], $ptExtra, $ptExtraCampaigns);

            $invBySku = [];
            foreach ($children as $c) {
                $sku = trim((string) ($c['sku'] ?? ''));
                if ($sku !== '') {
                    $invBySku[$sku] = (float) ($c['inv'] ?? 0);
                }
            }

            $missingUnion = [];
            foreach (array_merge($kwRollup['missing_skus'] ?? [], $ptRollup['missing_skus'] ?? []) as $ms) {
                $ms = trim((string) $ms);
                if ($ms !== '') {
                    $missingUnion[$ms] = true;
                }
            }
            $missingInvGt0 = [];
            foreach (array_keys($missingUnion) as $ms) {
                if (($invBySku[$ms] ?? 0) > 0) {
                    $missingInvGt0[] = $ms;
                }
            }

            $extraUnion = [];
            foreach (array_merge($kwRollup['extra_skus'] ?? [], $ptRollup['extra_skus'] ?? []) as $es) {
                $es = trim((string) $es);
                if ($es !== '') {
                    $extraUnion[$es] = true;
                }
            }
            $archivedExtraUnion = [];
            foreach (array_merge($kwRollup['archived_extra_skus'] ?? [], $ptRollup['archived_extra_skus'] ?? []) as $es) {
                $es = trim((string) $es);
                if ($es !== '') {
                    $archivedExtraUnion[$es] = true;
                }
            }

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
                'has_missing' => $missingUnion !== [],
                'has_missing_inv_gt0' => $missingInvGt0 !== [],
                'has_extra' => $extraUnion !== [] || $archivedExtraUnion !== [],
                'has_archived_extra' => $archivedExtraUnion !== [],
                'missing_sku_count' => count($missingUnion),
                'missing_inv_gt0_count' => count($missingInvGt0),
                'missing_inv_gt0_skus' => $missingInvGt0,
                'extra_sku_count' => count($extraUnion),
                'archived_extra_sku_count' => count($archivedExtraUnion),
            ], $this->prefixAdFields('kw', $kwRollup), $this->prefixAdFields('pt', $ptRollup));
        }

        $lastPulledAt = AmazonListingRaw::query()->max('report_imported_at');
        $listingsCount = AmazonListingRaw::query()->count();
        $campaignCount = $adLookup['campaign_count'] ?? 0;

        // Variations Issues = missing and/or extra ads (two-way mismatch)
        $variationsIssuesCount = count(array_filter(
            $formattedData,
            fn ($r) => ((int) ($r['kw_missing'] ?? 0) > 0)
                || ((int) ($r['pt_missing'] ?? 0) > 0)
                || ((int) ($r['kw_extra'] ?? 0) > 0)
                || ((int) ($r['pt_extra'] ?? 0) > 0)
        ));

        $missingSkuTotal = array_sum(array_map(fn ($r) => (int) ($r['missing_sku_count'] ?? 0), $formattedData));
        $missingInvGt0Total = array_sum(array_map(fn ($r) => (int) ($r['missing_inv_gt0_count'] ?? 0), $formattedData));
        $extraSkuTotal = array_sum(array_map(fn ($r) => (int) ($r['extra_sku_count'] ?? 0), $formattedData));
        $archivedExtraSkuTotal = array_sum(array_map(fn ($r) => (int) ($r['archived_extra_sku_count'] ?? 0), $formattedData));

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
                'missing_sku_count' => (int) $missingSkuTotal,
                'missing_inv_gt0_count' => (int) $missingInvGt0Total,
                'extra_sku_count' => (int) $extraSkuTotal,
                'archived_extra_sku_count' => (int) $archivedExtraSkuTotal,
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
     * Archive Extra ads campaigns in Amazon Ads when status is not already ARCHIVED.
     * Accepts campaign_names and/or extra_skus (bases). Skips ARCHIVED campaigns.
     */
    public function archiveExtraAds(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:KW,PT,kw,pt'],
            'extra_skus' => ['nullable', 'array'],
            'extra_skus.*' => ['string', 'max:255'],
            'campaign_names' => ['nullable', 'array'],
            'campaign_names.*' => ['string', 'max:255'],
        ]);

        $type = strtoupper(trim((string) ($validated['type'] ?? '')));
        $extraSkus = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $validated['extra_skus'] ?? []
        ))));
        $campaignNames = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $validated['campaign_names'] ?? []
        ))));
        $parent = trim((string) ($validated['parent'] ?? ''));

        if ($extraSkus === [] && $campaignNames === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Provide extra_skus and/or campaign_names to archive.',
            ], 422);
        }

        $targets = [];
        if ($campaignNames !== []) {
            foreach ($this->lookupCampaignsByNames($campaignNames, $type !== '' ? $type : null) as $row) {
                $targets[$row['campaign_id']] = $row;
            }
        }
        if ($extraSkus !== []) {
            foreach (['KW', 'PT'] as $t) {
                if ($type !== '' && $type !== $t) {
                    continue;
                }
                foreach ($this->resolveCampaignsForExtraBases($extraSkus, strtolower($t)) as $row) {
                    $targets[$row['campaign_id']] = $row;
                }
            }
        }

        if ($targets === []) {
            return response()->json([
                'ok' => false,
                'message' => 'No matching Extra campaigns found to archive.',
                'parent' => $parent !== '' ? $parent : null,
            ], 422);
        }

        $archived = [];
        $skipped = [];
        $failed = [];
        $ads = app(AmazonAdsService::class);

        foreach ($targets as $row) {
            $status = strtoupper(trim((string) ($row['campaign_status'] ?? '')));
            $cid = (string) ($row['campaign_id'] ?? '');
            $cname = (string) ($row['campaign_name'] ?? '');

            if ($cid === '') {
                $failed[] = ['campaign_name' => $cname, 'message' => 'Missing campaign_id'];
                continue;
            }

            if ($status === 'ARCHIVED') {
                $skipped[] = [
                    'campaign_id' => $cid,
                    'campaign_name' => $cname,
                    'reason' => 'Already ARCHIVED',
                ];
                continue;
            }

            try {
                $result = $ads->archiveCampaign($cid);
            } catch (\Throwable $e) {
                Log::error('AmzVariationVerify archiveExtraAds failed', [
                    'campaign_id' => $cid,
                    'campaign_name' => $cname,
                    'parent' => $parent,
                    'error' => $e->getMessage(),
                ]);
                $failed[] = [
                    'campaign_id' => $cid,
                    'campaign_name' => $cname,
                    'message' => $e->getMessage(),
                ];
                continue;
            }

            if (empty($result['success'])) {
                $failed[] = [
                    'campaign_id' => $cid,
                    'campaign_name' => $cname,
                    'message' => (string) ($result['message'] ?? 'Unknown error'),
                ];
                continue;
            }

            // Keep local L30 reports in sync so Extra clears on refresh.
            try {
                AmazonSpCampaignReport::query()
                    ->where('campaign_id', $cid)
                    ->update(['campaignStatus' => 'ARCHIVED']);
            } catch (\Throwable $e) {
                Log::warning('AmzVariationVerify: local status update after archive failed', [
                    'campaign_id' => $cid,
                    'error' => $e->getMessage(),
                ]);
            }

            $archived[] = [
                'campaign_id' => $cid,
                'campaign_name' => $cname,
                'previous_status' => $status !== '' ? $status : null,
            ];
        }

        $archivedN = count($archived);
        $skippedN = count($skipped);
        $failedN = count($failed);
        $msgParts = [];
        if ($archivedN > 0) {
            $msgParts[] = $archivedN === 1
                ? '1 campaign archived successfully'
                : $archivedN.' campaigns archived successfully';
        }
        if ($skippedN > 0) {
            $msgParts[] = $skippedN === 1
                ? '1 skipped (already archived)'
                : $skippedN.' skipped (already archived)';
        }
        if ($failedN > 0) {
            $msgParts[] = $failedN === 1 ? '1 failed' : $failedN.' failed';
        }
        if ($msgParts === []) {
            $msgParts[] = 'Nothing to archive';
        }

        return response()->json([
            'ok' => $failed === [],
            'message' => implode('. ', $msgParts).'.',
            'parent' => $parent !== '' ? $parent : null,
            'archived' => $archived,
            'skipped' => $skipped,
            'failed' => $failed,
        ], $failed === [] ? 200 : 207);
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
     *   pt_keys: array<string, true>,
     *   kw_parent_keys: array<string, true>,
     *   pt_parent_keys: array<string, true>,
     *   kw_campaign_bases: list<string>,
     *   pt_campaign_bases: list<string>,
     *   kw_campaign_names: list<string>,
     *   pt_campaign_names: list<string>
     * }
     */
    private function buildAdCampaignLookupFromReports(): array
    {
        $empty = [
            'empty' => true,
            'campaign_count' => 0,
            'kw_keys' => [],
            'pt_keys' => [],
            'kw_parent_keys' => [],
            'pt_parent_keys' => [],
            'kw_campaign_bases' => [],
            'pt_campaign_bases' => [],
            'kw_campaign_names' => [],
            'pt_campaign_names' => [],
        ];

        if (! Schema::hasTable('amazon_sp_campaign_reports')) {
            return $empty;
        }

        $campaigns = AmazonSpCampaignReport::query()
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->get(['campaignName', 'campaignStatus', 'campaign_id']);

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

        // Extra matching only uses non-ARCHIVED campaigns (already archived are not actionable).
        $kwActive = $kwCampaigns->filter(
            fn ($c) => strtoupper(trim((string) ($c->campaignStatus ?? ''))) !== 'ARCHIVED'
        )->values();
        $ptActive = $ptCampaigns->filter(
            fn ($c) => strtoupper(trim((string) ($c->campaignStatus ?? ''))) !== 'ARCHIVED'
        )->values();

        $pmRows = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('parent')
            ->where('parent', '!=', '')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->get(['parent', 'sku']);

        $kwKeys = [];
        $ptKeys = [];
        $kwParentKeys = [];
        $ptParentKeys = [];

        foreach ($pmRows as $pm) {
            $parent = trim((string) $pm->parent);
            $sku = trim((string) $pm->sku);
            $nameKey = strtoupper(trim(rtrim($sku, '.')));
            $norm = AmazonDatasheet::normalizeSkuForLookup($sku);
            $parentKey = strtoupper(trim(preg_replace('/\s+/', ' ', rtrim($parent, '.')) ?? $parent));

            // Direct: campaign named for this SKU (or PARENT + same SKU name / SKU prefix variants).
            // Do NOT credit PARENT {parent} to every child here — that caused false 4/4.
            $hasKwDirect = $this->skuHasNamedCampaign($kwCampaigns, $sku, 'kw');
            $hasPtDirect = $this->skuHasNamedCampaign($ptCampaigns, $sku, 'pt');

            if ($hasKwDirect) {
                if ($norm !== '') {
                    $kwKeys[$norm] = true;
                }
                if ($nameKey !== '') {
                    $kwKeys[$nameKey] = true;
                }
            }
            if ($hasPtDirect) {
                if ($norm !== '') {
                    $ptKeys[$norm] = true;
                }
                if ($nameKey !== '') {
                    $ptKeys[$nameKey] = true;
                }
            }

            // Parent-family campaigns (PARENT {parent} KW/PT) — credited later only for listed children.
            if ($parentKey !== '' && $this->parentHasNamedCampaign($kwCampaigns, $parent, 'kw')) {
                $kwParentKeys[$parentKey] = true;
            }
            if ($parentKey !== '' && $this->parentHasNamedCampaign($ptCampaigns, $parent, 'pt')) {
                $ptParentKeys[$parentKey] = true;
            }
        }

        $kwNames = $kwCampaigns->map(fn ($c) => trim((string) ($c->campaignName ?? '')))->filter()->unique()->values()->all();
        $ptNames = $ptCampaigns->map(fn ($c) => trim((string) ($c->campaignName ?? '')))->filter()->unique()->values()->all();

        return [
            'empty' => false,
            'campaign_count' => $campaigns->count(),
            'kw_keys' => $kwKeys,
            'pt_keys' => $ptKeys,
            'kw_parent_keys' => $kwParentKeys,
            'pt_parent_keys' => $ptParentKeys,
            // Include ARCHIVED so Extra rows can still show "Archived" status.
            'kw_campaign_bases' => $this->campaignBasesFromReports($kwCampaigns),
            'pt_campaign_bases' => $this->campaignBasesFromReports($ptCampaigns),
            'kw_campaign_bases_active' => $this->campaignBasesFromReports($kwActive),
            'pt_campaign_bases_active' => $this->campaignBasesFromReports($ptActive),
            'kw_campaign_names' => $kwNames,
            'pt_campaign_names' => $ptNames,
        ];
    }

    /**
     * Resolve SP campaigns for Extra bases (includes ARCHIVED).
     *
     * @param  list<string>  $extraBases
     * @return list<array{campaign_id: string, campaign_name: string, campaign_status: string, base: string}>
     */
    private function resolveCampaignsForExtraBases(array $extraBases, string $type): array
    {
        $extraBases = array_values(array_unique(array_filter(array_map(
            fn ($b) => $this->normalizeCampaignToken((string) $b),
            $extraBases
        ))));
        if ($extraBases === [] || ! Schema::hasTable('amazon_sp_campaign_reports')) {
            return [];
        }

        $isPt = $type === 'pt';
        $rows = AmazonSpCampaignReport::query()
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->get(['campaignName', 'campaign_id', 'campaignStatus']);

        $out = [];
        foreach ($rows as $row) {
            $rawName = trim((string) ($row->campaignName ?? ''));
            $cn = $this->normalizeCampaignToken($rawName);
            if ($cn === '') {
                continue;
            }
            $isPtName = str_ends_with($cn, ' PT') || str_ends_with($cn, ' PT.');
            if ($isPt && ! $isPtName) {
                continue;
            }
            if (! $isPt && $isPtName) {
                continue;
            }

            $base = $this->stripCampaignTypeSuffix($cn);
            $matchedBase = null;
            foreach ($extraBases as $eb) {
                if ($base === $eb || str_starts_with($base, $eb.' ') || $base === 'PARENT '.$eb || str_starts_with($base, 'PARENT '.$eb.' ')) {
                    $matchedBase = $eb;
                    break;
                }
            }
            if ($matchedBase === null) {
                continue;
            }

            $cid = preg_replace('/\D+/', '', trim((string) ($row->campaign_id ?? ''))) ?: '';
            if ($cid === '') {
                continue;
            }

            $out[$cid] = [
                'campaign_id' => $cid,
                'campaign_name' => $rawName,
                'campaign_status' => strtoupper(trim((string) ($row->campaignStatus ?? ''))),
                'base' => $matchedBase,
            ];
        }

        return array_values($out);
    }

    /**
     * @param  list<string>  $names
     * @return list<array{campaign_id: string, campaign_name: string, campaign_status: string, base: string}>
     */
    private function lookupCampaignsByNames(array $names, ?string $type = null): array
    {
        $want = [];
        foreach ($names as $n) {
            $tok = $this->normalizeCampaignToken($n);
            if ($tok !== '') {
                $want[$tok] = trim((string) $n);
            }
        }
        if ($want === [] || ! Schema::hasTable('amazon_sp_campaign_reports')) {
            return [];
        }

        $rows = AmazonSpCampaignReport::query()
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->get(['campaignName', 'campaign_id', 'campaignStatus']);

        $type = $type !== null ? strtoupper($type) : null;
        $out = [];
        foreach ($rows as $row) {
            $rawName = trim((string) ($row->campaignName ?? ''));
            $cn = $this->normalizeCampaignToken($rawName);
            if ($cn === '' || ! isset($want[$cn])) {
                continue;
            }
            $isPtName = str_ends_with($cn, ' PT') || str_ends_with($cn, ' PT.');
            if ($type === 'PT' && ! $isPtName) {
                continue;
            }
            if ($type === 'KW' && $isPtName) {
                continue;
            }
            $cid = preg_replace('/\D+/', '', trim((string) ($row->campaign_id ?? ''))) ?: '';
            if ($cid === '') {
                continue;
            }
            $out[$cid] = [
                'campaign_id' => $cid,
                'campaign_name' => $rawName,
                'campaign_status' => strtoupper(trim((string) ($row->campaignStatus ?? ''))),
                'base' => $this->stripCampaignTypeSuffix($cn),
            ];
        }

        return array_values($out);
    }

    /**
     * Campaign names with KW/PT type suffix removed (for Ads → CP Master extras).
     *
     * @param  \Illuminate\Support\Collection<int, object>  $campaigns
     * @return list<string>
     */
    private function campaignBasesFromReports($campaigns): array
    {
        $bases = [];
        foreach ($campaigns as $c) {
            $base = $this->stripCampaignTypeSuffix($this->normalizeCampaignToken($c->campaignName ?? ''));
            if ($base !== '') {
                $bases[$base] = true;
            }
        }

        return array_keys($bases);
    }

    private function stripCampaignTypeSuffix(string $name): string
    {
        $n = $this->normalizeCampaignToken($name);
        while (preg_match('/\s+(PT|KW)$/', $n)) {
            $n = preg_replace('/\s+(PT|KW)$/', '', $n) ?? $n;
            $n = rtrim($n, '.');
        }

        return $n;
    }

    private function normalizeCampaignToken(?string $value): string
    {
        $v = strtoupper(trim((string) $value));
        $v = preg_replace('/\s+/', ' ', $v) ?? $v;

        return rtrim($v, '.');
    }

    /**
     * Direct SKU campaign match (not parent-family).
     * Matches: "SKU", "SKU KW"/"SKU PT", "SKU …", "PARENT SKU", "PARENT SKU KW"/"PT", "PARENT SKU …"
     *
     * @param  \Illuminate\Support\Collection<int, object>  $campaigns
     */
    private function skuHasNamedCampaign($campaigns, string $sku, string $type): bool
    {
        $cleanSku = $this->normalizeCampaignToken($sku);
        if ($cleanSku === '') {
            return false;
        }

        $bases = [$cleanSku, 'PARENT '.$cleanSku];
        $isPt = $type === 'pt';

        return $campaigns->contains(function ($item) use ($bases, $isPt) {
            $cn = $this->normalizeCampaignToken($item->campaignName ?? '');
            if ($cn === '') {
                return false;
            }

            foreach ($bases as $base) {
                if ($isPt) {
                    if (
                        $cn === $base
                        || $cn === $base.' PT'
                        || str_starts_with($cn, $base.' ')
                        || str_ends_with($cn, $base.' PT')
                        || str_ends_with($cn, $base.' PT.')
                    ) {
                        return true;
                    }
                } elseif ($cn === $base || $cn === $base.' KW' || str_starts_with($cn, $base.' ')) {
                    // KW / other non-PT: exact, optional KW suffix, or SKU prefix (e.g. "SKU 2PCS KW")
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * True when a PARENT {parent} (+ KW/PT) campaign exists for the parent family.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $campaigns
     */
    private function parentHasNamedCampaign($campaigns, string $parent, string $type): bool
    {
        $cleanParent = $this->normalizeCampaignToken($parent);
        if ($cleanParent === '') {
            return false;
        }

        $bases = [$cleanParent, 'PARENT '.$cleanParent];
        $isPt = $type === 'pt';

        return $campaigns->contains(function ($item) use ($bases, $isPt) {
            $cn = $this->normalizeCampaignToken($item->campaignName ?? '');
            if ($cn === '') {
                return false;
            }

            foreach ($bases as $base) {
                // Exact parent / PARENT {parent} (+ type) only — not "PARENT {parent} 2PCS …"
                if ($isPt) {
                    if ($cn === $base || $cn === $base.' PT' || $cn === $base.' PT.') {
                        return true;
                    }
                } elseif ($cn === $base || $cn === $base.' KW') {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Child is in ads when:
     *  1) a campaign is named for this SKU (direct), OR
     *  2) a PARENT {parent} campaign exists AND this child is listed on Amazon
     *     (unlisted children are NOT credited — they stay Missing, not Over).
     *
     * @param  array{
     *   empty: bool,
     *   kw_keys: array<string, true>,
     *   pt_keys: array<string, true>,
     *   kw_parent_keys: array<string, true>,
     *   pt_parent_keys: array<string, true>
     * }  $lookup
     */
    private function skuHasCampaignType(string $sku, string $parent, ?bool $available, array $lookup, string $type): ?bool
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

        // Parent-family campaign covers listed variations only.
        if ($available === true) {
            $parentKeys = $type === 'pt' ? ($lookup['pt_parent_keys'] ?? []) : ($lookup['kw_parent_keys'] ?? []);
            $parentKey = $this->normalizeCampaignToken($parent);
            if ($parentKey !== '' && isset($parentKeys[$parentKey])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Campaign names that cover this child SKU (direct SKU match and/or PARENT {parent}).
     *
     * @param  array<string, mixed>  $lookup
     * @return list<string>
     */
    private function findMatchedCampaignNames(
        string $sku,
        string $parent,
        ?bool $available,
        array $lookup,
        string $type
    ): array {
        if ($lookup['empty'] ?? true) {
            return [];
        }

        $names = $type === 'pt'
            ? ($lookup['pt_campaign_names'] ?? [])
            : ($lookup['kw_campaign_names'] ?? []);
        if ($names === []) {
            return [];
        }

        $cleanSku = $this->normalizeCampaignToken($sku);
        $cleanParent = $this->normalizeCampaignToken($parent);
        $skuBases = array_values(array_filter([$cleanSku, $cleanSku !== '' ? 'PARENT '.$cleanSku : '']));
        $parentBases = array_values(array_filter([$cleanParent, $cleanParent !== '' ? 'PARENT '.$cleanParent : '']));
        $isPt = $type === 'pt';
        $allowParentFamily = $available === true;

        $matched = [];
        foreach ($names as $rawName) {
            $cn = $this->normalizeCampaignToken((string) $rawName);
            if ($cn === '') {
                continue;
            }

            $hit = false;
            foreach ($skuBases as $base) {
                if ($isPt) {
                    if (
                        $cn === $base
                        || $cn === $base.' PT'
                        || str_starts_with($cn, $base.' ')
                        || str_ends_with($cn, $base.' PT')
                        || str_ends_with($cn, $base.' PT.')
                    ) {
                        $hit = true;
                        break;
                    }
                } elseif ($cn === $base || $cn === $base.' KW' || str_starts_with($cn, $base.' ')) {
                    $hit = true;
                    break;
                }
            }

            if (! $hit && $allowParentFamily) {
                foreach ($parentBases as $base) {
                    if ($isPt) {
                        if ($cn === $base || $cn === $base.' PT' || $cn === $base.' PT.') {
                            $hit = true;
                            break;
                        }
                    } elseif ($cn === $base || $cn === $base.' KW') {
                        $hit = true;
                        break;
                    }
                }
            }

            if ($hit) {
                $matched[trim((string) $rawName)] = true;
            }
        }

        $list = array_keys($matched);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * Child-level KW/PT status fields.
     * Every child SKU is counted (INV is not used to skip).
     *
     * @return array{status: ?string, label: string, existing: bool, missing: bool, over: bool}
     */
    private function buildSiblingAdFields(?bool $inCampaign, ?bool $available, bool $adsEmpty): array
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

        // Ads existing: in campaign
        $existing = $inCampaign === true;

        // Missing: not in campaign
        $missing = $inCampaign === false;

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
     * Parent rollup for KW or PT siblings — all child SKUs (no INV skip) + Ads→CP extras.
     *
     * @param  array<int, array>  $children
     * @param  list<string>  $extraSkus
     * @return array{
     *   status: ?string,
     *   label: string,
     *   existing: int,
     *   missing: int,
     *   over: int,
     *   extra: int,
     *   required: int,
     *   missing_skus: list<string>,
     *   over_skus: list<string>,
     *   extra_skus: list<string>,
     *   archived_extra_skus: list<string>,
     *   extra_campaigns: list<array{campaign_id: string, campaign_name: string, campaign_status: string, base: string}>,
     *   added_skus: list<string>,
     *   added_campaigns: list<string>
     * }
     */
    private function rollupSiblingAds(array $children, string $type, bool $adsEmpty, array $extraSkus = [], array $extraCampaigns = []): array
    {
        $prefix = $type . '_';
        if ($adsEmpty) {
            return [
                'status' => null,
                'label' => '—',
                'existing' => 0,
                'missing' => 0,
                'over' => 0,
                'extra' => 0,
                'archived_extra' => 0,
                'required' => 0,
                'missing_skus' => [],
                'over_skus' => [],
                'extra_skus' => [],
                'archived_extra_skus' => [],
                'extra_campaigns' => [],
                'added_skus' => [],
                'added_campaigns' => [],
            ];
        }

        $required = count($children);
        $existing = count(array_filter($children, fn ($c) => ! empty($c[$prefix . 'existing'])));

        $missingSkus = [];
        $overSkus = [];
        $addedSkus = [];
        $addedCampaigns = [];
        foreach ($children as $c) {
            $sku = trim((string) ($c['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            if (! empty($c[$prefix . 'missing'])) {
                $missingSkus[] = $sku;
            }
            if (! empty($c[$prefix . 'over'])) {
                $overSkus[] = $sku;
            }
            if (! empty($c[$prefix . 'existing'])) {
                $addedSkus[] = $sku;
                foreach ($c[$prefix . 'campaign_names'] ?? [] as $cn) {
                    $cn = trim((string) $cn);
                    if ($cn !== '') {
                        $addedCampaigns[$cn] = true;
                    }
                }
            }
        }

        // Split Extra bases into active vs already ARCHIVED.
        $baseFlags = [];
        foreach ($extraCampaigns as $camp) {
            $base = $this->normalizeCampaignToken((string) ($camp['base'] ?? ''));
            if ($base === '') {
                continue;
            }
            if (! isset($baseFlags[$base])) {
                $baseFlags[$base] = ['active' => false, 'archived' => false];
            }
            if (strtoupper(trim((string) ($camp['campaign_status'] ?? ''))) === 'ARCHIVED') {
                $baseFlags[$base]['archived'] = true;
            } else {
                $baseFlags[$base]['active'] = true;
            }
        }

        $activeExtraSkus = [];
        $archivedExtraSkus = [];
        foreach ($extraSkus as $es) {
            $es = trim((string) $es);
            if ($es === '') {
                continue;
            }
            $tok = $this->normalizeCampaignToken($es);
            $flags = $baseFlags[$tok] ?? null;
            if ($flags && ! empty($flags['active'])) {
                $activeExtraSkus[] = $es;
            } elseif ($flags && ! empty($flags['archived'])) {
                $archivedExtraSkus[] = $es;
            } else {
                // No campaign status resolved — treat as actionable Extra.
                $activeExtraSkus[] = $es;
            }
        }

        sort($missingSkus, SORT_NATURAL | SORT_FLAG_CASE);
        sort($overSkus, SORT_NATURAL | SORT_FLAG_CASE);
        sort($activeExtraSkus, SORT_NATURAL | SORT_FLAG_CASE);
        sort($archivedExtraSkus, SORT_NATURAL | SORT_FLAG_CASE);
        sort($addedSkus, SORT_NATURAL | SORT_FLAG_CASE);
        $addedCampaignList = array_keys($addedCampaigns);
        sort($addedCampaignList, SORT_NATURAL | SORT_FLAG_CASE);

        $missing = count($missingSkus);
        $over = count($overSkus);
        $extra = count($activeExtraSkus);
        $archivedExtra = count($archivedExtraSkus);

        $ok = $required > 0 && $missing === 0 && $over === 0 && $extra === 0 && $archivedExtra === 0 && $existing === $required;
        $parts = [];
        if ($missing > 0) {
            $parts[] = $missing . ' missing';
        }
        if ($over > 0) {
            $parts[] = $over . ' over';
        }
        if ($extra > 0) {
            $parts[] = $extra . ' extra';
        }
        if ($archivedExtra > 0) {
            $parts[] = $archivedExtra . ' archived';
        }

        $label = ($existing . '/' . $required)
            . ($parts !== [] ? ' · ' . implode(' · ', $parts) : '');

        $status = 'ok';
        if ($missing > 0) {
            $status = 'missing';
        } elseif ($extra > 0) {
            $status = 'extra';
        } elseif ($archivedExtra > 0) {
            $status = 'archived_extra';
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
            'extra' => $extra,
            'archived_extra' => $archivedExtra,
            'required' => $required,
            'missing_skus' => $missingSkus,
            'over_skus' => $overSkus,
            'extra_skus' => $activeExtraSkus,
            'archived_extra_skus' => $archivedExtraSkus,
            'extra_campaigns' => array_values($extraCampaigns),
            'added_skus' => $addedSkus,
            'added_campaigns' => $addedCampaignList,
        ];
    }

    /**
     * Ads → CP Master: ad campaign bases under this parent family that are not CP Master children.
     *
     * @param  array<int, array{sku?: string}>  $children
     * @param  list<string>  $campaignBases  type-stripped campaign names
     * @param  array<string, string>  $pmParentByNorm
     * @param  list<string>  $allParentKeys
     * @return list<string>
     */
    private function findExtraAdSkus(
        string $parentKey,
        array $children,
        array $campaignBases,
        array $pmParentByNorm,
        array $allParentKeys
    ): array {
        if ($campaignBases === []) {
            return [];
        }

        $requiredNorms = [];
        $requiredTokens = [];
        foreach ($children as $c) {
            $sku = trim((string) ($c['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $norm = AmazonDatasheet::normalizeSkuForLookup($sku);
            if ($norm !== '' && ! isset($requiredNorms[$norm])) {
                $requiredNorms[$norm] = $sku;
            }
            $tok = $this->normalizeCampaignToken($sku);
            if ($tok !== '') {
                $requiredTokens[$tok] = true;
            }
        }

        $parentTok = $this->normalizeCampaignToken($parentKey);
        $parentNorm = AmazonDatasheet::normalizeSkuForLookup($parentKey);
        $childPrefix = $this->commonPrefix(array_keys($requiredNorms));

        $allParentToks = [];
        foreach ($allParentKeys as $pk) {
            $tok = $this->normalizeCampaignToken((string) $pk);
            if ($tok !== '') {
                $allParentToks[$tok] = true;
            }
        }

        $extras = [];
        foreach ($campaignBases as $base) {
            $base = $this->normalizeCampaignToken((string) $base);
            if ($base === '') {
                continue;
            }

            // Exact parent campaign (PARENT {parent} / {parent}) — not a child extra.
            if ($base === $parentTok || $base === 'PARENT '.$parentTok) {
                continue;
            }

            // Campaign named as another CP parent (without PARENT prefix) — belongs there.
            if (isset($allParentToks[$base]) && $base !== $parentTok) {
                continue;
            }

            if (str_starts_with($base, 'PARENT ')) {
                $body = trim(substr($base, 7));
                // Campaign for another CP parent (e.g. PARENT 12 CW 2PCS → parent "12 CW 2PCS").
                if (isset($allParentToks[$body]) && $body !== $parentTok) {
                    continue;
                }
                // PARENT {thisParent} {suffix} with no matching CP parent → Extra under this parent.
                if ($body !== $parentTok && str_starts_with($body, $parentTok.' ') && ! isset($allParentToks[$body])) {
                    $extras[$body] = $body;
                }
                continue;
            }

            // Named for a required child (exact or "SKU …" variant) — not Extra.
            $matchedRequired = false;
            foreach ($requiredTokens as $tok => $_) {
                if ($base === $tok || str_starts_with($base, $tok.' ')) {
                    $matchedRequired = true;
                    break;
                }
            }
            if ($matchedRequired) {
                continue;
            }

            $candidates = [$base];
            if (preg_match('/^(.+)\s+2PCS$/', $base, $m)) {
                $candidates[] = $m[1];
            }

            foreach ($candidates as $cand) {
                $norm = AmazonDatasheet::normalizeSkuForLookup($cand);
                if ($norm === '') {
                    continue;
                }
                if (isset($requiredNorms[$norm])) {
                    continue 2;
                }

                $pmParent = $pmParentByNorm[$norm] ?? null;
                if ($pmParent !== null) {
                    $pmTok = $this->normalizeCampaignToken($pmParent);
                    // Belongs to another CP parent — not Extra for this group.
                    if ($pmTok !== '' && $pmTok !== $parentTok) {
                        continue 2;
                    }
                    // Same parent in PM would already be required; skip.
                    if ($pmTok === $parentTok) {
                        continue 2;
                    }
                }

                if ($this->skuBelongsToParentFamily($norm, $parentNorm, $childPrefix)) {
                    $extras[$cand] = $cand;
                    continue 2;
                }
            }
        }

        $list = array_values($extras);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * @return array<string, string> normalized sku => parent
     */
    private function buildProductMasterParentLookup(): array
    {
        $map = [];

        $rows = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->get(['sku', 'parent']);

        foreach ($rows as $row) {
            $norm = AmazonDatasheet::normalizeSkuForLookup((string) ($row->sku ?? ''));
            if ($norm === '' || isset($map[$norm])) {
                continue;
            }
            $map[$norm] = trim((string) ($row->parent ?? ''));
        }

        return $map;
    }

    /**
     * @param  list<string>  $norms
     */
    private function commonPrefix(array $norms): string
    {
        if ($norms === []) {
            return '';
        }

        $prefix = $norms[0];
        foreach ($norms as $norm) {
            $max = min(strlen($prefix), strlen($norm));
            $i = 0;
            while ($i < $max && $prefix[$i] === $norm[$i]) {
                $i++;
            }
            $prefix = substr($prefix, 0, $i);
            if ($prefix === '') {
                return '';
            }
        }

        return $prefix;
    }

    private function skuBelongsToParentFamily(string $skuNorm, string $parentNorm, string $childPrefix): bool
    {
        if ($parentNorm !== '' && str_starts_with($skuNorm, $parentNorm)) {
            return true;
        }

        // Avoid tiny prefixes that match unrelated SKUs (e.g. "CS").
        if (strlen($childPrefix) >= 4 && str_starts_with($skuNorm, $childPrefix)) {
            return true;
        }

        return false;
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
