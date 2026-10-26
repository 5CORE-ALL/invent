<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\CvrMasterController;
use App\Http\Controllers\MarketPlace\TikTokPricingController;
use App\Models\ChannelMasterCalculatedData;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\TiktokShopDataView;
use App\Models\TiktokTwoShopDataView;
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
 * TikTok / TikTok 2 Std prc vs dil stack → SPRICE → TikTok Shop price API (page not required).
 *
 * Same steps as AmazonSprcDilAutoPushService, with TikTok as the source (/tiktok-pricing and /tiktok-2-pricing
 * share one page, so one service runs both channels):
 *  S PRC = Std Prc × (1 − (Age Disc + Dil Disc + B Disc + 0 Sold Disc + CVR Disc + Rev Disc) / 100)
 *  Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 *  Clearance SKUs are priced only from the Clearance Target NROI% (Sprc Dil modal, last row), no cap.
 *  0 Sold = TT L30 (tiktok_orders), the page's sold qty.
 *  Caps, same order as Amazon:
 *   1. LMP cap: S PRC at or above LMP becomes LMP when SGROI at LMP is ≥ 20%.
 *   2. Std Prc is the maximum.
 *  Never pushes a price whose SNROI is below 0 (NegativeSnroiPushGuard). The S PRC is still saved.
 *
 * Flow, same as Amazon:
 *  - Twice-daily run: skips a SKU whose last pushed price already equals the target.
 *  - Live pull: after every push the live TikTok price is read back into the Price column, and the
 *    blue catch-up pulls it first, so a SKU where TikTok is already right is repaired instead of re-pushed.
 *  - Blue catch-up run (tiktok:push-blue-prices): SKUs whose TT Price ≠ S PRC are pushed again once the
 *    cooldown has passed.
 *
 * Rows come from TikTokPricingController::getViewTikTokTabularData(), the same rows the page shows.
 */
class TiktokSprcDilAutoPushService
{
    public const CHANNELS = ['tiktok', 'tiktok2'];

    private string $channel;

    /** @var array<string, TiktokShopDataView|TiktokTwoShopDataView>|null */
    private ?array $viewsByNorm = null;

    public function __construct(string $channel = 'tiktok')
    {
        $this->channel = self::normalizeChannel($channel);
    }

