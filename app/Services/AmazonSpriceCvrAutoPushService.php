<?php

namespace App\Services;

use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Support\SpriceCvrMultRule;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automated Clear → Apply Sprice×CVR → Push pipeline for Amazon.
 *
 * SPRICE = Amazon price × (1 + signed%/100) from CVR L30 slab + CVR trend (L30 vs L60).
 * Same as the tabulator "% Sprice×CVR" button — Ads% is NOT used for SPRICE.
 * Rule key: shared ebay_sprice_cvr (SpriceCvrMultRule).
 */
class AmazonSpriceCvrAutoPushService
{
    /**
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    public function run(
        bool $dryRun = false,
        bool $skipClear = false,
        bool $skipPush = false,
        ?int $limit = null,
        int $sleepMs = 300,
        ?callable $logger = null
    ): array {
        $rule = SpriceCvrMultRule::settings();
        $summary = [
            'dry_run' => $dryRun,
            'skip_clear' => $skipClear,
            'skip_push' => $skipPush,
            'rule' => [
                'low_cvr' => $rule['low_cvr'] ?? 3.5,
                'mid_cvr' => $rule['mid_cvr'] ?? 7,
                'high_cvr' => $rule['high_cvr'] ?? 13,
                'trend_tolerance' => $rule['trend_tolerance'] ?? 0.1,
            ],
            'stats' => $this->runPipeline(
                $rule,
                $dryRun,
                $skipClear,
                $skipPush,
                $limit,
                max(0, $sleepMs),
                $logger
            ),
        ];

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    protected function runPipeline(
        array $rule,
        bool $dryRun,
        bool $skipClear,
        bool $skipPush,
        ?int $limit,
        int $sleepMs,
        ?callable $logger
    ): array {
        $stats = [
            'candidates' => 0,
            'cleared' => 0,
            'applied' => 0,
            'skipped_apply' => 0,
            'pushed' => 0,
            'push_failed' => 0,
            'errors' => [],
        ];

        try {
            $rows = $this->loadCandidates($limit);
        } catch (Throwable $e) {
            $stats['errors'][] = 'load: '.$e->getMessage();
            $this->log($logger, '✗ Amazon load failed: '.$e->getMessage());

            return $stats;
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with price");

        if ($stats['candidates'] === 0) {
            return $stats;
        }

        $this->log($logger, sprintf(
            'Rule (CVR%% only): Yellow≤%.2f Blue≤%.2f Green≤%.2f Pink>%.2f tol=±%.3f',
            (float) ($rule['low_cvr'] ?? 3.5),
            (float) ($rule['mid_cvr'] ?? 7),
            (float) ($rule['high_cvr'] ?? 13),
            (float) ($rule['high_cvr'] ?? 13) + 0.01,
            (float) ($rule['trend_tolerance'] ?? 0.1)
        ));

        $applied = [];
        $total = count($rows);
        foreach ($rows as $i => $row) {
            $computed = $this->computeSprice($row, $rule);

            if ($computed === null) {
                if (! $skipClear) {
                    try {
                        $this->clearSprice($row['sku']);
                        $stats['cleared']++;
                    } catch (Throwable $e) {
                        $stats['errors'][] = "clear {$row['sku']}: ".$e->getMessage();
                    }
                }
                $stats['skipped_apply']++;
                continue;
            }

            try {
                $this->saveSpriceFromCvr($row['sku'], $computed['sprice']);
                if (! $skipClear) {
                    $stats['cleared']++;
                }
                $stats['applied']++;
                $applied[] = [
                    'sku' => $row['sku'],
                    'seller_sku' => $row['seller_sku'],
                    'asin' => $row['asin'],
                    'sprice' => $computed['sprice'],
                    'signed_pct' => $computed['signed_pct'],
                    'trend' => $computed['trend'],
                    'slab' => $computed['slab'],
                ];
            } catch (Throwable $e) {
                $stats['skipped_apply']++;
                $stats['errors'][] = "apply {$row['sku']}: ".$e->getMessage();
            }

            if ($logger && (($i + 1) % 100 === 0 || ($i + 1) === $total)) {
                $this->log($logger, 'Progress '.($i + 1)."/{$total} (applied {$stats['applied']})");
            }
        }
        $this->log($logger, "Cleared+Applied {$stats['applied']} SKU(s) via CVR%% rule (skipped {$stats['skipped_apply']})");

        if ($dryRun || $skipPush) {
            $reason = $dryRun ? 'dry-run (Clear+Apply only, no Amazon push)' : '--skip-push';
            $this->log($logger, "Push skipped ({$reason}). SPRICE is saved in DB — refresh tabulator to verify.");
            $stats['push_skipped'] = true;

            return $stats;
        }

        $api = new AmazonSpApiService;
        foreach ($applied as $item) {
            try {
                $ok = $this->pushSprice($api, $item['sku'], $item['seller_sku'], $item['sprice']);
                if ($ok) {
                    $stats['pushed']++;
                } else {
                    $stats['push_failed']++;
                }
            } catch (Throwable $e) {
                $stats['push_failed']++;
                $stats['errors'][] = "push {$item['sku']}: ".$e->getMessage();
                Log::error('[AmazonSpriceCvrAutoPush] push exception', [
                    'sku' => $item['sku'],
                    'error' => $e->getMessage(),
                ]);
            }
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }
        $this->log($logger, "Pushed {$stats['pushed']} SKU(s) (failed {$stats['push_failed']})");

        return $stats;
    }

    /**
     * @return list<array{sku: string, seller_sku: string, asin: string, price: float, cvr_l30: float, cvr_l60: float}>
     */
    protected function loadCandidates(?int $limit): array
    {
        $query = AmazonDatasheet::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('price', '>', 0)
            ->orderBy('sku');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $sheets = $query->get([
            'sku',
            'asin',
            'price',
            'units_ordered_l30',
            'units_ordered_l60',
            'sessions_l30',
            'sessions_l60',
        ]);

        $out = [];
        $seen = [];
        foreach ($sheets as $m) {
            $sellerSku = trim((string) $m->sku);
            $sku = strtoupper($sellerSku);
            if ($sku === '' || isset($seen[$sku])) {
                continue;
            }
            $seen[$sku] = true;

            $price = (float) $m->price;
            if ($price <= 0) {
                continue;
            }

            $sess30 = (float) ($m->sessions_l30 ?? 0);
            $sess60 = (float) ($m->sessions_l60 ?? 0);
            $u30 = (float) ($m->units_ordered_l30 ?? 0);
            $u60 = (float) ($m->units_ordered_l60 ?? 0);
            // Same as amazon_tabulator_view amazonRowCvrL30 / amazonRowCvrL60
            $cvrL30 = $sess30 > 0 ? round(($u30 / $sess30) * 100, 2) : 0.0;
            $cvrL60 = $sess60 > 0 ? round(($u60 / $sess60) * 100, 2) : 0.0;

            $out[] = [
                'sku' => $sku,
                'seller_sku' => $sellerSku,
                'asin' => trim((string) ($m->asin ?? '')),
                'price' => $price,
                'cvr_l30' => $cvrL30,
                'cvr_l60' => $cvrL60,
            ];
        }

        return $out;
    }

