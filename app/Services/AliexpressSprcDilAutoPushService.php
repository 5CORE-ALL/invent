<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\AliexpressController;
use App\Models\AliexpressDataView;
use App\Models\ChannelTabulatorColumnSetting;
use App\Support\AmazonDilGroiRule;
use App\Support\EbayRuleSprice;
use App\Support\PushedListingPrice;
use App\Support\SpriceActiveRule;
use App\Support\StdPrcVsDilPricer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AliExpress Std prc vs dil stack → SPRICE → AliExpress price API (page not required).
 *
 * Same steps as AmazonSprcDilAutoPushService, with AliExpress as the source:
 *  S PRC = Std Prc × (1 − (Age Disc + Dil Disc + B Disc + 0 Sold Disc + CVR Disc + Rev Disc) / 100)
 *  Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 *  SNROI Cap (same as Amazon): a Dil slab with a Target SNROI % prices S PRC from that SNROI instead of the
 *  discounts. Then LMP cap when LMP is lower and SGROI at LMP ≥ 20%. Std Prc is the maximum.
 *  Skip when the live Price already equals the target. Price column updates on each push.
 *
 * Rows come from AliexpressController::buildPricingRows(), the same rows /aliexpress-pricing shows
 * (listed price, INV, OV L30, AL30, LP, Ship, AE margin, LMP, Std Prc, saved SPRICE).
 * No SGROI-stop guard and no negative-SNROI guard, same as Amazon.
 */
class AliexpressSprcDilAutoPushService
{
    public const LOCK_KEY = 'aliexpress-sprc-dil-auto-push-run';

