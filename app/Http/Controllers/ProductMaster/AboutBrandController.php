<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AboutBrandController extends Controller
{
    public function index(Request $request)
    {
        return view('about-brand', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
        ]);
    }

    /**
     * GET /about-brand-data — same product row format as Description Master, plus About Brand.
     */
    public function getData(Request $request)
    {
        try {
            @set_time_limit(180);
            @ini_set('memory_limit', '512M');

            $select = [
                'id', 'parent', 'sku', 'title150',
                'product_description', 'description_1500', 'description_1000', 'description_800', 'description_600',
            ];
            if (Schema::hasColumn('product_master', 'description_v2_brand')) {
                $select[] = 'description_v2_brand';
            }

            $products = ProductMaster::query()
                ->orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->whereRaw("UPPER(COALESCE(sku, '')) NOT LIKE '%PARENT%'")
                ->select($select)
                ->get();

            $result = [];
            foreach ($products as $product) {
                $result[] = [
                    'id' => $product->id,
                    'Parent' => $product->parent,
                    'SKU' => $product->sku,
                    'title150' => $product->title150,
                    'product_description' => $product->product_description,
                    'description_1500' => $product->description_1500,
                    'description_1000' => $product->description_1000,
                    'description_800' => $product->description_800,
                    'description_600' => $product->description_600,
                    'description_v2_brand' => (string) ($product->description_v2_brand ?? ''),
                    'about_brand' => (string) ($product->description_v2_brand ?? ''),
                ];
            }

            return response()->json([
                'message' => 'About Brand data loaded',
                'data' => $result,
                'status' => 200,
                'meta' => ['total' => count($result)],
            ]);
        } catch (\Throwable $e) {
            Log::error('AboutBrand: getData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load About Brand data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * POST /about-brand/save
     */
    public function save(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'about_brand' => 'nullable|string',
                'description_v2_brand' => 'nullable|string',
            ]);

            $sku = trim($validated['sku']);
            $brand = (string) ($validated['about_brand'] ?? $validated['description_v2_brand'] ?? '');

            $product = ProductMaster::where('sku', $sku)->first();
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            if (! Schema::hasColumn('product_master', 'description_v2_brand')) {
                return response()->json([
                    'success' => false,
                    'message' => 'About Brand column is not available. Run migrations.',
                ], 500);
            }

            $product->description_v2_brand = $brand;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'About Brand saved successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('AboutBrand: save failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save About Brand: '.$e->getMessage(),
            ], 500);
        }
    }
}
