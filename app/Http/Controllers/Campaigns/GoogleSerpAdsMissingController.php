<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\ShopifySku;
use App\Support\GoogleShoppingCampaignNameMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GoogleSerpAdsMissingController extends Controller
{
    private const CAMPAIGNS_TABLE = 'google_ads_campaigns';

    private const SIDEBAR_COUNT_CACHE_KEY = 'google_serp_ads_missing_sidebar_count';

    public function index()
    {
        return view('campaign.google_serp_ads_missing');
    }

    /**
     * In-stock parents with no matching Google SERP (SEARCH) campaign.
     * Cached briefly for the left-sidebar badge.
     */
    public static function missingTotalCount(): int
    {
        try {
            return (int) Cache::remember(self::SIDEBAR_COUNT_CACHE_KEY, 300, function () {
                return (new self)->computeMissingTotal();
            });
        } catch (\Throwable $e) {
            try {
                return (new self)->computeMissingTotal();
            } catch (\Throwable $e2) {
                return 0;
            }
        }
    }

    public static function forgetMissingTotalCache(): void
    {
        Cache::forget(self::SIDEBAR_COUNT_CACHE_KEY);
    }

    /**
     * One synthetic parent row per distinct parent — same method as Missing Google Shopping Ads:
     * soft-deleted and DC rows skipped; inventory = SUM child Shopify inv. Campaigns are
     * auto-matched from google_ads_campaigns whose names contain the word "SEARCH"
     * (same scope as /google/shopping/google-serp), after stripping the SEARCH suffix.
     */
    public function data(): JsonResponse
    {
        if (! Schema::hasTable('product_master')) {
            return response()->json(['data' => []]);
        }

        $rows = DB::table('product_master')
            ->select('parent', 'sku', 'Values')
            ->whereNull('deleted_at')
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        $inventoryByParent = $this->buildInventorySumByParent($rows);

        $parents = [];
        foreach ($rows as $r) {
            $sku = trim((string) ($r->sku ?? ''));
            if ($sku === '' || Str::startsWith(strtoupper($sku), 'PARENT') || $this->isDcProduct($r)) {
                continue;
            }
            $parent = preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? '')));
            if ($parent === '') {
                continue;
            }
            $parents[strtoupper($parent)] = $parent;
        }
        ksort($parents);

        $campaignsByParentSku = $this->matchedCampaignsByParentSku(array_values($parents));

        $data = collect($parents)->map(function ($parent, $parentKey) use ($inventoryByParent, $campaignsByParentSku) {
            $sku = 'PARENT '.$parent;

            return [
                'parent' => $parent,
                'sku' => $sku,
                'is_parent' => true,
                'inventory' => (int) round($inventoryByParent[$parentKey] ?? 0),
                'campaigns' => $campaignsByParentSku[$sku] ?? [],
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    private function computeMissingTotal(): int
    {
        if (! Schema::hasTable('product_master')) {
            return 0;
        }

        $rows = DB::table('product_master')
            ->select('parent', 'sku', 'Values')
            ->whereNull('deleted_at')
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        $inventoryByParent = $this->buildInventorySumByParent($rows);

        $parents = [];
        foreach ($rows as $r) {
            $sku = trim((string) ($r->sku ?? ''));
            if ($sku === '' || Str::startsWith(strtoupper($sku), 'PARENT') || $this->isDcProduct($r)) {
                continue;
            }
            $parent = preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? '')));
            if ($parent === '') {
                continue;
            }
            $parents[strtoupper($parent)] = $parent;
        }

        $campaignsByParentSku = $this->matchedCampaignsByParentSku(array_values($parents));

        $total = 0;
        foreach ($parents as $parentKey => $parent) {
            if ((int) round($inventoryByParent[$parentKey] ?? 0) <= 0) {
                continue;
            }
            $sku = 'PARENT '.$parent;
            if (($campaignsByParentSku[$sku] ?? []) === []) {
                $total++;
            }
        }

        return $total;
    }

    /**
     * Latest SERP campaigns (name contains " SEARCH") matched to each PARENT sku.
     * Matching strips the SEARCH suffix then uses GoogleShoppingCampaignNameMatcher
     * (same idea as UpdateSerpBudgetCronCommand).
     *
     * @param  list<string>  $parents
     * @return array<string, list<array{campaign_id: string, campaign_name: string, status: string, dot: string}>>
     */
    private function matchedCampaignsByParentSku(array $parents): array
    {
        if ($parents === [] || ! Schema::hasTable(self::CAMPAIGNS_TABLE)) {
            return [];
        }

        // Same name scope as /google/shopping/google-serp.
        $query = DB::table(self::CAMPAIGNS_TABLE)
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->whereRaw('UPPER(campaign_name) LIKE ?', ['% SEARCH%']);

        $latestIds = (clone $query)
            ->selectRaw('MAX(id) AS max_id')
            ->groupBy('campaign_id')
            ->pluck('max_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($latestIds === []) {
            return [];
        }

        $campaignRows = DB::table(self::CAMPAIGNS_TABLE)
            ->whereIn('id', $latestIds)
            ->get(['campaign_id', 'campaign_name', 'campaign_status']);

        $out = [];
        foreach ($parents as $parent) {
            $sku = 'PARENT '.$parent;
            $matched = [];
            $seen = [];
            foreach ($campaignRows as $c) {
                $name = (string) ($c->campaign_name ?? '');
                $base = $this->serpCampaignBaseName($name);
                if ($base === '' || ! GoogleShoppingCampaignNameMatcher::matches($base, $sku)) {
                    continue;
                }
                $cid = (string) ($c->campaign_id ?? '');
                if ($cid !== '' && isset($seen[$cid])) {
                    continue;
                }
                if ($cid !== '') {
                    $seen[$cid] = true;
                }
                $status = strtoupper(trim((string) ($c->campaign_status ?? '')));
                $dot = $status === 'ENABLED' ? 'green' : ($status !== '' ? 'red' : '');
                $matched[] = [
                    'campaign_id' => $cid,
                    'campaign_name' => $name,
                    'status' => $status,
                    'dot' => $dot,
                ];
            }
            if ($matched !== []) {
                $out[$sku] = $matched;
            }
        }

        return $out;
    }

    /**
     * Strip trailing " SEARCH" / " SEARCH." from a SERP campaign name for PARENT matching.
     */
    private function serpCampaignBaseName(string $campaignName): string
    {
        $norm = GoogleShoppingCampaignNameMatcher::normalize($campaignName);
        if (str_ends_with($norm, ' SEARCH')) {
            $norm = rtrim(substr($norm, 0, -strlen(' SEARCH')));
        }

        return GoogleShoppingCampaignNameMatcher::normalize($norm);
    }

    /**
     * @param  \Illuminate\Support\Collection  $rows
     * @return array<string, float>
     */
    private function buildInventorySumByParent($rows): array
    {
        $childSkus = [];
        foreach ($rows as $r) {
            $s = trim((string) ($r->sku ?? ''));
            if ($s === '' || Str::startsWith(strtoupper($s), 'PARENT') || $this->isDcProduct($r)) {
                continue;
            }
            $childSkus[] = $s;
        }

        $shopifyByPmSku = ShopifySku::mapByProductSkus(array_values(array_unique($childSkus)));

        $totals = [];
        foreach ($rows as $r) {
            $s = trim((string) ($r->sku ?? ''));
            if ($s === '' || Str::startsWith(strtoupper($s), 'PARENT') || $this->isDcProduct($r)) {
                continue;
            }
            $pKey = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? ''))));
            if ($pKey === '') {
                continue;
            }
            $rec = $shopifyByPmSku->get($s);
            $totals[$pKey] = ($totals[$pKey] ?? 0) + (float) ($rec?->inv ?? 0);
        }

        return $totals;
    }

    private function isDcProduct(object $row): bool
    {
        $values = $row->Values ?? null;
        if (is_string($values)) {
            $values = json_decode($values, true);
        }
        if (! is_array($values)) {
            return false;
        }

        return strtoupper(trim((string) ($values['status'] ?? ''))) === 'DC';
    }
}
