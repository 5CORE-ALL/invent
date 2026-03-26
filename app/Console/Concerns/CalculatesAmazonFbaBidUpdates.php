<?php

namespace App\Console\Concerns;

use Illuminate\Support\Collection;

/**
 * Shared bid math for Amazon FBA auto bid update commands (KW + PT).
 *
 * CPC values come from {@see \App\Models\AmazonSpCampaignReport} rows at L1 / L2 / L7
 * (`costPerClick`), keyed by campaign — equivalent to campaign-level CPC used in the FBA UI.
 */
trait CalculatesAmazonFbaBidUpdates
{
    public const MIN_BID = 0.10;

    public const MAX_BID = 5.00;

    public const DEFAULT_FALLBACK_BID = 0.60;

    public const UNDER_UTILIZED_THRESHOLD = 50;

    public const OVER_UTILIZED_THRESHOLD = 70;

    public const UNDER_BID_MULTIPLIER = 1.10;

    public const OVER_BID_MULTIPLIER = 0.90;

    /**
     * Best-effort current default bid from synced report rows (for before/after + API diff checks).
     *
     * @param object|null $l7
     * @param object|null $l1
     */
    /**
     * Campaign-level CPC from synced SP report rows (L1 / L2 / L7).
     */
    protected function cpcFromCampaign(Collection $reports, string $campaignId): float
    {
        $row = $reports->first(function ($r) use ($campaignId) {
            return (string) ($r->campaign_id ?? '') === $campaignId;
        });

        return floatval($row->costPerClick ?? 0);
    }

    protected function resolveCurrentBidFromReport(?object $l7, ?object $l1, float $l7Cpc = 0.0, float $l1Cpc = 0.0): float
    {
        foreach (['sbid', 'last_sbid'] as $field) {
            foreach ([$l7, $l1] as $row) {
                if ($row === null) {
                    continue;
                }
                $v = (float) ($row->{$field} ?? 0);
                if ($v > 0) {
                    return $v;
                }
            }
        }

        $proxy = max($l1Cpc, $l7Cpc);
        if ($proxy > 0) {
            return max(self::MIN_BID, round($proxy, 2));
        }

        return self::MIN_BID;
    }

    /**
     * RED / under-utilized: increase bid using CPC ladder (L1 → L2 → L7 → default).
     *
     * @return array{bid: float, source: string, base_cpc: float, multiplier: float}
     */
    protected function calculateUnderUtilizedBidFromCpc(float $cpcL1, float $cpcL2, float $cpcL7): array
    {
        $mult = self::UNDER_BID_MULTIPLIER;
        if ($cpcL1 > 0) {
            $base = $cpcL1;

            return $this->finalizeUnderOverBid($base * $mult, 'L1', $base, $mult);
        }
        if ($cpcL2 > 0) {
            $base = $cpcL2;

            return $this->finalizeUnderOverBid($base * $mult, 'L2', $base, $mult);
        }
        if ($cpcL7 > 0) {
            $base = $cpcL7;

            return $this->finalizeUnderOverBid($base * $mult, 'L7', $base, $mult);
        }

        return $this->finalizeUnderOverBid(self::DEFAULT_FALLBACK_BID, 'default', 0.0, 1.0);
    }

    /**
     * PINK / over-utilized: decrease bid using CPC ladder (L1 → L2 → L7 → default).
     *
     * @return array{bid: float, source: string, base_cpc: float, multiplier: float}
     */
    protected function calculateOverUtilizedBidFromCpc(float $cpcL1, float $cpcL2, float $cpcL7): array
    {
        $mult = self::OVER_BID_MULTIPLIER;
        if ($cpcL1 > 0) {
            $base = $cpcL1;

            return $this->finalizeUnderOverBid($base * $mult, 'L1', $base, $mult);
        }
        if ($cpcL2 > 0) {
            $base = $cpcL2;

            return $this->finalizeUnderOverBid($base * $mult, 'L2', $base, $mult);
        }
        if ($cpcL7 > 0) {
            $base = $cpcL7;

            return $this->finalizeUnderOverBid($base * $mult, 'L7', $base, $mult);
        }

        return $this->finalizeUnderOverBid(self::DEFAULT_FALLBACK_BID, 'default', 0.0, 1.0);
    }

    /**
     * @return array{bid: float, source: string, base_cpc: float, multiplier: float}
     */
    private function finalizeUnderOverBid(float $rawBid, string $source, float $baseCpc, float $multiplier): array
    {
        $bid = min(self::MAX_BID, max(self::MIN_BID, round($rawBid, 2)));

        return [
            'bid' => $bid,
            'source' => $source,
            'base_cpc' => round($baseCpc, 4),
            'multiplier' => $multiplier,
        ];
    }

    /**
     * Human-readable note for dry-run / logs (e.g. "based on L1 CPC: 0.50 × 1.10").
     */
    protected function describeBidCpcSource(array $calc): string
    {
        $src = $calc['source'] ?? 'default';
        if ($src === 'default') {
            return sprintf('fallback $%.2f (no L1/L2/L7 CPC)', self::DEFAULT_FALLBACK_BID);
        }

        $base = (float) ($calc['base_cpc'] ?? 0);
        $mult = (float) ($calc['multiplier'] ?? 1);

        return sprintf('based on %s CPC: %.2f × %.2f', $src, $base, $mult);
    }
}

