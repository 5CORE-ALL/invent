<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Temu2CampaignReport;
use App\Support\TemuGoodsIdHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

/**
 * Temu 2 Ads — standalone raw upload/view of temu2_campaign_reports.
 * Shows uploaded rows as-is (no ProductMaster / Shopify / pricing matching).
 */
class Temu2AdsController extends Controller
{
    public function index()
    {
        return view('campaign.temu2.temu2-ads');
    }

    /**
     * Return all raw rows from temu2_campaign_reports for Tabulator.
     */
    public function getTemu2AdsData(Request $request)
    {
        $query = Temu2CampaignReport::query()->orderByDesc('id');

        $range = $request->query('report_range');
        if (in_array($range, ['L7', 'L30', 'L60'], true)) {
            $query->where('report_range', $range);
        }

        $records = $query->get();
        $spendSum = round((float) $records->sum(fn (Temu2CampaignReport $r) => (float) ($r->spend ?? 0)), 2);

        $rows = $records->map(function (Temu2CampaignReport $r) {
            return [
                'id' => $r->id,
                'goods_name' => $r->goods_name,
                'goods_id' => $r->goods_id,
                'sku' => $r->sku,
                'report_range' => $r->report_range,
                'spend' => $r->spend !== null ? (float) $r->spend : null,
                'net_total_cost' => $r->net_total_cost !== null ? (float) $r->net_total_cost : null,
                'base_price_sales' => $r->base_price_sales !== null ? (float) $r->base_price_sales : null,
                'roas' => $r->roas !== null ? (float) $r->roas : null,
                'acos_ad' => $r->acos_ad !== null ? (float) $r->acos_ad : null,
                'cost_per_transaction' => $r->cost_per_transaction !== null ? (float) $r->cost_per_transaction : null,
                'sub_orders' => $r->sub_orders !== null ? (int) $r->sub_orders : null,
                'items' => $r->items !== null ? (int) $r->items : null,
                'impressions' => $r->impressions !== null ? (int) $r->impressions : null,
                'clicks' => $r->clicks !== null ? (int) $r->clicks : null,
                'ctr' => $r->ctr !== null ? (float) $r->ctr : null,
                'cvr' => $r->cvr !== null ? (float) $r->cvr : null,
                'add_to_cart_number' => $r->add_to_cart_number !== null ? (int) $r->add_to_cart_number : null,
                'net_declared_sales' => $r->net_declared_sales !== null ? (float) $r->net_declared_sales : null,
                'net_roas' => $r->net_roas !== null ? (float) $r->net_roas : null,
                'net_acos_ad' => $r->net_acos_ad !== null ? (float) $r->net_acos_ad : null,
                'net_cost_per_transaction' => $r->net_cost_per_transaction !== null ? (float) $r->net_cost_per_transaction : null,
                'net_orders' => $r->net_orders !== null ? (int) $r->net_orders : null,
                'net_number_pieces' => $r->net_number_pieces !== null ? (int) $r->net_number_pieces : null,
                'updated_at' => optional($r->updated_at)->toDateTimeString(),
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'total' => $rows->count(),
            'spend_sum' => $spendSum,
        ]);
    }

    /**
     * Upload Temu 2 ads export (xlsx/xls/csv/tsv/txt) into temu2_campaign_reports.
     * Replaces all rows for the selected report_range.
     */
    public function uploadCampaignReport(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file',
                'report_range' => 'required|in:L7,L30,L60',
            ]);

            $file = $request->file('file');
            $reportRange = $request->input('report_range');
            $ext = strtolower($file->getClientOriginalExtension());

            $isTsv = in_array($ext, ['txt', 'tsv', ''], true)
                || $this->detectTsv($file->getPathname());

            if ($isTsv) {
                [$headers, $dataRows] = $this->parseTsvFile($file->getPathname());
                $sheet = null;
            } else {
                $spreadsheet = IOFactory::load($file->getPathname());
                $sheet = $spreadsheet->getActiveSheet();
                $rawHeaders = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1', null, true, false)[0] ?? [];
                $headers = array_map(fn ($h) => is_string($h) ? trim($h) : $h, $rawHeaders);
                $dataRows = null;
            }

