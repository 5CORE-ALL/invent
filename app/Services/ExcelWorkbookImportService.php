<?php

namespace App\Services;

use App\Exceptions\ExcelImportException;
use App\Models\ExcelImport;
use App\Models\ExcelWorksheet;
use App\Models\ExcelWorksheetRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\RowCellIterator;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads multi-sheet Excel workbooks into the database.
 *
 * maatwebsite/excel is locked at v1.1.5 in this project, which targets Laravel 4
 * and cannot run on Laravel 10. PhpSpreadsheet is already the application's Excel library.
 */
class ExcelWorkbookImportService
{
    private const MAX_BYTES = 52428800;

    private const MAX_COLUMNS = 500;

    private const MAX_ROWS = 200000;

    public function import(UploadedFile $file, int $userId): ExcelImport
    {
        $this->assertUpload($file);

        DB::disableQueryLog();
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $extension = strtolower($file->getClientOriginalExtension());
        $storedPath = $file->storeAs(
            'excel-imports/'.$userId,
            (string) Str::uuid().'.'.$extension,
            'local'
        );

        if ($storedPath === false) {
            throw new ExcelImportException('The workbook could not be stored.');
        }

        $absolute = Storage::disk('local')->path($storedPath);

        try {
            $type = IOFactory::identify($absolute);
            if (! in_array($type, ['Xlsx', 'Xls'], true)) {
                throw new ExcelImportException('Only .xlsx and .xls workbooks are supported.');
            }

            $infoReader = IOFactory::createReader($type);
            $sheets = $infoReader->listWorksheetInfo($absolute);
            unset($infoReader);

            if ($sheets === []) {
                throw new ExcelImportException('The workbook does not contain any worksheets.');
            }

            DB::beginTransaction();

            $import = ExcelImport::create([
                'original_filename' => $this->originalFilename($file),
                'stored_filename' => $storedPath,
                'total_worksheets' => count($sheets),
                'uploaded_by' => $userId,
            ]);

            foreach (array_values($sheets) as $index => $info) {
                $name = (string) ($info['worksheetName'] ?? ('Sheet '.($index + 1)));
                $this->importWorksheet($type, $absolute, $import, $name, $index);
            }

            DB::commit();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Storage::disk('local')->delete($storedPath);

            if ($exception instanceof ExcelImportException) {
                throw $exception;
            }

            throw new ExcelImportException(
                'The workbook could not be opened. It may be damaged or password protected.',
                0,
                $exception
            );
        }

        return $import->load('worksheets');
    }

