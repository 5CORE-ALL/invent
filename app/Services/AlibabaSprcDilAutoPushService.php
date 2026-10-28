<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\AlibabaAnalyticsController;
use App\Models\ChannelMasterCalculatedData;
use App\Models\AlibabaPricingPrice;
use App\Models\ChannelTabulatorColumnSetting;
use App\Support\AmazonDilGroiRule;
use App\Support\NegativeSnroiPushGuard;
use App\Support\PushedListingPrice;
use App\Support\SpriceActiveRule;
use App\Support\StdPrcVsDilPricer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Alibaba Std prc vs dil stack → SPRICE → Alibaba listing price API (page not required).
 *
 * Same steps as AmazonSprcDilAutoPushService, with Alibaba as the source (/alibaba-analytics):
 *  S PRC = Std Prc × (1 − (Age Disc + Dil Disc + B Disc + 0 Sold Disc + CVR Disc + Rev Disc) / 100)
 *  Std Prc is the Amazon STANDARD_PRICE of the exact SKU. A SKU without one is skipped, never priced off the listing.
 *  Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 *  NPFT = ((S PRC × margin − LP) / S PRC) − Ads%. Clearance SKUs keep the discount price.
 *  0 Sold = AB L30 (Alibaba orders L30), the page's sold qty.
 *  SNROI Cap (same as Amazon): a Dil slab with a Target SNROI % prices S PRC from that SNROI, judged on the S PRC after
 *  Ship was taken off (S PRC × take-home − LP) ÷ LP, no Ads%. The discounts are not used for that SKU.
 *  Caps: Alibaba has no LMP, so Std Prc is the maximum.
 *  Never pushes a price whose SNROI is below 0 (NegativeSnroiPushGuard). The S PRC is still saved.
 *
 * Flow, same as Amazon:
 *  - Twice-daily run: skips a SKU whose last pushed price already equals the target.
 *  - Live pull: the blue catch-up pulls the live Alibaba price first, so a SKU where Alibaba is already right
 *    is repaired instead of re-pushed.
 *  - Blue catch-up run (alibaba:push-blue-prices): SKUs whose Price ≠ S PRC are pushed again once the
 *    cooldown has passed. It needs the push-meta columns (migration 2026_10_14_220000) and refuses to run without them.
 *
 * Rows come from AlibabaAnalyticsController::data(), the same rows the page shows.
 */
class AlibabaSprcDilAutoPushService
{
    public const CHANNEL = 'alibaba';

    /** Minutes a pushed price is left alone while the marketplace reviews it (Price column stays at the live price). */
    public const REVIEW_HOLD_MINUTES = 360;

    public const LOCK_KEY = 'alibaba-sprc-dil-auto-push-run';

    /** @var array<string, AlibabaPricingPrice>|null */
    private ?array $rowsByNorm = null;

    private ?bool $metaColumns = null;

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
        $pricer = StdPrcVsDilPricer::forChannel(self::CHANNEL);
        $adsFrac = $this->channelAdsFrac();
        // The page switch decides the S PRC rule, same as Amazon: Std prc vs dil, or the Sprc Dil modal (Dil slab Target SNROI,
        // Std Prc as the maximum; Alibaba has no LMP). Cron, the page and Save Rule all read the same choice.
        $dilContext = SpriceActiveRule::usesStdPrc(self::CHANNEL) ? null : $this->loadDilContext();

        $this->log($logger, $dilContext !== null
            ? 'Alibaba S PRC rule = Sprc Dil (Dil slab Target SNROI, Std max, no Ship, no Ads, no LMP)'
            : 'Loaded Alibaba Std prc vs dil (alibaba_std_prc_vs_dil) → Age/Dil/B/0 Sold/CVR/Rev Disc + Std max (no LMP)');

