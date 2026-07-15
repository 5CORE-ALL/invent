<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\GoogleAdsMissingLink;
use App\Models\ShopifySku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GoogleSerpAdsMissingController extends Controller
{
    private const CAMPAIGNS_TABLE = 'google_ads_campaigns';

    private const CHANNEL = 'serp';

    private const SIDEBAR_COUNT_CACHE_KEY = 'google_serp_ads_missing_sidebar_count';

    /** @var Collection<string, Collection<int, GoogleAdsMissingLink>>|null */
    private ?Collection $manualLinksCache = null;

    /** @var array<string, string>|null */
    private ?array $campaignStatusMapCache = null;

    public function index()
    {
        return view('campaign.google_serp_ads_missing');
    }

    /**
     * In-stock parents with no manually linked Google SERP campaign.
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
     * soft-deleted and DC rows skipped; inventory = SUM child Shopify inv.
     * Campaigns are manual links only (via +), same UX as /amazon-ads/missing.
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

        // Build status map once for linked names only (avoids N× queries on google_ads_campaigns).
        $manualBySku = $this->manualLinksBySku();
        $statusMap = $this->campaignStatusMapForLinks($manualBySku);

        $data = collect($parents)->map(function ($parent, $parentKey) use ($inventoryByParent, $manualBySku, $statusMap) {
            $sku = 'PARENT '.$parent;

            return [
                'parent' => $parent,
                'sku' => $sku,
                'is_parent' => true,
                'inventory' => (int) round($inventoryByParent[$parentKey] ?? 0),
                'campaigns' => $this->formatManualCampaigns($manualBySku->get($sku) ?? collect(), $statusMap),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * Distinct Google SERP (SEARCH) campaigns for the manual link picker.
     */
    public function campaigns(): JsonResponse
    {
        if (! Schema::hasTable(self::CAMPAIGNS_TABLE)) {
            return response()->json(['data' => []]);
        }

        $rows = DB::table(self::CAMPAIGNS_TABLE)
            ->selectRaw('campaign_name, MAX(campaign_id) AS campaign_id')
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->whereRaw('UPPER(campaign_name) LIKE ?', ['% SEARCH%'])
            ->groupBy('campaign_name')
            ->orderBy('campaign_name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Manually link a Google SERP campaign to a PARENT sku.
     */
    public function link(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255'],
            'campaign_name' => ['required', 'string', 'max:255'],
        ]);

        $campaignId = null;
        if (Schema::hasTable(self::CAMPAIGNS_TABLE)) {
            $campaignId = DB::table(self::CAMPAIGNS_TABLE)
                ->where('campaign_name', $validated['campaign_name'])
                ->max('campaign_id');
        }

        GoogleAdsMissingLink::firstOrCreate(
            [
                'channel' => self::CHANNEL,
                'sku' => $validated['sku'],
                'campaign_name' => $validated['campaign_name'],
            ],
            [
                'campaign_id' => $campaignId,
                'user_id' => Auth::id(),
                'created_at' => Carbon::now(),
            ]
        );

        // Clear in-request caches so response reflects the new link.
        $this->manualLinksCache = null;
        $this->campaignStatusMapCache = null;
        self::forgetMissingTotalCache();

        return response()->json($this->campaignsResponseForSku($validated['sku']));
    }

    /**
     * Remove a manually linked campaign by its id.
     */
    public function unlink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $link = GoogleAdsMissingLink::find($validated['id']);
        $sku = (string) ($link?->sku ?? '');
        if ($link && (string) $link->channel === self::CHANNEL) {
            $link->delete();
            $this->manualLinksCache = null;
            $this->campaignStatusMapCache = null;
            self::forgetMissingTotalCache();
        }

        return response()->json($this->campaignsResponseForSku($sku));
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

        $manualBySku = $this->manualLinksBySku();

        $total = 0;
        foreach ($parents as $parentKey => $parent) {
            if ((int) round($inventoryByParent[$parentKey] ?? 0) <= 0) {
                continue;
            }
            $sku = 'PARENT '.$parent;
            if (! ($manualBySku->get($sku)?->isNotEmpty() ?? false)) {
                $total++;
            }
        }

        return $total;
    }

    /**
     * @return Collection<string, Collection<int, GoogleAdsMissingLink>>
     */
    private function manualLinksBySku(): Collection
    {
        if ($this->manualLinksCache === null) {
            $this->manualLinksCache = GoogleAdsMissingLink::query()
                ->where('channel', self::CHANNEL)
                ->orderBy('id')
                ->get()
                ->groupBy(fn ($l) => (string) $l->sku);
        }

        return $this->manualLinksCache;
    }

    /**
     * @param  Collection<int, GoogleAdsMissingLink>  $links
     * @param  array<string, string>  $statusMap
     * @return list<array{id: int, campaign_id: ?string, campaign_name: string, status: string, dot: string, source: string}>
     */
    private function formatManualCampaigns(Collection $links, array $statusMap): array
    {
        return $links
            ->map(function ($l) use ($statusMap) {
                $name = (string) $l->campaign_name;
                $status = $statusMap[$this->normalizeCampaignName($name)] ?? '';
                $dot = $status === 'ENABLED' ? 'green' : ($status !== '' ? 'red' : '');

                return [
                    'id' => (int) $l->id,
                    'campaign_id' => $l->campaign_id,
                    'campaign_name' => $name,
                    'status' => $status,
                    'dot' => $dot,
                    'source' => 'manual',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{ok: bool, sku: string, campaigns: list<array{id: int, campaign_id: ?string, campaign_name: string, status: string, dot: string, source: string}>}
     */
    private function campaignsResponseForSku(string $sku): array
    {
        if ($sku === '') {
            return ['ok' => true, 'sku' => '', 'campaigns' => []];
        }

        $links = $this->manualLinksBySku()->get($sku) ?? collect();

        return [
            'ok' => true,
            'sku' => $sku,
            'campaigns' => $this->formatManualCampaigns(
                $links,
                $this->campaignStatusMapForLinks(collect([$sku => $links]))
            ),
        ];
    }

    /**
     * Status lookup only for campaign names that are actually linked (not the full SEARCH catalog).
     *
     * @param  Collection<string, Collection<int, GoogleAdsMissingLink>>  $manualBySku
     * @return array<string, string>
     */
    private function campaignStatusMapForLinks(Collection $manualBySku): array
    {
        if ($this->campaignStatusMapCache !== null) {
            return $this->campaignStatusMapCache;
        }

        $names = $manualBySku
            ->flatten(1)
            ->pluck('campaign_name')
            ->filter(fn ($n) => is_string($n) && trim($n) !== '')
            ->unique()
            ->values()
            ->all();

        if ($names === [] || ! Schema::hasTable(self::CAMPAIGNS_TABLE)) {
            return $this->campaignStatusMapCache = [];
        }

        $latestIds = DB::table(self::CAMPAIGNS_TABLE)
            ->selectRaw('campaign_name, MAX(id) AS max_id')
            ->whereIn('campaign_name', $names)
            ->groupBy('campaign_name')
            ->pluck('max_id', 'campaign_name');

        $map = [];
        if ($latestIds->isNotEmpty()) {
            $byId = DB::table(self::CAMPAIGNS_TABLE)
                ->whereIn('id', $latestIds->values()->all())
                ->get(['id', 'campaign_name', 'campaign_status'])
                ->keyBy('id');

            foreach ($latestIds as $name => $id) {
                $row = $byId->get((int) $id);
                $key = $this->normalizeCampaignName((string) $name);
                if ($key === '') {
                    continue;
                }
                $map[$key] = strtoupper(trim((string) ($row->campaign_status ?? '')));
            }
        }

        return $this->campaignStatusMapCache = $map;
    }

    private function normalizeCampaignName(string $name): string
    {
        return strtoupper(rtrim(preg_replace('/\s+/', ' ', trim($name)), '.'));
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
