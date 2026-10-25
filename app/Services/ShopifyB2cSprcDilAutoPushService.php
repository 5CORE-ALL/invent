<?php

namespace App\Services;

use App\Http\Controllers\Channels\ChannelMasterController;
use App\Http\Controllers\MarketPlace\Shopifyb2cController;
use App\Models\Shopifyb2cDataView;
use App\Support\AmazonDilGroiRule;
use App\Models\ChannelTabulatorColumnSetting;
use App\Support\EbayRuleSprice;
use App\Support\PushedListingPrice;
use App\Support\SpriceActiveRule;
use App\Support\StdPrcVsDilPricer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shopify B2C Std prc vs dil stack → SPRICE → Shopify live price (page not required).
 *
 * Same steps as AmazonSprcDilAutoPushService, with Shopify B2C as the source:
 *  S PRC = Std Prc × (1 − (Age Disc + Dil Disc + B Disc + 0 Sold Disc + CVR Disc + Rev Disc) / 100)
 *  Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 *  Then LMP cap when LMP is lower and SGROI at LMP ≥ 20%. Std Prc is the maximum.
 *  SNROI Cap (same as Amazon): a Dil slab with a Target SNROI % prices S PRC from that SNROI instead of the discounts,
 *  then the LMP cap and the Amz cap below still apply to it. Std Prc stays the maximum.
 *  Clearance SKUs (Inv days clearance = YES) are priced only from the Clearance Target NROI% (last row of the Sprc Dil
 *  modal), in Std prc vs dil and Sprc Dil alike: (LP × (1 + NROI%) + Ship) ÷ (0.95 − Ads%). No slab, Diff, CVR, LMP, Std
 *  or Amz rule touches them. They are saved and pushed like any other SKU and the page shows a red C after the price.
 *
 * Then the Amz cap (same as the old page), on the LMP-capped price: LMP below A Price → A Price;
 * price above A Price stays; price at/below A Price → A Price.
 * AMZ_CAPPED / SPRICE_UNCAPPED are saved so the page can show the "Amz" label without recalculating.
 *
 * This is the only S PRC rule on /shopify-b2c-pricing. Sprc Dil (Dil → Target NROI),
 * Dil vs PRMT / CVR vs CPN and the 0 Sold coupon were removed.
 *
 * Rows come from Shopifyb2cController::getViewShopifyB2cTabularData(), the same rows the page shows
 * (INV, OV L30, B2C L30, Views, LP, Ship, Google LMP, Std Prc, saved SPRICE).
 * The command then queues the live push (ChannelPushSpriceDailyEnqueue), which skips negative SNROI.
 */
class ShopifyB2cSprcDilAutoPushService
{
    public const LOCK_KEY = 'shopify-b2c-sprc-dil-auto-push-run';

    /** Shopify B2C take-home, same constant as the page and Shopifyb2cController. */
    public const MARGIN = 0.95;

