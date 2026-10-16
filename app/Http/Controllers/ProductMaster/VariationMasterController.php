<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\Marketplace\ListingManagerFamily;
use App\Support\Marketplace\VariationListingContent;
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
        $request->validate([
            'id' => 'required|integer',
            'parent' => 'nullable|string|max:255',
            'variation_type' => 'nullable|string|max:120',
            'variation_name' => 'nullable|string|max:500',
            'title' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:20000',
            'bullets' => 'nullable|string|max:8000',
        ]);

        $product = ProductMaster::query()->find($request->integer('id'));
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'SKU was not found on CP master.'], 404);
        }

        $childChanged = $request->exists('variation_type') || $request->exists('variation_name');
        if ($childChanged) {
            $values = is_array($product->Values) ? $product->Values : [];
            if ($request->exists('variation_type')) {
                $values['variation_type'] = $this->normalizeDisplay($request->input('variation_type'));
            }
            if ($request->exists('variation_name')) {
                $values['variation_name'] = $this->normalizeDisplay($request->input('variation_name'));
            }
            $product->Values = $values;
            $product->save();
        }

        $listingChanged = $request->exists('title') || $request->exists('description') || $request->exists('bullets');
        $listing = null;
        if ($listingChanged) {
            $parent = ListingManagerFamily::normalizeParentKey((string) ($request->input('parent') ?: $product->parent));
            $error = $this->writeListingText(
                $parent,
                $request->exists('title') ? $this->normalizeDisplay($request->input('title')) : null,
                $request->exists('description') ? $this->keepLines($request->input('description')) : null,
                $request->exists('bullets') ? $this->keepLines($request->input('bullets')) : null
            );
            if ($error !== null) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }
            $listing = VariationListingContent::forParent($parent);
        }

        $values = is_array($product->Values) ? $product->Values : [];

        return response()->json([
            'success' => true,
            'parent' => $this->normalizeDisplay($product->parent),
            'variation_type' => $values['variation_type'] ?? '',
            'variation_name' => $values['variation_name'] ?? '',
            'title' => $listing['title'] ?? '',
            'description' => $listing['description'] ?? '',
            'bullets' => isset($listing['bullets']) ? implode("\n", $listing['bullets']) : '',
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
                fputcsv($out, ['Parent SKU', 'Child SKU', 'Variation Type', 'Variation Name', 'Title', 'Description', 'Bullet Points']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['parent'],
                        $row['sku'],
                        $row['variation_type'],
                        $row['variation_name'],
                        $row['title'],
                        $row['description'],
                        $row['bullets'],
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
        $sheet->fromArray(['Parent SKU', 'Child SKU', 'Variation Type', 'Variation Name', 'Title', 'Description', 'Bullet Points'], null, 'A1');
        $rowNum = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['parent'],
                $row['sku'],
                $row['variation_type'],
                $row['variation_name'],
                $row['title'],
                $row['description'],
                $row['bullets'],
            ], null, 'A'.$rowNum);
            $rowNum++;
        }
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        foreach (range('A', 'G') as $col) {
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
        $parentIdx = $this->columnIndex($headers, ['parent sku', 'parentsku', 'parent']);
        $skuIdx = $this->columnIndex($headers, ['child sku', 'childsku', 'sku']);
        $typeIdx = $this->columnIndex($headers, ['variation type', 'variationtype', 'type']);
        $nameIdx = $this->columnIndex($headers, ['variation name', 'variationname']);
        $titleIdx = $this->columnIndex($headers, ['title']);
        $descriptionIdx = $this->columnIndex($headers, ['description']);
        $bulletsIdx = $this->columnIndex($headers, ['bullet points', 'bulletpoints', 'bullets', 'bullet point']);

        if ($skuIdx === null) {
            return response()->json(['success' => false, 'message' => 'Child SKU column is required.'], 422);
        }
        if ($typeIdx === null && $nameIdx === null && $titleIdx === null && $descriptionIdx === null && $bulletsIdx === null) {
            return response()->json(['success' => false, 'message' => 'Need a Variation Type, Variation Name, Title, Description, or Bullet Points column.'], 422);
        }

        $products = ProductMaster::query()->get(['id', 'parent', 'sku', 'Values']);
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

        DB::transaction(function () use ($rows, $parentIdx, $skuIdx, $typeIdx, $nameIdx, $titleIdx, $descriptionIdx, $bulletsIdx, $byNorm, &$updated, &$skipped, &$missing) {
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

                $changed = false;
                if ($typeIdx !== null || $nameIdx !== null) {
                    $values = is_array($product->Values) ? $product->Values : [];
                    if ($typeIdx !== null && array_key_exists($typeIdx, $row)) {
                        $values['variation_type'] = $this->normalizeDisplay($row[$typeIdx] ?? null);
                        $changed = true;
                    }
                    if ($nameIdx !== null && array_key_exists($nameIdx, $row)) {
                        $values['variation_name'] = $this->normalizeDisplay($row[$nameIdx] ?? null);
                        $changed = true;
                    }
                    if ($changed) {
                        $product->Values = $values;
                        $product->save();
                    }
                }

                $listingTouched = ($titleIdx !== null && array_key_exists($titleIdx, $row))
                    || ($descriptionIdx !== null && array_key_exists($descriptionIdx, $row))
                    || ($bulletsIdx !== null && array_key_exists($bulletsIdx, $row));
                if ($listingTouched) {
                    $parent = $parentIdx !== null
                        ? $this->normalizeDisplay($row[$parentIdx] ?? null)
                        : $this->normalizeDisplay($product->parent);
                    $error = $this->writeListingText(
                        $parent,
                        $titleIdx !== null ? $this->normalizeDisplay($row[$titleIdx] ?? null) : null,
                        $descriptionIdx !== null ? $this->keepLines($row[$descriptionIdx] ?? null) : null,
                        $bulletsIdx !== null ? $this->keepLines($row[$bulletsIdx] ?? null) : null
                    );
                    if ($error !== null) {
                        $missing++;

                        continue;
                    }
                    $changed = true;
                }

                if (! $changed) {
                    $skipped++;

                    continue;
                }

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
     * @return list<array{id: int, parent: string, sku: string, variation_type: string, variation_name: string, title: string, description: string, bullets: string}>
     */
    private function productRows(): array
    {
        $products = ProductMaster::query()
            ->select(['id', 'parent', 'sku', 'Values'])
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        $listingByParent = [];
        $children = [];
        foreach ($products as $product) {
            $sku = $this->normalizeDisplay($product->sku);
            if ($sku === '') {
                continue;
            }
            $values = is_array($product->Values) ? $product->Values : [];
            if (ListingManagerFamily::isParentSku($sku)) {
                $key = ListingManagerFamily::normalizeParentKey((string) ($product->parent ?: ''));
                if ($key === '') {
                    $key = ListingManagerFamily::normalizeParentKey($sku);
                }
                if ($key !== '' && ! isset($listingByParent[$key])) {
                    $listingByParent[$key] = $this->listingText($values);
                }

                continue;
            }

            $children[] = $product;
        }

        $rows = [];
        foreach ($children as $product) {
            $parent = ListingManagerFamily::normalizeParentKey($this->normalizeDisplay($product->parent));
            $values = is_array($product->Values) ? $product->Values : [];
            $listing = $listingByParent[$parent] ?? ['title' => '', 'description' => '', 'bullets' => ''];
            $rows[] = [
                'id' => (int) $product->id,
                'parent' => $this->normalizeDisplay($product->parent),
                'sku' => $this->normalizeDisplay($product->sku),
                'variation_type' => $this->normalizeDisplay($values['variation_type'] ?? null),
                'variation_name' => $this->normalizeDisplay($values['variation_name'] ?? null),
                'title' => $listing['title'],
                'description' => $listing['description'],
                'bullets' => $listing['bullets'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{title: string, description: string, bullets: string}
     */
    private function listingText(array $values): array
    {
        $bullets = $values[VariationListingContent::BULLETS_KEY] ?? '';
        if (is_array($bullets)) {
            $bullets = implode("\n", $bullets);
        }

        return [
            'title' => $this->normalizeDisplay($values[VariationListingContent::TITLE_KEY] ?? null),
            'description' => $this->keepLines($values[VariationListingContent::DESCRIPTION_KEY] ?? null),
            'bullets' => $this->keepLines($bullets),
        ];
    }

    private function writeListingText(string $parent, ?string $title, ?string $description, ?string $bullets): ?string
    {
        $parent = ListingManagerFamily::normalizeParentKey($parent);
        if ($parent === '') {
            return 'Parent is required before saving title, description, or bullet points.';
        }

        $row = VariationListingContent::parentProduct($parent);
        if (! $row) {
            return 'Add a PARENT row for '.$parent.' on CP master before saving listing text.';
        }

        $values = is_array($row->Values) ? $row->Values : [];
        if ($title !== null) {
            $values[VariationListingContent::TITLE_KEY] = $title;
        }
        if ($description !== null) {
            $values[VariationListingContent::DESCRIPTION_KEY] = $description;
        }
        if ($bullets !== null) {
            $values[VariationListingContent::BULLETS_KEY] = $bullets;
        }
        $row->Values = $values;
        $row->save();
        VariationListingContent::forget($parent);

        return null;
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

    private function keepLines(mixed $value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) ($value ?? ''));
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
