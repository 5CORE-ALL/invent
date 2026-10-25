<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\SheinController;
use App\Models\ChannelMasterCalculatedData;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\SheinDataView;
use App\Support\AmazonDilGroiRule;
use App\Support\EbayRuleSprice;
use App\Support\NegativeSnroiPushGuard;
use App\Support\PushedListingPrice;
use App\Support\SpriceActiveRule;
use App\Support\StdPrcVsDilPricer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shein Std prc vs dil stack → SPRICE → Shein product/price/save API (page not required).
 *
 * Same steps as AmazonSprcDilAutoPushService, with Shein as the source (/shein-pricing):
 *  S PRC = Std Prc × (1 − (Age Disc + Dil Disc + B Disc + 0 Sold Disc + CVR Disc + Rev Disc) / 100)
 *  Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 *  Clearance SKUs show "C" in S PRC: nothing is saved or pushed (same as Amazon).
 *  0 Sold = AL30 (shein_daily_data L30), the page's sold qty.
 *  Caps, same order as Amazon:
 *   0. SNROI Cap: a Dil slab with a Target SNROI % prices S PRC from that SNROI, judged like the page's SNROI%
 *      column, (S PRC × margin − Ship − LP) ÷ LP with no Ads%, instead of the discounts.
 *   1. LMP cap: S PRC at or above LMP becomes LMP when SGROI at LMP is ≥ 20%.
 *   2. Std Prc is the maximum.
 *  Never pushes a price whose SNROI is below 0 (NegativeSnroiPushGuard). The S PRC is still saved.
 *
 * Flow, same as Amazon:
 *  - Twice-daily run: skips a SKU whose last pushed price already equals the target.
 *  - Live pull: the blue catch-up pulls the live Shein price first, so a SKU where Shein is already right
 *    is repaired instead of re-pushed.
 *  - Blue catch-up run (shein:push-blue-prices): SKUs whose Sp. Price ≠ S PRC are pushed again once the
 *    cooldown has passed.
 *
 * Rows come from SheinController::getSheinPricingData(), the same rows the page shows.
 */
class SheinSprcDilAutoPushService
{
    public const CHANNEL = 'shein';

    /** Minutes a pushed price is left alone while the marketplace reviews it (Price column stays at the live price). */
    public const REVIEW_HOLD_MINUTES = 360;

    public const LOCK_KEY = 'shein-sprc-dil-auto-push-run';

    /** @var array<string, SheinDataView>|null */
    private ?array $viewsByNorm = null;

