<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ComparisonSpecification;
use App\Services\ComparisonSheetService;
use App\Services\ComparisonSheetStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * "Purchase Specific": every distinct Spec label (the first column of every Purchase Comparison form),
 * auto-normalised to sentence case, with edit / merge on top of the shared specification master list.
 */
class PurchaseSpecificController extends Controller
{
    private const CACHE_KEY = 'purchase_specific_groups_v1';

    private const CACHE_TTL = 600;

    /** Words that keep upper case inside a sentence-case label. */
    public const ACRONYMS = [
        'sku', 'usd', 'rmb', 'cbm', 'moq', 'upc', 'ean', 'asin', 'fob', 'exw', 'cif', 'gst', 'mrp',
        'hs', 'qc', 'pur', 'lmp', 'cp', 'id', 'url', 'pdf', 'led', 'usb', 'uv', 'abs', 'pvc', 'pu',
    ];

    public function __construct(
        private ComparisonSheetService $sheetService,
        private ComparisonSheetStorage $sheetStorage,
        private ComparisonController $comparison
    ) {
    }

    public function index()
    {
        return view('purchase-master.purchase-specific.index');
    }

    public function data(Request $request)
    {
        if ($request->boolean('fresh')) {
            Cache::forget(self::CACHE_KEY);
        }

        $groups = $this->groups();

        return response()->json([
            'success' => true,
            'acronyms' => self::ACRONYMS,
            'rows' => $groups,
        ]);
    }

    /** Rename one specification (all its spelling variants) everywhere; the new name is sentence-cased. */
    public function rename(Request $request)
    {
        $data = $request->validate([
            'key' => 'required|string|max:200',
            'new_name' => 'required|string|max:120',
        ]);

        return $this->mergeKeys([$data['key']], $data['new_name']);
    }

    /** Merge several specifications into one new (or existing) sentence-cased specification. */
    public function merge(Request $request)
    {
        $data = $request->validate([
            'keys' => 'required|array|min:2|max:200',
            'keys.*' => 'required|string|max:200',
            'new_name' => 'required|string|max:120',
        ]);

        return $this->mergeKeys($data['keys'], $data['new_name']);
    }

    /** Persist the sentence-case / de-duplicated spelling of every specification into all sheets + master list. */
    public function normalizeAll()
    {
        @set_time_limit(300);

        $groups = $this->groups(true);
        $map = [];      // specKey(raw variant) => target
        $targets = [];  // group key => target
        foreach ($groups as $g) {
            $targets[$g['key']] = $g['name'];
            foreach ($g['variants'] as $variant) {
                if ($variant !== $g['name']) {
                    $map[$this->looseKey($variant)] = $g['name'];
                }
            }
        }

        $stats = ['sheets' => 0, 'cells' => 0];
        if ($map !== []) {
            $stats = $this->rewriteSheets(function (array $cells) use ($map): array {
                $specCol = $this->sheetService->detectSpecColumnIndex($cells);
                $n = 0;
                foreach ($cells as $r => $row) {
                    if (! is_array($row) || ! array_key_exists($specCol, $row)) {
                        continue;
                    }
                    $label = (string) $row[$specCol];
                    $k = $this->looseKey($label);
                    if ($k !== '' && isset($map[$k]) && $map[$k] !== $label) {
                        $cells[$r][$specCol] = $map[$k];
                        $n++;
                    }
                }

                return [$cells, $n];
            });
        }

        $this->normalizeMasterList($targets);
        Cache::forget(self::CACHE_KEY);

        return response()->json([
            'success' => true,
            'updated_sheets' => $stats['sheets'],
            'updated_cells' => $stats['cells'],
        ]);
    }

    // ------------------------------------------------------------------ normalisation

    /** Sentence case: "  PRODUCT   price/rmb: " => "Product price/RMB". */
    public static function normalizeLabel(string $label): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', $label));
        $label = trim((string) preg_replace('/\s*\/\s*/u', '/', $label));
        $label = rtrim($label, " \t:;,.");
        if ($label === '') {
            return '';
        }

