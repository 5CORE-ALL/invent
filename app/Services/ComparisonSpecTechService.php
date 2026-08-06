<?php

namespace App\Services;

use App\Models\ComparisonData;
use App\Models\ComparisonSkuLink;

class ComparisonSpecTechService
{
    /** Meta / commercial Spec labels that should not appear in PO Tech. */
    private const SKIP_LABELS = [
        'supplier name',
        'supplier',
        'suppliers',
        'critical',
        'qc',
        'amazon',
        '5 core',
        '5core',
        'spec',
        'specs',
        'product photo',
        'person name review',
        'supplier link',
        'link',
        'reviews',
        'company name',
        'company',
        'comm',
        'qty',
        'quantity',
        'price',
        'price usd',
        'price rmb',
        'price usd (pair)',
        'supplier price',
        'supplier price (usd)',
        'supplier price usd',
        'supplier price usd (pair)',
        'rmb',
        'nw',
        'gw',
        'nw (lb)',
        'gw (lb)',
        'nw (lb) / gw per pcs (lb)',
        'nw (kg)',
        'gw (kg)',
        'cbm',
    ];

    public function __construct(
        private ComparisonSheetService $sheetService,
        private ComparisonSheetStorage $sheetStorage,
    ) {
    }

    /**
     * Build Tech text from comparison sheet Spec labels + 5 Core values.
     *
     * @param  list<string>  $skus
     * @return array<string, string> SKU => tech text
     */
    public function techBySkus(array $skus): array
    {
        $map = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || array_key_exists($sku, $map)) {
                continue;
            }
            $map[$sku] = $this->techForSku($sku);
        }

        return $map;
    }

    public function techForSku(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }

        $cells = $this->loadComparisonCellsForSku($sku);
        if ($cells === null) {
            return '';
        }

        return $this->formatTechFromCells($cells);
    }

    /**
     * NW / GW in KG from comparison Spec rows (prefers kg labels; converts lb → kg when needed).
     *
     * @param  list<string>  $skus
     * @return array<string, array{nw: ?string, gw: ?string}>
     */
    public function weightsKgBySkus(array $skus): array
    {
        $map = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || array_key_exists($sku, $map)) {
                continue;
            }
            $map[$sku] = $this->weightsKgForSku($sku);
        }

        return $map;
    }

    /**
     * @return array{nw: ?string, gw: ?string}
     */
    public function weightsKgForSku(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['nw' => null, 'gw' => null];
        }

        $cells = $this->loadComparisonCellsForSku($sku);
        if ($cells === null) {
            return ['nw' => null, 'gw' => null];
        }

        return $this->extractWeightsKgFromCells($cells);
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     * @return array{nw: ?string, gw: ?string}
     */
    private function extractWeightsKgFromCells(array $cells): array
    {
        $specCol = $this->sheetService->detectSpecColumnIndex($cells);
        $fiveCoreCol = max(0, $specCol - 1);

        $nwKg = null;
        $gwKg = null;
        $nwLb = null;
        $gwLb = null;

        foreach ($cells as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row[$specCol] ?? '')) ?? ''));
            if ($label === '') {
                continue;
            }

            $value = $this->cellDisplayValue($row[$fiveCoreCol] ?? '');
            if ($value === '') {
                continue;
            }

            $numeric = $this->parseWeightNumber($value);
            if ($numeric === null) {
                continue;
            }

            // Prefer explicit kg labels.
            if (preg_match('/\bnw\b.*\(?\s*kg\s*\)?/i', $label) || $label === 'nw (kg)' || $label === 'nw kg') {
                $nwKg = $numeric;
                continue;
            }
            if (preg_match('/\bgw\b.*\(?\s*kg\s*\)?/i', $label) || $label === 'gw (kg)' || $label === 'gw kg') {
                $gwKg = $numeric;
                continue;
            }

            // Combined "NW (lb) / GW per pcs (lb)" style rows.
            if (str_contains($label, 'nw') && str_contains($label, 'gw') && str_contains($label, 'lb')) {
                if (preg_match('/^\s*([0-9]+(?:\.[0-9]+)?)\s*[\/|]\s*([0-9]+(?:\.[0-9]+)?)/', $value, $m)) {
                    $nwLb = (float) $m[1];
                    $gwLb = (float) $m[2];
                }
                continue;
            }

            if (preg_match('/\bnw\b.*\(?\s*lb\s*\)?/i', $label) || $label === 'nw (lb)' || $label === 'nw lb' || $label === 'nw') {
                $nwLb = $numeric;
                continue;
            }
            if (preg_match('/\bgw\b.*\(?\s*lb\s*\)?/i', $label) || $label === 'gw (lb)' || $label === 'gw lb' || $label === 'gw') {
                $gwLb = $numeric;
            }
        }

        $nw = $nwKg;
        $gw = $gwKg;
        if ($nw === null && $nwLb !== null) {
            $nw = round($nwLb / 2.2046226218, 3);
        }
        if ($gw === null && $gwLb !== null) {
            $gw = round($gwLb / 2.2046226218, 3);
        }

        return [
            'nw' => $nw !== null ? $this->formatWeightNumber($nw) : null,
            'gw' => $gw !== null ? $this->formatWeightNumber($gw) : null,
        ];
    }

    /**
     * CBM from comparison Spec rows (label "CBM").
     *
     * @param  list<string>  $skus
     * @return array<string, ?string>
     */
    public function cbmBySkus(array $skus): array
    {
        $map = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || array_key_exists($sku, $map)) {
                continue;
            }
            $map[$sku] = $this->cbmForSku($sku);
        }

        return $map;
    }

    public function cbmForSku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $cells = $this->loadComparisonCellsForSku($sku);
        if ($cells === null) {
            return null;
        }

        return $this->extractCbmFromCells($cells);
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    private function extractCbmFromCells(array $cells): ?string
    {
        $specCol = $this->sheetService->detectSpecColumnIndex($cells);
        $fiveCoreCol = max(0, $specCol - 1);

        foreach ($cells as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row[$specCol] ?? '')) ?? ''));
            if ($label === '' || ! preg_match('/\bcbm\b/i', $label)) {
                continue;
            }

            $value = $this->cellDisplayValue($row[$fiveCoreCol] ?? '');
            if ($value === '') {
                continue;
            }

            $numeric = $this->parseWeightNumber($value);
            if ($numeric === null) {
                continue;
            }

            return $this->formatCbmNumber($numeric);
        }

        return null;
    }

    private function parseWeightNumber(string $value): ?float
    {
        if (! preg_match('/-?\d+(?:\.\d+)?/', $value, $m)) {
            return null;
        }

        return (float) $m[0];
    }

    private function formatWeightNumber(float $n): string
    {
        $formatted = rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private function formatCbmNumber(float $n): string
    {
        $formatted = rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * @return array<int, array<int, string>>|null
     */
    private function loadComparisonCellsForSku(string $sku): ?array
    {
        $candidates = $this->candidateSheetSkus($sku);

        foreach ($candidates as $candidate) {
            $cells = $this->loadCellsExact($candidate);
            if ($cells !== null && $this->sheetHasUsefulSpecRows($cells)) {
                return $cells;
            }
        }

        // Last resort: first non-null cells even if sparse.
        foreach ($candidates as $candidate) {
            $cells = $this->loadCellsExact($candidate);
            if ($cells !== null) {
                return $cells;
            }
        }

        return $this->loadCellsByPrefix($sku);
    }

    /**
     * @return list<string>
     */
    private function candidateSheetSkus(string $sku): array
    {
        $out = [$sku];
        $norm = strtoupper($sku);

        try {
            $links = ComparisonSkuLink::query()
                ->where('sku_norm', $norm)
                ->orWhere('linked_sku_norm', $norm)
                ->limit(40)
                ->get(['sku', 'linked_sku']);

            foreach ($links as $link) {
                foreach ([$link->sku ?? '', $link->linked_sku ?? ''] as $member) {
                    $member = trim((string) $member);
                    if ($member !== '') {
                        $out[] = $member;
                    }
                }
            }
        } catch (\Throwable) {
            // Linked SKUs are optional; exact + prefix still work.
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<int, array<int, string>>|null
     */
    private function loadCellsExact(string $sku): ?array
    {
        $filePayload = $this->sheetStorage->load($sku);
        $fileCells = is_array($filePayload) && ! empty($filePayload['cells']) && is_array($filePayload['cells'])
            ? ComparisonData::normalizeCells($filePayload['cells'])
            : null;

        $record = ComparisonData::query()
            ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])
            ->first();

        $dbCells = is_array($record?->sheet_data['cells'] ?? null)
            ? ComparisonData::normalizeCells($record->sheet_data['cells'])
            : null;

        // Prefer the grid that actually carries Spec/5-Core pairs.
        $cells = null;
        if ($fileCells !== null && $this->sheetHasUsefulSpecRows($fileCells)) {
            $cells = $fileCells;
        } elseif ($dbCells !== null && $this->sheetHasUsefulSpecRows($dbCells)) {
            $cells = $dbCells;
        } else {
            $cells = $fileCells ?? $dbCells;
        }

        if ($cells === null) {
            return null;
        }

        return ComparisonData::normalizeCells($this->sheetService->ensureLeadColumns($cells));
    }

    /**
     * Prefix fallback for sized variants (e.g. "CS 04 2W BLK" → "CS 04 2W").
     *
     * @return array<int, array<int, string>>|null
     */
    private function loadCellsByPrefix(string $sku): ?array
    {
        $parts = preg_split('/\s+/', $sku) ?: [];
        if (count($parts) < 2) {
            return null;
        }

        $prefix = implode(' ', array_slice($parts, 0, min(3, count($parts) - 1)));
        if ($prefix === '') {
            return null;
        }

        $candidates = ComparisonData::query()
            ->where('sku', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(sku) DESC')
            ->limit(25)
            ->pluck('sku');

        $bestCells = null;
        $bestLen = -1;

        foreach ($candidates as $candidateSku) {
            $candidateSku = trim((string) $candidateSku);
            if ($candidateSku === '' || strlen($candidateSku) > strlen($sku)) {
                continue;
            }
            if ($sku !== $candidateSku && ! str_starts_with($sku, $candidateSku . ' ')) {
                continue;
            }

            $cells = $this->loadCellsExact($candidateSku);
            if ($cells === null) {
                continue;
            }
            if (strlen($candidateSku) > $bestLen && $this->sheetHasUsefulSpecRows($cells)) {
                $bestLen = strlen($candidateSku);
                $bestCells = $cells;
            }
        }

        return $bestCells;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    private function sheetHasUsefulSpecRows(array $cells): bool
    {
        return $this->formatTechFromCells($cells) !== '';
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    private function formatTechFromCells(array $cells): string
    {
        $specCol = $this->sheetService->detectSpecColumnIndex($cells);
        $fiveCoreCol = max(0, $specCol - 1);
        $lines = [];

        foreach ($cells as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }

            if ((int) $rowIndex === 0) {
                $headerCandidate = strtolower(trim((string) ($row[$specCol] ?? '')));
                if (in_array($headerCandidate, ['spec', 'specs', 'supplier name', ''], true)) {
                    continue;
                }
            }

            if ($this->sheetService->isSupplierNameRow($cells, (int) $rowIndex, $specCol)) {
                continue;
            }

            $label = trim((string) ($row[$specCol] ?? ''));
            if ($label === '') {
                continue;
            }

            $labelKey = strtolower(preg_replace('/\s+/', ' ', $label) ?? $label);
            if (in_array($labelKey, self::SKIP_LABELS, true)) {
                continue;
            }

            // Broad commercial skips (price / weight rows with variant wording).
            if (preg_match('/\b(price|usd|rmb|¥|\$|nw|gw|cbm|qty|quantity|photo|link|review)\b/i', $label)) {
                continue;
            }

            $value = $this->cellDisplayValue($row[$fiveCoreCol] ?? '');
            if ($value === '') {
                continue;
            }

            // Skip placeholder / column-header bleed into 5 Core.
            if (in_array(strtolower($value), ['5 core', '5core', 'amazon', 'spec'], true)) {
                continue;
            }

            $line = $label . ': ' . $value;
            if (! in_array($line, $lines, true)) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    private function cellDisplayValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (str_starts_with(strtolower($text), 'data:image/')
            || str_starts_with($text, '[[photo:')
            || str_starts_with($text, '[[cmp-photo:')
            || str_contains(strtolower($text), 'base64,')) {
            return '';
        }

        if (preg_match('#^https?://#i', $text)) {
            return '';
        }

        if (mb_strlen($text) > 200) {
            return mb_substr($text, 0, 200) . '…';
        }

        return $text;
    }

    /**
     * Pull Supplier PRICE USD / Price RMB from comparison sheet-view for the relevant supplier.
     * Prefer USD as-is; if only RMB is present, convert to USD.
     *
     * @return array{
     *   price_usd: ?float,
     *   source: ?string,
     *   supplier_col: ?int,
     *   supplier_matched: string,
     *   is_lowest: bool,
     *   lowest_usd: ?float,
     *   found: bool
     * }
     */
    public function priceForRelevantSupplier(string $sku, string $supplierName, ?float $rmbToUsd = null): array
    {
        $empty = [
            'price_usd' => null,
            'source' => null,
            'supplier_col' => null,
            'supplier_matched' => '',
            'is_lowest' => true,
            'lowest_usd' => null,
            'found' => false,
        ];

        $sku = trim($sku);
        $supplierName = trim($supplierName);
        if ($sku === '' || $supplierName === '') {
            return $empty;
        }

        $cells = $this->loadComparisonCellsForSku($sku);
        if ($cells === null) {
            return $empty;
        }

        $cells = $this->sheetService->ensureLeadColumns($cells);
        $specCol = $this->sheetService->detectSpecColumnIndex($cells);
        $firstSupplierCol = $this->sheetService->getFirstSupplierColumnIndex($cells, $specCol);

        $maxCols = 0;
        foreach ($cells as $row) {
            if (is_array($row)) {
                $maxCols = max($maxCols, count($row));
            }
        }
        if ($firstSupplierCol >= $maxCols) {
            return $empty;
        }

        $usdRow = $this->sheetService->findRowIndexByLabels($cells, [
            'supplier price (usd)',
            'supplier price usd',
            'supplier price',
            'price usd',
            'usd',
        ], $specCol);
        $rmbRow = $this->sheetService->findRowIndexByLabels($cells, [
            'price rmb',
            'supplier price rmb',
            'rmb',
        ], $specCol);

        if ($usdRow === null && $rmbRow === null) {
            return $empty;
        }

        $fx = ($rmbToUsd !== null && $rmbToUsd > 0) ? $rmbToUsd : (1 / 7.2);

        $usdPriceAtCol = function (int $col) use ($cells, $usdRow, $rmbRow, $fx): ?float {
            if ($usdRow !== null) {
                $usd = $this->sheetService->parseSheetNumber((string) ($cells[$usdRow][$col] ?? ''));
                if ($usd !== null && $usd > 0) {
                    return round($usd, 2);
                }
            }
            if ($rmbRow !== null) {
                $rmb = $this->sheetService->parseSheetNumber((string) ($cells[$rmbRow][$col] ?? ''));
                if ($rmb !== null && $rmb > 0) {
                    return round($rmb * $fx, 2);
                }
            }

            return null;
        };

        $sourceAtCol = function (int $col) use ($cells, $usdRow, $rmbRow): ?string {
            if ($usdRow !== null) {
                $usd = $this->sheetService->parseSheetNumber((string) ($cells[$usdRow][$col] ?? ''));
                if ($usd !== null && $usd > 0) {
                    return 'usd';
                }
            }
            if ($rmbRow !== null) {
                $rmb = $this->sheetService->parseSheetNumber((string) ($cells[$rmbRow][$col] ?? ''));
                if ($rmb !== null && $rmb > 0) {
                    return 'rmb';
                }
            }

            return null;
        };

        $supplierCol = $this->resolveSupplierColumn($cells, $specCol, $firstSupplierCol, $maxCols, $supplierName);
        if ($supplierCol === null) {
            return $empty;
        }

        $priceUsd = $usdPriceAtCol($supplierCol);
        if ($priceUsd === null) {
            return $empty;
        }

        $lowestUsd = null;
        $lowestCol = null;
        for ($col = $firstSupplierCol; $col < $maxCols; $col++) {
            $p = $usdPriceAtCol($col);
            if ($p === null) {
                continue;
            }
            if ($lowestUsd === null || $p < $lowestUsd) {
                $lowestUsd = $p;
                $lowestCol = $col;
            }
        }

        $matchedName = $this->supplierNameAtColumn($cells, $specCol, $supplierCol);
        $isLowest = $lowestCol === null || $supplierCol === $lowestCol
            || ($lowestUsd !== null && abs($priceUsd - $lowestUsd) < 0.005);

        return [
            'price_usd' => $priceUsd,
            'source' => $sourceAtCol($supplierCol),
            'supplier_col' => $supplierCol,
            'supplier_matched' => $matchedName,
            'is_lowest' => $isLowest,
            'lowest_usd' => $lowestUsd,
            'found' => true,
        ];
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array{
     *   price_usd: ?float,
     *   source: ?string,
     *   supplier_col: ?int,
     *   supplier_matched: string,
     *   is_lowest: bool,
     *   lowest_usd: ?float,
     *   found: bool
     * }>
     */
    public function pricesForRelevantSupplierBySkus(array $skus, string $supplierName, ?float $rmbToUsd = null): array
    {
        $map = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || array_key_exists($sku, $map)) {
                continue;
            }
            $map[$sku] = $this->priceForRelevantSupplier($sku, $supplierName, $rmbToUsd);
        }

        return $map;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    private function resolveSupplierColumn(
        array $cells,
        int $specCol,
        int $firstSupplierCol,
        int $maxCols,
        string $supplierName
    ): ?int {
        $target = strtoupper(trim($supplierName));
        if ($target === '') {
            return null;
        }

        $supplierRow = null;
        foreach ($cells as $rowIndex => $row) {
            if ($this->sheetService->isSupplierNameRow($cells, (int) $rowIndex, $specCol)) {
                $supplierRow = (int) $rowIndex;
                break;
            }
        }

        if ($supplierRow === null) {
            return null;
        }

        $row = $cells[$supplierRow] ?? [];
        $contains = null;
        for ($col = $firstSupplierCol; $col < $maxCols; $col++) {
            $name = strtoupper(trim((string) ($row[$col] ?? '')));
            if ($name === '') {
                continue;
            }
            if ($name === $target) {
                return $col;
            }
            if ($contains === null && (str_contains($name, $target) || str_contains($target, $name))) {
                $contains = $col;
            }
        }

        return $contains;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    private function supplierNameAtColumn(array $cells, int $specCol, int $col): string
    {
        foreach ($cells as $rowIndex => $row) {
            if (! $this->sheetService->isSupplierNameRow($cells, (int) $rowIndex, $specCol)) {
                continue;
            }

            return trim((string) ($row[$col] ?? ''));
        }

        return '';
    }
}
