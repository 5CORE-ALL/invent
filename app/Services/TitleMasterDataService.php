<?php

namespace App\Services;

use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Title Master list query: paginated, server-side filters, aggregates (no amazon_listings_raw.raw_data).
 */
class TitleMasterDataService
{
    /** Collapse whitespace and NBSP for search (matches Excel/import quirks). */
    private function normalizeSearchWhitespace(string $s): string
    {
        $s = str_replace("\u{00a0}", ' ', $s);
        $s = preg_replace('/\s+/u', ' ', trim($s));

        return $s;
    }

    /**
     * MySQL: UTF-8 NBSP → space, then collapse runs of spaces (nested REPLACE).
     *
     * @param  string  $sqlExpr  Raw column expression, e.g. "COALESCE(psm.sku,'')"
     */
    private function sqlWhitespaceCollapsed(string $sqlExpr): string
    {
        $e = "REPLACE({$sqlExpr}, UNHEX('C2A0'), ' ')";
        for ($i = 0; $i < 10; $i++) {
            $e = "REPLACE({$e}, '  ', ' ')";
        }

        return $e;
    }

    /**
     * Match snapshot keys the same way CVR Master saves SKUs: trim + NBSP→space + collapse spaces.
     */
    private function sqlSkuKeyForSnapshotJoin(string $sqlExpr): string
    {
        return 'TRIM('.$this->sqlWhitespaceCollapsed($sqlExpr).')';
    }

