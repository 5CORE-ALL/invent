<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DHGateAnalyticsController extends Controller
{
    public function index(): View
    {
        return view('market-places.dhgate_analytics');
    }

    public function data(): JsonResponse
    {
        try {
            $productMasters = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->orderBy('parent')
                ->orderBy('sku')
                ->get(['id', 'sku', 'parent', 'Values', 'main_image']);

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyBySku = $skus === [] ? collect() : ShopifySku::mapByProductSkus($skus);

            $children = [];
            foreach ($productMasters as $pm) {
                $sku = trim((string) $pm->sku);
                if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                    continue;
                }

                $shopify = $shopifyBySku->get($sku);
                $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;
                if ($inv < 0) {
                    $inv = 0;
                }
                $ovL30 = $shopify ? (int) ($shopify->quantity ?? 0) : 0;
                if ($ovL30 < 0) {
                    $ovL30 = 0;
                }
                $dil = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0.0;
                $parent = trim((string) ($pm->parent ?? ''));
                $image = $this->productImage($pm, $shopify);

                $children[] = [
                    'id' => $pm->id,
                    'Parent' => $parent,
                    'parent' => $parent,
                    'sku' => $sku,
                    '(Child) sku' => $sku,
                    'image' => $image,
                    'image_path' => $image,
                    'inv' => $inv,
                    'INV' => $inv,
                    'ov_l30' => $ovL30,
                    'L30' => $ovL30,
                    'dil' => $dil,
                    'dil_percent' => $dil,
                    'is_parent' => false,
                    'is_parent_summary' => false,
                    'is_parent_row' => false,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $this->insertParentRows($children),
                'count' => count($children),
            ]);
        } catch (\Throwable $e) {
            Log::error('DH Gate analytics data failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param  mixed  $shopify
     */
    private function productImage(ProductMaster $pm, $shopify): ?string
    {
        $shopifyImage = is_object($shopify) ? ($shopify->image_src ?? null) : null;
        if (is_string($shopifyImage) && trim($shopifyImage) !== '') {
            return $shopifyImage;
        }

        $values = is_array($pm->Values) ? $pm->Values : [];
        $path = $values['image_path'] ?? ($pm->main_image ?? null);

        return is_string($path) && trim($path) !== '' ? $path : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function insertParentRows(array $rows): array
    {
        $result = [];
        $group = [];
        $currentParent = null;

        foreach ($rows as $row) {
            $parent = trim((string) ($row['Parent'] ?? ''));
            $parent = $parent !== '' ? $parent : null;

            if ($parent === null) {
                if ($group !== []) {
                    foreach ($group as $child) {
                        $result[] = $child;
                    }
                    $result[] = $this->parentRow((string) $currentParent, $group);
                    $group = [];
                    $currentParent = null;
                }
                $result[] = $row;
                continue;
            }

            if ($parent !== $currentParent) {
                if ($group !== []) {
                    foreach ($group as $child) {
                        $result[] = $child;
                    }
                    $result[] = $this->parentRow((string) $currentParent, $group);
                    $group = [];
                }
                $currentParent = $parent;
            }
            $group[] = $row;
        }

        if ($group !== []) {
            foreach ($group as $child) {
                $result[] = $child;
            }
            $result[] = $this->parentRow((string) $currentParent, $group);
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $childRows
     * @return array<string, mixed>
     */
    private function parentRow(string $parentName, array $childRows): array
    {
        $sumInv = 0;
        $sumOvL30 = 0;
        $seen = [];

        foreach ($childRows as $row) {
            $skuKey = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($skuKey !== '' && isset($seen[$skuKey])) {
                continue;
            }
            if ($skuKey !== '') {
                $seen[$skuKey] = true;
            }
            $sumInv += (int) ($row['INV'] ?? 0);
            $sumOvL30 += (int) ($row['L30'] ?? 0);
        }

        $dil = $sumInv > 0 ? round(($sumOvL30 / $sumInv) * 100, 2) : 0.0;
        $key = 'PARENT '.$parentName;

        return [
            'id' => null,
            'Parent' => $key,
            'parent' => $parentName,
            'sku' => $key,
            '(Child) sku' => $key,
            'image' => null,
            'image_path' => null,
            'inv' => $sumInv,
            'INV' => $sumInv,
            'ov_l30' => $sumOvL30,
            'L30' => $sumOvL30,
            'dil' => $dil,
            'dil_percent' => $dil,
            'is_parent' => true,
            'is_parent_summary' => true,
            'is_parent_row' => true,
        ];
    }
}
