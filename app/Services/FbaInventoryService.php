<?php

namespace App\Services;

use App\Models\FbaTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Single place for ProductMaster / MSKU ↔ fba_table.seller_sku resolution.
 * Matches FBA Analytics (FbaDataController) base-key semantics, plus compact disambiguation used on Amazon FBM.
 */
final class FbaInventoryService
{
    private Collection $fbaInventoryByFbaAnalyticsKey;

    /** seller_sku → row (last wins), compact uppercase no spaces */
    private array $fbaRowByFullSellerSkuCompact = [];

    /** compact base (FBA stripped) → FbaTable[] */
    private array $fbaRowsByCompactKey = [];

    public function __construct(Collection $fbaTableFbaRows)
    {
        $this->fbaInventoryByFbaAnalyticsKey = $fbaTableFbaRows->keyBy(function ($item) {
            return self::sellerSkuToAnalyticsListingKey($item->seller_sku ?? '');
        });
        foreach ($fbaTableFbaRows as $fbaRow) {
            $k = self::skuCompact($fbaRow->seller_sku ?? '');
            if ($k !== '') {
                $this->fbaRowByFullSellerSkuCompact[$k] = $fbaRow;
            }
        }
        foreach ($fbaTableFbaRows as $fbaRow) {
            $base = preg_replace('/\s*FBA\s*/i', '', self::normalizeSkuChars($fbaRow->seller_sku ?? ''));
            $ck = self::skuCompact($base);
            if ($ck === '') {
                continue;
            }
            if (! isset($this->fbaRowsByCompactKey[$ck])) {
                $this->fbaRowsByCompactKey[$ck] = [];
            }
            $this->fbaRowsByCompactKey[$ck][] = $fbaRow;
        }
    }

    public static function fromFbaRows(Collection $fbaTableFbaRows): self
    {
        return new self($fbaTableFbaRows);
    }

    /** Count of distinct FBA Analytics listing keys (after keyBy collision). */
    public function distinctAnalyticsKeyCount(): int
    {
        return $this->fbaInventoryByFbaAnalyticsKey->count();
    }

    /**
     * Same key as FBA Analytics uses for fba_table rows: strip "FBA" tokens, trim, uppercase (spaces kept).
     */
    public static function sellerSkuToAnalyticsListingKey(?string $sellerSku): string
    {
        $s = self::normalizeSkuChars((string) $sellerSku);
        if ($s === '') {
            return '';
        }
        $base = preg_replace('/\s*FBA\s*/i', '', $s);

        return strtoupper(trim($base));
    }

    public static function normalizeSkuChars(?string $s): string
    {
        $s = (string) $s;
        $s = str_replace("\xC2\xA0", ' ', $s);
        $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s);
        $s = preg_replace('/\s+/u', ' ', trim($s));

        return $s;
    }

    public static function skuCompact(string $raw): string
    {
        return strtoupper(str_replace(' ', '', self::normalizeSkuChars($raw)));
    }

    /**
     * Resolve fba_table row for a ProductMaster sku, orphan campaign base sku, or full MSKU (e.g. "CAPO AL BLK FBA").
     */
    public function resolve(string $sku): ?FbaTable
    {
        $norm = self::normalizeSkuChars($sku);
        if ($norm === '') {
            return null;
        }
        $pmFullCompact = self::skuCompact($norm);
        if (isset($this->fbaRowByFullSellerSkuCompact[$pmFullCompact])) {
            $hit = $this->fbaRowByFullSellerSkuCompact[$pmFullCompact];
            $this->logResolve($sku, $norm, $hit, 'full_msku_compact');

            return $hit;
        }
        $analyticsKey = self::sellerSkuToAnalyticsListingKey($norm);
        if ($analyticsKey !== '') {
            $hit = $this->fbaInventoryByFbaAnalyticsKey->get($analyticsKey);
            if ($hit) {
                $this->logResolve($sku, $norm, $hit, 'fba_analytics_base_key');

                return $hit;
            }
        }
        $baseRaw = preg_replace('/\s*FBA\s*/i', '', $norm);
        $compactKey = self::skuCompact($baseRaw);
        $cands = $this->fbaRowsByCompactKey[$compactKey] ?? [];
        if (count($cands) === 1) {
            $hit = $cands[0];
            $this->logResolve($sku, $norm, $hit, 'compact_base_unique');

            return $hit;
        }
        if (count($cands) > 1) {
            foreach ($cands as $c) {
                if (self::skuCompact($c->seller_sku ?? '') === $pmFullCompact) {
                    $this->logResolve($sku, $norm, $c, 'compact_base_disambiguated');

                    return $c;
                }
            }
            $this->logResolve($sku, $norm, null, 'compact_base_ambiguous');

            return null;
        }
        $this->logResolve($sku, $norm, null, 'no_match');

        return null;
    }

    public function quantityAndSellerSku(string $sku): array
    {
        $row = $this->resolve($sku);

        return [
            'quantity' => $row ? (int) ($row->quantity_available ?? 0) : 0,
            'seller_sku' => $row ? ($row->seller_sku ?? null) : null,
        ];
    }

    private function logResolve(string $inputSku, string $normalizedSku, ?FbaTable $hit, string $step): void
    {
        if (! filter_var(env('FBA_INVENTORY_DEBUG', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        Log::channel('fba_debug')->debug('FBA inventory resolve', [
            'input_sku' => $inputSku,
            'normalized_sku' => $normalizedSku,
            'step' => $step,
            'matched_seller_sku' => $hit?->seller_sku,
            'quantity_available' => $hit ? (int) ($hit->quantity_available ?? 0) : null,
        ]);
    }
}
