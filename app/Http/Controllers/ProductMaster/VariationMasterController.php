<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\Marketplace\ListingManagerFamily;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VariationMasterController extends Controller
{
    public function index(Request $request): View
    {
        return view('variation-master', [
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
            'variation_type' => 'nullable|string|max:120',
            'variation_name' => 'nullable|string|max:500',
        ]);

        $product = ProductMaster::query()->find($validated['id']);
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'SKU was not found on CP master.'], 404);
        }

        $values = is_array($product->Values) ? $product->Values : [];
        if ($request->exists('variation_type')) {
            $values['variation_type'] = $this->normalizeDisplay($request->input('variation_type'));
        }
        if ($request->exists('variation_name')) {
            $values['variation_name'] = $this->normalizeDisplay($request->input('variation_name'));
        }
        $product->Values = $values;
        $product->save();

        return response()->json([
            'success' => true,
            'variation_type' => $values['variation_type'] ?? '',
            'variation_name' => $values['variation_name'] ?? '',
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $format = strtolower(trim((string) $request->query('format', 'xlsx')));
        $rows = $this->productRows();

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Parent SKU', 'Child SKU', 'Variation Type', 'Variation Name']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['parent'],
                        $row['sku'],
                        $row['variation_type'],
                        $row['variation_name'],
                    ]);
                }
                fclose($out);
            }, 'variation-master.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Variation Master');
        $sheet->fromArray(['Parent SKU', 'Child SKU', 'Variation Type', 'Variation Name'], null, 'A1');
        $rowNum = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['parent'],
                $row['sku'],
                $row['variation_type'],
                $row['variation_name'],
            ], null, 'A'.$rowNum);
            $rowNum++;
        }
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'variation-master.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if ($rows === [] || $rows === null) {
            return response()->json(['success' => false, 'message' => 'The file is empty.'], 422);
        }

        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0] ?? []);
        $skuIdx = $this->columnIndex($headers, ['child sku', 'childsku', 'sku']);
        $typeIdx = $this->columnIndex($headers, ['variation type', 'variationtype', 'type']);
        $nameIdx = $this->columnIndex($headers, ['variation name', 'variationname']);

        if ($skuIdx === null) {
            return response()->json(['success' => false, 'message' => 'Child SKU column is required.'], 422);
        }
        if ($typeIdx === null && $nameIdx === null) {
            return response()->json(['success' => false, 'message' => 'Need a Variation Type or Variation Name column.'], 422);
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

        DB::transaction(function () use ($rows, $skuIdx, $typeIdx, $nameIdx, $byNorm, &$updated, &$skipped, &$missing) {
            foreach (array_slice($rows, 1) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $sku = $this->normalizeDisplay($row[$skuIdx] ?? null);
                if ($sku === '' || ListingManagerFamily::isParentSku($sku)) {
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
                if ($typeIdx !== null && array_key_exists($typeIdx, $row)) {
                    $values['variation_type'] = $this->normalizeDisplay($row[$typeIdx] ?? null);
                    $changed = true;
                }
                if ($nameIdx !== null && array_key_exists($nameIdx, $row)) {
                    $values['variation_name'] = $this->normalizeDisplay($row[$nameIdx] ?? null);
                    $changed = true;
                }

                if (! $changed) {
                    $skipped++;

                    continue;
                }

                $product->Values = $values;
                $product->save();
                $updated++;
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
     * @return list<array{id: int, parent: string, sku: string, variation_type: string, variation_name: string}>
     */
    private function productRows(): array
    {
        $products = ProductMaster::query()
            ->select(['id', 'parent', 'sku', 'Values'])
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        $rows = [];
        foreach ($products as $product) {
            $sku = $this->normalizeDisplay($product->sku);
            if ($sku === '' || ListingManagerFamily::isParentSku($sku)) {
                continue;
            }
            $values = is_array($product->Values) ? $product->Values : [];
            $rows[] = [
                'id' => (int) $product->id,
                'parent' => $this->normalizeDisplay($product->parent),
                'sku' => $sku,
                'variation_type' => $this->normalizeDisplay($values['variation_type'] ?? null),
                'variation_name' => $this->normalizeDisplay($values['variation_name'] ?? null),
            ];
        }

        return $rows;
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

    private function normalizeDisplay(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
