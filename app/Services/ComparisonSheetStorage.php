<?php

namespace App\Services;

use App\Models\ComparisonData;
use Illuminate\Support\Facades\Storage;

class ComparisonSheetStorage
{
    private const DISK = 'local';
    private const DIR = 'comparison-sheets';

    public function pathForSku(string $sku): string
    {
        return self::DIR . '/' . $this->filenameForSku($sku);
    }

    public function save(string $sku, array $payload): void
    {
        $payload['sku'] = $sku;
        $payload['stored_at'] = now()->toIso8601String();

        Storage::disk(self::DISK)->put(
            $this->pathForSku($sku),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function load(string $sku): ?array
    {
        $path = $this->pathForSku($sku);
        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $decoded = json_decode(Storage::disk(self::DISK)->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function delete(string $sku): void
    {
        $path = $this->pathForSku($sku);
        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function isGoogleSheetUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        return (bool) preg_match('#https?://(docs|sheets)\.google\.com/spreadsheets#i', $url);
    }

    /**
     * Full sheet grid including embedded data:image cells (file is source of truth).
     *
     * @return array<int, array<int, string>>|null
     */
    public function cellsForSku(string $sku): ?array
    {
        $payload = $this->load($sku);
        if (! is_array($payload) || empty($payload['cells']) || ! is_array($payload['cells'])) {
            return null;
        }

        return ComparisonData::normalizeCells($payload['cells']);
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    public function stripEmbeddedImages(array $cells): array
    {
        $stripped = [];

        foreach ($cells as $row) {
            if (! is_array($row)) {
                $stripped[] = [(string) $row];

                continue;
            }

            $nextRow = [];
            foreach ($row as $value) {
                $text = trim((string) $value);
                $nextRow[] = $this->isEmbeddedImageValue($text) ? '' : (string) $value;
            }
            $stripped[] = $nextRow;
        }

        return ComparisonData::normalizeCells($stripped);
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    public function hasEmbeddedImages(array $cells): bool
    {
        foreach ($cells as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $value) {
                if ($this->isEmbeddedImageValue(trim((string) $value))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     * @param  array<string, mixed>|null  $formats
     * @return array{cells: array<int, array<int, string>>, embedded_images_in_file: bool, formats: array<string, array<string, string>>}
     */
    public function sheetDataForDatabase(array $cells, ?array $formats = null): array
    {
        return [
            'cells' => $this->stripEmbeddedImages($cells),
            'embedded_images_in_file' => $this->hasEmbeddedImages($cells),
            'formats' => ComparisonData::normalizeFormats($formats),
        ];
    }

    /**
     * @return array{cells: array<string, string>, rows: array<string, string>, cols: array<string, string>}
     */
    public function formatsFromPayload(?array $payload): array
    {
        if (! is_array($payload)) {
            return ComparisonData::defaultSheetFormats();
        }

        return ComparisonData::normalizeFormats($payload['formats'] ?? []);
    }

    public function isEmbeddedImageValue(string $value): bool
    {
        return str_starts_with($value, 'data:image/');
    }

    private function filenameForSku(string $sku): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($sku));
        $safe = trim($safe, '_');
        if ($safe === '') {
            $safe = 'sku';
        }

        return $safe . '_' . substr(sha1(strtoupper(trim($sku))), 0, 12) . '.json';
    }
}