    /**
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
        $pricer = StdPrcVsDilPricer::forChannel('shopify_b2c');
        $adsPct = $this->channelAdsPercent();
        // The page switch (same as Amazon): Std prc vs dil <-> Sprc Dil. Only the ON rule fills S PRC.
        $dilContext = SpriceActiveRule::forChannel('shopify_b2c') === 'dil' ? self::dilContext() : null;

        $this->log($logger, $dilContext !== null
            ? 'Sprc Dil switch ON → Dil slab Target SNROI + CVR overlay + Diff rule + LMP cap (Dil band) + Std max + Amz cap. Ads%='.$adsPct
            : 'Loaded Shopify B2C Std prc vs dil (shopify_b2c_std_prc_vs_dil) → Age/Dil/B/0 Sold/CVR/Rev Disc + LMP cap + Amz cap. Ads%='.$adsPct);

        $stats = [
            'candidates' => 0,
            'applied' => 0,
            'skipped_unchanged' => 0,
            'skipped' => 0,
            // Saved, but the push skips them: SNROI is below 0 at this S PRC (NegativeSnroiPushGuard).
            'neg_snroi' => 0,
            'errors' => [],
        ];

        $lock = Cache::lock(self::LOCK_KEY, 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another Shopify B2C Sprc Dil run is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'stats' => $stats];
        }

        try {
            try {
                $rows = $this->loadCandidates($limit, $onlySkus);
            } catch (Throwable $e) {
                $stats['errors'][] = 'load: '.$e->getMessage();
                $this->log($logger, '✗ Load failed: '.$e->getMessage());

                return ['dry_run' => $dryRun, 'stats' => $stats];
            }

            $stats['candidates'] = count($rows);
            $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0");

            $total = count($rows);
            foreach ($rows as $i => $row) {
                try {
                    $computed = $this->computeTarget($row, $pricer, $adsPct, $dilContext);
                    if ($computed === null) {
                        $stats['skipped']++;
                        continue;
                    }
                    // Only SKUs whose live Price differs from S PRC matter: those stay blue because the push skips them.
                    if (($computed['snroi'] ?? 0) < 0 && ! PushedListingPrice::same($row['price'] ?? 0, $computed['sprice'])) {
                        $stats['neg_snroi']++;
                        if ($logger && $stats['neg_snroi'] <= 25) {
                            $this->log($logger, sprintf(
                                'neg SNROI %s: S PRC=%.2f LP=%.2f ship=%.2f A Price=%.2f LMP=%.2f Std=%.2f → SNROI %.1f%%',
                                $row['sku'], $computed['sprice'], $row['lp'], $row['ship'], $row['amz'], $row['lmp'], $row['standard_price'], $computed['snroi']
                            ));
                        }
                    }
                    if ($this->isUnchanged($row, $computed)) {
                        $stats['skipped_unchanged']++;
                        continue;
                    }
                    if (! $dryRun) {
                        $this->saveSprice((string) $row['sku'], $computed);
                    }
                    $stats['applied']++;
                } catch (Throwable $e) {
                    $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                    Log::error('[ShopifyB2cSprcDilAutoPush] exception', [
                        'sku' => $row['sku'] ?? '',
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($logger && (($i + 1) % 200 === 0 || ($i + 1) === $total)) {
                    $this->log($logger, 'Progress '.($i + 1)."/{$total} (applied {$stats['applied']}, unchanged {$stats['skipped_unchanged']})");
                }
            }

            $this->log($logger, sprintf(
                'Done: candidates=%d applied=%d unchanged=%d skipped=%d neg_snroi(not pushed)=%d errors=%d%s',
                $stats['candidates'],
                $stats['applied'],
                $stats['skipped_unchanged'],
                $stats['skipped'],
                $stats['neg_snroi'],
                count($stats['errors']),
                $dryRun ? ' [dry run]' : ''
            ));
        } finally {
            optional($lock)->release();
        }

        if (! $dryRun && (int) $stats['applied'] > 0) {
            Shopifyb2cController::forgetTabularDataCache();
        }

        return ['dry_run' => $dryRun, 'stats' => $stats];
    }

    /**
     * Page S PRC formula (INV > 0 child SKUs): the rule price of the ON switch, LMP cap, then the Amz cap.
     *  - Std prc vs dil switch ON ($dilContext = null): Std rule (Std is the maximum), LMP cap = LMP.
     *  - Sprc Dil switch ON ($dilContext from dilContext()): Dil slab Target SNROI + CVR overlay + Diff rule, LMP cap
     *    = LMP × (1 − x%) with x by the SKU's Dil band, Std Prc is the maximum (same as Amazon / eBay).
     * The Amz cap is the same in both and runs last.
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:array,cvr_adj:array,lmp_cap:array,diff_adj:array}|null  $dilContext
     * @return array{sprice:float,uncapped:float,amz_capped:bool,lmp_capped:bool,base:float,sgpft:float,snpft:float,sroi:float,snroi:float}|null
     */
    public function computeTarget(array $row, ?StdPrcVsDilPricer $pricer = null, float $adsPct = 0.0, ?array $dilContext = null): ?array
    {
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0)) {
            return null;
        }
        // Clearance: only the Clearance Target NROI% prices it, whichever rule is active. Needs LP, not Std Prc.
        if (($pricer ?? new StdPrcVsDilPricer([], 'shopify_b2c'))->isClearanceRow($row)) {
            return $this->finishClearance($row, $adsPct);
        }

        $dilOn = $dilContext !== null;
        $std = (float) ($row['standard_price'] ?? 0);
        // Std prc vs dil prices off Std Prc. Sprc Dil prices off the Dil slab, so a blank Std Prc only means "no maximum".
        if (! $dilOn && ! ($std > 0)) {
            return null;
        }
        $lp = (float) ($row['lp'] ?? 0);
        $ship = (float) ($row['ship'] ?? 0);
        $margin = self::MARGIN;
        $lmp = (float) ($row['lmp'] ?? 0);

        if ($dilOn) {
            $effective = self::dilPrice($row, $dilContext, $adsPct);
            if ($effective === null || ! ($effective > 0)) {
                return null;
            }
            // Sprc Dil solves a price from a Target SNROI that knows nothing about Std, so Std Prc caps it.
            $stdCeiling = $std;
            $lmpReducePct = AmazonDilGroiRule::lmpCapReducePct((float) ($row['dil'] ?? 0), $dilContext['lmp_cap'] ?? null);

            return $this->finish($row, $effective, $stdCeiling, $lmpReducePct, $adsPct);
        }

        $pricer ??= StdPrcVsDilPricer::forChannel('shopify_b2c');
        // Age / Dil / B / 0 Sold / CVR / Rev discounts, or the SNROI Cap price when the Dil slab has a Target SNROI %.
        $pricerRow = [
            'sku' => (string) ($row['sku'] ?? ''),
            'inv' => $inv,
            'dil' => (float) ($row['dil'] ?? 0),
            'std_price' => $std,
            'age_days' => $row['age_days'] ?? null,
            'cvr' => (float) ($row['cvr'] ?? 0),
            // The page has no review count for Shopify B2C, so Rev Disc stays 0 here too.
            'reviews' => 0.0,
            // 0 Sold = B2C L30 (shopify_b2c_daily_data), same as the page.
            'b2c_l30' => (float) ($row['b2c_l30'] ?? 0),
            'lp' => $lp,
            'ship' => $ship,
            'margin' => $margin,
            // SNROI Cap: judged like the page's SNROI% column, (S PRC × (take-home − Ads%) − Ship − LP) ÷ LP, with the
            // same channel Ads% (TCOS badge) the page uses.
            'ads' => $adsPct > 0 ? $adsPct / 100 : 0.0,
        ];
        if (array_key_exists('clearance', $row)) {
            $pricerRow['clearance'] = $row['clearance'];
        }
        $effective = $pricer->priceFromRow($pricerRow);
        if ($effective === null || ! ($effective > 0)) {
            return null;
        }

        return $this->finish($row, (float) $effective, max($std, (float) $effective), 0.0, $adsPct);
    }

    /**
     * Sprc Dil price (switch ON), the same chain as the page's Sprc Dil column and Amazon / eBay:
     *  1. Dil slab (nearest slab outside the table) → Target SNROI
     *  2. CVR overlay: CVR < down_lt → down_adj, CVR > up_gt → up_adj (needs views and B2C L30 > 0), floored at 0
     *  3. Diff rule: Diff = (Std Prc − Price) / Price above gt_pct adds `points`
     *  4. price = (LP × (1 + target%) + Ship) ÷ (0.95 − Ads%)
     * LP is required. Null with no slab.
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:array,cvr_adj:array,lmp_cap:array,diff_adj:array}  $ctx
     */
    public static function dilPrice(array $row, array $ctx, float $adsPct = 0.0): ?float
    {
        $meta = self::dilMeta($row, $ctx, $adsPct);

        return $meta['price'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{rules:array,cvr_adj:array,lmp_cap:array,diff_adj:array}  $ctx
     * @return array{slab:float,cvr_adj:float,diff_adj:float,target:float,price:?float}|null
     */
    public static function dilMeta(array $row, array $ctx, float $adsPct = 0.0): ?array
    {
        if (! ((float) ($row['inv'] ?? 0) > 0)) {
            return null;
        }
        $slab = AmazonDilGroiRule::matchOrNearest((float) ($row['dil'] ?? 0), $ctx['rules'] ?? []);
        if ($slab === null) {
            return null;
        }
        $slabTarget = (float) ($slab['nroi'] ?? $slab['groi']);
        $cvrCfg = AmazonDilGroiRule::normalizeCvrAdj(is_array($ctx['cvr_adj'] ?? null) ? $ctx['cvr_adj'] : null);
        $cvrAdj = 0.0;
        // Shopify B2C has no L60 CVR: Down / Up follow the thresholds. No views or B2C L30 = 0 skips the overlay.
        if ((float) ($row['views'] ?? 0) > 0 && (float) ($row['b2c_l30'] ?? 0) > 0) {
            $cvr = (float) ($row['cvr'] ?? 0);
            if ($cvr < $cvrCfg['down_lt']) {
                $cvrAdj = $cvrCfg['down_adj'];
            } elseif ($cvr > $cvrCfg['up_gt']) {
                $cvrAdj = $cvrCfg['up_adj'];
            }
        }
        // Floor at 0 after the CVR overlay, then add the Diff points (same order as the page).
        $afterCvr = max(0.0, round($slabTarget + $cvrAdj, 2));
        $diffPct = EbayRuleSprice::stdPriceDiffPct((float) ($row['standard_price'] ?? 0), (float) ($row['price'] ?? 0));
        $diffAdj = AmazonDilGroiRule::diffAdjPoints($diffPct, is_array($ctx['diff_adj'] ?? null) ? $ctx['diff_adj'] : null);
        $target = max(0.0, round($afterCvr + $diffAdj, 2));
        $price = AmazonDilGroiRule::suggestedPrice(
            (float) ($row['lp'] ?? 0),
            (float) ($row['ship'] ?? 0),
            $target,
            max(0.0, $adsPct),
            self::MARGIN
        );

        return [
            'slab' => $slabTarget,
            'cvr_adj' => $cvrAdj,
            'diff_adj' => $diffAdj,
            'target' => $target,
            'price' => ($price !== null && $price >= 0.01) ? $price : null,
        ];
    }

    /** Clearance Target NROI% saved with the Shopify B2C Sprc Dil rules (last row of the modal). Default 0. */
    public static function clearanceNroi(): float
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', 'shopify_b2c_dil_vs_groi')->first();
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($row?->visibility) ? $row->visibility : null);
        $n = $unpacked['clearance_nroi'] ?? null;

        return (is_numeric($n) && (float) $n >= 0) ? (float) $n : 0.0;
    }

    /**
     * Clearance price, no cap of any kind: (LP × (1 + Clearance NROI%) + Ship) ÷ (0.95 − Ads%).
     *
     * @param  array<string, mixed>  $row
     * @return array{sprice:float,uncapped:float,amz_capped:bool,lmp_capped:bool,base:float,sgpft:float,snpft:float,sroi:float,snroi:float}|null
     */
    protected function finishClearance(array $row, float $adsPct): ?array
    {
        $lp = (float) ($row['lp'] ?? 0);
        $ship = (float) ($row['ship'] ?? 0);
        // A row may carry its own clearance_nroi (tests); otherwise the saved Sprc Dil modal value.
        $nroi = isset($row['clearance_nroi']) && is_numeric($row['clearance_nroi']) ? (float) $row['clearance_nroi'] : self::clearanceNroi();
        $price = AmazonDilGroiRule::suggestedPrice($lp, $ship, $nroi, max(0.0, $adsPct), self::MARGIN);
        if ($price === null || ! ($price >= 0.01)) {
            return null;
        }
        $sprice = round($price, 2);
        $margin = self::MARGIN;
        $gross = ($sprice * $margin) - $lp - $ship;
        $sgpft = ($gross / $sprice) * 100;
        $sroi = $lp > 0 ? ($gross / $lp) * 100 : 0.0;
        $snroi = $lp > 0 ? (($gross - ($sprice * ($adsPct / 100))) / $lp) * 100 : 0.0;

        return [
            'sprice' => $sprice,
            'rule_price' => $sprice,
            'uncapped' => $sprice,
            'amz_capped' => false,
            'lmp_capped' => false,
            'base' => round((float) ($row['standard_price'] ?? 0), 2),
            'sgpft' => $sgpft,
            'snpft' => $sgpft - $adsPct,
            'sroi' => $sroi,
            'snroi' => $snroi,
        ];
    }

    /**
     * The Sprc Dil modal settings saved for Shopify B2C: Dil slabs, CVR overlay, LMP cap (reduce % by Dil band), Diff rule.
     *
     * @return array{rules:array,cvr_adj:array,lmp_cap:array,diff_adj:array}
     */
    public static function dilContext(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', 'shopify_b2c_dil_vs_groi')->first();
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($row?->visibility) ? $row->visibility : null);
        $rules = $unpacked['rules'] === []
            ? AmazonDilGroiRule::defaultsForChannel('shopify_b2c')
            : AmazonDilGroiRule::ensureZeroToZero($unpacked['rules']);

        return [
            'rules' => $rules,
            'cvr_adj' => $unpacked['cvr_adj'],
            'lmp_cap' => $unpacked['lmp_cap'],
            'diff_adj' => $unpacked['diff_adj'],
        ];
    }

    /**
     * The caps every Shopify B2C S PRC goes through, whichever rule priced it:
     *  1. LMP cap on the rule price (LMP lower and SGROI at LMP ≥ 20%). Std prc vs dil: S PRC = LMP. Sprc Dil: LMP × (1 − x%).
     *  2. Std Prc ceiling.
     *  3. Amz cap (A Price from the Amazon datasheet), on the LMP-capped price:
     *       - LMP below A Price                → S PRC = A Price
     *       - capped price above A Price       → capped price stays
     *       - capped price at/below A Price    → S PRC = A Price
     *
     * @param  array<string, mixed>  $row
     * @return array{sprice:float,uncapped:float,amz_capped:bool,lmp_capped:bool,base:float,sgpft:float,snpft:float,sroi:float,snroi:float}|null
     */
    protected function finish(array $row, float $effective, float $stdCeiling, float $lmpReducePct, float $adsPct): ?array
    {
        $std = (float) ($row['standard_price'] ?? 0);
        $lp = (float) ($row['lp'] ?? 0);
        $ship = (float) ($row['ship'] ?? 0);
        $margin = self::MARGIN;
        $lmp = (float) ($row['lmp'] ?? 0);
        $amz = round((float) ($row['amz'] ?? 0), 2);
        $effective = round($effective, 2);
        $uncapped = AmazonDilGroiRule::capToStdWhenNoLmp($effective, 0.0, $stdCeiling);
        $capped = round(AmazonDilGroiRule::capSpriceToLmp($effective, $lmp, $lp, $ship, $margin, $stdCeiling, $lmpReducePct), 2);
        $lmpCapped = $lmp > 0
            && ($effective - $capped) > 0.009
            && abs($capped - AmazonDilGroiRule::lmpTargetPrice($lmp, $lmpReducePct)) <= 0.02;
        $amzCapped = false;
        $final = $capped;
        if ($amz > 0) {
            $lmpBelowAmz = $lmp > 0 && $lmp + 0.0001 < $amz;
            if ($lmpBelowAmz || $capped <= $amz + 0.0001) {
                $final = $amz;
                $amzCapped = $lmpBelowAmz || abs($amz - $capped) > 0.004;
                if ($amzCapped) {
                    $lmpCapped = false;
                }
            }
        }

        if (! is_finite($final) || $final < 0.01) {
            return null;
        }
        $sprice = round($final, 2);

        // Same S-metrics the page and Shopifyb2cController::calculateAndSaveSprice write.
        $gross = ($sprice * $margin) - $lp - $ship;
        $sgpft = ($gross / $sprice) * 100;
        $sroi = $lp > 0 ? ($gross / $lp) * 100 : 0.0;
        $snroi = $lp > 0 ? (($gross - ($sprice * ($adsPct / 100))) / $lp) * 100 : 0.0;

        return [
            'sprice' => $sprice,
            // The rule price before any cap. The page uses it to show the LMP / Std Prc cap triangles and the hover card.
            'rule_price' => $effective,
            'uncapped' => round($uncapped, 2),
            'amz_capped' => $amzCapped,
            'lmp_capped' => $lmpCapped,
            'base' => round($std, 2),
            'sgpft' => $sgpft,
            'snpft' => $sgpft - $adsPct,
            'sroi' => $sroi,
            'snroi' => $snroi,
        ];
    }

    /**
     * Same rows as /shopify-b2c-pricing. Child SKUs with INV > 0 and a live Shopify price.
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
            Log::warning('[ShopifyB2cSprcDilAutoPush] age days lookup failed', ['error' => $e->getMessage()]);
        }

        // A fresh build, so the run never prices from a cached grid.
        Shopifyb2cController::forgetTabularDataCache();
        $pageRows = app(Shopifyb2cController::class)->getViewShopifyB2cTabularData();
        if (! is_array($pageRows)) {
            throw new \RuntimeException('Shopify B2C pricing data load failed');
        }

        $out = [];
        $seen = [];
        foreach ($pageRows as $r) {
            if (! is_array($r) || ! empty($r['is_parent_summary'])) {
                continue;
            }
            $sku = trim((string) ($r['(Child) sku'] ?? ''));
            $key = strtoupper($sku);
            if ($sku === '' || isset($seen[$key]) || str_contains($key, 'PARENT')) {
                continue;
            }
            if ($want !== null && ! isset($want[$key])) {
                continue;
            }
            $inv = (float) ($r['INV'] ?? 0);
            $price = (float) ($r['Price'] ?? 0);
            if (! ($inv > 0) || ! ($price > 0) || (($r['Missing'] ?? '') === 'M')) {
                continue;
            }
            $seen[$key] = true;

            $ovL30 = (float) ($r['L30'] ?? 0);
            $views = (float) ($r['Views'] ?? 0);
            $b2cL30 = (float) ($r['B2B L30'] ?? 0);

            $out[] = [
                'sku' => $sku,
                'price' => $price,
                'inv' => $inv,
                // Same as the page Dil: raw (OV L30 / INV) × 100, not rounded before slab match.
                'dil' => ($ovL30 / $inv) * 100,
                'ov_l30' => $ovL30,
                'b2c_l30' => $b2cL30,
                'views' => $views,
                'cvr' => $views > 0 ? ($b2cL30 / $views) * 100 : 0.0,
                'lp' => (float) ($r['LP_productmaster'] ?? 0),
                'ship' => (float) ($r['Ship_productmaster'] ?? 0),
                'amz' => (float) ($r['A Price'] ?? 0),
                'lmp' => (float) ($r['lmp_price'] ?? 0),
                'age_days' => $ageDays->ageDays($sku, $inv, $agePushBySku, $ageLastSaleBySku),
                'standard_price' => (float) ($r['STANDARD_PRICE'] ?? 0),
                'sprice' => (float) ($r['SPRICE'] ?? 0),
                'amz_capped' => ! empty($r['AMZ_CAPPED']),
                'rule_price' => (float) ($r['SPRICE_RULE_PRICE'] ?? 0),
            ];

            if ($limit !== null && $limit > 0 && count($out) >= $limit) {
                break;
            }
        }

        usort($out, static fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

        return $out;
    }

    /**
     * Saved S PRC already equals the target (to the cent).
     *
     * @param  array<string, mixed>  $row
     * @param  array{sprice:float}  $computed
     */
    protected function isUnchanged(array $row, array $computed): bool
    {
        if (! PushedListingPrice::same($row['sprice'] ?? 0, $computed['sprice'])
            || ! empty($row['amz_capped']) !== ! empty($computed['amz_capped'])) {
            return false;
        }
        // The rule price (before the caps) drives the page's cap triangles. Rows saved before it existed are refreshed
        // when their S PRC next changes; once saved it must follow the rule too.
        $savedRule = (float) ($row['rule_price'] ?? 0);

        return ! ($savedRule > 0) || abs($savedRule - (float) ($computed['rule_price'] ?? $savedRule)) < 0.005;
    }

    /**
     * Save S PRC the way the page does (shopifyb2c_data_view.value): SPRICE + SGPFT / SNPFT / SROI / SNROI.
     * Old rule leftovers (PRMT / CPN %) are cleared so nothing else overrides the Std rule.
     *
     * @param  array{sprice:float,uncapped:float,amz_capped:bool,sgpft:float,snpft:float,sroi:float,snroi:float}  $computed
     */
    protected function saveSprice(string $sku, array $computed): void
    {
        $key = strtoupper(trim($sku));
        $view = Shopifyb2cDataView::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$key])
            ->first()
            ?? new Shopifyb2cDataView(['sku' => $key]);

        $existing = $this->decodeValue($view->value);
        if (! $view->exists) {
            $view->sku = $key;
        }

        $existing['SPRICE'] = round((float) $computed['sprice'], 2);
        $existing['SGPFT'] = $computed['sgpft'];
        $existing['SNPFT'] = $computed['snpft'];
        $existing['SROI'] = $computed['sroi'];
        $existing['SNROI'] = $computed['snroi'];
        $existing['AMZ_SUGG_APPLIED'] = false;
        $existing['AMZ_CAPPED'] = (bool) $computed['amz_capped'];
        $existing['SPRICE_UNCAPPED'] = round((float) $computed['uncapped'], 2);
        $existing['SPRICE_RULE_PRICE'] = round((float) ($computed['rule_price'] ?? $computed['sprice']), 2);
        $existing['PEF_PRMT_PCT'] = 0;
        $existing['PEF_CPN_PCT'] = 0;
        $existing['SPRICE_STATUS'] = 'saved';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();

        $view->value = $existing;
        $view->save();
    }

    /** Shopify TCOS / Ads% — same source as the /shopify-b2c-pricing Ads badge. */
    public function channelAdsPercent(): float
    {
        try {
            $snap = Cache::remember('shopify_direct_l30_snapshot_v1', 90, function () {
                return app(ChannelMasterController::class)->getShopifyDirectL30Snapshot();
            });

            return max(0.0, (float) ($snap['tcos_pct'] ?? 0));
        } catch (Throwable $e) {
            return 0.0;
        }
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
