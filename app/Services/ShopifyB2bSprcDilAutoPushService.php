<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use App\Models\ShopifyB2BDataView;
use App\Support\AmazonDilGroiRule;
use App\Support\EbayRuleSprice;
use App\Support\PushedListingPrice;
use App\Support\SpriceActiveRule;
use App\Support\StdPrcVsDilPricer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shopify B2B (business5core.com) S PRC → saved SPRICE → live selling price, with the page closed.
 *
 * /shopify-b2b-pricing prices every row in the browser, so before this job a price only moved while somebody had
 * the page open. This job computes the SAME price the page shows (shopifyB2bDisplayedSprice), saves it, and pushes
 * every listed SKU (INV > 0, live Price > 0) whose live Price differs from it. Clearance SKUs are included.
 *
 * Price chain (Sprc Dil switch ON, the only rule this job prices; with Std prc vs dil ON it does nothing):
 *  1. Clearance SKU (Inv days clearance = YES): (LP × (1 + Clearance NROI%)) ÷ 0.95. No slab, Diff, CVR, LMP or Std rule.
 *  2. Dil slab (nearest slab outside the table) → Target NROI%
 *  3. CVR overlay: CVR < down_lt → down_adj, CVR > up_gt → up_adj (needs Views and B2B L30 > 0), floored at 0
 *  4. Diff rule: Std Prc above the rule price (WITHOUT the boost) by more than gt_pct adds `points`. Judged against the
 *     unboosted price, not the live Price, so pushing the boosted price cannot remove the boost and flip the price back.
 *  5. price = (LP × (1 + target%)) ÷ 0.95   (B2B has no Ship and no Ads%)
 *  6. LMP cap by Dil band (LMP × (1 − x%) when the price reaches LMP and SGROI at LMP ≥ 20%), then Std Prc is the maximum.
 */
class ShopifyB2bSprcDilAutoPushService
{
    public const CHANNEL = 'shopify_b2b';

    public const LOCK_KEY = 'shopify-b2b-sprc-dil-auto-push-run';

    /** Shopify B2B take-home, same constant as the page. */
    public const MARGIN = 0.95;

    /** A failed push of the same price is not retried for this many minutes. */
    public const FAIL_COOLDOWN_MINUTES = 60;

    public function __construct(protected StorePricePushService $pusher)
    {
    }

