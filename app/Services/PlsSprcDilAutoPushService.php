<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\PlsController;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\PLSDataView;
use App\Support\AmazonDilGroiRule;
use App\Support\EbayRuleSprice;
use App\Support\PushedListingPrice;
use App\Support\SpriceActiveRule;
use App\Support\StdPrcVsDilPricer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PLS (ProLightSounds) Std prc vs dil / Sprc Dil → SPRICE (page not required). Same shape as ShopifyB2cSprcDilAutoPushService:
 * this service only SAVES S PRC. The live ProLightSounds push is queued by ChannelPushSpriceDailyEnqueue
 * (collectPushTasks() below) and sent by the shared S PRC worker, exactly like Shopify B2C.
 *
 *  S PRC = Std Prc × (1 − (Age Disc + Dil Disc + B Disc + 0 Sold Disc + CVR Disc + Rev Disc) / 100)
 *  Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 *  PLS has no ads, so NPFT = GPFT. Clearance SKUs are priced only from the Clearance Target NROI% (Sprc Dil modal, last row), no cap.
 *  SNROI Cap (same as Amazon): a Dil slab with a Target SNROI % prices S PRC from that SNROI, judged like the page's
 *  SNROI% column, (S PRC × PLS% − Ship − LP) ÷ LP with no Ads%, instead of the discounts. Std Prc stays the maximum.
 *  0 Sold = P L30 (pls_products.p_l30), the page's sold qty. Std Prc is the maximum.
 *  PLS has no LMP and no Amz rule, so neither is used.
 *
 * The page switch picks the rule: Std prc vs dil (above) or Sprc Dil (Dil slab Target SNROI + Diff, Ship in the price,
 * Std Prc the maximum). Only the ON rule fills S PRC.
 *
 * Rows come from PlsController::pricingDataJson(), the same rows /pls-pricing shows.
 * Never pushes a price whose SNROI is below 0: the shared runner applies NegativeSnroiPushGuard.
 */
class PlsSprcDilAutoPushService
{
    public const LOCK_KEY = 'pls-sprc-dil-auto-push-run';

    /** @var array<string, PLSDataView>|null */
    private ?array $viewsByNorm = null;

