<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\CategoryMaster;
use App\Models\ProductMaster;
use App\Services\ProductMaster\BackstageSpecificationStore;
use App\Services\ProductMaster\EbayTechnicalSpecificationSync;
use App\Support\Marketplace\EbayCategoryAspects;
use App\Support\Marketplace\ProductUpc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

            // The long description texts are not loaded here (only Export needs them, see descriptions()).
            $select = ['id', 'parent', 'sku', 'title150'];
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
                    'description_v2_specifications' => $specs,
                    'technical_specifications' => $text,
                ];
            }

            $this->fillEbayCategoryFromParent($result);
            $upcBySku = ProductUpc::forSkus(array_map(static fn ($row) => (string) ($row['SKU'] ?? ''), $result));
            foreach ($result as &$row) {
                $found = $upcBySku[trim((string) ($row['SKU'] ?? ''))] ?? ['upc' => '', 'source' => ''];
                $row['upc'] = $found['upc'];
                $row['upc_source'] = $found['source'];
            }
            unset($row);
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
                // Every listed SKU carries a UPC column (CP Master, else Amazon). It goes to all eBay accounts on Push.
                if (! $this->isParentSku((string) ($row['SKU'] ?? ''))) {
                    $names = $this->mergeAspectNames($names, ['UPC']);
                }
                $values = [];
                $rowIsParent = $this->isParentSku((string) ($row['SKU'] ?? ''));
                foreach ($names as $name) {
                    $values[$name] = $this->specValue($row['description_v2_specifications'], $name);
                    // Always the same value, whatever is saved: Brand and Manufacturer are 5 Core, MPN and Model are the SKU.
                    $fixedKey = $this->specKey($name);
                    if (in_array($fixedKey, ['brand', 'manufacturer'], true)) {
                        $values[$name] = '5 Core';
                    } elseif (in_array($fixedKey, ['mpn', 'model'], true) && ! $rowIsParent) {
                        $values[$name] = (string) ($row['SKU'] ?? '');
                    }
                    $aspectNames[$this->specKey($name)] = $name;
                }
                $row['ebay_required_only'] = $requiredOnly;
                $row['ebay_required'] = $names;
                $row['ebay_values'] = $values;
                $row['hide_scope'] = $this->hideScope($categoryId, (string) ($row['category_ebay'] ?? ''), (string) ($row['SKU'] ?? ''));
                unset($row['category_ebay_id']);
            }
            unset($row);

            $ebayAspects = array_values($aspectNames);
            usort($ebayAspects, static fn ($a, $b) => strcasecmp($a, $b));

            return response()->json([
                'message' => 'Technical Specifications data loaded',
                'data' => $result,
                'ebay_aspects' => $ebayAspects,
                'hidden_fields' => $this->hiddenFieldsByScope(),
                'column_groups' => $this->columnGroups(),
                'column_sources' => array_values(array_map(
                    static fn ($s) => ['column' => $s['column'], 'source' => $s['source']],
                    $this->columnSources()
                )),
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
     * GET /technical-specifications-descriptions — long description texts by SKU, used only by Export.
     */
    public function descriptions()
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $map = [];
        ProductMaster::query()
            ->select(['sku', 'description_1500', 'description_1000', 'description_800', 'description_600'])
            ->orderBy('id')
            ->chunk(500, function ($products) use (&$map) {
                foreach ($products as $product) {
                    $map[(string) $product->sku] = [
                        'description_1500' => (string) ($product->description_1500 ?? ''),
                        'description_1000' => (string) ($product->description_1000 ?? ''),
                        'description_800' => (string) ($product->description_800 ?? ''),
                        'description_600' => (string) ($product->description_600 ?? ''),
                    ];
                }
            });

        return response()->json(['status' => 200, 'data' => $map]);
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

            $upcForSave = null;
            foreach ($specs as &$spec) {
                $name = $this->specKey((string) ($spec['key'] ?? ''));
                $fixed = ['brand' => '5 Core', 'manufacturer' => '5 Core', 'condition' => 'New', 'countryoforigin' => 'China'];
                if (isset($fixed[$name])) {
                    $spec['value'] = $fixed[$name];
                } elseif (($name === 'mpn' || $name === 'model') && ! $this->isParentSku($sku)) {
                    $spec['value'] = $sku;
                } elseif ($name === 'upc' && ! $this->isParentSku($sku)) {
                    // CP Master first, Amazon when CP Master is blank. Nothing found keeps what was typed.
                    $found = $upcForSave ??= ProductUpc::forSku($sku);
                    if ($found['upc'] !== '') {
                        $spec['value'] = $found['upc'];
                    }
                }
            }
            unset($spec);

            // Columns that always read another column keep following it.
            $specs = $this->applyColumnSources($specs);

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

    /**
     * Fields crossed out in the edit form are remembered per eBay category.
     */
    private function hideScope(string $categoryId, string $categoryPath, string $sku): string
    {
        if (trim($categoryId) !== '') {
            return 'ebay:'.trim($categoryId);
        }
        $path = strtolower(trim(preg_replace('/\s+/u', ' ', $categoryPath) ?? $categoryPath));
        if ($path !== '') {
            return 'path:'.$path;
        }

        return 'sku:'.$this->skuKey($sku);
    }

    /**
     * @return array<string, list<string>>
     */
    private function hiddenFieldsByScope(): array
    {
        if (! Schema::hasTable('technical_spec_hidden_fields')) {
            return [];
        }
        $map = [];
        foreach (DB::table('technical_spec_hidden_fields')->get(['scope', 'field_key']) as $row) {
            $map[(string) $row->scope][] = (string) $row->field_key;
        }

        return $map;
    }

    /**
     * Same table as the migration, created on first use so hiding a field works before migrations run.
     */
    private function ensureHiddenFieldsTable(): bool
    {
        try {
            if (Schema::hasTable('technical_spec_hidden_fields')) {
                return true;
            }
            Schema::create('technical_spec_hidden_fields', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('scope', 191);
                $table->string('field_key', 191);
                $table->string('label', 255)->nullable();
                $table->unsignedBigInteger('hidden_by')->nullable();
                $table->timestamps();
                $table->unique(['scope', 'field_key']);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('TechnicalSpecifications: could not create technical_spec_hidden_fields', ['error' => $e->getMessage()]);

            return Schema::hasTable('technical_spec_hidden_fields');
        }
    }

    /**
     * POST /technical-specifications/hidden-fields — cross a field out of the edit form for a category.
     * Saved values are not touched.
     */
    public function hideField(Request $request)
    {
        $data = $request->validate([
            'scope' => 'required|string|max:191',
            'field_key' => 'required|string|max:191',
            'label' => 'nullable|string|max:255',
        ]);
        if (! $this->ensureHiddenFieldsTable()) {
            return response()->json(['success' => false, 'message' => 'Could not create the table that remembers hidden fields. Run php artisan migrate.'], 500);
        }

        $exists = DB::table('technical_spec_hidden_fields')
            ->where('scope', $data['scope'])->where('field_key', $data['field_key'])->exists();
        if (! $exists) {
            DB::table('technical_spec_hidden_fields')->insert([
                'scope' => $data['scope'],
                'field_key' => $data['field_key'],
                'label' => $data['label'] ?? null,
                'hidden_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * POST /technical-specifications/hidden-fields/restore — show a crossed-out field of a category again
     * (every crossed-out field of the category when no field_key is sent).
     */
    public function restoreHiddenFields(Request $request)
    {
        $data = $request->validate([
            'scope' => 'required|string|max:191',
            'field_key' => 'nullable|string|max:191',
        ]);
        if (Schema::hasTable('technical_spec_hidden_fields')) {
            $query = DB::table('technical_spec_hidden_fields')->where('scope', $data['scope']);
            if (($data['field_key'] ?? '') !== '') {
                $query->where('field_key', $data['field_key']);
            }
            $query->delete();
        }

        return response()->json(['success' => true]);
    }

    private function skuKey(string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', trim($sku));

        return strtolower(preg_replace('/\s+/u', ' ', $sku) ?? $sku);
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
        $byPath = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['category_ebay_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $path = $this->skuKey((string) ($row['category_ebay'] ?? ''));
            if ($path !== '') {
                $byPath[$path][$id] = ($byPath[$path][$id] ?? 0) + 1;
            }
            $parent = $this->skuKey((string) ($row['Parent'] ?? ''));
            if ($parent !== '' && $this->isParentSku((string) ($row['SKU'] ?? ''))) {
                $byParent[$parent] = ['id' => $id, 'path' => $path];
            }
        }
        foreach ($rows as &$row) {
            if (trim((string) ($row['category_ebay_id'] ?? '')) !== '' || $this->isParentSku((string) ($row['SKU'] ?? ''))) {
                continue;
            }
            $path = $this->skuKey((string) ($row['category_ebay'] ?? ''));
            // A SKU that has a category name takes the ID other SKUs have for that same name.
            if ($path !== '' && isset($byPath[$path])) {
                $ids = $byPath[$path];
                arsort($ids);
                $row['category_ebay_id'] = (string) array_key_first($ids);

                continue;
            }
            // The parent's ID is used only when the SKU has no name of its own or the same name as the parent.
            $parent = $this->skuKey((string) ($row['Parent'] ?? ''));
            if ($parent !== '' && isset($byParent[$parent]) && ($path === '' || $path === $byParent[$parent]['path'])) {
                $row['category_ebay_id'] = $byParent[$parent]['id'];
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
        // A SKU can hold the same specification twice ("Magnet Grade" blank from an eBay pull and
        // "MAGNET GRADE" typed in); the one with a value wins, as in the edit form.
        foreach ($specs as $row) {
            if ($this->specKey((string) ($row['key'] ?? '')) === $want && trim((string) ($row['value'] ?? '')) !== '') {
                return (string) $row['value'];
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
     * GET /technical-specifications/column-options
     * Column names that are not on the table yet: item specifics already pulled from eBay 1, columns added by hand
     * on Ebay1 Data, and every source mapping. The page adds the names it already has.
     */
    public function columnOptions()
    {
        $names = Cache::remember('techspecs.column_options', 600, function () {
            $found = [];
            if (Schema::hasTable('ebay1_item_specifics')) {
                foreach (DB::table('ebay1_item_specifics')->pluck('specifics') as $json) {
                    $decoded = json_decode((string) $json, true);
                    foreach (is_array($decoded) ? array_keys($decoded) : [] as $name) {
                        $found[] = (string) $name;
                    }
                }
            }
            if (Schema::hasTable('ebay1_extra_columns')) {
                foreach (DB::table('ebay1_extra_columns')->pluck('name') as $name) {
                    $found[] = (string) $name;
                }
            }

            $unique = [];
            foreach ($found as $name) {
                $name = trim((string) $name);
                $key = $this->specKey($name);
                if ($name !== '' && $key !== '' && strlen($name) <= 120 && ! preg_match('/[{};<>]|text-decoration|^\./', $name) && ! isset($unique[$key])) {
                    $unique[$key] = $name;
                }
            }
            $list = array_values($unique);
            usort($list, static fn ($a, $b) => strcasecmp($a, $b));

            return $list;
        });

        return response()->json(['success' => true, 'columns' => $names]);
    }

    /**
     * POST /technical-specifications/add-columns
     * Writes each name as a blank specification on the given SKUs (the page sends every SKU of the categories of the
     * selected rows), so the column exists for those categories. The same columns are added to the Ebay1 Data tabs.
     */
    public function addColumns(Request $request)
    {
        $data = $request->validate([
            'names' => 'required|array|min:1|max:50',
            'names.*' => 'required|string|max:191',
            'skus' => 'required|array|min:1|max:300',
            'skus.*' => 'required|string|max:255',
            'categories' => 'nullable|array|max:300',
            'categories.*' => 'nullable|string|max:500',
        ]);
        if (! Schema::hasColumn('product_master', 'description_v2_specifications')) {
            return response()->json(['success' => false, 'message' => 'Technical Specifications column is not available. Run migrations.'], 500);
        }

        $names = [];
        foreach ($data['names'] as $candidate) {
            $candidate = trim(preg_replace('/\s+/u', ' ', (string) $candidate) ?? '');
            $key = $this->specKey($candidate);
            if ($candidate === '' || $key === '' || preg_match('/[{}<>;]/', $candidate)) {
                continue;
            }
            $names[$key] = $candidate;
        }
        if ($names === []) {
            return response()->json(['success' => false, 'message' => 'Enter a valid column name.'], 422);
        }
        @set_time_limit(300);

        $skus = array_values(array_unique(array_filter(
            array_map('trim', $data['skus']),
            static fn ($s) => $s !== '' && stripos($s, 'PARENT') === false
        )));

        $touched = 0;
        foreach (ProductMaster::query()->whereIn('sku', $skus)->get() as $product) {
            $specs = $this->normalizeSpecs($product->description_v2_specifications ?? null);
            $have = [];
            foreach ($specs as $spec) {
                $have[$this->specKey((string) ($spec['key'] ?? ''))] = true;
            }
            $changed = false;
            foreach ($names as $key => $name) {
                if (! isset($have[$key])) {
                    $specs[] = ['key' => $name, 'value' => ''];
                    $changed = true;
                }
            }
            if ($changed) {
                $product->description_v2_specifications = $specs;
                $product->save();
                $touched++;
            }
        }

        // The Ebay1 Data tabs of these categories get the same columns.
        if (Schema::hasTable('ebay1_extra_columns')) {
            foreach (array_unique(array_filter(array_map(static fn ($c) => trim((string) $c), (array) ($data['categories'] ?? [])))) as $category) {
                $hash = sha1(strtolower($category));
                foreach ($names as $key => $name) {
                    $exists = DB::table('ebay1_extra_columns')->where('category_hash', $hash)->where('name_key', $key)->exists();
                    if (! $exists) {
                        DB::table('ebay1_extra_columns')->insert([
                            'category' => $category,
                            'category_hash' => $hash,
                            'name' => $name,
                            'name_key' => $key,
                            'added_by' => $request->user()?->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
        Cache::forget('techspecs.column_options');
        Cache::forget('ebay1data.column_options');

        return response()->json(['success' => true, 'names' => array_values($names), 'skus_updated' => $touched]);
    }

    /**
     * POST /technical-specifications/delete-columns
     * Removes columns from the given SKUs: the saved specification, and (when asked) the item specific on the live
     * eBay 1 listing. A SKU where eBay refuses keeps its saved value, so it can be retried.
     */
    public function deleteColumns(Request $request, EbayTechnicalSpecificationSync $sync)
    {
        $data = $request->validate([
            'names' => 'required|array|min:1|max:50',
            'names.*' => 'required|string|max:191',
            'skus' => 'required|array|min:1|max:100',
            'skus.*' => 'required|string|max:255',
            'ebay' => 'nullable|boolean',
            'cleanup' => 'nullable|boolean',
            'categories' => 'nullable|array|max:300',
            'categories.*' => 'nullable|string|max:500',
        ]);
        $withEbay = (bool) ($data['ebay'] ?? false);
        if ($withEbay && count($data['skus']) > 10) {
            return response()->json(['success' => false, 'message' => 'Send at most 10 SKUs at a time when eBay is changed too.'], 422);
        }
        @set_time_limit(300);

        $names = [];
        foreach ($data['names'] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && $this->specKey($candidate) !== '') {
                $names[$this->specKey($candidate)] = $candidate;
            }
        }
        if ($names === []) {
            return response()->json(['success' => false, 'message' => 'Choose a column.'], 422);
        }
        $wanted = array_keys($names);

        if ($data['cleanup'] ?? false) {
            $this->forgetColumns($wanted, (array) ($data['categories'] ?? []));
        }

        $results = [];
        foreach (array_values(array_unique(array_map('trim', $data['skus']))) as $sku) {
            $product = ProductMaster::query()->where('sku', $sku)->first();
            if (! $product) {
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => 'SKU not found.'];

                continue;
            }

            $ebayNote = '';
            if ($withEbay) {
                $removed = $sync->removeSpecifics($sku, array_values($names));
                if ($removed['status'] === 'failed') {
                    $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $removed['message']];

                    continue;
                }
                $ebayNote = ' '.$removed['message'];
                $this->forgetPulledSpecifics($sku, $wanted);
            }

            $specs = $this->normalizeSpecs($product->description_v2_specifications ?? null);
            $kept = array_values(array_filter($specs, fn ($spec) => ! in_array($this->specKey((string) ($spec['key'] ?? '')), $wanted, true)));
            $had = count($kept) !== count($specs);
            if ($had) {
                $product->description_v2_specifications = $kept === [] ? null : $kept;
                $product->save();
            }
            $results[] = [
                'sku' => $sku,
                'status' => $had ? 'deleted' : 'absent',
                'message' => ($had ? 'Removed.' : 'Column was not saved for this SKU.').$ebayNote,
            ];
        }
        Cache::forget('techspecs.column_options');
        Cache::forget('ebay1data.column_options');

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * POST /technical-specifications/save-source
     * Saves (or, with a blank source, removes) the column another column always copies its value from,
     * then brings every SKU that already has the column in line.
     */
    public function saveSource(Request $request)
    {
        $data = $request->validate([
            'column' => 'required|string|max:191',
            'source' => 'nullable|string|max:191',
        ]);
        if (! Schema::hasTable('technical_spec_column_sources')) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table technical_spec_column_sources is missing).'], 500);
        }

        $column = trim(preg_replace('/\s+/u', ' ', (string) $data['column']) ?? '');
        $columnKey = $this->specKey($column);
        $source = trim(preg_replace('/\s+/u', ' ', (string) ($data['source'] ?? '')) ?? '');
        $sourceKey = $this->specKey($source);
        if ($columnKey === '') {
            return response()->json(['success' => false, 'message' => 'Choose a column.'], 422);
        }

        if ($source === '' || $sourceKey === '') {
            DB::table('technical_spec_column_sources')->where('column_key', $columnKey)->delete();

            return response()->json(['success' => true, 'removed' => true, 'updated' => 0]);
        }
        if ($sourceKey === $columnKey) {
            return response()->json(['success' => false, 'message' => 'A column cannot read from itself.'], 422);
        }
        if (in_array($columnKey, ['brand', 'manufacturer', 'condition', 'countryoforigin', 'mpn', 'model', 'upc'], true)) {
            return response()->json(['success' => false, 'message' => $column.' is filled automatically and cannot read from another column.'], 422);
        }

        $payload = [
            'column_name' => $column,
            'source_name' => $source,
            'source_key' => $sourceKey,
            'saved_by' => $request->user()?->id,
            'updated_at' => now(),
        ];
        if (DB::table('technical_spec_column_sources')->where('column_key', $columnKey)->exists()) {
            DB::table('technical_spec_column_sources')->where('column_key', $columnKey)->update($payload);
        } else {
            DB::table('technical_spec_column_sources')->insert($payload + ['column_key' => $columnKey, 'created_at' => now()]);
        }

        @set_time_limit(300);
        $updated = 0;
        ProductMaster::query()
            ->whereNotNull('description_v2_specifications')
            ->select(['id', 'sku', 'description_v2_specifications'])
            ->chunkById(300, function ($products) use ($columnKey, $source, &$updated) {
                foreach ($products as $product) {
                    $specs = $this->normalizeSpecs($product->description_v2_specifications ?? null);
                    [$next, $changed] = $this->copySourceValue($specs, $columnKey, $source);
                    if ($changed) {
                        $product->description_v2_specifications = $next;
                        $product->save();
                        $updated++;
                    }
                }
            });

        return response()->json(['success' => true, 'removed' => false, 'source' => $source, 'updated' => $updated]);
    }

    /**
     * POST /technical-specifications/column-groups
     * Replaces the whole list of headers and the columns in each (a column can be in several headers).
     */
    public function saveColumnGroups(Request $request)
    {
        $data = $request->validate([
            'groups' => 'present|array|max:100',
            'groups.*.name' => 'required|string|max:100',
            'groups.*.columns' => 'nullable|array|max:2000',
            'groups.*.columns.*' => 'nullable|string|max:191',
        ]);
        if (! Schema::hasTable('technical_spec_column_groups')) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table technical_spec_column_groups is missing).'], 500);
        }

        $groups = [];
        foreach ($data['groups'] as $group) {
            $name = trim(preg_replace('/\s+/u', ' ', (string) ($group['name'] ?? '')) ?? '');
            if ($name === '' || isset($groups[strtolower($name)])) {
                continue;
            }
            $columns = [];
            foreach ((array) ($group['columns'] ?? []) as $column) {
                $column = trim((string) $column);
                if ($column !== '' && $this->specKey($column) !== '') {
                    $columns[$this->specKey($column)] = $column;
                }
            }
            $groups[strtolower($name)] = ['name' => $name, 'columns' => array_values($columns)];
        }

        DB::transaction(function () use ($groups, $request) {
            DB::table('technical_spec_column_groups')->delete();
            $order = 0;
            foreach ($groups as $group) {
                DB::table('technical_spec_column_groups')->insert([
                    'name' => $group['name'],
                    'columns' => json_encode($group['columns'], JSON_UNESCAPED_UNICODE),
                    'sort_order' => $order++,
                    'saved_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['success' => true, 'groups' => array_values($groups)]);
    }

    /**
     * @return list<array{name: string, columns: list<string>}>
     */
    private function columnGroups(): array
    {
        if (! Schema::hasTable('technical_spec_column_groups')) {
            return [];
        }
        $out = [];
        foreach (DB::table('technical_spec_column_groups')->orderBy('sort_order')->orderBy('id')->get(['name', 'columns']) as $row) {
            $decoded = json_decode((string) $row->columns, true);
            $out[] = [
                'name' => (string) $row->name,
                'columns' => array_values(array_filter(is_array($decoded) ? $decoded : [], 'is_string')),
            ];
        }

        return $out;
    }

    /**
     * Source mappings: column key => {column, source, source_key}.
     *
     * @return array<string, array{column: string, source: string, source_key: string}>
     */
    private function columnSources(): array
    {
        if (! Schema::hasTable('technical_spec_column_sources')) {
            return [];
        }
        $out = [];
        foreach (DB::table('technical_spec_column_sources')->orderBy('id')->get(['column_key', 'column_name', 'source_name', 'source_key']) as $row) {
            $out[(string) $row->column_key] = [
                'column' => (string) $row->column_name,
                'source' => (string) $row->source_name,
                'source_key' => (string) $row->source_key,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{key: string, value: string}>  $specs
     * @return list<array{key: string, value: string}>
     */
    private function applyColumnSources(array $specs): array
    {
        foreach ($this->columnSources() as $columnKey => $map) {
            [$specs] = $this->copySourceValue($specs, (string) $columnKey, $map['source']);
        }

        return $specs;
    }

    /**
     * Sets every saved copy of the column to the value of the source column of the same SKU.
     *
     * @param  list<array{key: string, value: string}>  $specs
     * @return array{0: list<array{key: string, value: string}>, 1: bool}
     */
    private function copySourceValue(array $specs, string $columnKey, string $source): array
    {
        $value = $this->specValue($specs, $source);
        $changed = false;
        foreach ($specs as &$spec) {
            if ($this->specKey((string) ($spec['key'] ?? '')) === $columnKey && (string) ($spec['value'] ?? '') !== $value) {
                $spec['value'] = $value;
                $changed = true;
            }
        }
        unset($spec);

        return [$specs, $changed];
    }

    /**
     * Drops deleted columns from the Ebay1 Data tabs and from the source mappings.
     *
     * @param  list<string>  $keys
     * @param  list<string>  $categories
     */
    private function forgetColumns(array $keys, array $categories): void
    {
        $hashes = [];
        foreach ($categories as $category) {
            $category = trim((string) $category);
            if ($category !== '') {
                $hashes[] = sha1(strtolower($category));
            }
        }
        if ($hashes !== []) {
            if (Schema::hasTable('ebay1_extra_columns')) {
                DB::table('ebay1_extra_columns')->whereIn('category_hash', $hashes)->whereIn('name_key', $keys)->delete();
            }
            if (Schema::hasTable('ebay1_column_sources')) {
                DB::table('ebay1_column_sources')->whereIn('category_hash', $hashes)->whereIn('column_key', $keys)->delete();
            }
        }
        if (Schema::hasTable('technical_spec_column_sources')) {
            DB::table('technical_spec_column_sources')
                ->where(function ($q) use ($keys) {
                    $q->whereIn('column_key', $keys)->orWhereIn('source_key', $keys);
                })
                ->delete();
        }
    }

    /**
     * Keeps the stored eBay 1 pull of a SKU in line after eBay dropped those item specifics.
     *
     * @param  list<string>  $keys
     */
    private function forgetPulledSpecifics(string $sku, array $keys): void
    {
        if (! Schema::hasTable('ebay1_item_specifics')) {
            return;
        }
        $stored = DB::table('ebay1_item_specifics')->where('sku', $this->skuKey($sku))->first();
        if (! $stored) {
            return;
        }
        $decoded = json_decode((string) $stored->specifics, true);
        $decoded = is_array($decoded) ? $decoded : [];
        foreach (array_keys($decoded) as $name) {
            if (in_array($this->specKey((string) $name), $keys, true)) {
                unset($decoded[$name]);
            }
        }
        DB::table('ebay1_item_specifics')->where('sku', $this->skuKey($sku))->update([
            'specifics' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
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
            // UPC: CP Master, then Amazon, then a valid UPC typed into the specification.
            $upc = ProductUpc::forSku($sku)['upc'];
            if ($upc === '') {
                $upc = ProductUpc::normalize($this->specValue($specs, 'UPC'));
            }
            $pushed = $sync->push($sku, $specs, $upc);
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