        $out = (string) preg_replace_callback('/[\p{L}\p{N}\']+/u', function ($m) {
            $lower = mb_strtolower($m[0]);

            return in_array($lower, self::ACRONYMS, true) ? mb_strtoupper($lower) : $lower;
        }, $label);

        $first = mb_substr($out, 0, 1);

        return mb_strtoupper($first) . mb_substr($out, 1);
    }

    private function groupKey(string $label): string
    {
        return mb_strtolower(self::normalizeLabel($label));
    }

    /** Same key the existing rename/merge code uses to match stored sheet labels. */
    private function looseKey(string $label): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $label)));
    }

    private function isUsableLabel(string $label): bool
    {
        if ($label === '' || mb_strlen($label) > 60) {
            return false;
        }
        if (preg_match('/^(https?:|data:|\[)/i', $label) || is_numeric($label)) {
            return false;
        }
        if (preg_match('/^row\s*\d+$/i', $label)) {
            return false;
        }

        return mb_strtolower($label) !== 'spec';
    }

    // ------------------------------------------------------------------ data

    /**
     * @return list<array{key:string,name:string,count:int,variants:list<string>,needs_normalize:bool,in_master:bool}>
     */
    private function groups(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->buildGroups());
    }

    private function buildGroups(): array
    {
        @set_time_limit(300);

        $sheetsByKey = []; // groupKey => [sheetId => true]
        $variants = [];    // groupKey => [raw label => uses]

        $collect = function (array $cells, string $sheetId) use (&$sheetsByKey, &$variants) {
            $specCol = $this->sheetService->detectSpecColumnIndex($cells);
            foreach ($cells as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $raw = trim((string) preg_replace('/\s+/u', ' ', (string) ($row[$specCol] ?? '')));
                if (! $this->isUsableLabel($raw)) {
                    continue;
                }
                $key = $this->groupKey($raw);
                if ($key === '') {
                    continue;
                }
                $sheetsByKey[$key][$sheetId] = true;
                $variants[$key][$raw] = ($variants[$key][$raw] ?? 0) + 1;
            }
        };

        $this->eachSheet(function (array $cells, string $sheetId) use ($collect) {
            $collect($cells, $sheetId);
        });

        // Master list entries that no sheet uses yet still belong in the list (count 0).
        $master = [];
        if (Schema::hasTable('comparison_specifications')) {
            foreach (ComparisonSpecification::query()->pluck('name') as $name) {
                $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));
                if (! $this->isUsableLabel($name)) {
                    continue;
                }
                $key = $this->groupKey($name);
                $master[$key] = true;
                if (! isset($variants[$key][$name])) {
                    $variants[$key][$name] = 0;
                }
            }
        }

        $rows = [];
        foreach ($variants as $key => $raws) {
            // Display name = sentence-cased spelling of the most-used variant.
            $best = (string) (array_keys($raws, max($raws), true)[0] ?? array_key_first($raws));
            $name = self::normalizeLabel($best);
            $rawList = array_keys($raws);
            sort($rawList, SORT_NATURAL | SORT_FLAG_CASE);
            $rows[] = [
                'key' => (string) $key,
                'name' => $name,
                'count' => count($sheetsByKey[$key] ?? []),
                'variants' => $rawList,
                'needs_normalize' => count(array_diff($rawList, [$name])) > 0,
                'in_master' => isset($master[$key]),
            ];
        }

        usort($rows, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Visit every stored comparison sheet once (file copy preferred, DB copy otherwise).
     *
     * @param  callable(array, string): void  $callback
     */
    private function eachSheet(callable $callback): void
    {
        $disk = Storage::disk('local');
        $seen = [];

        foreach ($disk->files('comparison-sheets') as $path) {
            if (! str_ends_with($path, '.json')) {
                continue;
            }
            $payload = json_decode((string) $disk->get($path), true);
            if (! is_array($payload) || ! is_array($payload['cells'] ?? null)) {
                continue;
            }
            $seen[$path] = true;
            $callback($payload['cells'], $path);
        }

        if (! Schema::hasTable('comparison_data')) {
            return;
        }

        DB::table('comparison_data')->select('id', 'sku', 'sheet_data')->orderBy('id')->chunkById(50, function ($rows) use ($callback, &$seen) {
            foreach ($rows as $row) {
                $path = $this->sheetStorage->pathForSku((string) $row->sku);
                if (isset($seen[$path])) {
                    continue;
                }
                $data = json_decode((string) $row->sheet_data, true);
                if (! is_array($data) || ! is_array($data['cells'] ?? null)) {
                    continue;
                }
                $seen[$path] = true;
                $callback($data['cells'], $path);
            }
        });
    }

    /**
     * Rewrite the Spec labels of every stored sheet (file copy + DB copy).
     *
     * @param  callable(array): array{0: array, 1: int}  $rewrite  returns [cells, changedCount]
     * @return array{sheets:int,cells:int}
     */
    private function rewriteSheets(callable $rewrite): array
    {
        $sheets = 0;
        $cellsChanged = 0;

        $disk = Storage::disk('local');
        foreach ($disk->files('comparison-sheets') as $path) {
            if (! str_ends_with($path, '.json')) {
                continue;
            }
            $payload = json_decode((string) $disk->get($path), true);
            if (! is_array($payload) || ! is_array($payload['cells'] ?? null)) {
                continue;
            }
            [$cells, $n] = $rewrite($payload['cells']);
            if ($n > 0) {
                $payload['cells'] = $cells;
                $disk->put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
                $sheets++;
                $cellsChanged += $n;
            }
        }

        if (Schema::hasTable('comparison_data')) {
            DB::table('comparison_data')->select('id', 'sheet_data')->orderBy('id')->chunkById(50, function ($rows) use ($rewrite, &$sheets, &$cellsChanged) {
                foreach ($rows as $row) {
                    $data = json_decode((string) $row->sheet_data, true);
                    if (! is_array($data) || ! is_array($data['cells'] ?? null)) {
                        continue;
                    }
                    [$cells, $n] = $rewrite($data['cells']);
                    if ($n > 0) {
                        $data['cells'] = $cells;
                        DB::table('comparison_data')->where('id', $row->id)->update([
                            'sheet_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                        ]);
                        $sheets++;
                        $cellsChanged += $n;
                    }
                }
            });
        }

        return ['sheets' => $sheets, 'cells' => $cellsChanged];
    }

    // ------------------------------------------------------------------ writes

    /** @param  list<string>  $keys  group keys from the data endpoint */
    private function mergeKeys(array $keys, string $newName)
    {
        $target = self::normalizeLabel($newName);
        if ($target === '') {
            return response()->json(['success' => false, 'message' => 'New name is required.'], 422);
        }

        $byKey = collect($this->groups())->keyBy('key');
        $names = [];
        foreach ($keys as $key) {
            $group = $byKey->get(mb_strtolower(trim($key)));
            if (! $group) {
                return response()->json(['success' => false, 'message' => 'Specification "' . $key . '" no longer exists. Refresh the list.'], 422);
            }
            foreach ($group['variants'] as $variant) {
                $names[] = $variant;
            }
        }
        $names = array_values(array_unique($names));

        $response = $this->comparison->mergeSpecificationNames($names, $target);
        Cache::forget(self::CACHE_KEY);

        return $response;
    }

    /**
     * Master list: one row per group, named with the normalised spelling.
     *
     * @param  array<string,string>  $targets  group key => normalised name
     */
    private function normalizeMasterList(array $targets): void
    {
        if (! Schema::hasTable('comparison_specifications')) {
            return;
        }

        DB::transaction(function () use ($targets) {
            $rows = ComparisonSpecification::query()->orderBy('sort_order')->orderBy('id')->get();
            $kept = [];
            foreach ($rows as $row) {
                $key = $this->groupKey((string) $row->name);
                if (isset($kept[$key])) {
                    $row->delete(); // duplicate spelling of an entry we already keep
                    continue;
                }
                $kept[$key] = $row;
            }
            foreach ($kept as $key => $row) {
                $target = $targets[$key] ?? self::normalizeLabel((string) $row->name);
                if ($target !== '' && $row->name !== $target) {
                    $row->update(['name' => $target]);
                }
            }
        });
    }
}
