<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Support\Marketplace\ListingManagerFamily;
use App\Support\Marketplace\VariationListingContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VariationListingContentController extends Controller
{
    public function index(Request $request): View
    {
        return view('variation-listing-content', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
        ]);
    }

    public function getData(): JsonResponse
    {
        return response()->json([
            'data' => $this->parentRows(),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent' => 'required|string|max:255',
            'title' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:20000',
            'bullets' => 'nullable|string|max:8000',
        ]);

        $parent = ListingManagerFamily::normalizeParentKey($validated['parent']);
        $product = VariationListingContent::parentProduct($parent);
        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Add a PARENT row for '.$parent.' on CP master before saving listing text.',
            ], 422);
        }

        $values = is_array($product->Values) ? $product->Values : [];
        $values[VariationListingContent::TITLE_KEY] = $this->singleLine($request->input('title'));
        $values[VariationListingContent::DESCRIPTION_KEY] = $this->keepLines($request->input('description'));
        $values[VariationListingContent::BULLETS_KEY] = $this->keepLines($request->input('bullets'));
        $product->Values = $values;
        $product->save();
        VariationListingContent::forget($parent);

        return response()->json([
            'success' => true,
            'id' => (int) $product->id,
            'parent' => $parent,
            'title' => $values[VariationListingContent::TITLE_KEY],
            'description' => $values[VariationListingContent::DESCRIPTION_KEY],
            'bullets' => $values[VariationListingContent::BULLETS_KEY],
        ]);
    }

    public function download(): StreamedResponse
    {
        $rows = $this->parentRows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Parent', 'Title', 'Description', 'Bullet Points']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['parent'],
                    $row['title'],
                    $row['description'],
                    $row['bullets'],
                ]);
            }
            fclose($out);
        }, 'variation-listing-content.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
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
        $parentIdx = $this->columnIndex($headers, ['parent', 'parentsku', 'parent sku']);
        $titleIdx = $this->columnIndex($headers, ['title']);
        $descriptionIdx = $this->columnIndex($headers, ['description']);
        $bulletsIdx = $this->columnIndex($headers, ['bullet points', 'bulletpoints', 'bullets', 'bullet point']);

        if ($parentIdx === null) {
            return response()->json(['success' => false, 'message' => 'Parent column is required.'], 422);
        }
        if ($titleIdx === null && $descriptionIdx === null && $bulletsIdx === null) {
            return response()->json(['success' => false, 'message' => 'Need a Title, Description, or Bullet Points column.'], 422);
        }

        $updated = 0;
        $skipped = 0;
        $missing = 0;

        DB::transaction(function () use ($rows, $parentIdx, $titleIdx, $descriptionIdx, $bulletsIdx, &$updated, &$skipped, &$missing) {
            foreach (array_slice($rows, 1) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $parent = ListingManagerFamily::normalizeParentKey($this->singleLine($row[$parentIdx] ?? null));
                if ($parent === '') {
                    $skipped++;

                    continue;
                }

                $product = VariationListingContent::parentProduct($parent);
                if (! $product) {
                    $missing++;

                    continue;
                }

                $values = is_array($product->Values) ? $product->Values : [];
                $changed = false;
                if ($titleIdx !== null && array_key_exists($titleIdx, $row)) {
                    $values[VariationListingContent::TITLE_KEY] = $this->singleLine($row[$titleIdx] ?? null);
                    $changed = true;
                }
                if ($descriptionIdx !== null && array_key_exists($descriptionIdx, $row)) {
                    $values[VariationListingContent::DESCRIPTION_KEY] = $this->keepLines($row[$descriptionIdx] ?? null);
                    $changed = true;
                }
                if ($bulletsIdx !== null && array_key_exists($bulletsIdx, $row)) {
                    $values[VariationListingContent::BULLETS_KEY] = $this->keepLines($row[$bulletsIdx] ?? null);
                    $changed = true;
                }
                if (! $changed) {
                    $skipped++;

                    continue;
                }

                $product->Values = $values;
                $product->save();
                VariationListingContent::forget($parent);
                $updated++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Updated {$updated} parent(s). Skipped {$skipped}. Not found {$missing}.",
            'updated' => $updated,
            'skipped' => $skipped,
            'missing' => $missing,
        ]);
    }

    /**
     * @return list<array{id: int, parent: string, title: string, description: string, bullets: string}>
     */
    private function parentRows(): array
    {
        $products = ProductMaster::query()
            ->select(['id', 'parent', 'sku', 'Values'])
            ->orderBy('sku')
            ->get();

        $parentRows = [];
        $childParents = [];
        foreach ($products as $product) {
            $sku = trim((string) $product->sku);
            if ($sku === '') {
                continue;
            }
            if (ListingManagerFamily::isParentSku($sku)) {
                $key = ListingManagerFamily::normalizeParentKey((string) ($product->parent ?: ''));
                if ($key === '') {
                    $key = ListingManagerFamily::normalizeParentKey($sku);
                }
                if ($key !== '' && ! isset($parentRows[$key])) {
                    $parentRows[$key] = $product;
                }

                continue;
            }

            $key = ListingManagerFamily::normalizeParentKey((string) $product->parent);
            if ($key !== '') {
                $childParents[$key] = true;
            }
        }

        $keys = array_keys($childParents);
        natcasesort($keys);

        $rows = [];
        foreach ($keys as $key) {
            $row = $parentRows[$key] ?? null;
            $values = $row && is_array($row->Values) ? $row->Values : [];
            $bullets = $values[VariationListingContent::BULLETS_KEY] ?? '';
            if (is_array($bullets)) {
                $bullets = implode("\n", $bullets);
            }
            $rows[] = [
                'id' => $row ? (int) $row->id : 0,
                'parent' => $key,
                'title' => $this->singleLine($values[VariationListingContent::TITLE_KEY] ?? null),
                'description' => $this->keepLines($values[VariationListingContent::DESCRIPTION_KEY] ?? null),
                'bullets' => $this->keepLines($bullets),
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

    private function singleLine(mixed $value): string
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
