<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorPNG;

class MastersBarcodeController extends Controller
{
    public function index(Request $request)
    {
        return view('masters-barcode', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
        ]);
    }

    /**
     * GET /masters-barcode-data
     */
    public function getData()
    {
        try {
            $select = ['id', 'sku', 'parent', 'Values', 'main_image', 'image1'];
            if (Schema::hasColumn('product_master', 'barcode')) {
                $select[] = 'barcode';
            }

            $products = ProductMaster::query()
                ->orderBy('sku', 'asc')
                ->select($select)
                ->get();

            $rows = [];
            foreach ($products as $product) {
                $sku = trim((string) $product->sku);
                if ($sku === '') {
                    continue;
                }

                $values = $this->productValues($product);
                $upc = $this->extractUpc($values);
                $barcode = trim((string) ($product->barcode ?? ''));
                $displayCode = $barcode !== '' ? $barcode : $upc;
                $barcodeImage = $this->normalizeImageUrl($values['barcode_image'] ?? null);

                $rows[] = [
                    'id' => $product->id,
                    'sku' => $sku,
                    'parent' => trim((string) ($product->parent ?? '')),
                    'upc' => $upc,
                    'barcode' => $displayCode,
                    'barcode_image' => $barcodeImage,
                    'image' => $this->resolveProductImage($product, $values),
                ];
            }

            return response()->json([
                'message' => 'Masters Barcode data loaded',
                'data' => $rows,
                'status' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('MastersBarcode getData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load Masters Barcode data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * POST /masters-barcode/save
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'barcode' => 'nullable|string|max:255',
            'generate' => 'nullable|boolean',
        ]);

        $product = ProductMaster::find($validated['id']);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        try {
            if (!empty($validated['generate'])) {
                $result = $this->generateAndSaveFromUpc($product);
                if (!$result['success']) {
                    return response()->json($result, 422);
                }

                return response()->json($result);
            }

            $code = trim((string) ($validated['barcode'] ?? ''));
            if ($code !== '') {
                $normalizedCode = mb_strtolower(preg_replace('/\s+/', '', $code) ?? '');
                $exists = $normalizedCode !== '' && ProductMaster::withTrashed()
                    ->where('id', '!=', $product->id)
                    ->whereNotNull('barcode')
                    ->whereRaw(
                        "LOWER(REPLACE(REPLACE(REPLACE(TRIM(barcode), UNHEX('C2A0'), ''), ' ', ''), '\t', '')) = ?",
                        [$normalizedCode]
                    )
                    ->exists();
                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This barcode is already used by another SKU.',
                    ], 422);
                }
                $result = $this->saveBarcodeImageForCode($product, $code);
                if (!$result['success']) {
                    return response()->json($result, 422);
                }

                return response()->json($result);
            }

            $product->barcode = null;
            $values = $this->productValues($product);
            unset($values['barcode_image']);
            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Barcode cleared.',
                'barcode' => '',
                'barcode_image' => null,
                'upc' => $this->extractUpc($values),
            ]);
        } catch (\Throwable $e) {
            Log::error('MastersBarcode save failed', ['error' => $e->getMessage(), 'id' => $product->id]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save barcode: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /masters-barcode/autogenerate
     * Bulk: create barcode images from each SKU's UPC and save.
     */
    public function autogenerate(Request $request)
    {
        $onlyMissing = (bool) $request->boolean('only_missing', true);
        $ids = $request->input('ids');

        $query = ProductMaster::query()->select(['id', 'sku', 'barcode', 'Values']);
        if (is_array($ids) && count($ids)) {
            $query->whereIn('id', $ids);
        }

        $generated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($query->cursor() as $product) {
            $values = $this->productValues($product);
            $upc = $this->extractUpc($values);
            $hasImage = !empty($values['barcode_image']);
            $hasBarcode = trim((string) ($product->barcode ?? '')) !== '';

            if ($upc === '') {
                $skipped++;
                continue;
            }
            if ($onlyMissing && $hasImage && $hasBarcode) {
                $skipped++;
                continue;
            }

            $result = $this->generateAndSaveFromUpc($product);
            if ($result['success']) {
                $generated++;
            } else {
                $failed++;
                if (count($errors) < 10) {
                    $errors[] = trim((string) $product->sku).': '.($result['message'] ?? 'failed');
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Generated {$generated} barcode(s). Skipped {$skipped}. Failed {$failed}.",
            'generated' => $generated,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
        ]);
    }

    /**
     * Use SKU UPC as barcode value, generate square PNG, save path + barcode column.
     */
    private function generateAndSaveFromUpc(ProductMaster $product): array
    {
        $values = $this->productValues($product);
        $upc = $this->extractUpc($values);
        if ($upc === '') {
            return [
                'success' => false,
                'message' => 'No UPC found for this SKU in CP Master.',
            ];
        }

        return $this->saveBarcodeImageForCode($product, $upc);
    }

    private function saveBarcodeImageForCode(ProductMaster $product, string $code): array
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if ($code === '' || $code === '-') {
            return ['success' => false, 'message' => 'Invalid barcode/UPC.'];
        }

        $png = $this->buildSquareBarcodePng($code);
        if ($png === null) {
            return ['success' => false, 'message' => 'Could not generate barcode image for this UPC.'];
        }

        $safeSku = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string) $product->sku)) ?: ('id_'.$product->id);
        $relative = 'barcodes/'.$product->id.'_'.$safeSku.'.png';
        Storage::disk('public')->put($relative, $png);

        $publicPath = 'storage/'.$relative;

        $product->barcode = $code;
        $values = $this->productValues($product);
        $values['barcode_image'] = $publicPath;
        // Keep UPC in Values if missing but code looks like UPC
        if ($this->extractUpc($values) === '' && $this->isUpcLike($code)) {
            $values['upc'] = $code;
        }
        $product->Values = $values;
        $product->save();

        return [
            'success' => true,
            'message' => 'Barcode image generated and saved.',
            'barcode' => $code,
            'upc' => $this->extractUpc($values),
            'barcode_image' => '/'.ltrim($publicPath, '/'),
        ];
    }

    private function buildSquareBarcodePng(string $code): ?string
    {
        try {
            $generator = new BarcodeGeneratorPNG();
            $type = $this->isUpcLike($code)
                ? $generator::TYPE_UPC_A
                : $generator::TYPE_CODE_128;

            // UPC-A expects 11 digits (check digit optional) or 12 with check digit.
            $payload = $code;
            if ($type === $generator::TYPE_UPC_A) {
                $digits = preg_replace('/\D/', '', $code) ?? '';
                if (strlen($digits) === 12) {
                    $payload = substr($digits, 0, 11);
                } elseif (strlen($digits) === 11) {
                    $payload = $digits;
                } else {
                    $type = $generator::TYPE_CODE_128;
                    $payload = $code;
                }
            }

            $barcodeBinary = $generator->getBarcode($payload, $type, 2, 70);
            $src = @imagecreatefromstring($barcodeBinary);
            if (!$src) {
                return null;
            }

            $srcW = imagesx($src);
            $srcH = imagesy($src);
            $size = max(180, max($srcW, $srcH) + 40);
            $dst = imagecreatetruecolor($size, $size);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);

            $dstX = (int) (($size - $srcW) / 2);
            $dstY = (int) (($size - $srcH) / 2) - 8;
            imagecopy($dst, $src, $dstX, $dstY, 0, 0, $srcW, $srcH);

            // Print human-readable code under bars
            $black = imagecolorallocate($dst, 17, 24, 39);
            $label = $code;
            $font = 3;
            $textW = imagefontwidth($font) * strlen($label);
            $textX = (int) (($size - $textW) / 2);
            $textY = min($size - 18, $dstY + $srcH + 6);
            imagestring($dst, $font, $textX, $textY, $label, $black);

            ob_start();
            imagepng($dst);
            $out = ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);

            return $out ?: null;
        } catch (\Throwable $e) {
            Log::warning('Barcode PNG build failed', ['code' => $code, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function productValues(ProductMaster $product): array
    {
        $values = $product->Values;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($values)) {
            $values = [];
        }

        return $values;
    }

    private function extractUpc(array $values): string
    {
        foreach (['upc', 'UPC', 'gtin', 'ean', 'EAN'] as $key) {
            $raw = trim((string) ($values[$key] ?? ''));
            if ($raw === '' || $raw === '-') {
                continue;
            }
            // CP Master sometimes stores UPC as float-like number
            if (is_numeric($raw)) {
                $raw = preg_replace('/\D/', '', (string) $raw) ?? '';
            }
            $raw = preg_replace('/\s+/', '', $raw) ?? '';
            if ($raw !== '') {
                return $raw;
            }
        }

        return '';
    }

    private function isUpcLike(string $code): bool
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';

        return strlen($digits) === 11 || strlen($digits) === 12;
    }

    private function resolveProductImage(ProductMaster $product, ?array $values = null): ?string
    {
        $candidates = [];
        $values = $values ?? $this->productValues($product);

        foreach (['image_path', 'image', 'Image', 'main_image'] as $key) {
            if (!empty($values[$key])) {
                $candidates[] = $values[$key];
                break;
            }
        }

        if (!empty($product->main_image)) {
            $candidates[] = $product->main_image;
        }
        if (!empty($product->image1)) {
            $candidates[] = $product->image1;
        }

        foreach ($candidates as $candidate) {
            $url = $this->normalizeImageUrl($candidate);
            if ($url !== null) {
                return $url;
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

        return '/' . ltrim($p, '/');
    }
}