    /**
     * @param  array{sku: string, price: float, cvr_l30: float, cvr_l60: float}  $row
     * @param  array<string, mixed>  $rule
     * @return array{sprice: float, signed_pct: float, trend: string, slab: string}|null
     */
    protected function computeSprice(array $row, array $rule): ?array
    {
        $tol = (float) ($rule['trend_tolerance'] ?? SpriceCvrMultRule::DEFAULT_TREND_TOLERANCE);
        $cvr = (float) $row['cvr_l30'];
        $cvr60 = (float) $row['cvr_l60'];
        $trend = $this->cvrTrend($cvr, $cvr60, $tol);
        $slab = $this->cvrIsZero($cvr) ? 'zero' : $this->cvrSlab($cvr, $rule);
        $signedPct = $this->signedPctFor($slab, $trend, $rule);
        if ($signedPct === null) {
            return null;
        }

        $base = (float) $row['price'];
        if ($base <= 0) {
            return null;
        }

        $sprice = round($base * (1 + $signedPct / 100), 2);
        if (! is_finite($sprice) || $sprice <= 0) {
            return null;
        }

        return [
            'sprice' => $sprice,
            'signed_pct' => $signedPct,
            'trend' => $trend,
            'slab' => $slab,
        ];
    }

    protected function cvrTrend(float $cvrL30, float $cvrL60, float $tol): string
    {
        if ($cvrL30 > $cvrL60 + $tol) {
            return 'up';
        }
        if ($cvrL30 < $cvrL60 - $tol) {
            return 'down';
        }

        return 'equal';
    }

