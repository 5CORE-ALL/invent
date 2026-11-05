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
     * Pick the SKU and Category headers (exact names first, then any header containing the word).
     *
     * @param  array<int, mixed>  $headers
     * @return array{0: ?string, 1: ?string}
     */
    public function detectColumns(array $headers): array
    {
        $keyed = [];
        foreach ($headers as $header) {
            $keyed[(string) $header] = preg_replace('/[^a-z0-9]/', '', strtolower((string) $header));
        }

        $pick = function (array $exact, string $contains) use ($keyed): ?string {
            foreach ($keyed as $header => $key) {
                if (in_array($key, $exact, true)) {
                    return (string) $header;
                }
            }
            foreach ($keyed as $header => $key) {
                if (str_contains($key, $contains)) {
                    return (string) $header;
                }
            }

            return null;
        };

        return [
            $pick(self::SKU_HEADERS, 'sku'),
            $pick(self::CATEGORY_HEADERS, 'category'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function build(): array
    {
        $worksheets = ExcelWorksheet::query()->orderByDesc('id')->get();

        foreach ($worksheets as $worksheet) {
            [$skuHeader, $categoryHeader] = $this->detectColumns((array) ($worksheet->headers ?? []));

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