    /**
     * @param  list<string>|null  $onlySkus  SKUs to handle; null = all listed candidates
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    public function run(
        bool $dryRun = false,
        bool $push = true,
        ?int $limit = null,
        ?array $onlySkus = null,
        int $maxPushes = 120,
        ?callable $logger = null
    ): array {
        $stats = [
            'candidates' => 0,
            'saved' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'clearance' => 0,
            'blue' => 0,
            'pushed' => 0,
            'failed' => 0,
            'cooldown' => 0,
            'left_for_next_run' => 0,
            'errors' => [],
        ];

        if (SpriceActiveRule::forChannel(self::CHANNEL) !== 'dil') {
            $this->log($logger, 'Sprc Dil switch is OFF on Shopify B2B (Std prc vs dil is priced by the page) — nothing to do.');

            return ['dry_run' => $dryRun, 'stats' => $stats];
        }

        $lock = Cache::lock(self::LOCK_KEY, 3600);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another Shopify B2B Sprc Dil run is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'stats' => $stats];
        }

        try {
            $ctx = self::dilContext();
            $clearanceNroi = self::clearanceNroi();
            $this->log($logger, 'Sprc Dil ON → Dil slab Target NROI + CVR overlay + Diff rule + LMP cap (Dil band) + Std max. Clearance NROI='.$clearanceNroi.'%');

            try {
                $rows = $this->loadCandidates($limit, $onlySkus);
            } catch (Throwable $e) {
                $stats['errors'][] = 'load: '.$e->getMessage();
                $this->log($logger, '✗ Load failed: '.$e->getMessage());

                return ['dry_run' => $dryRun, 'stats' => $stats];
            }

            $stats['candidates'] = count($rows);
            $this->log($logger, "Found {$stats['candidates']} listed SKU(s) with INV > 0");

            $clearanceChecker = new StdPrcVsDilPricer([], self::CHANNEL);
            $toPush = [];
            foreach ($rows as $row) {
                try {
                    $row['clearance'] = $clearanceChecker->isClearanceRow($row);
                    $computed = $this->computeTarget($row, $ctx, $clearanceNroi);
                    if ($computed === null) {
                        $stats['skipped']++;

                        continue;
                    }
                    if ($row['clearance']) {
                        $stats['clearance']++;
                    }
                    if (PushedListingPrice::same($row['sprice'] ?? 0, $computed['sprice'])) {
                        $stats['unchanged']++;
                    } else {
                        if (! $dryRun) {
                            $this->saveSprice((string) $row['sku'], $computed);
                        }
                        $stats['saved']++;
                    }
                    if (! PushedListingPrice::same($row['price'], $computed['sprice'])) {
                        $stats['blue']++;
                        $toPush[] = [
                            'sku' => (string) $row['sku'],
                            'price' => $computed['sprice'],
                            'live' => (float) $row['price'],
                            'clearance' => (bool) $row['clearance'],
                        ];
                    }
                } catch (Throwable $e) {
                    $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                    Log::error('[ShopifyB2bSprcDilAutoPush] exception', [
                        'sku' => $row['sku'] ?? '',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Clearance first, then by SKU, so a run cut short by --max-pushes never starves them.
            usort($toPush, static function ($a, $b) {
                if ($a['clearance'] !== $b['clearance']) {
                    return $a['clearance'] ? -1 : 1;
                }

                return strnatcasecmp($a['sku'], $b['sku']);
            });

            if ($push && ! $dryRun && $toPush !== []) {
                $done = 0;
                foreach ($toPush as $i => $job) {
                    if ($done >= $maxPushes) {
                        $stats['left_for_next_run'] = count($toPush) - $i;
                        break;
                    }
                    $cooldownKey = self::cooldownKey($job['sku'], $job['price']);
                    if (Cache::has($cooldownKey)) {
                        $stats['cooldown']++;

                        continue;
                    }
                    $done++;
                    $result = $this->pushOne($job['sku'], $job['price']);
                    if ($result['success']) {
                        $stats['pushed']++;
                        $this->log($logger, sprintf('pushed %s: %.2f → %.2f%s', $job['sku'], $job['live'], $result['price'], $job['clearance'] ? ' (clearance)' : ''));
                    } else {
                        $stats['failed']++;
                        Cache::put($cooldownKey, 1, now()->addMinutes(self::FAIL_COOLDOWN_MINUTES));
                        $stats['errors'][] = $job['sku'].': '.$result['message'];
                        $this->log($logger, '✗ '.$job['sku'].': '.$result['message']);
                    }
                    // The store API is rate limited, same pause as the page push job.
                    usleep(1_500_000);
                }
            }

            $this->log($logger, sprintf(
                'Done: candidates=%d saved=%d unchanged=%d skipped=%d clearance=%d blue=%d pushed=%d failed=%d cooldown=%d left=%d%s',
                $stats['candidates'], $stats['saved'], $stats['unchanged'], $stats['skipped'], $stats['clearance'],
                $stats['blue'], $stats['pushed'], $stats['failed'], $stats['cooldown'], $stats['left_for_next_run'],
                $dryRun ? ' [dry run]' : ''
            ));
        } finally {
            optional($lock)->release();
        }

        return ['dry_run' => $dryRun, 'stats' => $stats, 'to_push' => $toPush ?? []];
    }

    public static function cooldownKey(string $sku, float $price): string
    {
        return 'shopify-b2b-push-fail:'.strtoupper(trim($sku)).':'.number_format($price, 2, '.', '');
    }

    /**
     * Same S PRC the page shows for this row (shopifyB2bDisplayedSprice with the Sprc Dil switch ON).
     *
     * @param  array<string, mixed>  $row  sku, inv, dil, views, b2b_l30, lp, lmp, standard_price, price, clearance
     * @param  array{rules:array,cvr_adj:array,lmp_cap:array,diff_adj:array}  $ctx
     * @return array{sprice:float,rule_price:float,clearance:bool,lmp_capped:bool,sgpft:float,snpft:float,sroi:float,snroi:float}|null
     */
    public function computeTarget(array $row, array $ctx, float $clearanceNroi = 0.0): ?array
    {
        if (! ((float) ($row['inv'] ?? 0) > 0)) {
            return null;
        }
        $lp = (float) ($row['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }

        // Clearance: only the Clearance Target NROI% prices it.
        if (! empty($row['clearance'])) {
            $price = AmazonDilGroiRule::suggestedPrice($lp, 0.0, $clearanceNroi, 0.0, self::MARGIN);
            if ($price === null || ! ($price >= 0.01)) {
                return null;
            }

            return $this->metrics(round($price, 2), round($price, 2), $lp, true, false);
        }

        $meta = self::dilMeta($row, $ctx);
        $raw = $meta['price'] ?? null;
        if ($raw === null || ! ($raw > 0)) {
            return null;
        }
        $raw = round($raw, 2);
        $std = (float) ($row['standard_price'] ?? 0);
        $lmp = (float) ($row['lmp'] ?? 0);
        $reducePct = AmazonDilGroiRule::lmpCapReducePct((float) ($row['dil'] ?? 0), $ctx['lmp_cap'] ?? null);

        $final = round(AmazonDilGroiRule::capSpriceToLmp($raw, $lmp, $lp, 0.0, self::MARGIN, $std, $reducePct), 2);
        if (! is_finite($final) || $final < 0.01) {
            return null;
        }
        $lmpCapped = $lmp > 0 && ($raw - $final) > 0.009
            && abs($final - AmazonDilGroiRule::lmpTargetPrice($lmp, $reducePct)) <= 0.02;

        return $this->metrics($final, $raw, $lp, false, $lmpCapped);
    }

    /**
     * Dil slab → CVR overlay → Diff rule → price. LP is required.
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:array,cvr_adj:array,lmp_cap:array,diff_adj:array}  $ctx
     * @return array{slab:float,cvr_adj:float,diff_adj:float,target:float,price:?float}|null
     */
    public static function dilMeta(array $row, array $ctx): ?array
    {
        if (! ((float) ($row['inv'] ?? 0) > 0)) {
            return null;
        }
        $lp = (float) ($row['lp'] ?? 0);
        $slab = AmazonDilGroiRule::matchOrNearest((float) ($row['dil'] ?? 0), $ctx['rules'] ?? []);
        if ($slab === null) {
            return null;
        }
        $slabTarget = (float) ($slab['nroi'] ?? $slab['groi']);
        $cvrCfg = AmazonDilGroiRule::normalizeCvrAdj(is_array($ctx['cvr_adj'] ?? null) ? $ctx['cvr_adj'] : null);
        $cvrAdj = 0.0;
        // No L60 CVR on B2B: Down / Up follow the thresholds. No Views or B2B L30 = 0 skips the overlay.
        if ((float) ($row['views'] ?? 0) > 0 && (float) ($row['b2b_l30'] ?? 0) > 0) {
            $cvr = ((float) $row['b2b_l30'] / (float) $row['views']) * 100;
            if ($cvr < $cvrCfg['down_lt']) {
                $cvrAdj = $cvrCfg['down_adj'];
            } elseif ($cvr > $cvrCfg['up_gt']) {
                $cvrAdj = $cvrCfg['up_adj'];
            }
        }
        $afterCvr = max(0.0, round($slabTarget + $cvrAdj, 2));
        // Ageing Rule: the SKU's age band points, after the CVR overlay and before the Diff rule (same as the page).
        $ageAdj = AmazonDilGroiRule::ageAdjPoints(
            is_numeric($row['age_days'] ?? null) ? (float) $row['age_days'] : null,
            is_array($ctx['age_adj'] ?? null) ? $ctx['age_adj'] : null
        );
        $afterCvr = max(0.0, round($afterCvr + $ageAdj, 2));

        // Diff judged against the rule price without the boost, so it does not depend on the live Price (no flip-flop).
        // A listed SKU (live Price > 0) and a Std Prc are still required, same as the page.
        $std = (float) ($row['standard_price'] ?? 0);
        $live = round((float) ($row['price'] ?? 0), 2);
        $diffAdj = 0.0;
        if (EbayRuleSprice::diffRefPrice($std, (float) ($row['lmp'] ?? 0)) > 0 && $live > 0) {
            $base = AmazonDilGroiRule::suggestedPrice($lp, 0.0, $afterCvr, 0.0, self::MARGIN);
            if ($base !== null && $base > 0) {
                $diffAdj = AmazonDilGroiRule::diffAdjPoints(
                    EbayRuleSprice::stdPriceDiffPct(EbayRuleSprice::diffRefPrice($std, (float) ($row['lmp'] ?? 0)), $base),
                    is_array($ctx['diff_adj'] ?? null) ? $ctx['diff_adj'] : null
                );
            }
        }
        $target = max(0.0, round($afterCvr + $diffAdj, 2));
        $price = AmazonDilGroiRule::suggestedPrice($lp, 0.0, $target, 0.0, self::MARGIN);

        return [
            'slab' => $slabTarget,
            'cvr_adj' => $cvrAdj,
            'age_adj' => $ageAdj,
            'diff_adj' => $diffAdj,
            'target' => $target,
            'price' => ($price !== null && $price >= 0.01) ? $price : null,
        ];
    }

    /** Clearance Target NROI% saved with the Shopify B2B Sprc Dil rules (last row of the modal). Default 0. */
    public static function clearanceNroi(): float
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::CHANNEL.'_dil_vs_groi')->first();
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($row?->visibility) ? $row->visibility : null);
        $n = $unpacked['clearance_nroi'] ?? null;