    /** AliExpress batchUpdatePrice size, same as the pricing page. */
    public const PUSH_CHUNK = 40;

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
        int $cooldownMinutes = 120
    ): array {
        StdPrcVsDilPricer::flush();
        $pricer = StdPrcVsDilPricer::forChannel('aliexpress');
        // The page switch decides the S PRC rule: Std prc vs dil, or the Sprc Dil modal (slab + Diff + LMP cap by Dil band + Std max).
        $dilContext = SpriceActiveRule::usesStdPrc('aliexpress') ? null : $this->loadDilContext();

        $this->log($logger, $dilContext !== null
            ? 'AliExpress S PRC rule = Sprc Dil (Dil slab Target SNROI + Diff, LMP cap by Dil band, Std max; out of slab = Std Prc or LMP)'
            : 'Loaded AliExpress Std prc vs dil (aliexpress_std_prc_vs_dil) → Age/Dil/B/0 Sold/CVR/Rev Disc + LMP cap');

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

        $lock = Cache::lock($skipPush && ! $dryRun ? self::LOCK_KEY.'-save-only' : self::LOCK_KEY, 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another AliExpress Sprc Dil push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0");

        $canPush = ! $dryRun && ! $skipPush;
        if ($canPush && empty(config('services.aliexpress.access_token'))) {
            $stats['errors'][] = 'ALIEXPRESS_ACCESS_TOKEN is missing in .env';
            $this->log($logger, '✗ ALIEXPRESS_ACCESS_TOKEN is missing — saving S PRC only, no push');
            $canPush = false;
        }

        $total = count($rows);
        /** @var list<array{sku:string,price:float}> $queue */
        $queue = [];

        try {
            foreach ($rows as $i => $row) {
                try {
                    $computed = $this->computeTarget($row, $pricer, $dilContext);
                    if ($computed === null) {
                        $stats['skipped']++;
                        continue;
                    }
                    $sprice = (float) $computed['sprice'];

                    if (PushedListingPrice::same($row['price'] ?? 0, $sprice)) {
                        // Live Price already equals the target. Keep the saved S PRC in step, no push.
                        if (! $dryRun && ! PushedListingPrice::same($row['sprice'] ?? 0, $sprice)) {
                            $this->saveSprice((string) $row['sku'], $computed, 'pushed');
                            $stats['applied']++;
                        }
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    if (! $pushAll) {
                        if (! empty($row['push_error_at']) && $this->withinCooldown((string) $row['push_error_at'], $cooldownMinutes)) {
                            // AliExpress rejected the last push — retry after the cooldown, not every run.
                            $stats['skipped']++;
                            continue;
                        }
                        if ($this->isUnchanged($row, $computed)
                            && $this->withinCooldown((string) ($row['pushed_at'] ?? ''), $cooldownMinutes)) {
                            $stats['skipped_unchanged']++;
                            continue;
                        }
                    }

                    if (! $dryRun) {
                        $this->saveSprice((string) $row['sku'], $computed, 'saved');
                    }
                    $stats['applied']++;

                    if (! $canPush) {
                        continue;
                    }

                    $queue[] = ['sku' => (string) $row['sku'], 'price' => round($sprice, 2)];
                    if (count($queue) >= self::PUSH_CHUNK) {
                        $this->flushQueue($queue, $stats, $logger);
                        $queue = [];
                        if ($sleepMs > 0) {
                            usleep($sleepMs * 1000);
                        }
                    }
                } catch (Throwable $e) {
                    $stats['push_failed']++;
                    $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                    Log::error('[AliexpressSprcDilAutoPush] exception', [
                        'sku' => $row['sku'] ?? '',
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($logger && (($i + 1) % 100 === 0 || ($i + 1) === $total)) {
                    $this->log($logger, 'Progress '.($i + 1)."/{$total} (pushed {$stats['pushed']}, unchanged {$stats['skipped_unchanged']})");
                }
            }

            if ($queue !== []) {
                $this->flushQueue($queue, $stats, $logger);
            }

            $this->log($logger, sprintf(
                'Done: applied=%d pushed=%d unchanged=%d skipped=%d failed=%d%s',
                $stats['applied'],
                $stats['pushed'],
                $stats['skipped_unchanged'],
                $stats['skipped'],
                $stats['push_failed'],
                ($dryRun || $skipPush) ? ' [no AliExpress push]' : ''
            ));

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Page S PRC formula (INV > 0 child SKUs): Std rule, then LMP cap, Std is the maximum.
     *
     * With a $dilContext (the Sprc Dil switch is ON) the Sprc Dil modal rules price it instead.
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array}|null  $dilContext
     * @return array{sprice:float,lmp_capped:bool,base:float,sgpft:int,sroi:int}|null
     */
    public function computeTarget(array $row, ?StdPrcVsDilPricer $pricer = null, ?array $dilContext = null): ?array
    {
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0)) {
            return null;
        }
        // Clearance SKUs (Inv days clearance = YES) get no S PRC from any rule: the page shows C, nothing is saved or pushed.
        if ((new StdPrcVsDilPricer([], 'aliexpress'))->isClearanceRow($row)) {
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
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 0.89;
        }
        $lmp = (float) ($row['lmp'] ?? 0);

        $pricer ??= StdPrcVsDilPricer::forChannel('aliexpress');
        // Age / Dil / B / 0 Sold / CVR / Rev discounts, then clearance SKUs keep the discount price.
        $pricerRow = [
            'sku' => (string) ($row['sku'] ?? ''),
            'inv' => $inv,
            'dil' => (float) ($row['dil'] ?? 0),
            'std_price' => $std,
            'age_days' => $row['age_days'] ?? null,
            'cvr' => (float) ($row['cvr'] ?? 0),
            'reviews' => (float) ($row['review_count'] ?? 0),
            'al30' => (float) ($row['al30'] ?? 0),
            'lp' => $lp,
            'ship' => $ship,
            'margin' => $margin,
            // SNROI Cap: the AliExpress SNROI% column has no Ads%, so the cap judges the same take-home and Ship only.
            'ads' => 0.0,
        ];
        if (array_key_exists('clearance', $row)) {
            $pricerRow['clearance'] = $row['clearance'];
        }
        $effective = $pricer->priceFromRow($pricerRow);
        if ($effective === null || ! ($effective > 0)) {
            return null;
        }

        $capped = AmazonDilGroiRule::capSpriceToLmp($effective, $lmp, $lp, $ship, $margin, max($std, $effective));
        $lmpCapped = $lmp > 0
            && ($effective - $capped) > 0.009
            && abs($capped - round($lmp, 2)) <= 0.02;

        if (! is_finite($capped) || $capped < 0.01) {
            return null;
        }
        $sprice = round($capped, 2);
        $profit = ($sprice * $margin) - $lp - $ship;

        return [
            'sprice' => $sprice,
            'lmp_capped' => $lmpCapped,
            'base' => round($std, 2),
            'sgpft' => (int) round(($profit / $sprice) * 100),
            'sroi' => $lp > 0 ? (int) round(($profit / $lp) * 100) : 0,
        ];
    }

    /**
     * Sprc Dil switch ON, same as the page (ebay-sprc-dil + /amazon-tabulator-view order):
     * Dil slab Target SNROI, Diff points, then price = (LP x (1 + Target%) + Ship) / take-home (no Ads on AliExpress),
     * LMP cap (price at/above LMP and SGROI at LMP >= 20% -> LMP x (1 - x%), x by the SKU's Dil band), Std Prc as the maximum.
     * AliExpress only: Dil outside every slab -> S PRC = Std Prc, and LMP when Std Prc is above LMP (no SGROI test, no Stop).
     * Clearance SKUs are priced like any other. No CVR overlay on AliExpress.
     *
     * @param  array<string, mixed>  $row
     * @param  array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array}  $ctx
     * @return array{sprice:float,lmp_capped:bool,base:float,sgpft:int,sroi:int}|null
     */
    protected function computeDilTarget(array $row, array $ctx): ?array
    {
        $margin = (float) ($row['margin'] ?? 0);
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 0.89;
        }
        $lp = (float) ($row['lp'] ?? 0);
        $ship = (float) ($row['ship'] ?? 0);
        $std = round((float) ($row['standard_price'] ?? 0), 2);
        $lmp = (float) ($row['lmp'] ?? 0);
        $dil = (float) ($row['dil'] ?? 0);

        $rule = AmazonDilGroiRule::match($dil, $ctx['rules']);
        $lmpCapped = false;
        if ($rule === null) {
            if (! ($std > 0)) {
                return null;
            }
            $lmpCapped = $lmp > 0 && $std + 0.0001 > $lmp;
            $sprice = $lmpCapped ? round($lmp, 2) : $std;
        } else {
            if (! ($lp > 0)) {
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
            $diffPct = EbayRuleSprice::stdPriceDiffPct(EbayRuleSprice::diffRefPrice($std, (float) ($row['lmp'] ?? 0)), (float) ($row['price'] ?? 0));
            $target = max(0.0, round($target + AmazonDilGroiRule::diffAdjPoints($diffPct, $ctx['diff_adj'] ?? null), 2));

            $raw = round(($lp * (1 + $target / 100) + $ship) / $margin, 2);
            if (! is_finite($raw) || $raw < 0.01) {
                return null;
            }
            $reducePct = AmazonDilGroiRule::lmpCapReducePct($dil, $ctx['lmp_cap'] ?? null);
            $sprice = round(AmazonDilGroiRule::capSpriceToLmp($raw, $lmp, $lp, $ship, $margin, $std, $reducePct), 2);
            $lmpCapped = $lmp > 0 && ($raw - $sprice) > 0.009 && abs($sprice - round($lmp * (1 - $reducePct / 100), 2)) <= 0.02;
        }
        if (! is_finite($sprice) || $sprice < 0.01) {
            return null;
        }
        $profit = ($sprice * $margin) - $lp - $ship;

        return [
            'sprice' => $sprice,
            'lmp_capped' => $lmpCapped,
            'base' => $std,
            'sgpft' => (int) round(($profit / $sprice) * 100),
            'sroi' => $lp > 0 ? (int) round(($profit / $lp) * 100) : 0,
        ];
    }

    /**
     * The Sprc Dil modal settings saved for AliExpress: slabs, Diff rule and LMP cap.
     *
     * @return array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array}
     */
    protected function loadDilContext(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', 'aliexpress_dil_vs_groi')->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        $unpacked = AmazonDilGroiRule::unpackStored($saved);
        $rules = $unpacked['rules'] === []
            ? AmazonDilGroiRule::defaultsForChannel('aliexpress')
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
     * Same rows as /aliexpress-pricing. Listed on AliExpress, INV > 0, child SKUs only.
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
            Log::warning('[AliexpressSprcDilAutoPush] age days lookup failed', ['error' => $e->getMessage()]);
        }

        $pageRows = app(AliexpressController::class)->buildPricingRows(false);

        $out = [];
        $seen = [];
        foreach ($pageRows as $r) {
            if (! empty($r['is_parent'])) {
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
            $price = (float) ($r['price'] ?? 0);
            if (! ($inv > 0) || ! ($price > 0) || ! empty($r['is_missing_aliexpress'])) {
                continue;
            }
            if (trim((string) ($r['ae_product_id'] ?? '')) === '') {
                continue;
            }
            $seen[$key] = true;

            $ov = (float) ($r['ov_l30'] ?? 0);
            $status = strtolower((string) ($r['SPRICE_STATUS'] ?? ''));

            $out[] = [
                'sku' => $sku,
                'price' => $price,
                'inv' => $inv,
                // Same as the page Dil: raw (OV L30 / INV) × 100, not rounded before slab match.
                'dil' => ($ov / $inv) * 100,
                'ov_l30' => $ov,
                'al30' => (float) ($r['al30'] ?? 0),
                'cvr' => (float) ($r['cvr'] ?? 0),
                'review_count' => (float) ($r['reviews'] ?? 0),
                'lp' => (float) ($r['lp'] ?? 0),
                'ship' => (float) ($r['ship'] ?? 0),
                'margin' => (float) ($r['_margin'] ?? 0),
                'lmp' => (float) ($r['lmp'] ?? 0),
                'age_days' => $ageDays->ageDays($sku, $inv, $agePushBySku, $ageLastSaleBySku),
                'standard_price' => (float) ($r['STANDARD_PRICE'] ?? 0),
                'sprice' => (float) ($r['sprice'] ?? 0),
                'pushed_value' => isset($r['SPRICE_PUSHED_VALUE']) && is_numeric($r['SPRICE_PUSHED_VALUE'])
                    ? (float) $r['SPRICE_PUSHED_VALUE']
                    : null,
                'pushed_at' => $this->pushedAt($sku),
                'push_error_at' => $status === 'error' ? ($r['SPRICE_STATUS_UPDATED_AT'] ?? null) : null,
            ];

            if ($limit !== null && $limit > 0 && count($out) >= $limit) {
                break;
            }
        }

        usort($out, static fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

        return $out;
    }

    /** Last successful push time from the data view JSON (cooldown). */
    protected function pushedAt(string $sku): ?string
    {
        return $this->pushedAtBySku()[strtoupper(trim($sku))] ?? null;
    }

    /** @var array<string, string>|null */
    private ?array $pushedAtCache = null;

    /** @return array<string, string> */
    private function pushedAtBySku(): array
    {
        if ($this->pushedAtCache !== null) {
            return $this->pushedAtCache;
        }
        $map = [];
        try {
            foreach (AliexpressDataView::query()->get(['sku', 'value']) as $view) {
                $val = $this->decodeValue($view->value);
                $at = $val['SPRICE_PUSHED_AT'] ?? null;
                if (is_string($at) && $at !== '') {
                    $map[strtoupper(trim((string) $view->sku))] = $at;
                }
            }
        } catch (Throwable $e) {
            Log::warning('[AliexpressSprcDilAutoPush] pushed-at load failed', ['error' => $e->getMessage()]);
        }

        return $this->pushedAtCache = $map;
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
     * @param  array{sprice:float,sgpft:int,sroi:int}  $computed
     */
    protected function saveSprice(string $sku, array $computed, string $status = 'saved'): void
    {
        $key = strtoupper(trim($sku));
        $view = AliexpressDataView::whereRaw('UPPER(TRIM(sku)) = ?', [$key])->first()
            ?? new AliexpressDataView(['sku' => trim($sku)]);

        $existing = $this->decodeValue($view->value);
        $existing['SPRICE'] = round((float) $computed['sprice'], 2);
        $existing['SGPFT'] = (int) $computed['sgpft'];
        $existing['SROI'] = (int) $computed['sroi'];
        $existing['SPRICE_STATUS'] = $status;
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
        if ($status === 'pushed') {
            $existing['SPRICE_PUSHED_VALUE'] = round((float) $computed['sprice'], 2);
        }

        $view->value = $existing;
        $view->save();
    }

    /**
     * Push one batch through the same endpoint as the pricing page (AliExpress batchUpdatePrice).
     * No SGROI-stop guard. The controller writes Price, SPRICE_STATUS and SPRICE_PUSHED_VALUE.
     *
     * @param  list<array{sku:string,price:float}>  $queue
     * @param  array<string, mixed>  $stats
     * @param  callable(string): void|null  $logger
     */
    protected function flushQueue(array $queue, array &$stats, ?callable $logger): void
    {
        if ($queue === []) {
            return;
        }

        $results = $this->pushBatch($queue);
        if ($results === null) {
            $stats['push_failed'] += count($queue);
            $stats['errors'][] = 'push: batch failed';

            return;
        }

        $seen = [];
        foreach ($results as $r) {
            $sku = (string) ($r['sku'] ?? '');
            $seen[strtoupper(trim($sku))] = true;
            if (! empty($r['success'])) {
                $stats['pushed']++;
                $this->stampPushedAt($sku);
            } else {
                $stats['push_failed']++;
                $err = (string) ($r['error'] ?? 'AliExpress push failed');
                $stats['errors'][] = $sku.': '.$err;
                $this->log($logger, 'Push failed '.$sku.': '.$err);
                Log::error('AliExpress Sprc Dil push failed', ['sku' => $sku, 'error' => $err]);
            }
        }
        foreach ($queue as $q) {
            if (! isset($seen[strtoupper(trim($q['sku']))])) {
                $stats['push_failed']++;
                $this->savePushMeta($q['sku'], 'error');
            }
        }
    }

    /**
     * @param  list<array{sku:string,price:float}>  $queue
     * @return list<array<string, mixed>>|null
     */
    protected function pushBatch(array $queue): ?array
    {
        try {
            $req = Request::create('/aliexpress/pricing-push-price', 'POST', ['updates' => $queue]);
            $req->headers->set('Accept', 'application/json');
            $res = app(AliexpressController::class)->pushPricingPrice($req, app(AliExpressApiService::class));
            $data = $res->getData(true);

            return is_array($data['results'] ?? null) ? $data['results'] : [];
        } catch (Throwable $e) {
            Log::error('[AliexpressSprcDilAutoPush] push batch exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function stampPushedAt(string $sku): void
    {
        $this->savePushMeta($sku, null);
    }

    /** SPRICE_PUSHED_AT on success (null status keeps the controller's status). */
    protected function savePushMeta(string $sku, ?string $status): void
    {
        try {
            $view = AliexpressDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first()
                ?? new AliexpressDataView(['sku' => trim($sku)]);
            $existing = $this->decodeValue($view->value);
            if ($status === null) {
                $existing['SPRICE_PUSHED_AT'] = now()->toDateTimeString();
            } else {
                $existing['SPRICE_STATUS'] = $status;
                $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            }
            $view->value = $existing;
            $view->save();
        } catch (Throwable $e) {
            Log::error('[AliexpressSprcDilAutoPush] status save failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
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
