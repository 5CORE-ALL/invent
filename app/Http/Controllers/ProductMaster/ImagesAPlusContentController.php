<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\Concerns\SyncsAPlusContentFields;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ImagesAPlusContentController extends Controller
{
    use SyncsAPlusContentFields;

    public function index(Request $request)
    {
        return view('images-a-plus-content', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
        ]);
    }

    /**
     * GET /images-a-plus-content-data — same product row format as Description Master + A+ images.
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
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->whereRaw("UPPER(COALESCE(sku, '')) NOT LIKE '%PARENT%'")
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
                'message' => 'Images A+ Content data loaded',
                'data' => $result,
                'status' => 200,
                'meta' => ['total' => count($result)],
            ]);
        } catch (\Throwable $e) {
            Log::error('ImagesAPlusContent: getData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load Images A+ Content data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * POST /images-a-plus-content/save — syncs A+ Content + Images A+ Content fields.
     */
    public function save(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'aplus_images' => 'nullable|array',
                'aplus_images.*' => 'nullable|string|max:2048',
                'description_v2_images' => 'nullable|array',
                'description_v2_images.*' => 'nullable|string|max:2048',
            ]);

            $sku = trim($validated['sku']);
            $product = ProductMaster::where('sku', $sku)->first();
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            if (! Schema::hasColumn('product_master', 'description_v2_images')) {
                return response()->json([
                    'success' => false,
                    'message' => 'A+ images column is not available. Run migrations.',
                ], 500);
            }

            $raw = $validated['aplus_images'] ?? $validated['description_v2_images'] ?? [];
            $this->syncAPlusImageFields($product, is_array($raw) ? $raw : []);
            $product->save();

            $synced = $this->aPlusSyncedPayload($product);

            return response()->json(array_merge([
                'success' => true,
                'message' => 'A+ Content images saved and synced to A+ Content page.',
            ], $synced));
        } catch (\Throwable $e) {
            Log::error('ImagesAPlusContent: save failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save A+ images: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /images-a-plus-content/upload-image
     */
    public function uploadImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'slot' => 'required|integer|min:0|max:11',
                'image_file' => 'required|file|mimes:jpeg,jpg,png,gif,bmp,webp,svg|max:10240',
                'current_images' => 'nullable|string',
            ]);

            $sku = trim(str_replace("\u{00a0}", ' ', $validated['sku']));
            $product = ProductMaster::where('sku', $sku)->first()
                ?: ProductMaster::where('sku', $validated['sku'])->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found for SKU: '.$validated['sku'],
                ], 404);
            }

            if (! Schema::hasColumn('product_master', 'description_v2_images')) {
                return response()->json([
                    'success' => false,
                    'message' => 'A+ images column is not available. Run migrations.',
                ], 500);
            }

            $imageFile = $request->file('image_file');
            $safeSku = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $sku) ?: 'sku';
            $imageName = 'aplus_'.$safeSku.'_slot'.$validated['slot'].'_'.time().'.'.$imageFile->getClientOriginalExtension();

            $directory = 'aplus_content_images';
            if (! Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $imagePath = $imageFile->storeAs($directory, $imageName, 'public');
            $publicUrl = asset('storage/'.$imagePath);

            $slotImages = array_fill(0, self::APLUS_MAX_IMAGES, '');
            $currentRaw = json_decode((string) ($validated['current_images'] ?? '[]'), true);
            if (! is_array($currentRaw) || $currentRaw === []) {
                $currentRaw = $this->normalizeAPlusImages($product->description_v2_images ?? null);
            }
            foreach (array_values($currentRaw) as $i => $url) {
                if ($i >= self::APLUS_MAX_IMAGES) {
                    break;
                }
                $slotImages[$i] = trim((string) $url);
            }
            $slotImages[(int) $validated['slot']] = $publicUrl;

            $this->syncAPlusImageFields($product, $slotImages);
            $product->save();

            $compact = $this->normalizeAPlusImages($slotImages);

            return response()->json([
                'success' => true,
                'message' => 'A+ image uploaded and synced to A+ Content page.',
                'image_url' => $publicUrl,
                'image_path' => $imagePath,
                'slot' => (int) $validated['slot'],
                'slot_images' => $slotImages,
                'aplus_images' => $compact,
                'description_v2_images' => $compact,
            ]);
        } catch (\Throwable $e) {
            Log::error('ImagesAPlusContent: upload failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: '.$e->getMessage(),
            ], 500);
        }
    }
}
