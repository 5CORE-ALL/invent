<?php

namespace App\Services\Wayfair;

use App\Http\Controllers\MarketPlace\WayfairController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WayfairPriceFileGenerator
{
    /** Row 2 machine headers on the Partner Home Pricing sheet. */
    public const REQUIRED_KEYS = ['SupplierPartNumber', 'BaseCost'];

    /**
     * Rows from the existing Wayfair pricing page. Does not recalculate prices.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pricingRows(): array
    {
        $response = app(WayfairController::class)->getWayfairPricingData(
            Request::create('/wayfair/pricing-data', 'GET')
        );
        $payload = json_decode($response->getContent(), true);
        if (! is_array($payload)) {
            throw new \RuntimeException('Wayfair pricing data was not a list.');
        }
        if (isset($payload['error'])) {
            throw new \RuntimeException((string) $payload['error']);
        }

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, float> sku => price already calculated by the pricing page
     */
    public function priceMapFromRows(array $rows, ?string $field = null): array
    {
        $field = $field ?: (string) config('wayfair_upload.price_field', 'sprice');
        $map = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! empty($row['is_parent'])) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            if (! isset($row[$field]) || ! is_numeric($row[$field])) {
                continue;
            }
            $price = round((float) $row[$field], 2);
            if ($price <= 0) {
                continue;
            }
            $map[$sku] = $price;
        }

        ksort($map);

        return $map;
    }

    /**
     * Copy the Partner Home export and fill New Base Cost only.
     * Gray pre-filled columns are left unchanged.
     *
     * @param  array<string, float|int|string>  $prices  calculated S PRC keyed by our SKU or supplier part number
     */
    public function write(array $prices, ?string $filename = null): array
    {
        $template = (string) config('wayfair_upload.template');
        if ($template === '' || ! is_file($template)) {
            throw new \RuntimeException('Wayfair cost-change template is missing. Export Product Spreadsheet from Partner Home and set WAYFAIR_COST_CHANGE_TEMPLATE.');
        }

        $filename = $filename ?: 'wayfair_price_'.now()->format('Y-m-d_H-i-s').'.xlsx';
        if (! str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }
        $relativeDir = trim((string) config('wayfair_upload.outgoing_directory', 'wayfair/outgoing'), '/');
        $relative = $relativeDir.'/'.$filename;
        Storage::disk('local')->makeDirectory($relativeDir);
        $absolute = Storage::disk('local')->path($relative);

        $spreadsheet = IOFactory::load($template);
        $sheet = $spreadsheet->getSheetByName('Pricing');
        if ($sheet === null) {
            throw new \RuntimeException('Wayfair template has no Pricing sheet.');
        }

        $columns = $this->headerColumns($sheet);
        foreach (self::REQUIRED_KEYS as $key) {
            if (! isset($columns[$key])) {
                throw new \RuntimeException('Wayfair template is missing the '.$key.' column.');
            }
        }

        $lookup = [];
        foreach ($prices as $sku => $price) {
            $lookup[strtoupper(trim((string) $sku))] = round((float) $price, 2);
        }

        $partCol = $columns['SupplierPartNumber'];
        $skuCol = $columns['skus'] ?? null;
        $statusCol = $columns['Status'] ?? null;
        $currentCol = $columns['CurrentBaseCost'] ?? null;
        $newCol = $columns['BaseCost'];
        $filled = [];

        $highest = (int) $sheet->getHighestRow();
        for ($row = 5; $row <= $highest; $row++) {
            $part = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($partCol).$row)->getValue());
            if ($part === '') {
                continue;
            }
            $status = strtolower(trim((string) ($statusCol ? $sheet->getCell(Coordinate::stringFromColumnIndex($statusCol).$row)->getValue() : '')));
            if ($status !== '' && ! str_starts_with($status, 'live')) {
                continue;
            }

            $keys = [strtoupper($part)];
            if ($skuCol) {
                $rawSkus = (string) $sheet->getCell(Coordinate::stringFromColumnIndex($skuCol).$row)->getValue();
                foreach (preg_split('/\s*,\s*/', $rawSkus) ?: [] as $sku) {
                    $sku = strtoupper(trim($sku));
                    if ($sku !== '') {
                        $keys[] = $sku;
                    }
                }
            }

            $matched = [];
            foreach (array_unique($keys) as $key) {
                if (isset($lookup[$key])) {
                    $matched[] = $lookup[$key];
                }
            }
            $matched = array_values(array_unique($matched));
            if (count($matched) !== 1) {
                continue;
            }
            $price = $matched[0];
            if ($currentCol) {
                $current = $sheet->getCell(Coordinate::stringFromColumnIndex($currentCol).$row)->getValue();
                if (is_numeric($current) && (int) round(((float) $current) * 100) === (int) round($price * 100)) {
                    continue;
                }
            }

            $sheet->setCellValue(Coordinate::stringFromColumnIndex($newCol).$row, $price);
            $filled[$part] = number_format($price, 2, '.', '');
        }

        $spreadsheet->getProperties()->setCustomProperty('generated', 'wayfair-price-upload');
        (new Xlsx($spreadsheet))->save($absolute);
        $spreadsheet->disconnectWorksheets();

        ksort($filled);
        $canonical = '';
        foreach ($filled as $part => $price) {
            $canonical .= $part.'|'.$price."\n";
        }

        return [
            'filename' => $filename,
            'file_path' => $relative,
            'absolute_path' => $absolute,
            'file_type' => 'xlsx',
            'file_sha256' => hash('sha256', $canonical),
            'filled' => count($filled),
        ];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<string, string>}
     */
    public function read(string $absolutePath): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new \RuntimeException('Wayfair price file is not readable.');
        }

        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getSheetByName('Pricing');
        if ($sheet === null) {
            $spreadsheet->disconnectWorksheets();

            return ['headers' => [], 'rows' => []];
        }

        $columns = $this->headerColumns($sheet);
        $rows = [];
        if (isset($columns['SupplierPartNumber'], $columns['BaseCost'])) {
            $partCol = Coordinate::stringFromColumnIndex($columns['SupplierPartNumber']);
            $newCol = Coordinate::stringFromColumnIndex($columns['BaseCost']);
            $highest = (int) $sheet->getHighestRow();
            for ($row = 5; $row <= $highest; $row++) {
                $part = trim((string) $sheet->getCell($partCol.$row)->getValue());
                $price = $sheet->getCell($newCol.$row)->getCalculatedValue();
                if ($part === '' || $price === null || $price === '') {
                    continue;
                }
                $rows[$part] = is_numeric($price)
                    ? number_format((float) $price, 2, '.', '')
                    : trim((string) $price);
            }
        }
        $spreadsheet->disconnectWorksheets();

        return [
            'headers' => array_keys($columns),
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, int> machine header => 1-based column index
     */
    private function headerColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $columns = [];
        $highest = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($col = 1; $col <= $highest; $col++) {
            $key = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col).'2')->getValue());
            if ($key !== '') {
                $columns[$key] = $col;
            }
        }

        return $columns;
    }
}
