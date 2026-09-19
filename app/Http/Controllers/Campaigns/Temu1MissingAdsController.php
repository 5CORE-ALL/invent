<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TemuAdsApiReport;
use App\Models\TemuMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Temu 1 Missing Ads — goods with Status "No ad" and Shopify Inv > 0.
 * Same create eligibility as the Create badge on /temu/ads.
 */
class Temu1MissingAdsController extends Controller
{
    private const SIDEBAR_COUNT_CACHE_KEY = 'temu1_ads_missing_sidebar_count';

    public function index()
    {
        return view('campaign.temu.temu1-missing-ads');
    }

    /**
     * In-stock Temu 1 goods with no campaign (Status No ad), unique by goods_id.
     */
    public static function missingTotalCount(): int
    {
        try {
            $cached = Cache::get(self::SIDEBAR_COUNT_CACHE_KEY);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $total = (new static)->collectMissingRows(false)->count();
            try {
                Cache::put(self::SIDEBAR_COUNT_CACHE_KEY, $total, now()->addMinutes(5));
            } catch (\Throwable $e) {
                // ignore
            }

            return $total;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function forgetMissingTotalCache(): void
    {
        try {
            Cache::forget(self::SIDEBAR_COUNT_CACHE_KEY);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function data(): JsonResponse
    {
        $rows = $this->collectMissingRows();

        try {
            Cache::put(self::SIDEBAR_COUNT_CACHE_KEY, $rows->count(), now()->addMinutes(5));
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json([
            'data' => $rows->values(),
            'total' => $rows->count(),
        ]);
    }

    /**
     * Unique missing-ad goods: Status is No ad (or no ads report yet) and Inv > 0.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function collectMissingRows(bool $withImages = true): Collection
    {
        if (! Schema::hasTable('temu_ads_api_reports') && ! Schema::hasTable('temu_metrics')) {
            return collect();
        }

        $hasCreateReject = Schema::hasTable('temu_ads_api_reports')
            && Schema::hasColumn('temu_ads_api_reports', 'ad_create_reject');

        $candidates = collect();
        foreach ($this->noAdReportsByGoodsId($hasCreateReject) as $report) {
            $gid = trim((string) ($report->goods_id ?? ''));
            if ($gid === '') {
                continue;
            }
            $candidates[$gid] = [
                'goods_id' => $gid,
                'sku' => trim((string) ($report->sku ?? '')),
                'ad_status' => $report->displayAdStatus(),
                'ad_create_reject' => $hasCreateReject ? trim((string) ($report->ad_create_reject ?? '')) : '',
                'clicks_l7' => 0,
                'clicks_l30' => (int) ($report->period === 'L30' ? ($report->clicks ?? 0) : 0),
                'impressions' => (int) ($report->impressions ?? 0),
            ];
        }

        foreach ($this->metricsWithoutAdsReport() as $metric) {
            $gid = trim((string) ($metric->goods_id ?? ''));
            if ($gid === '' || isset($candidates[$gid])) {
                continue;
            }
            $candidates[$gid] = [
                'goods_id' => $gid,
                'sku' => trim((string) ($metric->sku ?? '')),
                'ad_status' => 'No ad',
                'ad_create_reject' => '',
                'clicks_l7' => 0,
                'clicks_l30' => 0,
                'impressions' => 0,
            ];
        }

        if ($candidates->isEmpty()) {
            return collect();
        }

        if ($withImages) {
            $this->attachL7Clicks($candidates);
        }

        $skus = $candidates->pluck('sku')
            ->filter(fn ($s) => $s !== null && trim((string) $s) !== '')
            ->map(fn ($s) => (string) $s)
            ->unique()
            ->values()
            ->all();
        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku($skus);
        $productMasterByNorm = $withImages ? $this->productMasterByNormalizedSku($skus) : [];

        return $candidates
            ->map(function (array $row) use ($shopifyByNorm, $productMasterByNorm, $withImages) {
                $skuKey = ShopifySku::normalizeSkuForShopifyLookup((string) ($row['sku'] ?? ''));
                $shopify = $skuKey !== '' ? ($shopifyByNorm[$skuKey] ?? null) : null;
                $productMaster = $withImages && $skuKey !== '' ? ($productMasterByNorm[$skuKey] ?? null) : null;
                $soldQty = $shopify ? (float) ($shopify->quantity ?? $shopify->shopify_l30 ?? 0) : 0;
                $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;
                $ovl30 = (int) round($soldQty);
                $dilPercent = $inv > 0 ? round(($ovl30 / $inv) * 100, 2) : 0;

                $row['image_path'] = $withImages ? $this->productMasterImagePath($productMaster, $shopify) : null;
                $row['inv'] = $inv;
                $row['ovl30'] = $ovl30;
                $row['dil_percent'] = $dilPercent;

                return $row;
            })
            ->filter(fn (array $row) => (int) ($row['inv'] ?? 0) > 0)
            ->sortBy(fn (array $row) => [strtoupper((string) ($row['sku'] ?? '')), (string) ($row['goods_id'] ?? '')])
            ->values();
    }

    /**
     * @return Collection<string, TemuAdsApiReport>
     */
    private function noAdReportsByGoodsId(bool $hasCreateReject): Collection
    {
        if (! Schema::hasTable('temu_ads_api_reports')) {
            return collect();
        }

        $cols = ['id', 'goods_id', 'sku', 'period', 'impressions', 'clicks', 'ad_status', 'updated_at'];
        if ($hasCreateReject) {
            $cols[] = 'ad_create_reject';
        }

        return TemuAdsApiReport::query()
            ->where('ad_status', 'No ad')
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->orderByRaw("CASE period WHEN 'L30' THEN 0 WHEN 'L7' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get($cols)
            ->unique(fn (TemuAdsApiReport $r) => (string) $r->goods_id)
            ->keyBy(fn (TemuAdsApiReport $r) => (string) $r->goods_id);
    }

    /**
     * Temu 1 listings that have a goods_id but no ads-report row yet.
     *
     * @return Collection<int, TemuMetric>
     */
    private function metricsWithoutAdsReport(): Collection
    {
        if (! Schema::hasTable('temu_metrics')) {
            return collect();
        }

        $reported = [];
        if (Schema::hasTable('temu_ads_api_reports')) {
            foreach (TemuAdsApiReport::query()
                ->whereNotNull('goods_id')
                ->where('goods_id', '!=', '')
                ->pluck('goods_id') as $gid
            ) {
                $reported[(string) $gid] = true;
            }
        }

        return TemuMetric::query()
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->get(['goods_id', 'sku'])
            ->filter(function (TemuMetric $m) use ($reported) {
                $gid = trim((string) $m->goods_id);

                return $gid !== '' && ! isset($reported[$gid]);
            })
            ->values();
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $candidates
     */
    private function attachL7Clicks(Collection $candidates): void
    {
        if (! Schema::hasTable('temu_ads_api_reports') || $candidates->isEmpty()) {
            return;
        }

        $l7 = TemuAdsApiReport::query()
            ->where('period', 'L7')
            ->whereIn('goods_id', $candidates->keys()->all())
            ->get(['goods_id', 'clicks'])
            ->keyBy(fn (TemuAdsApiReport $r) => (string) $r->goods_id);

        foreach ($candidates as $gid => $row) {
            $row['clicks_l7'] = (int) (optional($l7->get((string) $gid))->clicks ?? 0);
            $candidates[$gid] = $row;
        }
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, ProductMaster>
     */
    private function productMasterByNormalizedSku(array $skus): array
    {
        $wanted = [];
        foreach ($skus as $sku) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($key !== '') {
                $wanted[$key] = true;
            }
        }
        if ($wanted === []) {
            return [];
        }

        $out = [];
        foreach (ProductMaster::query()->whereIn('sku', $skus)->get(['id', 'sku', 'Values', 'main_image']) as $pm) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $pm->sku);
            if ($key !== '' && isset($wanted[$key]) && ! isset($out[$key])) {
                $out[$key] = $pm;
            }
        }

        return $out;
    }

    private function productMasterImagePath(?ProductMaster $productMaster, ?ShopifySku $shopify): ?string
    {
        $values = is_array($productMaster?->Values)
            ? $productMaster->Values
            : (is_string($productMaster?->Values) ? (json_decode((string) $productMaster->Values, true) ?: []) : []);
        $local = trim((string) ($values['image_path'] ?? $productMaster?->main_image ?? ''));
        $shopifyImage = trim((string) ($shopify?->image_src ?? ''));

        if ($local !== '' && (str_contains($local, 'storage/') || str_contains($local, '/storage/'))) {
            return '/'.ltrim($local, '/');
        }
        if ($shopifyImage !== '') {
            return $shopifyImage;
        }
        if ($local === '') {
            return null;
        }
        if (str_starts_with($local, 'http://') || str_starts_with($local, 'https://')) {
            return $local;
        }

        return '/'.ltrim($local, '/');
    }
}
