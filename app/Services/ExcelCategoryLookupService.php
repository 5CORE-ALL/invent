<?php

namespace App\Services;

use App\Models\ExcelWorksheet;
use App\Models\ExcelWorksheetRow;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Builds an MPN => Category map from the workbook uploaded in /excel.
 *
 * In that workbook every worksheet tab is a category (e.g. "Stands & Hangers",
 * "Stools & Benches"), and each row carries an MPN. The category of a SKU is the
 * name of the worksheet whose MPN column contains that SKU. Non-category tabs
 * (Summary, Guide, Google Shopping Rejections) are ignored. No match => blank.
 */
class ExcelCategoryLookupService
{
    public const CACHE_KEY = 'excel_category_by_mpn_v2';

    private const MPN_HEADERS = ['mpn', 'mpnnumber', 'manufacturerpartnumber', 'partnumber'];

    /** Worksheet tabs that are not categories. */
    private const IGNORED_SHEETS = ['summary', 'guide', 'googleshoppingrejections'];

    public static function normalizeSku(?string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', (string) $sku);

        return strtoupper(trim(preg_replace('/\s+/u', ' ', $sku) ?? ''));
    }

    /**
     * @return array<string, string> normalized MPN/SKU => category
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
     * Detect the MPN header of a worksheet.
     *
     * @param  array<int, mixed>  $headers
     */
    public function detectMpnHeader(array $headers): ?string
    {
        $keyed = [];
        foreach ($headers as $header) {
            $keyed[(string) $header] = preg_replace('/[^a-z0-9]/', '', strtolower((string) $header));
        }

        foreach ($keyed as $header => $key) {
            if (in_array($key, self::MPN_HEADERS, true)) {
                return (string) $header;
            }
        }
        foreach ($keyed as $header => $key) {
            if (str_contains($key, 'mpn')) {
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

            $mpnHeader = $this->detectMpnHeader((array) ($worksheet->headers ?? []));
            if ($mpnHeader === null) {
                continue;
            }

            ExcelWorksheetRow::query()
                ->where('excel_worksheet_id', $worksheet->id)
                ->select(['id', 'data'])
                ->orderBy('row_index')
                ->chunkById(2000, function ($rows) use (&$map, $mpnHeader, $category) {
                    foreach ($rows as $row) {
                        $data = is_array($row->data) ? $row->data : (json_decode((string) $row->data, true) ?: []);
                        $mpn = self::normalizeSku((string) ($data[$mpnHeader] ?? ''));
                        if ($mpn !== '' && ! isset($map[$mpn])) {
                            $map[$mpn] = $category;
                        }
                    }
                });
        }

        return $map;
    }
}
