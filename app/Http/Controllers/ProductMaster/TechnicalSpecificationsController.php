<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\CategoryMaster;
use App\Models\ChannelMaster;
use App\Models\ProductMaster;
use App\Services\ProductMaster\BackstageSpecificationStore;
use App\Services\ProductMaster\EbayTechnicalSpecificationSync;
use App\Support\Marketplace\EbayCategoryAspects;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TechnicalSpecificationsController extends Controller
{
    public function index(Request $request)
    {
        return view('technical-specifications', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
        ]);
    }

    /**
     * GET /technical-specifications-data — same product row format as Description Master, plus specs.
     */
    public function getData(Request $request)
    {
        try {
            @set_time_limit(300);
            @ini_set('memory_limit', '512M');

            $select = [
                'id', 'parent', 'sku', 'title150',
                'product_description', 'description_1500', 'description_1000', 'description_800', 'description_600',
            ];
            if (Schema::hasColumn('product_master', 'description_v2_specifications')) {
                $select[] = 'description_v2_specifications';
            }

            $products = ProductMaster::query()
                ->orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 0 ELSE 1 END")
                ->orderBy('sku', 'asc')
                ->select($select)
                ->get();

            $categoryColumns = ['sku', 'category_ebay', 'category_amz', 'category_temu'];
            if (Schema::hasTable('category_masters') && Schema::hasColumn('category_masters', 'category_ebay_id')) {
                $categoryColumns[] = 'category_ebay_id';
            }
            $categories = Schema::hasTable('category_masters')
                ? CategoryMaster::query()->get($categoryColumns)
                    ->keyBy(fn ($row) => $this->skuKey((string) $row->sku))
                : collect();

            $result = [];
            foreach ($products as $product) {
                $specs = $this->normalizeSpecs($product->description_v2_specifications ?? null);
                $text = $this->specsToText($specs);
                $category = $categories->get($this->skuKey((string) $product->sku));

                $result[] = [
                    'id' => $product->id,
                    'category_ebay' => (string) ($category->category_ebay ?? ''),
                    'category_ebay_id' => $this->ebayCategoryId($category),
                    'category_amz' => (string) ($category->category_amz ?? ''),
                    'category_temu' => (string) ($category->category_temu ?? ''),
                    'Parent' => $product->parent,
                    'SKU' => $product->sku,
                    'title150' => $product->title150,
                    'product_description' => $product->product_description,
                    'description_1500' => $product->description_1500,
                    'description_1000' => $product->description_1000,
                    'description_800' => $product->description_800,
                    'description_600' => $product->description_600,
                    'description_v2_specifications' => $specs,
                    'technical_specifications' => $text,
                    'specifications_text' => $text,
                ];
            }

            $this->fillEbayCategoryFromParent($result);
            $aspectsByCategory = $this->ebayRequiredAspects($result);
            $savedByCategory = [];
            foreach ($result as $row) {
                $categoryId = (string) ($row['category_ebay_id'] ?? '');
                if ($categoryId === '') {
                    continue;
                }
                foreach ($row['description_v2_specifications'] as $spec) {
                    $key = trim((string) ($spec['key'] ?? ''));
                    if ($key !== '') {
                        $savedByCategory[$categoryId][$this->specKey($key)] = $key;
                    }
                }
            }
            $aspectNames = [];
            foreach ($result as &$row) {
                $categoryId = (string) ($row['category_ebay_id'] ?? '');
                $saved = $categoryId !== ''
                    ? array_values($savedByCategory[$categoryId] ?? [])
                    : array_map(static fn ($spec) => (string) ($spec['key'] ?? ''), $row['description_v2_specifications']);
                $requiredOnly = array_values($aspectsByCategory[$categoryId] ?? []);
                $names = $this->mergeAspectNames($requiredOnly, $saved);
                $values = [];
                foreach ($names as $name) {
                    $values[$name] = $this->specValue($row['description_v2_specifications'], $name);
                    $aspectNames[$this->specKey($name)] = $name;
                }
                $row['ebay_required_only'] = $requiredOnly;
                $row['ebay_required'] = $names;
                $row['ebay_values'] = $values;
                unset($row['category_ebay_id']);
            }
            unset($row);

            $ebayAspects = array_values($aspectNames);
            usort($ebayAspects, static fn ($a, $b) => strcasecmp($a, $b));

            return response()->json([
                'message' => 'Technical Specifications data loaded',
                'data' => $result,
                'ebay_aspects' => $ebayAspects,
                'channels' => $this->channelNames(),
                'status' => 200,
                'meta' => [
                    'total' => count($result),
                    'ebay_categories' => count($aspectsByCategory),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('TechnicalSpecifications: getData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load Technical Specifications data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * POST /technical-specifications/save
     */
    public function save(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'technical_specifications' => 'nullable|string',
                'specifications_text' => 'nullable|string',
                'description_v2_specifications' => 'nullable|array',
                'description_v2_specifications.*.key' => 'nullable|string|max:255',
                'description_v2_specifications.*.value' => 'nullable|string|max:2000',
            ]);

            $sku = trim($validated['sku']);
            $product = ProductMaster::where('sku', $sku)->first();
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            if (! Schema::hasColumn('product_master', 'description_v2_specifications')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Technical Specifications column is not available. Run migrations.',
                ], 500);
            }

            if (array_key_exists('description_v2_specifications', $validated) && is_array($validated['description_v2_specifications'])) {
                $specs = $this->normalizeSpecs($validated['description_v2_specifications']);
            } else {
                $text = (string) ($validated['technical_specifications'] ?? $validated['specifications_text'] ?? '');
                $specs = $this->textToSpecs($text);
            }

            foreach ($specs as &$spec) {
                $name = $this->specKey((string) ($spec['key'] ?? ''));
                $fixed = ['brand' => '5 Core', 'condition' => 'New', 'countryoforigin' => 'China'];
                if (isset($fixed[$name])) {
                    $spec['value'] = $fixed[$name];
                } elseif ($name === 'mpn' && ! $this->isParentSku($sku)) {
                    $spec['value'] = $sku;
                }
            }
            unset($spec);

            $product->description_v2_specifications = $specs === [] ? null : $specs;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Technical Specifications saved successfully.',
                'description_v2_specifications' => $specs,
                'technical_specifications' => $this->specsToText($specs),
            ]);
        } catch (\Throwable $e) {
            Log::error('TechnicalSpecifications: save failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save Technical Specifications: '.$e->getMessage(),
            ], 500);
        }
    }

    private function skuKey(string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', trim($sku));

        return strtolower(preg_replace('/\s+/u', ' ', $sku) ?? $sku);
    }

    /**
     * @return list<string>
     */
    private function channelNames(): array
    {
        if (! Schema::hasTable('channel_master')) {
            return [];
        }

        return ChannelMaster::query()
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->orderBy('channel')
            ->pluck('channel')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->values()
            ->all();
    }

    private function ebayCategoryId($category): string
    {
        if ($category === null) {
            return '';
        }
        $id = trim((string) ($category->category_ebay_id ?? ''));
        if (preg_match('/^\d+$/', $id)) {
            return $id;
        }
        $name = trim((string) ($category->category_ebay ?? ''));
        if (preg_match('/^\d+$/', $name)) {
            return $name;
        }

        return '';
    }

    /**
     * A child with no eBay category uses the category saved on its parent row.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function fillEbayCategoryFromParent(array &$rows): void
    {
        $byParent = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['category_ebay_id'] ?? ''));
            $parent = $this->skuKey((string) ($row['Parent'] ?? ''));
            if ($id === '' || $parent === '' || ! $this->isParentSku((string) ($row['SKU'] ?? ''))) {
                continue;
            }
            $byParent[$parent] = $id;
        }
        foreach ($rows as &$row) {
            if (trim((string) ($row['category_ebay_id'] ?? '')) !== '' || $this->isParentSku((string) ($row['SKU'] ?? ''))) {
                continue;
            }
            $parent = $this->skuKey((string) ($row['Parent'] ?? ''));
            if ($parent !== '' && isset($byParent[$parent])) {
                $row['category_ebay_id'] = $byParent[$parent];
            }
        }
        unset($row);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<string>>
     */
    private function ebayRequiredAspects(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['category_ebay_id'] ?? ''));
            if ($id !== '') {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        return EbayCategoryAspects::requiredNamesByCategory(
            array_values($ids),
            '',
            (string) config('services.ebay.app_id'),
            (string) config('services.ebay.cert_id')
        );
    }

    private function isParentSku(string $sku): bool
    {
        return stripos(trim($sku), 'PARENT ') === 0;
    }

    /**
     * @param  list<array{key: string, value: string}>  $specs
     */
    private function specValue(array $specs, string $name): string
    {
        $want = $this->specKey($name);
        foreach ($specs as $row) {
            if ($this->specKey((string) ($row['key'] ?? '')) === $want) {
                return (string) ($row['value'] ?? '');
            }
        }

        return '';
    }

    private function specKey(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?? $name);
    }

    /**
     * @param  list<string>  $required
     * @param  list<string>  $saved
     * @return list<string>
     */
    private function mergeAspectNames(array $required, array $saved): array
    {
        $out = [];
        $seen = [];
        foreach (array_merge($required, $saved) as $name) {
            $name = trim((string) $name);
            $key = $this->specKey($name);
            if ($name === '' || $key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $name;
        }

        return $out;
    }

    /**
     * @param  mixed  $raw
     * @return list<array{key: string, value: string}>
     */
    private function normalizeSpecs($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? $row['Key'] ?? ''));
            $value = trim((string) ($row['value'] ?? $row['Value'] ?? ''));
            if ($key === '' && $value === '') {
                continue;
            }
            $out[] = ['key' => $key, 'value' => $value];
        }

        return $out;
    }

    /**
     * @param  list<array{key: string, value: string}>  $specs
     */
    private function specsToText(array $specs): string
    {
        $lines = [];
        foreach ($specs as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($key === '' && $value === '') {
                continue;
            }
            if ($key !== '' && $value !== '') {
                $lines[] = $key.': '.$value;
            } elseif ($key !== '') {
                $lines[] = $key;
            } else {
                $lines[] = $value;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function textToSpecs(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(.+?)\s*:\s*(.*)$/u', $line, $m)) {
                $out[] = ['key' => trim($m[1]), 'value' => trim($m[2])];
            } elseif (preg_match('/^(.+?)\s*\|\s*(.*)$/u', $line, $m)) {
                $out[] = ['key' => trim($m[1]), 'value' => trim($m[2])];
            } else {
                $out[] = ['key' => $line, 'value' => ''];
            }
        }

        return $out;
    }

    /**
     * POST /technical-specifications/pull-ebay
     * Live eBay item specifics replace the specifications saved for each selected SKU.
     */
    public function pullEbay(Request $request, EbayTechnicalSpecificationSync $sync)
    {
        $skus = $this->selectedSkus($request);
        @set_time_limit(300);

        $results = [];
        foreach ($skus as $sku) {
            $product = ProductMaster::query()->where('sku', $sku)->first();
            if (! $product) {
                $results[] = $this->syncResult($sku, false, 'SKU not found.');

                continue;
            }
            $pulled = $sync->pull($sku);
            if ($pulled['success'] && Schema::hasColumn('product_master', 'description_v2_specifications')) {
                $product->description_v2_specifications = $pulled['specs'] === [] ? null : $pulled['specs'];
                $product->save();
            }
            $results[] = $this->syncResult($sku, $pulled['success'], $pulled['message'], $pulled['specs']);
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * POST /technical-specifications/push-ebay
     * Sends only values that differ from the live listing. Blank values are never sent.
     */
    public function pushEbay(Request $request, EbayTechnicalSpecificationSync $sync)
    {
        $skus = $this->selectedSkus($request);
        @set_time_limit(300);

        $results = [];
        foreach ($skus as $sku) {
            $product = ProductMaster::query()->where('sku', $sku)->first();
            if (! $product) {
                $results[] = $this->syncResult($sku, false, 'SKU not found.');

                continue;
            }
            $specs = $this->normalizeSpecs($product->description_v2_specifications ?? null);
            $pushed = $sync->push($sku, $specs);
            if ($pushed['success']) {
                app(BackstageSpecificationStore::class)->recordPush($sku, 'ebay1', $specs);
            }
            $results[] = $this->syncResult($sku, $pushed['success'], $pushed['message'], $specs);
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * @return list<string>
     */
    private function selectedSkus(Request $request): array
    {
        $data = $request->validate([
            'skus' => 'required|array|min:1|max:25',
            'skus.*' => 'required|string|max:255',
        ]);

        $skus = [];
        foreach ($data['skus'] as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $skus[$sku] = $sku;
            }
        }
        if ($skus === []) {
            abort(response()->json(['success' => false, 'message' => 'Select the rows first.'], 422));
        }

        return array_values($skus);
    }

    /**
     * @param  list<array{key: string, value: string}>  $specs
     * @return array{sku: string, success: bool, message: string, description_v2_specifications: list<array{key: string, value: string}>, technical_specifications: string}
     */
    private function syncResult(string $sku, bool $success, string $message, array $specs = []): array
    {
        return [
            'sku' => $sku,
            'success' => $success,
            'message' => $message,
            'description_v2_specifications' => $specs,
            'technical_specifications' => $this->specsToText($specs),
        ];
    }
}
