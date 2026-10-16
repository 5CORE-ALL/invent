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
            'bullet_1' => 'nullable|string|max:1000',
            'bullet_2' => 'nullable|string|max:1000',
            'bullet_3' => 'nullable|string|max:1000',
            'bullet_4' => 'nullable|string|max:1000',
            'bullet_5' => 'nullable|string|max:1000',
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

        $listingChanged = $request->exists('title') || $request->exists('description') || $this->requestHasBullet($request);
        $listing = null;
        if ($listingChanged) {
            $parent = ListingManagerFamily::normalizeParentKey((string) ($request->input('parent') ?: $product->parent));
            $error = $this->writeListingText(
                $parent,
                $request->exists('title') ? $this->normalizeDisplay($request->input('title')) : null,
                $request->exists('description') ? $this->keepLines($request->input('description')) : null,
                $this->requestHasBullet($request) ? $this->joinBullets($this->bulletSlotsFromRequest($request)) : null
            );
            if ($error !== null) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }
            $parentRow = VariationListingContent::parentProduct($parent);
            $listing = $this->listingText(is_array($parentRow?->Values) ? $parentRow->Values : []);
        }

        $values = is_array($product->Values) ? $product->Values : [];

        return response()->json([
            'success' => true,
            'parent' => $this->normalizeDisplay($product->parent),
            'variation_type' => $values['variation_type'] ?? '',
            'variation_name' => $values['variation_name'] ?? '',
            'title' => $listing['title'] ?? '',
            'description' => $listing['description'] ?? '',
            'bullet_1' => $listing['bullet_1'] ?? '',
            'bullet_2' => $listing['bullet_2'] ?? '',
            'bullet_3' => $listing['bullet_3'] ?? '',
            'bullet_4' => $listing['bullet_4'] ?? '',
            'bullet_5' => $listing['bullet_5'] ?? '',
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
                fputcsv($out, ['Parent SKU', 'Child SKU', 'Variation Type', 'Variation Name', 'Title', 'Description', 'Bullet 1', 'Bullet 2', 'Bullet 3', 'Bullet 4', 'Bullet 5']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['parent'],
                        $row['sku'],
                        $row['variation_type'],
                        $row['variation_name'],
                        $row['title'],
                        $row['description'],
                        $row['bullet_1'],
                        $row['bullet_2'],
                        $row['bullet_3'],
                        $row['bullet_4'],
                        $row['bullet_5'],
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
        $sheet->fromArray(['Parent SKU', 'Child SKU', 'Variation Type', 'Variation Name', 'Title', 'Description', 'Bullet 1', 'Bullet 2', 'Bullet 3', 'Bullet 4', 'Bullet 5'], null, 'A1');
        $rowNum = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['parent'],
                $row['sku'],
                $row['variation_type'],
                $row['variation_name'],
                $row['title'],
                $row['description'],
                $row['bullet_1'],
                $row['bullet_2'],
                $row['bullet_3'],
                $row['bullet_4'],
                $row['bullet_5'],
            ], null, 'A'.$rowNum);
            $rowNum++;
        }
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        foreach (range('A', 'K') as $col) {
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
        $legacyBulletsIdx = $this->columnIndex($headers, ['bullet points', 'bulletpoints', 'bullets', 'bullet point']);
        $bulletIdx = [];
        for ($i = 1; $i <= 5; $i++) {
            $bulletIdx[$i] = $this->columnIndex($headers, ['bullet '.$i, 'bullet'.$i]);
        }
        $hasBulletColumn = $legacyBulletsIdx !== null || count(array_filter($bulletIdx, static fn ($idx) => $idx !== null)) > 0;

        if ($skuIdx === null) {
            return response()->json(['success' => false, 'message' => 'Child SKU column is required.'], 422);
        }
        if ($typeIdx === null && $nameIdx === null && $titleIdx === null && $descriptionIdx === null && ! $hasBulletColumn) {
            return response()->json(['success' => false, 'message' => 'Need a Variation Type, Variation Name, Title, Description, or Bullet column.'], 422);
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

        DB::transaction(function () use ($rows, $parentIdx, $skuIdx, $typeIdx, $nameIdx, $titleIdx, $descriptionIdx, $legacyBulletsIdx, $bulletIdx, $byNorm, &$updated, &$skipped, &$missing) {
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

                $numberedBullet = false;
                foreach ($bulletIdx as $idx) {
                    if ($idx !== null && array_key_exists($idx, $row)) {
                        $numberedBullet = true;
                        break;
                    }
                }
                $listingTouched = ($titleIdx !== null && array_key_exists($titleIdx, $row))
                    || ($descriptionIdx !== null && array_key_exists($descriptionIdx, $row))
                    || $numberedBullet
                    || ($legacyBulletsIdx !== null && array_key_exists($legacyBulletsIdx, $row));
                if ($listingTouched) {
                    $parent = $parentIdx !== null
                        ? $this->normalizeDisplay($row[$parentIdx] ?? null)
                        : $this->normalizeDisplay($product->parent);
                    $error = $this->writeListingText(
                        $parent,
                        $titleIdx !== null ? $this->normalizeDisplay($row[$titleIdx] ?? null) : null,
                        $descriptionIdx !== null ? $this->keepLines($row[$descriptionIdx] ?? null) : null,
                        $this->importBulletText($parent, $row, $bulletIdx, $legacyBulletsIdx, $numberedBullet)
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
     * @return list<array{id: int, parent: string, sku: string, variation_type: string, variation_name: string, title: string, description: string, bullet_1: string, bullet_2: string, bullet_3: string, bullet_4: string, bullet_5: string}>
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
            $listing = $listingByParent[$parent] ?? $this->emptyListingText();
            $rows[] = [
                'id' => (int) $product->id,
                'parent' => $this->normalizeDisplay($product->parent),
                'sku' => $this->normalizeDisplay($product->sku),
                'variation_type' => $this->normalizeDisplay($values['variation_type'] ?? null),
                'variation_name' => $this->normalizeDisplay($values['variation_name'] ?? null),
                'title' => $listing['title'],
                'description' => $listing['description'],
                'bullet_1' => $listing['bullet_1'],
                'bullet_2' => $listing['bullet_2'],
                'bullet_3' => $listing['bullet_3'],
                'bullet_4' => $listing['bullet_4'],
                'bullet_5' => $listing['bullet_5'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{title: string, description: string, bullet_1: string, bullet_2: string, bullet_3: string, bullet_4: string, bullet_5: string}
     */
    private function listingText(array $values): array
    {
        $bullets = $values[VariationListingContent::BULLETS_KEY] ?? '';
        if (is_array($bullets)) {
            $bullets = implode("\n", $bullets);
        }
        $slots = $this->bulletSlots($bullets);

        return [
            'title' => $this->normalizeDisplay($values[VariationListingContent::TITLE_KEY] ?? null),
            'description' => $this->keepLines($values[VariationListingContent::DESCRIPTION_KEY] ?? null),
            'bullet_1' => $slots[0],
            'bullet_2' => $slots[1],
            'bullet_3' => $slots[2],
            'bullet_4' => $slots[3],
            'bullet_5' => $slots[4],
        ];
    }

    /**
     * @return array{title: string, description: string, bullet_1: string, bullet_2: string, bullet_3: string, bullet_4: string, bullet_5: string}
     */
    private function emptyListingText(): array
    {
        return [
            'title' => '',
            'description' => '',
            'bullet_1' => '',
            'bullet_2' => '',
            'bullet_3' => '',
            'bullet_4' => '',
            'bullet_5' => '',
        ];
    }

    /**
     * @return list<string>
     */
    private function bulletSlots(mixed $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw) ?: [];
        $slots = [];
        for ($i = 0; $i < 5; $i++) {
            $slots[] = $this->normalizeDisplay($lines[$i] ?? '');
        }

        return $slots;
    }

    /**
     * @param  list<string>  $slots
     */
    private function joinBullets(array $slots): string
    {
        $lines = [];
        for ($i = 0; $i < 5; $i++) {
            $lines[] = $this->normalizeDisplay($slots[$i] ?? '');
        }
        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }

    private function requestHasBullet(Request $request): bool
    {
        for ($i = 1; $i <= 5; $i++) {
            if ($request->exists('bullet_'.$i)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function bulletSlotsFromRequest(Request $request): array
    {
        $slots = [];
        for ($i = 1; $i <= 5; $i++) {
            $slots[] = $this->normalizeDisplay($request->input('bullet_'.$i));
        }

        return $slots;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<int, int|null>  $bulletIdx
     */
    private function importBulletText(string $parent, array $row, array $bulletIdx, ?int $legacyBulletsIdx, bool $numberedBullet): ?string
    {
        if ($numberedBullet) {
            $existing = $this->bulletSlots('');
            $parentRow = VariationListingContent::parentProduct($parent);
            if ($parentRow) {
                $stored = is_array($parentRow->Values) ? ($parentRow->Values[VariationListingContent::BULLETS_KEY] ?? '') : '';
                if (is_array($stored)) {
                    $stored = implode("\n", $stored);
                }
                $existing = $this->bulletSlots($stored);
            }
            foreach ($bulletIdx as $number => $idx) {
                if ($idx !== null && array_key_exists($idx, $row)) {
                    $existing[$number - 1] = $this->normalizeDisplay($row[$idx] ?? '');
                }
            }

            return $this->joinBullets($existing);
        }

        if ($legacyBulletsIdx !== null && array_key_exists($legacyBulletsIdx, $row)) {
            return $this->joinBullets($this->bulletSlots($row[$legacyBulletsIdx] ?? ''));
        }

        return null;
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