    /**
     * @param  list<string>|null  $onlySkus  Uppercase SKUs; null = all listed candidates
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    public function run(
        bool $dryRun = false,
        bool $skipPush = false,
        ?int $limit = null,
        int $sleepMs = 300,
        ?array $onlySkus = null,
        ?callable $logger = null,
        bool $pushAll = false,
        bool $blueCatchUp = false,
        int $cooldownMinutes = 120,
        int $errorCooldownMinutes = 120,
        int $maxPushes = 0
    ): array {
        StdPrcVsDilPricer::flush();
        $pricer = StdPrcVsDilPricer::forChannel(self::CHANNEL);
        $adsFrac = $this->channelAdsFrac();
        // The page switch decides the S PRC rule: Std prc vs dil, or the Sprc Dil modal (slab + CVR overlay + Diff + LMP cap + Std max).
        $dilContext = SpriceActiveRule::usesStdPrc(self::CHANNEL) ? null : $this->loadDilContext();

        $this->log($logger, $dilContext !== null
            ? 'Shein S PRC rule = Sprc Dil (Dil slab Target SNROI + CVR overlay + Diff, Ship in the price, LMP cap by Dil band, Std max)'
            : 'Loaded Shein Std prc vs dil (shein_std_prc_vs_dil) → Age/Dil/B/0 Sold/CVR/Rev Disc + LMP cap + Std max');

        $stats = [
            'candidates' => 0,
            'applied' => 0,
            'pushed' => 0,
            'skipped_unchanged' => 0,
            'price_repaired' => 0,
            'skipped' => 0,
            'push_failed' => 0,
            'errors' => [],
        ];

        try {
            $rows = $this->loadCandidates($limit, $onlySkus);
        } catch (Throwable $e) {
            $stats['errors'][] = 'load: '.$e->getMessage();
            $this->log($logger, '✗ Load failed: '.$e->getMessage());

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $lock = Cache::lock($skipPush && ! $dryRun ? self::LOCK_KEY.'-save-only' : self::LOCK_KEY, 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another '.'Shein'.' Sprc Dil push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0 listed on ".'Shein');

        $canPush = ! $dryRun && ! $skipPush;
        $total = count($rows);

        try {
            foreach ($rows as $i => $row) {
                try {
                    $computed = $this->computeTarget($row, $pricer, $adsFrac, $dilContext);
                    if ($computed === null) {
                        $stats['skipped']++;
                        continue;
                    }
                    $sprice = (float) $computed['sprice'];

                    if (PushedListingPrice::same($row['price'] ?? 0, $sprice)) {
                        // Live Shein Price already equals the target. Keep the saved S PRC in step, no push.
                        if (! $dryRun && ! PushedListingPrice::same($row['sprice'] ?? 0, $sprice)) {
                            $this->saveSprice($row, $computed, 'pushed');
                            $stats['applied']++;
                        }
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    if ($blueCatchUp) {
                        // Headless "Push on reload": the blue-triangle SKUs (INV > 0, Sp. Price ≠ S PRC), same as Amazon.
                        // The twice-daily run skips a SKU whose last pushed price already equals the target, so a Price
                        // that drifted afterwards (Shein reverted it) stayed blue until someone opened the page.
                        if ($maxPushes > 0 && $stats['pushed'] + $stats['push_failed'] >= $maxPushes) {
                            $this->log($logger, "Reached max pushes ({$maxPushes}) — rest next run");
                            break;
                        }
                        if (! empty($row['push_error_at']) && $this->withinCooldown((string) $row['push_error_at'], $cooldownMinutes)) {
                            // Shein rejected the last push — retry after the cooldown, not every run.
                            $stats['skipped']++;
                            continue;
                        }
                        if ($this->isUnchanged($row, $computed)) {
                            if ($this->withinCooldown((string) ($row['pushed_at'] ?? ''), self::REVIEW_HOLD_MINUTES)
                                || $this->withinCooldown((string) ($row['pushed_at'] ?? ''), $cooldownMinutes)) {
                                // This exact price was pushed and Shein is still reviewing it: the Price column keeps
                                // the live price, so the same price is not pushed again until the hold ends.
                                $stats['skipped_unchanged']++;
                                continue;
                            }
                            // Pull the live Price first. Shein may already be right and only the Price column stale.
                            if (! $dryRun && $canPush) {
                                $pull = $this->pullLive((string) $row['sku'], $sprice);
                                if ($sleepMs > 0) {
                                    usleep($sleepMs * 1000);
                                }
                                if ($pull && ! empty($pull['success']) && PushedListingPrice::same($pull['price'] ?? 0, $sprice)) {
                                    $stats['price_repaired']++;
                                    $stats['skipped_unchanged']++;
                                    $this->saveSprice($row, $computed, 'pushed');
                                    continue;
                                }
                            }
                        }
                    } elseif (! $pushAll && $this->isUnchanged($row, $computed)) {
                        // Last push already equals the target. Refresh the live Shein Price column, no push.
                        // The blue catch-up handles a Price that stayed different.
                        if (! $dryRun && $canPush) {
                            $pull = $this->pullLive((string) $row['sku']);
                            if ($pull && ! empty($pull['success'])) {
                                $stats['price_repaired']++;
                            }
                            if ($sleepMs > 0) {
                                usleep($sleepMs * 1000);
                            }
                        }
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    // The blue catch-up runs every 10 min: only rewrite the saved S PRC when it changed.
                    if (! $dryRun && (! $blueCatchUp || ! PushedListingPrice::same($row['sprice'] ?? 0, $sprice))) {
                        $this->saveSprice($row, $computed, 'applied');
                    }
                    $stats['applied']++;

                    if (! $canPush) {
                        continue;
                    }

                    if (NegativeSnroiPushGuard::shouldSkip(self::CHANNEL, (string) $row['sku'], $sprice)) {
                        $stats['skipped']++;
                        $this->log($logger, 'Skip '.$row['sku'].': negative SNROI');
                        continue;
                    }

                    $this->pushOne($row, $computed, $stats, $logger);
                    if ($sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }
                } catch (Throwable $e) {
                    $stats['push_failed']++;
                    $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                    Log::error('[SheinSprcDilAutoPush] exception', [
                        'channel' => self::CHANNEL,
                        'sku' => $row['sku'] ?? '',
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($logger && (($i + 1) % 100 === 0 || ($i + 1) === $total)) {
                    $this->log($logger, 'Progress '.($i + 1)."/{$total} (pushed {$stats['pushed']}, unchanged {$stats['skipped_unchanged']})");
                }
            }

            $this->log($logger, sprintf(
                'Done: applied=%d pushed=%d unchanged=%d repaired=%d skipped=%d failed=%d%s',
                $stats['applied'],
                $stats['pushed'],
                $stats['skipped_unchanged'],
                $stats['price_repaired'],
                $stats['skipped'],
                $stats['push_failed'],
                ($dryRun || $skipPush) ? ' [no '.'Shein'.' push]' : ''
            ));

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Page S PRC formula (INV > 0 child SKUs): Std rule, LMP cap, Std is the maximum.
     *
     * With a $dilContext (the Sprc Dil switch is ON) the Sprc Dil modal rules price it instead.
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array}|null  $dilContext
     * @return array{sprice:float,uncapped:float,lmp_capped:bool,base:float,margin:float,lp:float,ship:float,lmp:float}|null
     */
    public function computeTarget(array $row, ?StdPrcVsDilPricer $pricer = null, ?float $adsFrac = null, ?array $dilContext = null): ?array
    {
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0)) {
            return null;
        }
        // Clearance SKUs (Inv days clearance = YES) are priced ONLY from the Clearance Target NROI% (Sprc Dil modal, last row), in
        // Std prc vs dil and Sprc Dil alike: (LP × (1 + NROI%) + Ship) ÷ take-home. No slab, Diff, LMP or Std rule, and no Ads.
        if ((new StdPrcVsDilPricer([], self::CHANNEL))->isClearanceRow($row)) {
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
        $lmp = (float) ($row['lmp'] ?? 0);
        $ads = $adsFrac ?? $this->channelAdsFrac();

        $pricer ??= StdPrcVsDilPricer::forChannel(self::CHANNEL);
        // Age / Dil / B / 0 Sold / CVR / Rev discounts, then clearance SKUs keep the discount price.
        $pricerRow = [
            'sku' => (string) ($row['sku'] ?? ''),
            'inv' => $inv,
            'dil' => (float) ($row['dil'] ?? 0),
            'std_price' => $std,
            'age_days' => $row['age_days'] ?? null,
            'cvr' => 0.0,
            'reviews' => 0.0,
            // 0 Sold = AL30 (shein_daily_data), same as the page.
            'al30' => (float) ($row['al30'] ?? 0),
            'lp' => $lp,
            'ship' => $ship,
            'margin' => $margin,
            // Shein's SNROI% column has no Ads%, so the SNROI Cap must not take any off either.
            'ads' => 0.0,
        ];
        if (array_key_exists('clearance', $row)) {
            $pricerRow['clearance'] = $row['clearance'];
        }
        $effective = $pricer->priceFromRow($pricerRow);
        if ($effective === null || ! ($effective > 0)) {
            return null;
        }

        // Same cap order as Amazon: LMP cap (SGROI at LMP ≥ 20%), then Std Prc is the maximum.
        $capped = AmazonDilGroiRule::capSpriceToLmp($effective, $lmp, $lp, $ship, $margin, max($std, $effective));
        $lmpCapped = $lmp > 0
            && ($effective - $capped) > 0.009
            && abs($capped - round($lmp, 2)) <= 0.02;

        if (! is_finite($capped) || $capped < 0.01) {
            return null;
        }

        return [
            'sprice' => round($capped, 2),
            'uncapped' => round($effective, 2),
            'lmp_capped' => $lmpCapped,
            'base' => round($std, 2),
            'margin' => $margin,
            'lp' => $lp,
            'ship' => $ship,
            'lmp' => round($lmp, 2),
        ];
    }

    /**
     * Clearance SKU: (LP × (1 + Clearance NROI%) + Ship) ÷ take-home. Shein's SNROI has no Ads%. No LMP cap and no Std maximum.
     *
     * @param  array<string, mixed>  $row
     * @return array{sprice:float,uncapped:float,lmp_capped:bool,base:float,margin:float,lp:float,ship:float,lmp:float}|null
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
        // A row may carry its own clearance_nroi (tests); otherwise the saved Sprc Dil modal value.
        $nroi = isset($row['clearance_nroi']) && is_numeric($row['clearance_nroi']) ? (float) $row['clearance_nroi'] : $this->clearanceNroi();
        $price = round(($lp * (1 + max(0.0, $nroi) / 100) + $ship) / $margin, 2);
        if (! is_finite($price) || $price < 0.01) {
            return null;
        }

        return [
            'sprice' => $price,
            'uncapped' => $price,
            'lmp_capped' => false,
            'base' => round((float) ($row['standard_price'] ?? 0), 2),
            'margin' => $margin,
            'lp' => $lp,
            'ship' => $ship,
            'lmp' => round((float) ($row['lmp'] ?? 0), 2),
        ];
    }

    /** Clearance Target NROI% saved with the Shein Sprc Dil rules (last row of the modal). Default 0. */
    protected function clearanceNroi(): float
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::CHANNEL.'_dil_vs_groi')->first();
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($row?->visibility) ? $row->visibility : null);
        $n = $unpacked['clearance_nroi'] ?? null;

        return (is_numeric($n) && (float) $n >= 0) ? (float) $n : 0.0;
    }

    /**
     * Sprc Dil switch ON, same as the page (ebay-sprc-dil + /amazon-tabulator-view order):
     * Dil slab Target SNROI (the nearest slab when Dil is outside the table), CVR overlay (only with views, no L60 CVR so the
     * level thresholds), Diff points, then price = (LP x (1 + Target%) + Ship) / take-home. No Ads, no Amz rule.
     * Then the LMP cap (SGROI at LMP >= 20% -> LMP x (1 - Dil band %)), then Std Prc is the maximum.
     * Clearance SKUs never reach this (computeClearanceTarget).
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array}  $ctx
     * @return array{sprice:float,uncapped:float,lmp_capped:bool,base:float,margin:float,lp:float,ship:float,lmp:float}|null
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
        $lmp = (float) ($row['lmp'] ?? 0);
        $dil = (float) ($row['dil'] ?? 0);

        $rule = AmazonDilGroiRule::matchOrNearest($dil, $ctx['rules']);
        if ($rule === null) {
            return null;
        }
        $target = (float) $rule['groi'];
        if ((float) ($row['views'] ?? 0) > 0) {
            $target = AmazonDilGroiRule::adjustGroiForCvrLevel($target, (float) ($row['cvr'] ?? 0), $ctx['cvr_adj'] ?? null);
        }
        $target = max(0.0, round($target, 2));
        $diffPct = EbayRuleSprice::stdPriceDiffPct($std, (float) ($row['price'] ?? 0));
        $target = max(0.0, round($target + AmazonDilGroiRule::diffAdjPoints($diffPct, $ctx['diff_adj'] ?? null), 2));

        $raw = round(($lp * (1 + $target / 100) + $ship) / $margin, 2);
        if (! is_finite($raw) || $raw < 0.01) {
            return null;
        }
        $reducePct = AmazonDilGroiRule::lmpCapReducePct($dil, $ctx['lmp_cap'] ?? null);
        $sprice = round(AmazonDilGroiRule::capSpriceToLmp($raw, $lmp, $lp, $ship, $margin, $std, $reducePct), 2);
        $lmpCapped = $lmp > 0 && ($raw - $sprice) > 0.009 && abs($sprice - round($lmp * (1 - $reducePct / 100), 2)) <= 0.02;
        if (! is_finite($sprice) || $sprice < 0.01) {
            return null;
        }

        return [
            'sprice' => $sprice,
            'uncapped' => $raw,
            'lmp_capped' => $lmpCapped,
            'base' => $std,
            'margin' => $margin,
            'lp' => $lp,
            'ship' => $ship,
            'lmp' => round($lmp, 2),
        ];
    }

    /**
     * The Sprc Dil modal settings saved for Shein: slabs, CVR overlay, Diff rule and LMP cap.
     *
     * @return array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array}
     */
    protected function loadDilContext(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::CHANNEL.'_dil_vs_groi')->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        $unpacked = AmazonDilGroiRule::unpackStored($saved);
        $rules = $unpacked['rules'] === []
            ? AmazonDilGroiRule::defaultsForChannel(self::CHANNEL)
            : AmazonDilGroiRule::ensureZeroToZero($unpacked['rules']);

        return [
            'rules' => $rules,
            'cvr_adj' => $unpacked['cvr_adj'] ?? null,
            'diff_adj' => $unpacked['diff_adj'] ?? null,
            'lmp_cap' => $unpacked['lmp_cap'] ?? null,
        ];
    }

    /**
     * Channel Ads% as a fraction. Same source as NegativeSnroiPushGuard so the
     * negative-SNROI check agree.
     */
    protected function channelAdsFrac(): float
    {
        try {
            $pct = (float) (ChannelMasterCalculatedData::query()
                ->where('channel', 'like', self::CHANNEL.'%')
                ->value('ads_percentage') ?? 0);
        } catch (Throwable $e) {
            return 0.0;
        }

        return $pct > 0 ? min(0.99, $pct / 100) : 0.0;
    }

    /**
     * Same rows as /shein-pricing. Listed on Shein, INV > 0, child SKUs only.
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
            Log::warning('[SheinSprcDilAutoPush] age days lookup failed', ['error' => $e->getMessage()]);
        }

        $res = app(SheinController::class)->getSheinPricingData(new Request());
        $payload = method_exists($res, 'getData') ? $res->getData(true) : [];
        $pageRows = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($pageRows)) {
            throw new \RuntimeException('Shein pricing data load failed'
                .(is_array($payload) && ! empty($payload['error']) ? ': '.$payload['error'] : ''));
        }

        $out = [];
        $seen = [];
        foreach ($pageRows as $r) {
            if (! is_array($r) || ! empty($r['is_parent'])) {
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
            $inv = (float) ($r['inv'] ?? 0);
            $price = (float) ($r['special_offer'] ?? 0);
            if (! ($inv > 0) || ! ($price > 0) || (string) ($r['missing'] ?? '') === 'M') {
                continue;
            }
            $seen[$key] = true;

            $status = strtolower((string) ($r['SPRICE_STATUS'] ?? ''));
            $statusAt = $r['SPRICE_STATUS_UPDATED_AT'] ?? null;

            $out[] = [
                'sku' => $sku,
                'price' => $price,
                'inv' => $inv,
                // Same as the page Dil: raw (OV L30 / INV) × 100, not rounded before slab match.
                'dil' => ((float) ($r['ov_l30'] ?? 0) / $inv) * 100,
                'al30' => (float) ($r['al30'] ?? 0),
                'views' => (float) ($r['views'] ?? 0),
                'cvr' => (float) ($r['cvr'] ?? 0),
                'lp' => (float) ($r['lp'] ?? 0),
                'ship' => (float) ($r['ship'] ?? 0),
                'margin' => (float) ($r['_margin'] ?? 0),
                'age_days' => $ageDays->ageDays($sku, $inv, $agePushBySku, $ageLastSaleBySku),
                'standard_price' => (float) ($r['STANDARD_PRICE'] ?? 0),
                'lmp' => is_numeric($r['lmp_price'] ?? null) ? (float) $r['lmp_price'] : 0.0,
                'sprice' => (float) ($r['sprice'] ?? 0),
                'pushed_value' => isset($r['SPRICE_PUSHED_VALUE']) && is_numeric($r['SPRICE_PUSHED_VALUE'])
                    ? (float) $r['SPRICE_PUSHED_VALUE']
                    : null,
                // Last successful push time. Kept when a later push failed, so a recent push is not repeated early.
                'pushed_at' => $r['SPRICE_PUSHED_AT'] ?? ($status === 'pushed' ? $statusAt : null),
                'push_error_at' => $status === 'error' ? $statusAt : null,
            ];

            if ($limit !== null && $limit > 0 && count($out) >= $limit) {
                break;
            }
        }

        usort($out, static fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

        return $out;
    }

    /**
     * Last push already equals the target.
     *
     * @param  array<string, mixed>  $row
     * @param  array{sprice:float}  $computed
     */
    protected function isUnchanged(array $row, array $computed): bool
    {
        return PushedListingPrice::same($row['pushed_value'] ?? null, $computed['sprice']);
    }

    protected function withinCooldown(string $at, int $cooldownMinutes): bool
    {
        if ($cooldownMinutes <= 0 || $at === '') {
            return false;
        }
        try {
            return Carbon::parse($at)->gt(now()->subMinutes($cooldownMinutes));
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Save S PRC the way the page does (shein_data_views.value): SPRICE + SGPFT / SROI + status.
     * SGPFT / SROI include Ship, same as SheinController::saveSheinSpriceUpdates.
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
        $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
        $sroi = $lp > 0 ? round((($sprice * $margin - $lp - $ship) / $lp) * 100, 2) : 0;
        $now = now()->toDateTimeString();

        $existing['SPRICE'] = $sprice;
        $existing['SGPFT'] = $sgpft;
        $existing['SROI'] = $sroi;
        $existing['SPRICE_STATUS'] = $status;
        $existing['SPRICE_STATUS_UPDATED_AT'] = $now;
        if ($status === 'pushed') {
            $existing['SPRICE_PUSHED_VALUE'] = $sprice;
            $existing['SPRICE_PUSHED_AT'] = $now;
        }

        $this->persistView($view, $existing);
    }

    /**
     * Push one SKU through SheinController::pushPricingPrice (Shein product/price/save). That is the same call the
     * old page push and the old runner used: it also writes the sale price to shein_pricing_prices / shein_metrics.
     * Shein accepting the save is the confirmation, so there is no extra pull right after the push. The blue
     * catch-up pulls the live price before it pushes again.
     *
     * @param  array<string, mixed>  $row
     * @param  array{sprice:float}  $computed
     * @param  array<string, mixed>  $stats
     * @param  callable(string): void|null  $logger
     */
    protected function pushOne(array $row, array $computed, array &$stats, ?callable $logger): void
    {
        $sku = (string) $row['sku'];
        $price = round((float) $computed['sprice'], 2);
        $result = $this->pushPrice($sku, $price);

        if (! empty($result['success'])) {
            $stats['pushed']++;
            $this->savePushMeta($sku, 'pushed', $price);

            return;
        }

        $stats['push_failed']++;
        $err = (string) ($result['message'] ?? 'Shein price push failed');
        $stats['errors'][] = $sku.': '.$err;
        $this->log($logger, 'Push failed '.$sku.': '.$err);
        Log::error('Shein Sprc Dil push failed', ['sku' => $sku, 'price' => $price, 'error' => $err]);
        $this->savePushMeta($sku, 'error', null);
    }

    /**
     * Live Shein price. With an expected price the Price column only moves when Shein shows it;
     * without one the live price is written as is.
     *
     * @return array{success?:bool,price?:?float,message?:string}|null
     */
    protected function pullLive(string $sku, ?float $expected = null): ?array
    {
        try {
            return app(ChannelPushedPricePullService::class)
                ->pullSkus(self::CHANNEL, [$sku], $expected !== null ? [strtoupper(trim($sku)) => $expected] : [])[0] ?? null;
        } catch (Throwable $e) {
            Log::warning('[SheinSprcDilAutoPush] live price pull failed', ['channel' => self::CHANNEL, 'sku' => $sku, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array{success:bool,message?:string,price?:?float} */
    protected function pushPrice(string $sku, float $price): array
    {
        try {
            $req = Request::create('/shein/pricing-push-price', 'POST', [
                'updates' => [['sku' => $sku, 'price' => $price]],
            ]);
            $req->headers->set('Accept', 'application/json');
            $res = app(SheinController::class)->pushPricingPrice($req, app(SheinApiService::class));
            $payload = method_exists($res, 'getData') ? $res->getData(true) : [];
            $status = method_exists($res, 'getStatusCode') ? $res->getStatusCode() : 200;
            $payload = is_array($payload) ? $payload : [];
            $ok = $status < 400 && ! empty($payload['success']) && (int) ($payload['pushed'] ?? 0) > 0;
            if ($ok) {
                return [
                    'success' => true,
                    'price' => isset($payload['price']) && is_numeric($payload['price']) ? (float) $payload['price'] : $price,
                ];
            }

            return [
                'success' => false,
                'message' => (string) (($payload['results'][0]['error'] ?? null) ?: ($payload['message'] ?? 'Shein price push failed')),
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Status, time and pushed value (same keys as the page push wrote). */
    protected function savePushMeta(string $sku, string $status, ?float $price): void
    {
        try {
            $view = $this->findView($sku);
            $existing = $this->decodeValue($view->value);
            $now = now()->toDateTimeString();
            $existing['SPRICE_STATUS'] = $status;
            $existing['SPRICE_STATUS_UPDATED_AT'] = $now;
            if ($status === 'pushed' && $price !== null && $price > 0) {
                $existing['SPRICE_PUSHED_VALUE'] = $price;
                $existing['SPRICE_PUSHED_AT'] = $now;
                $existing['SPRICE_PUSHED_BY'] = 'Server (Sprc Dil auto push)';
            }
            $this->persistView($view, $existing);
        } catch (Throwable $e) {
            Log::error('[SheinSprcDilAutoPush] status save failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The page joins the data view by the normalized SKU (NBSP folded, whitespace collapsed, upper), so match the
     * same way. A new row keeps the trimmed Product Master spelling.
     */
    protected function findView(string $sku): SheinDataView
    {
        if ($this->viewsByNorm === null) {
            $this->viewsByNorm = [];
            foreach (SheinDataView::query()->get() as $v) {
                $this->viewsByNorm[$this->normalizeSku((string) $v->sku)] ??= $v;
            }
        }

        return $this->viewsByNorm[$this->normalizeSku($sku)] ?? new SheinDataView(['sku' => trim($sku)]);
    }

    /** @param  array<string, mixed>  $value */
    protected function persistView(SheinDataView $view, array $value): void
    {
        $view->value = $value;
        $view->save();
        $this->viewsByNorm[$this->normalizeSku((string) $view->sku)] = $view;
    }

    /** Same folding as SheinController::normalizeSheinSkuExact. */
    protected function normalizeSku(string $sku): string
    {
        $sku = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', trim($sku));
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $sku);

        return strtoupper(preg_replace('/\s+/u', ' ', $clean !== false ? $clean : $sku));
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
