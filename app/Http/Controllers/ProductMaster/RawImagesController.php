<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ProductRawImage;
use App\Models\ShopifySku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RawImagesController extends Controller
{
    private const SIDEBAR_CACHE_PREFIX = 'raw_images_missing_sidebar_count';

    private const MAX_FILES = 20;

    public function index(Request $request): View
    {
        return view('raw-images', $this->pageConfig($this->kindFromRequest($request)));
    }

    public function getData(Request $request): JsonResponse
    {
        $kind = $this->kindFromRequest($request);

        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $shopifySkus = ShopifySku::all()->keyBy(function ($item) {
            return $this->normalizeSku($item->sku);
        });

        $rawBySku = ProductRawImage::query()
            ->where('kind', $kind)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (ProductRawImage $img) => $this->normalizeSku($img->sku));

        $result = [];
        foreach ($products as $product) {
            $normalizedSku = $this->normalizeSku($product->sku);

            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
            ];

            if (is_array($product->Values)) {
                $row = array_merge($row, $product->Values);
            } elseif (is_string($product->Values)) {
                $values = json_decode($product->Values, true);
                if (is_array($values)) {
                    $row = array_merge($row, $values);
                }
            }

            if (isset($shopifySkus[$normalizedSku])) {
                $shopifyData = $shopifySkus[$normalizedSku];
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float) $shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float) $shopifyData->quantity : 0;
                $row['ovl30'] = $row['shopify_quantity'];

                $inv = $row['shopify_inv'];
                $ovl30 = $row['shopify_quantity'];
                $row['dil'] = ($inv > 0) ? round(($ovl30 / $inv) * 100, 2) : 0;

                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $row['ovl30'] = 0;
                $row['dil'] = 0;
                $shopifyImage = null;
            }

            $localImage = ! empty($row['image_path']) ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage;
            } elseif ($localImage) {
                $row['image_path'] = '/'.ltrim($localImage, '/');
            } else {
                $row['image_path'] = null;
            }

            $images = $rawBySku[$normalizedSku] ?? collect();
            $row['raw_images'] = $images->map(fn (ProductRawImage $img) => $img->toUiArray())->values()->all();
            $row['raw_image_count'] = $images->count();
            $row['has_raw_image'] = $images->isNotEmpty();
            $row['raw_image_url'] = $images->first()?->url;

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $kind = $this->kindFromRequest($request);

        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'files' => 'required|array|min:1|max:'.self::MAX_FILES,
            'files.*' => 'file|max:51200',
        ]);

        $sku = $this->normalizeSku($validated['sku']);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        $product = ProductMaster::where('sku', $sku)
            ->orWhere('sku', $validated['sku'])
            ->first();
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found for SKU: '.$sku], 404);
        }

        $allowedExt = [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic', 'heif',
            'dng', 'cr2', 'cr3', 'nef', 'arw', 'raf', 'orf', 'rw2',
        ];

        $safeSku = preg_replace('/[^a-zA-Z0-9_\- ]/', '_', $sku) ?: 'sku';
        $folder = 'raw-images/'.$kind.'/'.$safeSku;
        $uploaded = [];

        foreach ($request->file('files', []) as $file) {
            if (! $file) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: '');
            if ($ext === '' || ! in_array($ext, $allowedExt, true)) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $baseName = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME)) ?: 'raw';
            $uniqueName = $baseName.'_'.uniqid().'.'.$ext;
            $path = $file->storeAs($folder, $uniqueName, 'public');

            $record = ProductRawImage::create([
                'sku' => $sku,
                'kind' => $kind,
                'image_path' => $path,
                'original_name' => $originalName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            ]);

            $uploaded[] = $record->toUiArray();
        }

        if ($uploaded === []) {
            return response()->json([
                'success' => false,
                'message' => 'No valid image files were uploaded. Use JPG, PNG, WEBP, or camera RAW files.',
            ], 422);
        }

        self::forgetMissingSidebarCountCache($kind);

        return response()->json([
            'success' => true,
            'message' => count($uploaded) === 1 ? 'Raw image uploaded.' : count($uploaded).' raw images uploaded.',
            'images' => $this->imagesForSku($sku, $kind),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $kind = $this->kindFromRequest($request);
        $image = ProductRawImage::query()->where('kind', $kind)->find($id);
        if (! $image) {
            return response()->json(['success' => false, 'message' => 'Raw image not found.'], 404);
        }

        $sku = $this->normalizeSku($image->sku);

        try {
            if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
        } catch (\Throwable $e) {
            Log::warning('Raw image disk delete failed', [
                'id' => $id,
                'path' => $image->image_path,
                'error' => $e->getMessage(),
            ]);
        }

        $image->delete();
        self::forgetMissingSidebarCountCache($kind);

        return response()->json([
            'success' => true,
            'message' => 'Raw image removed.',
            'images' => $this->imagesForSku($sku, $kind),
        ]);
    }

    public static function missingCountForSidebar(string $kind = ProductRawImage::KIND_RAW): int
    {
        try {
            return (int) Cache::remember(self::sidebarCacheKey($kind), 300, function () use ($kind) {
                if (! Schema::hasTable('product_master') || ! Schema::hasTable('product_raw_images')) {
                    return 0;
                }

                $skusWithRaw = ProductRawImage::query()->where('kind', $kind)->select('sku');

                return (int) ProductMaster::query()
                    ->whereRaw("UPPER(sku) NOT LIKE '%PARENT%'")
                    ->whereNotIn('sku', $skusWithRaw)
                    ->count();
            });
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function forgetMissingSidebarCountCache(?string $kind = null): void
    {
        if ($kind) {
            Cache::forget(self::sidebarCacheKey($kind));

            return;
        }

        Cache::forget(self::sidebarCacheKey(ProductRawImage::KIND_RAW));
        Cache::forget(self::sidebarCacheKey(ProductRawImage::KIND_BATCH_COO));
    }

    /**
     * @return array<string, string>
     */
    private function pageConfig(string $kind): array
    {
        if ($kind === ProductRawImage::KIND_BATCH_COO) {
            return [
                'pageTitle' => 'Raw Images (Batch +COO)',
                'pageSubtitle' => 'Upload batch and COO raw image files by SKU',
                'kind' => $kind,
                'dataUrl' => route('raw.images.batch.coo.data'),
                'uploadUrl' => route('raw.images.batch.coo.upload'),
                'destroyBaseUrl' => url('/raw-images-batch-coo'),
            ];
        }

        return [
            'pageTitle' => 'Raw Images',
            'pageSubtitle' => 'Upload original raw image files by SKU',
            'kind' => ProductRawImage::KIND_RAW,
            'dataUrl' => route('raw.images.data'),
            'uploadUrl' => route('raw.images.upload'),
            'destroyBaseUrl' => url('/raw-images'),
        ];
    }

    private function kindFromRequest(Request $request): string
    {
        $name = (string) ($request->route()?->getName() ?? '');
        if (str_contains($name, 'batch.coo')) {
            return ProductRawImage::KIND_BATCH_COO;
        }

        $kind = (string) $request->input('kind', '');
        if (in_array($kind, [ProductRawImage::KIND_RAW, ProductRawImage::KIND_BATCH_COO], true)) {
            return $kind;
        }

        return ProductRawImage::KIND_RAW;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function imagesForSku(string $sku, string $kind): array
    {
        return ProductRawImage::query()
            ->where('sku', $sku)
            ->where('kind', $kind)
            ->orderBy('id')
            ->get()
            ->map(fn (ProductRawImage $img) => $img->toUiArray())
            ->values()
            ->all();
    }

    private function normalizeSku(?string $sku): string
    {
        return trim(str_replace("\u{00a0}", ' ', (string) $sku));
    }

    private static function sidebarCacheKey(string $kind): string
    {
        return self::SIDEBAR_CACHE_PREFIX.':'.$kind;
    }
}
