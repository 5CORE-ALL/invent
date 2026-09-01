<?php

namespace App\Support;

/**
 * Parse TikTok Ads Manager campaign exports (xlsx / csv / tab-separated txt).
 * Video titles often contain raw newlines, so TSV rows are rebuilt by column count.
 * Uploaded temp files usually have no extension — detect format from bytes + original name.
 */
class TikTokAdsExportParser
{
    /**
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    public static function parse(string $path, ?string $originalExtension = null): array
    {
        $ext = strtolower((string) ($originalExtension ?: pathinfo($path, PATHINFO_EXTENSION)));
        if (in_array($ext, ['xlsx', 'xls'], true) || self::looksLikeSpreadsheet($path)) {
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
     * @param  list<string>  $headers
     */
    public static function hasCampaignId(array $headers): bool
    {
        foreach ($headers as $header) {
            if (self::isCampaignIdHeader($header)) {
                return true;
            }
        }

        return false;
    }

    public static function isCampaignIdHeader(mixed $header): bool
    {
        $key = self::normalizeHeader($header);

        return $key === 'campaignid';
    }

    public static function normalizeHeader(mixed $header): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?? (string) $header;
        $value = str_replace(["\u{00A0}", "\xC2\xA0"], ' ', $value);
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;

        return $value;
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

        [$headers, $dataRows] = self::splitHeaderAndRows($rows);
        $data = [];
        foreach ($dataRows as $row) {
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

        $all = [];
        while (($row = fgetcsv($handle)) !== false) {
            $all[] = $row;
        }
        fclose($handle);

        [$headers, $dataRows] = self::splitHeaderAndRows($all);
        $data = [];
        foreach ($dataRows as $row) {
            if (self::isEmptyRow($row) || self::isTotalRow($row)) {
                continue;
            }
            $data[] = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
        }

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

        $headerLine = '';
        $headerOffset = 0;
        foreach ($lines as $i => $line) {
            $candidate = array_map('trim', explode($delimiter, $line));
            if (self::hasCampaignId($candidate)) {
                $headerLine = $line;
                $headerOffset = $i + 1;
                break;
            }
        }
        if ($headerLine === '') {
            $headerLine = $lines[0] ?? '';
            $headerOffset = 1;
        }

        $headers = array_map(static fn ($h) => trim((string) $h), explode($delimiter, $headerLine));
        $expected = count($headers);
        if ($expected < 2) {
            return [[], []];
        }

        $titleIdx = self::titleColumnIndex($headers);
        $data = [];
        $buffer = '';
        $rest = array_slice($lines, $headerOffset);
        foreach ($rest as $line) {
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
     * @param  list<list<mixed>>  $rows
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private static function splitHeaderAndRows(array $rows): array
    {
        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $headers = array_map(static fn ($h) => trim((string) $h), $row);
            if (self::hasCampaignId($headers)) {
                return [$headers, array_slice($rows, $i + 1)];
            }
        }

        $first = array_map(static fn ($h) => trim((string) $h), $rows[0] ?? []);

        return [$first, array_slice($rows, 1)];
    }

    /**
     * @param  list<string>  $headers
     */
    private static function titleColumnIndex(array $headers): ?int
    {
        foreach ($headers as $i => $header) {
            if (self::normalizeHeader($header) === 'videotitle') {
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

    private static function looksLikeSpreadsheet(string $path): bool
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $magic = (string) fread($fh, 8);
        fclose($fh);

        return str_starts_with($magic, 'PK') || str_starts_with($magic, "\xD0\xCF\x11\xE0");
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
