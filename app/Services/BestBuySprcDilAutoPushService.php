<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\BestBuyPricingController;
use App\Models\BestbuyUSADataView;
use App\Models\ChannelTabulatorColumnSetting;
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
 * Best Buy Std prc vs dil stack → SPRICE → Best Buy MCM price import (page not required).
 *
 * Same steps as AmazonSprcDilAutoPushService, with Best Buy as the source:
 *  S PRC = Std Prc × (1 − (Age Disc + Dil Disc + B Disc + 0 Sold Disc + Rev Disc) / 100)
 *  CVR Disc is off: Best Buy has no session CVR (Amazon A L30 / Sess30 is not used).
 *  Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 *  SNROI Cap (same as Amazon): a Dil slab with a Target SNROI % prices S PRC from that SNROI instead of the discounts.
 *  Clearance SKUs show "C" in S PRC: nothing is saved or pushed (same as Amazon).
 *  Std Prc is the maximum, then the LMP cap (SGROI at LMP ≥ 20%).
 *  Last, the Amz rule: S PRC never sits below the Amazon price (A Price). That floor wins over the LMP cap.
 *  Ship BB is part of profit (same as the page). Best Buy has no ads, so none is used.
 *  Never pushes a price whose SNROI is below 0 (NegativeSnroiPushGuard). The S PRC is still saved.
 *
 * Flow, same as Amazon:
 *  - Twice-daily run: skips a SKU whose last pushed price already equals the target.
 *  - Blue catch-up run (bestbuy:push-blue-prices): SKUs whose BB Price ≠ S PRC are pushed again once the
 *    cooldown has passed. BB Price itself is refreshed by the MCM OF21 sync, so a pushed price shows in the
 *    Price column after the next sync, not right away.
 *
 * Rows come from BestBuyPricingController::getViewBestBuyData(), the same rows /bestbuy-pricing shows.
 */
class BestBuySprcDilAutoPushService
{
    public const LOCK_KEY = 'bestbuy-sprc-dil-auto-push-run';

    public const CHANNEL = 'bestbuy';

    public function __construct(private readonly BestBuyApiService $bbApi) {}

