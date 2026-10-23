<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonAdsCampaignSku;
use App\Models\AmazonDatasheet;
use App\Models\AmazonListingRaw;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonSpProductAd;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\Marketplace\AmazonAdsMissingLinks;
use App\Support\Marketplace\AmazonListingCounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmzVariationVerifyController extends Controller
{
    /**
     * Parent / child Ads KW + PT verification data (the page itself was removed; used by parentAdsSummary()).
     * Listings: amazon_listings_raw / amazon_datsheets
     * Ads KW/PT: amazon_sp_campaign_reports (L30 name match — same as Ad Running)
     *
     * Two-way match:
     *  1) CP Master → Ads: missing / over (existing logic)
     *  2) Ads → CP Master: Extra = PARENT {parent} {suffix} campaign that is not
     *     a CP Master child and is not owned by a more specific CP parent.
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
            ->get(['parent', 'sku', 'Values']);

        $shopifyBySku = ShopifySku::mapByProductSkus(
            $productMasters->pluck('sku')->filter()->unique()->values()->all()
        );
        $nrlSet = AmazonListingCounts::nrlSetForSkus(
            $productMasters->pluck('sku')->filter()->unique()->values()->all()
        );

        $childRows = $productMasters->map(function ($pm) use ($listedSkuSet, $adLookup, $shopifyBySku, $nrlSet) {
            $parent = trim((string) ($pm->parent ?? ''));
            $sku = trim((string) ($pm->sku ?? ''));
            $available = $this->isSkuListed($sku, $listedSkuSet);
            $match = $available === null ? null : ($available === true);

            $inv = (float) ($shopifyBySku[$sku]->inv ?? 0);

            $isComing = $pm->isComing();
            $isNrl = AmazonListingCounts::skuIsNrl($sku, $nrlSet);
            $isLowInv = $inv <= 1;
            $skipAdsRequired = $isComing || $isNrl || $isLowInv;

            $hasKw = $this->skuHasCampaignType($sku, $parent, $available, $adLookup, 'kw');
            $hasPt = $this->skuHasCampaignType($sku, $parent, $available, $adLookup, 'pt');

            $kwFields = $this->buildSiblingAdFields($hasKw, $available, $adLookup['empty'], $isComing, $isLowInv, $isNrl);
            $ptFields = $this->buildSiblingAdFields($hasPt, $available, $adLookup['empty'], $isComing, $isLowInv, $isNrl);

            if (! empty($kwFields['existing']) || ! empty($kwFields['over'])) {
                $kwFields['campaign_names'] = $this->mergeCampaignNameLists(
                    $this->findMatchedCampaignNames($sku, $parent, $available, $adLookup, 'kw'),
                    $this->productAdCampaignNamesForSku($sku, $adLookup, 'kw')
                );
            } else {
                $kwFields['campaign_names'] = [];
            }
            if (! empty($ptFields['existing']) || ! empty($ptFields['over'])) {
                $ptFields['campaign_names'] = $this->mergeCampaignNameLists(
                    $this->findMatchedCampaignNames($sku, $parent, $available, $adLookup, 'pt'),
                    $this->productAdCampaignNamesForSku($sku, $adLookup, 'pt')
                );
            } else {
                $ptFields['campaign_names'] = [];
            }

            return array_merge([
                'parent' => $parent,
                'sku' => $sku,
                'inv' => $inv,
                'is_parent' => false,
                'pm_status' => $pm->statusValue(),
                'pm_status_label' => $pm->statusLabel(),
                'is_coming' => $isComing,
                'is_nrl' => $isNrl,
                'is_low_inv' => $isLowInv,
                'skip_ads_required' => $skipAdsRequired,
                'child_sku_required' => ! $skipAdsRequired,
                'child_sku_required_label' => $isComing ? 'Coming' : ($isNrl ? 'NRL' : 'Yes'),
                'child_sku_available' => $available,
                'child_sku_available_label' => $available === null ? '—' : ($available ? 'Yes' : 'No'),
                'match_status' => $skipAdsRequired ? null : $match,
                'match_label' => $isComing
                    ? 'Coming'
                    : ($isNrl
                        ? 'NRL'
                        : ($match === null ? '—' : ($match ? 'match' : 'mismatch'))),
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

        $parentStatusMap = $this->buildParentProductMasterStatusMap(array_keys($parentGroups));
        $missingCampaignsByParent = AmazonAdsMissingLinks::listsByParent(array_keys($parentGroups));

        $formattedData = [];
        foreach ($parentGroups as $parentKey => $children) {
            $requiredChildren = array_values(array_filter($children, fn ($c) => empty($c['skip_ads_required'])));
            $known = array_filter($requiredChildren, fn ($c) => $c['child_sku_available'] !== null);
            $availableCount = count(array_filter($known, fn ($c) => $c['child_sku_available'] === true));
            $requiredCount = count($requiredChildren);
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
            // INV ≤ 0 and Amazon NRL never belong in ads missing counts after refresh.
            foreach (array_keys($missingUnion) as $ms) {
                if (($invBySku[$ms] ?? 0) <= 0 || AmazonListingCounts::skuIsNrl($ms, $nrlSet)) {
                    unset($missingUnion[$ms]);
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

            $parentStatus = $parentStatusMap[$parentKey] ?? ['pm_status' => '', 'pm_status_label' => ''];
            if (($parentStatus['pm_status'] ?? '') === '') {
                $parentStatus = $this->rollupChildProductMasterStatus($children);
            }

            $missingCampaigns = $missingCampaignsByParent[$parentKey] ?? ['kw' => [], 'pt' => []];

            $formattedData[] = array_merge([
                'parent' => $parentKey,
                'sku' => 'PARENT ' . $parentKey,
                'campaign_link_sku' => AmazonAdsMissingLinks::skuForParent($parentKey),
                'campaign_kw' => $missingCampaigns['kw'] ?? [],
                'campaign_pt' => $missingCampaigns['pt'] ?? [],
                'inv' => array_sum(array_column($children, 'inv')),
                'is_parent' => true,
                'pm_status' => $parentStatus['pm_status'],
                'pm_status_label' => $parentStatus['pm_status_label'],
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
                '_children' => array_values(array_map(function ($c) {
                    return [
                        'sku' => trim((string) ($c['sku'] ?? '')),
                        'pm_status' => (string) ($c['pm_status'] ?? ''),
                        'pm_status_label' => (string) ($c['pm_status_label'] ?? ''),
                        'is_coming' => ! empty($c['is_coming']),
                        'is_nrl' => ! empty($c['is_nrl']),
                    ];
                }, $children)),
            ], $this->prefixAdFields('kw', $kwRollup), $this->prefixAdFields('pt', $ptRollup));
        }

        $lastPulledAt = AmazonListingRaw::query()->max('report_imported_at');
        $listingsCount = AmazonListingRaw::query()->count();
        $campaignCount = $adLookup['campaign_count'] ?? 0;
        $kwCampaignCount = $adLookup['kw_campaign_count'] ?? 0;
        $ptCampaignCount = $adLookup['pt_campaign_count'] ?? 0;

        // Issues = missing and/or extra ads, counted separately for KW and PT.
        $parentRows = array_values(array_filter($formattedData, fn ($r) => ! empty($r['is_parent'])));
        $kwIssuesCount = count(array_filter(
            $parentRows,
            fn ($r) => ((int) ($r['kw_missing'] ?? 0) > 0) || ((int) ($r['kw_extra'] ?? 0) > 0)
        ));
        $ptIssuesCount = count(array_filter(
            $parentRows,
            fn ($r) => ((int) ($r['pt_missing'] ?? 0) > 0) || ((int) ($r['pt_extra'] ?? 0) > 0)
        ));
        $variationsIssuesCount = count(array_filter(
            $parentRows,
            fn ($r) => ((int) ($r['kw_missing'] ?? 0) > 0)
                || ((int) ($r['pt_missing'] ?? 0) > 0)
                || ((int) ($r['kw_extra'] ?? 0) > 0)
                || ((int) ($r['pt_extra'] ?? 0) > 0)
        ));

        $missingSkuTotal = array_sum(array_map(fn ($r) => (int) ($r['missing_sku_count'] ?? 0), $parentRows));
        $missingInvGt0Total = array_sum(array_map(fn ($r) => (int) ($r['missing_inv_gt0_count'] ?? 0), $parentRows));
        $extraSkuTotal = array_sum(array_map(fn ($r) => (int) ($r['extra_sku_count'] ?? 0), $parentRows));
        $archivedExtraSkuTotal = array_sum(array_map(fn ($r) => (int) ($r['archived_extra_sku_count'] ?? 0), $parentRows));

        return response()->json([
            'data' => $formattedData,
            'meta' => [
                'listings_count' => (int) $listingsCount,
                'last_pulled_at' => $lastPulledAt,
                'has_listings_cache' => $listingsCount > 0,
                'required_parent_count' => count($parentGroups),
                'required_child_count' => count($childRows),
                'variations_issues_count' => $variationsIssuesCount,
                'kw_issues_count' => $kwIssuesCount,
                'pt_issues_count' => $ptIssuesCount,
                'missing_sku_count' => (int) $missingSkuTotal,
                'missing_inv_gt0_count' => (int) $missingInvGt0Total,
                'extra_sku_count' => (int) $extraSkuTotal,
                'archived_extra_sku_count' => (int) $archivedExtraSkuTotal,
                'required_refreshed_at' => now()->toDateTimeString(),
                'ads_count' => $campaignCount,
                'kw_ads_count' => (int) $kwCampaignCount,
                'pt_ads_count' => (int) $ptCampaignCount,
                'ads_pulled_at' => null,
                'has_ads_cache' => ! $adLookup['empty'],
                'ads_source' => 'amazon_sp_campaign_reports (L30 KW/PT)',
            ],
        ]);
    }

    /**
     * Per-parent "Parent Vs Ads SKU" KW / PT result, computed by the same logic as data() above, for pages that
     * show the same column (amazon-ads/all and amazon-ads/missing). Keyed by UPPER(parent) with spaces collapsed.
     * Cached briefly because data() reads the whole campaign / listing set; ?refresh=1 bypasses the cache.
     */
    public function parentAdsSummary(Request $request): JsonResponse
    {
        $build = function (): array {
            $payload = $this->data(request())->getData(true);
            $keys = [
                'ad_status', 'ad_label', 'missing', 'extra', 'archived_extra', 'over',
                'missing_skus', 'over_skus', 'extra_skus', 'archived_extra_skus', 'added_campaigns', 'added_skus',
            ];
            $map = [];
            foreach ((array) ($payload['data'] ?? []) as $row) {
                if (empty($row['is_parent'])) {
                    continue;
                }
                $key = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($row['parent'] ?? ''))));
                if ($key === '') {
                    continue;
                }
                foreach (['kw', 'pt'] as $type) {
                    $f = [];
                    foreach ($keys as $k) {
                        $f[$k] = $row[$type.'_'.$k] ?? null;
                    }
                    $map[$key][$type] = $f;
                }
            }

            return $map;
        };

        try {
            if ($request->boolean('refresh')) {
                Cache::forget('amz_parent_vs_ads_summary');
            }
            $map = Cache::remember('amz_parent_vs_ads_summary', 120, $build);
        } catch (\Throwable $e) {
            Log::warning('Parent vs ads summary failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'Could not compute Parent Vs Ads SKU.', 'data' => (object) []], 500);
        }

        return response()->json(['ok' => true, 'data' => (object) $map]);
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
     *   kw_campaign_count: int,
     *   pt_campaign_count: int,
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
            'kw_campaign_count' => 0,
            'pt_campaign_count' => 0,
            'kw_keys' => [],
            'pt_keys' => [],
            'kw_parent_keys' => [],
            'pt_parent_keys' => [],
            'kw_campaign_bases' => [],
            'pt_campaign_bases' => [],
            'kw_campaign_names' => [],
            'pt_campaign_names' => [],
            'kw_product_ad_skus' => [],
            'pt_product_ad_skus' => [],
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
        $productAdSkus = $this->buildProductAdSkuLookup($kwCampaigns, $ptCampaigns);
        $advertised = $this->buildAdvertisedCampaignSkuLookup($kwCampaigns, $ptCampaigns);
        foreach (['kw', 'pt'] as $type) {
            foreach ($advertised[$type] as $key => $_) {
                $productAdSkus[$type][$key] = true;
            }
        }

        return [
            'empty' => false,
            'campaign_count' => $campaigns->count(),
            'kw_campaign_count' => $kwCampaigns->count(),
            'pt_campaign_count' => $ptCampaigns->count(),
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
            'kw_product_ad_skus' => $productAdSkus['kw'],
            'pt_product_ad_skus' => $productAdSkus['pt'],
            'kw_product_ad_campaigns' => $advertised['kw_campaigns'],
            'pt_product_ad_campaigns' => $advertised['pt_campaigns'],
        ];
    }

    /**
     * SKUs already pushed as product ads (INV is ignored).
     * Used so Add-to-campaign survives a table refresh.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $kwCampaigns
     * @param  \Illuminate\Support\Collection<int, object>  $ptCampaigns
     * @return array{kw: array<string, true>, pt: array<string, true>}
     */
    private function buildProductAdSkuLookup($kwCampaigns, $ptCampaigns): array
    {
        $out = ['kw' => [], 'pt' => []];
        if (! Schema::hasTable('amazon_sp_product_ads')) {
            return $out;
        }

        $kwIds = [];
        foreach ($kwCampaigns as $c) {
            $cid = preg_replace('/\D+/', '', trim((string) ($c->campaign_id ?? ''))) ?: '';
            if ($cid !== '') {
                $kwIds[$cid] = true;
            }
        }
        $ptIds = [];
        foreach ($ptCampaigns as $c) {
            $cid = preg_replace('/\D+/', '', trim((string) ($c->campaign_id ?? ''))) ?: '';
            if ($cid !== '') {
                $ptIds[$cid] = true;
            }
        }

        $rows = AmazonSpProductAd::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where(function ($q) {
                $q->whereNull('state')
                    ->orWhereRaw('UPPER(TRIM(state)) != ?', ['ARCHIVED']);
            })
            ->get(['sku', 'campaign_id']);

        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $cid = preg_replace('/\D+/', '', trim((string) ($row->campaign_id ?? ''))) ?: '';
            $isKw = $cid !== '' && isset($kwIds[$cid]);
            $isPt = $cid !== '' && isset($ptIds[$cid]);
            // Campaign report missing (just-added) — credit both so the row stays Added.
            if (! $isKw && ! $isPt) {
                $isKw = true;
                $isPt = true;
            }
            foreach ($this->advertisedSkuKeys($sku) as $key) {
                if ($isKw) {
                    $out['kw'][$key] = true;
                }
                if ($isPt) {
                    $out['pt'][$key] = true;
                }
            }
        }

        return $out;
    }

    /**
     * Real SP product ads from /amazon-ads (amazon_ads_campaign_skus).
     * Campaign title does not have to match the CP parent. A live product ad
     * is KW or PT from its campaign id, then from its name. If neither is
     * known, it is kept on both so the SKU is not left missing.
     * Name-derived rows are ignored (they list every child of a parent campaign).
     * Sponsored Brand / headline names are not KW or PT.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $kwCampaigns
     * @param  \Illuminate\Support\Collection<int, object>  $ptCampaigns
     * @return array{
     *   kw: array<string, true>,
     *   pt: array<string, true>,
     *   kw_campaigns: array<string, array<string, true>>,
     *   pt_campaigns: array<string, array<string, true>>
     * }
     */
    private function buildAdvertisedCampaignSkuLookup($kwCampaigns, $ptCampaigns): array
    {
        $out = [
            'kw' => [],
            'pt' => [],
            'kw_campaigns' => [],
            'pt_campaigns' => [],
        ];
        if (! Schema::hasTable('amazon_ads_campaign_skus')) {
            return $out;
        }

        $nameByCampaignId = [];
        $typeByCampaignId = [];
        foreach (['kw' => $kwCampaigns, 'pt' => $ptCampaigns] as $type => $campaigns) {
            foreach ($campaigns as $campaign) {
                $cid = preg_replace('/\D+/', '', trim((string) ($campaign->campaign_id ?? ''))) ?: '';
                $name = trim((string) ($campaign->campaignName ?? ''));
                if ($cid === '') {
                    continue;
                }
                if ($name !== '') {
                    $nameByCampaignId[$cid] = $name;
                }
                if (! isset($typeByCampaignId[$cid])) {
                    $typeByCampaignId[$cid] = $type;
                }
            }
        }

        $rows = AmazonAdsCampaignSku::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('ad_id')
            ->where('ad_id', 'not like', 'name:%')
            ->where('ad_id', 'not like', 'sb:%')
            ->where(function ($q) {
                $q->whereNull('state')
                    ->orWhereRaw('UPPER(TRIM(state)) != ?', ['ARCHIVED']);
            })
            ->get(['sku', 'campaign_id', 'campaign_name']);

        $unresolved = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $cid = preg_replace('/\D+/', '', trim((string) ($row->campaign_id ?? ''))) ?: '';
            $campaignName = trim((string) ($row->campaign_name ?? ''));
            if ($campaignName === '' && $cid !== '') {
                $campaignName = $nameByCampaignId[$cid] ?? '';
            }
            $type = $this->resolveAdvertisedAdType($cid, $campaignName, $typeByCampaignId);
            if ($type === null && $cid !== '' && $campaignName === '' && ! isset($typeByCampaignId[$cid])) {
                $unresolved[$cid][] = $sku;
                continue;
            }
            $this->storeAdvertisedProductAd($out, $sku, $campaignName, $type);
        }

        if ($unresolved !== [] && Schema::hasTable('amazon_sp_campaign_reports')) {
            $extraNames = AmazonSpCampaignReport::query()
                ->whereIn('campaign_id', array_keys($unresolved))
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '')
                ->get(['campaign_id', 'campaignName']);
            foreach ($extraNames as $campaign) {
                $cid = preg_replace('/\D+/', '', trim((string) ($campaign->campaign_id ?? ''))) ?: '';
                $name = trim((string) ($campaign->campaignName ?? ''));
                if ($cid !== '' && $name !== '' && ! isset($nameByCampaignId[$cid])) {
                    $nameByCampaignId[$cid] = $name;
                }
            }
            foreach ($unresolved as $cid => $skus) {
                $campaignName = $nameByCampaignId[$cid] ?? '';
                $type = $this->resolveAdvertisedAdType($cid, $campaignName, $typeByCampaignId);
                foreach ($skus as $sku) {
                    $this->storeAdvertisedProductAd($out, $sku, $campaignName, $type);
                }
            }
        }

        return $out;
    }

    /**
     * Campaign id in the KW/PT report wins. Otherwise the campaign name.
     *
     * @param  array<string, string>  $typeByCampaignId
     */
    private function resolveAdvertisedAdType(string $campaignId, string $campaignName, array $typeByCampaignId): ?string
    {
        if ($campaignId !== '' && isset($typeByCampaignId[$campaignId])) {
            $fromReport = $typeByCampaignId[$campaignId];
            if ($fromReport === 'kw' || $fromReport === 'pt') {
                return $fromReport;
            }
        }

        return $this->campaignReportAdType($campaignName);
    }

    /**
     * @param  array{
     *   kw: array<string, true>,
     *   pt: array<string, true>,
     *   kw_campaigns: array<string, array<string, true>>,
     *   pt_campaigns: array<string, array<string, true>>
     * }  $bucket
     */
    private function storeAdvertisedProductAd(array &$bucket, string $sku, string $campaignName, ?string $type): void
    {
        if ($type === 'kw' || $type === 'pt') {
            $this->rememberAdvertisedSku($bucket, $sku, $campaignName, $type);

            return;
        }
        if ($this->isHeadlineCampaignName($campaignName)) {
            return;
        }
        // Real product ad with no KW/PT label — do not leave it missing.
        $this->rememberAdvertisedSku($bucket, $sku, $campaignName, 'kw');
        $this->rememberAdvertisedSku($bucket, $sku, $campaignName, 'pt');
    }

    /**
     * KW unless the campaign name is PT. Headline / HL names are not KW or PT.
     */
    private function campaignReportAdType(string $campaignName): ?string
    {
        $cn = $this->normalizeCampaignToken($campaignName);
        if ($cn === '') {
            return null;
        }
        if (preg_match('/\s+(HEAD|HL)$/', $cn)) {
            return null;
        }
        if (preg_match('/\s+PT$/', $cn)) {
            return 'pt';
        }

        return 'kw';
    }

    private function isHeadlineCampaignName(string $campaignName): bool
    {
        $cn = $this->normalizeCampaignToken($campaignName);

        return $cn !== '' && (bool) preg_match('/\s+(HEAD|HL)$/', $cn);
    }

    /**
     * @return list<string>
     */
    private function advertisedSkuKeys(string $sku): array
    {
        $norm = AmazonDatasheet::normalizeSkuForLookup($sku);
        $nameKey = strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)));
        $nameKey = preg_replace('/\s+/', ' ', $nameKey) ?? $nameKey;
        $nameKey = rtrim($nameKey, '.');
        $keys = [];
        foreach ([$norm, $nameKey] as $key) {
            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * @param  array{
     *   kw: array<string, true>,
     *   pt: array<string, true>,
     *   kw_campaigns: array<string, array<string, true>>,
     *   pt_campaigns: array<string, array<string, true>>
     * }  $bucket
     */
    private function rememberAdvertisedSku(array &$bucket, string $sku, string $campaignName, ?string $type = null): void
    {
        $type = ($type === 'kw' || $type === 'pt') ? $type : $this->campaignReportAdType($campaignName);
        if ($type !== 'kw' && $type !== 'pt') {
            return;
        }

        $rawName = trim($campaignName);
        $skuMapKey = $type;
        $campaignMapKey = $type.'_campaigns';

        foreach ($this->advertisedSkuKeys($sku) as $key) {
            $bucket[$skuMapKey][$key] = true;
            if ($rawName !== '') {
                $bucket[$campaignMapKey][$key][$rawName] = true;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @return list<string>
     */
    private function productAdCampaignNamesForSku(string $sku, array $lookup, string $type): array
    {
        $map = $type === 'pt'
            ? ($lookup['pt_product_ad_campaigns'] ?? [])
            : ($lookup['kw_product_ad_campaigns'] ?? []);
        if ($map === []) {
            return [];
        }

        $found = [];
        foreach ($this->advertisedSkuKeys($sku) as $key) {
            if ($key === '' || empty($map[$key]) || ! is_array($map[$key])) {
                continue;
            }
            foreach ($map[$key] as $name => $_) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $found[$name] = true;
                }
            }
        }

        $list = array_keys($found);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * @param  list<string>  ...$lists
     * @return list<string>
     */
    private function mergeCampaignNameLists(array ...$lists): array
    {
        $out = [];
        foreach ($lists as $list) {
            foreach ($list as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $out[$name] = true;
                }
            }
        }

        $names = array_keys($out);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
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
     *  2) a product ad for this SKU is in a KW/PT campaign
     *     (amazon_sp_product_ads or amazon_ads_campaign_skus; INV ignored), OR
     *  3) a PARENT {parent} campaign exists AND this child is listed on Amazon
     *     (unlisted children stay Missing unless explicitly added via #2).
     *
     * @param  array{
     *   empty: bool,
     *   kw_keys: array<string, true>,
     *   pt_keys: array<string, true>,
     *   kw_parent_keys: array<string, true>,
     *   pt_parent_keys: array<string, true>,
     *   kw_product_ad_skus: array<string, true>,
     *   pt_product_ad_skus: array<string, true>
     * }  $lookup
     */
    private function skuHasCampaignType(string $sku, string $parent, ?bool $available, array $lookup, string $type): ?bool
    {
        if ($lookup['empty']) {
            return null;
        }

        $keys = $type === 'pt' ? ($lookup['pt_keys'] ?? []) : ($lookup['kw_keys'] ?? []);
        $adSkus = $type === 'pt' ? ($lookup['pt_product_ad_skus'] ?? []) : ($lookup['kw_product_ad_skus'] ?? []);

        foreach ($this->advertisedSkuKeys($sku) as $key) {
            if (isset($keys[$key]) || isset($adSkus[$key])) {
                return true;
            }
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
     * Coming, Amazon NRL, and INV ≤ 1 are never flagged as Missing and are not required.
     *
     * @return array{status: ?string, label: string, existing: bool, missing: bool, over: bool}
     */
    private function buildSiblingAdFields(
        ?bool $inCampaign,
        ?bool $available,
        bool $adsEmpty,
        bool $isComing = false,
        bool $isLowInv = false,
        bool $isNrl = false
    ): array {
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

        $skipMissing = $isComing || $isNrl || $isLowInv;

        // Missing: not in campaign (Coming, NRL, and INV ≤ 1 are excluded)
        $missing = $inCampaign === false && ! $skipMissing;

        if ($over) {
            $status = 'over';
            $label = 'Over';
        } elseif ($existing) {
            $status = 'added';
            $label = 'Added';
        } elseif ($isComing) {
            $status = 'coming';
            $label = 'Coming';
        } elseif ($isNrl) {
            $status = 'nrl';
            $label = 'NRL';
        } elseif ($isLowInv) {
            $status = 'low_inv';
            $label = 'INV ≤1';
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
     * Parent rollup for KW or PT siblings — required children only (Coming / NRL / INV ≤ 1 skipped) + extras.
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

        $requiredChildren = array_values(array_filter($children, fn ($c) => empty($c['skip_ads_required']) && empty($c['is_coming']) && empty($c['is_nrl']) && empty($c['is_low_inv'])));
        $required = count($requiredChildren);
        $existing = count(array_filter($requiredChildren, fn ($c) => ! empty($c[$prefix . 'existing'])));

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
     * Ads → CP Master extras for this parent.
     *
     * Extra is a campaign explicitly named under this parent
     * (`PARENT {parent} {suffix}`) that is not a CP Master child and is not
     * owned by a more specific CP parent. Other campaigns that only share a
     * name prefix (e.g. "DS VEL RED REST-LVR" vs parent "DS VEL") are their
     * own ads — not extra SKUs inside PARENT {parent}.
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

        $requiredTokens = [];
        foreach ($children as $c) {
            $sku = trim((string) ($c['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $tok = $this->normalizeCampaignToken($sku);
            if ($tok !== '') {
                $requiredTokens[$tok] = true;
            }
        }

        $parentTok = $this->normalizeCampaignToken($parentKey);

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

            // Exact parent campaign (PARENT {parent} / {parent}) — not Extra.
            if ($base === $parentTok || $base === 'PARENT '.$parentTok) {
                continue;
            }

            // Campaign named as another CP parent — belongs there.
            if (isset($allParentToks[$base]) && $base !== $parentTok) {
                continue;
            }

            if (! str_starts_with($base, 'PARENT ')) {
                continue;
            }

            $body = trim(substr($base, 7));
            if ($body === '' || $body === $parentTok) {
                continue;
            }

            // PARENT {otherParent} — belongs to that parent, even without a suffix.
            if (isset($allParentToks[$body]) && $body !== $parentTok) {
                continue;
            }

            // Named for a required child of this parent — not Extra.
            if (isset($requiredTokens[$body])) {
                continue;
            }
            $childHit = false;
            foreach ($requiredTokens as $tok => $_) {
                if ($body === $tok || str_starts_with($body, $tok.' ')) {
                    $childHit = true;
                    break;
                }
            }
            if ($childHit) {
                continue;
            }

            $norm = AmazonDatasheet::normalizeSkuForLookup($body);
            $pmParent = $norm !== '' ? ($pmParentByNorm[$norm] ?? null) : null;
            if ($pmParent !== null) {
                $pmTok = $this->normalizeCampaignToken($pmParent);
                if ($pmTok !== '' && $pmTok !== $parentTok) {
                    continue;
                }
                if ($pmTok === $parentTok) {
                    continue;
                }
            }

            // Only Extra when this parent is the most specific CP owner of the body.
            // "PARENT DS VEL REST LVR LT" belongs to "DS VEL REST LVR", not "DS VEL".
            if ($this->longestMatchingParentToken($body, $allParentToks) !== $parentTok) {
                continue;
            }

            $extras[$body] = $body;
        }

        $list = array_values($extras);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * Most specific CP parent whose name equals $name or is a space-delimited prefix.
     *
     * @param  array<string, true>  $allParentToks
     */
    private function longestMatchingParentToken(string $name, array $allParentToks): ?string
    {
        $name = $this->normalizeCampaignToken($name);
        if ($name === '' || $allParentToks === []) {
            return null;
        }

        $best = null;
        $bestLen = -1;
        foreach ($allParentToks as $tok => $_) {
            $tok = $this->normalizeCampaignToken((string) $tok);
            if ($tok === '') {
                continue;
            }
            if ($name !== $tok && ! str_starts_with($name, $tok.' ')) {
                continue;
            }
            $len = strlen($tok);
            if ($len > $bestLen) {
                $best = $tok;
                $bestLen = $len;
            }
        }

        return $best;
    }

    /**
     * Status from the PARENT {parent} (or sku=parent) Product Master row.
     *
     * @param  list<string>  $parentKeys
     * @return array<string, array{pm_status: string, pm_status_label: string}>
     */
    private function buildParentProductMasterStatusMap(array $parentKeys): array
    {
        $map = [];
        $parentKeys = array_values(array_filter(array_map(
            fn ($p) => trim((string) $p),
            $parentKeys
        )));
        if ($parentKeys === []) {
            return $map;
        }

        $rows = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($parentKeys) {
                $q->where(function ($q2) use ($parentKeys) {
                    $q2->whereIn('parent', $parentKeys)
                        ->whereRaw('UPPER(TRIM(sku)) LIKE ?', ['PARENT%']);
                })->orWhereIn('sku', $parentKeys);
            })
            ->get(['sku', 'parent', 'Values']);

        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            $parent = trim((string) ($row->parent ?? ''));
            $isParentSku = (bool) preg_match('/^PARENT\s+/i', $sku);
            $key = $isParentSku
                ? ($parent !== '' ? $parent : trim((string) preg_replace('/^PARENT\s+/i', '', $sku)))
                : $sku;
            if ($key === '') {
                continue;
            }

            $payload = [
                'pm_status' => $row->statusValue(),
                'pm_status_label' => $row->statusLabel(),
            ];

            if ($isParentSku || ! isset($map[$key])) {
                $map[$key] = $payload;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array{pm_status?: string, pm_status_label?: string}>  $children
     * @return array{pm_status: string, pm_status_label: string}
     */
    private function rollupChildProductMasterStatus(array $children): array
    {
        $byLabel = [];
        foreach ($children as $child) {
            $label = trim((string) ($child['pm_status_label'] ?? ''));
            $raw = trim((string) ($child['pm_status'] ?? ''));
            if ($label === '' && $raw === '') {
                continue;
            }
            $key = $label !== '' ? $label : ProductMaster::statusDisplayLabel($raw);
            if ($key === '') {
                continue;
            }
            if (! isset($byLabel[$key])) {
                $byLabel[$key] = $raw !== '' ? $raw : $key;
            }
        }

        if ($byLabel === []) {
            return ['pm_status' => '', 'pm_status_label' => ''];
        }

        return [
            'pm_status' => implode(',', array_values($byLabel)),
            'pm_status_label' => implode(', ', array_keys($byLabel)),
        ];
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
