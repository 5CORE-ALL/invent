<?php

namespace App\Services\Wayfair;

class WayfairPriceFileValidator
{
    /**
     * @param  array{headers: array<int, string>, rows: array<string, string>}  $parsed
     * @return array<int, string>
     */
    public function errors(array $parsed): array
    {
        $errors = [];
        $headers = $parsed['headers'] ?? [];
        if ($headers !== WayfairPriceFileGenerator::HEADERS) {
            $errors[] = 'File does not match the Wayfair price template (Supplier Part Number, New Base Cost).';
        }

        $rows = $parsed['rows'] ?? [];
        if ($rows === []) {
            $errors[] = 'Empty file is rejected. No SKU prices to upload.';

            return $errors;
        }

        $seen = [];
        foreach ($rows as $sku => $rawPrice) {
            $key = strtoupper(trim((string) $sku));
            if ($key === '') {
                $errors[] = 'A row is missing Supplier Part Number.';
                continue;
            }
            if (isset($seen[$key])) {
                $errors[] = 'Duplicate SKU: '.$sku;
            }
            $seen[$key] = true;

            if (! is_numeric($rawPrice)) {
                $errors[] = 'Non-numeric price for '.$sku;
                continue;
            }
            if ((float) $rawPrice < 0) {
                $errors[] = 'Negative price for '.$sku;
            }
        }

        return $errors;
    }
}