        return (is_numeric($n) && (float) $n >= 0) ? (float) $n : 0.0;
    }

    /**
     * The Sprc Dil modal settings saved for Shopify B2B: Dil slabs, CVR overlay, LMP cap (reduce % by Dil band), Diff rule.
     *
     * @return array{rules:array,cvr_adj:array,lmp_cap:array,diff_adj:array}
     */
    public static function dilContext(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::CHANNEL.'_dil_vs_groi')->first();
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($row?->visibility) ? $row->visibility : null);
        $rules = $unpacked['rules'] === []
            ? AmazonDilGroiRule::defaultsForChannel(self::CHANNEL)
            : AmazonDilGroiRule::ensureZeroToZero($unpacked['rules']);

        return [
            'rules' => $rules,
            'cvr_adj' => $unpacked['cvr_adj'],
            'lmp_cap' => $unpacked['lmp_cap'],
            'diff_adj' => $unpacked['diff_adj'],
            'age_adj' => $unpacked['age_adj'] ?? null,
        ];
    }

    /**
     * @return array{sprice:float,rule_price:float,clearance:bool,lmp_capped:bool,sgpft:float,snpft:float,sroi:float,snroi:float}
     */
    protected function metrics(float $sprice, float $rulePrice, float $lp, bool $clearance, bool $lmpCapped): array
    {
        // B2B metrics exclude Ship and Ads%, same as Shopifyb2bController::calculateAndSaveSprice.
        $gross = ($sprice * self::MARGIN) - $lp;
        $sgpft = $sprice > 0 ? ($gross / $sprice) * 100 : 0.0;
        $sroi = $lp > 0 ? ($gross / $lp) * 100 : 0.0;

        return [
            'sprice' => $sprice,
            'rule_price' => $rulePrice,
            'clearance' => $clearance,
            'lmp_capped' => $lmpCapped,
            'sgpft' => $sgpft,
            'snpft' => $sgpft,
            'sroi' => $sroi,
            'snroi' => $sroi,
        ];
    }

    /**
     * Same rows as /shopify-b2b-pricing. Child SKUs with INV > 0 and a live store price.
     *
     * @param  list<string>|null  $onlySkus
     * @return list<array<string, mixed>>
     */
    protected function loadCandidates(?int $limit, ?array $onlySkus): array
    {
        $want = null;
        if ($onlySkus !== null && $onlySkus !== []) {
            $want = array_flip(array_map(static fn ($s) => strtoupper(trim((string) $s)), $onlySkus));
        }

        // Ageing Rule: the Shopify push clock (same source as the Age Disc column).
        $ageCtl = app(\App\Http\Controllers\ProductMaster\InvDaysController::class);
        $agePushBySku = [];
        $ageLastSaleBySku = [];
        try {
            $ageSources = $ageCtl->ageDaySources();
            $agePushBySku = $ageSources['push'] ?? [];
            $ageLastSaleBySku = $ageSources['last_sale'] ?? [];
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ShopifyB2bSprcDilAutoPush] age days lookup failed', ['error' => $e->getMessage()]);
        }

        $pageRows = app(\App\Http\Controllers\MarketPlace\Shopifyb2bController::class)->getViewShopifyB2bTabularData();
        if (! is_array($pageRows)) {
            throw new \RuntimeException('Shopify B2B pricing data load failed');
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
            if (! ($inv > 0) || ! ($price > 0)) {
                continue;
            }
            $seen[$key] = true;

            $ovL30 = (float) ($r['L30'] ?? 0);
            $out[] = [
                'sku' => $sku,
                'price' => $price,
                'inv' => $inv,
                // Same as the page Dil: raw (OV L30 / INV) × 100, not rounded before the slab match.
                'dil' => ($ovL30 / $inv) * 100,
                'views' => (float) ($r['Views'] ?? 0),
                'b2b_l30' => (float) ($r['B2B L30'] ?? 0),
                'lp' => (float) ($r['LP_productmaster'] ?? 0),
                'lmp' => (float) ($r['lmp_price'] ?? 0),
                'age_days' => $ageCtl->ageDays($sku, $inv, $agePushBySku, $ageLastSaleBySku),
                'standard_price' => (float) ($r['STANDARD_PRICE'] ?? 0),
                'sprice' => (float) ($r['SPRICE'] ?? 0),
            ];
            if ($limit !== null && $limit > 0 && count($out) >= $limit) {
                break;
            }
        }

        usort($out, static fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

        return $out;
    }

    /**
     * Save S PRC the way the page does (shopifyb2b_data_view.value).
     *
     * @param  array{sprice:float,sgpft:float,snpft:float,sroi:float,snroi:float}  $computed
     */
    protected function saveSprice(string $sku, array $computed): void
    {
        $view = ShopifyB2BDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first()
            ?? new ShopifyB2BDataView(['sku' => $sku]);
        $existing = $this->decodeValue($view->value);
        $existing['SPRICE'] = round((float) $computed['sprice'], 2);
        $existing['calc_price'] = round((float) $computed['sprice'], 2);
        $existing['SGPFT'] = $computed['sgpft'];
        $existing['SNPFT'] = $computed['snpft'];
        $existing['SPFT'] = $computed['snpft'];
        $existing['SROI'] = $computed['sroi'];
        $existing['SNROI'] = $computed['snroi'];
        $existing['SPRICE_STATUS'] = 'saved';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
        $view->value = $existing;
        $view->save();
    }

    /**
     * Push to business5core.com and record the result like Shopifyb2bController::pushSpriceToWebsite.
     *
     * @return array{success:bool,message:string,price:float}
     */
    protected function pushOne(string $sku, float $price): array
    {
        try {
            $result = $this->pusher->pushSprice($sku, $price);
        } catch (Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        $view = ShopifyB2BDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first()
            ?? new ShopifyB2BDataView(['sku' => $sku]);
        $existing = $this->decodeValue($view->value);
        if (! empty($result['success'])) {
            $live = isset($result['price']) ? round((float) $result['price'], 2) : round($price, 2);
            $existing['SPRICE'] = $live;
            $existing['SPRICE_STATUS'] = 'pushed';
            unset($existing['PUSH_PRC_ERROR'], $existing['PUSH_PRC_ERROR_AT']);
        } else {
            $existing['SPRICE_STATUS'] = 'error';
            $existing['PUSH_PRC_ERROR'] = (string) ($result['message'] ?? 'push failed');
            $existing['PUSH_PRC_ERROR_AT'] = now()->toDateTimeString();
        }
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
        $view->value = $existing;
        $view->save();

        return [
            'success' => ! empty($result['success']),
            'message' => (string) ($result['message'] ?? ''),
            'price' => ! empty($result['success']) ? (float) ($result['price'] ?? $price) : $price,
        ];
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
