<?php

namespace App\Services\Wayfair;

class WayfairPriceUploadPlanner
{
    /**
     * @param  array<string, float|int|string>  $current
     * @param  array<string, float|int|string>|null  $previous
     * @return array<string, float>
     */
    public function changedSkus(array $current, ?array $previous): array
    {
        $previous = $previous ?? [];
        $changed = [];
        foreach ($current as $sku => $price) {
            $cents = $this->cents($price);
            $had = array_key_exists($sku, $previous);
            if (! $had || $this->cents($previous[$sku]) !== $cents) {
                $changed[(string) $sku] = round((float) $price, 2);
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, float|int|string>  $current
     * @param  array<string, float|int|string>  $changed
     * @return array{action: string, message: string, lines: array<string, float>}
     */
    public function decide(
        array $current,
        array $changed,
        bool $onlyChanged,
        ?float $maxPercent,
        bool $hasPrevious,
        bool $force
    ): array {
        $lines = $onlyChanged ? $changed : $this->normalize($current);

        if ($current === []) {
            return [
                'action' => 'empty',
                'message' => 'Empty file is rejected. No SKU prices to upload.',
                'lines' => [],
            ];
        }

        if ($lines === []) {
            return [
                'action' => 'no_changes',
                'message' => 'No price changes — upload skipped',
                'lines' => [],
            ];
        }

        if ($hasPrevious && $maxPercent !== null && ! $force) {
            $baseline = max(count($current), 1);
            $percent = (count($changed) / $baseline) * 100;
            if ($percent > $maxPercent) {
                return [
                    'action' => 'requires_review',
                    'message' => 'Wayfair upload blocked: abnormal price change detected.',
                    'lines' => $lines,
                ];
            }
        }

        return [
            'action' => 'upload',
            'message' => '',
            'lines' => $lines,
        ];
    }

    public function cents(mixed $price): int
    {
        return (int) round(((float) $price) * 100);
    }

    /**
     * @param  array<string, float|int|string>  $prices
     * @return array<string, float>
     */
    private function normalize(array $prices): array
    {
        $out = [];
        foreach ($prices as $sku => $price) {
            $out[(string) $sku] = round((float) $price, 2);
        }

        return $out;
    }
}
