<?php

namespace App\Services;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayThreeDataView;
use App\Models\EbayTwoDataView;
use App\Models\ProductMaster;
use App\Support\SpriceCvrMultRule;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automated Clear → Apply Sprice×CVR → Push pipeline for eBay 1 / 2 / 3.
 *
 * SPRICE = eBay price × (1 + signed%/100) from CVR L30 slab + CVR trend (L30 vs L60).
 * Same as the tabulator "% Sprice×CVR" button — Ads% is NOT used for SPRICE.
 */
class EbaySpriceCvrAutoPushService
{
    public const CHANNELS = ['ebay1', 'ebay2', 'ebay3'];

    /**
     * @param  list<string>  $channels
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    public function run(
        array $channels = self::CHANNELS,
        bool $dryRun = false,
        bool $skipClear = false,
        bool $skipPush = false,
        ?int $limit = null,
        int $sleepMs = 300,
        ?callable $logger = null
    ): array {
        $channels = array_values(array_intersect(self::CHANNELS, array_map('strtolower', $channels)));
        if ($channels === []) {
            $channels = self::CHANNELS;
        }

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
            'channels' => [],
        ];

        foreach ($channels as $channel) {
            $this->log($logger, "━━━ {$channel}: Clear → Apply Sprice×CVR → Push ━━━");
            $summary['channels'][$channel] = $this->runChannel(
                $channel,
                $rule,
                $dryRun,
                $skipClear,
                $skipPush,
                $limit,
                max(0, $sleepMs),
                $logger
            );
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    protected function runChannel(
        string $channel,
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
            $rows = $this->loadCandidates($channel, $limit);
        } catch (Throwable $e) {
            $stats['errors'][] = 'load: '.$e->getMessage();
            $this->log($logger, "✗ {$channel} load failed: ".$e->getMessage());

            return $stats;
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with item_id + price");

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

        // Clear + Apply atomically per SKU (SPRICE from CVR slabs — no Ads%).
        $applied = [];
        $total = count($rows);
        foreach ($rows as $i => $row) {
            $computed = $this->computeSprice($row, $rule);

            if ($computed === null) {
                if (! $skipClear) {
                    try {
                        $this->clearSprice($channel, $row['sku']);
                        $stats['cleared']++;
                    } catch (Throwable $e) {
                        $stats['errors'][] = "clear {$row['sku']}: ".$e->getMessage();
                    }
                }
                $stats['skipped_apply']++;
                continue;
            }

            try {
                // Overwrite SPRICE only — value is 100% CVR-rule based.
                $this->saveSpriceFromCvr($channel, $row['sku'], $computed['sprice']);
                if (! $skipClear) {
                    $stats['cleared']++;
                }
                $stats['applied']++;
                $applied[] = [
                    'sku' => $row['sku'],
                    'sprice' => $computed['sprice'],
                    'item_id' => $row['item_id'],
                    'signed_pct' => $computed['signed_pct'],
                    'trend' => $computed['trend'],
                    'slab' => $computed['slab'],
                    'cvr_l30' => $row['cvr_l30'],
                    'cvr_l60' => $row['cvr_l60'],
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

        // 3) Push to eBay — skipped for dry-run / --skip-push
        if ($dryRun || $skipPush) {
            $reason = $dryRun ? 'dry-run (Clear+Apply only, no eBay push)' : '--skip-push';
            $this->log($logger, "Push skipped ({$reason}). SPRICE is saved in DB — refresh tabulator to verify.");
            $stats['push_skipped'] = true;

            return $stats;
        }

        foreach ($applied as $item) {
            try {
                $ok = $this->pushSprice($channel, $item['sku'], $item['sprice'], $item['item_id']);
                if ($ok) {
                    $stats['pushed']++;
                } else {
                    $stats['push_failed']++;
                }
            } catch (Throwable $e) {
                $stats['push_failed']++;
                $stats['errors'][] = "push {$item['sku']}: ".$e->getMessage();
                Log::error('[EbaySpriceCvrAutoPush] push exception', [
                    'channel' => $channel,
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
     * @return list<array{sku: string, item_id: string, price: float, cvr_l30: float, cvr_l60: float, lp: float, ship: float}>
     */
    protected function loadCandidates(string $channel, ?int $limit): array
    {
        $metricModel = match ($channel) {
            'ebay2' => Ebay2Metric::class,
            'ebay3' => Ebay3Metric::class,
            default => EbayMetric::class,
        };

        $query = $metricModel::query()
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->where('ebay_price', '>', 0)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('sku');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $metrics = $query->get(['sku', 'item_id', 'ebay_price', 'ebay_l30', 'ebay_l60', 'views']);
        $skuList = $metrics->pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->unique()->values()->all();
        $pmBySku = [];
        if ($skuList !== []) {
            // Case-insensitive SKU match against product_master
            $placeholders = implode(',', array_fill(0, count($skuList), '?'));
            $pms = ProductMaster::query()
                ->whereRaw('UPPER(TRIM(sku)) IN ('.$placeholders.')', array_map(
                    fn ($s) => strtoupper($s),
                    $skuList
                ))
                ->get();
            foreach ($pms as $pm) {
                $pmBySku[strtoupper(trim((string) $pm->sku))] = $pm;
            }
        }

        $out = [];
        $seen = [];
        foreach ($metrics as $m) {
            $sku = strtoupper(trim((string) $m->sku));
            if ($sku === '' || isset($seen[$sku])) {
                continue;
            }
            $seen[$sku] = true;

            $price = (float) $m->ebay_price;
            if ($price <= 0) {
                continue;
            }

            $views = (float) ($m->views ?? 0);
            $l30 = (float) ($m->ebay_l30 ?? 0);
            $l60 = (float) ($m->ebay_l60 ?? 0);
            $cvrL30 = $views > 0 ? round(($l30 / $views) * 100, 2) : 0.0;
            $cvrL60 = $views > 0 ? round(($l60 / $views) * 100, 2) : 0.0;

            $lp = 0.0;
            $ship = 0.0;
            $pm = $pmBySku[$sku] ?? null;
            if ($pm) {
                [$lp, $ship] = $this->lpShipFromProductMaster($pm);
            }

            $out[] = [
                'sku' => $sku,
                'item_id' => (string) $m->item_id,
                'price' => $price,
                'cvr_l30' => $cvrL30,
                'cvr_l60' => $cvrL60,
                'lp' => $lp,
                'ship' => $ship,
            ];
        }

        return $out;
    }

