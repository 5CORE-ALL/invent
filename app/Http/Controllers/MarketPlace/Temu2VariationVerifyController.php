<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\Temu2CampaignReport;
use App\Models\Temu2Pricing;
use App\Support\TemuGoodsIdHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Temu2VariationVerifyController extends Controller
{
    public function index()
    {
        return view('market-places.temu2_variation_verify');
    }

    /**
     * Tabulator data for Temu 2 Ads Variation Verification.
     * Listings: temu2_pricing
     * Ads: temu2_campaign_reports (L30) — same goods_id → SKU → loose SKU match as Temu 2 Analytics
     *
     * Ads existing = in campaign AND inv >= 0
     * Over = in campaign but NOT listed in temu2_pricing
     */
    public function data(Request $request)
    {
        $listedSkuSet = $this->buildListedSkuLookup();
        $adLookup = $this->buildAdCampaignLookupFromReports();
        $goodsIdBySku = $this->buildGoodsIdBySkuLookup();

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

        $childRows = $productMasters->map(function ($pm) use ($listedSkuSet, $adLookup, $shopifyBySku, $goodsIdBySku) {
            $parent = trim((string) ($pm->parent ?? ''));
            $sku = trim((string) ($pm->sku ?? ''));
            $available = $this->isSkuListed($sku, $listedSkuSet);
            $match = $available === null ? null : ($available === true);

            $inv = (float) ($shopifyBySku[$sku]->inv ?? 0);
            $invEligible = $inv >= 0;

            $hasAd = $this->skuHasCampaign($sku, $adLookup, $goodsIdBySku);
            $adFields = $this->buildSiblingAdFields($hasAd, $invEligible, $available, $adLookup['empty']);

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
            ], $this->prefixAdFields('ad', $adFields));
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

            $adRollup = $this->rollupSiblingAds($children, 'ad', $adLookup['empty']);

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
            ], $this->prefixAdFields('ad', $adRollup));
        }

        $listingsCount = (int) Temu2Pricing::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
        $lastPulledAt = Temu2Pricing::query()->max('updated_at');
        $campaignCount = $adLookup['campaign_count'] ?? 0;

        return response()->json([
            'data' => $formattedData,
            'meta' => [
                'listings_count' => $listingsCount,
                'last_pulled_at' => $lastPulledAt,
                'has_listings_cache' => $listingsCount > 0,
                'required_parent_count' => count($parentGroups),
                'required_child_count' => count($childRows),
                'required_refreshed_at' => now()->toDateTimeString(),
                'ads_count' => $campaignCount,
                'ads_pulled_at' => null,
                'has_ads_cache' => ! $adLookup['empty'],
                'ads_source' => 'temu2_campaign_reports (L30)',
            ],
        ]);
    }

    /**
     * Temu 2 listings come from the temu2_pricing Excel upload (no API pull).
     */
    public function pullListings(Request $request)
    {
        try {
            $count = (int) Temu2Pricing::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
            $lastPulledAt = Temu2Pricing::query()->max('updated_at');

            if ($count === 0) {
                return response()->json([
                    'status' => 422,
                    'message' => 'No Temu 2 listings in temu2_pricing. Upload pricing on Temu 2 Analytics first.',
                    'count' => 0,
                    'last_pulled_at' => $lastPulledAt,
                ], 422);
            }

            return response()->json([
                'status' => 200,
                'message' => "Temu 2 listings ready. {$count} SKUs in temu2_pricing.",
                'count' => $count,
                'last_pulled_at' => $lastPulledAt,
            ]);
        } catch (\Throwable $e) {
            Log::error('Temu 2 Ads Variation Verification: pull listings failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Pull failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Same SKU normalize as Temu 2 Analytics (PCS folding + space collapse).
     */
    private function normalizeSku(?string $sku): string
    {
        $sku = strtoupper(trim((string) $sku));
        $sku = str_replace("\xC2\xA0", ' ', $sku);
        $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
        $sku = preg_replace('/\s+/', ' ', $sku);

        return $sku;
    }

    /**
     * Alphanumeric-only SKU (Temu 2 Analytics loose fallback).
     */
    private function normalizeSkuLoose(?string $sku): string
    {
        $s = strtoupper(trim((string) $sku));
        if ($s === '') {
            return '';
        }

        return preg_replace('/[^A-Z0-9]/', '', $s);
    }

    /**
     * @return array{set: array<string, true>, empty: bool}
     */
    private function buildListedSkuLookup(): array
    {
        $set = [];

        foreach (Temu2Pricing::query()->whereNotNull('sku')->where('sku', '!=', '')->pluck('sku') as $sku) {
            $norm = $this->normalizeSku($sku);
            if ($norm !== '') {
                $set[$norm] = true;
            }
        }

        return [
            'set' => $set,
            'empty' => empty($set),
        ];
    }

    /**
     * Normalized SKU → normalized goods_id from temu2_pricing.
     *
     * @return array<string, string>
     */
    private function buildGoodsIdBySkuLookup(): array
    {
        $map = [];

        if (! Schema::hasTable('temu2_pricing')) {
            return $map;
        }

        Temu2Pricing::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'goods_id'])
            ->each(function ($row) use (&$map) {
                $norm = $this->normalizeSku($row->sku);
                $gid = TemuGoodsIdHelper::normalizeKey($row->goods_id);
                if ($norm !== '' && $gid !== null && $gid !== '') {
                    $map[$norm] = $gid;
                }
            });

        return $map;
    }

    /**
     * Build L30 campaign presence indexes (goods_id / SKU / loose SKU).
     *
     * @return array{
     *   empty: bool,
     *   campaign_count: int,
     *   by_goods_id: array<string, true>,
     *   by_sku: array<string, true>,
     *   by_sku_loose: array<string, true>
     * }
     */
    private function buildAdCampaignLookupFromReports(): array
    {
        $empty = [
            'empty' => true,
            'campaign_count' => 0,
            'by_goods_id' => [],
            'by_sku' => [],
            'by_sku_loose' => [],
        ];

        if (! Schema::hasTable('temu2_campaign_reports')) {
            return $empty;
        }

        $rows = Temu2CampaignReport::query()
            ->where('report_range', 'L30')
            ->get(['goods_id', 'sku']);

        if ($rows->isEmpty()) {
            return $empty;
        }

        $byGoodsId = [];
        $bySku = [];
        $bySkuLoose = [];

        foreach ($rows as $row) {
            $gid = TemuGoodsIdHelper::normalizeKey($row->goods_id);
            if ($gid !== null && $gid !== '') {
                $byGoodsId[$gid] = true;
            }

            $skuNorm = $this->normalizeSku($row->sku ?? '');
            if ($skuNorm !== '') {
                $bySku[$skuNorm] = true;
            }

            $skuLoose = $this->normalizeSkuLoose($row->sku ?? '');
            if ($skuLoose !== '') {
                $bySkuLoose[$skuLoose] = true;
            }
        }

        return [
            'empty' => false,
            'campaign_count' => $rows->count(),
            'by_goods_id' => $byGoodsId,
            'by_sku' => $bySku,
            'by_sku_loose' => $bySkuLoose,
        ];
    }

    /**
     * Same match chain as Temu 2 Analytics: goods_id → strict SKU → loose SKU.
     *
     * @param  array{empty: bool, by_goods_id: array<string, true>, by_sku: array<string, true>, by_sku_loose: array<string, true>}  $lookup
     * @param  array<string, string>  $goodsIdBySku
     */
    private function skuHasCampaign(string $sku, array $lookup, array $goodsIdBySku): ?bool
    {
        if ($lookup['empty']) {
            return null;
        }

        $norm = $this->normalizeSku($sku);
        $loose = $this->normalizeSkuLoose($sku);
        $goodsIdKey = $norm !== '' ? ($goodsIdBySku[$norm] ?? null) : null;

        if ($goodsIdKey !== null && isset($lookup['by_goods_id'][$goodsIdKey])) {
            return true;
        }
        if ($norm !== '' && isset($lookup['by_sku'][$norm])) {
            return true;
        }
        if ($loose !== '' && isset($lookup['by_sku_loose'][$loose])) {
            return true;
        }

        return false;
    }

    /**
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

        $over = $inCampaign === true && $available === false;
        $existing = $inCampaign === true && $invEligible;
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

        $norm = $this->normalizeSku($sku);
        if ($norm === '') {
            return false;
        }

        return isset($lookup['set'][$norm]);
    }
}
