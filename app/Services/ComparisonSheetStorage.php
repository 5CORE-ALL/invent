<?php

namespace App\Services;

use App\Models\ComparisonData;
use Illuminate\Support\Facades\Storage;

class ComparisonSheetStorage
{
    private const DISK = 'local';
    private const DIR = 'comparison-sheets';
    private const PHOTO_DIR = 'comparison-sheet-photos';
    private const PHOTO_TOKEN_PREFIX = '[cmp-photo:';

    public function pathForSku(string $sku): string
    {
        return self::DIR . '/' . $this->filenameForSku($sku);
    }

    public function save(string $sku, array $payload): void
    {
        $payload['sku'] = $sku;
        $payload['stored_at'] = now()->toIso8601String();

        // Compact JSON — pretty-print of multi-MB base64 photos freezes PHP and the browser.
        Storage::disk(self::DISK)->put(
            $this->pathForSku($sku),
            json_encode($payload, JSON_UNESCAPED_UNICODE)
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
     * Full sheet grid including photo tokens (and legacy data:image until migrated).
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
    public function stripRawBase64Images(array $cells): array
    {
        $stripped = [];

        foreach ($cells as $row) {
            if (! is_array($row)) {
                $stripped[] = [(string) $row];

                continue;
            }

            $nextRow = [];
            foreach ($row as $value) {
                $text = (string) $value;
                $nextRow[] = $this->isEmbeddedImageValue(ltrim($text)) ? '' : $text;
            }
            $stripped[] = $nextRow;
        }

        return ComparisonData::normalizeCells($stripped);
    }

    /**
     * @deprecated Use stripRawBase64Images — photo tokens must remain in the DB.
     *
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    public function stripEmbeddedImages(array $cells): array
    {
        return $this->stripRawBase64Images($cells);
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
                $text = trim((string) $value);
                if (
                    $this->isEmbeddedImageValue($text)
                    || $this->isPhotoToken($text)
                    || $this->isEmbeddedImagePlaceholder($text)
                ) {
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
        // Keep [cmp-photo:…] tokens + http(s) URLs in DB so photos survive without Google Sheets.
        return [
            'cells' => $this->stripRawBase64Images($cells),
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

    public function isEmbeddedImagePlaceholder(string $value): bool
    {
        return str_starts_with($value, '[embedded-image:');
    }

    public function isPhotoToken(string $value): bool
    {
        return str_starts_with($value, self::PHOTO_TOKEN_PREFIX) && str_ends_with($value, ']');
    }

    public function photoIdFromToken(string $value): ?string
    {
        if (! preg_match('/^\[cmp-photo:([A-Za-z0-9._-]+\.(?:jpe?g|png|gif|webp))\]$/i', trim($value), $matches)) {
            return null;
        }

        return $matches[1];
    }

    public function photoToken(string $photoId): string
    {
        return self::PHOTO_TOKEN_PREFIX . $photoId . ']';
    }

    /**
     * @return array{mime: string, bytes: string}|null
     */
    public function decodeEmbeddedImage(string $value): ?array
    {
        $value = ltrim($value);
        if (! $this->isEmbeddedImageValue($value)) {
            return null;
        }

        if (! preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#s', $value, $matches)) {
            return null;
        }

        $bytes = base64_decode($matches[2], true);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return [
            'mime' => $matches[1],
            'bytes' => $bytes,
        ];
    }

    public function fileMtimeForSku(string $sku): int
    {
        $path = $this->pathForSku($sku);
        if (! Storage::disk(self::DISK)->exists($path)) {
            return 0;
        }

        return (int) Storage::disk(self::DISK)->lastModified($path);
    }

    public function photoDirForSku(string $sku): string
    {
        return self::PHOTO_DIR . '/' . substr(sha1(strtoupper(trim($sku))), 0, 16);
    }

    /**
     * @deprecated Legacy row/col image cache directory.
     */
    public function imageDirForSku(string $sku): string
    {
        return 'comparison-sheet-images/' . substr(sha1(strtoupper(trim($sku))), 0, 16);
    }

    public function photoPath(string $sku, string $photoId): string
    {
        return $this->photoDirForSku($sku) . '/' . $photoId;
    }

    /**
     * Store raw image bytes and return a stable [cmp-photo:…] token for DB/file cells.
     */
    public function storePhotoBytes(string $sku, string $bytes, string $mime = 'image/jpeg'): string
    {
        $ext = match (strtolower($mime)) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => 'jpg',
        };
        $photoId = substr(sha1($bytes), 0, 20) . '.' . $ext;
        $path = $this->photoPath($sku, $photoId);
        if (! Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->put($path, $bytes);
        }

        return $this->photoToken($photoId);
    }

    /**
     * @return array{mime: string, bytes: string}|null
     */
    public function readPhotoById(string $sku, string $photoId): ?array
    {
        if (! preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|gif|webp)$/i', $photoId)) {
            return null;
        }

        $path = $this->photoPath($sku, $photoId);
        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $bytes = Storage::disk(self::DISK)->get($path);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return ['mime' => $mime, 'bytes' => $bytes];
    }