    /**
     * % Sprice×CVR (same as tabulator button):
     *   trend = CVR L30 vs CVR L60 (±tol) → down|equal|up
     *   slab  = CVR L30 band (zero / yellow=red / blue / green / pink)
     *   SPRICE = eBay price × (1 + signed%/100)   — of price, not Ads%/PFT%
     *
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

        // 0% → hold at eBay price; ±N% adjust listing price (never Ads%).
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

    protected function clearSprice(string $channel, string $sku): void
    {
        $view = $this->findOrNewDataView($channel, $sku);
        if (! $view->exists && ! $view->sku) {
            // Nothing stored yet — no clear needed.
            return;
        }
        $existing = $this->decodeValue($view->value);

        unset(
            $existing['SPRICE'],
            $existing['SPFT'],
            $existing['SROI'],
            $existing['SGROI'],
            $existing['SGPFT'],
            $existing['SPRICE_STATUS'],
            $existing['SPRICE_STATUS_UPDATED_AT'],
            $existing['sprice_push_status'],
            $existing['sprice_push_time']
        );

        if ($channel === 'ebay3') {
            $existing['SPRICE_CLEARED'] = true;
        }

        if (! $view->exists) {
            $view->sku = $sku;
        }
        $view->value = $existing;
        $view->save();
    }

    /**
     * Persist CVR-rule SPRICE only. Tabulator recalculates SPFT/SROI on load.
     * Does not read or use Ads%.
     */
    protected function saveSpriceFromCvr(string $channel, string $sku, float $sprice): void
    {
        $view = $this->findOrNewDataView($channel, $sku);
        $existing = $this->decodeValue($view->value);
        unset($existing['SPRICE_CLEARED']);

        // Keep existing SKU casing so tabulator lookup still matches.
        if (! $view->exists) {
            $view->sku = $sku;
        }

        // Drop stale profit fields so the UI recalculates from the new SPRICE.
        unset($existing['SPFT'], $existing['SROI'], $existing['SGROI'], $existing['SGPFT']);

        $view->value = array_merge($existing, [
            'SPRICE' => $sprice,
        ]);
        $view->save();
    }

