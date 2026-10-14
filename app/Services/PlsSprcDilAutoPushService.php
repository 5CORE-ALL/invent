<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\CvrMasterController;
use App\Http\Controllers\MarketPlace\PlsController;
use App\Models\AmazonDataView;
use App\Models\PLSDataView;
use App\Support\AmazonDilGroiRule;
use App\Support\NegativeSnroiPushGuard;
use App\Support\PushedListingPrice;
use App\Support\StdPrcVsDilPricer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PLS (ProLightSounds) Std prc vs dil stack → SPRICE → ProLightSounds Shopify price (page not required).
 *
 * Same steps as AmazonSprcDilAutoPushService, with PLS as the source:
 *  S PRC = Std Prc × (1 − (Age Disc + Dil Disc + B Disc + 0 Sold Disc + CVR Disc + Rev Disc) / 100)
 *  Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 *  Then raised to Min NPFT % (saved in pls_std_prc_vs_dil, default 10). NPFT = ((S PRC × margin − Ship − LP) / S PRC).
 *  PLS has no ads, so NPFT = GPFT. Clearance SKUs keep the discount price.
 *  0 Sold = P L30 (pls_products.p_l30), the page's sold qty. Std Prc is the maximum.
 *  PLS has no LMP and no Amz rule, so neither is used.
 *  Never pushes a price whose SNROI is below 0 (NegativeSnroiPushGuard). The S PRC is still saved.
 *
 * Flow, same as Amazon:
 *  - Twice-daily run: skips a SKU whose last pushed price already equals the target.
 *  - Live pull: the blue catch-up reads the live ProLightSounds variant price first, so a SKU where the store is
 *    already right is repaired instead of re-pushed. The push itself is verified by Shopify (the returned price
 *    must equal the pushed price), so no second read is needed after a push.
 *  - Blue catch-up run (pls:push-blue-prices): SKUs whose PLS Price ≠ S PRC are pushed again once the
 *    cooldown has passed.
 *
 * Rows come from PlsController::pricingDataJson(), the same rows /pls-pricing shows.
 */
class PlsSprcDilAutoPushService
{
    public const LOCK_KEY = 'pls-sprc-dil-auto-push-run';