    private function likeContainsPattern(string $normalized): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $normalized);

        return '%'.$escaped.'%';
    }

    private function isMysql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }

    /**
     * Match ShopifySku::normalizeSkuForShopifyLookup in SQL (NBSP → space, collapse spaces, uppercase).
     * Used so INV / Dil% sorting uses the same Shopify row as mapByProductSkus(), not only exact sku strings.
     */
    private function sqlTitleMasterShopifySkuNormKey(string $aliasDotSkuColumn): string
    {
        $collapsed = $this->sqlWhitespaceCollapsed($aliasDotSkuColumn);

        return 'UPPER(TRIM('.$collapsed.'))';
    }

    /**
     * One Shopify row per normalized SKU (lowest id) so joins cannot multiply Title Master rows.
     *
     * @param  \Illuminate\Database\Query\Builder  $base
     */
    private function joinShopifySkusForTitleMaster($base): void
    {
        if (! Schema::hasTable('shopify_skus')) {
            return;
        }

        if (! $this->isMysql()) {
            $base->leftJoin('shopify_skus as ss', 'ss.sku', '=', 'skus.sku');

            return;
        }

        $normInner = $this->sqlTitleMasterShopifySkuNormKey('ss_pick.sku');
        $pickIds = DB::table('shopify_skus as ss_pick')
            ->select(DB::raw('MIN(ss_pick.id) as pick_id'))
            ->whereNotNull('ss_pick.sku')
            ->where('ss_pick.sku', '!=', '')
            ->groupBy(DB::raw($normInner));

        $ssOne = DB::table('shopify_skus as ss')
            ->joinSub($pickIds, 'pick', function ($join) {
                $join->on('ss.id', '=', 'pick.pick_id');
            })
            ->select('ss.*', DB::raw($this->sqlTitleMasterShopifySkuNormKey('ss.sku').' as ss_norm_key'));

        $base->leftJoinSub($ssOne, 'ss', function ($join) {
            $skuNorm = $this->sqlTitleMasterShopifySkuNormKey('skus.sku');
            $join->whereRaw('ss.ss_norm_key = '.$skuNorm);
        });
    }

    /**
     * Hide open-box SKUs: suffix "open box" / "open-box" / "openbox" (case-insensitive, NBSP normalized).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyExcludeOpenBoxSuffixSku($query, string $skuColumn = 'sku'): void
    {
        $trimmed = "RTRIM(TRIM(REPLACE(COALESCE({$skuColumn}, ''), UNHEX('C2A0'), ' ')))";
        $lower = "LOWER({$trimmed})";

        if ($this->isMysql()) {
            $query->whereRaw("{$lower} NOT REGEXP '(open[[:space:]_-]+box|openbox)[[:space:]]*$'");

            return;
        }

        $query->whereRaw("NOT (LENGTH({$trimmed}) >= 8 AND LOWER(SUBSTR({$trimmed}, -8)) = 'open box')")
            ->whereRaw("NOT (LENGTH({$trimmed}) >= 9 AND LOWER(SUBSTR({$trimmed}, -9)) IN ('open-box', 'open_box'))")
            ->whereRaw("NOT (LENGTH({$trimmed}) >= 7 AND LOWER(SUBSTR({$trimmed}, -7)) = 'openbox')");
    }

    /**
     * Distinct SKUs for Title Master: mappings table plus product_master rows that are not in mappings
     * (so titles can be edited even before inventory sync creates a psm row).
     */
    private function titleMasterSkuUnion()
    {
        $notParent = "UPPER(COALESCE(sku, '')) NOT LIKE '%PARENT%'";

        $fromMappings = DB::table('product_stock_mappings')
            ->select('sku')
            ->whereNotNull('sku')
            ->whereRaw($notParent);
        $this->applyExcludeOpenBoxSuffixSku($fromMappings, 'sku');

        $fromMaster = DB::table('product_master')
            ->select('sku')
            ->whereNotNull('sku')
            ->whereRaw($notParent);
        $this->applyExcludeOpenBoxSuffixSku($fromMaster, 'sku');

        return $fromMappings->union($fromMaster);
    }

    public function getList(Request $request)
    {
        $export = $request->boolean('export');
        $perPage = min(max((int) $request->query('per_page', 75), 10), $export ? 15000 : 150);
        $page = max((int) $request->query('page', 1), 1);

        $latestAmazonIds = DB::table('amazon_listings_raw')
            ->select('seller_sku', DB::raw('MAX(id) as max_id'))
            ->groupBy('seller_sku');

        $latestAmazonBySku = DB::table('amazon_listings_raw as alr')
            ->joinSub($latestAmazonIds, 'latest', function ($join) {
                $join->on('alr.seller_sku', '=', 'latest.seller_sku')
                    ->on('alr.id', '=', 'latest.max_id');
            })
            ->select(['alr.seller_sku', 'alr.item_name']);

        $pmImage7Column = Schema::hasColumn('product_master', 'image7')
            ? 'image7'
            : (Schema::hasColumn('product_master', 'images7') ? 'images7' : null);
        $pmImage8Column = Schema::hasColumn('product_master', 'image8')
            ? 'image8'
            : (Schema::hasColumn('product_master', 'images8') ? 'images8' : null);
        $pmImage9Column = Schema::hasColumn('product_master', 'image9')
            ? 'image9'
            : (Schema::hasColumn('product_master', 'images9') ? 'images9' : null);
        $pmImage10Column = Schema::hasColumn('product_master', 'image10')
            ? 'image10'
            : (Schema::hasColumn('product_master', 'images10') ? 'images10' : null);
        $pmImage11Column = Schema::hasColumn('product_master', 'image11')
            ? 'image11'
            : (Schema::hasColumn('product_master', 'images11') ? 'images11' : null);
        $pmImage12Column = Schema::hasColumn('product_master', 'image12')
            ? 'image12'
            : (Schema::hasColumn('product_master', 'images12') ? 'images12' : null);

        $hasPricingCvrSnapshot = Schema::hasTable('pricing_master_daily_snapshots_sku');
        $hasJungleScoutProductData = Schema::hasTable('junglescout_product_data');
        $hasAmazonDataView = Schema::hasTable('amazon_data_view');

        $base = DB::query()
            ->fromSub($this->titleMasterSkuUnion(), 'skus')
            ->leftJoin('product_stock_mappings as psm', 'psm.sku', '=', 'skus.sku')
            ->leftJoin('product_master as pm', 'pm.sku', '=', 'skus.sku')
            ->leftJoinSub($latestAmazonBySku, 'alr', function ($join) {
                $join->on('alr.seller_sku', '=', 'skus.sku');
            })
            ->leftJoin('amazon_datsheets as ads', 'ads.sku', '=', 'skus.sku');
        $this->joinShopifySkusForTitleMaster($base);

        if ($hasAmazonDataView) {
            $base->leftJoin('amazon_data_view as adv', 'adv.sku', '=', 'skus.sku');
        }

        if ($hasJungleScoutProductData) {
            $jsMaxPerSku = DB::table('junglescout_product_data')
                ->select('sku', DB::raw('MAX(id) as max_id'))
                ->whereNotNull('sku')
                ->groupBy('sku');
            $jsLatestBySku = DB::table('junglescout_product_data as j2')
                ->joinSub($jsMaxPerSku, 'mx', function ($join) {
                    $join->on('j2.sku', '=', 'mx.sku')->on('j2.id', '=', 'mx.max_id');
                })
                ->select('j2.sku', 'j2.data');

            $jsMaxPerParent = DB::table('junglescout_product_data')
                ->select('parent', DB::raw('MAX(id) as max_id'))
                ->whereNotNull('parent')
                ->groupBy('parent');
            $jsLatestByParent = DB::table('junglescout_product_data as j2')
                ->joinSub($jsMaxPerParent, 'mx', function ($join) {
                    $join->on('j2.parent', '=', 'mx.parent')->on('j2.id', '=', 'mx.max_id');
                })
                ->select('j2.parent', 'j2.data');

            $base->leftJoinSub($jsLatestBySku, 'js_sku', function ($join) {
                $join->on('js_sku.sku', '=', 'skus.sku');
            });
            $base->leftJoinSub($jsLatestByParent, 'js_parent', function ($join) {
                $join->on('js_parent.parent', '=', 'pm.parent');
            });
        }

        if ($hasPricingCvrSnapshot) {
            if ($this->isMysql()) {
                $skuKey = $this->sqlSkuKeyForSnapshotJoin('skus.sku');
                $base->leftJoin('pricing_master_daily_snapshots_sku as pmds', function ($join) use ($skuKey) {
                    $join->whereRaw("pmds.sku = {$skuKey}")
                        ->whereRaw("pmds.snapshot_date = (SELECT MAX(p2.snapshot_date) FROM pricing_master_daily_snapshots_sku AS p2 WHERE p2.sku = {$skuKey})");
                });
            } else {
                $base->leftJoin('pricing_master_daily_snapshots_sku as pmds', function ($join) {
                    $join->on('pmds.sku', '=', 'skus.sku')
                        ->whereRaw('pmds.snapshot_date = (SELECT MAX(p2.snapshot_date) FROM pricing_master_daily_snapshots_sku AS p2 WHERE p2.sku = skus.sku)');
                });
            }
        }

        $this->applyFilters($base, $request, $hasPricingCvrSnapshot);

        $stats = $this->aggregateStats($base);

        $selectColumns = $this->selectColumns(
            $pmImage7Column,
            $pmImage8Column,
            $pmImage9Column,
            $pmImage10Column,
            $pmImage11Column,
            $pmImage12Column,
            $hasPricingCvrSnapshot,
            $hasJungleScoutProductData,
            $hasAmazonDataView
        );

        $dataQuery = $base->clone()->select($selectColumns);

        $sortMeta = $this->parseTitleMasterSortRequest($request);
        if ($sortMeta['column'] === 'cvr' && ! $hasPricingCvrSnapshot) {
            $sortMeta['column'] = 'sku';
        }

        if ($export) {
            $this->applyTitleMasterSort($dataQuery, $request, $hasPricingCvrSnapshot);
            $listings = $dataQuery->limit(15000)->get();
            $result = $this->mapListings($listings);

            return response()->json([
                'message' => 'Title Master export',
                'data' => $result,
                'stats' => $stats,
                'status' => 200,
            ]);
        }

        $this->applyTitleMasterSort($dataQuery, $request, $hasPricingCvrSnapshot);
        $paginator = $dataQuery->paginate($perPage, ['*'], 'page', $page);
        $result = $this->mapListings($paginator->items());

        return response()->json([
            'message' => 'Title Master data',
            'data' => $result,
            'stats' => $stats,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'tm_sort' => $sortMeta['column'],
                'tm_dir' => $sortMeta['dir'],
            ],
            'status' => 200,
        ]);
    }

    public function skuOptions(Request $request)
    {
        $q = $this->normalizeSearchWhitespace((string) $request->query('q', ''));
        $query = DB::query()->fromSub($this->titleMasterSkuUnion(), 'skus');
        if ($q !== '') {
            if ($this->isMysql()) {
                $col = $this->sqlWhitespaceCollapsed('COALESCE(skus.sku, \'\')');
                $pattern = $this->likeContainsPattern($q);
                $query->whereRaw("LOWER({$col}) LIKE LOWER(?)", [$pattern]);
            } else {
                $safe = addcslashes($q, '%_\\');
                $query->where('skus.sku', 'like', '%'.$safe.'%');
            }
        }
        $skus = $query->orderBy('skus.sku')->limit(500)->pluck('skus.sku');

        return response()->json(['data' => $skus]);
    }

    /**
     * INV used for filtering: snapshot first, then Shopify, then product_stock_mappings.inventory_shopify (same order as display fallback).
     */
    private function sqlTitleMasterEffectiveInvExpr(bool $hasPricingCvrSnapshot): string
    {
        $parts = ['ss.inv'];
        if ($hasPricingCvrSnapshot) {
            array_unshift($parts, 'pmds.inventory');
        }
        if (Schema::hasColumn('product_stock_mappings', 'inventory_shopify')) {
            $parts[] = 'psm.inventory_shopify';
        }

        return 'COALESCE('.implode(', ', $parts).')';
    }

    /** Numeric ordering for sort (avoids string sort on varchar inventory fields). */
    private function sqlTitleMasterNumericSortWrap(string $expr): string
    {
        if ($this->isMysql()) {
            return 'CAST(('.$expr.') AS DECIMAL(20,4))';
        }

        return 'CAST(('.$expr.') AS REAL)';
    }

    /**
     * Dil% for SQL sort: snapshot first, else Shopify OV L30 / INV (same idea as CVR Master).
     */
    private function sqlTitleMasterEffectiveDilSortExpr(bool $hasPricingCvrSnapshot): string
    {
        $shopifyDil = 'CASE WHEN ss.inv IS NOT NULL AND CAST(ss.inv AS DECIMAL(20,4)) > 0'
            .' THEN (CAST(ss.quantity AS DECIMAL(20,4)) / CAST(ss.inv AS DECIMAL(20,4))) * 100 ELSE NULL END';
        if ($hasPricingCvrSnapshot) {
            return 'COALESCE(pmds.dil_percent, '.$shopifyDil.')';
        }

        return $shopifyDil;
    }

    /**
     * @return array{column: string, dir: string}
     */
    private function parseTitleMasterSortRequest(Request $request): array
    {
        $sort = strtolower(trim((string) $request->query('tm_sort', 'sku')));
        if (! in_array($sort, ['sku', 'inv', 'dil', 'cvr'], true)) {
            $sort = 'sku';
        }
        $dir = strtolower(trim((string) $request->query('tm_dir', 'asc')));
        if ($dir !== 'desc') {
            $dir = 'asc';
        }

        return ['column' => $sort, 'dir' => $dir];
    }

    private function applyTitleMasterSort($query, Request $request, bool $hasPricingCvrSnapshot): void
    {
        $parsed = $this->parseTitleMasterSortRequest($request);
        $sort = $parsed['column'];
        $dir = strtoupper($parsed['dir']);
        if ($sort === 'cvr' && ! $hasPricingCvrSnapshot) {
            $sort = 'sku';
        }

        $nullsLast = '(CASE WHEN (%1$s) IS NULL THEN 1 ELSE 0 END) ASC';

        if ($sort === 'inv') {
            $e = $this->sqlTitleMasterEffectiveInvExpr($hasPricingCvrSnapshot);
            $eNum = $this->sqlTitleMasterNumericSortWrap($e);
            $query->orderByRaw(sprintf($nullsLast.', (%2$s) %3$s, skus.sku ASC', $e, $eNum, $dir));

            return;
        }

        if ($sort === 'dil') {
            $e = $this->sqlTitleMasterEffectiveDilSortExpr($hasPricingCvrSnapshot);
            $eNum = $this->sqlTitleMasterNumericSortWrap($e);
            $query->orderByRaw(sprintf($nullsLast.', (%2$s) %3$s, skus.sku ASC', $e, $eNum, $dir));

            return;
        }

        if ($sort === 'cvr') {
            $e = 'pmds.avg_cvr';
            $eNum = $this->isMysql()
                ? 'CAST(('.$e.') AS DECIMAL(20,8))'
                : 'CAST(('.$e.') AS REAL)';
            $query->orderByRaw(sprintf($nullsLast.', (%2$s) %3$s, skus.sku ASC', $e, $eNum, $dir));

            return;
        }

        $query->orderBy('skus.sku', $dir === 'DESC' ? 'desc' : 'asc');
    }

    private function applyFilters($query, Request $request, bool $hasPricingCvrSnapshot): void
    {
        $qParent = $this->normalizeSearchWhitespace((string) $request->query('q_parent', ''));
        $qSku = $this->normalizeSearchWhitespace((string) $request->query('q_sku', ''));
        $search = $this->normalizeSearchWhitespace((string) $request->query('search', ''));
        if ($qSku === '' && $search !== '') {
            $qSku = $search;
        }

        if ($qParent !== '') {
            if ($this->isMysql()) {
                $col = $this->sqlWhitespaceCollapsed('COALESCE(pm.parent, \'\')');
                $pattern = $this->likeContainsPattern($qParent);
                $query->whereRaw("LOWER({$col}) LIKE LOWER(?)", [$pattern]);
            } else {
                $safe = addcslashes($qParent, '%_\\');
                $query->where('pm.parent', 'like', '%'.$safe.'%');
            }
        }

        if ($qSku !== '') {
            if ($this->isMysql()) {
                $colKey = $this->sqlWhitespaceCollapsed('COALESCE(skus.sku, \'\')');
                $pattern = $this->likeContainsPattern($qSku);
                $query->whereRaw("LOWER({$colKey}) LIKE LOWER(?)", [$pattern]);
            } else {
                $safe = addcslashes($qSku, '%_\\');
                $query->where('skus.sku', 'like', '%'.$safe.'%');
            }
        }

        $f150 = (string) $request->query('filter_title150', 'all');
        if ($f150 === 'missing') {
            $query->whereRaw(
                '(IFNULL(NULLIF(TRIM(alr.item_name), ""), NULLIF(TRIM(ads.amazon_title), "")) IS NULL OR IFNULL(NULLIF(TRIM(alr.item_name), ""), NULLIF(TRIM(ads.amazon_title), "")) = "")'
            );
        } elseif ($f150 === 'exceeds') {
            $query->whereRaw(
                'CHAR_LENGTH(IFNULL(NULLIF(TRIM(alr.item_name), ""), NULLIF(TRIM(ads.amazon_title), ""))) > 150'
            );
        }

        $this->applyPmTitleMissingFilter($query, (string) $request->query('filter_title100', 'all'), 'pm.title100');
        $this->applyPmTitleMissingFilter($query, (string) $request->query('filter_title80', 'all'), 'pm.title80');
        $this->applyPmTitleMissingFilter($query, (string) $request->query('filter_title60', 'all'), 'pm.title60');

        $fInv = strtolower(trim((string) $request->query('filter_inv', 'gt_zero')));
        if ($fInv === '') {
            $fInv = 'gt_zero';
        }
        if ($fInv !== 'all') {
            $invExpr = $this->sqlTitleMasterEffectiveInvExpr($hasPricingCvrSnapshot);
            if ($fInv === 'zero' || $fInv === '0') {
                $query->whereRaw("COALESCE({$invExpr}, 0) = 0");
            } elseif ($fInv === 'gt_zero' || $fInv === 'gt0' || $fInv === '>0') {
                $query->whereRaw("({$invExpr}) > 0");
            }
        }
    }

    private function applyPmTitleMissingFilter($query, string $mode, string $column): void
    {
        if ($mode !== 'missing') {
            return;
        }
        $query->where(function ($q) use ($column) {
            $q->whereNull($column)->orWhereRaw('TRIM(IFNULL('.$column.', "")) = ""');
        });
    }

    private function aggregateStats($base): array
    {
        $row = $base->clone()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(DISTINCT pm.parent) as parents')
            ->selectRaw(
                'SUM(CASE WHEN (IFNULL(NULLIF(TRIM(alr.item_name), ""), NULLIF(TRIM(ads.amazon_title), "")) IS NULL OR IFNULL(NULLIF(TRIM(alr.item_name), ""), NULLIF(TRIM(ads.amazon_title), "")) = "") THEN 1 ELSE 0 END) as m150'
            )
            ->selectRaw(
                'SUM(CASE WHEN CHAR_LENGTH(IFNULL(NULLIF(TRIM(alr.item_name), ""), NULLIF(TRIM(ads.amazon_title), ""))) > 150 THEN 1 ELSE 0 END) as exceeds_150'
            )
            ->selectRaw('SUM(CASE WHEN (pm.title100 IS NULL OR TRIM(IFNULL(pm.title100, "")) = "") THEN 1 ELSE 0 END) as m100')
            ->selectRaw('SUM(CASE WHEN (pm.title80 IS NULL OR TRIM(IFNULL(pm.title80, "")) = "") THEN 1 ELSE 0 END) as m80')
            ->selectRaw('SUM(CASE WHEN (pm.title60 IS NULL OR TRIM(IFNULL(pm.title60, "")) = "") THEN 1 ELSE 0 END) as m60')
            ->first();

        return [
            'total_rows' => (int) ($row->total ?? 0),
            'distinct_parents' => (int) ($row->parents ?? 0),
            'title150_missing' => (int) ($row->m150 ?? 0),
            'title150_exceeds' => (int) ($row->exceeds_150 ?? 0),
            'title100_missing' => (int) ($row->m100 ?? 0),
            'title80_missing' => (int) ($row->m80 ?? 0),
            'title60_missing' => (int) ($row->m60 ?? 0),
        ];
    }

    private function selectColumns(
        ?string $pmImage7Column,
        ?string $pmImage8Column,
        ?string $pmImage9Column,
        ?string $pmImage10Column,
        ?string $pmImage11Column,
        ?string $pmImage12Column,
        bool $hasPricingCvrSnapshot = false,
        bool $hasJungleScoutProductData = false,
        bool $hasAmazonDataView = false
    ): array {
        $select = [
            'pm.id as pm_id',
            DB::raw('COALESCE(psm.sku, pm.sku, skus.sku) as psm_sku'),
            'pm.parent',
            'pm.title150',
            'pm.title100',
            'pm.title80',
            'pm.title60',
            'pm.bullet1',
            'pm.bullet2',
            'pm.bullet3',
            'pm.bullet4',
            'pm.bullet5',
            'pm.product_description',
            'pm.feature1',
            'pm.feature2',
            'pm.feature3',
            'pm.feature4',
            'pm.main_image',
            'pm.main_image_brand',
            'pm.image1',
            'pm.image2',
            'pm.image3',
            'pm.image4',
            'pm.image5',
            'pm.image6',
            'pm.Values as pm_values',
            'alr.seller_sku',
            'alr.item_name',
            'ads.amazon_title as ads_amazon_title',
            'ads.sku as ads_sku',
            'ss.image_src as shopify_image',
            'psm.image as psm_image',
        ];

        $add = [];
        if ($pmImage7Column) {
            $add[] = DB::raw("pm.`{$pmImage7Column}` as image7");
        } else {
            $add[] = DB::raw('NULL as image7');
        }
        if ($pmImage8Column) {
            $add[] = DB::raw("pm.`{$pmImage8Column}` as image8");
        } else {
            $add[] = DB::raw('NULL as image8');
        }
        if ($pmImage9Column) {
            $add[] = DB::raw("pm.`{$pmImage9Column}` as image9");
        } else {
            $add[] = DB::raw('NULL as image9');
        }
        if ($pmImage10Column) {
            $add[] = DB::raw("pm.`{$pmImage10Column}` as image10");
        } else {
            $add[] = DB::raw('NULL as image10');
        }
        if ($pmImage11Column) {
            $add[] = DB::raw("pm.`{$pmImage11Column}` as image11");
        } else {
            $add[] = DB::raw('NULL as image11');
        }
        if ($pmImage12Column) {
            $add[] = DB::raw("pm.`{$pmImage12Column}` as image12");
        } else {
            $add[] = DB::raw('NULL as image12');
        }

        if ($hasPricingCvrSnapshot) {
            $add[] = 'pmds.inventory as pricing_snapshot_inv';
            $add[] = 'pmds.dil_percent as pricing_snapshot_dil_percent';
            $add[] = 'pmds.avg_cvr as pricing_snapshot_avg_cvr';
        } else {
            $add[] = DB::raw('NULL as pricing_snapshot_inv');
            $add[] = DB::raw('NULL as pricing_snapshot_dil_percent');
            $add[] = DB::raw('NULL as pricing_snapshot_avg_cvr');
        }

        if (Schema::hasColumn('product_stock_mappings', 'inventory_shopify')) {
            $add[] = 'psm.inventory_shopify as tm_psm_inventory_shopify';
        } else {
            $add[] = DB::raw('NULL as tm_psm_inventory_shopify');
        }

        if ($hasJungleScoutProductData) {
            $add[] = DB::raw('COALESCE(js_sku.data, js_parent.data) as jspd_merged_data');
        } else {
            $add[] = DB::raw('NULL as jspd_merged_data');
        }

        if ($hasAmazonDataView) {
            if ($this->isMysql()) {
                $add[] = DB::raw('NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(adv.value, \'$.buyer_link\'))), \'\') as tm_adv_buyer_link');
                $add[] = DB::raw('NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(adv.value, \'$.seller_link\'))), \'\') as tm_adv_seller_link');
                $add[] = DB::raw('NULL as tm_adv_value');
            } else {
                $add[] = DB::raw('NULL as tm_adv_buyer_link');
                $add[] = DB::raw('NULL as tm_adv_seller_link');
                $add[] = 'adv.value as tm_adv_value';
            }
        } else {
            $add[] = DB::raw('NULL as tm_adv_buyer_link');
            $add[] = DB::raw('NULL as tm_adv_seller_link');
            $add[] = DB::raw('NULL as tm_adv_value');
        }

        return array_merge($select, $add);
    }

    /**
     * @param  mixed  $jspdMergedData  JSON text from junglescout_product_data.data (SKU row preferred, else parent row).
     */
    /**
     * Buyer / Seller links from amazon_data_view.value (same fields as Amazon FBM tabulator Links column).
     *
     * @return array{0: ?string, 1: ?string} [buyer_link, seller_link]
     */
    private function parseAmazonDataViewBsLinks(object $listing): array
    {
        $b = isset($listing->tm_adv_buyer_link) && $listing->tm_adv_buyer_link !== null && trim((string) $listing->tm_adv_buyer_link) !== ''
            ? trim((string) $listing->tm_adv_buyer_link)
            : null;
        $s = isset($listing->tm_adv_seller_link) && $listing->tm_adv_seller_link !== null && trim((string) $listing->tm_adv_seller_link) !== ''
            ? trim((string) $listing->tm_adv_seller_link)
            : null;

        if ($b === null && $s === null && isset($listing->tm_adv_value) && $listing->tm_adv_value !== null && $listing->tm_adv_value !== '') {
            $raw = $listing->tm_adv_value;
            if (is_array($raw)) {
                $decoded = $raw;
            } else {
                $decoded = json_decode((string) $raw, true);
            }
            if (is_array($decoded)) {
                $bRaw = $decoded['buyer_link'] ?? null;
                $sRaw = $decoded['seller_link'] ?? null;
                $b = $bRaw !== null && trim((string) $bRaw) !== '' ? trim((string) $bRaw) : null;
                $s = $sRaw !== null && trim((string) $sRaw) !== '' ? trim((string) $sRaw) : null;
            }
        }

        return [$b, $s];
    }

    private function parseJungleScoutListingQualityScore($jspdMergedData): ?int
    {
        if ($jspdMergedData === null || $jspdMergedData === '') {
            return null;
        }
        if (is_array($jspdMergedData)) {
            $decoded = $jspdMergedData;
        } else {
            $decoded = json_decode((string) $jspdMergedData, true);
        }
        if (! is_array($decoded) || ! array_key_exists('listing_quality_score', $decoded)) {
            return null;
        }
        $v = $decoded['listing_quality_score'];
        if ($v === null || $v === '') {
            return null;
        }
        if (is_string($v) && ! is_numeric(trim($v))) {
            return null;
        }

        return (int) round((float) $v);
    }

    /**
     * @param  iterable<object>  $listings
     */
    private function mapListings(iterable $listings): array
    {
        $result = [];
        foreach ($listings as $listing) {
            $sku = $listing->psm_sku ?: $listing->seller_sku;
            if (empty($sku)) {
                continue;
            }

            $amazonTitle = null;
            if (! empty($listing->item_name)) {
                $amazonTitle = trim((string) $listing->item_name);
            }
            if (empty($amazonTitle) && ! empty($listing->ads_amazon_title)) {
                $amazonTitle = trim((string) $listing->ads_amazon_title);
            }

            $row = [
                'id' => $listing->pm_id,
                'Parent' => $listing->parent,
                'SKU' => $sku,
                'amazon_title' => $amazonTitle,
                'title150' => $listing->title150,
                'title100' => $listing->title100,
                'title80' => $listing->title80,
                'title60' => $listing->title60,
                'bullet1' => $listing->bullet1,
                'bullet2' => $listing->bullet2,
                'bullet3' => $listing->bullet3,
                'bullet4' => $listing->bullet4,
                'bullet5' => $listing->bullet5,
                'product_description' => $listing->product_description,
                'feature1' => $listing->feature1,
                'feature2' => $listing->feature2,
                'feature3' => $listing->feature3,
                'feature4' => $listing->feature4,
                'main_image' => $listing->main_image,
                'main_image_brand' => $listing->main_image_brand,
                'image1' => $listing->image1,
                'image2' => $listing->image2,
                'image3' => $listing->image3,
                'image4' => $listing->image4,
                'image5' => $listing->image5,
                'image6' => $listing->image6,
                'image7' => $listing->image7,
                'image8' => $listing->image8,
                'image9' => $listing->image9,
                'image10' => $listing->image10,
                'image11' => $listing->image11,
                'image12' => $listing->image12,
            ];

            if (is_array($listing->pm_values)) {
                $row = array_merge($row, $listing->pm_values);
            } elseif (is_string($listing->pm_values)) {
                $values = json_decode($listing->pm_values, true);
                if (is_array($values)) {
                    $row = array_merge($row, $values);
                }
            }

            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($localImage && (strpos($localImage, 'storage/') !== false || strpos($localImage, '/storage/') !== false)) {
                $row['image_path'] = '/'.ltrim($localImage, '/');
            } elseif (! empty($listing->shopify_image)) {
                $row['image_path'] = $listing->shopify_image;
            } elseif ($localImage) {
                $row['image_path'] = '/'.ltrim($localImage, '/');
            } else {
                $row['image_path'] = $row['image_path'] ?? $listing->main_image ?? $listing->psm_image ?? null;
            }

            $row['pricing_cvr_inventory'] = isset($listing->pricing_snapshot_inv) && $listing->pricing_snapshot_inv !== null
                ? (int) $listing->pricing_snapshot_inv
                : null;
            $row['pricing_cvr_dil_percent'] = isset($listing->pricing_snapshot_dil_percent) && $listing->pricing_snapshot_dil_percent !== null
                ? round((float) $listing->pricing_snapshot_dil_percent, 2)
                : null;
            $row['pricing_cvr_avg_cvr'] = isset($listing->pricing_snapshot_avg_cvr) && $listing->pricing_snapshot_avg_cvr !== null
                ? (int) round((float) $listing->pricing_snapshot_avg_cvr)
                : null;

            $row['lqs'] = $this->parseJungleScoutListingQualityScore($listing->jspd_merged_data ?? null);

            [$row['amazon_buyer_link'], $row['amazon_seller_link']] = $this->parseAmazonDataViewBsLinks($listing);

            $row['_tm_psm_shopify_inv'] = isset($listing->tm_psm_inventory_shopify) && $listing->tm_psm_inventory_shopify !== null
                ? (int) $listing->tm_psm_inventory_shopify
                : null;

            $result[] = $row;
        }

        $this->applyPricingCvrLiveFallbacks($result);

        foreach ($result as &$r) {
            unset($r['_tm_psm_shopify_inv']);
        }
        unset($r);

        return $result;
    }

    /**
     * When no pricing snapshot row exists (or join missed), use the same Shopify INV / OV L30 as CVR Master
     * (ShopifySku::mapByProductSkus), then optional product_stock_mappings.inventory_shopify for INV only.
     * Avg CVR% stays null without a snapshot — it needs multi-marketplace views.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyPricingCvrLiveFallbacks(array &$rows): void
    {
        $needSkus = [];
        foreach ($rows as $r) {
            if ($this->rowNeedsPricingCvrLiveFallback($r)) {
                $sku = $r['SKU'] ?? '';
                if ($sku !== '') {
                    $needSkus[$sku] = true;
                }
            }
        }

        if ($needSkus === []) {
            return;
        }

        $shopifyByPmSku = ShopifySku::mapByProductSkus(array_keys($needSkus));

        foreach ($rows as &$r) {
            if (! $this->rowNeedsPricingCvrLiveFallback($r)) {
                continue;
            }

            $sku = (string) ($r['SKU'] ?? '');
            $hit = $shopifyByPmSku->get($sku);
            if ($hit !== null) {
                $inv = (int) ($hit->inv ?? 0);
                $ov = (float) ($hit->quantity ?? 0);
                $r['pricing_cvr_inventory'] = $inv;
                $r['pricing_cvr_dil_percent'] = $inv > 0 ? round(($ov / $inv) * 100, 2) : 0.0;

                continue;
            }

            $psmInv = $r['_tm_psm_shopify_inv'] ?? null;
            if ($psmInv !== null) {
                $r['pricing_cvr_inventory'] = (int) $psmInv;
                if ((int) $psmInv === 0) {
                    $r['pricing_cvr_dil_percent'] = 0.0;
                }
            }
        }
        unset($r);

        foreach ($rows as &$r) {
            if (($r['pricing_cvr_inventory'] ?? null) !== null && ($r['pricing_cvr_dil_percent'] ?? null) !== null) {
                continue;
            }
            $invFromVals = array_key_exists('shopify_inv', $r) && $r['shopify_inv'] !== null && $r['shopify_inv'] !== ''
                ? (int) round((float) $r['shopify_inv'])
                : null;
            $ovFromVals = array_key_exists('shopify_quantity', $r) && $r['shopify_quantity'] !== null && $r['shopify_quantity'] !== ''
                ? (float) $r['shopify_quantity']
                : null;
            if ($invFromVals === null && $ovFromVals === null) {
                continue;
            }
            if (($r['pricing_cvr_inventory'] ?? null) === null && $invFromVals !== null) {
                $r['pricing_cvr_inventory'] = $invFromVals;
            }
            if (($r['pricing_cvr_dil_percent'] ?? null) === null) {
                $invUse = (int) ($r['pricing_cvr_inventory'] ?? $invFromVals ?? 0);
                $ov = $ovFromVals ?? 0.0;
                $r['pricing_cvr_dil_percent'] = $invUse > 0 ? round(($ov / $invUse) * 100, 2) : 0.0;
            }
        }
        unset($r);
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function rowNeedsPricingCvrLiveFallback(array $r): bool
    {
        return ($r['pricing_cvr_inventory'] ?? null) === null
            && ($r['pricing_cvr_dil_percent'] ?? null) === null
            && ($r['pricing_cvr_avg_cvr'] ?? null) === null;
    }
}