    /**
     * Re-price every listed SKU with INV > 0 and save S PRC where it changed. Nothing is pushed here.
     *
     * @param  list<string>|null  $onlySkus  Uppercase SKUs; null = all listed candidates
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    public function run(
        bool $dryRun = false,
        ?int $limit = null,
        ?array $onlySkus = null,
        ?callable $logger = null
    ): array {
        StdPrcVsDilPricer::flush();
        $pricer = StdPrcVsDilPricer::forChannel('pls');
        // The page switch decides the S PRC rule: Std prc vs dil, or the Sprc Dil modal (slab + Diff + Std max).
        $dilContext = SpriceActiveRule::usesStdPrc('pls') ? null : $this->loadDilContext();

        $this->log($logger, $dilContext !== null
            ? 'PLS S PRC rule = Sprc Dil (Dil slab Target SNROI + Diff, Ship in the price, Std max)'
            : 'Loaded PLS Std prc vs dil (pls_std_prc_vs_dil) → Age/Dil/B/0 Sold/CVR/Rev Disc + Std max');

        $stats = [
            'candidates' => 0,
            'applied' => 0,
            'skipped_unchanged' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $lock = Cache::lock(self::LOCK_KEY, 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another PLS S PRC run is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'stats' => $stats];
        }

        try {
            try {
                $rows = $this->loadCandidates($limit, $onlySkus);
                // Prices set by hand in the Price Increase modal are not recomputed / overwritten by this run
                if (empty($onlySkus)) {
                    $rows = \App\Services\Support\ManualSpriceFlag::withoutManual($rows, \App\Models\PLSDataView::class, 'value');
                }
            } catch (Throwable $e) {
                $stats['errors'][] = 'load: '.$e->getMessage();
                $this->log($logger, '✗ Load failed: '.$e->getMessage());

                return ['dry_run' => $dryRun, 'stats' => $stats];
            }

            $stats['candidates'] = count($rows);
            $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0 listed on PLS");

            $total = count($rows);
            foreach ($rows as $i => $row) {
                try {
                    $computed = $this->computeTarget($row, $pricer, $dilContext);
                    if ($computed === null) {
                        $stats['skipped']++;
                        continue;
                    }
                    $sprice = (float) $computed['sprice'];

                    if (PushedListingPrice::same($row['sprice'] ?? 0, $sprice)) {
                        // Saved S PRC already equals the rule price.
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    if (! $dryRun) {
                        // Live Price already at the target: mark it pushed, so no push is queued for it.
                        $this->saveSprice($row, $computed, PushedListingPrice::same($row['price'] ?? 0, $sprice) ? 'pushed' : 'applied');
                    }
                    $stats['applied']++;
                } catch (Throwable $e) {
                    $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                    Log::error('[PlsSprcDilAutoPush] exception', [
                        'sku' => $row['sku'] ?? '',
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($logger && (($i + 1) % 200 === 0 || ($i + 1) === $total)) {
                    $this->log($logger, 'Progress '.($i + 1)."/{$total} (applied {$stats['applied']}, unchanged {$stats['skipped_unchanged']})");
                }
            }

            $this->log($logger, sprintf(
                'Done: candidates=%d applied=%d unchanged=%d skipped=%d errors=%d%s',
                $stats['candidates'],
                $stats['applied'],
                $stats['skipped_unchanged'],
                $stats['skipped'],
                count($stats['errors']),
                $dryRun ? ' [dry run]' : ''
            ));
        } finally {
            optional($lock)->release();
        }

        return ['dry_run' => $dryRun, 'stats' => $stats];
    }

    /**
     * The blue triangles: listed, INV > 0, a saved S PRC that differs from the live PLS Price. These are the tasks the
     * shared S PRC worker pushes (ChannelPushSpriceDailyEnqueue::collect('pls')). Same rule as the page's blue badge.
     *
     * @return list<array{sku: string, price: float}>
     */
    public function collectPushTasks(): array
    {
        $out = [];
        foreach ($this->loadCandidates(null, null) as $row) {
            $saved = round((float) ($row['sprice'] ?? 0), 2);
            $live = round((float) ($row['price'] ?? 0), 2);
            if (! ($saved > 0) || ! ($live > 0) || PushedListingPrice::same($saved, $live)) {
                continue;
            }
            $out[] = ['sku' => strtoupper(trim((string) $row['sku'])), 'price' => $saved];
        }

        return $out;
    }

