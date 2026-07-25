<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\Concerns\SyncsAPlusContentFields;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DescriptionForHtmlController extends Controller
{
    use SyncsAPlusContentFields;

    public function index(Request $request)
    {
        return view('description-for-html', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
        ]);
    }

    /**
     * GET /description-for-html-data — same product rows + description fields as Description Master.
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
            if (Schema::hasColumn('product_master', 'description_html')) {
                $select[] = 'description_html';
            }
            if (Schema::hasColumn('product_master', 'description_v2_images')) {
                $select[] = 'description_v2_images';
            }

            $products = ProductMaster::query()
                ->orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 0 ELSE 1 END")
                ->orderBy('sku', 'asc')
                ->select($select)
                ->get();

            $result = [];
            foreach ($products as $product) {
                $synced = $this->aPlusSyncedPayload($product);

                $result[] = array_merge([
                    'id' => $product->id,
                    'Parent' => $product->parent,
                    'SKU' => $product->sku,
                    'title150' => $product->title150,
                    'description_1000' => $product->description_1000,
                    'description_800' => $product->description_800,
                    'description_600' => $product->description_600,
                ], $synced);
            }

            return response()->json([
                'message' => 'Description For HTML data loaded',
                'data' => $result,
                'status' => 200,
                'meta' => ['total' => count($result)],
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionForHtml: getData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load Description For HTML data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * POST /description-for-html/save — syncs Description Master + A+ Content HTML fields.
     */
    public function save(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'description_html' => 'nullable|string',
                'description_for_html' => 'nullable|string',
            ]);

            $sku = trim($validated['sku']);
            $html = (string) ($validated['description_for_html'] ?? $validated['description_html'] ?? '');

            $product = ProductMaster::where('sku', $sku)->first();
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            $this->syncDescriptionHtmlFields($product, $html);
            $product->save();

            $synced = $this->aPlusSyncedPayload($product);

            return response()->json(array_merge([
                'success' => true,
                'message' => 'Description For HTML saved and synced to A+ Content / Description Master.',
            ], $synced));
        } catch (\Throwable $e) {
            Log::error('DescriptionForHtml: save failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save Description For HTML: '.$e->getMessage(),
            ], 500);
        }
    }
}
