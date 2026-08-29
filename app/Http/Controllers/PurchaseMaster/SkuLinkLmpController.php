<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\EbayMetric;
use App\Models\EbaySkuCompetitor;
use App\Models\LmpCompetitorHistory;
use App\Models\LmpSkuMark;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\LmpSkuGroupService;
use App\Services\LmpSkuLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SkuLinkLmpController extends Controller
{
    public function __construct(
        private LmpSkuLinkService $skuLinkService,
        private LmpSkuGroupService $skuGroupService
    ) {
    }

    public function index()
    {
        return view('purchase-master.sku-link-lmp.index');
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

            $lmpLookups = EbaySkuCompetitor::buildGroupedLookup('ebay');
            $lmpDetailsLookup = $lmpLookups['details'];

            $ebayMetricsBySku = EbayMetric::query()
                ->whereIn('sku', $skus)
                ->get(['sku', 'ebay_price', 'ebay_l30', 'ebay_l60', 'views'])
                ->keyBy(fn ($metric) => ShopifySku::normalizeSkuForShopifyLookup($metric->sku));

            $skuNorms = array_values(array_unique(array_filter(array_map(
                fn ($sku) => strtoupper(trim((string) $sku)),
                $skus
            ))));

            $marksBySkuNorm = LmpSkuMark::query()
                ->whereIn('sku_norm', $skuNorms)
                ->get(['sku_norm', 'm'])
                ->keyBy('sku_norm');

            $defaultUser = Auth::user()?->name ?? 'N/A';
            foreach ($products as $product) {
                $skuNorm = strtoupper(trim((string) ($product->sku ?? '')));
                if ($skuNorm === '' || $marksBySkuNorm->has($skuNorm)) {
                    continue;
                }

                $mark = LmpSkuMark::updateOrCreate(
                    ['sku_norm' => $skuNorm],
                    [
                        'sku' => (string) $product->sku,
                        'm' => '1',
                        'updated_by' => $defaultUser,
                    ]
                );
                $marksBySkuNorm->put($skuNorm, $mark);
            }

            $historySummary = LmpCompetitorHistory::buildSummaryMap($skus, 15);

            $data = $products->map(function ($product) use ($shopifyBySku, $lmpDetailsLookup, $ebayMetricsBySku, $marksBySkuNorm, $historySummary) {
                $shopify = $shopifyBySku->get($product->sku);
                $sku = (string) ($product->sku ?? '');
                $skuNorm = strtoupper(trim($sku));
                $linkedLmpSkus = $this->linkedLmpSkusForProduct($sku);
                $lmp = $this->resolveEbayLmpForSkus($linkedLmpSkus, $lmpDetailsLookup);
                $ebayMetric = $ebayMetricsBySku->get(ShopifySku::normalizeSkuForShopifyLookup($sku));
                $ebayPrice = $ebayMetric && is_numeric($ebayMetric->ebay_price)
                    ? (float) $ebayMetric->ebay_price
                    : null;
                $eCvr = $this->computeECvr(
                    $ebayMetric?->ebay_l30,
                    $ebayMetric?->views
                );
                $eCvr60 = $this->computeECvr(
                    $ebayMetric?->ebay_l60,
                    $ebayMetric?->views
                );
                $mark = $marksBySkuNorm->get($skuNorm);
                $m = $this->normalizeMChar($mark?->m) ?? '1';
                $history = $historySummary[$skuNorm] ?? [
                    'history_count' => 0,
                    'latest_history_at' => null,
                    'latest_history_time' => null,
                    'latest_history_by' => null,
                    'latest_change' => null,
                    'history_stale' => false,
                ];

                return [
                    'parent' => (string) ($product->parent ?? ''),
                    'sku' => $sku,
                    'e_cvr' => $eCvr,
                    'e_cvr_60' => $eCvr60,
                    'image' => $this->resolveProductImage($product, $shopify),
                    'm' => $m,
                    'linked_lmp_skus' => $linkedLmpSkus,
                    'ebay_price' => $ebayPrice,
                    'lmp_price' => $lmp['lmp_price'],
                    'e_lmp' => $this->computeELmp($lmp['lmp_price'], $m),
                    'lmp_link' => $lmp['lmp_link'],
                    'lmp_entries_total' => $lmp['lmp_entries_total'],
                    'history_count' => $history['history_count'],
                    'latest_history_at' => $history['latest_history_at'],
                    'latest_history_time' => $history['latest_history_time'],
                    'latest_history_by' => $history['latest_history_by'],
                    'latest_change' => $history['latest_change'],
                    'history_stale' => $history['history_stale'],
                ];
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
            $rows = LmpCompetitorHistory::query()
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->map(function (LmpCompetitorHistory $row) {
                    return [
                        'updated_at' => $row->updated_at?->timezone('America/New_York')->format('m-d-Y H:i'),
                        'updated_by' => $row->updated_by ?: 'N/A',
                        'action' => ucfirst((string) $row->action),
                        'item_id' => $row->item_id,
                        'changes' => $row->changes,
                        'product_title' => $row->product_title,
                        'total_price' => $row->total_price,
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

        return response()->json([
            'success' => true,
            'message' => 'LMP linked SKU added.',
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

        $affectedBySku = [];
        foreach ($this->buildAffectedLinkedSkuRows($skus[0]) as $row) {
            $affectedBySku[$row['sku']] = $row;
        }

        return response()->json([
            'success' => true,
            'message' => 'Selected SKUs linked for LMP.',
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
        // Re-prepare with the initial members so the full connected component is loaded.
        $this->skuGroupService->prepareForSkus($initialGroup);
        $beforeGroup = $this->resolveLinkedSkuGroupMembers($sku);

        $this->skuLinkService->unlinkFromGroup(
            $linkedSku,
            $beforeGroup,
            Auth::user()?->name ?? 'N/A'
        );

        $this->skuGroupService->prepareForSkus($beforeGroup);

        $affectedBySku = [];
        foreach ($beforeGroup as $memberSku) {
            foreach ($this->buildAffectedLinkedSkuRows($memberSku) as $row) {
                $affectedBySku[$row['sku']] = $row;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'LMP linked SKU removed.',
            'affected' => array_values($affectedBySku),
        ]);
    }

    public function saveM(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'm' => 'nullable|string|max:1',
        ]);

        $sku = trim($validated['sku']);
        if ($sku === '') {
            return response()->json([
                'success' => false,
                'message' => 'SKU is required.',
            ], 422);
        }

        $m = $this->normalizeMChar($validated['m'] ?? null);
        $user = Auth::user()?->name ?? 'N/A';

        if ($m === null) {
            LmpSkuMark::query()
                ->where('sku_norm', strtoupper($sku))
                ->delete();
        } else {
            LmpSkuMark::updateOrCreate(
                ['sku_norm' => strtoupper($sku)],
                [
                    'sku' => $sku,
                    'm' => $m,
                    'updated_by' => $user,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'M updated.',
            'm' => $m,
        ]);
    }

    public function bulkSaveM(Request $request)
    {
        $validated = $request->validate([
            'skus' => 'required|array|min:1',
            'skus.*' => 'required|string',
            'm' => 'required|string|max:1',
        ]);

        $skus = array_values(array_unique(array_filter(array_map('trim', $validated['skus']))));
        $m = $this->normalizeMChar($validated['m'] ?? null);
        $user = Auth::user()?->name ?? 'N/A';

        if ($m === null) {
            return response()->json([
                'success' => false,
                'message' => 'M value is required.',
            ], 422);
        }

        if ($skus === []) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least one SKU.',
            ], 422);
        }

        foreach ($skus as $sku) {
            LmpSkuMark::updateOrCreate(
                ['sku_norm' => strtoupper($sku)],
                [
                    'sku' => $sku,
                    'm' => $m,
                    'updated_by' => $user,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'M updated for ' . count($skus) . ' SKU(s).',
            'm' => $m,
            'updated_count' => count($skus),
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

    public function getLmpData(Request $request)
    {
        try {
            $sku = trim((string) $request->input('sku', ''));
            $linkedSkus = $request->input('linked_lmp_skus', []);

            if ($sku === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU is required.',
                ], 400);
            }

            if (! is_array($linkedSkus)) {
                $linkedSkus = [];
            }

            $groupSkus = array_values(array_unique(array_filter(array_map(
                fn ($value) => trim((string) $value),
                array_merge([$sku], $linkedSkus)
            ))));

            $competitors = collect();

            foreach ($groupSkus as $groupSku) {
                foreach (EbaySkuCompetitor::resolveLookupKeys($groupSku) as $lookupSku) {
                    foreach (EbaySkuCompetitor::getCompetitorsForSku($lookupSku, 'ebay') as $competitor) {
                        $competitors->push($competitor);
                    }
                }
            }

            $competitors = EbaySkuCompetitor::dedupeByItemId($competitors, $sku);

            $lowestPrice = $competitors->first();

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'competitors' => $competitors->map(function ($comp) {
                    return [
                        'id' => $comp->id,
                        'item_id' => $comp->item_id,
                        'price' => (float) ($comp->price ?? 0),
                        'shipping_cost' => (float) ($comp->shipping_cost ?? 0),
                        'total_price' => (float) ($comp->total_price ?? 0),
                        'link' => $comp->product_link,
                        'title' => $comp->product_title,
                        'image' => $comp->image ?? null,
                        'created_at' => $comp->created_at?->format('Y-m-d H:i:s'),
                    ];
                }),
                'lowest_price' => $lowestPrice ? (float) $lowestPrice->total_price : null,
                'total_count' => $competitors->count(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  list<string>  $skus
     * @return array{lmp_price: float|null, lmp_link: string|null, lmp_entries_total: int}
     */
    private function resolveEbayLmpForSkus(array $skus, $lmpDetailsLookup): array
    {
        $allEntries = collect();

        foreach ($skus as $sku) {
            $skuLookupKey = strtoupper(trim((string) $sku));
            if ($skuLookupKey === '') {
                continue;
            }

            $entries = $lmpDetailsLookup->get($skuLookupKey);
            if ($entries instanceof \Illuminate\Support\Collection) {
                $allEntries = $allEntries->merge($entries);
            }
        }

        $allEntries = EbaySkuCompetitor::dedupeByItemId($allEntries, $skus[0] ?? null);

        $lowest = $allEntries->first();

        return [
            'lmp_price' => ($lowest && is_numeric($lowest->total_price))
                ? (float) $lowest->total_price
                : null,
            'lmp_link' => $lowest->product_link ?? null,
            'lmp_entries_total' => $allEntries->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function linkedLmpSkusForProduct(string $sku): array
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
     * @return list<array{sku: string, linked_lmp_skus: list<string>}>
     */
    private function buildAffectedLinkedSkuRows(string $sku): array
    {
        $group = $this->resolveLinkedSkuGroupMembers($sku);
        $rows = [];

        foreach ($group as $memberSku) {
            $rows[] = [
                'sku' => $memberSku,
                'linked_lmp_skus' => $group,
            ];
        }

        return $rows;
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

    private function normalizeMChar(mixed $value): ?string
    {
        $char = mb_substr(trim((string) ($value ?? '')), 0, 1);

        return $char !== '' ? $char : null;
    }

    private function computeELmp(?float $lmpPrice, ?string $m): ?float
    {
        if ($lmpPrice === null || $m === null || $m === '') {
            return null;
        }

        if (! is_numeric($m)) {
            return null;
        }

        return round($lmpPrice * (float) $m, 2);
    }

    private function applyProductListFilters($query, Request $request): void
    {
        $skuFilter = trim((string) $request->query('sku', ''));
        $parentFilter = trim((string) $request->query('parent', ''));
        $parentExact = trim((string) $request->query('parent_exact', ''));
        $historyAlert = in_array(strtolower(trim((string) $request->query('history_alert', ''))), ['1', 'true', 'yes'], true);
        $cvrFilter = trim((string) $request->query('cvr', 'all'));

        if ($skuFilter !== '') {
            $query->whereRaw('LOWER(TRIM(sku)) LIKE ?', ['%' . strtolower($skuFilter) . '%']);
        }

        if ($parentExact !== '') {
            $query->whereRaw('LOWER(TRIM(parent)) = ?', [strtolower($parentExact)]);
        } elseif ($parentFilter !== '') {
            $query->whereRaw('LOWER(TRIM(parent)) LIKE ?', ['%' . strtolower($parentFilter) . '%']);
        }

        if ($historyAlert) {
            $staleSkuNorms = LmpCompetitorHistory::staleSkuNorms(15);
            if ($staleSkuNorms === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn(DB::raw('UPPER(TRIM(sku))'), $staleSkuNorms);
            }
        }

        $this->applyCvrFilter($query, $cvrFilter);
    }

    /**
     * E CVR slabs — same bands as /ebay-tabulator-view CVR% filter.
     */
    private function applyCvrFilter($query, string $cvrFilter): void
    {
        if ($cvrFilter === '' || $cvrFilter === 'all') {
            return;
        }

        $valid = ['0-0', '0-3', '3-7', '7-13', '13plus'];
        if (! in_array($cvrFilter, $valid, true)) {
            return;
        }

        $cvrExpr = 'CASE WHEN COALESCE(em.views, 0) > 0 THEN ROUND((COALESCE(em.ebay_l30, 0) / em.views) * 100, 2) ELSE 0 END';
        $skuMatch = 'UPPER(TRIM(em.sku)) = UPPER(TRIM(product_master.sku))';

        if ($cvrFilter === '0-0') {
            $query->where(function ($q) use ($cvrExpr, $skuMatch) {
                $q->whereNotExists(function ($sub) use ($skuMatch) {
                    $sub->select(DB::raw(1))
                        ->from('ebay_metrics as em')
                        ->whereRaw($skuMatch);
                })->orWhereExists(function ($sub) use ($cvrExpr, $skuMatch) {
                    $sub->select(DB::raw(1))
                        ->from('ebay_metrics as em')
                        ->whereRaw($skuMatch)
                        ->whereRaw("({$cvrExpr}) = 0");
                });
            });

            return;
        }

        $query->whereExists(function ($sub) use ($cvrFilter, $cvrExpr, $skuMatch) {
            $sub->select(DB::raw(1))
                ->from('ebay_metrics as em')
                ->whereRaw($skuMatch);

            match ($cvrFilter) {
                '0-3' => $sub->whereRaw("({$cvrExpr}) > 0 AND ({$cvrExpr}) <= 3"),
                '3-7' => $sub->whereRaw("({$cvrExpr}) > 3 AND ({$cvrExpr}) <= 7"),
                '7-13' => $sub->whereRaw("({$cvrExpr}) > 7 AND ({$cvrExpr}) <= 13"),
                '13plus' => $sub->whereRaw("({$cvrExpr}) > 13"),
                default => null,
            };
        });
    }

    /**
     * eBay CVR (SCVR on /ebay-tabulator-view): (eBay L30 / views) × 100
     */
    private function computeECvr(mixed $ebaySold, mixed $views): float
    {
        $sold = is_numeric($ebaySold) ? (float) $ebaySold : 0.0;
        $viewCount = is_numeric($views) ? (float) $views : 0.0;

        if ($viewCount <= 0) {
            return 0.0;
        }

        return round(($sold / $viewCount) * 100, 2);
    }
}
