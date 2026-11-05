<?php

namespace App\Services;

use App\Models\ExcelWorksheet;
use App\Models\ExcelWorksheetRow;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Builds an Item ID => Category map from the workbook uploaded in /excel.
 *
 * In that workbook every worksheet tab is a category (e.g. "Stands & Hangers",
 * "Stools & Benches"), and each row carries an eBay Item ID. The category of a listing is the
 * name of the worksheet whose Item ID column contains that item id. Non-category tabs
 * (Summary, Guide, Google Shopping Rejections) are ignored. No match => blank.
 */
class ExcelCategoryLookupService
{
    public const CACHE_KEY = 'excel_category_by_item_id_v3';

    private const ITEM_ID_HEADERS = ['itemid', 'ebayitemid', 'itemnumber', 'listingid', 'ebayitemnumber'];

    /** Worksheet tabs that are not categories. */
    private const IGNORED_SHEETS = ['summary', 'guide', 'googleshoppingrejections'];

    /**
     * Normalize an eBay item id so "123456789012", "123456789012.0" and " 123456789012 "
     * (Excel often stores ids as numbers) compare equal.
     */
    public static function normalizeItemId($value): string
    {
        $value = trim(str_replace("\u{00a0}", ' ', (string) $value));
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d+)\.0+$/', $value, $m)) {
            return $m[1];
        }
        if (is_numeric($value) && stripos($value, 'e') !== false) {
            return number_format((float) $value, 0, '', '');
        }

        return strtoupper($value);
    }

    /**
     * @return array<string, string> normalized item id => category
     */
    public function map(): array
    {
        $latestId = (int) ExcelWorksheet::query()->max('id');
        if ($latestId === 0) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY.':'.$latestId, 600, function () {
            try {
                return $this->build();
            } catch (\Throwable $e) {
                Log::warning('ExcelCategoryLookupService: '.$e->getMessage());

                return [];
            }
        });
    }

    /**
     * Detect the Item ID header of a worksheet.
     *
     * @param  array<int, mixed>  $headers
     */
    public function detectItemIdHeader(array $headers): ?string
    {
        $keyed = [];
        foreach ($headers as $header) {
            $keyed[(string) $header] = preg_replace('/[^a-z0-9]/', '', strtolower((string) $header));
        }

        foreach ($keyed as $header => $key) {
            if (in_array($key, self::ITEM_ID_HEADERS, true)) {
                return (string) $header;
            }
        }
        foreach ($keyed as $header => $key) {
            if (str_contains($key, 'itemid')) {
                return (string) $header;
            }
        }

        return null;
    }

    public function isCategorySheet(string $name): bool
    {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower($name));

        return $key !== '' && ! in_array($key, self::IGNORED_SHEETS, true);
    }

    /**
     * @return array<string, string>
     */
    private function build(): array
    {
        // Only the most recently uploaded workbook is used.
        $importId = ExcelWorksheet::query()->orderByDesc('id')->value('excel_import_id');
        if (! $importId) {
            return [];
        }

        $worksheets = ExcelWorksheet::query()
            ->where('excel_import_id', $importId)
            ->orderBy('worksheet_index')
            ->get();

        $map = [];
        foreach ($worksheets as $worksheet) {
            $category = trim((string) $worksheet->worksheet_name);
            if (! $this->isCategorySheet($category)) {
                continue;
            }

            $itemIdHeader = $this->detectItemIdHeader((array) ($worksheet->headers ?? []));
            if ($itemIdHeader === null) {
                continue;
            }

            ExcelWorksheetRow::query()
                ->where('excel_worksheet_id', $worksheet->id)
                ->select(['id', 'data'])
                ->orderBy('row_index')
                ->chunkById(2000, function ($rows) use (&$map, $itemIdHeader, $category) {
                    foreach ($rows as $row) {
                        $data = is_array($row->data) ? $row->data : (json_decode((string) $row->data, true) ?: []);
                        $itemId = self::normalizeSku((string) ($data[$itemIdHeader] ?? ''));
                        if ($itemId !== '' && ! isset($map[$itemId])) {
                            $map[$itemId] = $category;
                        }
                    }
                });
        }

        return $map;
    }
}
