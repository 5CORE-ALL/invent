<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use App\Models\ProductMaster;
use App\Models\ProductRawImage;
use App\Models\ProductRawImageAiPrompt;
use App\Models\ShopifySku;
use App\Services\RawImagesAiImageService;
use App\Support\Badges\RawImagesBadgeCalculator;
use App\Support\Badges\RawImagesBatchCooBadgeCalculator;
use App\Support\Badges\RawImagesHero2BadgeCalculator;
use App\Support\OpenAiRequest;
use App\Support\VideoThumbnailUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class RawImagesController extends Controller
{
    private const SIDEBAR_CACHE_PREFIX = 'raw_images_missing_sidebar_count';

    private const MAX_FILES = 20;

    private const DEFAULT_AI_PROMPT = "Make a raw shoot image background for the image in Hero image column and paste it in raw image column.\nThe size should be  2000x2000px.\nmake it realistic and Natural so that AI can not Detect.\nif product is dark then use light Background or vice-versa.";

    private const DEFAULT_HERO_2_AI_PROMPT = "Make a hero image 2 from the image in the Hero image column and paste it in the Hero Image 2 column.\nThe size should be  2000x2000px.\nmake it realistic and Natural so that AI can not Detect.\nif product is dark then use light Background or vice-versa.";

    private const DEFAULT_PKG_AI_PROMPT = "Make a raw packaging photo from the Hero image and put it in the Pkg Raw column.\nThe size should be 2000x2000px.\nShow the product as a realistic packaged / item-pkg raw shoot, natural lighting, no text, no watermark.\nIf the product is dark then use a light background or vice-versa.";

    public function index(Request $request): View
    {
        return view('raw-images', $this->pageConfig($this->kindFromRequest($request)));
    }

    public function getData(Request $request): JsonResponse
    {
        $kind = $this->kindFromRequest($request);

        $baseResponse = app(PMController::class)->getViewProductData($request);
        $baseData = $baseResponse->getData(true);
        $products = $baseData['data'] ?? [];

        $rawBySku = ProductRawImage::query()
            ->whereIn('kind', [$kind, ProductRawImage::aiKindFor($kind), ProductRawImage::KIND_PKG, ProductRawImage::KIND_PKG_AI])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (ProductRawImage $img) => $this->normalizeSku($img->sku));

        $hasBarcodeColumn = Schema::hasColumn('product_master', 'barcode');

        $pmExtra = [];
        if ($hasBarcodeColumn) {
            $pmExtra = ProductMaster::query()
                ->get(['sku', 'barcode'])
                ->keyBy(fn (ProductMaster $p) => $this->normalizeSku($p->sku));
        }

        $result = [];
        foreach ($products as $row) {
            if (! is_array($row)) {
                $row = (array) $row;
            }

            $normalizedSku = $this->normalizeSku($row['SKU'] ?? null);
            $inv = (float) ($row['shopify_inv'] ?? 0);
            $ovl30 = (float) ($row['shopify_quantity'] ?? 0);
            $row['ovl30'] = $ovl30;
            $row['dil'] = ($inv > 0) ? round(($ovl30 / $inv) * 100, 2) : 0;
            $row['image_path'] = $this->normalizePublicImageUrl($row['image_path'] ?? null);

            $heroImages = $this->collectHeroImagesFromRow($row);
            $hero = $heroImages[0] ?? null;
            $row['hero_image'] = $hero;
            $row['hero_thumb'] = $this->localCachedThumbUrl($hero) ?: $hero;
            $row['ebay_hero_images'] = array_map(function (string $url) {
                $thumb = $this->localCachedThumbUrl($url) ?: $url;

                return ['url' => $url, 'thumb_url' => $thumb];
            }, $heroImages);
            $row['ebay_hero_image'] = $hero;
            $row['ebay_hero_thumb'] = $row['hero_thumb'];
            $row['ebay_hero_image_count'] = count($heroImages);

            $extra = $pmExtra[$normalizedSku] ?? null;

            $images = $rawBySku[$normalizedSku] ?? collect();
            $pkgImages = $images->filter(fn (ProductRawImage $img) => in_array((string) $img->kind, [ProductRawImage::KIND_PKG, ProductRawImage::KIND_PKG_AI], true))->values();
            $pageImages = $images->reject(fn (ProductRawImage $img) => in_array((string) $img->kind, [ProductRawImage::KIND_PKG, ProductRawImage::KIND_PKG_AI], true))->values();
            $aiImages = $pageImages->filter(fn (ProductRawImage $img) => $img->isAiGenerated())->values();
            $manualImages = $pageImages->reject(fn (ProductRawImage $img) => $img->isAiGenerated())->values();
            $pkgAiImages = $pkgImages->filter(fn (ProductRawImage $img) => $img->isAiGenerated() || (string) $img->kind === ProductRawImage::KIND_PKG_AI)->values();
            $pkgManualImages = $pkgImages->reject(fn (ProductRawImage $img) => $img->isAiGenerated() || (string) $img->kind === ProductRawImage::KIND_PKG_AI)->values();
            $row['raw_images'] = $manualImages->map(fn (ProductRawImage $img) => $img->toUiArray())->values()->all();
            $row['raw_image_count'] = $manualImages->count();
            $row['has_raw_image'] = $manualImages->isNotEmpty();
            $row['raw_image_url'] = $manualImages->first()?->url;
            $row['raw_ai_images'] = $aiImages->map(fn (ProductRawImage $img) => $img->toUiArray())->values()->all();
            $row['raw_ai_image_count'] = $aiImages->count();
            $row['has_raw_ai_image'] = $aiImages->isNotEmpty();
            $row['raw_ai_image_url'] = $aiImages->first()?->url;
            $row['pkg_raw_images'] = $pkgManualImages->map(fn (ProductRawImage $img) => $img->toUiArray())->values()->all();
            $row['pkg_raw_image_count'] = $pkgManualImages->count();
            $row['has_pkg_raw_image'] = $pkgManualImages->isNotEmpty();
            $row['pkg_raw_image_url'] = $pkgManualImages->first()?->url;
            $row['pkg_ai_images'] = $pkgAiImages->map(fn (ProductRawImage $img) => $img->toUiArray())->values()->all();
            $row['pkg_ai_image_count'] = $pkgAiImages->count();
            $row['has_pkg_ai_image'] = $pkgAiImages->isNotEmpty();
            $row['pkg_ai_image_url'] = $pkgAiImages->first()?->url;

            $upc = $this->extractUpcFromValues($row);
            $storedBarcode = $hasBarcodeColumn
                ? trim((string) ($extra?->barcode ?? $row['barcode'] ?? ''))
                : trim((string) ($row['barcode'] ?? ''));
            $row['upc'] = $upc;
            $row['barcode'] = $storedBarcode !== '' ? $storedBarcode : $upc;
            $row['barcode_image'] = $this->normalizePublicImageUrl($row['barcode_image'] ?? null);

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
        $kind = $this->imageKindFromRequest($request);

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
            $this->writeImageThumb($record);

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
        $image = ProductRawImage::query()
            ->whereIn('kind', [$kind, ProductRawImage::aiKindFor($kind), ProductRawImage::KIND_PKG, ProductRawImage::KIND_PKG_AI])
            ->find($id);
        if (! $image) {
            return response()->json(['success' => false, 'message' => 'Raw image not found.'], 404);
        }

        $sku = $this->normalizeSku($image->sku);

        try {
            if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
            $thumb = $image->thumbPath();
            if (Storage::disk('public')->exists($thumb)) {
                Storage::disk('public')->delete($thumb);
            }
        } catch (\Throwable $e) {
            Log::warning('Raw image disk delete failed', [
                'id' => $id,
                'path' => $image->image_path,
                'error' => $e->getMessage(),
            ]);
        }

        $wasAi = $image->isAiGenerated();
        $isPkg = in_array((string) $image->kind, [ProductRawImage::KIND_PKG, ProductRawImage::KIND_PKG_AI], true);
        $imageKind = $isPkg ? ProductRawImage::KIND_PKG : $kind;
        $image->delete();
        self::forgetMissingSidebarCountCache($kind);

        return response()->json([
            'success' => true,
            'message' => 'Raw image removed.',
            'source' => $isPkg ? ($wasAi ? 'pkg_ai' : 'pkg') : ($wasAi ? 'ai' : 'manual'),
            'images' => $this->imagesForSku($sku, $imageKind, $wasAi),
        ]);
    }

    public function bulkImport(Request $request): JsonResponse
    {
        $kind = $this->kindFromRequest($request);
        $source = strtolower(trim((string) $request->input('source', 'sheet')));
        if (! in_array($source, ['sheet', 'dropbox'], true)) {
            $source = 'sheet';
        }

        $request->validate([
            'file' => 'nullable|file|mimes:xlsx,xls,csv,txt|max:10240',
            'sheet_url' => 'nullable|string|max:2000',
            'sheet_tab' => 'nullable|string|max:120',
            'urls' => 'nullable|string|max:200000',
        ]);

        try {
            $pairs = $source === 'dropbox'
                ? $this->pairsFromDropboxRequest($request)
                : $this->pairsFromSheetRequest($request);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($pairs === []) {
            return response()->json([
                'success' => false,
                'message' => $source === 'dropbox'
                    ? 'No SKU + Dropbox/file URLs found. Use "SKU, URL" per line or a sheet with SKU and URL columns.'
                    : 'No SKU + image URL rows found. Use a sheet with SKU and URL columns, or upload a CSV/Excel file.',
            ], 422);
        }

        if (count($pairs) > 200) {
            return response()->json([
                'success' => false,
                'message' => 'Limit is 200 image URLs per bulk update. Split the sheet and try again.',
            ], 422);
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $touchedSkus = [];

        foreach ($pairs as $index => $pair) {
            $rowLabel = 'Row '.($index + 1);
            $sku = $this->resolveImportSku($pair['sku'] ?? '', $pair['url'] ?? '');
            if ($sku === null) {
                $skipped++;
                $errors[] = $rowLabel.': SKU not found'.($pair['sku'] !== '' ? ' ('.$pair['sku'].')' : '').'.';
                continue;
            }

            try {
                $this->importRemoteImage($sku, $kind, $pair['url']);
                $imported++;
                $touchedSkus[$sku] = true;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = $rowLabel.' ('.$sku.'): '.$e->getMessage();
            }
        }

        if ($imported > 0) {
            self::forgetMissingSidebarCountCache($kind);
        }

        $bySku = [];
        foreach (array_keys($touchedSkus) as $sku) {
            $bySku[$sku] = $this->imagesForSku($sku, $kind);
        }

        return response()->json([
            'success' => $imported > 0,
            'message' => $imported > 0
                ? $imported.' image'.($imported === 1 ? '' : 's').' imported.'
                : 'No images were imported.',
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 40),
            'by_sku' => $bySku,
        ], $imported > 0 ? 200 : 422);
    }

    public function downloadSelected(Request $request): StreamedResponse|JsonResponse
    {
        $kind = $this->kindFromRequest($request);
        $skus = $request->input('skus', []);
        if (! is_array($skus) || $skus === []) {
            return response()->json(['success' => false, 'message' => 'Select at least one SKU.'], 422);
        }

        $normalized = [];
        foreach ($skus as $sku) {
            $norm = $this->normalizeSku(is_string($sku) ? $sku : (string) $sku);
            if ($norm !== '') {
                $normalized[$norm] = true;
            }
        }
        $skuList = array_keys($normalized);
        if ($skuList === []) {
            return response()->json(['success' => false, 'message' => 'Select at least one SKU.'], 422);
        }

        $images = ProductRawImage::query()
            ->whereIn('kind', [$kind, ProductRawImage::aiKindFor($kind)])
            ->whereIn('sku', $skuList)
            ->orderBy('sku')
            ->orderBy('id')
            ->get();

        if ($images->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No raw images found for the selected SKUs.'], 404);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ri_zip_');
        if ($tmp === false) {
            return response()->json(['success' => false, 'message' => 'Could not create a temp file for the zip.'], 500);
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);

            return response()->json(['success' => false, 'message' => 'Could not create zip archive.'], 500);
        }

        $added = 0;
        $usedNames = [];
        foreach ($images as $image) {
            if (! $image->image_path || ! Storage::disk('public')->exists($image->image_path)) {
                continue;
            }
            $folder = preg_replace('/[^a-zA-Z0-9_\- ]/', '_', $image->sku) ?: 'sku';
            $base = $image->original_name ?: basename((string) $image->image_path);
            $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?: 'image';
            $entry = $folder.'/'.$safeBase;
            $n = 1;
            while (isset($usedNames[$entry])) {
                $entry = $folder.'/'.$n.'_'.$safeBase;
                $n++;
            }
            $usedNames[$entry] = true;
            $zip->addFromString($entry, Storage::disk('public')->get($image->image_path));
            $added++;
        }
        $zip->close();

        if ($added === 0) {
            @unlink($tmp);

            return response()->json(['success' => false, 'message' => 'Image files were missing on disk.'], 404);
        }

        $fileName = $this->zipFilePrefix($kind).'-'.date('Y-m-d-His').'.zip';

        return response()->streamDownload(function () use ($tmp) {
            readfile($tmp);
            @unlink($tmp);
        }, $fileName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function downloadTemplate(): \Illuminate\Http\Response
    {
        $csv = "SKU,URL\nEXAMPLE-SKU,https://www.dropbox.com/s/example/image.jpg?dl=0\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="raw-images-bulk-template.csv"',
        ]);
    }

    public function aiPrompt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|min:2|max:8000',
        ]);

        $prompt = trim($validated['prompt']);
        $kind = $this->imageKindFromRequest($request);
        $rows = $this->selectedAiRows($request);
        if ($rows === []) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least one SKU in the table. Run only works on selected rows.',
            ], 422);
        }
        if (count($rows) > 8) {
            return response()->json([
                'success' => false,
                'message' => 'Select up to 8 SKUs at a time.',
            ], 422);
        }

        @set_time_limit(600);
        $this->persistAiPrompt($kind, $prompt);
        $result = $this->generateRawImagesForSelected($rows, $kind, $prompt);

        if ($result['imported'] > 0) {
            self::forgetMissingSidebarCountCache($kind);
        }

        return response()->json([
            'success' => $result['imported'] > 0,
            'reply' => $result['reply'],
            'message' => $result['reply'],
            'action' => ['type' => 'refresh_images'],
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            'by_sku' => $result['by_sku'],
        ], $result['imported'] > 0 ? 200 : 422);
    }

    public function saveAiPrompt(Request $request): JsonResponse
    {
        $prompt = trim((string) $request->input('prompt', ''));
        if (strlen($prompt) > 8000) {
            $prompt = substr($prompt, 0, 8000);
        }

        if ($prompt !== '') {
            $this->persistAiPrompt($this->imageKindFromRequest($request), $prompt);
        }

        return response()->json([
            'success' => true,
            'prompt' => $prompt,
        ]);
    }

    public function cachedImage(Request $request)
    {
        $url = trim((string) $request->query('u', ''));
        if ($url === '') {
            abort(404);
        }

        try {
            $rel = $this->ensureRemoteImageCached($url);
        } catch (\Throwable $e) {
            abort(404);
        }

        $abs = Storage::disk('public')->path($rel);
        if (! is_file($abs)) {
            abort(404);
        }

        return response()->file($abs, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function savedAiPrompt(string $kind): string
    {
        try {
            if (Schema::hasTable('product_raw_image_ai_prompts')) {
                $row = ProductRawImageAiPrompt::query()->where('kind', $kind)->first();
                $prompt = trim((string) ($row?->prompt ?? ''));
                if ($prompt !== '') {
                    return $prompt;
                }
            }
        } catch (\Throwable $e) {
            //
        }

        return match ($kind) {
            ProductRawImage::KIND_PKG => self::DEFAULT_PKG_AI_PROMPT,
            ProductRawImage::KIND_HERO_2 => self::DEFAULT_HERO_2_AI_PROMPT,
            default => self::DEFAULT_AI_PROMPT,
        };
    }

    private function persistAiPrompt(string $kind, string $prompt): void
    {
        $prompt = trim($prompt);
        $kind = trim($kind);
        if ($prompt === '' || $kind === '' || ! Schema::hasTable('product_raw_image_ai_prompts')) {
            return;
        }

        try {
            ProductRawImageAiPrompt::query()->updateOrCreate(
                ['kind' => $kind],
                ['prompt' => $prompt]
            );
        } catch (\Throwable $e) {
            Log::warning('Raw images AI prompt persist failed', [
                'kind' => $kind,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<array{sku: string, hero_image: ?string}>
     */
    private function selectedAiRows(Request $request): array
    {
        $rows = [];
        $selected = $request->input('selected', $request->input('selected_skus', []));
        if (! is_array($selected)) {
            return [];
        }

        foreach ($selected as $item) {
            if (is_string($item)) {
                $sku = $this->normalizeSku($item);
                if ($sku !== '') {
                    $rows[] = ['sku' => $sku, 'hero_image' => null];
                }
                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            $sku = $this->normalizeSku((string) ($item['sku'] ?? $item['SKU'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $hero = $this->normalizePublicImageUrl($item['hero_image'] ?? $item['image_path'] ?? null);
            $rows[] = ['sku' => $sku, 'hero_image' => $hero];
        }

        return $rows;
    }

    /**
     * @param  list<array{sku: string, hero_image: ?string}>  $rows
     * @return array{reply: string, imported: int, skipped: int, errors: list<string>, by_sku: array<string, list<array<string, mixed>>>}
     */
    private function generateRawImagesForSelected(array $rows, string $kind, string $prompt): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $bySku = [];

        foreach ($rows as $row) {
            $sku = $row['sku'];
            try {
                $heroUrl = $row['hero_image'] ?: $this->heroUrlForSku($sku);
                if (! $heroUrl) {
                    throw new \RuntimeException('No hero image found.');
                }
                $bytes = $this->generateRawShootFromHero($heroUrl, $prompt, $sku);
                $this->storeRawImageBytes($sku, ProductRawImage::aiKindFor($kind), $bytes, $sku.'_ai_raw_2000.jpg');
                $imported++;
                $bySku[$sku] = $this->imagesForSku($sku, $kind, true);
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = $sku.': '.$e->getMessage();
                Log::warning('Raw images AI generate failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $reply = $imported > 0
            ? 'Created raw images for '.$imported.' selected SKU'.($imported === 1 ? '' : 's').'.'
            : 'No raw images were created.';
        if ($errors !== []) {
            $reply .= ' '.count($errors).' skipped.';
        }

        return [
            'reply' => $reply,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 20),
            'by_sku' => $bySku,
        ];
    }

    private function heroUrlForSku(string $sku): ?string
    {
        $product = ProductMaster::query()->where('sku', $sku)->first();
        if (! $product) {
            return null;
        }
        $values = is_array($product->Values) ? $product->Values : [];
        $shopify = ShopifySku::query()->where('sku', $sku)->value('image_src');

        return $this->resolveHeroImageUrl(
            $product,
            $values,
            is_string($shopify) ? $shopify : null,
            $values['image_path'] ?? null
        );
    }

    private function generateRawShootFromHero(string $heroUrl, string $prompt, string $sku): string
    {
        $heroBytes = $this->downloadImageBytes($heroUrl);
        $this->storeRemoteImageCacheFromBytes($heroUrl, $heroBytes);

        return app(RawImagesAiImageService::class)->generateFromHeroBytes($heroBytes, $prompt, $sku);
    }

    private function downloadImageBytes(string $url): string
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $rel = preg_replace('#^/storage/#', '', $url) ?? $url;
            if (Storage::disk('public')->exists($rel)) {
                $bytes = Storage::disk('public')->get($rel);
                if (is_string($bytes) && $bytes !== '') {
                    return $bytes;
                }
            }
            $abs = public_path(ltrim($url, '/'));
            if (is_file($abs)) {
                $bytes = (string) file_get_contents($abs);
                if ($bytes !== '') {
                    return $bytes;
                }
            }
        }

        $this->assertSafeRemoteUrl($url);
        $response = Http::timeout(60)
            ->withHeaders(['User-Agent' => 'Invent-RawImages/1.0'])
            ->get($url);
        if (! $response->successful() || strlen($response->body()) < 32) {
            throw new \RuntimeException('Could not download the hero image.');
        }

        return $response->body();
    }

    private function resizeToSquareJpeg(string $bytes, int $size = 2000): string
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return $bytes;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $dst = imagecreatetruecolor($size, $size);
        $bg = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $size, $size, $bg);
        $scale = min($size / max($w, 1), $size / max($h, 1));
        $nw = (int) max(1, round($w * $scale));
        $nh = (int) max(1, round($h * $scale));
        $x = (int) (($size - $nw) / 2);
        $y = (int) (($size - $nh) / 2);
        imagecopyresampled($dst, $src, $x, $y, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($dst, null, 92);
        $out = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return $out !== '' ? $out : $bytes;
    }

    private function storeRawImageBytes(string $sku, string $kind, string $bytes, string $originalName): ProductRawImage
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ri_ai_');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create a temp file.');
        }
        file_put_contents($tmp, $bytes);

        try {
            $safeSku = preg_replace('/[^a-zA-Z0-9_\- ]/', '_', $sku) ?: 'sku';
            $folder = 'raw-images/'.$kind.'/'.$safeSku;
            $uniqueName = 'ai_raw_'.uniqid().'.jpg';
            $stored = Storage::disk('public')->putFileAs($folder, new \Illuminate\Http\File($tmp), $uniqueName);
            if (! $stored) {
                throw new \RuntimeException('Could not store the generated image.');
            }

            $record = ProductRawImage::create([
                'sku' => $sku,
                'kind' => $kind,
                'image_path' => $stored,
                'original_name' => $originalName,
                'file_size' => strlen($bytes),
                'mime_type' => 'image/jpeg',
            ]);
            $this->writeImageThumb($record);

            return $record;
        } finally {
            @unlink($tmp);
        }
    }

    private function cachedDisplayUrl(?string $url): ?string
    {
        return $this->localCachedThumbUrl($url) ?: $this->normalizePublicImageUrl($url);
    }

    private function localCachedThumbUrl(?string $url): ?string
    {
        $url = $this->normalizePublicImageUrl($url);
        if (! $url) {
            return null;
        }

        if (str_contains($url, '/storage/image-cache/')) {
            return $url;
        }

        $rel = $this->remoteCachePath($url);
        if (! Storage::disk('public')->exists($rel)) {
            return null;
        }

        return '/storage/'.$rel;
    }

    private function ensureRemoteImageCached(string $url): string
    {
        $url = $this->normalizePublicImageUrl($url) ?? $url;
        $rel = $this->remoteCachePath($url);
        if (Storage::disk('public')->exists($rel)) {
            return $rel;
        }

        $bytes = $this->downloadImageBytes($url);
        $this->storeRemoteImageCacheFromBytes($url, $bytes);

        return $rel;
    }

    private function storeRemoteImageCacheFromBytes(string $url, string $bytes): void
    {
        $url = $this->normalizePublicImageUrl($url) ?? $url;
        $rel = $this->remoteCachePath($url);
        if (Storage::disk('public')->exists($rel) || $bytes === '') {
            return;
        }
        try {
            Storage::disk('public')->put($rel, $this->resizeToSquareJpeg($bytes, 240));
            $this->ensureImageCacheHtaccess();
        } catch (\Throwable $e) {
            //
        }
    }

    private function remoteCachePath(string $url): string
    {
        return 'image-cache/remote/'.sha1($url).'.jpg';
    }

    private function writeImageThumb(ProductRawImage $image): void
    {
        try {
            if (! $image->isPreviewable() || ! $image->image_path) {
                return;
            }
            if (! Storage::disk('public')->exists($image->image_path)) {
                return;
            }
            $bytes = Storage::disk('public')->get($image->image_path);
            if (! is_string($bytes) || $bytes === '') {
                return;
            }
            Storage::disk('public')->put($image->thumbPath(), $this->resizeToSquareJpeg($bytes, 240));
            $this->ensureImageCacheHtaccess();
        } catch (\Throwable $e) {
            Log::warning('Raw image thumb cache failed', [
                'id' => $image->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function ensureImageCacheHtaccess(): void
    {
        $path = Storage::disk('public')->path('image-cache/.htaccess');
        if (is_file($path)) {
            return;
        }
        @mkdir(dirname($path), 0755, true);
        @file_put_contents($path, "<IfModule mod_headers.c>\n    Header set Cache-Control \"public, max-age=31536000, immutable\"\n</IfModule>\n");
    }

    public static function missingCountForSidebar(string $kind = ProductRawImage::KIND_RAW): int
    {
        try {
            return (int) Cache::remember(self::sidebarCacheKey($kind), 300, function () use ($kind) {
                $calculator = match ($kind) {
                    ProductRawImage::KIND_BATCH_COO => RawImagesBatchCooBadgeCalculator::class,
                    ProductRawImage::KIND_HERO_2 => RawImagesHero2BadgeCalculator::class,
                    default => RawImagesBadgeCalculator::class,
                };

                return (int) ($calculator::calculate()['missing'] ?? 0);
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
        Cache::forget(self::sidebarCacheKey(ProductRawImage::KIND_HERO_2));
    }

    /**
     * @return array<string, string>
     */
    private function pageConfig(string $kind): array
    {
        $labels = $this->pageLabels($kind);

        if ($kind === ProductRawImage::KIND_HERO_2) {
            return array_merge($labels, [
                'kind' => $kind,
                'dataUrl' => route('raw.images.hero.2.data'),
                'uploadUrl' => route('raw.images.hero.2.upload'),
                'destroyBaseUrl' => url('/raw-images-hero-2'),
                'bulkImportUrl' => route('raw.images.hero.2.bulk.import'),
                'downloadUrl' => route('raw.images.hero.2.download'),
                'templateUrl' => route('raw.images.hero.2.template'),
                'aiPromptUrl' => route('raw.images.hero.2.ai.prompt'),
                'aiPromptSaveUrl' => route('raw.images.hero.2.ai.prompt.save'),
                'cachedImageUrl' => route('raw.images.cached.image'),
                'savedAiPrompt' => $this->savedAiPrompt($kind),
                'savedAiPkgPrompt' => $this->savedAiPrompt(ProductRawImage::KIND_PKG),
            ]);
        }

        if ($kind === ProductRawImage::KIND_BATCH_COO) {
            return array_merge($labels, [
                'kind' => $kind,
                'dataUrl' => route('raw.images.batch.coo.data'),
                'uploadUrl' => route('raw.images.batch.coo.upload'),
                'destroyBaseUrl' => url('/raw-images-batch-coo'),
                'bulkImportUrl' => route('raw.images.batch.coo.bulk.import'),
                'downloadUrl' => route('raw.images.batch.coo.download'),
                'templateUrl' => route('raw.images.batch.coo.template'),
                'aiPromptUrl' => route('raw.images.batch.coo.ai.prompt'),
                'aiPromptSaveUrl' => route('raw.images.batch.coo.ai.prompt.save'),
                'cachedImageUrl' => route('raw.images.cached.image'),
                'savedAiPrompt' => $this->savedAiPrompt($kind),
                'savedAiPkgPrompt' => $this->savedAiPrompt(ProductRawImage::KIND_PKG),
            ]);
        }

        return array_merge($labels, [
            'kind' => ProductRawImage::KIND_RAW,
            'dataUrl' => route('raw.images.data'),
            'uploadUrl' => route('raw.images.upload'),
            'destroyBaseUrl' => url('/raw-images'),
            'bulkImportUrl' => route('raw.images.bulk.import'),
            'downloadUrl' => route('raw.images.download'),
            'templateUrl' => route('raw.images.template'),
            'aiPromptUrl' => route('raw.images.ai.prompt'),
            'aiPromptSaveUrl' => route('raw.images.ai.prompt.save'),
            'cachedImageUrl' => route('raw.images.cached.image'),
            'savedAiPrompt' => $this->savedAiPrompt($kind),
            'savedAiPkgPrompt' => $this->savedAiPrompt(ProductRawImage::KIND_PKG),
        ]);
    }

    /**
     * @return array{pageTitle: string, pageSubtitle: string, manualColumnTitle: string, aiColumnTitle: string, missingBadgeLabel: string, zipFileName: string}
     */
    private function pageLabels(string $kind): array
    {
        if ($kind === ProductRawImage::KIND_HERO_2) {
            return [
                'pageTitle' => 'Hero Image 2',
                'pageSubtitle' => 'Upload hero image 2 files by SKU',
                'manualColumnTitle' => 'Hero Image 2',
                'aiColumnTitle' => 'Hero Image 2 AI',
                'missingBadgeLabel' => 'Missing Hero Image 2',
                'zipFileName' => 'hero-image-2.zip',
            ];
        }

        if ($kind === ProductRawImage::KIND_BATCH_COO) {
            return [
                'pageTitle' => 'Raw Images (Batch +COO)',
                'pageSubtitle' => 'Upload batch and COO raw image files by SKU',
                'manualColumnTitle' => 'Raw Images',
                'aiColumnTitle' => 'Raw Images AI',
                'missingBadgeLabel' => 'Missing Raw Images',
                'zipFileName' => 'raw-images.zip',
            ];
        }

        return [
            'pageTitle' => 'Raw Images',
            'pageSubtitle' => 'Upload original raw image files by SKU',
            'manualColumnTitle' => 'Raw Images',
            'aiColumnTitle' => 'Raw Images AI',
            'missingBadgeLabel' => 'Missing Raw Images',
            'zipFileName' => 'raw-images.zip',
        ];
    }

    private function kindFromRequest(Request $request): string
    {
        $name = (string) ($request->route()?->getName() ?? '');
        if (str_contains($name, 'hero.2')) {
            return ProductRawImage::KIND_HERO_2;
        }
        if (str_contains($name, 'batch.coo')) {
            return ProductRawImage::KIND_BATCH_COO;
        }

        $kind = (string) $request->input('kind', '');
        if (in_array($kind, [ProductRawImage::KIND_RAW, ProductRawImage::KIND_BATCH_COO, ProductRawImage::KIND_HERO_2], true)) {
            return $kind;
        }

        return ProductRawImage::KIND_RAW;
    }

    private function zipFilePrefix(string $kind): string
    {
        return match ($kind) {
            ProductRawImage::KIND_BATCH_COO => 'raw-images-batch-coo',
            ProductRawImage::KIND_HERO_2 => 'hero-image-2',
            default => 'raw-images',
        };
    }

    private function pageShortName(string $kind): string
    {
        return match ($kind) {
            ProductRawImage::KIND_BATCH_COO => 'Batch +COO raw images',
            ProductRawImage::KIND_HERO_2 => 'hero image 2',
            default => 'raw images',
        };
    }

    private function imageKindFromRequest(Request $request): string
    {
        $source = strtolower(trim((string) $request->input('image_kind', $request->input('source', ''))));
        if (in_array($source, ['pkg', 'pkg_raw', 'package'], true)) {
            return ProductRawImage::KIND_PKG;
        }

        return $this->kindFromRequest($request);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function imagesForSku(string $sku, string $kind, bool $aiOnly = false): array
    {
        $pageKind = ProductRawImage::pageKindFor($kind);
        $kinds = [$pageKind, ProductRawImage::aiKindFor($pageKind)];

        return ProductRawImage::query()
            ->where('sku', $sku)
            ->whereIn('kind', $kinds)
            ->orderBy('id')
            ->get()
            ->filter(fn (ProductRawImage $img) => $aiOnly ? $img->isAiGenerated() : ! $img->isAiGenerated())
            ->values()
            ->map(function (ProductRawImage $img) {
                if ($img->isPreviewable() && ! $img->thumbUrl()) {
                    $this->writeImageThumb($img);
                }

                return $img->toUiArray();
            })
            ->all();
    }

    private function normalizeSku(?string $sku): string
    {
        return trim(str_replace("\u{00a0}", ' ', (string) $sku));
    }

    /**
     * Every hero image on this row from the CP Master / Raw Images data table
     * (main_image, image1–image20, image_path). Same source as the Hero Image column.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function collectHeroImagesFromRow(array $row): array
    {
        $urls = [];
        $seen = [];
        $add = function (mixed $value) use (&$urls, &$seen): void {
            $url = $this->normalizePublicImageUrl($value);
            if (! $url) {
                return;
            }
            $key = strtolower($url);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $urls[] = $url;
        };

        $add($row['main_image'] ?? null);
        for ($i = 1; $i <= 20; $i++) {
            $add($row['image'.$i] ?? null);
        }
        $add($row['image_url'] ?? null);
        $add($row['image_path'] ?? null);

        return $urls;
    }

    /**
     * Same source as Listing Manager / Image Master / CP Master: main_image, image1+, image_path.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolveHeroImageFromRow(array $row): ?string
    {
        $images = $this->collectHeroImagesFromRow($row);

        return $images[0] ?? null;
    }

    /**
     * Same source as Listing Manager / Image Master: main_image, image1+, Values.image_path, Shopify.
     */
    private function resolveHeroImageUrl(ProductMaster $product, array $row, ?string $shopifyImage, ?string $localImage): ?string
    {
        $merged = $row;
        foreach (['main_image', 'image_url', 'image1', 'image2', 'image3', 'image4', 'image5', 'image6'] as $field) {
            if (empty($merged[$field]) && ! empty($product->{$field})) {
                $merged[$field] = $product->{$field};
            }
        }
        if (empty($merged['image_path'])) {
            $merged['image_path'] = $localImage ?: $shopifyImage;
        }

        return $this->resolveHeroImageFromRow($merged)
            ?: $this->normalizePublicImageUrl($shopifyImage);
    }

    /**
     * First gallery URL stored on eBay 1 (image_urls / image_master_json), keyed by normalized SKU.
     *
     * @return array<string, string>
     */
    private function ebayHeroImageBySku(): array
    {
        try {
            if (! Schema::hasTable('ebay_metrics') || ! Schema::hasColumn('ebay_metrics', 'sku')) {
                return [];
            }

            $select = ['sku'];
            $hasUrls = Schema::hasColumn('ebay_metrics', 'image_urls');
            $hasJson = Schema::hasColumn('ebay_metrics', 'image_master_json');
            if ($hasUrls) {
                $select[] = 'image_urls';
            }
            if ($hasJson) {
                $select[] = 'image_master_json';
            }
            if (count($select) === 1) {
                return [];
            }

            $map = [];
            foreach (DB::table('ebay_metrics')->select($select)->whereNotNull('sku')->get() as $row) {
                $sku = $this->normalizeSku($row->sku ?? null);
                if ($sku === '' || isset($map[$sku])) {
                    continue;
                }
                $url = $this->firstImageUrlFromPayload($hasUrls ? ($row->image_urls ?? null) : null)
                    ?: $this->firstImageUrlFromPayload($hasJson ? ($row->image_master_json ?? null) : null);
                if ($url) {
                    $map[$sku] = $url;
                }
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('Raw images: load eBay hero images failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Image Master eBay main slot on CP Master, then the CP Master Images column.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolveEbayHeroFromRow(array $row, mixed $mainByMarketplaceJson = null): ?string
    {
        $images = [];
        for ($i = 1; $i <= 20; $i++) {
            $url = $this->normalizePublicImageUrl($row['image'.$i] ?? null);
            if ($url) {
                $images[] = $url;
            }
        }
        $main = $this->normalizePublicImageUrl($row['main_image'] ?? null);
        if ($main && ($images === [] || $images[0] !== $main)) {
            array_unshift($images, $main);
        }

        $idx = 0;
        $raw = trim((string) $mainByMarketplaceJson);
        if ($raw !== '' && $raw !== '{}') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['ebay'])) {
                $idx = max(0, (int) $decoded['ebay']);
            }
        }

        return ($images[$idx] ?? $images[0] ?? null)
            ?: $this->normalizePublicImageUrl($row['image_path'] ?? null);
    }

    private function firstImageUrlFromPayload(mixed $payload): ?string
    {
        if (is_array($payload)) {
            foreach ($payload as $item) {
                if (is_array($item)) {
                    $nested = $this->firstImageUrlFromPayload($item['url'] ?? $item['src'] ?? $item['image'] ?? null);
                    if ($nested) {
                        return $nested;
                    }

                    continue;
                }
                $url = $this->normalizePublicImageUrl($item);
                if ($url) {
                    return $url;
                }
            }

            return $this->firstImageUrlFromPayload($payload['urls'] ?? $payload['images'] ?? $payload['url'] ?? null);
        }

        $raw = trim((string) $payload);
        if ($raw === '' || $raw === '[]' || $raw === '{}') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $this->firstImageUrlFromPayload($decoded);
        }

        return $this->normalizePublicImageUrl($raw);
    }

    /**
     * Same UPC fields as /masters-barcode.
     *
     * @param  array<string, mixed>  $values
     */
    private function extractUpcFromValues(array $values): string
    {
        foreach (['upc', 'UPC', 'gtin', 'ean', 'EAN'] as $key) {
            $raw = trim((string) ($values[$key] ?? ''));
            if ($raw === '' || $raw === '-') {
                continue;
            }
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

    private function normalizePublicImageUrl(mixed $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '-') {
            return null;
        }
        if (str_starts_with($v, '//')) {
            return 'https:'.$v;
        }
        if (str_starts_with($v, 'http://') || str_starts_with($v, 'https://')) {
            return $v;
        }

        return '/'.ltrim($v, '/');
    }

    /**
     * @return list<array{sku: string, url: string}>
     */
    private function pairsFromSheetRequest(Request $request): array
    {
        $file = $request->file('file');
        $sheetUrl = trim((string) $request->input('sheet_url', ''));
        $tab = trim((string) $request->input('sheet_tab', '')) ?: 'Sheet1';

        if ($file) {
            return $this->pairsFromGrid($this->rowsFromUploadedSheet($file));
        }

        if ($sheetUrl !== '') {
            return $this->pairsFromGrid($this->rowsFromGoogleSheet($sheetUrl, $tab));
        }

        $pasted = trim((string) $request->input('urls', ''));
        if ($pasted !== '') {
            return $this->pairsFromPastedText($pasted);
        }

        throw new \InvalidArgumentException('Upload a CSV/Excel file or paste a Google Sheet URL.');
    }

    /**
     * @return list<array{sku: string, url: string}>
     */
    private function pairsFromDropboxRequest(Request $request): array
    {
        $pairs = [];
        $file = $request->file('file');
        if ($file) {
            $pairs = array_merge($pairs, $this->pairsFromGrid($this->rowsFromUploadedSheet($file)));
        }

        $sheetUrl = trim((string) $request->input('sheet_url', ''));
        if ($sheetUrl !== '') {
            $pairs = array_merge($pairs, $this->pairsFromGrid(
                $this->rowsFromGoogleSheet($sheetUrl, trim((string) $request->input('sheet_tab', '')) ?: 'Sheet1')
            ));
        }

        $pasted = trim((string) $request->input('urls', ''));
        if ($pasted !== '') {
            $pairs = array_merge($pairs, $this->pairsFromPastedText($pasted));
        }

        if ($pairs === []) {
            throw new \InvalidArgumentException('Paste Dropbox file links (SKU, URL per line) or upload a sheet with those columns.');
        }

        return $pairs;
    }

    /**
     * @return list<array<int, string>>
     */
    private function rowsFromUploadedSheet($file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->parseCsvBody((string) file_get_contents($file->getRealPath()));
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];
        foreach ($sheet->toArray(null, true, true, false) as $row) {
            $rows[] = array_map(static fn ($cell) => trim((string) ($cell ?? '')), is_array($row) ? $row : []);
        }

        return $rows;
    }

    /**
     * @return list<array<int, string>>
     */
    private function rowsFromGoogleSheet(string $url, string $_tab = 'Sheet1'): array
    {
        if (! preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $matches)) {
            throw new \InvalidArgumentException('Invalid Google Sheet URL.');
        }

        $spreadsheetId = $matches[1];
        $gid = null;
        if (preg_match('~[?&#]gid=(\d+)~', $url, $gidMatch)) {
            $gid = $gidMatch[1];
        }

        $params = ['format' => 'csv'];
        if ($gid !== null) {
            $params['gid'] = $gid;
        }

        $csvUrl = 'https://docs.google.com/spreadsheets/d/'.$spreadsheetId.'/export?'.http_build_query($params);
        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'Invent-RawImages/1.0'])
            ->get($csvUrl);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Could not read the Google Sheet. Publish it to the web, or share it so CSV export works.'
            );
        }

        $rows = $this->parseCsvBody($response->body());
        if ($rows === []) {
            throw new \RuntimeException('The Google Sheet is empty.');
        }

        return $rows;
    }

    /**
     * @return list<array<int, string>>
     */
    private function parseCsvBody(string $body): array
    {
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }

        $lines = preg_split("/\r\n|\n|\r/", trim($body)) ?: [];
        $rows = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $rows[] = array_map(static fn ($cell) => trim((string) $cell), str_getcsv($line, ',', '"', '\\'));
        }

        return $rows;
    }

    /**
     * @param  list<array<int, string>>  $rows
     * @return list<array{sku: string, url: string}>
     */
    private function pairsFromGrid(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $header = array_map(function ($cell) {
            return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', (string) $cell), '_'));
        }, $rows[0]);

        $skuAliases = ['sku', 'sku_code', 'item', 'item_sku'];
        $urlAliases = ['url', 'image', 'image_url', 'imageurl', 'dropbox', 'dropbox_url', 'link', 'raw_image', 'raw_url', 'file', 'file_url'];

        $skuIdx = null;
        $urlIdxs = [];
        foreach ($header as $i => $name) {
            if ($skuIdx === null && in_array($name, $skuAliases, true)) {
                $skuIdx = $i;
            }
            if (in_array($name, $urlAliases, true)) {
                $urlIdxs[] = $i;
            }
        }

        $start = 0;
        if ($skuIdx === null) {
            $first = trim((string) ($rows[0][0] ?? ''));
            if ($this->looksLikeUrl($first) || in_array($header[0] ?? '', ['sku', 'url'], true)) {
                $skuIdx = 0;
                $start = 1;
                if ($urlIdxs === [] && isset($rows[0][1])) {
                    $urlIdxs[] = 1;
                }
            } else {
                $skuIdx = 0;
                $urlIdxs = $urlIdxs ?: range(1, max(1, count($rows[0]) - 1));
            }
        } else {
            $start = 1;
            if ($urlIdxs === []) {
                foreach ($header as $i => $name) {
                    if ($i !== $skuIdx) {
                        $urlIdxs[] = $i;
                    }
                }
            }
        }

        $pairs = [];
        for ($r = $start; $r < count($rows); $r++) {
            $row = $rows[$r];
            $sku = $this->normalizeSku((string) ($row[$skuIdx] ?? ''));
            foreach ($urlIdxs as $ui) {
                $url = trim((string) ($row[$ui] ?? ''));
                if ($url === '' || ! $this->looksLikeUrl($url)) {
                    continue;
                }
                $pairs[] = ['sku' => $sku, 'url' => $url];
            }
            if ($urlIdxs === []) {
                foreach ($row as $i => $cell) {
                    if ($i === $skuIdx) {
                        continue;
                    }
                    $url = trim((string) $cell);
                    if ($this->looksLikeUrl($url)) {
                        $pairs[] = ['sku' => $sku, 'url' => $url];
                    }
                }
            }
        }

        return $pairs;
    }

    /**
     * @return list<array{sku: string, url: string}>
     */
    private function pairsFromPastedText(string $text): array
    {
        $pairs = [];
        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('#^(https?://\S+)\s+(.+)$#i', $line, $m)) {
                $pairs[] = ['sku' => trim($m[2]), 'url' => trim($m[1])];
                continue;
            }

            if (preg_match('#^(.+?)[\s,;|\t]+(https?://\S+)$#i', $line, $m)) {
                $pairs[] = ['sku' => trim($m[1], " \t\"'"), 'url' => trim($m[2])];
                continue;
            }

            if ($this->looksLikeUrl($line)) {
                $pairs[] = ['sku' => '', 'url' => $line];
            }
        }

        return $pairs;
    }

    private function resolveImportSku(string $skuHint, string $url): ?string
    {
        $hint = $this->normalizeSku($skuHint);
        if ($hint !== '') {
            $product = ProductMaster::query()
                ->where('sku', $hint)
                ->orWhere('sku', $skuHint)
                ->first();
            if ($product) {
                return $this->normalizeSku($product->sku);
            }
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $base = pathinfo(urldecode((string) $path), PATHINFO_FILENAME);
        $candidates = array_filter([
            $this->normalizeSku($base),
            $this->normalizeSku(str_replace(['_', '-'], ' ', $base)),
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $product = ProductMaster::query()->where('sku', $candidate)->first();
            if ($product) {
                return $this->normalizeSku($product->sku);
            }
        }

        return null;
    }

    private function importRemoteImage(string $sku, string $kind, string $url): ProductRawImage
    {
        $url = VideoThumbnailUrl::normalize($url);
        $this->assertSafeRemoteUrl($url);

        if (str_contains($url, '/scl/fo/')) {
            throw new \RuntimeException('Dropbox folder links are not supported. Use a file share link.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ri_dl_');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create a temp file.');
        }

        try {
            $response = Http::timeout(90)
                ->sink($tmp)
                ->withHeaders(['User-Agent' => 'Invent-RawImages/1.0'])
                ->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException('Download failed (HTTP '.$response->status().').');
            }

            $size = filesize($tmp);
            if ($size === false || $size < 32) {
                throw new \RuntimeException('Downloaded file is empty.');
            }
            if ($size > 50 * 1024 * 1024) {
                throw new \RuntimeException('File is larger than 50 MB.');
            }

            $head = (string) file_get_contents($tmp, false, null, 0, 200);
            if (stripos($head, '<html') !== false || stripos($head, '<!doctype html') !== false) {
                throw new \RuntimeException('URL returned a web page, not an image. Use a Dropbox file link (dl=0 is converted automatically).');
            }

            $ext = $this->extensionFromUrlOrFile($url, $tmp);
            if (! in_array($ext, $this->allowedExtensions(), true)) {
                throw new \RuntimeException('Unsupported file type (.'.$ext.').');
            }

            $safeSku = preg_replace('/[^a-zA-Z0-9_\- ]/', '_', $sku) ?: 'sku';
            $folder = 'raw-images/'.$kind.'/'.$safeSku;
            $pathName = parse_url($url, PHP_URL_PATH) ?: '';
            $originalName = urldecode(basename((string) $pathName)) ?: ('raw.'.$ext);
            $baseName = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME)) ?: 'raw';
            $uniqueName = $baseName.'_'.uniqid().'.'.$ext;
            $stored = Storage::disk('public')->putFileAs($folder, new \Illuminate\Http\File($tmp), $uniqueName);
            if (! $stored) {
                throw new \RuntimeException('Could not store the downloaded file.');
            }

            $mime = @mime_content_type($tmp) ?: null;

            $record = ProductRawImage::create([
                'sku' => $sku,
                'kind' => $kind,
                'image_path' => $stored,
                'original_name' => $originalName,
                'file_size' => $size,
                'mime_type' => $mime,
            ]);
            $this->writeImageThumb($record);

            return $record;
        } finally {
            @unlink($tmp);
        }
    }

    private function assertSafeRemoteUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \RuntimeException('Only http/https image URLs are allowed.');
        }
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            throw new \RuntimeException('Local URLs are not allowed.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            if (! filter_var($host, FILTER_VALIDATE_IP, $flags)) {
                throw new \RuntimeException('Private or reserved IP URLs are not allowed.');
            }
        }
    }

    private function extensionFromUrlOrFile(string $url, string $tmpPath): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, $this->allowedExtensions(), true)) {
            return $ext;
        }

        $mime = @mime_content_type($tmpPath) ?: '';
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tif',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
        ];

        return $map[$mime] ?? ($ext !== '' ? $ext : 'jpg');
    }

    /**
     * @return list<string>
     */
    private function allowedExtensions(): array
    {
        return [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic', 'heif',
            'dng', 'cr2', 'cr3', 'nef', 'arw', 'raf', 'orf', 'rw2',
        ];
    }

    /**
     * @param  list<string>  $selectedSkus
     * @return array{reply: string, action: array<string, mixed>}
     */
    private function interpretRawImagesPrompt(string $prompt, string $kind, array $selectedSkus): array
    {
        $local = $this->matchRawImagesPromptLocally($prompt, $kind, $selectedSkus);
        if ($local !== null) {
            return $local;
        }

        $ai = $this->matchRawImagesPromptWithAi($prompt, $kind, $selectedSkus);
        if ($ai !== null) {
            return $ai;
        }

        return [
            'reply' => 'Tell me what to do on this page — for example show missing images, search a SKU, open Dropbox import, or copy selected SKUs.',
            'action' => ['type' => 'none'],
        ];
    }

    /**
     * @param  list<string>  $selectedSkus
     * @return array{reply: string, action: array<string, mixed>}|null
     */
    private function matchRawImagesPromptLocally(string $prompt, string $kind, array $selectedSkus): ?array
    {
        $text = strtolower($prompt);
        $page = $this->pageShortName($kind);

        if (preg_match('/\b(dropbox|from dropbox)\b/', $text)) {
            return [
                'reply' => 'Opening Dropbox bulk update. Paste file links as SKU, URL.',
                'action' => ['type' => 'open_dropbox'],
            ];
        }
        if (preg_match('/\b(sheet|excel|csv|google sheet|spreadsheet|from sheet)\b/', $text)) {
            return [
                'reply' => 'Opening sheet bulk update. Upload a CSV/Excel file or paste a Google Sheet URL.',
                'action' => ['type' => 'open_sheet'],
            ];
        }
        if (preg_match('/\b(download|zip|export images)\b/', $text)) {
            return [
                'reply' => $selectedSkus === []
                    ? 'Select SKUs first, then I can download their '.$page.'.'
                    : 'Downloading raw images for the selected SKUs.',
                'action' => ['type' => $selectedSkus === [] ? 'none' : 'download_selected'],
            ];
        }
        if (preg_match('/\b(copy missing|copy all missing)\b/', $text) || (str_contains($text, 'copy') && str_contains($text, 'missing'))) {
            return [
                'reply' => 'Copying SKUs that are missing '.$page.' and have inventory.',
                'action' => ['type' => 'copy_missing'],
            ];
        }
        if (preg_match('/\b(copy sku|copy selected|copy urls|copy links)\b/', $text)) {
            $copyUrls = str_contains($text, 'url') || str_contains($text, 'link');

            return [
                'reply' => $copyUrls ? 'Copying selected image URLs.' : 'Copying selected SKUs.',
                'action' => ['type' => $copyUrls ? 'copy_urls' : 'copy_skus'],
            ];
        }
        if (preg_match('/\b(show all|reset filter|clear filter|all skus)\b/', $text)) {
            return [
                'reply' => 'Showing all SKUs.',
                'action' => ['type' => 'filter_all'],
            ];
        }
        if (preg_match('/\b(missing|no raw|without (a )?raw|need(s)? (a )?(raw )?image|no image)\b/', $text)) {
            return [
                'reply' => 'Filtering to SKUs that still need '.$page.' (inventory greater than 0).',
                'action' => ['type' => 'filter_missing'],
            ];
        }
        if (preg_match('/\b(?:search|find|filter|show|open)\s+(?:parent\s+)?["\']?(.+?)["\']?\s*$/i', $prompt, $m)) {
            $query = trim($m[1], " \t\"'");
            if ($query !== '' && ! preg_match('/\b(missing|all|dropbox|sheet)\b/i', $query)) {
                $field = str_contains($text, 'parent') ? 'parent' : (str_contains($text, 'sku') ? 'sku' : 'general');

                return [
                    'reply' => 'Searching '.$page.' for “'.$query.'”.',
                    'action' => ['type' => 'search', 'query' => $query, 'field' => $field],
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $selectedSkus
     * @return array{reply: string, action: array<string, mixed>}|null
     */
    private function matchRawImagesPromptWithAi(string $prompt, string $kind, array $selectedSkus): ?array
    {
        $headers = OpenAiRequest::authHeaders();
        if ($headers === []) {
            return null;
        }

        $page = $this->pageLabels($kind)['pageTitle'];
        $schema = 'Return JSON only: {"reply":"short message","action":{"type":"filter_missing|filter_all|search|open_sheet|open_dropbox|download_selected|copy_skus|copy_urls|copy_missing|none","query":"","field":"general|sku|parent"}}';

        try {
            $response = Http::timeout(20)
                ->withHeaders($headers)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.packing_vision_model', 'gpt-4o-mini'),
                    'temperature' => 0.2,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You help users operate the '.$page.' inventory page. '.$schema
                                .' Use search when they name a SKU or parent. Use none if unclear. Selected SKUs: '
                                .($selectedSkus === [] ? 'none' : implode(', ', array_slice($selectedSkus, 0, 20))),
                        ],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                return null;
            }

            $allowed = [
                'filter_missing', 'filter_all', 'search', 'open_sheet', 'open_dropbox',
                'download_selected', 'copy_skus', 'copy_urls', 'copy_missing', 'none',
            ];
            $type = (string) data_get($decoded, 'action.type', 'none');
            if (! in_array($type, $allowed, true)) {
                $type = 'none';
            }

            return [
                'reply' => trim((string) ($decoded['reply'] ?? 'Done.')) ?: 'Done.',
                'action' => [
                    'type' => $type,
                    'query' => trim((string) data_get($decoded, 'action.query', '')),
                    'field' => in_array((string) data_get($decoded, 'action.field', 'general'), ['general', 'sku', 'parent'], true)
                        ? (string) data_get($decoded, 'action.field', 'general')
                        : 'general',
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('Raw images AI prompt failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function looksLikeUrl(string $value): bool
    {
        return (bool) preg_match('#^https?://#i', trim($value));
    }

    private static function sidebarCacheKey(string $kind): string
    {
        return self::SIDEBAR_CACHE_PREFIX.':invgt0:'.$kind;
    }
}
