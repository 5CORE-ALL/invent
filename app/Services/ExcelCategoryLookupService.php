<?php

namespace App\Services;

use App\Models\ExcelWorksheet;
use App\Models\ExcelWorksheetRow;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Builds a SKU => Category map from the data uploaded in /excel (Excel Data Manager).
 *
 * The most recently imported worksheet that has both a SKU column and a Category
 * column is used. SKUs with no match are simply absent from the map (blank category).
 */
class ExcelCategoryLookupService
{
    public const CACHE_KEY = 'excel_category_by_sku_v1';

    private const SKU_HEADERS = ['sku', 'childsku', 'itemsku', 'sellersku', 'productsku'];

    private const CATEGORY_HEADERS = ['category'];

    public static function normalizeSku(?string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', (string) $sku);

        return strtoupper(trim(preg_replace('/\s+/u', ' ', $sku) ?? ''));
    }

    /**
     * @return array<string, string> normalized SKU => category
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
     * @return array<string, string>
     */
    private function build(): array
    {
        $worksheets = ExcelWorksheet::query()->orderByDesc('id')->get();

        foreach ($worksheets as $worksheet) {
            $skuHeader = null;
            $categoryHeader = null;

            foreach ((array) ($worksheet->headers ?? []) as $header) {
                $key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $header));
                if ($skuHeader === null && in_array($key, self::SKU_HEADERS, true)) {
                    $skuHeader = $header;
                }
                if ($categoryHeader === null && in_array($key, self::CATEGORY_HEADERS, true)) {
                    $categoryHeader = $header;
                }
            }

            if ($skuHeader === null || $categoryHeader === null) {
                continue;
            }

            $map = [];
            ExcelWorksheetRow::query()
                ->where('excel_worksheet_id', $worksheet->id)
                ->select(['id', 'data'])
                ->orderBy('row_index')
                ->chunkById(2000, function ($rows) use (&$map, $skuHeader, $categoryHeader) {
                    foreach ($rows as $row) {
                        $data = is_array($row->data) ? $row->data : (json_decode((string) $row->data, true) ?: []);
                        $sku = self::normalizeSku((string) ($data[$skuHeader] ?? ''));
                        $category = trim((string) ($data[$categoryHeader] ?? ''));
                        if ($sku !== '' && $category !== '' && ! isset($map[$sku])) {
                            $map[$sku] = $category;
                        }
                    }
                });

            return $map;
        }

        return [];
    }
}
