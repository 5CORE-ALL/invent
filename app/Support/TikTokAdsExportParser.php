<?php

namespace App\Support;

/**
 * Parse TikTok Ads Manager campaign exports (xlsx / csv / tab-separated txt).
 * Video titles often contain raw newlines, so TSV rows are rebuilt by column count.
 */
class TikTokAdsExportParser
{
    /**
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    public static function parse(string $path): array
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return self::parseSpreadsheet($path);
        }

        $sample = self::firstLine($path);
        if ($sample !== '' && substr_count($sample, "\t") >= 3) {
            return self::parseDelimited($path, "\t");
        }

        if (in_array($ext, ['csv'], true) || substr_count($sample, ',') >= 3) {
            return self::parseCsv($path);
        }

        return self::parseDelimited($path, "\t");
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private static function parseSpreadsheet(string $path): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        if ($rows === []) {
            return [[], []];
        }

        $headers = array_map(static fn ($h) => trim((string) $h), array_shift($rows) ?? []);
        $data = [];
        foreach ($rows as $row) {
            if (! is_array($row) || self::isEmptyRow($row) || self::isTotalRow($row)) {
                continue;
            }
            $data[] = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
        }

        return [$headers, $data];
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private static function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [[], []];
        }

        $headers = [];
        $data = [];
        $first = true;
        while (($row = fgetcsv($handle)) !== false) {
            if ($first) {
                $headers = array_map(static fn ($h) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), $row);
                $first = false;
                continue;
            }
            if (self::isEmptyRow($row) || self::isTotalRow($row)) {
                continue;
            }
            $data[] = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
        }
        fclose($handle);

        return [$headers, $data];
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private static function parseDelimited(string $path, string $delimiter): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = explode("\n", $raw);
        $headerLine = array_shift($lines) ?? '';
        $headers = array_map('trim', explode($delimiter, $headerLine));
        $expected = count($headers);
        if ($expected < 2) {
            return [[], []];
        }

        $titleIdx = self::titleColumnIndex($headers);
        $data = [];
        $buffer = '';
        foreach ($lines as $line) {
            $buffer = $buffer === '' ? $line : $buffer."\n".$line;
            $fields = explode($delimiter, $buffer);
            if (count($fields) < $expected) {
                continue;
            }
            if (count($fields) > $expected && $titleIdx !== null) {
                $extra = count($fields) - $expected;
                $merged = implode($delimiter, array_slice($fields, $titleIdx, $extra + 1));
                $fields = array_merge(
                    array_slice($fields, 0, $titleIdx),
                    [$merged],
                    array_slice($fields, $titleIdx + $extra + 1)
                );
            }
            $fields = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, array_slice($fields, 0, $expected));
            $buffer = '';
            if (self::isEmptyRow($fields) || self::isTotalRow($fields)) {
                continue;
            }
            $data[] = $fields;
        }

        return [$headers, $data];
    }

    /**
     * @param  list<string>  $headers
     */
    private static function titleColumnIndex(array $headers): ?int
    {
        foreach ($headers as $i => $header) {
            if (strcasecmp(trim((string) $header), 'Video title') === 0) {
                return $i;
            }
        }

        return isset($headers[4]) ? 4 : null;
    }

    /**
     * @param  list<mixed>  $row
     */
    private static function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $row
     */
    private static function isTotalRow(array $row): bool
    {
        $first = trim((string) ($row[0] ?? ''));

        return $first !== '' && stripos($first, 'Total') !== false;
    }

    private static function firstLine(string $path): string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return '';
        }
        $line = (string) fgets($handle);
        fclose($handle);

        return preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
    }
}