    protected function pushSprice(string $channel, string $sku, float $sprice, string $itemId): bool
    {
        $price = round($sprice, 2);
        if ($price < 0.01 || $price > 10000) {
            $this->savePushStatus($channel, $sku, 'error');

            return false;
        }

        $result = match ($channel) {
            'ebay2' => (new Ebay2ApiService)->reviseFixedPriceItem($itemId, $price),
            'ebay3' => (new EbayThreeApiService)->reviseFixedPriceItem($itemId, $price, null, $sku),
            default => (new EbayApiService)->reviseFixedPriceItem($itemId, $price),
        };

        $ok = (bool) ($result['success'] ?? false);
        if ($ok) {
            if ($channel === 'ebay2') {
                Ebay2Metric::where('sku', $sku)->update(['ebay_price' => $price]);
            } elseif ($channel === 'ebay3') {
                Ebay3Metric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                    ->update(['ebay_price' => $price]);
            }
            $this->savePushStatus($channel, $sku, 'pushed');

            return true;
        }

        $restricted = (bool) ($result['accountRestricted'] ?? false);
        $status = $restricted ? 'account_restricted' : ($channel === 'ebay2' ? 'failed' : 'error');
        $this->savePushStatus($channel, $sku, $status);

        Log::warning('[EbaySpriceCvrAutoPush] push failed', [
            'channel' => $channel,
            'sku' => $sku,
            'price' => $price,
            'errors' => $result['errors'] ?? ($result['message'] ?? null),
        ]);

        return false;
    }

    protected function savePushStatus(string $channel, string $sku, string $status): void
    {
        try {
            $view = $this->findOrNewDataView($channel, $sku);
            $existing = $this->decodeValue($view->value);
            if ($channel === 'ebay2') {
                $existing['sprice_push_status'] = $status;
                $existing['sprice_push_time'] = now()->toDateTimeString();
            } else {
                $existing['SPRICE_STATUS'] = $status;
                $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            }
            $view->sku = $sku;
            $view->value = $existing;
            $view->save();
        } catch (Throwable $e) {
            Log::error('[EbaySpriceCvrAutoPush] status save failed', [
                'channel' => $channel,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function findOrNewDataView(string $channel, string $sku)
    {
        return match ($channel) {
            'ebay2' => EbayTwoDataView::firstOrNew(['sku' => $sku]),
            'ebay3' => EbayThreeDataView::firstOrNew(['sku' => $sku]),
            default => EbayDataView::whereRaw('LOWER(TRIM(sku)) = ?', [strtolower($sku)])->first()
                ?? new EbayDataView(['sku' => $sku]),
        };
    }

    /**
     * @return array{0: float, 1: float}
     */
    protected function lpShipFromProductMaster(ProductMaster $pm): array
    {
        $values = is_array($pm->Values)
            ? $pm->Values
            : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);

        $lp = 0.0;
        foreach ($values as $k => $v) {
            if (strtolower((string) $k) === 'lp') {
                $lp = (float) $v;
                break;
            }
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = (float) $pm->lp;
        }

        $ship = isset($values['ship'])
            ? (float) $values['ship']
            : (isset($pm->ship) ? (float) $pm->ship : 0.0);

        return [$lp, $ship];
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
        Log::info('[EbaySpriceCvrAutoPush] '.$message);
    }
}