    /**
     * Page S PRC formula (INV > 0 child SKUs): Std rule, Std is the maximum.
     * PLS has no LMP, ads or Amz rule.
     *
     * With a $dilContext (the Sprc Dil switch is ON) the Sprc Dil modal rules price it instead.
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array}|null  $dilContext
     * @return array{sprice:float,uncapped:float,base:float,margin:float,lp:float,ship:float}|null
     */
    public function computeTarget(array $row, ?StdPrcVsDilPricer $pricer = null, ?array $dilContext = null): ?array
    {
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0)) {
            return null;
        }
        // Clearance SKUs (Inv days clearance = YES) are priced ONLY from the Clearance Target NROI%:
        // (LP × (1 + NROI%) + Ship) ÷ PLS%. No slab, Diff or Std rule.
        if ((new StdPrcVsDilPricer([], 'pls'))->isClearanceRow($row)) {
            return $this->computeClearanceTarget($row);
        }
        if ($dilContext !== null) {
            return $this->computeDilTarget($row, $dilContext);
        }
        $std = (float) ($row['standard_price'] ?? 0);
        if (! ($std > 0)) {
            return null;
        }
        $lp = (float) ($row['lp'] ?? 0);
        $ship = (float) ($row['ship'] ?? 0);
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 1.0;
        }

        $pricer ??= StdPrcVsDilPricer::forChannel('pls');
        // Age / Dil / B / 0 Sold / CVR / Rev discounts, then clearance SKUs keep the discount price.
        $pricerRow = [
            'sku' => (string) ($row['sku'] ?? ''),
            'inv' => $inv,
            'dil' => (float) ($row['dil'] ?? 0),
            'std_price' => $std,
            'age_days' => $row['age_days'] ?? null,
            'cvr' => 0.0,
            'reviews' => 0.0,
            // 0 Sold = P L30 (pls_products.p_l30), same as the page.
            'pls_l30' => (float) ($row['pls_l30'] ?? 0),
            'lp' => $lp,
            'ship' => $ship,
            'margin' => $margin,
            'ads' => 0.0,
        ];
        if (array_key_exists('clearance', $row)) {
            $pricerRow['clearance'] = $row['clearance'];
        }
        $effective = $pricer->priceFromRow($pricerRow);
        if ($effective === null || ! ($effective > 0)) {
            return null;
        }

        // No LMP cap and no Amz rule. Std Prc is the maximum.
        $capped = AmazonDilGroiRule::capToStdWhenNoLmp($effective, 0.0, max($std, $effective));

        if (! is_finite($capped) || $capped < 0.01) {
            return null;
        }

        return [
            'sprice' => round($capped, 2),
            'uncapped' => round($effective, 2),
            'base' => round($std, 2),
            'margin' => $margin,
            'lp' => $lp,
            'ship' => $ship,
        ];
    }

    /**
     * Clearance SKU: (LP × (1 + Clearance NROI%) + Ship) ÷ PLS%. PLS has no Ads, LMP or Amz rule, and Std Prc does not cap it.
     *
     * @param  array<string, mixed>  $row
     * @return array{sprice:float,uncapped:float,base:float,margin:float,lp:float,ship:float}|null
     */
    protected function computeClearanceTarget(array $row): ?array
    {
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 1.0;
        }
        $lp = (float) ($row['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }
        $ship = (float) ($row['ship'] ?? 0);
        // A row may carry its own clearance_nroi (tests); otherwise the saved value.
        $nroi = isset($row['clearance_nroi']) && is_numeric($row['clearance_nroi']) ? (float) $row['clearance_nroi'] : $this->clearanceNroi();
        $price = round(($lp * (1 + max(0.0, $nroi) / 100) + $ship) / $margin, 2);
        if (! is_finite($price) || $price < 0.01) {
            return null;
        }

        return [
            'sprice' => $price,
            'uncapped' => $price,
            'base' => round((float) ($row['standard_price'] ?? 0), 2),
            'margin' => $margin,
            'lp' => $lp,
            'ship' => $ship,
        ];
    }

    /** Clearance Target NROI% saved with the PLS Sprc Dil rules (last row of the modal). Default 0. */
    protected function clearanceNroi(): float
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', 'pls_dil_vs_groi')->first();
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($row?->visibility) ? $row->visibility : null);
        $n = $unpacked['clearance_nroi'] ?? null;

        return (is_numeric($n) && (float) $n >= 0) ? (float) $n : 0.0;
    }

    /**
     * Sprc Dil switch ON, same as the page (ebay-sprc-dil + /amazon-tabulator-view order):
     * Dil slab Target SNROI, Diff points, then price = (LP x (1 + Target%) + Ship) / PLS%. PLS has no Ads, no CVR
     * overlay, no LMP and no Amz rule. Std Prc is the maximum. A SKU whose Dil is outside every slab has no Sprc Dil
     * price. Clearance SKUs never reach this (computeClearanceTarget).
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array}  $ctx
     * @return array{sprice:float,uncapped:float,base:float,margin:float,lp:float,ship:float}|null
     */
    protected function computeDilTarget(array $row, array $ctx): ?array
    {
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 1.0;
        }
        $lp = (float) ($row['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }
        $ship = (float) ($row['ship'] ?? 0);
        $std = round((float) ($row['standard_price'] ?? 0), 2);
        $dil = (float) ($row['dil'] ?? 0);

        $rule = AmazonDilGroiRule::match($dil, $ctx['rules']);
        if ($rule === null) {
            return null;
        }
        $target = max(0.0, round((float) $rule['groi'], 2));
        // Ageing Rule: the SKU's age band adds its points to the Target NROI, before the Diff rule (floored at 0).
        if (is_array($ctx['age_adj'] ?? null) && $ctx['age_adj'] !== []) {
            $target = AmazonDilGroiRule::adjustGroiForAge(
                $target,
                is_numeric($row['age_days'] ?? null) ? (float) $row['age_days'] : null,
                $ctx['age_adj']
            );
        }
        // Diff rule: judged against the rule price WITHOUT the Diff points, not the live PLS Price (same as Amazon,
        // Purchasing Power, Shein and the page). Read against the live Price, the boosted price is pushed -> Diff drops
        // under the threshold -> the boost is removed -> the lower price is pushed -> Diff rises -> the boost is back,
        // so the SKU alternated between two prices every 10-minute run. A listed SKU (live Price > 0) and a Std Prc
        // are still required.
        if ($std > 0 && round((float) ($row['price'] ?? 0), 2) > 0) {
            $diffBase = AmazonDilGroiRule::suggestedPrice($lp, $ship, $target, 0.0, $margin);
            if ($diffBase !== null && $diffBase > 0) {
                $target = max(0.0, round($target + AmazonDilGroiRule::diffAdjPoints(
                    EbayRuleSprice::stdPriceDiffPct($std, $diffBase),
                    $ctx['diff_adj'] ?? null
                ), 2));
            }
        }

        $raw = round(($lp * (1 + $target / 100) + $ship) / $margin, 2);
        if (! is_finite($raw) || $raw < 0.01) {
            return null;
        }
        $capped = ($std > 0 && $raw > $std) ? $std : $raw;
        if (! is_finite($capped) || $capped < 0.01) {
            return null;
        }

        return [
            'sprice' => round($capped, 2),
            'uncapped' => $raw,
            'base' => $std,
            'margin' => $margin,
            'lp' => $lp,
            'ship' => $ship,
        ];
    }

    /**
     * The Sprc Dil modal settings saved for PLS: slabs and the Diff rule.
     *
     * @return array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array}
     */
    protected function loadDilContext(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', 'pls_dil_vs_groi')->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        $unpacked = AmazonDilGroiRule::unpackStored($saved);
        $rules = $unpacked['rules'] === []
            ? AmazonDilGroiRule::defaultsForChannel('pls')
            : AmazonDilGroiRule::ensureZeroToZero($unpacked['rules']);

        return [
            'rules' => $rules,
            'cvr_adj' => $unpacked['cvr_adj'] ?? null,
            'diff_adj' => $unpacked['diff_adj'] ?? null,
            'lmp_cap' => $unpacked['lmp_cap'] ?? null,
            'age_adj' => $unpacked['age_adj'] ?? null,
        ];
    }

    /**
     * Same rows as /pls-pricing. Listed on PLS, INV > 0, child SKUs only.
     *
     * @param  list<string>|null  $onlySkus
     * @return list<array<string, mixed>>
     */
    protected function loadCandidates(?int $limit, ?array $onlySkus): array
    {
        $want = null;
        if ($onlySkus !== null && $onlySkus !== []) {
            $want = array_flip(array_map(
                static fn ($s) => strtoupper(trim((string) $s)),
                $onlySkus
            ));
        }

        $ageDays = app(\App\Http\Controllers\ProductMaster\InvDaysController::class);
        $agePushBySku = [];
        $ageLastSaleBySku = [];
        try {
            $ageSources = $ageDays->ageDaySources();
            $agePushBySku = $ageSources['push'] ?? [];
            $ageLastSaleBySku = $ageSources['last_sale'] ?? [];
        } catch (Throwable $e) {
            Log::warning('[PlsSprcDilAutoPush] age days lookup failed', ['error' => $e->getMessage()]);
        }

        $res = app(PlsController::class)->pricingDataJson(new Request());
        $pageRows = method_exists($res, 'getData') ? $res->getData(true) : [];
        if (! is_array($pageRows) || isset($pageRows['error'])) {
            throw new \RuntimeException('PLS pricing data load failed');
        }

        $out = [];
        $seen = [];
        foreach ($pageRows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $sku = trim((string) ($r['sku'] ?? ''));
            $key = strtoupper($sku);
            if ($sku === '' || isset($seen[$key]) || str_contains($key, 'PARENT')) {
                continue;
            }
            if ($want !== null && ! isset($want[$key])) {
                continue;
            }
            $inv = (float) ($r['inventory'] ?? 0);
            $price = (float) ($r['price'] ?? 0);
            if (! ($inv > 0) || ! ($price > 0) || (string) ($r['missing'] ?? '') === 'M') {
                continue;
            }
            $seen[$key] = true;

            $out[] = [
                'sku' => $sku,
                'price' => $price,
                'inv' => $inv,
                // Same as the page Dil: raw (OV L30 / INV) × 100, not rounded before slab match.
                'dil' => ((float) ($r['l30'] ?? 0) / $inv) * 100,
                'pls_l30' => (float) ($r['pls_l30'] ?? 0),
                'lp' => (float) ($r['lp'] ?? 0),
                'ship' => (float) ($r['ship'] ?? 0),
                'margin' => (float) ($r['_margin'] ?? 0),
                'age_days' => $ageDays->ageDays($sku, $inv, $agePushBySku, $ageLastSaleBySku),
                'standard_price' => (float) ($r['STANDARD_PRICE'] ?? 0),
                'sprice' => (float) ($r['sprice'] ?? 0),
            ];

            if ($limit !== null && $limit > 0 && count($out) >= $limit) {
                break;
            }
        }

        usort($out, static fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

        return $out;
    }

    /**
     * Save S PRC the way the page does (pls_data_views.value): sprice + sgpft / sroi (with Ship) + status.
     * Same keys and formulas as PlsController::savePlsSprice.
     *
     * @param  array<string, mixed>  $row
     * @param  array{sprice:float,margin:float,lp:float,ship:float}  $computed
     */
    protected function saveSprice(array $row, array $computed, string $status = 'applied'): void
    {
        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku === '') {
            return;
        }
        $view = $this->findView($sku);
        $existing = $this->decodeValue($view->value);

        $sprice = round((float) $computed['sprice'], 2);
        $margin = (float) $computed['margin'];
        $lp = (float) $computed['lp'];
        $ship = (float) $computed['ship'];
        $now = now()->toDateTimeString();

        $existing['sprice'] = $sprice;
        $existing['SPRICE'] = $sprice;
        $existing['sgpft'] = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
        $existing['sroi'] = $lp > 0 ? round((($sprice * $margin - $lp - $ship) / $lp) * 100, 2) : 0;
        $existing['SPRICE_STATUS'] = $status;
        $existing['SPRICE_STATUS_UPDATED_AT'] = $now;
        unset($existing['SPRICE_CLEARED']);
        if ($status === 'pushed') {
            $existing['SPRICE_PUSHED_VALUE'] = $sprice;
            $existing['SPRICE_PUSHED_AT'] = $now;
        }

        $this->persistView($view, $existing);
    }

    /**
     * The page joins pls_data_views by the normalized SKU (NBSP folded), so match the same way.
     * A new row keeps the trimmed Product Master spelling.
     */
    protected function findView(string $sku): PLSDataView
    {
        if ($this->viewsByNorm === null) {
            $this->viewsByNorm = [];
            foreach (PLSDataView::query()->get() as $v) {
                $this->viewsByNorm[$this->normalizeSku((string) $v->sku)] = $v;
            }
        }
        $key = $this->normalizeSku($sku);

        return $this->viewsByNorm[$key] ?? new PLSDataView(['sku' => trim($sku)]);
    }

    /** @param  array<string, mixed>  $value */
    protected function persistView(PLSDataView $view, array $value): void
    {
        $view->value = $value;
        $view->save();
        $this->viewsByNorm[$this->normalizeSku((string) $view->sku)] = $view;
    }

    /** The page joins pls_data_views with this exact folding. */
    protected function normalizeSku(string $sku): string
    {
        return \App\Models\ShopifySku::normalizeSkuForShopifyLookup($sku);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeValue(mixed $value): array
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

    /** @param  callable(string): void|null  $logger */
    protected function log(?callable $logger, string $msg): void
    {
        if ($logger) {
            $logger($msg);
        }
    }
}
