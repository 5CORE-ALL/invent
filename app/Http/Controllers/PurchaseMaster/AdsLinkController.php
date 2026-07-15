<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\AmazonAds\AmazonCampaignLinkController;
use App\Http\Controllers\Controller;
use App\Models\AdsLinkSkuField;
use App\Models\AdsSkuLinkHistory;
use App\Models\AmazonAdsMissingLink;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\AdsSkuGroupService;
use App\Services\AdsSkuLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdsLinkController extends Controller
{
    public function __construct(
        private AdsSkuLinkService $skuLinkService,
        private AdsSkuGroupService $skuGroupService
    ) {
    }

    public function index()
    {
        return view('purchase-master.ads-link.index');
    }

    public function getData(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $size = min(200, max(1, (int) $request->query('size', 50)));

            $baseQuery = ProductMaster::query()
                ->whereRaw("UPPER(sku) NOT LIKE '%PARENT%'");

            $this->applyProductListFilters($baseQuery, $request);

            $total = (clone $baseQuery)->count();

            $products = (clone $baseQuery)
                ->orderBy('parent')
                ->orderBy('sku')
                ->forPage($page, $size)
                ->get(['id', 'parent', 'sku', 'Values', 'main_image', 'image1']);

            if ($products->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'last_page' => max(1, (int) ceil($total / $size)),
                    'total' => $total,
                ]);
            }

            $skus = $products->pluck('sku')->filter()->values()->all();
            $shopifyBySku = ShopifySku::mapByProductSkus($skus);
            $this->skuGroupService->prepareForSkus($skus);
            $historySummary = AdsSkuLinkHistory::buildSummaryMap($skus, 15);
            $fieldsBySku = AdsLinkSkuField::mapBySkus($skus);
            $parentLinkSkus = $products
                ->map(fn ($p) => $this->missingLinkSkuForParent((string) ($p->parent ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $linksByParentSku = $parentLinkSkus === []
                ? collect()
                : AmazonAdsMissingLink::query()
                    ->whereIn('sku', $parentLinkSkus)
                    ->orderBy('id')
                    ->get()
                    ->groupBy(fn ($l) => (string) $l->sku);
            $campaignStatusMap = $this->campaignStatusMap();
            $kwCampaignNames = $linksByParentSku
                ->flatten(1)
                ->filter(fn ($l) => (string) $l->type === 'KW')
                ->pluck('campaign_name')
                ->map(fn ($n) => trim((string) $n))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $keywordCountsByCampaign = $this->keywordCountsByCampaign($kwCampaignNames);
            $negativeCountsByCampaign = $this->negativeCountsByCampaign($kwCampaignNames);

            $data = $products->map(function ($product) use ($shopifyBySku, $historySummary, $fieldsBySku, $linksByParentSku, $campaignStatusMap, $keywordCountsByCampaign, $negativeCountsByCampaign) {
                $shopify = $shopifyBySku->get($product->sku);
                $sku = (string) ($product->sku ?? '');
                $skuNorm = strtoupper(trim($sku));
                $parent = (string) ($product->parent ?? '');
                $history = $historySummary[$skuNorm] ?? [
                    'history_count' => 0,
                    'latest_history_at' => null,
                    'latest_history_time' => null,
                    'latest_history_by' => null,
                    'latest_change' => null,
                    'history_stale' => false,
                ];
                $fields = $fieldsBySku->get($skuNorm);
                $fieldPayload = $fields ? $fields->toPayload() : AdsLinkSkuField::emptyPayload();
                $linkSku = $this->missingLinkSkuForParent($parent);
                $links = $linkSku !== ''
                    ? $linksByParentSku->get($linkSku, collect())
                    : collect();
                $kw = $this->linkListForType($links, 'KW', $campaignStatusMap);
                $pt = $this->linkListForType($links, 'PT', $campaignStatusMap);
                $kwMeta = $this->keywordMetaForLinks($kw, $keywordCountsByCampaign);
                $negMeta = $this->keywordMetaForLinks($kw, $negativeCountsByCampaign);

                return array_merge([
                    'parent' => $parent,
                    'sku' => $sku,
                    'image' => $this->resolveProductImage($product, $shopify),
                    'linked_ads_skus' => $this->linkedAdsSkusForProduct($sku),
                    'campaign_link_sku' => $linkSku,
                    'kw' => $kw,
                    'pt' => $pt,
                    'keyword_count' => $kwMeta['keyword_count'],
                    'kw_campaign' => $kwMeta['kw_campaign'],
                    'negative_count' => $negMeta['keyword_count'],
                    'history_count' => $history['history_count'],
                    'latest_history_at' => $history['latest_history_at'],
                    'latest_history_time' => $history['latest_history_time'],
                    'latest_history_by' => $history['latest_history_by'],
                    'latest_change' => $history['latest_change'],
                    'history_stale' => $history['history_stale'],
                ], $fieldPayload);
            })->values()->all();

            return response()->json([
                'success' => true,
                'data' => $data,
                'last_page' => max(1, (int) ceil($total / $size)),
                'total' => $total,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getHistory(Request $request)
    {
        $sku = trim((string) $request->query('sku', ''));
        $parent = trim((string) $request->query('parent', ''));

        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 400);
        }

        try {
            $rows = AdsSkuLinkHistory::query()
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->map(function (AdsSkuLinkHistory $row) {
                    return [
                        'updated_at' => $row->updated_at?->timezone('America/New_York')->format('m-d-Y H:i'),
                        'updated_by' => $row->updated_by ?: 'N/A',
                        'action' => ucfirst((string) $row->action),
                        'linked_sku' => $row->linked_sku,
                        'changes' => $row->changes,
                    ];
                })
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'parent' => $parent !== '' ? $parent : null,
                'history' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getParents()
    {
        try {
            $parents = ProductMaster::query()
                ->whereRaw("UPPER(sku) NOT LIKE '%PARENT%'")
                ->whereNotNull('parent')
                ->where('parent', '!=', '')
                ->select('parent')
                ->distinct()
                ->orderBy('parent')
                ->pluck('parent')
                ->map(fn ($parent) => trim((string) $parent))
                ->filter()
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'data' => $parents,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addLinkedSku(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'linked_sku' => 'required|string',
        ]);

        $sku = trim($validated['sku']);
        $linkedSku = trim($validated['linked_sku']);
        $user = Auth::user()?->name ?? 'N/A';

        if ($sku === '' || $linkedSku === '') {
            return response()->json([
                'success' => false,
                'message' => 'Both SKUs are required.',
            ], 422);
        }

        if (strtoupper($sku) === strtoupper($linkedSku)) {
            return response()->json([
                'success' => false,
                'message' => 'A SKU cannot be linked to itself.',
            ], 422);
        }

        $this->skuLinkService->link($sku, $linkedSku, $user);
        $this->skuGroupService->prepareForSkus([$sku, $linkedSku]);
        $this->logLinkHistory($sku, $linkedSku, 'linked', $user);

        return response()->json([
            'success' => true,
            'message' => 'Ads linked SKU added.',
            'affected' => $this->buildAffectedLinkedSkuRows($sku),
        ]);
    }

    public function bulkLinkSkus(Request $request)
    {
        $validated = $request->validate([
            'skus' => 'required|array|min:2',
            'skus.*' => 'required|string',
        ]);

        $skus = array_values(array_unique(array_filter(array_map('trim', $validated['skus']))));
        $user = Auth::user()?->name ?? 'N/A';

        if (count($skus) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least two SKUs to link.',
            ], 422);
        }

        $this->skuLinkService->syncFullyConnectedGroup($skus, $user);
        $this->skuGroupService->prepareForSkus($skus);

        for ($i = 0, $count = count($skus); $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $this->logLinkHistory($skus[$i], $skus[$j], 'linked', $user);
            }
        }

        $affectedBySku = [];
        foreach ($this->buildAffectedLinkedSkuRows($skus[0]) as $row) {
            $affectedBySku[$row['sku']] = $row;
        }

        return response()->json([
            'success' => true,
            'message' => 'Selected SKUs linked for Ads.',
            'affected' => array_values($affectedBySku),
        ]);
    }

    public function removeLinkedSku(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'linked_sku' => 'required|string',
        ]);

        $sku = trim($validated['sku']);
        $linkedSku = trim($validated['linked_sku']);
        $user = Auth::user()?->name ?? 'N/A';

        if ($sku === '' || $linkedSku === '') {
            return response()->json([
                'success' => false,
                'message' => 'Both SKUs are required.',
            ], 422);
        }

        if (strtoupper($sku) === strtoupper($linkedSku)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove a SKU from itself.',
            ], 422);
        }

        $this->skuGroupService->prepareForSkus([$sku, $linkedSku]);
        $initialGroup = $this->resolveLinkedSkuGroupMembers($sku);
        $this->skuGroupService->prepareForSkus($initialGroup);
        $beforeGroup = $this->resolveLinkedSkuGroupMembers($sku);

        $this->skuLinkService->unlinkFromGroup($linkedSku, $beforeGroup, $user);
        $this->logLinkHistory($sku, $linkedSku, 'unlinked', $user);

        $this->skuGroupService->prepareForSkus($beforeGroup);

        $affectedBySku = [];
        foreach ($beforeGroup as $memberSku) {
            foreach ($this->buildAffectedLinkedSkuRows($memberSku) as $row) {
                $affectedBySku[$row['sku']] = $row;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Ads linked SKU removed.',
            'affected' => array_values($affectedBySku),
        ]);
    }

    public function getFilteredSkus(Request $request)
    {
        try {
            $query = ProductMaster::query()
                ->whereRaw("UPPER(sku) NOT LIKE '%PARENT%'");

            $this->applyProductListFilters($query, $request);

            $skus = $query
                ->orderBy('parent')
                ->orderBy('sku')
                ->limit(50)
                ->pluck('sku')
                ->map(fn ($sku) => trim((string) $sku))
                ->filter()
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'skus' => $skus,
                'total' => count($skus),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveListField(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'field' => 'required|string|in:plus_kw,minus_kw,plus_pt,minus_pt,plus_kw_spl,pt_spl,spl_minus_kw,spl_minus_pt',
            'items' => 'nullable|array',
            'items.*' => 'nullable|string',
        ]);

        $sku = trim($validated['sku']);
        $field = $validated['field'];
        $items = AdsLinkSkuField::normalizeList($validated['items'] ?? []);
        $user = Auth::user()?->name ?? 'N/A';

        if ($sku === '') {
            return response()->json([
                'success' => false,
                'message' => 'SKU is required.',
            ], 422);
        }

        $row = AdsLinkSkuField::updateOrCreate(
            ['sku_norm' => AdsLinkSkuField::normalizeSku($sku)],
            [
                'sku' => $sku,
                $field => $items,
                'updated_by' => $user,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'sku' => $sku,
            'field' => $field,
            'items' => AdsLinkSkuField::normalizeList($row->{$field}),
        ]);
    }

    public function saveSplField(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'field' => 'required|string|in:plus_kw_spl,pt_spl',
            'value' => 'nullable|numeric',
        ]);

        $sku = trim($validated['sku']);
        $field = $validated['field'];
        $user = Auth::user()?->name ?? 'N/A';

        if ($sku === '') {
            return response()->json([
                'success' => false,
                'message' => 'SKU is required.',
            ], 422);
        }

        $raw = $request->input('value');
        $value = ($raw === null || $raw === '') ? null : round((float) $raw, 2);

        $row = AdsLinkSkuField::updateOrCreate(
            ['sku_norm' => AdsLinkSkuField::normalizeSku($sku)],
            [
                'sku' => $sku,
                $field => $value,
                'updated_by' => $user,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'sku' => $sku,
            'field' => $field,
            'value' => $row->{$field} !== null ? (float) $row->{$field} : null,
        ]);
    }

    /**
     * Campaign picker source — same SP campaign list as /amazon-ads/missing.
     */
    public function getCampaigns(Request $request)
    {
        try {
            if (! Schema::hasTable('amazon_sp_campaign_reports')
                || ! Schema::hasColumn('amazon_sp_campaign_reports', 'campaignName')) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $search = trim((string) $request->query('q', ''));
            $parent = trim((string) $request->query('parent', ''));
            $sku = trim((string) $request->query('sku', ''));

            $query = DB::table('amazon_sp_campaign_reports')
                ->selectRaw('campaignName AS campaign_name, MAX(campaign_id) AS campaign_id')
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '')
                ->groupBy('campaignName');

            if ($search !== '') {
                $query->whereRaw('LOWER(campaignName) LIKE ?', ['%' . strtolower($search) . '%']);
            }

            $rows = $query
                ->orderBy('campaignName')
                ->limit(200)
                ->get();

            $statusMap = $this->campaignStatusMap();
            $needles = array_values(array_filter([
                strtoupper($parent),
                strtoupper($sku),
            ]));

            $data = $rows->map(function ($row) use ($statusMap, $needles) {
                $name = (string) ($row->campaign_name ?? '');
                $status = $statusMap[$this->normalizeCampaignName($name)] ?? '';
                $dot = $status === 'ENABLED' ? 'green' : ($status !== '' ? 'red' : '');
                $nameUpper = strtoupper($name);
                $relevant = false;
                foreach ($needles as $needle) {
                    if ($needle !== '' && str_contains($nameUpper, $needle)) {
                        $relevant = true;
                        break;
                    }
                }

                return [
                    'campaign_name' => $name,
                    'campaign_id' => $row->campaign_id,
                    'status' => $status,
                    'dot' => $dot,
                    'relevant' => $relevant,
                ];
            })
                ->sortByDesc(fn ($row) => $row['relevant'] ? 1 : 0)
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Link a campaign using amazon_ads_missing_links (same table as /amazon-ads/missing).
     * Links are stored against PARENT {parent}, matching the missing page.
     */
    public function linkCampaign(Request $request)
    {
        $validated = $request->validate([
            'parent' => 'required|string',
            'type' => 'required|string|in:KW,PT',
            'campaign_name' => 'required|string|max:255',
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $type = strtoupper($validated['type']);
        $campaignName = trim($validated['campaign_name']);
        $linkSku = $this->missingLinkSkuForParent($parent);

        if ($linkSku === '' || $campaignName === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parent and campaign name are required.',
            ], 422);
        }

        $campaignId = null;
        if (Schema::hasTable('amazon_sp_campaign_reports')
            && Schema::hasColumn('amazon_sp_campaign_reports', 'campaignName')) {
            $campaignId = DB::table('amazon_sp_campaign_reports')
                ->where('campaignName', $campaignName)
                ->max('campaign_id');
        }

        AmazonAdsMissingLink::firstOrCreate(
            [
                'sku' => $linkSku,
                'type' => $type,
                'campaign_name' => $campaignName,
            ],
            [
                'campaign_id' => $campaignId,
                'user_id' => Auth::id(),
                'created_at' => Carbon::now(),
            ]
        );

        return response()->json(array_merge(
            ['success' => true, 'message' => 'Campaign linked.'],
            $this->linksResponseForParent($parent)
        ));
    }

    public function unlinkCampaign(Request $request)
    {
        $validated = $request->validate([
            'parent' => 'required|string',
            'id' => 'required|integer',
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $linkSku = $this->missingLinkSkuForParent($parent);
        $id = (int) $validated['id'];

        if ($linkSku === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parent is required.',
            ], 422);
        }

        AmazonAdsMissingLink::query()
            ->where('id', $id)
            ->where('sku', $linkSku)
            ->delete();

        return response()->json(array_merge(
            ['success' => true, 'message' => 'Campaign unlinked.'],
            $this->linksResponseForParent($parent)
        ));
    }

    /**
     * Merge keywords across all Campaign KW linked to a parent (union into each campaign; duplicates skipped).
     */
    public function mergeKeywords(Request $request)
    {
        $validated = $request->validate([
            'parent' => 'required|string',
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $linkSku = $this->missingLinkSkuForParent($parent);

        if ($linkSku === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parent is required.',
            ], 422);
        }

        $campaigns = AmazonAdsMissingLink::query()
            ->where('sku', $linkSku)
            ->where('type', 'KW')
            ->orderBy('id')
            ->pluck('campaign_name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique(fn ($n) => strtoupper($n))
            ->values()
            ->all();

        if (count($campaigns) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Link at least two Campaign KW campaigns before merging keywords.',
                'campaigns' => $campaigns,
            ], 422);
        }

        /** @var AmazonCampaignLinkController $merger */
        $merger = app(AmazonCampaignLinkController::class);
        $result = $merger->runMerge($campaigns);

        return response()->json(array_merge(
            $result,
            $this->linksResponseForParent($parent)
        ), ($result['success'] ?? false) ? 200 : ($result['status'] ?? 422));
    }

    private function missingLinkSkuForParent(string $parent): string
    {
        $parent = preg_replace('/\s+/', ' ', trim($parent));

        return $parent !== '' ? 'PARENT '.$parent : '';
    }

    /**
     * @return array{campaign_link_sku: string, kw: array, pt: array, keyword_count: int, kw_campaign: ?string, negative_count: int}
     */
    private function linksResponseForParent(string $parent): array
    {
        $linkSku = $this->missingLinkSkuForParent($parent);
        $links = $linkSku === ''
            ? collect()
            : AmazonAdsMissingLink::query()->where('sku', $linkSku)->orderBy('id')->get();
        $statusMap = $this->campaignStatusMap();
        $kw = $this->linkListForType($links, 'KW', $statusMap);
        $pt = $this->linkListForType($links, 'PT', $statusMap);
        $kwNames = array_column($kw, 'campaign_name');
        $kwMeta = $this->keywordMetaForLinks($kw, $this->keywordCountsByCampaign($kwNames));
        $negMeta = $this->keywordMetaForLinks($kw, $this->negativeCountsByCampaign($kwNames));

        return [
            'campaign_link_sku' => $linkSku,
            'kw' => $kw,
            'pt' => $pt,
            'keyword_count' => $kwMeta['keyword_count'],
            'kw_campaign' => $kwMeta['kw_campaign'],
            'negative_count' => $negMeta['keyword_count'],
        ];
    }

    /**
     * @param  list<array{campaign_name?: string}>  $kwLinks
     * @param  array<string, int>  $countsByCampaign
     * @return array{keyword_count: int, kw_campaign: ?string}
     */
    private function keywordMetaForLinks(array $kwLinks, array $countsByCampaign): array
    {
        $names = [];
        $total = 0;
        foreach ($kwLinks as $link) {
            $name = trim((string) ($link['campaign_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $names[] = $name;
            $total += (int) ($countsByCampaign[$name] ?? 0);
        }

        return [
            'keyword_count' => $total,
            'kw_campaign' => $names[0] ?? null,
        ];
    }

    /**
     * Same source/count method as /amazon-ads/campaign-link Keywords column.
     *
     * @param  list<string>  $campaignNames
     * @return array<string, int>
     */
    private function keywordCountsByCampaign(array $campaignNames): array
    {
        return $this->distinctKeywordCountsByCampaign($campaignNames, 'amazon_sp_keyword_reports');
    }

    /**
     * Same source/count method as /amazon-ads/negative-link Negatives column.
     *
     * @param  list<string>  $campaignNames
     * @return array<string, int>
     */
    private function negativeCountsByCampaign(array $campaignNames): array
    {
        return $this->distinctKeywordCountsByCampaign($campaignNames, 'amazon_sp_negative_keywords');
    }

    /**
     * @param  list<string>  $campaignNames
     * @return array<string, int>
     */
    private function distinctKeywordCountsByCampaign(array $campaignNames, string $table): array
    {
        $names = collect($campaignNames)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($names === [] || ! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->whereIn('campaignName', $names)
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->select('campaignName', DB::raw('COUNT(DISTINCT keyword_id) AS keyword_count'))
            ->groupBy('campaignName')
            ->pluck('keyword_count', 'campaignName')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection  $links
     * @param  array<string, string>  $statusMap
     * @return list<array{id: int, campaign_id: ?string, campaign_name: string, status: string, dot: string}>
     */
    private function linkListForType($links, string $type, array $statusMap = []): array
    {
        return $links
            ->filter(fn ($l) => (string) $l->type === $type)
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
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function campaignStatusMap(): array
    {
        if (! Schema::hasTable('amazon_sp_campaign_reports')
            || ! Schema::hasColumn('amazon_sp_campaign_reports', 'campaignName')
            || ! Schema::hasColumn('amazon_sp_campaign_reports', 'campaignStatus')) {
            return [];
        }

        $latestIds = DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->selectRaw('MAX(id) AS max_id')
            ->groupBy('campaignName')
            ->pluck('max_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($latestIds === []) {
            return [];
        }

        $map = [];
        DB::table('amazon_sp_campaign_reports')
            ->whereIn('id', $latestIds)
            ->get(['campaignName', 'campaignStatus'])
            ->each(function ($row) use (&$map) {
                $key = $this->normalizeCampaignName((string) ($row->campaignName ?? ''));
                if ($key === '') {
                    return;
                }
                $map[$key] = strtoupper(trim((string) ($row->campaignStatus ?? '')));
            });

        return $map;
    }

    private function normalizeCampaignName(string $name): string
    {
        return strtoupper(rtrim(preg_replace('/\s+/', ' ', trim($name)), '.'));
    }

    private function logLinkHistory(string $sku, string $linkedSku, string $action, string $user): void
    {
        $parents = ProductMaster::query()
            ->whereIn('sku', [$sku, $linkedSku])
            ->pluck('parent', 'sku');

        AdsSkuLinkHistory::logAction(
            $sku,
            $action,
            $linkedSku,
            trim((string) ($parents[$sku] ?? '')),
            $user
        );
        AdsSkuLinkHistory::logAction(
            $linkedSku,
            $action,
            $sku,
            trim((string) ($parents[$linkedSku] ?? '')),
            $user
        );
    }

    /**
     * @return list<string>
     */
    private function linkedAdsSkusForProduct(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $group = $this->skuGroupService->groupContaining($sku);

        return $this->normalizeLinkedSkuGroup($group !== [] ? $group : [$sku]);
    }

    /**
     * @param  list<string>  $group
     * @return list<string>
     */
    private function normalizeLinkedSkuGroup(array $group): array
    {
        $seen = [];
        $normalized = [];

        foreach ($group as $memberSku) {
            $display = trim((string) $memberSku);
            $norm = strtoupper($display);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $normalized[] = $display;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function resolveLinkedSkuGroupMembers(string $sku): array
    {
        $group = $this->skuGroupService->groupContaining(trim($sku));

        return $this->normalizeLinkedSkuGroup($group !== [] ? $group : [trim($sku)]);
    }

    /**
     * @return list<array{sku: string, linked_ads_skus: list<string>}>
     */
    private function buildAffectedLinkedSkuRows(string $sku): array
    {
        $group = $this->resolveLinkedSkuGroupMembers($sku);
        $rows = [];

        foreach ($group as $memberSku) {
            $rows[] = [
                'sku' => $memberSku,
                'linked_ads_skus' => $group,
            ];
        }

        return $rows;
    }

    private function applyProductListFilters($query, Request $request): void
    {
        $skuFilter = trim((string) $request->query('sku', ''));
        $parentFilter = trim((string) $request->query('parent', ''));
        $parentExact = trim((string) $request->query('parent_exact', ''));

        if ($skuFilter !== '') {
            $query->whereRaw('LOWER(TRIM(sku)) LIKE ?', ['%' . strtolower($skuFilter) . '%']);
        }

        if ($parentExact !== '') {
            $query->whereRaw('LOWER(TRIM(parent)) = ?', [strtolower($parentExact)]);
        } elseif ($parentFilter !== '') {
            $query->whereRaw('LOWER(TRIM(parent)) LIKE ?', ['%' . strtolower($parentFilter) . '%']);
        }
    }

    private function resolveProductImage(ProductMaster $product, ?ShopifySku $shopify): ?string
    {
        $candidates = [
            $shopify?->image_src,
        ];

        $values = $product->Values;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($values)) {
            $values = [];
        }

        foreach (['image_path', 'image', 'Image', 'main_image'] as $key) {
            if (! empty($values[$key])) {
                $candidates[] = $values[$key];
                break;
            }
        }

        $candidates[] = $product->main_image;
        $candidates[] = $product->image1;

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeImageUrl($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeImageUrl(mixed $path): ?string
    {
        $p = trim((string) ($path ?? ''));
        if ($p === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $p) || str_starts_with($p, 'data:')) {
            return $p;
        }

        if (str_starts_with($p, '//')) {
            return 'https:' . $p;
        }

        return '/' . ltrim($p, '/');
    }
}