        if ($blueCatchUp && ! $this->hasMetaColumns()) {
            // Without the push-meta columns a pushed SKU has no cooldown and would be pushed every 10 minutes.
            $this->log($logger, 'Blue catch-up skipped: run php artisan migrate (2026_10_14_220000_add_push_meta_to_alibaba_pricing_prices)');

            return [
                'dry_run' => $dryRun,
                'skip_push' => $skipPush,
                'stats' => ['candidates' => 0, 'applied' => 0, 'pushed' => 0, 'skipped_unchanged' => 0, 'price_repaired' => 0,
                    'skipped' => 0, 'push_failed' => 0, 'errors' => ['migration missing']],
            ];
        }

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
            $this->log($logger, 'Skipped: another '.'Alibaba'.' Sprc Dil push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0 listed on ".'Alibaba');

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
                        // Live Alibaba Price already equals the target. Keep the saved S PRC in step, no push.
                        if (! $dryRun && ! PushedListingPrice::same($row['sprice'] ?? 0, $sprice)) {
                            $this->saveSprice($row, $computed, 'pushed');
                            $stats['applied']++;
                        }
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    if ($blueCatchUp) {
                        // Headless "Push on reload": SKUs whose TT Price ≠ S PRC. The twice-daily run skips a SKU
                        // whose last pushed price already equals the target, so a Price that stayed different
                        // afterwards (Alibaba reverted it, or the link map re-read an older price) is picked up here.
                        if ($maxPushes > 0 && $stats['pushed'] + $stats['push_failed'] >= $maxPushes) {
                            $this->log($logger, "Reached max pushes ({$maxPushes}) — rest next run");
                            break;
                        }
                        if (! empty($row['push_error_at']) && $this->withinCooldown((string) $row['push_error_at'], $errorCooldownMinutes)) {
                            // Alibaba rejected the last push — retry after the cooldown, not every run.
                            $stats['skipped']++;
                            continue;
                        }
                        if ($this->isUnchanged($row, $computed)
                            && $this->withinCooldown((string) ($row['pushed_at'] ?? ''), self::REVIEW_HOLD_MINUTES)) {
                            // This exact price was pushed and the marketplace is still reviewing it: the Price
                            // column keeps the live price, so do not push the same price again until the hold ends.
                            $stats['skipped_unchanged']++;
                            continue;
                        }
                        if ($this->withinCooldown((string) ($row['pushed_at'] ?? ''), $cooldownMinutes)) {
                            // Pushed recently and Price still differs: do not loop on it every 10 minutes.
                            $stats['skipped_unchanged']++;
                            continue;
                        }
                    } elseif (! $pushAll && $this->isUnchanged($row, $computed)) {
                        // Last push already equals the target. Refresh the live Alibaba Price column, no push.
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

                    // The blue catch-up and the save-only run (every 10 min) only rewrite the saved S PRC when it changed.
                    if (! $dryRun && (! ($blueCatchUp || $skipPush) || ! PushedListingPrice::same($row['sprice'] ?? 0, $sprice))) {
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

                    if ($blueCatchUp) {
                        // Pull the live Alibaba price first: Alibaba may already be right and only the Price column
                        // stale. A matching live price is written, no push.
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

                    $this->pushOne($row, $computed, $stats, $logger);
                    if ($sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }
                } catch (Throwable $e) {
                    $stats['push_failed']++;
                    $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                    Log::error('[AlibabaSprcDilAutoPush] exception', [
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
                ($dryRun || $skipPush) ? ' [no '.'Alibaba'.' push]' : ''
            ));

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Page S PRC formula (INV > 0 child SKUs): Std rule, Std is the maximum (no LMP).
     *
     * With a $dilContext (the Sprc Dil switch is ON) the Sprc Dil modal slabs price it instead.
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:list<array<string,mixed>>}|null  $dilContext
     * @return array{sprice:float,uncapped:float,base:float,margin:float,lp:float}|null
     */
    public function computeTarget(array $row, ?StdPrcVsDilPricer $pricer = null, ?float $adsFrac = null, ?array $dilContext = null): ?array
    {
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0)) {
            return null;
        }
        // Clearance SKUs (Inv days clearance = YES) get no S PRC from any rule: the page shows C, nothing is saved or pushed.
        if ((new StdPrcVsDilPricer([], 'alibaba'))->isClearanceRow($row)) {
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
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 1.0;
        }

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
            // 0 Sold = AB L30 (Alibaba orders L30), same as the page.
            'al30' => (float) ($row['al30'] ?? 0),
            'lp' => $lp,
            'ship' => 0.0,
            // S PRC = calc − Ship. Taken off inside the pricer before the SNROI cap, so the cap judges the S PRC
            // that is really saved and pushed.
            'ship_off' => max(0.0, (float) ($row['ship'] ?? 0)),
            'margin' => $margin,
            // SNROI Cap: the page's SNROI% column is SGROI (S PRC × take-home − LP) ÷ LP, with no Ads% and no Ship (Ship is
            // already inside S PRC). The cap judges that same number, so it never subtracts Ads% here.
            'ads' => 0.0,
        ];
        if (array_key_exists('clearance', $row)) {
            $pricerRow['clearance'] = $row['clearance'];
        }
        $effective = $pricer->priceFromRow($pricerRow);
        if ($effective === null || ! ($effective > 0)) {
            return null;
        }

        // Alibaba has no LMP. Std Prc is the maximum.
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
        ];
    }

    /**
     * Sprc Dil switch ON, same as the page (ebay-sprc-dil): the Dil slab Target SNROI priced as (LP × (1 + target%)) ÷ take-home,
     * then Std Prc as the maximum. Alibaba has no Ship in this price (the Std rule takes Ship off, this one never adds it),
     * no Ads%, no CVR overlay and no LMP. A SKU whose Dil is outside every slab has no Sprc Dil price (S PRC left as is).
     * Dil = OV L30 ÷ INV, including when AB L30 = 0. Dil = 0 uses the 0–0 slab.
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:list<array<string,mixed>>}  $ctx
     * @return array{sprice:float,uncapped:float,base:float,margin:float,lp:float}|null
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
            $margin = 1.0;
        }
        $std = round((float) ($row['standard_price'] ?? 0), 2);

        $rule = AmazonDilGroiRule::match((float) ($row['dil'] ?? 0), $ctx['rules']);
        if ($rule === null) {
            return null;
        }
        $target = max(0.0, round((float) ($rule['nroi'] ?? $rule['groi']), 2));
        // Ageing Rule: the SKU's age band adds its points to the Target NROI (floored at 0).
        if (is_array($ctx['age_adj'] ?? null) && $ctx['age_adj'] !== []) {
            $target = AmazonDilGroiRule::adjustGroiForAge(
                $target,
                is_numeric($row['age_days'] ?? null) ? (float) $row['age_days'] : null,
                $ctx['age_adj']
            );
        }

        // Diff rule (same as eBay 1 / Faire): Diff = (Std Prc − Price) / Price above the saved % adds the saved points to the Target.
        $diffPct = \App\Support\EbayRuleSprice::stdPriceDiffPct($std, (float) ($row['price'] ?? 0));
        $target = max(0.0, round($target + AmazonDilGroiRule::diffAdjPoints($diffPct, $ctx['diff_adj'] ?? null), 2));

        $raw = round(($lp * (1 + $target / 100)) / $margin, 2);
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
        ];
    }

    /**
     * The Sprc Dil modal settings saved for Alibaba (alibaba_dil_vs_groi): the Dil slabs.
     *
     * @return array{rules:list<array<string,mixed>>,age_adj:?array}
     */
    protected function loadDilContext(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::CHANNEL.'_dil_vs_groi')->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        $unpacked = AmazonDilGroiRule::unpackStored($saved);
        $rules = $unpacked['rules'] === []
            ? AmazonDilGroiRule::defaultsForChannel(self::CHANNEL)
            : AmazonDilGroiRule::ensureZeroToZero($unpacked['rules']);

        return ['rules' => $rules, 'age_adj' => $unpacked['age_adj'] ?? null, 'diff_adj' => $unpacked['diff_adj'] ?? null];
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
     * Same rows as /alibaba-analytics. Listed on Alibaba, INV > 0, child SKUs only.
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

        $res = app(AlibabaAnalyticsController::class)->data();
        $payload = method_exists($res, 'getData') ? $res->getData(true) : [];
        $pageRows = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($pageRows)) {
            throw new \RuntimeException('Alibaba pricing data load failed');
        }

        $out = [];
        $seen = [];
        foreach ($pageRows as $r) {
            if (! is_array($r) || ! empty($r['is_parent_summary']) || ! empty($r['is_parent_row']) || ! empty($r['is_parent'])) {
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
            $inv = (float) ($r['INV'] ?? 0);
            $price = (float) ($r['price'] ?? 0);
            // No listing price or no product id = not on Alibaba, so nothing to push to.
            if (! ($inv > 0) || ! ($price > 0) || trim((string) ($r['product_id'] ?? '')) === '') {
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
                'lp' => (float) ($r['lp'] ?? 0),
                'ship' => (float) ($r['ship'] ?? 0),
                'margin' => (float) ($r['_margin'] ?? 0),
                'age_days' => $r['age_days'] ?? null,
                'standard_price' => (float) ($r['STANDARD_PRICE'] ?? 0),
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
     * Save S PRC the way the page does (alibaba_pricing_prices.sprice) plus the status columns.
     *
     * @param  array<string, mixed>  $row
     * @param  array{sprice:float}  $computed
     */
    protected function saveSprice(array $row, array $computed, string $status = 'applied'): void
    {
        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku === '') {
            return;
        }
        $sprice = round((float) $computed['sprice'], 2);
        $attrs = ['sprice' => $sprice];
        if ($this->hasMetaColumns()) {
            $now = now()->toDateTimeString();
            $attrs['sprice_status'] = $status;
            $attrs['sprice_status_at'] = $now;
            if ($status === 'pushed') {
                $attrs['sprice_pushed_value'] = $sprice;
                $attrs['sprice_pushed_at'] = $now;
            }
        }
        $this->persistRow($sku, $attrs);
    }

    /**
     * Push one SKU through AlibabaController::pushPricingPrice (Alibaba product/price/save). That is the same call the
     * old page push and the old runner used: it also writes the sale price to alibaba_pricing_prices / alibaba_metrics.
     * Push and pull together: after Alibaba accepts the save the live price is read straight away
     * (AlibabaPushedPricePullService::afterPush) and re-read by alibaba:pull-pushed-prices until it matches.
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
            \App\Support\AlibabaPushError::forget($sku);
            // Push and pull together, same as Amazon: read the live price now. Alibaba usually still shows the old
            // price while it reviews the change, so alibaba:pull-pushed-prices keeps re-reading it.
            try {
                $pull = app(AlibabaPushedPricePullService::class)->afterPush($sku, $price);
                if (! empty($pull['success'])) {
                    $stats['pulled'] = ($stats['pulled'] ?? 0) + 1;
                }
            } catch (Throwable $e) {
                Log::warning('[AlibabaSprcDilAutoPush] immediate Price pull after push failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            }

            return;
        }

        $stats['push_failed']++;
        $err = (string) ($result['message'] ?? 'Alibaba price push failed');
        \App\Support\AlibabaPushError::remember($sku, $err);
        $stats['errors'][] = $sku.': '.$err;
        $this->log($logger, 'Push failed '.$sku.': '.$err);
        Log::error('Alibaba Sprc Dil push failed', ['sku' => $sku, 'price' => $price, 'error' => $err]);
        $this->savePushMeta($sku, 'error', null);
    }

    /**
     * Live Alibaba price. With an expected price the Price column only moves when Alibaba shows it;
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
            Log::warning('[AlibabaSprcDilAutoPush] live price pull failed', ['channel' => self::CHANNEL, 'sku' => $sku, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array{success:bool,message?:string,price?:?float} */
    protected function pushPrice(string $sku, float $price): array
    {
        try {
            $req = Request::create('/alibaba-analytics/push-price', 'POST', [
                'updates' => [['sku' => $sku, 'price' => $price]],
            ]);
            $req->headers->set('Accept', 'application/json');
            $res = app(AlibabaAnalyticsController::class)->pushPrice($req, app(AlibabaApiService::class));
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
                'message' => (string) (($payload['results'][0]['message'] ?? null) ?: ($payload['message'] ?? 'Alibaba price push failed')),
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Status, time and pushed value. Needs the push-meta columns; without them only the price is saved. */
    protected function savePushMeta(string $sku, string $status, ?float $price): void
    {
        if (! $this->hasMetaColumns()) {
            return;
        }
        try {
            $now = now()->toDateTimeString();
            $attrs = ['sprice_status' => $status, 'sprice_status_at' => $now];
            if ($status === 'pushed' && $price !== null && $price > 0) {
                $attrs['sprice_pushed_value'] = $price;
                $attrs['sprice_pushed_at'] = $now;
            }
            $this->persistRow($sku, $attrs);
        } catch (Throwable $e) {
            Log::error('[AlibabaSprcDilAutoPush] status save failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    /** True once migration 2026_10_14_220000 has added the push-meta columns. */
    public function hasMetaColumns(): bool
    {
        if ($this->metaColumns === null) {
            try {
                $this->metaColumns = Schema::hasTable('alibaba_pricing_prices')
                    && Schema::hasColumn('alibaba_pricing_prices', 'sprice_pushed_at');
            } catch (Throwable $e) {
                $this->metaColumns = false;
            }
        }

        return $this->metaColumns;
    }

    /**
     * The page reads alibaba_pricing_prices by UPPER(TRIM(sku)), so match the same way. A new row keeps the
     * trimmed display SKU.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function persistRow(string $sku, array $attrs): void
    {
        if ($this->rowsByNorm === null) {
            $this->rowsByNorm = [];
            foreach (AlibabaPricingPrice::query()->get() as $r) {
                $this->rowsByNorm[$this->normalizeSku((string) $r->sku)] ??= $r;
            }
        }
        $key = $this->normalizeSku($sku);
        $row = $this->rowsByNorm[$key] ?? new AlibabaPricingPrice(['sku' => trim($sku)]);
        $row->fill($attrs);
        $row->save();
        $this->rowsByNorm[$key] = $row;
    }

    protected function normalizeSku(string $sku): string
    {
        return strtoupper(trim($sku));
    }

    /** @param  callable(string): void|null  $logger */
    protected function log(?callable $logger, string $msg): void
    {
        if ($logger) {
            $logger($msg);
        }
    }
}
