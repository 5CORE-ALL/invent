<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
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
            @set_time_limit(180);
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

            $result = [];
            foreach ($products as $product) {
                $specs = $this->normalizeSpecs($product->description_v2_specifications ?? null);
                $text = $this->specsToText($specs);

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
                    'description_v2_specifications' => $specs,
                    'technical_specifications' => $text,
                    'specifications_text' => $text,
                ];
            }

            return response()->json([
                'message' => 'Technical Specifications data loaded',
                'data' => $result,
                'status' => 200,
                'meta' => ['total' => count($result)],
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
}