    protected function cvrIsZero(float $cvr): bool
    {
        return abs($cvr) < 0.00001;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function cvrSlab(float $cvr, array $rule): string
    {
        $low = (float) ($rule['low_cvr'] ?? SpriceCvrMultRule::DEFAULT_LOW_CVR);
        $mid = (float) ($rule['mid_cvr'] ?? SpriceCvrMultRule::DEFAULT_MID_CVR);
        $high = (float) ($rule['high_cvr'] ?? SpriceCvrMultRule::DEFAULT_HIGH_CVR);
        $pinkAfter = $high + 0.01;
        if ($cvr <= $low) {
            return 'red';
        }
        if ($cvr <= $mid) {
            return 'blue';
        }
        if ($cvr <= $pinkAfter) {
            return 'green';
        }

        return 'pink';
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function signedPctFor(string $slab, string $trend, array $rule): ?float
    {
        $slabPct = $rule['slab_pct'] ?? [];
        if (! is_array($slabPct)) {
            return null;
        }
        $row = $slabPct[$slab] ?? null;
        if (! is_array($row)) {
            return null;
        }
        $key = in_array($trend, ['down', 'equal', 'up'], true) ? $trend : 'equal';
        if (! isset($row[$key]) || ! is_numeric($row[$key])) {
            return null;
        }

        return (float) $row[$key];
    }

    protected function clearSprice(string $sku): void
    {
        $view = AmazonDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
        if (! $view) {
            return;
        }
        $existing = $this->decodeValue($view->value);
        $existing['SPRICE'] = null;
        $existing['SPFT'] = null;
        $existing['SROI'] = null;
        $existing['SGROI'] = null;
        $existing['SGPFT'] = null;
        $existing['SPRICE_STATUS'] = null;
        unset($existing['SPRICE_STATUS_UPDATED_AT']);
        $view->value = $existing;
        $view->save();
    }

    /** Persist CVR-rule SPRICE only (Ads% unused). Tabulator recalculates SPFT/SROI on load. */
    protected function saveSpriceFromCvr(string $sku, float $sprice): void
    {
        $view = AmazonDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first()
            ?? new AmazonDataView(['sku' => $sku]);

        $existing = $this->decodeValue($view->value);
        if (! $view->exists) {
            $view->sku = $sku;
        }

        $existing['SPRICE'] = $sprice;
        $existing['SPFT'] = null;
        $existing['SROI'] = null;
        $existing['SGROI'] = null;
        $existing['SGPFT'] = null;
        $existing['SPRICE_STATUS'] = 'saved';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();

        $view->value = $existing;
        $view->save();
    }

    protected function pushSprice(AmazonSpApiService $api, string $statusSku, string $sellerSku, float $sprice): bool
    {
        $price = round($sprice, 2);
        if ($price < 0.01 || $price > 999999.99) {
            $this->savePushStatus($statusSku, 'error');

            return false;
        }

        $apiSku = $sellerSku !== '' ? $sellerSku : $statusSku;
        // updateAmazonPriceUS sets our_price + minimum_seller_allowed_price together
        $result = $api->updateAmazonPriceUS($apiSku, $price);

        if (isset($result['errors']) && ! empty($result['errors'])) {
            $this->savePushStatus($statusSku, 'error');
            Log::warning('[AmazonSpriceCvrAutoPush] push failed', [
                'status_sku' => $statusSku,
                'amazon_api_sku' => $apiSku,
                'price' => $price,
                'errors' => $result['errors'],
            ]);

            return false;
        }

        // Explicit min-price merge as backup (same floor as listing price)
        try {
            $minResult = $api->updateCompetitivePriceConstraints($apiSku, $price);
            if (isset($minResult['errors']) && ! empty($minResult['errors'])) {
                Log::warning('[AmazonSpriceCvrAutoPush] min price update failed (non-blocking)', [
                    'status_sku' => $statusSku,
                    'amazon_api_sku' => $apiSku,
                    'min_price' => $price,
                    'errors' => $minResult['errors'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[AmazonSpriceCvrAutoPush] min price update exception (non-blocking)', [
                'status_sku' => $statusSku,
                'amazon_api_sku' => $apiSku,
                'error' => $e->getMessage(),
            ]);
        }

        $this->savePushStatus($statusSku, 'pushed');

        return true;
    }

    protected function savePushStatus(string $sku, string $status): void
    {
        try {
            $view = AmazonDataView::firstOrNew(['sku' => strtoupper(trim($sku))]);
            $existing = $this->decodeValue($view->value);
            $existing['SPRICE_STATUS'] = $status;
            $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            if (! $view->exists) {
                $view->sku = strtoupper(trim($sku));
            }
            $view->value = $existing;
            $view->save();
        } catch (Throwable $e) {
            Log::error('[AmazonSpriceCvrAutoPush] status save failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    protected function decodeValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param  callable(string): void|null  $logger
     */
    protected function log(?callable $logger, string $message): void
    {
        if ($logger) {
            $logger($message);
        }
        Log::info('[AmazonSpriceCvrAutoPush] '.$message);
    }
}
