<?php

namespace App\Services;

use App\Models\ExcelImport;
use App\Models\ExcelWorksheet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads the "More benchmarks" block of every category tab from the eBay Listing Quality
 * report uploaded in /excel.
 *
 * The /excel importer only stores the listing table that sits below that block, so the
 * block is read straight from the stored .xlsx. Layout of each category tab (col B):
 *
 *   B22  "More benchmarks"
 *   B24  header row  : B "Impressions in eBay search", F metric name, G.. attribute names
 *   B25  "<seller>"  : your values            \
 *   B26  "Top 10% ..."                         } repeated for 3 sections:
 *   B27  "Bottom 10% ..."                     /  Impressions, Click-through, Sales conversion
 *
 * Output is flattened to one row per (category, section, benchmark).
 */
class ExcelBenchmarksService
{
    private const MAX_SCAN_ROWS = 90;

    private const LAST_COLUMN = 'AZ';

    /**
     * @return array{
     *     source: array{filename: ?string, import_id: ?int},
     *     columns: array<int, array{field: string, title: string}>,
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function get(): array
    {
        $importId = ExcelWorksheet::query()->orderByDesc('id')->value('excel_import_id');
        $import = $importId ? ExcelImport::query()->find($importId) : null;
        if (! $import) {
            return $this->empty();
        }

        $path = Storage::disk('local')->path($import->stored_filename);
        if (! is_file($path)) {
            return $this->empty($import);
        }

        $key = 'excel_benchmarks_v1:'.$import->id.':'.filemtime($path);
        $cached = Cache::get($key);
        if (is_array($cached) && ($cached['rows'] ?? []) !== []) {
            return $cached;
        }

        try {
            $data = $this->parse($path, $import);
        } catch (\Throwable $e) {
            Log::warning('ExcelBenchmarksService: '.$e->getMessage());

            return $this->empty($import);
        }

        if ($data['rows'] !== []) {
            Cache::put($key, $data, 86400);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(?ExcelImport $import = null): array
    {
        return [
            'source' => ['filename' => $import?->original_filename, 'import_id' => $import?->id],
            'columns' => [],
            'rows' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(string $path, ExcelImport $import): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $spreadsheet = $reader->load($path);

        $columns = [];   // field => title (ordered by first appearance)
        $rows = [];

        try {
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $block = $this->parseSheet($sheet, $columns);
                foreach ($block as $row) {
                    $rows[] = $row;
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $reader);
        }

        $columnList = [];
        foreach ($columns as $field => $title) {
            $columnList[] = ['field' => $field, 'title' => $title];
        }

        return [
            'source' => ['filename' => $import->original_filename, 'import_id' => $import->id],
            'columns' => $columnList,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, string>  $columns  attribute columns discovered so far (by reference)
     * @return array<int, array<string, mixed>>
     */
    private function parseSheet(Worksheet $sheet, array &$columns): array
    {
        $maxRow = min($sheet->getHighestRow(), self::MAX_SCAN_ROWS);
        if ($maxRow < 5) {
            return [];
        }

        // Formatted values (so percentages show as "96%") keyed by column letter.
        $grid = $sheet->rangeToArray('A1:'.self::LAST_COLUMN.$maxRow, null, true, true, true);

        $moreRow = null;
        foreach ($grid as $rowNum => $cells) {
            if (strcasecmp($this->text($cells['B'] ?? null), 'More benchmarks') === 0) {
                $moreRow = (int) $rowNum;
                break;
            }
        }
        if ($moreRow === null) {
            return []; // Summary / Guide / Google Shopping Rejections etc.
        }

        // Header row = first row below "More benchmarks" with a label in col B.
        $headerRow = null;
        for ($i = $moreRow + 1; $i <= $maxRow; $i++) {
            if ($this->text($grid[$i]['B'] ?? null) !== '') {
                $headerRow = $i;
                break;
            }
        }
        if ($headerRow === null) {
            return [];
        }

        // Attribute headers: every non-empty header cell from col G onward.
        $attrFields = [];
        foreach ($grid[$headerRow] as $letter => $value) {
            if ($this->columnIndex($letter) < 7) {
                continue; // A..F are label / metric columns
            }
            $title = $this->text($value);
            if ($title === '') {
                continue;
            }
            $field = 'a_'.$this->slug($title);
            $attrFields[$letter] = $field;
            $columns[$field] = $columns[$field] ?? $title;
        }

        $category = $sheet->getTitle();
        [$priceRange, $condition] = $this->parseCategoryLine($this->text($grid[3]['B'] ?? null));
        $salesRank = $this->parseSalesRank($this->text($grid[2]['J'] ?? null));

        $section = $this->text($grid[$headerRow]['B'] ?? null);
        $metric = $this->text($grid[$headerRow]['F'] ?? null);

        $out = [];
        for ($i = $headerRow + 1; $i <= $maxRow; $i++) {
            $cells = $grid[$i] ?? [];
            $label = $this->text($cells['B'] ?? null);

            if ($label === '' || str_starts_with($label, '*')) {
                break; // end of the benchmarks block
            }

            $hasAttrs = false;
            foreach ($attrFields as $letter => $field) {
                if ($this->text($cells[$letter] ?? null) !== '') {
                    $hasAttrs = true;
                    break;
                }
            }

            // A row with a metric name but no attribute values starts a new section.
            if (! $hasAttrs) {
                $section = $label;
                $metric = $this->text($cells['F'] ?? null);

                continue;
            }

            $row = [
                'category' => $category,
                'condition' => $condition,
                'price_range' => $priceRange,
                'sales_rank' => $salesRank,
                'section' => $section,
                'benchmark' => $this->benchmarkType($label),
                'benchmark_label' => $label,
                'metric' => $metric,
                'value' => $this->text($cells['F'] ?? null),
            ];
            foreach ($attrFields as $letter => $field) {
                $row[$field] = $this->text($cells[$letter] ?? null);
            }
            $out[] = $row;
        }

        return $out;
    }

    private function benchmarkType(string $label): string
    {
        if (stripos($label, 'Top 10%') === 0) {
            return 'Top 10%';
        }
        if (stripos($label, 'Bottom 10%') === 0) {
            return 'Bottom 10%';
        }

        return 'Yours';
    }

    /**
     * "Category: Stands, Listings condition: New, Price range: $7 - $68" => [price range, condition]
     *
     * @return array{0: string, 1: string}
     */
    private function parseCategoryLine(string $line): array
    {
        $price = preg_match('/Price range:\s*(.+)$/i', $line, $m) ? trim($m[1]) : '';
        $condition = preg_match('/Listings condition:\s*([^,]+)/i', $line, $m) ? trim($m[1]) : '';

        return [$price, $condition];
    }

    /** "Your sales rank (GMV) in this section: 1 out of 455 sellers" => "1 / 455" */
    private function parseSalesRank(string $line): string
    {
        if (preg_match('/:\s*([\d,]+)\s+out of\s+([\d,]+)/i', $line, $m)) {
            return $m[1].' / '.$m[2];
        }

        return '';
    }

    private function text($value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(str_replace("\u{00a0}", ' ', (string) $value));
    }

    private function slug(string $title): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $title) ?? '');

        return trim($slug, '_') ?: md5($title);
    }

    private function columnIndex(string $letter): int
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($letter);
    }
}
