<?php

namespace App\Services\Wayfair;

use App\Http\Controllers\MarketPlace\WayfairController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WayfairPriceFileGenerator
{
    public const HEADERS = ['Supplier Part Number', 'New Base Cost'];

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
     * @param  array<string, float|int|string>  $prices
     */
    public function write(array $prices, ?string $filename = null): array
    {
        $filename = $filename ?: 'wayfair_price_'.now()->format('Y-m-d_H-i-s').'.csv';
        $relativeDir = trim((string) config('wayfair_upload.outgoing_directory', 'wayfair/outgoing'), '/');
        $relative = $relativeDir.'/'.$filename;
        Storage::disk('local')->makeDirectory($relativeDir);

        $handle = fopen(Storage::disk('local')->path($relative), 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Could not write the Wayfair price file.');
        }
        fputcsv($handle, self::HEADERS);
        foreach ($prices as $sku => $price) {
            fputcsv($handle, [$sku, number_format((float) $price, 2, '.', '')]);
        }
        fclose($handle);

        $absolute = Storage::disk('local')->path($relative);

        return [
            'filename' => $filename,
            'file_path' => $relative,
            'absolute_path' => $absolute,
            'file_type' => 'csv',
            'file_sha256' => hash_file('sha256', $absolute) ?: '',
        ];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<string, string>}
     */
    public function read(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Wayfair price file is not readable.');
        }
        $headers = fgetcsv($handle) ?: [];
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === false) {
                continue;
            }
            $sku = trim((string) ($line[0] ?? ''));
            if ($sku === '') {
                continue;
            }
            $rows[$sku] = trim((string) ($line[1] ?? ''));
        }
        fclose($handle);

        return [
            'headers' => array_map(static fn ($h) => trim((string) $h), $headers),
            'rows' => $rows,
        ];
    }
}