    public function exportTo(ExcelWorksheet $worksheet, string $absolutePath): void
    {
        $directory = dirname($absolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle($worksheet->worksheet_name));

        $headers = array_values($worksheet->headers ?? []);
        if ($headers !== []) {
            $sheet->fromArray($headers, null, 'A1', true);
            $sheet->getStyle('1:1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
        }

        $excelRow = 2;
        $lastIndex = 0;
        $lastId = 0;

        do {
            $rows = ExcelWorksheetRow::query()
                ->where('excel_worksheet_id', $worksheet->id)
                ->where(function ($query) use ($lastIndex, $lastId) {
                    $query->where('row_index', '>', $lastIndex)
                        ->orWhere(function ($query) use ($lastIndex, $lastId) {
                            $query->where('row_index', $lastIndex)->where('id', '>', $lastId);
                        });
                })
                ->orderBy('row_index')
                ->orderBy('id')
                ->limit(500)
                ->get(['id', 'row_index', 'data']);

            foreach ($rows as $row) {
                $data = $row->data ?? [];
                $line = [];
                foreach ($headers as $header) {
                    $line[] = $data[$header] ?? null;
                }
                if ($line !== []) {
                    $sheet->fromArray($line, null, 'A'.$excelRow, true);
                }
                $excelRow++;
                $lastIndex = (int) $row->row_index;
                $lastId = (int) $row->id;
            }
        } while ($rows->count() === 500);

        if ($headers !== [] && count($headers) <= 30 && (int) $worksheet->total_rows <= 2000) {
            foreach (range(1, count($headers)) as $column) {
                $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
            }
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($absolutePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function importWorksheet(string $type, string $absolute, ExcelImport $import, string $name, int $index): void
    {
        $reader = IOFactory::createReader($type);
        $reader->setReadDataOnly(false);
        $reader->setReadEmptyCells(false);
        $reader->setIncludeCharts(false);
        $reader->setLoadSheetsOnly([$name]);
        $spreadsheet = $reader->load($absolute);

        try {
            $sheet = $spreadsheet->getSheetByName($name) ?? $spreadsheet->getActiveSheet();
            $this->storeSheet($import, $sheet, $name, $index);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $reader);
        }
    }

    private function storeSheet(ExcelImport $import, Worksheet $sheet, string $name, int $index): void
    {
        $worksheet = ExcelWorksheet::create([
            'excel_import_id' => $import->id,
            'worksheet_name' => mb_substr($name, 0, 191),
            'worksheet_index' => $index,
            'total_rows' => 0,
            'headers' => [],
        ]);

        $headers = [];
        $buffer = [];
        $inserted = 0;
        $now = now()->toDateTimeString();
        $headerRow = $this->detectHeaderRow($sheet, $name);

        foreach ($sheet->getRowIterator() as $row) {
            $rowIndex = $row->getRowIndex();
            if ($rowIndex > self::MAX_ROWS + 1) {
                throw new ExcelImportException('Worksheet "'.$name.'" exceeds the '.number_format(self::MAX_ROWS).' row limit.');
            }

            if ($rowIndex < $headerRow) {
                continue;
            }

            $cells = $this->readCells($row->getCellIterator(), $name);

            if ($rowIndex === $headerRow) {
                $headers = $this->headersFromRow($cells);
                continue;
            }

            if ($cells === []) {
                continue;
            }

            foreach (array_keys($cells) as $columnIndex) {
                $this->ensureHeader($headers, (int) $columnIndex);
            }

            $data = [];
            foreach ($cells as $columnIndex => $value) {
                $data[$headers[$columnIndex - 1]] = $value;
            }

            $buffer[] = [
                'excel_worksheet_id' => $worksheet->id,
                'row_index' => $rowIndex,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
                'search_text' => mb_substr(implode(' ', array_values($data)), 0, 12000),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $inserted++;

            if (count($buffer) >= 300) {
                ExcelWorksheetRow::query()->insert($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            ExcelWorksheetRow::query()->insert($buffer);
        }

        $worksheet->headers = array_values($headers);
        $worksheet->total_rows = $inserted;
        $worksheet->save();
    }

    /**
     * The real table often starts below a chart, title, or summary block.
     * Pick the earliest wide label row and ignore everything above it.
     */
    private function detectHeaderRow(Worksheet $sheet, string $name): int
    {
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $rowIndex = $row->getRowIndex();
            if ($rowIndex > 150) {
                break;
            }
            $rows[$rowIndex] = $this->readCells($row->getCellIterator(), $name);
        }

        $bestIndex = null;
        $bestScore = 0;
        foreach ($rows as $rowIndex => $cells) {
            $score = $this->headerScore($cells, $this->nextPopulatedRow($rows, $rowIndex));
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $rowIndex;
            }
        }

        if ($bestIndex !== null) {
            return $bestIndex;
        }

        foreach ($rows as $rowIndex => $cells) {
            if ($cells !== []) {
                return $rowIndex;
            }
        }

        return 1;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, string>|null
     */
    private function nextPopulatedRow(array $rows, int $rowIndex): ?array
    {
        for ($offset = 1; $offset <= 3; $offset++) {
            if (! empty($rows[$rowIndex + $offset])) {
                return $rows[$rowIndex + $offset];
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<int, string>|null  $next
     */
    private function headerScore(array $cells, ?array $next): int
    {
        if (count($cells) < 2 || $next === null) {
            return 0;
        }

        $labels = 0;
        foreach ($cells as $value) {
            if (! $this->looksLikeDataValue($value)) {
                $labels++;
            }
        }

        if ($labels < 2) {
            return 0;
        }

        $overlap = 0;
        foreach (array_keys($cells) as $column) {
            if (isset($next[$column])) {
                $overlap++;
            }
        }

        if ($overlap < 1) {
            return 0;
        }

        return ($labels * 100) + (count($cells) * 10) + $overlap;
    }

    private function looksLikeDataValue(string $value): bool
    {
        $value = trim($value);
        if (preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1) {
            return true;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2})?$/', $value) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function readCells(RowCellIterator $iterator, string $worksheetName): array
    {
        $iterator->setIterateOnlyExistingCells(true);
        $cells = [];

        foreach ($iterator as $cell) {
            $columnIndex = Coordinate::columnIndexFromString($cell->getColumn());
            if ($columnIndex > self::MAX_COLUMNS) {
                throw new ExcelImportException('Worksheet "'.$worksheetName.'" has more than '.self::MAX_COLUMNS.' columns.');
            }

            $value = $this->cellValue($cell);
            if ($value !== null) {
                $cells[$columnIndex] = $value;
            }
        }

        return $cells;
    }

    /**
     * @param  array<int, string>  $cells
     * @return array<int, string>
     */
    private function headersFromRow(array $cells): array
    {
        if ($cells === []) {
            return [];
        }

        $headers = [];
        $max = max(array_keys($cells));
        for ($column = 1; $column <= $max; $column++) {
            $label = isset($cells[$column]) ? trim($cells[$column]) : '';
            if ($label === '') {
                $label = 'Column '.Coordinate::stringFromColumnIndex($column);
            }
            $headers[] = $this->uniqueHeader(mb_substr($label, 0, 191), $headers);
        }

        return $headers;
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function ensureHeader(array &$headers, int $columnIndex): void
    {
        while (count($headers) < $columnIndex) {
            $next = count($headers) + 1;
            $headers[] = $this->uniqueHeader('Column '.Coordinate::stringFromColumnIndex($next), $headers);
        }
    }

    /**
     * @param  array<int, string>  $existing
     */
    private function uniqueHeader(string $name, array $existing): string
    {
        $name = $name !== '' ? $name : 'Column';
        $used = array_fill_keys($existing, true);
        $candidate = $name;
        $suffix = 2;

        while (isset($used[$candidate])) {
            $candidate = $name.' ('.$suffix.')';
            $suffix++;
        }

        return $candidate;
    }

    private function cellValue(Cell $cell): ?string
    {
        $raw = $cell->getValue();

        if ($raw instanceof RichText) {
            return $this->stringOrNull($raw->getPlainText());
        }

        if ($cell->isFormula()) {
            $cached = $cell->getOldCalculatedValue();
            if ($cached instanceof RichText) {
                $cached = $cached->getPlainText();
            }
            if ($cached !== null && $cached !== '') {
                $raw = $cached;
            } else {
                try {
                    $raw = $cell->getCalculatedValue();
                } catch (Throwable) {
                    return null;
                }
                if ($raw instanceof RichText) {
                    $raw = $raw->getPlainText();
                }
            }
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $formatCode = (string) $cell->getStyle()->getNumberFormat()->getFormatCode();
            if (Date::isDateTimeFormatCode($formatCode)) {
                try {
                    $date = Date::excelToDateTimeObject((float) $raw);
                    $hasTime = Date::isDateTimeFormatCode($formatCode, false);

                    return $date->format($hasTime ? 'Y-m-d H:i:s' : 'Y-m-d');
                } catch (Throwable) {
                    // Keep the numeric value when the serial cannot be converted.
                }
            }
        }

        if (is_bool($raw)) {
            return $raw ? 'TRUE' : 'FALSE';
        }

        if (is_int($raw)) {
            return (string) $raw;
        }

        if (is_float($raw)) {
            if (! is_finite($raw)) {
                return null;
            }

            if (abs($raw) >= 1000000000000 && abs($raw - round($raw)) < 0.000001) {
                return sprintf('%.0f', $raw);
            }

            $formatted = $cell->getFormattedValue();
            if (is_string($formatted) && trim($formatted) !== '' && ! preg_match('/e\+/i', $formatted)) {
                return trim($formatted);
            }

            $text = rtrim(rtrim(sprintf('%.10F', $raw), '0'), '.');

            return $text === '-0' ? '0' : $text;
        }

        return $this->stringOrNull(is_string($raw) ? $raw : (string) $raw);
    }

    private function stringOrNull(string $value): ?string
    {
        $value = str_replace("\0", '', $value);

        return trim($value) === '' ? null : $value;
    }

    private function assertUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new ExcelImportException('The upload did not finish. Please try again.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            throw new ExcelImportException('The file must be an Excel workbook (.xlsx or .xls).');
        }

        $size = $file->getSize();
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new ExcelImportException('The file must be a non-empty Excel workbook of 50 MB or smaller.');
        }

        $path = $file->getRealPath();
        $handle = $path !== false ? fopen($path, 'rb') : false;
        if ($handle === false) {
            throw new ExcelImportException('The uploaded file could not be read.');
        }

        $magic = fread($handle, 8) ?: '';
        fclose($handle);

        if ($extension === 'xlsx' && ! str_starts_with($magic, 'PK')) {
            throw new ExcelImportException('The .xlsx file is damaged or is not a real Excel workbook.');
        }

        if ($extension === 'xls' && ! str_starts_with($magic, "\xD0\xCF\x11\xE0")) {
            throw new ExcelImportException('The .xls file is damaged or is not a real Excel workbook.');
        }
    }

    private function originalFilename(UploadedFile $file): string
    {
        $name = str_replace(['\\', '/'], '', $file->getClientOriginalName());
        $name = trim($name);

        return mb_substr($name !== '' ? $name : 'workbook.xlsx', 0, 255);
    }

    private function sheetTitle(string $name): string
    {
        $title = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]/u', ' ', $name) ?? 'Sheet';
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        $title = mb_substr($title, 0, 31);

        return $title !== '' ? $title : 'Sheet';
    }
}
