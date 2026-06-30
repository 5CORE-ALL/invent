<?php

namespace App\Services;

use App\Models\ComparisonData;
use Google_Client;
use Google_Service_Sheets;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ComparisonSheetService
{
    public function parseSpreadsheetId(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function parseGid(string $url): ?string
    {
        if (preg_match('~[?&#]gid=(\d+)~', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function fetchFromGoogleSheet(string $url, ?string $tab = null): array
    {
        $spreadsheetId = $this->parseSpreadsheetId($url);
        if ($spreadsheetId === null) {
            throw new \InvalidArgumentException('Invalid Google Sheet URL.');
        }

        $tab = trim((string) ($tab ?: 'Sheet1'));
        $gid = $this->parseGid($url);

        $textCells = [];
        try {
            $textCells = $this->fetchViaApi($spreadsheetId, $tab);
        } catch (\Throwable $e) {
            Log::warning('Comparison sheet API fetch failed', [
                'message' => $e->getMessage(),
                'spreadsheet_id' => $spreadsheetId,
            ]);
        }

        if ($textCells === []) {
            try {
                $textCells = $this->fetchViaCsvExport($spreadsheetId, $gid);
            } catch (\Throwable $e) {
                Log::warning('Comparison sheet CSV export failed', [
                    'message' => $e->getMessage(),
                    'spreadsheet_id' => $spreadsheetId,
                ]);
            }
        }

        $htmlBody = '';
        $imageMap = [];
        $zipImageList = [];

        try {
            $zipPayload = $this->fetchZipExportPayload($spreadsheetId, $gid);
            $htmlBody = $zipPayload['html'] ?? '';
            $imageMap = $zipPayload['images'] ?? [];
            $zipImageList = $zipPayload['image_list'] ?? [];
        } catch (\Throwable $e) {
            Log::warning('Comparison sheet ZIP export failed', [
                'message' => $e->getMessage(),
                'spreadsheet_id' => $spreadsheetId,
            ]);
        }

        if ($htmlBody === '') {
            try {
                $htmlBody = $this->fetchHtmlExportBody($spreadsheetId, $gid);
            } catch (\Throwable $e) {
                Log::warning('Comparison sheet HTML export failed', [
                    'message' => $e->getMessage(),
                    'spreadsheet_id' => $spreadsheetId,
                ]);
            }
        }

        if ($textCells !== []) {
            $textCells = $this->sanitizeImportedTextGrid($textCells);
            if ($htmlBody !== '' || $zipImageList !== []) {
                $textCells = $this->applyDetectedImagesToGrid($textCells, $htmlBody, $imageMap, $zipImageList);
            }

            return $textCells;
        }

        $imageCells = [];
        if ($htmlBody !== '') {
            $imageCells = $this->parseHtmlExportBody($htmlBody, $imageMap);
        }

        if ($imageCells === []) {
            try {
                $imageCells = $this->fetchViaApiFormulas($spreadsheetId, $tab);
            } catch (\Throwable $e) {
                Log::warning('Comparison sheet formula image fetch failed', [
                    'message' => $e->getMessage(),
                    'spreadsheet_id' => $spreadsheetId,
                ]);
            }
        }

        if ($imageCells !== []) {
            return $this->applyDetectedImagesToGrid(
                $this->sanitizeImportedTextGrid($imageCells),
                $htmlBody,
                $imageMap,
                $zipImageList
            );
        }

        throw new \RuntimeException(
            'Could not read the Google Sheet. Publish it to the web or share it with the service account in google-credentials.json.'
        );
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function fetchViaApi(string $spreadsheetId, string $tab): array
    {
        $service = $this->createSheetsService();
        if ($service === null) {
            return [];
        }

        $quotedTab = $this->quoteSheetTab($tab);
        $range = $quotedTab . '!A1:ZZ2000';
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues() ?? [];

        return $this->normalizeValues($values);
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function fetchViaCsvExport(string $spreadsheetId, ?string $gid = null): array
    {
        $params = ['format' => 'csv'];
        if ($gid !== null && $gid !== '') {
            $params['gid'] = $gid;
        }

        $csvUrl = 'https://docs.google.com/spreadsheets/d/' . $spreadsheetId . '/export?' . http_build_query($params);
        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'Invent-ComparisonSheet/1.0'])
            ->get($csvUrl);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Could not read the Google Sheet. Publish it to the web or share it with the service account in google-credentials.json.'
            );
        }

        return $this->parseCsvBody($response->body());
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function parseCsvBody(string $body): array
    {
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }

        $lines = preg_split("/\r\n|\n|\r/", trim($body));
        $rows = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $rows[] = str_getcsv($line, ',', '"', '\\');
        }

        return $this->normalizeValues($rows);
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, array<int, string>>
     */
    protected function normalizeValues(array $values): array
    {
        $cells = [];
        foreach ($values as $row) {
            if (! is_array($row)) {
                $cells[] = [(string) $row];
                continue;
            }
            $cells[] = array_map(fn ($value) => trim((string) ($value ?? '')), $row);
        }

        return ComparisonData::normalizeCells($cells);
    }

    /**
     * ZIP export includes pasted/over-grid images as files under images/.
     *
     * @return array{html: string, images: array<string, string>, image_list: list<string>}
     */
    protected function fetchZipExportPayload(string $spreadsheetId, ?string $gid = null): array
    {
        $urls = [
            'https://docs.google.com/spreadsheets/d/' . $spreadsheetId . '/export?format=zip',
        ];
        if ($gid !== null && $gid !== '') {
            $urls[] = 'https://docs.google.com/spreadsheets/d/' . $spreadsheetId . '/export?format=zip&gid=' . $gid;
        }

        foreach ($urls as $exportUrl) {
            $binary = $this->downloadExportBinary($exportUrl);
            if ($binary === '') {
                continue;
            }

            $payload = $this->parseZipExportBinary($binary);
            if ($payload['html'] !== '' || $payload['image_list'] !== []) {
                return $payload;
            }
        }

        return ['html' => '', 'images' => [], 'image_list' => []];
    }

    protected function downloadExportBinary(string $url): string
    {
        $response = Http::timeout(90)
            ->withHeaders(['User-Agent' => 'Invent-ComparisonSheet/1.0'])
            ->get($url);

        if ($response->successful() && $this->looksLikeZipBinary($response->body())) {
            return $response->body();
        }

        $token = $this->getGoogleAccessToken();
        if ($token === null) {
            return '';
        }

        $authResponse = Http::timeout(90)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'Invent-ComparisonSheet/1.0',
            ])
            ->get($url);

        if ($authResponse->successful() && $this->looksLikeZipBinary($authResponse->body())) {
            return $authResponse->body();
        }

        return '';
    }

    protected function looksLikeZipBinary(string $binary): bool
    {
        return str_starts_with($binary, 'PK');
    }

    /**
     * @return array{html: string, images: array<string, string>, image_list: list<string>}
     */
    protected function parseZipExportBinary(string $binary): array
    {
        if (! class_exists(\ZipArchive::class)) {
            return ['html' => '', 'images' => [], 'image_list' => []];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cmp_sheet_zip_');
        if ($tmp === false) {
            return ['html' => '', 'images' => [], 'image_list' => []];
        }

        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);

            return ['html' => '', 'images' => [], 'image_list' => []];
        }

        $html = '';
        $htmlCandidates = [];
        $images = [];
        $imageList = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if ($name === '') {
                continue;
            }

            if (preg_match('/\.html$/i', $name)) {
                $content = $zip->getFromIndex($index);
                if (is_string($content) && $content !== '') {
                    $htmlCandidates[$name] = $content;
                }
                continue;
            }

            if (! preg_match('#(^|/)(images|resources)/(.+\.(png|jpe?g|gif|webp|bmp))$#i', $name, $matches)) {
                continue;
            }

            $binaryImage = $zip->getFromIndex($index);
            if (! is_string($binaryImage) || $binaryImage === '') {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'bmp' => 'image/bmp',
                default => 'application/octet-stream',
            };

            $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($binaryImage);
            $relativePath = preg_replace('#^.*?((?:images|resources)/.+)$#i', '$1', $name) ?? $name;
            $images[$relativePath] = $dataUrl;
            $images[ltrim($relativePath, './')] = $dataUrl;
            $images[basename($name)] = $dataUrl;
            $imageList[] = $dataUrl;
        }

        $zip->close();
        @unlink($tmp);

        if ($htmlCandidates !== []) {
            uasort($htmlCandidates, fn ($a, $b) => strlen($b) <=> strlen($a));
            $html = (string) reset($htmlCandidates);
        }

        return [
            'html' => $html,
            'images' => $images,
            'image_list' => array_values(array_unique($imageList)),
        ];
    }

    protected function getGoogleAccessToken(): ?string
    {
        $credentialsPath = config('googlesheets.credentials_path', storage_path('app/google-credentials.json'));
        if (! is_readable($credentialsPath)) {
            return null;
        }

        try {
            $client = new Google_Client();
            $client->setAuthConfig($credentialsPath);
            $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
            $token = $client->fetchAccessTokenWithAssertion();

            return is_array($token) ? ($token['access_token'] ?? null) : null;
        } catch (\Throwable $e) {
            Log::warning('Comparison sheet access token failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, string>  $localImageMap
     * @param  list<string>  $zipImageList
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    protected function applyDetectedImagesToGrid(
        array $cells,
        string $html,
        array $localImageMap = [],
        array $zipImageList = []
    ): array {
        if ($cells === []) {
            return $cells;
        }

        $cells = $this->applyPosObjImagesToGrid($cells, $html, $localImageMap);

        $positioned = $this->extractPositionedImagesFromHtml($html, $localImageMap);
        if ($positioned !== []) {
            $cells = $this->assignPositionedImagesToGrid($cells, $positioned, $html);
        }

        $cells = $this->fillPhotoRowImagesFromHtml($cells, $html, $localImageMap, $zipImageList);
        $cells = $this->fillGridWithRemainingImages($cells, $positioned, $localImageMap, $zipImageList);

        return ComparisonData::normalizeCells($cells);
    }

    /**
     * Google ZIP exports place pasted images via posObj() calls in the HTML footer.
     *
     * @param  array<string, string>  $localImageMap
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    protected function applyPosObjImagesToGrid(array $cells, string $html, array $localImageMap = []): array
    {
        if (trim($html) === '' || $cells === []) {
            return $cells;
        }

        $embedImages = $this->extractEmbedIdImageMap($html, $localImageMap);
        $placements = $this->extractPosObjPlacements($html);
        if ($embedImages === [] || $placements === []) {
            return $cells;
        }

        $labelCol = $this->detectLabelColumnIndex($cells);
        usort($placements, fn ($a, $b) => $a['row'] <=> $b['row']
            ?: $a['col'] <=> $b['col']
            ?: $a['x'] <=> $b['x']);

        $filled = [];
        foreach ($placements as $placement) {
            $url = $embedImages[$placement['embed_id']] ?? '';
            if ($url === '' || ! $this->isImageUrl($url)) {
                continue;
            }

            $row = $placement['row'];
            $col = $placement['col'];
            if ($col === $labelCol && $this->cellContainsRowLabel($cells[$row][$col] ?? '')) {
                continue;
            }

            $slot = $row . ':' . $col;
            if (isset($filled[$slot])) {
                continue;
            }

            $existing = trim((string) ($cells[$row][$col] ?? ''));
            if ($existing !== '' && ! $this->isPlaceholderSheetCellValue($existing) && ! $this->isImageUrl($existing)) {
                continue;
            }

            $cells[$row][$col] = $url;
            $filled[$slot] = true;
        }

        return $cells;
    }

    /**
     * @return array<string, string>
     */
    protected function extractEmbedIdImageMap(string $html, array $localImageMap = []): array
    {
        preg_match_all(
            "/<div id='(embed_[0-9]+)'[^>]*><img[^>]+src='([^']+)'/i",
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $map = [];
        foreach ($matches as $match) {
            $url = $this->resolveHtmlImageSrc($match[2], $localImageMap);
            if ($url !== '' && $this->isImageUrl($url)) {
                $map[$match[1]] = $url;
            }
        }

        return $map;
    }

    /**
     * @return list<array{embed_id: string, row: int, col: int, x: int, y: int}>
     */
    protected function extractPosObjPlacements(string $html): array
    {
        preg_match_all(
            "/posObj\\('[^']*',\\s*'(embed_[0-9]+)',\\s*(\\d+),\\s*(\\d+),\\s*(\\d+),\\s*(\\d+)\\)/",
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $placements = [];
        foreach ($matches as $match) {
            $placements[] = [
                'embed_id' => $match[1],
                'row' => (int) $match[2],
                'col' => (int) $match[3],
                'x' => (int) $match[4],
                'y' => (int) $match[5],
            ];
        }

        return $placements;
    }

    /**
     * Remove HTML-export artifacts that should never appear in the editable grid.
     *
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    protected function sanitizeImportedTextGrid(array $cells): array
    {
        if ($cells === []) {
            return $cells;
        }

        $labelCol = $this->detectLabelColumnIndex($cells);
        $photoRowIndex = $this->findRowIndexByLabel($cells, 'product photo', $labelCol) ?? 0;

        if (isset($cells[$photoRowIndex]) && is_array($cells[$photoRowIndex])) {
            foreach ($cells[$photoRowIndex] as $colIndex => $value) {
                if ((int) $colIndex === $labelCol) {
                    continue;
                }
                if ($this->isPlaceholderSheetCellValue((string) $value)) {
                    $cells[$photoRowIndex][$colIndex] = '';
                }
            }
        }

        $clean = [];
        foreach ($cells as $rowIndex => $row) {
            if (! is_array($row)) {
                $clean[] = [(string) $row];

                continue;
            }

            if ($rowIndex > 0 && $this->isHtmlExportDuplicateRow($cells[$rowIndex - 1] ?? [], $row, $labelCol)) {
                continue;
            }

            $clean[] = $row;
        }

        return ComparisonData::normalizeCells($clean);
    }

    protected function isPlaceholderSheetCellValue(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return (bool) preg_match('/^[A-Z]{1,3}$/', $value);
    }

    protected function cellContainsRowLabel(string $value): bool
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return false;
        }

        foreach (['product photo', 'person name review', 'supplier link', 'supplier name', 'company name'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $previousRow
     * @param  array<int, string>  $row
     */
    protected function isHtmlExportDuplicateRow(array $previousRow, array $row, int $labelCol): bool
    {
        $leading = trim((string) ($row[0] ?? ''));
        if ($leading === '' || ! ctype_digit($leading)) {
            return false;
        }

        $offset = 1;
        $maxCols = max(count($previousRow), count($row) - $offset);
        $matches = 0;
        $compared = 0;

        for ($colIndex = 0; $colIndex < $maxCols; $colIndex++) {
            $previousValue = trim((string) ($previousRow[$colIndex] ?? ''));
            $currentValue = trim((string) ($row[$colIndex + $offset] ?? ''));
            if ($previousValue === '' && $currentValue === '') {
                continue;
            }
            $compared++;
            if ($previousValue === $currentValue) {
                $matches++;
            }
        }

        return $compared > 0 && ($matches / $compared) >= 0.8;
    }

    /**
     * @param  array<string, string>  $localImageMap
     * @return list<array{url: string, top: int, left: int}>
     */
    protected function extractPositionedImagesFromHtml(string $html, array $localImageMap = []): array
    {
        if (trim($html) === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $results = [];
        $images = $dom->getElementsByTagName('img');
        for ($index = 0; $index < $images->length; $index++) {
            $img = $images->item($index);
            if (! $img instanceof \DOMElement) {
                continue;
            }

            $src = '';
            foreach (['src', 'data-src', 'data-original-src'] as $attribute) {
                $candidate = trim($img->getAttribute($attribute));
                if ($candidate !== '') {
                    $src = $candidate;
                    break;
                }
            }
            if ($src === '') {
                continue;
            }

            $url = $this->resolveHtmlImageSrc($src, $localImageMap);
            if ($url === '' || $this->isIgnoredHtmlImageUrl($url) || ! $this->isImageUrl($url)) {
                continue;
            }

            $position = $this->extractElementPosition($img);
            $results[] = [
                'url' => $url,
                'top' => $position['top'],
                'left' => $position['left'],
            ];
        }

        usort($results, fn ($a, $b) => $a['top'] <=> $b['top'] ?: $a['left'] <=> $b['left']);

        return $results;
    }

    /**
     * @return array{top: int, left: int}
     */
    protected function extractElementPosition(\DOMElement $element): array
    {
        $top = 0;
        $left = 0;
        $node = $element;

        while ($node instanceof \DOMElement) {
            $style = $node->getAttribute('style');
            if ($style !== '') {
                if (preg_match('/(?:^|;)\s*top:\s*(-?[\d.]+)px/i', $style, $matches)) {
                    $top += (int) round((float) $matches[1]);
                }
                if (preg_match('/(?:^|;)\s*left:\s*(-?[\d.]+)px/i', $style, $matches)) {
                    $left += (int) round((float) $matches[1]);
                }
                if (preg_match('/margin-top:\s*(-?[\d.]+)px/i', $style, $matches)) {
                    $top += (int) round((float) $matches[1]);
                }
                if (preg_match('/margin-left:\s*(-?[\d.]+)px/i', $style, $matches)) {
                    $left += (int) round((float) $matches[1]);
                }
            }
            $node = $node->parentNode instanceof \DOMElement ? $node->parentNode : null;
        }

        return ['top' => max(0, $top), 'left' => max(0, $left)];
    }

    /**
     * @param  list<array{url: string, top: int, left: int}>  $positionedImages
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    protected function assignPositionedImagesToGrid(array $cells, array $positionedImages, string $html): array
    {
        if ($positionedImages === []) {
            return $cells;
        }

        $labelCol = $this->detectLabelColumnIndex($cells);
        $photoRowIndex = $this->findRowIndexByLabel($cells, 'product photo', $labelCol) ?? 0;
        $colWidth = $this->estimateColumnWidthFromHtml($html);
        $rowHeight = $this->estimateRowHeightFromHtml($html);

        $bands = [];
        foreach ($positionedImages as $image) {
            $band = (int) floor($image['top'] / max(1, $rowHeight));
            $bands[$band][] = $image;
        }
        ksort($bands);
        $photoBand = reset($bands) ?: $positionedImages;

        foreach ($photoBand as $image) {
            $col = (int) max(0, floor($image['left'] / max(1, $colWidth)));
            if ($col === $labelCol) {
                $col++;
            }

            while (isset($cells[$photoRowIndex][$col])
                && trim((string) $cells[$photoRowIndex][$col]) !== ''
                && trim((string) $cells[$photoRowIndex][$col]) !== $image['url']) {
                $col++;
            }

            $cells[$photoRowIndex][$col] = $image['url'];
        }

        return $cells;
    }

    protected function estimateColumnWidthFromHtml(string $html): int
    {
        if (preg_match('/<col[^>]*width=["\']?(\d+)/i', $html, $matches)) {
            return max(40, (int) $matches[1]);
        }

        return 100;
    }

    protected function estimateRowHeightFromHtml(string $html): int
    {
        if (preg_match('/row-height:\s*([\d.]+)pt/i', $html, $matches)) {
            return max(20, (int) round(((float) $matches[1]) * 1.33));
        }

        return 80;
    }

    /**
     * @param  array<string, string>  $localImageMap
     * @param  list<string>  $zipImageList
     * @param  list<array{url: string, top: int, left: int}>  $positionedImages
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    protected function fillGridWithRemainingImages(
        array $cells,
        array $positionedImages,
        array $localImageMap = [],
        array $zipImageList = []
    ): array {
        $used = [];
        foreach ($cells as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $value) {
                $trimmed = trim((string) $value);
                if ($this->isImageUrl($trimmed)) {
                    $used[$trimmed] = true;
                }
            }
        }

        $queue = [];
        foreach ($positionedImages as $image) {
            if (! isset($used[$image['url']])) {
                $queue[] = $image['url'];
            }
        }
        foreach ($zipImageList as $url) {
            if (! isset($used[$url])) {
                $queue[] = $url;
            }
        }
        foreach ($localImageMap as $url) {
            if (! isset($used[$url])) {
                $queue[] = $url;
            }
        }

        $queue = array_values(array_unique(array_filter($queue)));
        if ($queue === []) {
            return $cells;
        }

        $labelCol = $this->detectLabelColumnIndex($cells);
        $photoRowIndex = $this->findRowIndexByLabel($cells, 'product photo', $labelCol) ?? 0;
        $queueIndex = 0;

        foreach ($cells[$photoRowIndex] ?? [] as $colIndex => $value) {
            if ((int) $colIndex === $labelCol) {
                continue;
            }
            $trimmed = trim((string) $value);
            if ($trimmed !== '' && ! $this->isPlaceholderSheetCellValue($trimmed)) {
                continue;
            }
            if (! isset($queue[$queueIndex])) {
                break;
            }
            $cells[$photoRowIndex][$colIndex] = $queue[$queueIndex];
            $queueIndex++;
        }

        return $cells;
    }

    /**
     * @param  array<string, string>  $localImageMap
     */
    protected function resolveHtmlImageSrc(string $src, array $localImageMap = []): string
    {
        $src = $this->normalizeExportImageUrl($src);
        if ($src === '') {
            return '';
        }

        if (str_starts_with($src, 'data:image/')) {
            return $src;
        }

        $candidates = [
            $src,
            ltrim($src, './'),
            'images/' . basename($src),
            'resources/' . basename($src),
            basename($src),
        ];

        foreach ($candidates as $candidate) {
            if (isset($localImageMap[$candidate])) {
                return $localImageMap[$candidate];
            }
        }

        return $src;
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function fetchViaHtmlExport(string $spreadsheetId, ?string $gid = null): array
    {
        $htmlBody = $this->fetchHtmlExportBody($spreadsheetId, $gid);
        if ($htmlBody === '') {
            return [];
        }

        return $this->parseHtmlExportBody($htmlBody);
    }

    protected function fetchHtmlExportBody(string $spreadsheetId, ?string $gid = null): string
    {
        $params = ['format' => 'html'];
        if ($gid !== null && $gid !== '') {
            $params['gid'] = $gid;
        }

        $exportUrl = 'https://docs.google.com/spreadsheets/d/' . $spreadsheetId . '/export?' . http_build_query($params);
        $response = Http::timeout(45)
            ->withHeaders(['User-Agent' => 'Invent-ComparisonSheet/1.0'])
            ->get($exportUrl);

        if (! $response->successful()) {
            return '';
        }

        return $response->body();
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function fetchViaApiFormulas(string $spreadsheetId, string $tab): array
    {
        $service = $this->createSheetsService();
        if ($service === null) {
            return [];
        }

        $quotedTab = $this->quoteSheetTab($tab);
        $range = $quotedTab . '!A1:ZZ2000';
        $response = $service->spreadsheets_values->get($spreadsheetId, $range, [
            'valueRenderOption' => 'FORMULA',
        ]);
        $values = $response->getValues() ?? [];
        if ($values === []) {
            return [];
        }

        $cells = [];
        foreach ($values as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cells[] = array_map(function ($value) {
                $text = trim((string) ($value ?? ''));

                return $this->extractImageUrlFromFormula($text) ?? $text;
            }, $row);
        }

        return ComparisonData::normalizeCells($cells);
    }

    /**
     * @param  array<string, string>  $localImageMap
     * @return array<int, array<int, string>>
     */
    protected function parseHtmlExportBody(string $html, array $localImageMap = []): array
    {
        if (trim($html) === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        $bestTable = null;
        $bestScore = 0;
        foreach ($tables as $table) {
            $score = $table->getElementsByTagName('tr')->length;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTable = $table;
            }
        }

        if ($bestTable === null) {
            return [];
        }

        $rows = [];
        $rowSpanTracker = [];

        foreach ($bestTable->getElementsByTagName('tr') as $tr) {
            $rowIndex = count($rows);
            $currentRow = $rowSpanTracker[$rowIndex] ?? [];

            foreach ($tr->childNodes as $cell) {
                if (! $cell instanceof \DOMElement) {
                    continue;
                }
                if (! in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                    continue;
                }

                $colIndex = 0;
                while (isset($currentRow[$colIndex])) {
                    $colIndex++;
                }

                $colSpan = max(1, (int) $cell->getAttribute('colspan'));
                $rowSpan = max(1, (int) $cell->getAttribute('rowspan'));
                $value = $this->extractCellValueFromHtmlNode($cell, $localImageMap);

                for ($rowOffset = 0; $rowOffset < $rowSpan; $rowOffset++) {
                    for ($colOffset = 0; $colOffset < $colSpan; $colOffset++) {
                        $targetRow = $rowIndex + $rowOffset;
                        $targetCol = $colIndex + $colOffset;
                        $cellValue = ($rowOffset === 0 && $colOffset === 0) ? $value : '';

                        if ($rowOffset === 0) {
                            $currentRow[$targetCol] = $cellValue;
                        } else {
                            $rowSpanTracker[$targetRow][$targetCol] = $cellValue;
                        }
                    }
                }
            }

            ksort($currentRow);
            if ($currentRow !== []) {
                $maxCol = max(array_keys($currentRow));
                $normalizedRow = [];
                for ($col = 0; $col <= $maxCol; $col++) {
                    $normalizedRow[] = $currentRow[$col] ?? '';
                }
                $rows[] = $normalizedRow;
            }
        }

        return $this->normalizeValues($rows);
    }

    /**
     * Map every image found in the HTML export onto empty Product Photo row cells.
     *
     * @param  array<string, string>  $localImageMap
     * @param  list<string>  $zipImageList
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    protected function fillPhotoRowImagesFromHtml(
        array $cells,
        string $html,
        array $localImageMap = [],
        array $zipImageList = []
    ): array {
        $images = $this->extractAllImageUrlsFromHtml($html, $localImageMap);
        foreach ($zipImageList as $url) {
            if ($this->isImageUrl($url)) {
                $images[] = $url;
            }
        }
        $images = array_values(array_unique($images));
        if ($images === [] || $cells === []) {
            return $cells;
        }

        $labelCol = $this->detectLabelColumnIndex($cells);
        $photoRowIndex = $this->findRowIndexByLabel($cells, 'product photo', $labelCol) ?? 0;
        if (! isset($cells[$photoRowIndex]) || ! is_array($cells[$photoRowIndex])) {
            return $cells;
        }

        $usedImages = [];
        foreach ($cells as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $value) {
                $trimmed = trim((string) $value);
                if ($this->isImageUrl($trimmed)) {
                    $usedImages[$trimmed] = true;
                }
            }
        }

        $queue = array_values(array_filter($images, fn ($url) => ! isset($usedImages[$url])));
        $queueIndex = 0;

        foreach ($cells[$photoRowIndex] as $colIndex => $value) {
            if ((int) $colIndex === $labelCol) {
                continue;
            }
            $trimmed = trim((string) $value);
            if ($trimmed !== '' && ! $this->isPlaceholderSheetCellValue($trimmed)) {
                continue;
            }
            if (! isset($queue[$queueIndex])) {
                break;
            }
            $cells[$photoRowIndex][$colIndex] = $queue[$queueIndex];
            $queueIndex++;
        }

        return ComparisonData::normalizeCells($cells);
    }

    /**
     * @param  array<string, string>  $localImageMap
     * @return list<string>
     */
    protected function extractAllImageUrlsFromHtml(string $html, array $localImageMap = []): array
    {
        if (trim($html) === '') {
            return [];
        }

        preg_match_all('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $html, $matches);
        preg_match_all('/background-image:\s*url\([\'"]?([^\'")]+)/i', $html, $backgroundMatches);
        $urls = [];

        foreach (array_merge($matches[1] ?? [], $backgroundMatches[1] ?? []) as $src) {
            $url = $this->resolveHtmlImageSrc((string) $src, $localImageMap);
            if ($url === '' || $this->isIgnoredHtmlImageUrl($url)) {
                continue;
            }
            if ($this->isImageUrl($url)) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    protected function isIgnoredHtmlImageUrl(string $url): bool
    {
        $lower = strtolower($url);

        return str_contains($lower, 'cleardot.gif')
            || str_contains($lower, '/sheetz/v1/img/')
            || str_contains($lower, 'google.com/images/spreadsheet')
            || str_contains($lower, 'favicon');
    }

    /**
     * @param  array<string, string>  $localImageMap
     */
    protected function extractCellValueFromHtmlNode(\DOMElement $cell, array $localImageMap = []): string
    {
        $imgs = $cell->getElementsByTagName('img');
        for ($index = 0; $index < $imgs->length; $index++) {
            $img = $imgs->item($index);
            if (! $img instanceof \DOMElement) {
                continue;
            }

            foreach (['src', 'data-src', 'data-original-src'] as $attribute) {
                $src = trim($img->getAttribute($attribute));
                if ($src === '' || $this->isIgnoredHtmlImageUrl($src)) {
                    continue;
                }
                $url = $this->resolveHtmlImageSrc($src, $localImageMap);
                if ($this->isImageUrl($url)) {
                    return $url;
                }
            }
        }

        $style = $cell->getAttribute('style');
        if ($style !== '' && preg_match('/background-image:\s*url\([\'"]?([^\'")]+)/i', $style, $matches)) {
            $url = $this->resolveHtmlImageSrc($matches[1], $localImageMap);
            if ($this->isImageUrl($url)) {
                return $url;
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $cell->textContent ?? ''));
    }

    protected function normalizeExportImageUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5);
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return $url;
    }

    protected function extractImageUrlFromFormula(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($this->isImageUrl($value)) {
            return $value;
        }

        if (! str_starts_with($value, '=')) {
            return null;
        }

        if (preg_match('/=IMAGE\s*\(\s*"([^"]+)"/i', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match("/=IMAGE\s*\(\s*'([^']+)'/i", $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('/https?:\/\/[^\s"\')]+/i', $value, $matches)) {
            return $this->isImageUrl($matches[0]) ? $matches[0] : null;
        }

        return null;
    }

    protected function isImageUrl(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (str_starts_with($value, 'data:image/')) {
            return true;
        }

        if (str_starts_with($value, '/')) {
            return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp|svg)(\?|$)/i', $value)
                || str_contains($value, '/storage/');
        }

        if (! preg_match('/^https?:\/\//i', $value)) {
            return false;
        }

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp|svg)(\?|$)/i', $value)
            || str_contains($value, 'googleusercontent.com')
            || str_contains($value, 'ggpht.com')
            || str_contains($value, 'cdn.shopify.com')
            || str_contains($value, 'docs.google.com/feeds')
            || str_contains($value, 'drive.google.com/thumbnail');
    }

    /**
     * @param  array<int, array<int, string>>  $primary
     * @param  array<int, array<int, string>>  $secondary
     * @return array<int, array<int, string>>
     */
    protected function mergeCellGrids(array $primary, array $secondary): array
    {
        $maxRows = max(count($primary), count($secondary));
        $merged = [];

        for ($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++) {
            $rowA = $primary[$rowIndex] ?? [];
            $rowB = $secondary[$rowIndex] ?? [];
            $maxCols = max(count($rowA), count($rowB));
            $row = [];

            for ($colIndex = 0; $colIndex < $maxCols; $colIndex++) {
                $a = trim((string) ($rowA[$colIndex] ?? ''));
                $b = trim((string) ($rowB[$colIndex] ?? ''));

                if ($this->isImageUrl($b)) {
                    $row[] = $b;
                } elseif ($this->isImageUrl($a)) {
                    $row[] = $a;
                } elseif ($b !== '' && $a === '') {
                    $row[] = $b;
                } elseif ($a !== '') {
                    $row[] = $a;
                } else {
                    $row[] = '';
                }
            }

            $merged[] = $row;
        }

        return ComparisonData::normalizeCells($merged);
    }

    /**
     * Fill empty cells on the Product Photo row with the SKU product image.
     *
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    public function enrichProductPhotoRow(array $cells, ?string $productImageUrl): array
    {
        $productImageUrl = trim((string) ($productImageUrl ?? ''));
        if ($productImageUrl === '' || $cells === []) {
            return $cells;
        }

        $labelCol = $this->detectSpecColumnIndex($cells);
        $photoRowIndex = $this->findRowIndexByLabel($cells, 'product photo', $labelCol);
        if ($photoRowIndex === null) {
            $photoRowIndex = 0;
        }

        $row = $cells[$photoRowIndex] ?? [];
        $targetCol = max(0, $labelCol - 1);

        if (trim((string) ($row[$targetCol] ?? '')) === '') {
            $cells[$photoRowIndex][$targetCol] = $productImageUrl;

            return ComparisonData::normalizeCells($cells);
        }

        return $cells;
    }

    public const SPEC_COLUMN_COLOR = '#fed7aa';

    public const LOWEST_PRICE_COLOR = '#bbf7d0';

    public const SUPPLIER_NAME_ROW_COLOR = '#42c4f0';

    /**
     * @param  array<int, array<int, string>>  $cells
     * @return array{cells: array<string, string>, rows: array<string, string>, cols: array<string, string>}
     */
    public function computeAutoFormats(array $cells): array
    {
        $formats = ComparisonData::defaultSheetFormats();
        $cells = ComparisonData::normalizeCells($cells);
        if ($cells === []) {
            return $formats;
        }

        $specCol = $this->detectSpecColumnIndex($cells);
        $formats['cols'][(string) $specCol] = self::SPEC_COLUMN_COLOR;

        foreach ($cells as $rowIndex => $row) {
            if ($this->isSupplierNameRow($cells, (int) $rowIndex, $specCol)) {
                $formats['rows'][(string) $rowIndex] = self::SUPPLIER_NAME_ROW_COLOR;
            }
        }

        $firstSupplierCol = $specCol + 1;
        $colCount = max(array_map(fn ($sheetRow) => is_array($sheetRow) ? count($sheetRow) : 0, $cells));

        foreach (['usd', 'rmb'] as $needle) {
            $rowIndex = $this->findRowIndexByLabel($cells, $needle, $specCol);
            if ($rowIndex === null) {
                continue;
            }

            $bestCol = null;
            $bestValue = PHP_FLOAT_MAX;
            for ($colIndex = $firstSupplierCol; $colIndex < $colCount; $colIndex++) {
                $value = $this->parseSheetNumber((string) ($cells[$rowIndex][$colIndex] ?? ''));
                if ($value === null || $value <= 0 || $value >= $bestValue) {
                    continue;
                }
                $bestValue = $value;
                $bestCol = $colIndex;
            }

            if ($bestCol !== null) {
                $formats['cells']["{$rowIndex}:{$bestCol}"] = self::LOWEST_PRICE_COLOR;
            }
        }

        return ComparisonData::normalizeFormats($formats);
    }

    public function isSupplierNameRowLabel(string $text): bool
    {
        $text = strtolower(trim($text));
        if ($text === '' || str_contains($text, 'company name')) {
            return false;
        }

        if (str_contains($text, 'supplier name')) {
            return true;
        }

        return in_array($text, ['supplier', 'suppliers'], true);
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    public function isSupplierNameRow(array $cells, int $rowIndex, ?int $specCol = null): bool
    {
        $row = $cells[$rowIndex] ?? [];
        if (! is_array($row)) {
            return false;
        }

        $specCol ??= $this->detectSpecColumnIndex($cells);

        foreach ($row as $colIndex => $value) {
            $text = trim((string) $value);
            if ($text === '' || ! $this->isSupplierNameRowLabel($text)) {
                continue;
            }

            if ((int) $colIndex === $specCol) {
                return true;
            }

            if (strlen($text) <= 48) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure Amazon + 5 Core columns exist before the Spec column and move the lowest USD supplier column first.
     *
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    public function normalizeComparisonLayout(array $cells): array
    {
        $cells = ComparisonData::normalizeCells($cells);
        $cells = $this->ensureLeadColumns($cells);
        $cells = $this->moveLowestPriceSupplierAfterSpec($cells);

        return ComparisonData::normalizeCells($cells);
    }

    /**
     * Move the supplier column with the lowest USD (or RMB) price to the first slot after Spec.
     *
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    public function moveLowestPriceSupplierAfterSpec(array $cells): array
    {
        $cells = ComparisonData::normalizeCells($cells);
        $specCol = $this->detectSpecColumnIndex($cells);
        $colCount = max(array_map(fn ($row) => is_array($row) ? count($row) : 0, $cells));
        $firstSupplierCol = $specCol + 1;

        if ($firstSupplierCol >= $colCount) {
            return $cells;
        }

        $bestCol = $this->findLowestSupplierColumn($cells, $specCol, 'usd');
        if ($bestCol === null) {
            $bestCol = $this->findLowestSupplierColumn($cells, $specCol, 'rmb');
        }

        if ($bestCol === null || $bestCol === $firstSupplierCol) {
            return $cells;
        }

        return $this->moveColumn($cells, $bestCol, $firstSupplierCol);
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    protected function findLowestSupplierColumn(array $cells, int $specCol, string $labelNeedle): ?int
    {
        $rowIndex = $this->findRowIndexByLabel($cells, $labelNeedle, $specCol);
        if ($rowIndex === null) {
            return null;
        }

        $colCount = max(array_map(fn ($row) => is_array($row) ? count($row) : 0, $cells));
        $firstSupplierCol = $specCol + 1;
        $bestCol = null;
        $bestValue = PHP_FLOAT_MAX;

        for ($colIndex = $firstSupplierCol; $colIndex < $colCount; $colIndex++) {
            $value = $this->parseSheetNumber((string) ($cells[$rowIndex][$colIndex] ?? ''));
            if ($value === null || $value <= 0) {
                continue;
            }

            if ($value < $bestValue) {
                $bestValue = $value;
                $bestCol = $colIndex;
            }
        }

        return $bestCol;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    public function ensureLeadColumns(array $cells): array
    {
        $cells = ComparisonData::normalizeCells($cells);
        $specCol = $this->detectSpecColumnIndex($cells);

        if ($specCol < 2 && ! $this->columnMatchesKeywords($cells, 0, ['amazon'])) {
            $insertAt = max(0, $specCol - 1);
            if ($specCol === 1 && $this->columnMatchesKeywords($cells, 0, ['5 core', '5core', '5-core'])) {
                $insertAt = 0;
            }
            $cells = $this->insertColumnAt($cells, $insertAt);
            $specCol = $this->detectSpecColumnIndex($cells);
        }

        while ($specCol < 2) {
            $cells = $this->insertColumnAt($cells, 0);
            $specCol++;
        }

        $specCol = $this->detectSpecColumnIndex($cells);
        $amazonCol = $specCol - 2;
        $fiveCoreCol = $specCol - 1;

        if (! $this->columnMatchesKeywords($cells, $amazonCol, ['amazon'])) {
            $this->stampColumnHeader($cells, $amazonCol, 'Amazon');
        }

        if (! $this->columnMatchesKeywords($cells, $fiveCoreCol, ['5 core', '5core', '5-core'])) {
            $this->stampColumnHeader($cells, $fiveCoreCol, '5 Core');
        }

        return ComparisonData::normalizeCells($cells);
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    public function detectSpecColumnIndex(array $cells): int
    {
        $scores = [];
        $maxRows = min(count($cells), 30);

        for ($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++) {
            $row = $cells[$rowIndex] ?? [];
            if (! is_array($row)) {
                continue;
            }

            foreach ($row as $colIndex => $value) {
                $text = strtolower(trim((string) $value));
                if ($text === '' || str_starts_with($text, 'http') || str_starts_with($text, 'data:image/')) {
                    continue;
                }

                if (str_contains($text, 'supplier')
                    || str_contains($text, 'product photo')
                    || str_contains($text, 'person name review')
                    || str_contains($text, 'company name')) {
                    $scores[(int) $colIndex] = ($scores[(int) $colIndex] ?? 0) + 1;
                }
            }
        }

        if ($scores === []) {
            return 2;
        }

        arsort($scores);

        return (int) array_key_first($scores);
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    public function writeRoiChannelRow(array $cells, string $channel, array $rowData): array
    {
        $specCol = $this->detectSpecColumnIndex($cells);
        $rowIndex = $this->findCostCalculatorChannelRow($cells, $channel, $specCol);
        $colCount = max(
            $specCol + 10,
            6,
            ...array_map(fn ($row) => is_array($row) ? count($row) : 0, $cells),
        );

        if ($rowIndex === null) {
            $newRow = array_fill(0, $colCount, '');
            $newRow[$specCol] = ucfirst(strtolower(trim($channel))) === 'Ebay' ? 'Ebay' : 'Amazon';
            $cells[] = $newRow;
            $rowIndex = count($cells) - 1;
        }

        while (count($cells[$rowIndex]) < $colCount) {
            $cells[$rowIndex][] = '';
        }

        $offsets = [
            'cp' => 1,
            'cbm' => 2,
            'freight' => 3,
            'gw' => 4,
            'shipping' => 5,
            'sale' => 6,
            'pPct' => 7,
            'profit' => 8,
            'roi' => 9,
        ];

        foreach ($offsets as $key => $offset) {
            $value = $rowData[$key] ?? '';
            if (in_array($key, ['pPct', 'roi'], true) && $value !== '') {
                $value = str_replace('%', '', (string) $value);
            }
            $cells[$rowIndex][$specCol + $offset] = (string) $value;
        }

        return $cells;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    public function findCostCalculatorChannelRow(array $cells, string $channel, ?int $specCol = null): ?int
    {
        $specCol ??= $this->detectSpecColumnIndex($cells);
        $needle = strtolower(trim($channel));

        for ($rowIndex = 0; $rowIndex < count($cells); $rowIndex++) {
            $label = strtolower(trim((string) ($cells[$rowIndex][$specCol] ?? '')));
            if ($label === $needle || str_starts_with($label, $needle.' ')) {
                return $rowIndex;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     * @param  list<string>  $keywords
     */
    protected function columnMatchesKeywords(array $cells, int $colIndex, array $keywords): bool
    {
        if ($colIndex < 0) {
            return false;
        }

        $maxRows = min(count($cells), 8);
        for ($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++) {
            $text = strtolower(trim((string) ($cells[$rowIndex][$colIndex] ?? '')));
            if ($text === '') {
                continue;
            }

            foreach ($keywords as $keyword) {
                if (str_contains($text, strtolower($keyword))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    protected function stampColumnHeader(array &$cells, int $colIndex, string $header): void
    {
        $existing = trim((string) ($cells[0][$colIndex] ?? ''));
        if ($existing !== '' && ! $this->isPlaceholderSheetCellValue($existing)) {
            if ($this->isImageUrl($existing) || str_starts_with($existing, 'http')) {
                return;
            }
        }

        $cells[0][$colIndex] = $header;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    protected function insertColumnAt(array $cells, int $index): array
    {
        $index = max(0, $index);

        foreach ($cells as $rowIndex => $row) {
            if (! is_array($row)) {
                $row = [(string) $row];
            }
            array_splice($row, $index, 0, ['']);
            $cells[$rowIndex] = $row;
        }

        return $cells;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     * @return array<int, array<int, string>>
     */
    protected function moveColumn(array $cells, int $fromIndex, int $toIndex): array
    {
        if ($fromIndex === $toIndex) {
            return $cells;
        }

        foreach ($cells as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = $row[$fromIndex] ?? '';
            array_splice($row, $fromIndex, 1);
            array_splice($row, $toIndex, 0, [$value]);
            $cells[$rowIndex] = $row;
        }

        return $cells;
    }

    protected function parseSheetNumber(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value)) ?? '';
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    protected function detectLabelColumnIndex(array $cells): int
    {
        return $this->detectSpecColumnIndex($cells);
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    protected function findRowIndexByLabel(array $cells, string $labelNeedle, int $labelCol): ?int
    {
        foreach ($cells as $rowIndex => $row) {
            $label = strtolower(trim((string) ($row[$labelCol] ?? '')));
            if (str_contains($label, strtolower($labelNeedle))) {
                return $rowIndex;
            }
        }

        return null;
    }

    protected function quoteSheetTab(string $tab): string
    {
        $tab = trim($tab);
        if ($tab === '') {
            $tab = 'Sheet1';
        }
        if (preg_match('/[^A-Za-z0-9_]/', $tab)) {
            return "'" . str_replace("'", "''", $tab) . "'";
        }

        return $tab;
    }

    protected function createSheetsService(): ?Google_Service_Sheets
    {
        $credentialsPath = config('googlesheets.credentials_path', storage_path('app/google-credentials.json'));
        if (! is_readable($credentialsPath)) {
            return null;
        }

        try {
            $client = new Google_Client();
            $client->setApplicationName('Invent Comparison Sheet');
            $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
            $client->setAccessType('offline');
            $client->setAuthConfig($credentialsPath);

            return new Google_Service_Sheets($client);
        } catch (\Throwable $e) {
            Log::warning('Comparison sheet Sheets client init failed', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
