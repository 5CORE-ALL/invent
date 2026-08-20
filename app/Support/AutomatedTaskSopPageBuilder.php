<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;

class AutomatedTaskSopPageBuilder
{
    public static function htmlFromSopLink(string $link, string $title): string
    {
        $link = trim($link);
        $path = self::localPathFromLink($link);
        $publicUrl = self::publicUrlFromLink($link);

        if ($path && is_file($path)) {
            $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $inner = match ($ext) {
                'csv', 'xls', 'xlsx' => self::spreadsheetHtml($path),
                'txt' => self::textHtml($path),
                'png', 'jpg', 'jpeg', 'gif', 'webp' => self::imageHtml($publicUrl ?: $link),
                'pdf' => self::embedHtml($publicUrl ?: $link, 'pdf'),
                'docx' => self::docxHtml($path),
                default => self::fallbackFileHtml($publicUrl ?: $link, $ext),
            };

            return self::wrap($title, $inner, $publicUrl ?: $link);
        }

        if ($publicUrl !== '') {
            return self::wrap($title, self::fallbackFileHtml($publicUrl, ''), $publicUrl);
        }

        return self::wrap($title, '<p>No SOP file was found. Add content below.</p>', null);
    }

    public static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;
        $html = preg_replace('#javascript\s*:#i', '', $html) ?? $html;

        return $html;
    }

    /**
     * @return array{text: string, html: string, kind: string, fetched: bool, link: string, error: string|null}
     */
    public static function extractSourceMaterial(string $link): array
    {
        return AutomatedTaskSopSourceReader::read($link);
    }

    public static function googleDocumentId(string $link): ?string
    {
        if (preg_match('#docs\.google\.com/document/d/([a-zA-Z0-9_-]+)#', $link, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function googleSpreadsheetId(string $link): ?string
    {
        if (preg_match('#docs\.google\.com/spreadsheets/d/([a-zA-Z0-9_-]+)#', $link, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function localPathFromLink(string $link): ?string
    {
        $link = trim($link);
        if ($link === '') {
            return null;
        }

        if (preg_match('~/uploads/tasks/sop/([^/?#]+)~', $link, $m)) {
            $path = public_path('uploads/tasks/sop/'.$m[1]);

            return is_file($path) ? $path : null;
        }

        $pathPart = (string) (parse_url($link, PHP_URL_PATH) ?: '');
        if ($pathPart === '' && str_starts_with($link, '/')) {
            $pathPart = $link;
        }

        if (str_starts_with($pathPart, '/uploads/')) {
            $path = public_path(ltrim($pathPart, '/'));

            return is_file($path) ? $path : null;
        }

        if (preg_match('~/storage/(.+)$~', $pathPart !== '' ? $pathPart : $link, $m)) {
            $path = storage_path('app/public/'.$m[1]);

            return is_file($path) ? $path : null;
        }

        return null;
    }

    public static function publicUrlFromLink(string $link): string
    {
        $link = trim($link);
        if ($link === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $link) || str_starts_with($link, '/')) {
            return $link;
        }

        return '';
    }

    private static function wrap(string $title, string $inner, ?string $source): string
    {
        $heading = '<h1>'.e($title !== '' ? $title : 'SOP').'</h1>';
        $sourceHtml = $source
            ? '<p class="text-muted small">Source file: <a href="'.e($source).'" target="_blank" rel="noopener noreferrer">Open original</a></p>'
            : '';

        return $heading.$sourceHtml.$inner;
    }

    private static function textHtml(string $path): string
    {
        $raw = (string) @file_get_contents($path);

        return '<pre style="white-space:pre-wrap;">'.e($raw).'</pre>';
    }

    private static function imageHtml(string $url): string
    {
        return '<p><img src="'.e($url).'" alt="SOP" style="max-width:100%;height:auto;"></p>';
    }

    private static function embedHtml(string $url, string $kind): string
    {
        return '<iframe src="'.e($url).'" title="SOP '.$kind.'" style="width:100%;min-height:70vh;border:1px solid #dee2e6;border-radius:8px;"></iframe>';
    }

    private static function fallbackFileHtml(string $url, string $ext): string
    {
        $label = $ext !== '' ? strtoupper($ext).' file' : 'SOP file';

        return '<p>This SOP is a '.$label.'. <a href="'.e($url).'" target="_blank" rel="noopener noreferrer">Open the file</a>, then edit this page with the steps.</p>';
    }

    private static function spreadsheetHtml(string $path): string
    {
        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return '<p>Could not read the spreadsheet. <a href="'.e(asset('uploads/tasks/sop/'.basename($path))).'" target="_blank" rel="noopener noreferrer">Open the file</a>.</p>';
        }

        $html = '<div class="table-responsive"><table class="table table-bordered table-sm">';
        foreach ($rows as $i => $row) {
            if ($i > 200) {
                $html .= '<tr><td colspan="99">… truncated after 200 rows</td></tr>';
                break;
            }
            $html .= '<tr>';
            foreach ((array) $row as $cell) {
                $tag = $i === 0 ? 'th' : 'td';
                $html .= '<'.$tag.'>'.e((string) $cell).'</'.$tag.'>';
            }
            $html .= '</tr>';
        }
        $html .= '</table></div>';

        return $html;
    }

    private static function docxHtml(string $path): string
    {
        if (! class_exists(\ZipArchive::class)) {
            return self::fallbackFileHtml(asset('uploads/tasks/sop/'.basename($path)), 'docx');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return self::fallbackFileHtml(asset('uploads/tasks/sop/'.basename($path)), 'docx');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml) || $xml === '') {
            return self::fallbackFileHtml(asset('uploads/tasks/sop/'.basename($path)), 'docx');
        }

        $xml = str_replace(['</w:p>', '<w:tab/>'], ["\n", "\t"], $xml);
        $text = trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $paragraphs = preg_split("/\n+/", $text) ?: [];
        $html = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p !== '') {
                $html .= '<p>'.e($p).'</p>';
            }
        }

        return $html !== '' ? $html : '<p>No text could be read from the Word file.</p>';
    }

    private static function spreadsheetText(string $path): string
    {
        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return '';
        }

        $lines = [];
        foreach (array_slice($rows, 0, 200) as $row) {
            $cells = array_map(static fn ($v) => trim((string) $v), (array) $row);
            $line = implode("\t", $cells);
            if (trim($line) !== '') {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    private static function docxText(string $path): string
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

    private static function fetchUrlText(string $url): string
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get($url);
        } catch (\Throwable $e) {
            return '';
        }

        if (! $response->successful()) {
            return '';
        }

        $body = trim((string) $response->body());
        $ctype = strtolower((string) $response->header('Content-Type'));
        if ($body === '' || str_contains($ctype, 'text/html')) {
            if (stripos($body, 'Sign in') !== false || stripos($body, 'accounts.google.com') !== false) {
                return '';
            }
        }

        $text = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