    public static function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return in_array($channel, ['tiktok2', 'tiktok_2', 'tiktok-2'], true) ? 'tiktok2' : 'tiktok';
    }

    public static function forChannel(string $channel): self
    {
        return new self($channel);
    }

    public function channel(): string
    {
        return $this->channel;
    }

    public function lockKey(): string
    {
        return $this->channel.'-sprc-dil-auto-push-run';
    }

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
        $pricer = StdPrcVsDilPricer::forChannel($this->channel);
        $adsFrac = $this->channelAdsFrac();
        // The page switch decides the S PRC rule: Std prc vs dil, or the Sprc Dil modal (slab + CVR overlay + Diff + LMP cap by Dil band + Std max).
        $dilContext = SpriceActiveRule::usesStdPrc($this->channel) ? null : $this->loadDilContext();

        $this->log($logger, $dilContext !== null
            ? $this->label().' S PRC rule = Sprc Dil (Dil slab Target SNROI + CVR overlay + Diff, LMP cap by Dil band, Std max)'
            : 'Loaded '.$this->label().' Std prc vs dil ('.$this->channel.'_std_prc_vs_dil) → Age/Dil/B/0 Sold/CVR/Rev Disc + LMP cap + Std max');

        $stats = [
            'channel' => $this->channel,
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

        $lock = Cache::lock($skipPush && ! $dryRun ? $this->lockKey().'-save-only' : $this->lockKey(), 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another '.$this->label().' Sprc Dil push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0 listed on ".$this->label());

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
                        // Live TikTok Price already equals the target. Keep the saved S PRC in step, no push.
                        if (! $dryRun && ! PushedListingPrice::same($row['sprice'] ?? 0, $sprice)) {
                            $this->saveSprice($row, $computed, 'pushed');
                            $stats['applied']++;
                        }
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    if ($blueCatchUp) {
                        // Headless "Push on reload": the blue-triangle SKUs (INV > 0, Price ≠ S PRC), same as Amazon.
                        // The twice-daily run skips a SKU whose last pushed price already equals the target, so a Price
                        // that drifted afterwards (TikTok reverted it) stayed blue until someone opened the page.
                        if ($maxPushes > 0 && $stats['pushed'] + $stats['push_failed'] >= $maxPushes) {
                            $this->log($logger, "Reached max pushes ({$maxPushes}) — rest next run");
                            break;
                        }
                        if (! empty($row['push_error_at']) && $this->withinCooldown((string) $row['push_error_at'], $cooldownMinutes)) {
                            // TikTok rejected the last push — retry after the cooldown, not every run.
                            $stats['skipped']++;
                            continue;
                        }
                        if ($this->isUnchanged($row, $computed)) {
                            if ($this->withinCooldown((string) ($row['pushed_at'] ?? ''), $cooldownMinutes)) {
                                $stats['skipped_unchanged']++;
                                continue;
                            }
                            // Pull the live Price first. TikTok may already be right and only the Price column stale.
                            if (! $dryRun && $canPush) {
                                $pull = $this->pullLive((string) $row['sku'], $sprice);
                                if ($sleepMs > 0) {
                                    usleep($sleepMs * 1000);
                                }
                                if ($pull && ! empty($pull['success']) && PushedListingPrice::same($pull['price'] ?? 0, $sprice)) {
                                    $stats['price_repaired']++;
                                    $stats['skipped_unchanged']++;
                                    continue;
                                }
                            }
                        }
                    } elseif (! $pushAll && $this->isUnchanged($row, $computed)) {
                        // Last push already equals the target. Refresh the live TikTok Price column, no push.
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

                    if (NegativeSnroiPushGuard::shouldSkip($this->channel, (string) $row['sku'], $sprice)) {
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
                    Log::error('[TiktokSprcDilAutoPush] exception', [
                        'channel' => $this->channel,
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
                ($dryRun || $skipPush) ? ' [no '.$this->label().' push]' : ''
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
        // Std prc vs dil and Sprc Dil alike: (LP × (1 + NROI%) + Ship) ÷ (take-home − Ads%). No slab, Diff, LMP or Std rule.
        if ((new StdPrcVsDilPricer([], $this->channel))->isClearanceRow($row)) {
            return $this->computeClearanceTarget($row, $adsFrac);
        }
        if ($dilContext !== null) {
            return $this->computeDilTarget($row, $dilContext, $adsFrac);
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
        // The SNROI Cap must judge the Ads% the page's SNROI column uses: the listing's own TACOS% on TikTok,
        // and none on TikTok 2. Without a TACOS% on the row, fall back to the channel Ads%.
        if ($this->channel === 'tiktok2') {
            $ads = 0.0;
        } elseif (is_numeric($row['tacos'] ?? null)) {
            $tacos = (float) $row['tacos'];
            $ads = ($tacos > 0 && $tacos < 100) ? $tacos / 100 : 0.0;
        } else {
            $ads = $adsFrac ?? $this->channelAdsFrac();
        }

        $pricer ??= StdPrcVsDilPricer::forChannel($this->channel);
        // Age / Dil / B / 0 Sold / CVR / Rev discounts, then clearance SKUs keep the discount price.
        $pricerRow = [
            'sku' => (string) ($row['sku'] ?? ''),
            'inv' => $inv,
            'dil' => (float) ($row['dil'] ?? 0),
            'std_price' => $std,
            'age_days' => $row['age_days'] ?? null,
            'cvr' => 0.0,
            'reviews' => 0.0,
            // 0 Sold = TT L30 (tiktok_orders L30), same as the page.
            'tt_l30' => (float) ($row['tt_l30'] ?? 0),
            'lp' => $lp,
            'ship' => $ship,
            'margin' => $margin,
            'ads' => $ads,
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
     * Clearance SKU: (LP × (1 + Clearance NROI%) + Ship) ÷ (take-home − Ads%), Ads% = the listing TACOS% (TikTok 2 = 0).
     * No LMP cap and no Std Prc maximum.
     *
     * @param  array<string, mixed>  $row
     * @return array{sprice:float,uncapped:float,lmp_capped:bool,base:float,margin:float,lp:float,ship:float,lmp:float}|null
     */
    protected function computeClearanceTarget(array $row, ?float $adsFrac = null): ?array
    {
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 0.80;
        }
        $lp = (float) ($row['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }
        $ship = (float) ($row['ship'] ?? 0);
        if ($this->channel === 'tiktok2') {
            $ads = 0.0;
        } elseif (is_numeric($row['tacos'] ?? null)) {
            $tacos = (float) $row['tacos'];
            $ads = ($tacos > 0 && $tacos < 100) ? $tacos / 100 : 0.0;
        } else {
            $ads = $adsFrac ?? $this->channelAdsFrac();
        }
        $denom = $margin - $ads;
        if (! ($denom > 0)) {
            return null;
        }
        // A row may carry its own clearance_nroi (tests); otherwise the saved Sprc Dil modal value.
        $nroi = isset($row['clearance_nroi']) && is_numeric($row['clearance_nroi']) ? (float) $row['clearance_nroi'] : $this->clearanceNroi();
        $price = round(($lp * (1 + max(0.0, $nroi) / 100) + $ship) / $denom, 2);
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

    /** Clearance Target NROI% saved with this channel's Sprc Dil rules (last row of the modal). Default 0. */
    protected function clearanceNroi(): float
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', $this->channel.'_dil_vs_groi')->first();
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($row?->visibility) ? $row->visibility : null);
        $n = $unpacked['clearance_nroi'] ?? null;

        return (is_numeric($n) && (float) $n >= 0) ? (float) $n : 0.0;
    }

    /**
     * Sprc Dil switch ON, same as the page (ebay-sprc-dil + /amazon-tabulator-view order):
     * Dil slab Target SNROI, CVR overlay (no L60 CVR on the rows), Diff points, then
     * price = (LP x (1 + Target%) + Ship) / (take-home - Ads%), Ads% = the listing TACOS% (TikTok 2 = 0),
     * LMP cap (price at/above LMP and SGROI at LMP >= 20% -> LMP x (1 - x%), x by the SKU's Dil band), Std Prc as the maximum.
     * A SKU whose Dil is outside every slab has no Sprc Dil price. Clearance SKUs never reach this (computeClearanceTarget).
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array}  $ctx
     * @return array{sprice:float,uncapped:float,lmp_capped:bool,base:float,margin:float,lp:float,ship:float,lmp:float}|null
     */
    protected function computeDilTarget(array $row, array $ctx, ?float $adsFrac = null): ?array
    {
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 0.80;
        }
        $lp = (float) ($row['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }
        $ship = (float) ($row['ship'] ?? 0);
        $std = round((float) ($row['standard_price'] ?? 0), 2);
        $lmp = (float) ($row['lmp'] ?? 0);
        $dil = (float) ($row['dil'] ?? 0);
        if ($this->channel === 'tiktok2') {
            $ads = 0.0;
        } elseif (is_numeric($row['tacos'] ?? null)) {
            $tacos = (float) $row['tacos'];
            $ads = ($tacos > 0 && $tacos < 100) ? $tacos / 100 : 0.0;
        } else {
            $ads = $adsFrac ?? $this->channelAdsFrac();
        }

        $rule = AmazonDilGroiRule::match($dil, $ctx['rules']);
        if ($rule === null) {
            return null;
        }
        // Same order as the page: CVR overlay (no L60 CVR on TikTok), floor at 0, then the Diff points.
        $target = AmazonDilGroiRule::adjustGroiForCvrArrow((float) $rule['groi'], (float) ($row['cvr'] ?? 0), 0.0, $ctx['cvr_adj'] ?? null);
        $target = max(0.0, round($target, 2));
        // Ageing Rule: the SKU's age band adds its points to the Target NROI, after the CVR overlay and before the Diff rule.
        if (is_array($ctx['age_adj'] ?? null) && $ctx['age_adj'] !== []) {
            $target = AmazonDilGroiRule::adjustGroiForAge(
                $target,
                is_numeric($row['age_days'] ?? null) ? (float) $row['age_days'] : null,
                $ctx['age_adj']
            );
        }
        $diffPct = EbayRuleSprice::stdPriceDiffPct($std, (float) ($row['price'] ?? 0));
        $target = max(0.0, round($target + AmazonDilGroiRule::diffAdjPoints($diffPct, $ctx['diff_adj'] ?? null), 2));

        $denom = $margin - $ads;
        if (! ($denom > 0)) {
            return null;
        }
        $raw = round(($lp * (1 + $target / 100) + $ship) / $denom, 2);
        if (! is_finite($raw) || $raw < 0.01) {
            return null;
        }

        $reducePct = AmazonDilGroiRule::lmpCapReducePct($dil, $ctx['lmp_cap'] ?? null);
        $capped = round(AmazonDilGroiRule::capSpriceToLmp($raw, $lmp, $lp, $ship, $margin, $std, $reducePct), 2);
        if (! is_finite($capped) || $capped < 0.01) {
            return null;
        }
        $lmpCapped = $lmp > 0 && ($raw - $capped) > 0.009 && abs($capped - round($lmp * (1 - $reducePct / 100), 2)) <= 0.02;

        return [
            'sprice' => $capped,
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
     * The Sprc Dil modal settings saved for this channel: slabs, CVR overlay, Diff rule and LMP cap.
     *
     * @return array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array}
     */
    protected function loadDilContext(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', $this->channel.'_dil_vs_groi')->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        $unpacked = AmazonDilGroiRule::unpackStored($saved);
        $rules = $unpacked['rules'] === []
            ? AmazonDilGroiRule::defaultsForChannel($this->channel)
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
     * Channel Ads% as a fraction. Same source as NegativeSnroiPushGuard so the
     * negative-SNROI check agree. TikTok 2 has no Channel Ads% (0).
     */
    protected function channelAdsFrac(): float
    {
        try {
            $pct = (float) (ChannelMasterCalculatedData::query()
                ->where('channel', 'like', $this->channel.'%')
                ->value('ads_percentage') ?? 0);
        } catch (Throwable $e) {
            return 0.0;
        }

        return $pct > 0 ? min(0.99, $pct / 100) : 0.0;
    }

    /**
     * Same rows as /tiktok-pricing (or /tiktok-2-pricing). Listed on TikTok, INV > 0, child SKUs only.
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
            Log::warning('[TiktokSprcDilAutoPush] age days lookup failed', ['error' => $e->getMessage()]);
        }

        $res = app(TikTokPricingController::class)->getViewTikTokTabularData(
            new Request(),
            $this->channel === 'tiktok2' ? 'v2' : 'v1'
        );
        $payload = method_exists($res, 'getData') ? $res->getData(true) : [];
        $pageRows = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($pageRows)) {
            throw new \RuntimeException($this->label().' pricing data load failed');
        }

        $out = [];
        $seen = [];
        foreach ($pageRows as $r) {
            if (! is_array($r) || ! empty($r['is_parent'])) {
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
            $price = (float) ($r['TT Price'] ?? 0);
            if (! ($inv > 0) || ! ($price > 0) || (string) ($r['Missing'] ?? '') === 'M') {
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
                'dil' => ((float) ($r['L30'] ?? 0) / $inv) * 100,
                'tt_l30' => (float) ($r['TT L30'] ?? 0),
                'cvr' => (float) ($r['cvr'] ?? $r['CVR%'] ?? 0),
                'lp' => (float) ($r['LP_productmaster'] ?? 0),
                'ship' => (float) ($r['Ship_productmaster'] ?? 0),
                'margin' => (float) ($r['percentage'] ?? 0),
                'tacos' => is_numeric($r['TACOS%'] ?? null) ? (float) $r['TACOS%'] : null,
                'age_days' => $ageDays->ageDays($sku, $inv, $agePushBySku, $ageLastSaleBySku),
                'standard_price' => (float) ($r['STANDARD_PRICE'] ?? 0),
                'lmp' => is_numeric($r['lmp_price'] ?? null) ? (float) $r['lmp_price'] : 0.0,
                'sprice' => (float) ($r['SPRICE'] ?? 0),
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
     * Save S PRC the way the page does (tiktok_shop_data_views.value): SPRICE + SGPFT / SPFT / SROI + status.
     * SGPFT / SROI include Ship, same as TikTokPricingController::saveSpriceUpdatesToModel.
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
        $existing['SPFT'] = $sgpft.'%';
        $existing['SROI'] = $sroi.'%';
        $existing['SPRICE_STATUS'] = $status;
        $existing['SPRICE_STATUS_UPDATED_AT'] = $now;
        if ($status === 'pushed') {
            $existing['SPRICE_PUSHED_VALUE'] = $sprice;
            $existing['SPRICE_PUSHED_AT'] = $now;
        }

        $this->persistView($view, $existing);
    }

    /**
     * Push one SKU through CvrMasterController::pushPriceToAmazon (marketplace tiktok / tiktok2). That is the same call
     * the old page push and the old runner used: it writes the price to tiktok_products and pulls it back.
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
            // The push call already read the price back. Pull once more when TikTok showed something else, so the
            // Price column is only overwritten with a price TikTok really shows.
            $live = $result['price'] ?? null;
            if (! is_numeric($live) || abs((float) $live - $price) >= 0.05) {
                $pull = $this->pullLive($sku, $price);
                if ($pull && empty($pull['success']) && isset($pull['price']) && $pull['price'] > 0) {
                    $this->log($logger, 'Pulled '.$sku.': '.$this->label().' shows $'.number_format((float) $pull['price'], 2).', pushed $'.number_format($price, 2));
                }
                // Still not showing the pushed price: tiktok:pull-pushed-prices re-reads it every 2 minutes (same as Amazon).
                if (! ($pull && ! empty($pull['success']))) {
                    app(TiktokPushedPricePullService::class)->scheduleAfterPush($this->channel, $sku);
                }
            }

            return;
        }

        $stats['push_failed']++;
        $err = (string) ($result['message'] ?? $this->label().' price push failed');
        $stats['errors'][] = $sku.': '.$err;
        $this->log($logger, 'Push failed '.$sku.': '.$err);
        Log::error('TikTok Sprc Dil push failed', ['channel' => $this->channel, 'sku' => $sku, 'price' => $price, 'error' => $err]);
        $this->savePushMeta($sku, 'error', null);
    }

    /**
     * Live TikTok price. With an expected price the Price column only moves when TikTok shows it;
     * without one the live price is written as is.
     *
     * @return array{success?:bool,price?:?float,message?:string}|null
     */
    protected function pullLive(string $sku, ?float $expected = null): ?array
    {
        try {
            return app(ChannelPushedPricePullService::class)
                ->pullSkus($this->channel, [$sku], $expected !== null ? [strtoupper(trim($sku)) => $expected] : [])[0] ?? null;
        } catch (Throwable $e) {
            Log::warning('[TiktokSprcDilAutoPush] live price pull failed', ['channel' => $this->channel, 'sku' => $sku, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array{success:bool,message?:string,price?:?float} */
    protected function pushPrice(string $sku, float $price): array
    {
        try {
            $res = app(CvrMasterController::class)->pushPriceToAmazon(Request::create('/cvr-master-push-price', 'POST', [
                'sku' => $sku,
                'price' => $price,
                'marketplace' => $this->channel,
            ]));
            $payload = method_exists($res, 'getData') ? $res->getData(true) : [];
            $status = method_exists($res, 'getStatusCode') ? $res->getStatusCode() : 200;
            $payload = is_array($payload) ? $payload : [];
            $ok = $status < 400
                && ! (isset($payload['success']) && $payload['success'] === false)
                && empty($payload['errors']);
            if ($ok) {
                return [
                    'success' => true,
                    'price' => isset($payload['ebay_price']) && is_numeric($payload['ebay_price'])
                        ? (float) $payload['ebay_price']
                        : (isset($payload['price']) && is_numeric($payload['price']) ? (float) $payload['price'] : null),
                ];
            }

            return [
                'success' => false,
                'message' => (string) (($payload['errors'][0]['message'] ?? null) ?: ($payload['message'] ?? $this->label().' price push failed')),
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
            Log::error('[TiktokSprcDilAutoPush] status save failed', [
                'channel' => $this->channel,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return class-string<TiktokShopDataView|TiktokTwoShopDataView> */
    protected function viewClass(): string
    {
        return $this->channel === 'tiktok2' ? TiktokTwoShopDataView::class : TiktokShopDataView::class;
    }

    /**
     * The page joins the shop data view by the normalized SKU (NBSP folded, upper), so match the same way.
     * A new row keeps the trimmed Product Master spelling.
     */
    protected function findView(string $sku): TiktokShopDataView|TiktokTwoShopDataView
    {
        $class = $this->viewClass();
        if ($this->viewsByNorm === null) {
            $this->viewsByNorm = [];
            foreach ($class::query()->get() as $v) {
                $this->viewsByNorm[$this->normalizeSku((string) $v->sku)] = $v;
            }
        }
        $key = $this->normalizeSku($sku);

        return $this->viewsByNorm[$key] ?? new $class(['sku' => trim($sku)]);
    }

    /** @param  array<string, mixed>  $value */
    protected function persistView(TiktokShopDataView|TiktokTwoShopDataView $view, array $value): void
    {
        $view->value = $value;
        $view->save();
        $this->viewsByNorm[$this->normalizeSku((string) $view->sku)] = $view;
    }

    /** Same folding as TikTokPricingController::normalizeTiktokSkuKey. */
    protected function normalizeSku(string $sku): string
    {
        return strtoupper(str_replace("\u{00a0}", ' ', trim($sku)));
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

    protected function label(): string
    {
        return $this->channel === 'tiktok2' ? 'TikTok 2' : 'TikTok';
    }

    /** @param  callable(string): void|null  $logger */
    protected function log(?callable $logger, string $msg): void
    {
        if ($logger) {
            $logger($msg);
        }
    }
}