            $goodsIdColIdx = array_search('Goods ID', $headers, true);
            if ($goodsIdColIdx === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'File must contain a column named exactly "Goods ID".',
                ], 422);
            }
            $skuColIdx = array_search('SKU', $headers, true);

            $normalizeCellValue = function ($value) {
                if ($value instanceof RichText) {
                    return trim($value->getPlainText());
                }
                if (is_object($value) && method_exists($value, '__toString')) {
                    return trim((string) $value);
                }
                if (is_string($value)) {
                    return trim($value);
                }

                return $value;
            };
            $parseCurrency = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if ($value === null || $value === '' || $value === '∞') {
                    return null;
                }

                return floatval(str_replace(['$', ','], '', (string) $value));
            };
            $parsePercent = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if ($value === null || $value === '' || $value === '∞') {
                    return null;
                }

                return floatval(str_replace('%', '', (string) $value));
            };
            $parseNumber = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if ($value === null || $value === '' || $value === '∞') {
                    return 0;
                }

                return floatval(str_replace([',', '%', '$'], '', (string) $value));
            };
            $col = function (array $rowData, array $aliases) {
                foreach ($aliases as $a) {
                    if (array_key_exists($a, $rowData) && $rowData[$a] !== null && $rowData[$a] !== '') {
                        return $rowData[$a];
                    }
                }

                return null;
            };

            $imported = 0;
            $skipped = 0;
            $rowErrors = 0;
            $firstRowError = null;
            $numCols = count($headers);
            $highestRow = 0;
            $allRows = $isTsv ? $dataRows : null;
            if (! $isTsv) {
                $highestRow = (int) $sheet->getHighestDataRow();
            }

            DB::beginTransaction();
            try {
                Temu2CampaignReport::where('report_range', $reportRange)->delete();

                $iterateFn = function () use ($isTsv, $allRows, &$sheet, $highestRow, $normalizeCellValue, $numCols) {
                    if ($isTsv) {
                        foreach ($allRows as $row) {
                            yield $row;
                        }
                    } else {
                        for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
                            $raw = [];
                            for ($c = 1; $c <= $numCols; $c++) {
                                $raw[] = $normalizeCellValue($sheet->getCell(Coordinate::stringFromColumnIndex($c).$rowNum)->getValue());
                            }
                            yield ['_rowNum' => $rowNum, '_raw' => $raw];
                        }
                    }
                };

                foreach ($iterateFn() as $entry) {
                    if ($isTsv) {
                        $row = $entry;
                        if (stripos((string) ($row[0] ?? ''), 'Total') !== false) {
                            $skipped++;
                            continue;
                        }
                        $rowNum = null;
                    } else {
                        $rowNum = $entry['_rowNum'];
                        $row = $entry['_raw'];
                        $firstCell = $row[0] ?? null;
                        if ($firstCell !== null && $firstCell !== '' && stripos((string) $firstCell, 'Total') !== false) {
                            $skipped++;
                            continue;
                        }
                    }

                    if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                        $skipped++;
                        continue;
                    }

                    $rowData = @array_combine($headers, array_pad(array_slice($row, 0, $numCols), $numCols, null));
                    if (! is_array($rowData)) {
                        $skipped++;
                        continue;
                    }

                    if ($isTsv) {
                        $rawGoodsId = trim((string) ($row[$goodsIdColIdx] ?? ''));
                        $goodsIdNormalized = $rawGoodsId !== '' ? TemuGoodsIdHelper::normalizeKey($rawGoodsId) : null;
                    } else {
                        $goodsCell = $sheet->getCell(Coordinate::stringFromColumnIndex($goodsIdColIdx + 1).$rowNum);
                        $goodsIdNormalized = TemuGoodsIdHelper::fromSpreadsheetCell($goodsCell);
                    }

                    if (! $goodsIdNormalized) {
                        $skipped++;
                        continue;
                    }

                    $skuValue = $skuColIdx !== false
                        ? trim((string) ($row[$skuColIdx] ?? ''))
                        : null;

                    try {
                        Temu2CampaignReport::create([
                            'goods_name' => $rowData['Goods name'] ?? null,
                            'goods_id' => $goodsIdNormalized,
                            'sku' => $skuValue !== '' ? $skuValue : null,
                            'report_range' => $reportRange,
                            'spend' => $parseCurrency($col($rowData, ['Spend'])),
                            'base_price_sales' => $parseCurrency($col($rowData, ['Base Price Sales (Ad)', 'Base Price Sales (Overall)', 'Base price sales'])),
                            'roas' => $parseNumber($col($rowData, ['ROAS (Ad)', 'ROAS (Overall)', 'ROAS']) ?? 0),
                            'acos_ad' => $parsePercent($col($rowData, ['ACOS (Ad)', 'ACOS (Overall)', 'ACOS(AD)'])),
                            'cost_per_transaction' => $parseCurrency($col($rowData, ['Cost Per Order (Ad)', 'Cost Per Order (Overall)', 'Cost per transaction'])),
                            'sub_orders' => (int) str_replace(',', '', (string) ($col($rowData, ['Sub Order Count (Ad)', 'Sub Order Count (Overall)', 'Sub-Orders']) ?? 0)),
                            'items' => (int) str_replace(',', '', (string) ($col($rowData, ['Item Quantity (Ad)', 'Items (Overall)', 'Items']) ?? 0)),
                            'net_total_cost' => $parseCurrency($col($rowData, ['Net total cost'])),
                            'net_declared_sales' => $parseCurrency($col($rowData, ['Net Base Price Sales (Ad)', 'Net Base Price Sales (Overall)', 'Net declared sales'])),
                            'net_roas' => $parseNumber($col($rowData, ['Net ROAS (Ad)', 'Net ROAS (Overall)', 'Net advertising return on investment (ROAS)']) ?? 0),
                            'net_acos_ad' => $parsePercent($col($rowData, ['Net ACOS (Ad)', 'Net ACOS (Overall)', 'Net advertising cost ratio (advertising)'])),
                            'net_cost_per_transaction' => $parseCurrency($col($rowData, ['Net Cost Per Order (Ad)', 'Net Cost Per Order (Overall)', 'Net cost per transaction'])),
                            'net_orders' => (int) str_replace(',', '', (string) ($col($rowData, ['Net Sub Order Count (Ad)', 'Net Sub Order Count (Overall)', 'Net Orders']) ?? 0)),
                            'net_number_pieces' => (int) str_replace(',', '', (string) ($col($rowData, ['Net Item Quantity (Ad)', 'Net Items (Overall)', 'Net number of pieces']) ?? 0)),
                            'impressions' => (int) str_replace(',', '', (string) ($col($rowData, ['Impressions (Ad)', 'Impressions (Overall)', 'Impressions']) ?? 0)),
                            'clicks' => (int) str_replace(',', '', (string) ($col($rowData, ['Clicks (Ad)', 'Clicks (Overall)', 'Clicks']) ?? 0)),
                            'ctr' => $parsePercent($col($rowData, ['Click Through Rate (Ad)', 'CTR (Overall)', 'CTR'])),
                            'cvr' => $parsePercent($col($rowData, ['Conversion Rate (Ad)', 'CVR (Overall)', 'Conversion Rate (CVR)'])),
                            'add_to_cart_number' => (int) str_replace(',', '', (string) ($col($rowData, ['Add To Cart (Ad)', 'Add to cart count (Overall)', 'Add-to-cart number']) ?? 0)),
                        ]);
                        $imported++;
                    } catch (\Exception $e) {
                        $skipped++;
                        $rowErrors++;
                        if ($firstRowError === null) {
                            $firstRowError = $e->getMessage();
                        }
                        Log::warning('Temu 2 ads upload row failed: '.$e->getMessage());
                    }
                }

                if ($imported === 0) {
                    DB::rollBack();
                    $msg = "Imported 0 rows for {$reportRange}. Existing {$reportRange} data was kept.";
                    if ($firstRowError) {
                        $msg .= " First row error: {$firstRowError}";
                    } else {
                        $msg .= ' All rows were skipped (check file format/headers).';
                    }

                    return response()->json([
                        'success' => false,
                        'message' => $msg,
                        'imported' => 0,
                        'skipped' => $skipped,
                        'row_errors' => $rowErrors,
                    ], 422);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$imported} records for {$reportRange}",
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'row_errors' => $rowErrors,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error uploading Temu 2 campaign report: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error uploading file: '.$e->getMessage(),
            ], 500);
        }
    }

    private function detectTsv(string $path): bool
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return false;
        }
        $line = fgets($handle);
        fclose($handle);

        return $line !== false && substr_count($line, "\t") >= 3;
    }

    private function parseTsvFile(string $path): array
    {
        $headers = [];
        $dataRows = [];
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [[], []];
        }

        $lineNum = 0;
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }

            $cols = array_map('trim', explode("\t", $line));

            if ($lineNum === 0) {
                $headers = $cols;
            } else {
                if (stripos($cols[0] ?? '', 'Total') !== false && $lineNum === 1) {
                    $lineNum++;
                    continue;
                }
                $dataRows[] = $cols;
            }
            $lineNum++;
        }
        fclose($handle);

        return [$headers, $dataRows];
    }
}
