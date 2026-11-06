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
    public const CACHE_KEY = 'excel_category_by_item_id_v4';

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
     * Put `excel_category` on every grid row (and its `_children`) from the account's uploaded report,
     * matched by eBay item id. Blank when there is no match / no upload yet.
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, mixed>
     */
    public function attachToRows(array $rows, string $account): array
    {
        $map = $this->map($account);
        $walk = function (array $list) use (&$walk, $map): array {
            foreach ($list as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $key = self::normalizeItemId($row['eBay_item_id'] ?? $row['listing_id'] ?? '');
                $row['excel_category'] = $key !== '' ? ($map[$key] ?? '') : '';
                if (isset($row['_children']) && is_array($row['_children'])) {
                    $row['_children'] = $walk($row['_children']);
                }
                $list[$i] = $row;
            }

            return $list;
        };

        return $walk($rows);
    }

    /**
     * @return array<string, string> normalized item id => category
     */
    public function map(string $account = 'ebay1'): array
    {
        $latestId = (int) ExcelWorksheet::query()->forAccount($account)->max('id');
        if ($latestId === 0) {
            return [];
        }

        $key = self::CACHE_KEY.($account === 'ebay1' ? '' : ':'.$account).':'.$latestId;
        $cached = Cache::get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        try {
            $map = $this->build(null, $account);
        } catch (\Throwable $e) {
            Log::warning('ExcelCategoryLookupService: '.$e->getMessage());

            return [];
        }

        // Never cache an empty result, so a failed/empty build is retried on the next request.
        if ($map !== []) {
            Cache::put($key, $map, 600);
        }

        return $map;
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
     * Build the map without caching or swallowing errors (used by excel:category-debug).
     *
     * @param  (callable(string, string, int, int, array<int, string>): void)|null  $onSheet
     * @return array<string, string>
     */
    public function build(?callable $onSheet = null, string $account = 'ebay1'): array
    {
        // Only the most recently uploaded workbook is used.
        $importId = ExcelWorksheet::query()->forAccount($account)->orderByDesc('id')->value('excel_import_id');
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

            $before = count($map);
            $rowCount = 0;
            $sampleKeys = [];
            ExcelWorksheetRow::query()
                ->where('excel_worksheet_id', $worksheet->id)
                ->select(['id', 'data'])
                ->chunkById(2000, function ($rows) use (&$map, &$rowCount, &$sampleKeys, $itemIdHeader, $category) {
                    foreach ($rows as $row) {
                        $rowCount++;
                        $data = is_array($row->data) ? $row->data : (json_decode((string) $row->data, true) ?: []);
                        if ($sampleKeys === []) {
                            $sampleKeys = array_slice(array_map('strval', array_keys($data)), 0, 12);
                        }
                        $itemId = self::normalizeItemId($data[$itemIdHeader] ?? '');
                        if ($itemId !== '' && ! isset($map[$itemId])) {
                            $map[$itemId] = $category;
                        }
                    }
                });

            if ($onSheet) {
                $onSheet($category, $itemIdHeader, $rowCount, count($map) - $before, $sampleKeys);
            }
        }

        return $map;
    }
}