    /** @var array<string, string> Why a requested SKU was left out of the candidates (--sku runs). */
    protected array $skipReasons = [];

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
        int $cooldownMinutes = 60,
        int $errorCooldownMinutes = 120,
        int $maxPushes = 0
    ): array {
        $pricer = StdPrcVsDilPricer::forChannel('bestbuy');

        // The page switch decides the S PRC rule: Std prc vs dil, or the Sprc Dil modal (slab + Diff + LMP cap by Dil band + Std max).
        $dilContext = SpriceActiveRule::usesStdPrc(self::CHANNEL) ? null : $this->loadDilContext();

        $this->log($logger, $dilContext !== null
            ? 'Best Buy S PRC rule = Sprc Dil (Dil slab Target SNROI + Diff, Ship BB in the price, LMP cap by Dil band, Std max, Amz rule last)'
            : 'Loaded Best Buy Std prc vs dil (bestbuy_std_prc_vs_dil) → Age/Dil/B/0 Sold/Rev Disc + LMP cap + Amz rule (CVR off)');

        $stats = [
            'candidates' => 0,
            'applied' => 0,
            'pushed' => 0,
            'skipped_unchanged' => 0,
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

        if ($onlySkus !== null && $onlySkus !== []) {
            $found = [];
            foreach ($rows as $cand) {
                $found[strtoupper(trim((string) ($cand['sku'] ?? '')))] = true;
            }
            foreach ($onlySkus as $want) {
                $wantKey = strtoupper(trim((string) $want));
                if (isset($found[$wantKey])) {
                    continue;
                }
                $this->log($logger, $wantKey.': not a candidate — '.($this->skipReasons[$wantKey]
                    ?? 'not found on the page rows (check the SKU spelling, or it is not a child SKU in Product Master)'));
            }
        }

        $lock = Cache::lock($skipPush && ! $dryRun ? self::LOCK_KEY.'-save-only' : self::LOCK_KEY, 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another Best Buy Sprc Dil push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0 listed on Best Buy");

        $canPush = ! $dryRun && ! $skipPush;
        if ($canPush && ! $this->apiConfigured()) {
            $stats['errors'][] = 'Best Buy MCM API key is missing in .env (BESTBUY_MCM_API_KEY)';
            $this->log($logger, '✗ Best Buy MCM API is not configured — saving S PRC only, no push');
            $canPush = false;
        }

        $total = count($rows);

        try {
            foreach ($rows as $i => $row) {
                try {
                    $computed = $this->computeTarget($row, $pricer, $dilContext);
                    if ($onlySkus !== null && $onlySkus !== []) {
                        // Single-SKU run: show the inputs and the result, so a row that looks wrong can be traced.
                        $this->log($logger, $this->explainRow($row, $computed));
                    }
                    if ($computed === null) {
                        $stats['skipped']++;
                        continue;
                    }
                    $sprice = (float) $computed['sprice'];

                    if (PushedListingPrice::same($row['price'] ?? 0, $sprice)) {
                        // Live BB Price already equals the target. Keep the saved S PRC in step, no push.
                        if (! $dryRun && (! PushedListingPrice::same($row['sprice'] ?? 0, $sprice) || ! empty($row['amz_capped']) !== ! empty($computed['amz_capped']))) {
                            $this->saveSprice($row, $computed, 'pushed');
                            $stats['applied']++;
                        }
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    // Blue run: keep the saved S PRC (and SGPFT / SNPFT) on the current rule every 10 min, even for a SKU
                    // that is still inside the push cooldown. Without this a stale S PRC stays on the page until the cooldown ends.
                    $amzFlagStale = ! empty($row['amz_capped']) !== ! empty($computed['amz_capped']);
                    if ($blueCatchUp && ! $dryRun && (! PushedListingPrice::same($row['sprice'] ?? 0, $sprice) || $amzFlagStale)) {
                        $this->saveSprice($row, $computed, 'applied');
                        $row['sprice'] = $sprice;
                        $stats['refreshed'] = ($stats['refreshed'] ?? 0) + 1;
                    }

                    if ($blueCatchUp) {
                        // Headless "Push on reload": SKUs whose BB Price ≠ S PRC. The twice-daily run skips a SKU
                        // whose last pushed price already equals the target, so a Price that stayed different
                        // afterwards (import rejected, or the offer was edited) is picked up here.
                        if ($maxPushes > 0 && $stats['pushed'] + $stats['push_failed'] >= $maxPushes) {
                            $this->log($logger, "Reached max pushes ({$maxPushes}) — rest next run");
                            break;
                        }
                        if (! empty($row['push_error_at']) && $this->withinCooldown((string) $row['push_error_at'], $errorCooldownMinutes)) {
                            // MCM rejected the last push — retry after the cooldown, not every run.
                            $stats['skipped']++;
                            continue;
                        }
                        if ($this->isUnchanged($row, $computed)
                            && $this->withinCooldown((string) ($row['pushed_at'] ?? ''), $cooldownMinutes)) {
                            // This exact price was pushed and BB Price only follows after the next MCM sync, so give it
                            // time. A changed target (a rule was saved) is pushed at once, same as Amazon.
                            $stats['skipped_unchanged']++;
                            continue;
                        }
                    } elseif (! $pushAll && $this->isUnchanged($row, $computed)) {
                        // Last push already equals the target. The blue catch-up handles a Price that stayed different.
                        // Keep the saved Amz flag in step: a price that now only equals A Price is no longer "raised".
                        if (! $dryRun && ! empty($row['amz_capped']) !== ! empty($computed['amz_capped'])) {
                            $this->saveSprice($row, $computed, 'pushed');
                            $stats['applied']++;
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

                    if (NegativeSnroiPushGuard::shouldSkip('bestbuy', (string) $row['sku'], $sprice)) {
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
                    Log::error('[BestBuySprcDilAutoPush] exception', [
                        'sku' => $row['sku'] ?? '',
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($logger && (($i + 1) % 100 === 0 || ($i + 1) === $total)) {
                    $this->log($logger, 'Progress '.($i + 1)."/{$total} (pushed {$stats['pushed']}, unchanged {$stats['skipped_unchanged']})");
                }
            }

            $this->log($logger, sprintf(
                'Done: applied=%d pushed=%d unchanged=%d skipped=%d failed=%d%s',
                $stats['applied'],
                $stats['pushed'],
                $stats['skipped_unchanged'],
                $stats['skipped'],
                $stats['push_failed'],
                ($dryRun || $skipPush) ? ' [no Best Buy push]' : ''
            ));

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * One line with the numbers behind a row's S PRC (used by --sku runs).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $computed
     */
    protected function explainRow(array $row, ?array $computed): string
    {
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        $lp = (float) ($row['lp'] ?? 0);
        $ship = (float) ($row['ship'] ?? 0);
        $line = sprintf(
            '%s: live=%.2f saved=%.2f std=%.2f amz=%.2f lmp=%.2f lp=%.2f ship=%.2f margin=%.2f inv=%s',
            (string) ($row['sku'] ?? ''),
            (float) ($row['price'] ?? 0),
            (float) ($row['sprice'] ?? 0),
            (float) ($row['standard_price'] ?? 0),
            (float) ($row['amz'] ?? 0),
            (float) ($row['lmp'] ?? 0),
            $lp,
            $ship,
            $margin,
            (string) ($row['inv'] ?? 0)
        );
        if ($computed === null) {
            $why = ! ((float) ($row['inv'] ?? 0) > 0) ? 'INV is 0'
                : (! ((float) ($row['standard_price'] ?? 0) > 0) ? 'Std Prc is blank (Amazon STANDARD_PRICE for this SKU or its Sku Link siblings)' : 'rule gave no price');

            return $line.' → no target: '.$why;
        }
        $sp = (float) $computed['sprice'];
        $snpft = $sp > 0 ? (($sp * ($margin > 0 ? $margin : 0.80) - $ship - $lp) / $sp) * 100 : 0.0;

        return $line.sprintf(
            ' → target=%.2f snpft=%.1f%% amz_capped=%s',
            $sp,
            $snpft,
            ! empty($computed['amz_capped']) ? 'yes' : 'no'
        );
    }

    /**
     * Page S PRC formula (INV > 0 child SKUs): Std rule, Std is the maximum, LMP cap,
     * then the Amz rule (never below A Price). Ship BB included, no ads.
     *
     * With a $dilContext (the Sprc Dil switch is ON) the Sprc Dil modal rules price it instead: Dil slab Target SNROI
     * + Diff points, LMP cap = LMP x (1 - x%) by Dil band, Std Prc as the maximum, and the Amz rule last.
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:list<array<string,mixed>>,diff_adj:?array,lmp_cap:?array}|null  $dilContext
     * @return array{sprice:float,uncapped:float,amz_capped:bool,lmp_capped:bool,base:float,margin:float,lp:float,ship:float}|null
     */
    public function computeTarget(array $row, ?StdPrcVsDilPricer $pricer = null, ?array $dilContext = null): ?array
    {
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0)) {
            return null;
        }
        // Clearance SKUs show "C" in S PRC. No price is saved or pushed for them (same as Amazon).
        if ((new StdPrcVsDilPricer([], 'bestbuy'))->isClearanceRow($row)) {
            return null;
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
            $margin = 0.80;
        }
        $lmp = (float) ($row['lmp'] ?? 0);

        $pricer ??= StdPrcVsDilPricer::forChannel('bestbuy');
        // Age / Dil / B / 0 Sold / Rev discounts, or the SNROI Cap price when the Dil slab has a Target SNROI %. CVR is off.
        // The cap judges the page's SNROI%: (S PRC × take-home − Ship BB − LP) ÷ LP, no Ads%.
        $pricerRow = [
            'sku' => (string) ($row['sku'] ?? ''),
            'inv' => $inv,
            'dil' => (float) ($row['dil'] ?? 0),
            'std_price' => $std,
            'age_days' => $row['age_days'] ?? null,
            'cvr' => 0.0,
            'reviews' => 0.0,
            // 0 Sold = BB L30 (MCM orders), same as the page.
            'bb_l30' => (float) ($row['bb_l30'] ?? 0),
            // Live BB Price: ROI Disc is judged on GROI% at the current listing price (same as the page's GROI% column).
            'price' => (float) ($row['price'] ?? 0),
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

        // LMP cap (SGROI at LMP ≥ 20%), Std Prc is the maximum.
        $capped = AmazonDilGroiRule::capSpriceToLmp($effective, $lmp, $lp, $ship, $margin, max($std, $effective));
        $lmpCapped = $lmp > 0
            && ($effective - $capped) > 0.009
            && abs($capped - round($lmp, 2)) <= 0.02;
        $uncapped = AmazonDilGroiRule::capToStdWhenNoLmp(round($effective, 2), 0.0, max($std, $effective));

        // Amz rule: S PRC is never below the Amazon price (A Price). It wins over the LMP cap.
        $amz = round((float) ($row['amz'] ?? 0), 2);
        $amzCapped = $amz > 0 && $capped + 0.0001 < $amz;
        $final = $amzCapped ? $amz : $capped;
        if ($amzCapped) {
            $lmpCapped = false;
        }

        if (! is_finite($final) || $final < 0.01) {
            return null;
        }

        return [
            'sprice' => round($final, 2),
            'uncapped' => round($uncapped, 2),
            'amz_capped' => $amzCapped,
            'lmp_capped' => $lmpCapped,
            'base' => round($std, 2),
            'margin' => $margin,
            'lp' => $lp,
            'ship' => $ship,
        ];
    }

    /**
     * Sprc Dil switch ON: the Sprc Dil modal rules, same chain as the page.
     *  1. Dil slab Target SNROI (no slab for the Dil % = no price). No CVR overlay: Best Buy has no session CVR.
     *  2. + Diff points: (Std Prc - Price) / Price above the Diff threshold.
     *  3. price = (LP x (1 + target%) + Ship BB) / take-home, no Ads.
     *  4. LMP cap (SGROI at LMP >= 20%): LMP x (1 - x%) by the SKU's Dil band, then Std Prc as the maximum.
     *  5. Amz rule last: never below A Price (wins over the LMP cap).
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:list<array<string,mixed>>,diff_adj:?array,lmp_cap:?array}  $ctx
     * @return array{sprice:float,uncapped:float,amz_capped:bool,lmp_capped:bool,base:float,margin:float,lp:float,ship:float}|null
     */
    protected function computeDilTarget(array $row, array $ctx): ?array
    {
        $lp = (float) ($row['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 0.80;
        }
        $ship = (float) ($row['ship'] ?? 0);
        $std = round((float) ($row['standard_price'] ?? 0), 2);
        $lmp = (float) ($row['lmp'] ?? 0);
        $dil = (float) ($row['dil'] ?? 0);

        $rule = AmazonDilGroiRule::match($dil, $ctx['rules']);
        if ($rule === null) {
            return null;
        }
        $target = max(0.0, round((float) $rule['groi'], 2));
        $diffPct = EbayRuleSprice::stdPriceDiffPct($std, (float) ($row['price'] ?? 0));
        $target = max(0.0, round($target + AmazonDilGroiRule::diffAdjPoints($diffPct, $ctx['diff_adj'] ?? null), 2));

        $raw = round(($lp * (1 + $target / 100) + $ship) / $margin, 2);
        if (! is_finite($raw) || $raw < 0.01) {
            return null;
        }
        $reducePct = AmazonDilGroiRule::lmpCapReducePct($dil, $ctx['lmp_cap'] ?? null);
        $capped = round(AmazonDilGroiRule::capSpriceToLmp($raw, $lmp, $lp, $ship, $margin, $std, $reducePct), 2);
        $lmpCapped = $lmp > 0 && ($raw - $capped) > 0.009 && abs($capped - round($lmp * (1 - $reducePct / 100), 2)) <= 0.02;

        $amz = round((float) ($row['amz'] ?? 0), 2);
        $amzCapped = $amz > 0 && $capped + 0.0001 < $amz;
        $final = $amzCapped ? $amz : $capped;
        if ($amzCapped) {
            $lmpCapped = false;
        }
        if (! is_finite($final) || $final < 0.01) {
            return null;
        }

        return [
            'sprice' => round($final, 2),
            'uncapped' => $std > 0 ? round(min($raw, $std), 2) : $raw,
            'amz_capped' => $amzCapped,
            'lmp_capped' => $lmpCapped,
            'base' => $std,
            'margin' => $margin,
            'lp' => $lp,
            'ship' => $ship,
        ];
    }

    /**
     * The Sprc Dil modal settings saved for Best Buy: Dil slabs, Diff rule and LMP cap (reduce % by Dil band).
     *
     * @return array{rules:list<array<string,mixed>>,diff_adj:?array,lmp_cap:?array}
     */
    protected function loadDilContext(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::CHANNEL.'_dil_vs_groi')->first();
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($row?->visibility) ? $row->visibility : null);
        $rules = $unpacked['rules'] === []
            ? AmazonDilGroiRule::defaultsForChannel(self::CHANNEL)
            : AmazonDilGroiRule::ensureZeroToZero($unpacked['rules']);

        return [
            'rules' => $rules,
            'diff_adj' => $unpacked['diff_adj'] ?? null,
            'lmp_cap' => $unpacked['lmp_cap'] ?? null,
        ];
    }

    /**
     * Same rows as /bestbuy-pricing. Listed on Best Buy MCM, INV > 0, child SKUs only.
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
            Log::warning('[BestBuySprcDilAutoPush] age days lookup failed', ['error' => $e->getMessage()]);
        }

        $res = app(BestBuyPricingController::class)->getViewBestBuyData(new Request());
        $payload = method_exists($res, 'getData') ? $res->getData(true) : [];
        $pageRows = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($pageRows)) {
            throw new \RuntimeException('Best Buy pricing data load failed');
        }

        $out = [];
        $seen = [];
        foreach ($pageRows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $sku = trim((string) ($r['(Child) sku'] ?? $r['sku'] ?? ''));
            $key = strtoupper($sku);
            if ($sku === '' || isset($seen[$key]) || str_contains($key, 'PARENT')) {
                continue;
            }
            if ($want !== null && ! isset($want[$key])) {
                continue;
            }
            $inv = (float) ($r['INV'] ?? 0);
            // Live MCM price. "BB Price" can show the last pushed value, which hides a push that never landed.
            $price = (float) ($r['BB Price MCM'] ?? $r['BB Price'] ?? 0);
            if (! ($inv > 0) || ! ($price > 0) || ! empty($r['is_bb_inactive']) || ! empty($r['is_missing_bb'])) {
                if ($want !== null) {
                    // Single-SKU run: remember why a requested SKU is not a candidate.
                    $why = [];
                    if (! ($inv > 0)) {
                        $why[] = 'INV is 0';
                    }
                    if (! ($price > 0)) {
                        $why[] = 'no live price on the marketplace';
                    }
                    if (! empty($r['is_bb_inactive'])) {
                        $why[] = 'listing is inactive';
                    }
                    if (! empty($r['is_missing_bb'])) {
                        $why[] = 'listing is missing on the marketplace';
                    }
                    $this->skipReasons[$key] = implode(', ', $why);
                }
                continue;
            }
            $seen[$key] = true;

            $status = strtolower((string) ($r['SPRICE_STATUS'] ?? $r['push_status'] ?? ''));
            $statusAt = $r['SPRICE_STATUS_UPDATED_AT'] ?? null;

            $out[] = [
                'sku' => $sku,
                'price' => $price,
                'inv' => $inv,
                // Same as the page Dil: raw (OV L30 / INV) × 100, not rounded before slab match.
                'dil' => ((float) ($r['L30'] ?? 0) / $inv) * 100,
                'bb_l30' => (float) ($r['BB L30'] ?? 0),
                'lp' => (float) ($r['LP_productmaster'] ?? 0),
                'ship' => (float) ($r['Ship_productmaster'] ?? 0),
                'margin' => (float) ($r['percentage'] ?? 0),
                'amz' => (float) ($r['A Price'] ?? 0),
                'lmp' => (float) ($r['lmp_price'] ?? 0),
                'age_days' => $ageDays->ageDays($sku, $inv, $agePushBySku, $ageLastSaleBySku),
                'standard_price' => (float) ($r['STANDARD_PRICE'] ?? 0),
                'sprice' => (float) ($r['SPRICE'] ?? 0),
                'amz_capped' => ! empty($r['AMZ_CAPPED']),
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
     * Save S PRC the way the page does (bestbuy_usa_data_views.value): SPRICE + SGPFT / SROI + status.
     * AMZ_CAPPED / SPRICE_UNCAPPED let the page show the "Amz" label without recalculating.
     *
     * @param  array<string, mixed>  $row
     * @param  array{sprice:float,uncapped:float,amz_capped:bool,margin:float,lp:float,ship:float}  $computed
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
        $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0.0;
        $sroi = $lp > 0 ? round((($sprice * $margin - $lp - $ship) / $lp) * 100, 2) : 0.0;
        $now = now()->toDateTimeString();

        unset($existing['SPRICE_CLEARED']);
        $existing['SPRICE'] = $sprice;
        $existing['sprice'] = $sprice;
        $existing['SGPFT'] = $sgpft;
        $existing['SPFT'] = $sgpft;
        $existing['SROI'] = $sroi;
        $existing['SNROI'] = $sroi;
        $existing['SGROI'] = $sroi;
        $existing['has_custom_sprice'] = true;
        $existing['AMZ_CAPPED'] = (bool) $computed['amz_capped'];
        $existing['SPRICE_UNCAPPED'] = round((float) $computed['uncapped'], 2);
        $existing['sprice_updated_at'] = $now;
        $existing['SPRICE_STATUS'] = $status;
        $existing['SPRICE_STATUS_UPDATED_AT'] = $now;
        if ($status === 'pushed') {
            $existing['SPRICE_PUSHED_VALUE'] = $sprice;
        }

        $view->value = $existing;
        $view->save();
    }

    /**
     * Push one SKU through BestBuyApiService::updatePrice (MCM price import), then stamp the result.
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

        if (($result['success'] ?? false) === true) {
            $stats['pushed']++;
            $this->savePushMeta($sku, 'pushed', $price);

            return;
        }

        $stats['push_failed']++;
        $err = (string) ($result['message'] ?? 'Best Buy push failed');
        $stats['errors'][] = $sku.': '.$err;
        $this->log($logger, 'Push failed '.$sku.': '.$err);
        Log::error('Best Buy Sprc Dil push failed', ['sku' => $sku, 'price' => $price, 'error' => $err]);
        $this->savePushMeta($sku, 'error', null);
    }

    protected function apiConfigured(): bool
    {
        try {
            return $this->bbApi->isConfigured();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array{success?:bool,message?:string} */
    protected function pushPrice(string $sku, float $price): array
    {
        try {
            return $this->bbApi->updatePrice($sku, $price);
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
            $view->value = $existing;
            $view->save();
        } catch (Throwable $e) {
            Log::error('[BestBuySprcDilAutoPush] status save failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** The page reads the view by the exact Product Master SKU, so a new row keeps that spelling. */
    protected function findView(string $sku): BestbuyUSADataView
    {
        $key = strtoupper(trim($sku));

        return BestbuyUSADataView::whereRaw('UPPER(TRIM(sku)) = ?', [$key])->first()
            ?? new BestbuyUSADataView(['sku' => trim($sku)]);
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