    /** @var array<string, PLSDataView>|null */
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
        int $cooldownMinutes = 60,
        int $errorCooldownMinutes = 120,
        int $maxPushes = 0
    ): array {
        $pricer = StdPrcVsDilPricer::forChannel('pls');

        $this->log($logger, 'Loaded PLS Std prc vs dil (pls_std_prc_vs_dil) → Age/Dil/B/0 Sold/CVR/Rev Disc + Min NPFT (Ship, no ads) + Std max');

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

        $lock = Cache::lock(self::LOCK_KEY, 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another PLS Sprc Dil push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0 listed on PLS");

        $canPush = ! $dryRun && ! $skipPush;
        $total = count($rows);

        try {
            foreach ($rows as $i => $row) {
                try {
                    $computed = $this->computeTarget($row, $pricer);
                    if ($computed === null) {
                        $stats['skipped']++;
                        continue;
                    }
                    $sprice = (float) $computed['sprice'];

                    if (PushedListingPrice::same($row['price'] ?? 0, $sprice)) {
                        // Live PLS Price already equals the target. Keep the saved S PRC in step, no push.
                        if (! $dryRun && ! PushedListingPrice::same($row['sprice'] ?? 0, $sprice)) {
                            $this->saveSprice($row, $computed, 'pushed');
                            $stats['applied']++;
                        }
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    if ($blueCatchUp) {
                        // Headless "Push on reload": SKUs whose PLS Price ≠ S PRC. The twice-daily run skips a SKU
                        // whose last pushed price already equals the target, so a Price that stayed different
                        // afterwards (the store reverted it, or the Price column is stale) is picked up here.
                        if ($maxPushes > 0 && $stats['pushed'] + $stats['push_failed'] >= $maxPushes) {
                            $this->log($logger, "Reached max pushes ({$maxPushes}) — rest next run");
                            break;
                        }
                        if (! empty($row['push_error_at']) && $this->withinCooldown((string) $row['push_error_at'], $errorCooldownMinutes)) {
                            // Shopify rejected the last push — retry after the cooldown, not every run.
                            $stats['skipped']++;
                            continue;
                        }
                        if ($this->withinCooldown((string) ($row['pushed_at'] ?? ''), $cooldownMinutes)) {
                            // Pushed recently and Price still differs: do not loop on it every 10 minutes.
                            $stats['skipped_unchanged']++;
                            continue;
                        }
                    } elseif (! $pushAll && $this->isUnchanged($row, $computed)) {
                        // Last push already equals the target. Refresh the live PLS Price column, no push.
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

                    if (NegativeSnroiPushGuard::shouldSkip('pls', (string) $row['sku'], $sprice)) {
                        $stats['skipped']++;
                        $this->log($logger, 'Skip '.$row['sku'].': negative SNROI');
                        continue;
                    }

                    if ($blueCatchUp) {
                        // Pull the live PLS price first: the store may already be right and only the Price column
                        // stale. A matching live price is written, no push.
                        $pull = $this->pullLive((string) $row['sku'], $sprice);
                        if ($sleepMs > 0) {
                            usleep($sleepMs * 1000);
                        }
                        if ($pull && ! empty($pull['success']) && PushedListingPrice::same($pull['price'] ?? 0, $sprice)) {
                            $stats['price_repaired']++;
                            $stats['skipped_unchanged']++;
                            $this->saveSprice($row, $computed, 'pushed');
                            $this->setPlsStatus((string) $row['sku'], 'pushed');
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
                    Log::error('[PlsSprcDilAutoPush] exception', [
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
                ($dryRun || $skipPush) ? ' [no PLS push]' : ''
            ));

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Page S PRC formula (INV > 0 child SKUs): Std rule + Min NPFT (Ship, no ads), Std is the maximum.
     * PLS has no LMP, ads or Amz rule.
     *
     * @param  array<string, mixed>  $row
     * @return array{sprice:float,uncapped:float,base:float,margin:float,lp:float,ship:float}|null
     */
    public function computeTarget(array $row, ?StdPrcVsDilPricer $pricer = null): ?array
    {
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0)) {
            return null;
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
        // Age / Dil / B / 0 Sold / CVR / Rev discounts, then Min NPFT (clearance exempt, Ship included, no ads).
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
        $capped = AmazonDilGroiRule::capToStdWhenNoLmp($effective, 0.0, $std);

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

            $plsStatus = strtolower((string) ($r['pls_status'] ?? ''));
            $plsStatusAt = $r['pls_status_at'] ?? null;

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
                'pushed_value' => isset($r['sprice_pushed_value']) && is_numeric($r['sprice_pushed_value'])
                    ? (float) $r['sprice_pushed_value']
                    : null,
                // Last successful push time. Kept when a later push failed, so a recent push is not repeated early.
                'pushed_at' => $r['sprice_pushed_at'] ?? ($plsStatus === 'pushed' ? $plsStatusAt : null),
                'push_error_at' => $plsStatus === 'error' ? $plsStatusAt : null,
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
     * Push one SKU through CvrMasterController::pushPriceToAmazon (marketplace pls). That is the same call the
     * old page push and the old runner used: it sets the ProLightSounds Shopify variant price and verifies it.
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
            // Shopify returned the new price and it matched, so the Price column moves to the pushed price.
            try {
                ChannelLivePriceSync::writeLive('pls', $sku, $price);
            } catch (Throwable $e) {
                Log::warning('[PlsSprcDilAutoPush] price column write failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            }
            $this->savePushMeta($sku, 'pushed', $price);
            $this->setPlsStatus($sku, 'pushed');

            return;
        }

        $stats['push_failed']++;
        $err = (string) ($result['message'] ?? 'PLS price push failed');
        $stats['errors'][] = $sku.': '.$err;
        $this->log($logger, 'Push failed '.$sku.': '.$err);
        Log::error('PLS Sprc Dil push failed', ['sku' => $sku, 'price' => $price, 'error' => $err]);
        $this->savePushMeta($sku, 'error', null);
        $this->setPlsStatus($sku, 'error');
    }

    /**
     * Live ProLightSounds variant price. With an expected price the Price column only moves when the store
     * shows it; without one the live price is written as is.
     *
     * @return array{success?:bool,price?:?float,message?:string}|null
     */
    protected function pullLive(string $sku, ?float $expected = null): ?array
    {
        try {
            return app(ChannelPushedPricePullService::class)
                ->pullSkus('pls', [$sku], $expected !== null ? [strtoupper(trim($sku)) => $expected] : [])[0] ?? null;
        } catch (Throwable $e) {
            Log::warning('[PlsSprcDilAutoPush] live price pull failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array{success:bool,message?:string} */
    protected function pushPrice(string $sku, float $price): array
    {
        try {
            $res = app(CvrMasterController::class)->pushPriceToAmazon(Request::create('/cvr-master-push-price', 'POST', [
                'sku' => strtoupper(trim($sku)),
                'price' => $price,
                'marketplace' => 'pls',
            ]));
            $payload = method_exists($res, 'getData') ? $res->getData(true) : [];
            $status = method_exists($res, 'getStatusCode') ? $res->getStatusCode() : 200;
            $payload = is_array($payload) ? $payload : [];
            $ok = $status < 400
                && ! (isset($payload['success']) && $payload['success'] === false)
                && empty($payload['errors']);
            if ($ok) {
                return ['success' => true];
            }

            return [
                'success' => false,
                'message' => (string) (($payload['errors'][0]['message'] ?? null) ?: ($payload['message'] ?? 'PLS price push failed')),
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Status, time and pushed value in pls_data_views (the keys the page and cooldown read). */
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
            Log::error('[PlsSprcDilAutoPush] status save failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    /** The Push column on /pls-pricing reads PLS_STATUS from amazon_data_views. Keep it in step. */
    protected function setPlsStatus(string $sku, string $status): void
    {
        try {
            // One indexed lookup per pushed SKU. amazon_data_views is large, so it is never loaded whole.
            $view = AmazonDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first()
                ?? new AmazonDataView(['sku' => strtoupper(trim($sku))]);
            $existing = $this->decodeValue($view->value);
            $existing['PLS_STATUS'] = $status;
            $existing['PLS_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            $view->value = $existing;
            $view->save();
        } catch (Throwable $e) {
            Log::error('[PlsSprcDilAutoPush] PLS_STATUS save failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
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