    /**
     * Convert data:image + legacy placeholders into stable photo tokens stored on disk.
     * Result is safe for both the JSON sheet file and comparison_data.sheet_data.
     *
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    public function persistPhotosInCells(string $sku, array $cells): array
    {
        $cells = ComparisonData::normalizeCells($cells);

        // First pass: materialize every raw base64 cell into a stable photo token.
        foreach ($cells as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $colIndex => $value) {
                $decoded = $this->decodeEmbeddedImage((string) $value);
                if ($decoded === null) {
                    continue;
                }
                $cells[$rowIndex][$colIndex] = $this->storePhotoBytes($sku, $decoded['bytes'], $decoded['mime']);
            }
        }

        // Second pass: resolve legacy [embedded-image:r:c] placeholders.
        foreach ($cells as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $colIndex => $value) {
                $text = trim((string) $value);
                if (! $this->isEmbeddedImagePlaceholder($text)) {
                    continue;
                }

                $resolved = $this->resolveLegacyPlaceholder($sku, $cells, $text, (int) $rowIndex, (int) $colIndex);
                $cells[$rowIndex][$colIndex] = $resolved ?? '';
            }
        }

        return ComparisonData::normalizeCells($cells);
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    private function resolveLegacyPlaceholder(
        string $sku,
        array $cells,
        string $placeholder,
        int $currentRow,
        int $currentCol
    ): ?string {
        $row = $currentRow;
        $col = $currentCol;
        if (preg_match('/^\[embedded-image:(\d+):(\d+)\]$/', $placeholder, $matches)) {
            $row = (int) $matches[1];
            $col = (int) $matches[2];
        }

        $candidates = [
            [$row, $col],
            [$currentRow, $currentCol],
        ];

        foreach ($candidates as [$r, $c]) {
            $existing = trim((string) ($cells[$r][$c] ?? ''));
            if ($this->isPhotoToken($existing)) {
                return $existing;
            }
            $decoded = $this->decodeEmbeddedImage($existing);
            if ($decoded !== null) {
                return $this->storePhotoBytes($sku, $decoded['bytes'], $decoded['mime']);
            }

            // Legacy row_col extract cache.
            foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
                $legacyPath = $this->imageDirForSku($sku) . '/' . $r . '_' . $c . '.' . $ext;
                if (! Storage::disk(self::DISK)->exists($legacyPath)) {
                    continue;
                }
                $bytes = Storage::disk(self::DISK)->get($legacyPath);
                if ($bytes === null || $bytes === '') {
                    continue;
                }
                $mime = match ($ext) {
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    default => 'image/jpeg',
                };

                return $this->storePhotoBytes($sku, $bytes, $mime);
            }
        }

        return null;
    }

    /**
     * Browser-safe grid: short photo tokens / URLs only — never multi-MB base64.
     *
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    public function cellsForBrowser(array $cells, ?string $sku = null): array
    {
        if ($sku !== null && $sku !== '') {
            $cells = $this->persistPhotosInCells($sku, $cells);
        }

        $out = [];
        foreach ($cells as $rowIndex => $row) {
            if (! is_array($row)) {
                $out[] = [(string) $row];

                continue;
            }

            $nextRow = [];
            foreach ($row as $colIndex => $value) {
                $text = (string) $value;
                if ($this->isEmbeddedImageValue(ltrim($text))) {
                    // Should already be persisted; keep a safe placeholder if not.
                    $nextRow[] = $sku
                        ? ($this->persistPhotosInCells($sku, [[$text]])[0][0] ?? '')
                        : '[embedded-image:' . (int) $rowIndex . ':' . (int) $colIndex . ']';
                } else {
                    $nextRow[] = $text;
                }
            }
            $out[] = $nextRow;
        }

        return ComparisonData::normalizeCells($out);
    }

    /**
     * When the browser saves with photo tokens / placeholders, keep stored photos.
     *
     * @param  array<int, array<int, string>>  $incoming
     * @param  array<int, array<int, string>>|null  $existing
     * @return array<int, array<int, string>>
     */
    public function restoreEmbeddedImages(array $incoming, ?array $existing): array
    {
        $incoming = ComparisonData::normalizeCells($incoming);
        if (! is_array($existing) || $existing === []) {
            return $incoming;
        }

        foreach ($incoming as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $colIndex => $value) {
                $text = trim((string) $value);
                if ($this->isEmbeddedImageValue($text) || $this->isPhotoToken($text)) {
                    continue;
                }
                if ($text !== '' && ! $this->isEmbeddedImagePlaceholder($text)) {
                    continue;
                }

                $existingVal = trim((string) ($existing[$rowIndex][$colIndex] ?? ''));
                if ($this->isEmbeddedImageValue($existingVal) || $this->isPhotoToken($existingVal)) {
                    $incoming[$rowIndex][$colIndex] = $existing[$rowIndex][$colIndex];
                }
            }
        }

        return ComparisonData::normalizeCells($incoming);
    }

    /**
     * @deprecated Prefer persistPhotosInCells + photo tokens.
     *
     * @param  array<int, array<int, string>>  $processedCells
     */
    public function syncImageFiles(string $sku, array $processedCells): void
    {
        $this->persistPhotosInCells($sku, $processedCells);
    }

    /**
     * @return array{mime: string, bytes: string}|null
     */
    public function readImageFile(string $sku, int $row, int $col): ?array
    {
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            $path = $this->imageDirForSku($sku) . '/' . $row . '_' . $col . '.' . $ext;
            if (! Storage::disk(self::DISK)->exists($path)) {
                continue;
            }
            $bytes = Storage::disk(self::DISK)->get($path);
            if ($bytes === null || $bytes === '') {
                continue;
            }
            $mime = match ($ext) {
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            };

            return ['mime' => $mime, 'bytes' => $bytes];
        }

        return null;
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
