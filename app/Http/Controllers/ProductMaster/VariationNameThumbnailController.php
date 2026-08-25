<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\MarketplaceVariationNamePushService;
use App\Services\Support\AllMarketplaceChannelRegistry;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VariationNameThumbnailController extends Controller
{
    private const PUSH_CHANNELS_CACHE = 'vnt.push_channels.user.';

    public function index(Request $request): View
    {
        return view('variation-name-thumbnail', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
        ]);
    }

    public function getData(): JsonResponse
    {
        return response()->json([
            'data' => $this->productRows(),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'variation_name' => 'nullable|string|max:500',
            'thumbnail' => 'nullable|string|max:2000',
            'inv' => 'nullable|numeric',
        ]);

        $product = ProductMaster::query()->find($validated['id']);
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $saved = $this->applyRowUpdates($product, $request);

        return response()->json(array_merge(['success' => true], $saved));
    }

    public function saveBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'variation_name' => 'nullable|string|max:500',
            'thumbnail' => 'nullable|string|max:2000',
            'inv' => 'nullable|numeric',
            'update_variation_name' => 'sometimes|boolean',
            'update_thumbnail' => 'sometimes|boolean',
            'update_inv' => 'sometimes|boolean',
        ]);

        $products = ProductMaster::query()->whereIn('id', $validated['ids'])->get();
        if ($products->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No matching products found.'], 404);
        }

        $updated = 0;
        DB::transaction(function () use ($products, $request, &$updated) {
            foreach ($products as $product) {
                $this->applyRowUpdates($product, $request);
                $updated++;
            }
        });

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'message' => 'Updated '.$updated.' row'.($updated === 1 ? '' : 's').'.',
        ]);
    }

    public function pushChannels(): JsonResponse
    {
        $api = app(MarketplaceApiConfigService::class);
        $registry = app(AllMarketplaceChannelRegistry::class);
        $tilesByKey = [];
        foreach ($registry->channels() as $ch) {
            $tilesByKey[$ch['key']] = $ch;
            $tilesByKey[$api->normalizeChannelKey($ch['key'])] = $ch;
            $tilesByKey[$api->normalizeChannelKey($ch['label'])] = $ch;
        }

        $select = ['id', 'channel', 'status'];
        if (Schema::hasColumn('channel_master', 'logo')) {
            $select[] = 'logo';
        }

        $rows = [];
        if (Schema::hasTable('channel_master')) {
            $rows = ChannelMaster::query()
                ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                ->whereNotNull('channel')
                ->where('channel', '!=', '')
                ->orderBy('channel')
                ->get($select);
        }

        $channels = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->channel);
            $key = $api->resolveKey($name);
            $norm = $api->normalizeChannelKey($name);
            $tile = $tilesByKey[$key] ?? $tilesByKey[$norm] ?? null;
            $logo = trim((string) ($row->logo ?? ''));
            $pushKey = $key !== '' ? $key : $norm;
            $supports = MarketplaceVariationNamePushService::supports($pushKey);
            $channels[] = [
                'id' => $row->id,
                'channel' => $name,
                'key' => $pushKey,
                'label' => $tile['label'] ?? $name,
                'short' => $tile['short'] ?? strtoupper(substr($name, 0, 2)),
                'cls' => $tile['cls'] ?? 'btn-secondary',
                'logo' => $this->publicImageUrl($logo !== '' ? $logo : null),
                'configured' => $supports && ($api->isConfigured($name) || $api->isConfigured($pushKey)),
            ];
        }

        $userId = (int) (Auth::id() ?? 0);
        $selected = $userId > 0 ? Cache::get(self::PUSH_CHANNELS_CACHE.$userId, []) : [];
        if (! is_array($selected)) {
            $selected = [];
        }
        $allowedKeys = [];
        foreach ($channels as $ch) {
            if (! empty($ch['configured'])) {
                $allowedKeys[] = (string) $ch['key'];
            }
        }
        $selected = array_values(array_intersect(
            array_values(array_filter(array_map('strval', $selected))),
            $allowedKeys
        ));

        return response()->json([
            'channels' => $channels,
            'selected' => $selected,
        ]);
    }

    public function savePushChannels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channels' => 'present|array',
            'channels.*' => 'string|max:80',
        ]);
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $keys = array_values(array_unique(array_filter(array_map('strval', $validated['channels']))));
        Cache::forever(self::PUSH_CHANNELS_CACHE.$userId, $keys);

        return response()->json(['success' => true, 'selected' => $keys]);
    }

    public function pushVariationNames(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'channels' => 'required|array|min:1',
            'channels.*' => 'string|max:80',
        ]);

        $userId = (int) (Auth::id() ?? 0);
        if ($userId > 0) {
            Cache::forever(
                self::PUSH_CHANNELS_CACHE.$userId,
                array_values(array_unique(array_map('strval', $validated['channels'])))
            );
        }

        @set_time_limit(180);
        $products = ProductMaster::query()->whereIn('id', $validated['ids'])->get();
        $pusher = app(MarketplaceVariationNamePushService::class);
        $results = [];
        $ok = 0;
        $fail = 0;

        foreach ($products as $product) {
            $sku = $this->normalizeDisplay($product->sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                $fail++;
                $results[] = [
                    'sku' => $sku !== '' ? $sku : '(blank)',
                    'channel' => '—',
                    'success' => false,
                    'message' => 'Skipped PARENT / empty SKU.',
                ];

                continue;
            }

            $values = is_array($product->Values) ? $product->Values : [];
            $name = $this->normalizeDisplay($values['variation_name'] ?? null);
            if ($name === '') {
                $shopify = ShopifySku::firstForProductSku((string) $product->sku);
                $name = $this->normalizeDisplay($shopify?->variant_title);
            }
            if ($name === '') {
                $fail++;
                $results[] = [
                    'sku' => $sku,
                    'channel' => '—',
                    'success' => false,
                    'message' => 'No variation name to push.',
                ];

                continue;
            }

            foreach ($validated['channels'] as $channelKey) {
                $channelKey = strtolower(trim((string) $channelKey));
                $push = $pusher->push($channelKey, $sku, $name);
                $success = (bool) ($push['success'] ?? false);
                if ($success) {
                    $ok++;
                } else {
                    $fail++;
                }
                $results[] = [
                    'sku' => $sku,
                    'channel' => $channelKey,
                    'success' => $success,
                    'message' => $push['message'] ?? ($success ? 'Pushed.' : 'Failed.'),
                ];
            }
        }

        return response()->json([
            'success' => $fail === 0,
            'ok' => $ok,
            'fail' => $fail,
            'message' => "Pushed {$ok} update(s), {$fail} failed.",
            'results' => $results,
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Variation Name Thumbnail');
        $sheet->fromArray(['SKU', 'Variation Name', 'Thumbnail', 'INV'], null, 'A1');

        $rowNum = 2;
        foreach ($this->productRows() as $row) {
            $sheet->fromArray([
                $row['sku'],
                $row['variation_name'],
                $row['thumbnail'],
                $row['inv'],
            ], null, 'A'.$rowNum);
            $rowNum++;
        }

        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C6ED5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'pm-variation-name-thumbnail.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if ($rows === [] || $rows === null) {
            return response()->json(['success' => false, 'message' => 'The file is empty.'], 422);
        }

        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0] ?? []);
        $skuIdx = $this->columnIndex($headers, ['sku']);
        $nameIdx = $this->columnIndex($headers, ['variation name', 'variation_name', 'variationname']);
        $thumbIdx = $this->columnIndex($headers, ['thumbnail']);
        $invIdx = $this->columnIndex($headers, ['inv', 'inventory', 'shopify_inv']);

        if ($skuIdx === null) {
            return response()->json(['success' => false, 'message' => 'SKU column is required.'], 422);
        }
        if ($nameIdx === null && $thumbIdx === null && $invIdx === null) {
            return response()->json(['success' => false, 'message' => 'Need Variation Name, Thumbnail, or INV columns.'], 422);
        }

        $products = ProductMaster::query()->get(['id', 'sku', 'Values']);
        $byNorm = [];
        foreach ($products as $product) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $product->sku);
            if ($key !== '' && ! isset($byNorm[$key])) {
                $byNorm[$key] = $product;
            }
        }

        $updated = 0;
        $skipped = 0;
        $missing = 0;

        DB::transaction(function () use ($rows, $skuIdx, $nameIdx, $thumbIdx, $invIdx, $byNorm, &$updated, &$skipped, &$missing) {
            foreach (array_slice($rows, 1) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $sku = $this->normalizeDisplay($row[$skuIdx] ?? null);
                if ($sku === '') {
                    $skipped++;

                    continue;
                }

                $key = ShopifySku::normalizeSkuForShopifyLookup($sku);
                $product = $byNorm[$key] ?? null;
                if (! $product) {
                    $missing++;

                    continue;
                }

                $values = is_array($product->Values) ? $product->Values : [];
                $changed = false;

                if ($nameIdx !== null && array_key_exists($nameIdx, $row)) {
                    $values['variation_name'] = $this->normalizeDisplay($row[$nameIdx] ?? null);
                    $changed = true;
                }
                if ($thumbIdx !== null && array_key_exists($thumbIdx, $row)) {
                    $values['variation_thumbnail'] = $this->normalizeDisplay($row[$thumbIdx] ?? null);
                    $changed = true;
                }

                if ($changed) {
                    $product->Values = $values;
                    $product->save();
                }

                if ($invIdx !== null && array_key_exists($invIdx, $row) && trim((string) ($row[$invIdx] ?? '')) !== '') {
                    $invRaw = str_replace(',', '', (string) $row[$invIdx]);
                    if (is_numeric($invRaw)) {
                        $shopify = ShopifySku::firstForProductSku((string) $product->sku);
                        if ($shopify) {
                            $shopify->inv = (float) $invRaw;
                            $shopify->save();
                            $changed = true;
                        }
                    }
                }

                if ($changed) {
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Updated {$updated} SKU(s). Skipped {$skipped}. Not found {$missing}.",
            'updated' => $updated,
            'skipped' => $skipped,
            'missing' => $missing,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productRows(): array
    {
        $select = ['id', 'parent', 'sku', 'Values'];
        if (Schema::hasColumn('product_master', 'main_image')) {
            $select[] = 'main_image';
        }
        if (Schema::hasColumn('product_master', 'image1')) {
            $select[] = 'image1';
        }

        $products = ProductMaster::query()
            ->select($select)
            ->orderBy('parent')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku')
            ->get();

        $shopifyBySku = ShopifySku::mapByProductSkus(
            $products->pluck('sku')->filter()->map(fn ($sku) => (string) $sku)->all()
        );

        $rows = [];
        foreach ($products as $product) {
            $parent = $this->normalizeDisplay($product->parent);
            $sku = $this->normalizeDisplay($product->sku);
            $shopify = $shopifyBySku->get($product->sku) ?? $shopifyBySku->get($sku);

            $values = is_array($product->Values) ? $product->Values : [];
            $storedName = $this->normalizeDisplay($values['variation_name'] ?? null);
            $shopifyName = $this->normalizeDisplay($shopify?->variant_title);

            $rows[] = [
                'id' => $product->id,
                'image' => $this->resolveImage($shopify?->image_src, $product),
                'parent' => $parent,
                'sku' => $sku,
                'variation_name' => $storedName !== '' ? $storedName : $shopifyName,
                'thumbnail' => $this->normalizeDisplay(is_string($values['variation_thumbnail'] ?? null) ? $values['variation_thumbnail'] : null),
                'inv' => ($shopify && $shopify->inv !== null) ? (float) $shopify->inv : 0,
                'is_parent' => stripos($sku, 'PARENT') !== false,
            ];
        }

        return $rows;
    }

    /**
     * @return array{variation_name: string, thumbnail: string, inv: float|null}
     */
    private function applyRowUpdates(ProductMaster $product, Request $request): array
    {
        $values = is_array($product->Values) ? $product->Values : [];
        $updateName = $request->exists('update_variation_name')
            ? $request->boolean('update_variation_name')
            : $request->exists('variation_name');
        $updateThumb = $request->exists('update_thumbnail')
            ? $request->boolean('update_thumbnail')
            : $request->exists('thumbnail');
        $updateInv = $request->exists('update_inv')
            ? $request->boolean('update_inv')
            : $request->exists('inv');

        if ($updateName) {
            $values['variation_name'] = $this->normalizeDisplay($request->input('variation_name'));
        }
        if ($updateThumb) {
            $values['variation_thumbnail'] = $this->normalizeDisplay($request->input('thumbnail'));
        }

        if ($updateName || $updateThumb) {
            $product->Values = $values;
            $product->save();
        }

        $inv = null;
        if ($updateInv && $request->input('inv') !== null && $request->input('inv') !== '') {
            $shopify = ShopifySku::firstForProductSku((string) $product->sku);
            if ($shopify) {
                $shopify->inv = (float) $request->input('inv');
                $shopify->save();
                $inv = (float) $shopify->inv;
            }
        }

        return [
            'variation_name' => $values['variation_name'] ?? '',
            'thumbnail' => $values['variation_thumbnail'] ?? '',
            'inv' => $inv,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $aliases
     */
    private function columnIndex(array $headers, array $aliases): ?int
    {
        $normalize = static function (string $value): string {
            return strtolower(preg_replace('/[^a-z0-9]+/', '', $value) ?? '');
        };
        $want = array_map($normalize, $aliases);
        foreach ($headers as $i => $header) {
            if (in_array($normalize((string) $header), $want, true)) {
                return (int) $i;
            }
        }

        return null;
    }

    private function resolveImage(?string $shopifySrc, ProductMaster $product): ?string
    {
        $shopifySrc = trim((string) $shopifySrc);
        if ($shopifySrc !== '') {
            return $shopifySrc;
        }

        foreach ([$product->main_image ?? null, $product->image1 ?? null] as $col) {
            $url = $this->publicImageUrl($col);
            if ($url) {
                return $url;
            }
        }

        $values = is_array($product->Values) ? $product->Values : [];
        $fromValues = $values['image_path'] ?? $values['image'] ?? null;

        return $this->publicImageUrl(is_string($fromValues) ? $fromValues : null);
    }

    private function publicImageUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }
        if (str_starts_with($value, '//')) {
            return 'https:'.$value;
        }
        if (str_starts_with($value, '/')) {
            return $value;
        }
        if (str_starts_with($value, 'storage/')) {
            return '/'.$value;
        }

        return '/storage/'.ltrim($value, '/');
    }

    private function normalizeDisplay(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $s = html_entity_decode(trim((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($s === '') {
            return '';
        }
        $s = str_replace("\u{00A0}", ' ', $s);
        $s = preg_replace('/[\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}]/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }
}
