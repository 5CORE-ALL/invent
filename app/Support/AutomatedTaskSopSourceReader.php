<?php

namespace App\Support;

use Google_Client;
use Google_Service_Docs;
use Google_Service_Sheets;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutomatedTaskSopSourceReader
{
    /**
     * @return array{text: string, html: string, kind: string, fetched: bool, link: string, error: string|null}
     */
    public static function read(string $link): array
    {
        $link = trim($link);
        $empty = ['text' => '', 'html' => '', 'kind' => 'url', 'fetched' => false, 'link' => $link, 'error' => null];
        if ($link === '') {
            $empty['error'] = 'No SOP link.';

            return $empty;
        }

        $path = AutomatedTaskSopPageBuilder::localPathFromLink($link);
        if ($path && is_file($path)) {
            return self::fromLocalFile($path, $link);
        }

        $docId = AutomatedTaskSopPageBuilder::googleDocumentId($link);
        if ($docId) {
            return self::fromGoogleDoc($docId, $link);
        }

        $sheetId = AutomatedTaskSopPageBuilder::googleSpreadsheetId($link);
        if ($sheetId) {
            return self::fromGoogleSheet($sheetId, $link);
        }

        $driveId = self::googleDriveFileId($link);
        if ($driveId) {
            return self::fromGoogleDriveFile($driveId, $link);
        }

        if (preg_match('#^https?://#i', $link)) {
            return self::fromWebLink($link);
        }

        $empty['error'] = 'Unsupported SOP link.';

        return $empty;
    }

    public static function sourceSection(array $material): string
    {
        $link = trim((string) ($material['link'] ?? ''));
        $fetched = ! empty($material['fetched']);
        $kind = (string) ($material['kind'] ?? 'url');
        $inner = trim((string) ($material['html'] ?? ''));
        $text = trim((string) ($material['text'] ?? ''));
        $error = trim((string) ($material['error'] ?? ''));
        $label = match ($kind) {
            'google_sheet', 'file_sheet' => 'Sheet data',
            'google_doc', 'file_doc' => 'Document data',
            'file' => 'File data',
            default => 'Linked data',
        };

        if ($inner === '' && $text !== '') {
            $inner = '<pre class="sop-source-pre">'.e(mb_substr($text, 0, 20000)).'</pre>';
        }

        $status = $fetched ? 'ok' : 'miss';
        $html = '<section class="sop-source-data" data-sop-source="'.$status.'"><h2>'.$label.'</h2>';
        if ($fetched && $inner !== '') {
            $html .= $inner;
        } else {
            $html .= '<p>The SOP link could not be read automatically'
                .($error !== '' ? ' ('.e($error).')' : '')
                .'. Open the original file and follow those steps.</p>';
            $shareHint = self::serviceAccountEmail();
            if ($shareHint !== '' && ($kind === 'google_doc' || $kind === 'google_sheet')) {
                $html .= '<p class="text-muted small">Share the Google file with <strong>'.e($shareHint).'</strong> as Viewer so this page can load the real content.</p>';
            }
        }
        if ($link !== '') {
            $html .= '<p class="text-muted small mb-0">Original: <a href="'.e($link).'" target="_blank" rel="noopener noreferrer">Open SOP link</a></p>';
        }
        $html .= '</section>';

        return $html;
    }

    /**
     * @return array{text: string, html: string, kind: string, fetched: bool, link: string, error: string|null}
     */
    private static function fromLocalFile(string $path, string $link): array
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $text = '';
        $html = '';
        $kind = 'file';

        if (in_array($ext, ['csv', 'xls', 'xlsx'], true)) {
            $text = self::localSpreadsheetText($path);
            $html = self::rowsToTable(self::tsvToRows($text));
            $kind = 'file_sheet';
        } elseif ($ext === 'txt' || $ext === 'csv') {
            $text = (string) @file_get_contents($path);
            $html = '<pre class="sop-source-pre">'.e(mb_substr($text, 0, 20000)).'</pre>';
        } elseif ($ext === 'docx') {
            $text = self::localDocxText($path);
            $html = self::textToParagraphs($text);
            $kind = 'file_doc';
        }

        $text = trim($text);

        return [
            'text' => mb_substr($text, 0, 40000),
            'html' => $html,
            'kind' => $kind,
            'fetched' => $text !== '',
            'link' => $link,
            'error' => $text === '' ? 'Could not read the uploaded file.' : null,
        ];
    }

    /**
     * @return array{text: string, html: string, kind: string, fetched: bool, link: string, error: string|null}
     */
    private static function fromGoogleDoc(string $docId, string $link): array
    {
        $text = self::googleDocsApiText($docId);
        if ($text === '') {
            $text = self::googleDriveExportText($docId, 'text/plain');
        }
        if ($text === '') {
            $text = self::fetchPublicText([
                'https://docs.google.com/document/d/'.$docId.'/export?format=txt',
                'https://docs.google.com/feeds/download/documents/export/Export?id='.$docId.'&exportFormat=txt',
                'https://docs.google.com/document/d/'.$docId.'/pub',
            ]);
        }

        $text = trim($text);

        return [
            'text' => mb_substr($text, 0, 40000),
            'html' => $text !== '' ? self::textToParagraphs($text) : '',
            'kind' => 'google_doc',
            'fetched' => $text !== '',
            'link' => $link,
            'error' => $text === '' ? 'Google Doc is private or not shared with the app.' : null,
        ];
    }

    /**
     * @return array{text: string, html: string, kind: string, fetched: bool, link: string, error: string|null}
     */
    private static function fromGoogleSheet(string $sheetId, string $link): array
    {
        $gid = self::googleSheetGid($link);
        $rows = self::googleSheetsApiRows($sheetId, $gid);
        if ($rows === []) {
            $csv = self::googleDriveExportText($sheetId, 'text/csv');
            if ($csv === '') {
                $export = 'https://docs.google.com/spreadsheets/d/'.$sheetId.'/export?format=csv';
                if ($gid !== null) {
                    $export .= '&gid='.$gid;
                }
                $csv = self::fetchPublicText([
                    $export,
                    'https://docs.google.com/spreadsheets/d/'.$sheetId.'/gviz/tq?tqx=out:csv'.($gid !== null ? '&gid='.$gid : ''),
                ]);
            }
            $rows = self::csvToRows($csv);
        }

        $text = self::rowsToTsv($rows);

        return [
            'text' => mb_substr($text, 0, 40000),
            'html' => $rows !== [] ? self::rowsToTable($rows) : '',
            'kind' => 'google_sheet',
            'fetched' => $text !== '',
            'link' => $link,
            'error' => $text === '' ? 'Google Sheet is private or not shared with the app.' : null,
        ];
    }

    /**
     * @return array{text: string, html: string, kind: string, fetched: bool, link: string, error: string|null}
     */
    private static function fromGoogleDriveFile(string $fileId, string $link): array
    {
        $text = self::googleDriveExportText($fileId, 'text/plain');
        if ($text === '') {
            $text = self::googleDriveExportText($fileId, 'text/csv');
        }
        $text = trim($text);
        $rows = self::csvToRows($text);
        $looksLikeSheet = count($rows) > 1;

        return [
            'text' => mb_substr($text, 0, 40000),
            'html' => $looksLikeSheet ? self::rowsToTable($rows) : ($text !== '' ? self::textToParagraphs($text) : ''),
            'kind' => $looksLikeSheet ? 'google_sheet' : 'google_doc',
            'fetched' => $text !== '',
            'link' => $link,
            'error' => $text === '' ? 'Google Drive file is private or not shared with the app.' : null,
        ];
    }

    /**
     * @return array{text: string, html: string, kind: string, fetched: bool, link: string, error: string|null}
     */
    private static function fromWebLink(string $link): array
    {
        $text = self::fetchPublicText([$link]);
        $text = trim($text);

        return [
            'text' => mb_substr($text, 0, 40000),
            'html' => $text !== '' ? self::textToParagraphs($text) : '',
            'kind' => 'url',
            'fetched' => $text !== '',
            'link' => $link,
            'error' => $text === '' ? 'Could not read the linked page.' : null,
        ];
    }

    public static function googleDriveFileId(string $link): ?string
    {
        if (preg_match('#drive\.google\.com/file/d/([a-zA-Z0-9_-]+)#', $link, $m)) {
            return $m[1];
        }
        if (preg_match('#drive\.google\.com/open\?id=([a-zA-Z0-9_-]+)#', $link, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function googleSheetGid(string $link): ?int
    {
        if (preg_match('/(?:[?&#]gid=)([0-9]+)/', $link, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private static function googleClient(): ?Google_Client
    {
        $path = (string) config('googlesheets.credentials_path', storage_path('app/google-credentials.json'));
        if (! is_readable($path)) {
            return null;
        }

        try {
            $client = new Google_Client();
            $client->setApplicationName('Invent SOP Pages');
            $client->setAuthConfig($path);
            $client->setScopes([
                Google_Service_Sheets::SPREADSHEETS_READONLY,
                Google_Service_Docs::DOCUMENTS_READONLY,
                Google_Service_Drive::DRIVE_READONLY,
            ]);
            $client->setAccessType('offline');

            return $client;
        } catch (\Throwable $e) {
            Log::warning('SOP Google client failed', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    private static function googleAccessToken(): ?string
    {
        $client = self::googleClient();
        if (! $client) {
            return null;
        }
        try {
            $token = $client->fetchAccessTokenWithAssertion();

            return is_array($token) ? ($token['access_token'] ?? null) : null;
        } catch (\Throwable $e) {
            Log::warning('SOP Google token failed', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    private static function serviceAccountEmail(): string
    {
        $path = (string) config('googlesheets.credentials_path', storage_path('app/google-credentials.json'));
        if (! is_readable($path)) {
            return '';
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? trim((string) ($json['client_email'] ?? '')) : '';
    }

    private static function googleDocsApiText(string $docId): string
    {
        $client = self::googleClient();
        if (! $client) {
            return '';
        }
        try {
            $docs = new Google_Service_Docs($client);
            $document = $docs->documents->get($docId);
            $body = $document->getBody();
            if (! $body) {
                return '';
            }

            return trim(self::docsElementsText($body->getContent() ?? []));
        } catch (\Throwable $e) {
            Log::info('SOP Docs API read failed', ['doc' => $docId, 'msg' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * @param  array<int, mixed>  $elements
     */
    private static function docsElementsText(array $elements): string
    {
        $out = '';
        foreach ($elements as $el) {
            if (! is_object($el)) {
                continue;
            }
            if (method_exists($el, 'getParagraph') && $el->getParagraph()) {
                $para = $el->getParagraph();
                $line = '';
                foreach ($para->getElements() ?? [] as $piece) {
                    if (is_object($piece) && method_exists($piece, 'getTextRun') && $piece->getTextRun()) {
                        $line .= (string) ($piece->getTextRun()->getContent() ?? '');
                    }
                }
                $out .= $line;
                if (! str_ends_with($line, "\n")) {
                    $out .= "\n";
                }
            }
            if (method_exists($el, 'getTable') && $el->getTable()) {
                foreach ($el->getTable()->getTableRows() ?? [] as $row) {
                    $cells = [];
                    foreach ($row->getTableCells() ?? [] as $cell) {
                        $cells[] = trim(self::docsElementsText($cell->getContent() ?? []));
                    }
                    $out .= implode("\t", $cells)."\n";
                }
            }
        }

        return $out;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function googleSheetsApiRows(string $spreadsheetId, ?int $gid): array
    {
        $client = self::googleClient();
        if (! $client) {
            return [];
        }
        try {
            $sheets = new Google_Service_Sheets($client);
            $range = 'A1:Z300';
            if ($gid !== null) {
                $ss = $sheets->spreadsheets->get($spreadsheetId, ['fields' => 'sheets(properties(sheetId,title))']);
                foreach ($ss->getSheets() ?? [] as $sheet) {
                    $props = $sheet->getProperties();
                    if ($props && (int) $props->getSheetId() === $gid) {
                        $title = (string) $props->getTitle();
                        $range = "'".str_replace("'", "''", $title)."'!A1:Z300";
                        break;
                    }
                }
            }
            $result = $sheets->spreadsheets_values->get($spreadsheetId, $range);
            $values = $result->getValues() ?? [];

            return self::normalizeRows($values);
        } catch (\Throwable $e) {
            Log::info('SOP Sheets API read failed', ['sheet' => $spreadsheetId, 'msg' => $e->getMessage()]);

            return [];
        }
    }

    private static function googleDriveExportText(string $fileId, string $mime): string
    {
        $token = self::googleAccessToken();
        if (! $token) {
            return '';
        }
        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->accept($mime)
                ->get('https://www.googleapis.com/drive/v3/files/'.$fileId.'/export', [
                    'mimeType' => $mime,
                ]);
        } catch (\Throwable $e) {
            return '';
        }
        if (! $response->successful()) {
            return '';
        }
        $body = trim((string) $response->body());
        if ($body === '' || str_contains(strtolower($body), 'sign in')) {
            return '';
        }

        return self::plainFromBody($body, (string) $response->header('Content-Type'));
    }

    /**
     * @param  list<string>  $urls
     */
    private static function fetchPublicText(array $urls): string
    {
        $token = self::googleAccessToken();
        foreach ($urls as $url) {
            try {
                $request = Http::timeout(25)->withHeaders(['User-Agent' => 'Mozilla/5.0 InventSOP/1.0']);
                if ($token && str_contains($url, 'docs.google.com')) {
                    $request = $request->withToken($token);
                }
                $response = $request->get($url);
            } catch (\Throwable $e) {
                continue;
            }
            if (! $response->successful()) {
                continue;
            }
            $text = self::plainFromBody((string) $response->body(), (string) $response->header('Content-Type'));
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private static function plainFromBody(string $body, string $ctype): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }
        $ctype = strtolower($ctype);
        if (str_contains($ctype, 'text/html') || str_starts_with($body, '<!DOCTYPE') || str_starts_with($body, '<html')) {
            if (stripos($body, 'accounts.google.com') !== false && stripos($body, 'Sign in') !== false) {
                return '';
            }
            $body = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $body) ?? $body;
            $body = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $body) ?? $body;
        }
        $text = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        if (stripos($text, 'Sign in') !== false && strlen($text) < 400) {
            return '';
        }

        return trim($text);
    }

    private static function textToParagraphs(string $text): string
    {
        $parts = preg_split("/\n+/", $text) ?: [];
        $html = '';
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $html .= '<p>'.e($p).'</p>';
            }
        }

        return $html;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private static function rowsToTable(array $rows): string
    {
        if ($rows === []) {
            return '';
        }
        $html = '<div class="table-responsive"><table class="table table-bordered table-sm sop-source-table">';
        foreach (array_slice($rows, 0, 250) as $i => $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $tag = $i === 0 ? 'th' : 'td';
                $html .= '<'.$tag.'>'.e($cell).'</'.$tag.'>';
            }
            $html .= '</tr>';
        }
        $html .= '</table></div>';

        return $html;
    }

    /**
     * @param  array<int, array<int, mixed>>  $values
     * @return array<int, array<int, string>>
     */
    private static function normalizeRows(array $values): array
    {
        $rows = [];
        foreach ($values as $row) {
            $cells = array_map(static fn ($v) => trim((string) $v), (array) $row);
            if (implode('', $cells) === '') {
                continue;
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private static function rowsToTsv(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = implode("\t", $row);
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function csvToRows(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '') {
            return [];
        }
        $rows = [];
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return self::tsvToRows($csv);
        }
        fwrite($fh, $csv);
        rewind($fh);
        while (($data = fgetcsv($fh)) !== false) {
            $cells = array_map(static fn ($v) => trim((string) $v), $data);
            if (implode('', $cells) !== '') {
                $rows[] = $cells;
            }
        }
        fclose($fh);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function tsvToRows(string $tsv): array
    {
        $rows = [];
        foreach (preg_split("/\n/", $tsv) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $rows[] = array_map('trim', explode("\t", $line));
        }

        return $rows;
    }

    private static function localSpreadsheetText(string $path): string
    {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return '';
        }
        $lines = [];
        foreach (array_slice($rows, 0, 250) as $row) {
            $line = implode("\t", array_map(static fn ($v) => trim((string) $v), (array) $row));
            if (trim($line) !== '') {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    private static function localDocxText(string $path): string
    {
        if (! class_exists(\ZipArchive::class)) {
            return '';
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml) || $xml === '') {
            return '';
        }
        $xml = str_replace(['</w:p>', '<w:tab/>'], ["\n", "\t"], $xml);

        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
