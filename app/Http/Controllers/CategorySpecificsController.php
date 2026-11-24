<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategorySpecificMap;
use App\Models\ProductCategory;
use App\Models\ProductMaster;
use App\Support\Marketplace\EbayCategoryAspects;
use App\Support\Marketplace\EbayCategoryTree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Category Specifics: one tab per MFR category (the Category list page), each showing the item specifics of the
 * eBay category it matches (the Ebay1 Data page). When an MFR category and an eBay category do not match by
 * name or by their SKUs, the page asks which eBay category is the right one and keeps the answer here.
 */
class CategorySpecificsController extends Controller
{
    public function index()
    {
        return view('category-specifics');
    }

    /**
     * GET /category-specifics/data
     * The MFR categories with their SKUs (CP Master, same link as the Category list), and the confirmed matches.
     * The eBay side (categories and specifics) is read by the page from the Ebay1 Data rows.
     */
    public function data(): JsonResponse
    {
        CategorySpecificMap::ensureTable();

        $categories = Category::query()->orderBy('name')->get(['id', 'name']);

        // MFR category id (product_categories) => category key, for the categories of the Category list.
        $keys = [];
        foreach ($categories as $category) {
            $key = CategorySpecificMap::keyFor((string) $category->name);
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
        $keyByMfrId = [];
        foreach (ProductCategory::query()->get(['id', 'category_name']) as $mfr) {
            $key = CategorySpecificMap::keyFor((string) $mfr->category_name);
            if (isset($keys[$key])) {
                $keyByMfrId[(int) $mfr->id] = $key;
            }
        }

        $skusByKey = [];
        if ($keyByMfrId !== [] && Schema::hasTable('product_master')) {
            $select = ['sku', 'parent', 'category_id'];
            if (Schema::hasColumn('product_master', 'mfr_category_ids')) {
                $select[] = 'mfr_category_ids';
            }
            $query = ProductMaster::query()->whereNotNull('sku')->where('sku', '!=', '');
            $query->where(function ($q) {
                $q->whereNotNull('category_id');
                if (Schema::hasColumn('product_master', 'mfr_category_ids')) {
                    $q->orWhereNotNull('mfr_category_ids');
                }
            });
            foreach ($query->get($select) as $product) {
                $sku = trim((string) $product->sku);
                if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                    continue; // the eBay pages list child SKUs only
                }
                $ids = is_array($product->mfr_category_ids ?? null) ? array_map('intval', $product->mfr_category_ids) : [];
                if ($product->category_id) {
                    $ids[] = (int) $product->category_id;
                }
                foreach (array_unique($ids) as $id) {
                    if (isset($keyByMfrId[$id])) {
                        $skusByKey[$keyByMfrId[$id]][$sku] = trim((string) ($product->parent ?? ''));
                    }
                }
            }
        }

        $list = [];
        foreach ($categories as $category) {
            $name = trim((string) $category->name);
            $key = CategorySpecificMap::keyFor($name);
            if ($key === '') {
                continue;
            }
            $skus = array_keys($skusByKey[$key] ?? []);
            sort($skus, SORT_NATURAL | SORT_FLAG_CASE);
            $list[$key] = [
                'id' => (int) $category->id,
                'key' => $key,
                'name' => $name,
                'skus' => $skus,
            ];
        }

        $maps = [];
        foreach (CategorySpecificMap::query()->get() as $map) {
            $maps[$map->mfr_key] = [
                'path' => (string) $map->ebay_path,
                'id' => (string) ($map->ebay_category_id ?? ''),
                'at' => optional($map->updated_at)->toDateTimeString(),
            ];
        }

        return response()->json([
            'success' => true,
            'categories' => array_values($list),
            'maps' => (object) $maps,
        ]);
    }

    /**
     * GET /category-specifics/aspects?path=&id=
     * EVERY item specific eBay defines for a category (required ones first), not only the ones SKUs already carry.
     * eBay's Taxonomy answer is cached for 7 days.
     */
    public function aspects(Request $request): JsonResponse
    {
        @set_time_limit(120);
        $path = trim((string) $request->query('path', ''));
        $id = trim((string) $request->query('id', ''));

        if ($id === '' || ! ctype_digit($id)) {
            $id = '';
            $found = $path !== '' ? EbayCategoryTree::byPath($path) : null;
            if ($found !== null) {
                $id = $found['id'];
            }
        }
        if ($id === '') {
            return response()->json([
                'success' => false,
                'message' => EbayCategoryTree::$error !== ''
                    ? EbayCategoryTree::$error
                    : 'That eBay category was not found in the eBay category tree, so its full list of specifics is not available.',
            ], 200);
        }

        $aspects = EbayCategoryAspects::forCategory($id, '', (string) config('services.ebay.app_id'), (string) config('services.ebay.cert_id'));
        if ($aspects === []) {
            return response()->json(['success' => false, 'category_id' => $id, 'message' => 'eBay did not return the specifics of category '.$id.'. Try again in a minute.'], 200);
        }

        usort($aspects, static function ($a, $b) {
            return ((int) $b['required'] <=> (int) $a['required']);
        });

        return response()->json([
            'success' => true,
            'category_id' => $id,
            'aspects' => array_map(static fn ($a) => [
                'name' => $a['name'],
                'required' => (bool) $a['required'],
                'variations' => (bool) $a['variations'],
                'free_text' => (bool) $a['free_text'],
                'value_count' => count($a['values']),
                'values' => array_slice($a['values'], 0, 25),
            ], $aspects),
        ]);
    }

    /** POST /category-specifics/map : confirm which eBay category an MFR category is. */
    public function saveMap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mfr_name' => 'required|string|max:191',
            'ebay_path' => 'required|string|max:500',
            'ebay_category_id' => 'nullable|string|max:64',
        ]);
        CategorySpecificMap::ensureTable();

        $key = CategorySpecificMap::keyFor($data['mfr_name']);
        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'The MFR category name is empty.'], 422);
        }

        $map = CategorySpecificMap::query()->updateOrCreate(
            ['mfr_key' => $key],
            [
                'mfr_name' => trim($data['mfr_name']),
                'ebay_path' => trim($data['ebay_path']),
                'ebay_category_id' => trim((string) ($data['ebay_category_id'] ?? '')) ?: null,
                'confirmed_by' => $request->user()?->id,
            ]
        );

        return response()->json([
            'success' => true,
            'key' => $key,
            'map' => ['path' => (string) $map->ebay_path, 'id' => (string) ($map->ebay_category_id ?? ''), 'at' => optional($map->updated_at)->toDateTimeString()],
        ]);
    }

    /** POST /category-specifics/unmap : forget a confirmed match, so the page asks again. */
    public function removeMap(Request $request): JsonResponse
    {
        $data = $request->validate(['mfr_name' => 'required|string|max:191']);
        CategorySpecificMap::ensureTable();
        CategorySpecificMap::query()->where('mfr_key', CategorySpecificMap::keyFor($data['mfr_name']))->delete();

        return response()->json(['success' => true]);
    }
}
