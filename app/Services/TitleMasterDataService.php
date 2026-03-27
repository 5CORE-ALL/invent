<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Title Master list query: paginated, server-side filters, aggregates (no amazon_listings_raw.raw_data).
 */
class TitleMasterDataService
{
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

        $base = DB::table('product_stock_mappings as psm')
            ->leftJoin('product_master as pm', 'pm.sku', '=', 'psm.sku')
            ->leftJoinSub($latestAmazonBySku, 'alr', function ($join) {
                $join->on('alr.seller_sku', '=', 'psm.sku');
            })
            ->leftJoin('amazon_datsheets as ads', 'ads.sku', '=', 'psm.sku')
            ->leftJoin('shopify_skus as ss', 'ss.sku', '=', 'psm.sku')
            ->whereRaw("UPPER(COALESCE(psm.sku, '')) NOT LIKE '%PARENT%'");

        $this->applyFilters($base, $request);

        $stats = $this->aggregateStats($base);

        $selectColumns = $this->selectColumns(
            $pmImage7Column,
            $pmImage8Column,
            $pmImage9Column,
            $pmImage10Column,
            $pmImage11Column,
            $pmImage12Column
        );

        $dataQuery = $base->clone()->select($selectColumns);

        if ($export) {
            $listings = $dataQuery->orderBy('psm.sku')->limit(15000)->get();
            $result = $this->mapListings($listings);

            return response()->json([
                'message' => 'Title Master export',
                'data' => $result,
                'stats' => $stats,
                'status' => 200,
            ]);
        }

        $paginator = $dataQuery->orderBy('psm.sku')->paginate($perPage, ['*'], 'page', $page);
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
            ],
            'status' => 200,
        ]);
    }

    public function skuOptions(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = DB::table('product_stock_mappings')
            ->whereNotNull('sku')
            ->whereRaw("UPPER(COALESCE(sku, '')) NOT LIKE '%PARENT%'");
        if ($q !== '') {
            $safe = addcslashes($q, '%_\\');
            $query->where('sku', 'like', '%'.$safe.'%');
        }
        $skus = $query->orderBy('sku')->limit(500)->pluck('sku');

        return response()->json(['data' => $skus]);
    }

    private function applyFilters($query, Request $request): void
    {
        $qParent = trim((string) $request->query('q_parent', ''));
        $qSku = trim((string) $request->query('q_sku', ''));
        $search = trim((string) $request->query('search', ''));
        if ($qSku === '' && $search !== '') {
            $qSku = $search;
        }

        if ($qParent !== '') {
            $safe = addcslashes($qParent, '%_\\');
            $query->where('pm.parent', 'like', '%'.$safe.'%');
        }

        if ($qSku !== '') {
            $safe = addcslashes($qSku, '%_\\');
            $query->where(function ($q) use ($safe) {
                $q->where('psm.sku', 'like', '%'.$safe.'%')
                    ->orWhere('alr.seller_sku', 'like', '%'.$safe.'%');
            });
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
        ?string $pmImage12Column
    ): array {
        $select = [
            'pm.id as pm_id',
            'psm.sku as psm_sku',
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

        return array_merge($select, $add);
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

            $result[] = $row;
        }

        return $result;
    }
}
