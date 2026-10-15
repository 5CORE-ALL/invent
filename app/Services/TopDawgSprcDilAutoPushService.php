<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\TopDawgPricingController;
use App\Models\ChannelMasterCalculatedData;
use App\Models\TopDawgDataView;
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
 * TopDawg Std prc vs dil stack → SPRICE → TopDawg price API (page not required).
 *
 * Same steps as AmazonSprcDilAutoPushService / ReverbSprcDilAutoPushService, with TopDawg as the source:
 *  S PRC = Std Prc × (1 − (Age Disc + Dil Disc + B Disc + 0 Sold Disc + CVR Disc + Rev Disc) / 100)
 *  Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 *  Std Prc is the maximum. No Amz floor and no LMP cap.
 *  TopDawg profit has no Ship (same as /topdawg/sales-dashboard).
 *
 * Same flow as Amazon, except Ship (TopDawg profit has none) and LMP (no LMP cap, only Std caps):
 *  - Twice-daily run: skips a SKU whose last pushed price already equals the target (and pulls the live
 *    TD Price so the Price column is not stale).
 *  - Blue catch-up run (topdawg:push-blue-prices, every 10 min): SKUs whose TD Price ≠ S PRC are checked,
 *    the live price is pulled first, and the S PRC is pushed only where TopDawg is still different.
 *  - Never pushes a price whose SNROI is below 0 (NegativeSnroiPushGuard). The S PRC is still saved.
 *  - Ads % comes from Channel Master (same source as Amazon).
 *
 * TopDawg does not list a pushed price right away: a 200 means "queued for review" and TD Price follows
 * once TopDawg approves (1–24h). Re-submitting the same price while it is in review can restart that review,
 * so the blue catch-up waits for the cooldown (default 24h, --cooldown) before re-pushing the same price.
 *
 * Rows come from TopDawgPricingController::getViewTopDawgTabularData(), the same rows /topdawg-pricing shows.
 */
class TopDawgSprcDilAutoPushService
{
    public const LOCK_KEY = 'topdawg-sprc-dil-auto-push-run';

    /** Minutes the pushed price stays in the Price column while TopDawg reviews it (same as the blue-run cooldown). */
    public const REVIEW_HOLD_MINUTES = 1440;

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
        int $cooldownMinutes = 1440,
        int $errorCooldownMinutes = 120,
        int $maxPushes = 0
    ): array {
        $pricer = StdPrcVsDilPricer::forChannel('topdawg');
        $adsPct = $this->channelAdsPercent();

        $this->log($logger, 'Loaded TopDawg Std prc vs dil (topdawg_std_prc_vs_dil) → Age/Dil/B/0 Sold/CVR/Rev Disc, Ads%='.$adsPct);

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
            $this->log($logger, 'Skipped: another TopDawg Sprc Dil push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0");

        $canPush = ! $dryRun && ! $skipPush;
        if ($canPush && ! $this->apiConfigured()) {
            $stats['errors'][] = 'TOPDAWG_API_TOKEN is missing in .env';
            $this->log($logger, '✗ TOPDAWG_API_TOKEN is missing — saving S PRC only, no push');
            $canPush = false;
        }

        $total = count($rows);

        try {
            foreach ($rows as $i => $row) {
                try {
                    $row['ads_pct'] = $adsPct;
                    $computed = $this->computeTarget($row, $pricer);
                    if ($computed === null) {
                        $stats['skipped']++;
                        continue;
                    }
                    $sprice = (float) $computed['sprice'];

                    if (PushedListingPrice::same($row['price'] ?? 0, $sprice)) {
                        // Live TD Price already equals the target. Keep the saved S PRC in step, no push.
                        if (! $dryRun && ! PushedListingPrice::same($row['sprice'] ?? 0, $sprice)) {
                            $this->saveSprice((string) $row['sku'], $computed, 'pushed');
                            $stats['applied']++;
                        }
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    if ($blueCatchUp) {
                        // Headless "Push on reload": SKUs whose TD Price ≠ S PRC. The twice-daily run skips a SKU
                        // whose last pushed price already equals the target, so a Price that stayed different
                        // afterwards (review rejected, or the site reverted) is picked up here.
                        if ($maxPushes > 0 && $stats['pushed'] + $stats['push_failed'] >= $maxPushes) {
                            $this->log($logger, "Reached max pushes ({$maxPushes}) — rest next run");
                            break;
                        }
                        if (! empty($row['push_error_at']) && $this->withinCooldown((string) $row['push_error_at'], $errorCooldownMinutes)) {
                            // TopDawg rejected the last push — retry after the cooldown, not every run.
                            $stats['skipped']++;
                            continue;
                        }
                        if ($this->withinCooldown((string) ($row['pushed_at'] ?? ''), $cooldownMinutes)) {
                            // A push is still in TopDawg review (1–24h). Re-submitting, even at a changed price,
                            // can restart that review. A changed target is picked up by the twice-daily run.
                            $stats['skipped_unchanged']++;
                            continue;
                        }
                        if ($this->isUnchanged($row, $computed) && ! $dryRun && $canPush) {
                            // Review window is over. Pull the live price first: TopDawg may already be right
                            // and only the Price column stale.
                            $pull = $this->pullLive((string) $row['sku'], $sprice);
                            if ($sleepMs > 0) {
                                usleep($sleepMs * 1000);
                            }
                            if ($pull && ! empty($pull['success'])
                                && PushedListingPrice::same($pull['price'] ?? 0, $sprice)) {
                                $stats['price_repaired']++;
                                $stats['skipped_unchanged']++;
                                continue;
                            }
                        }
                    } elseif (! $pushAll && $this->isUnchanged($row, $computed)) {
                        // Last push already equals the target. Refresh the live TD Price column, no push.
                        if (! $dryRun && $canPush) {
                            $pull = $this->pullLive((string) $row['sku'], $sprice);
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
                        $this->saveSprice((string) $row['sku'], $computed, 'applied');
                    }
                    $stats['applied']++;

                    if (! $canPush) {
                        continue;
                    }

                    if (NegativeSnroiPushGuard::shouldSkip('topdawg', (string) $row['sku'], $sprice)) {
                        $stats['skipped']++;
                        $this->log($logger, 'Skip '.$row['sku'].': negative SNROI');
                        continue;
                    }

                    $this->pushOne((string) $row['sku'], $computed, $stats, $logger);
                    if ($sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }
                } catch (Throwable $e) {
                    $stats['push_failed']++;
                    $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                    Log::error('[TopDawgSprcDilAutoPush] exception', [
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
                ($dryRun || $skipPush) ? ' [no TopDawg push]' : ''
            ));

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Page S PRC formula (INV > 0 child SKUs): Std rule, Std is the maximum (no LMP cap, no Amz floor).
     * TopDawg profit has no Ship. Ads % is the Channel Master value (row key ads_pct).
     *
     * @param  array<string, mixed>  $row
     * @return array{sprice:float,uncapped:float,amz_capped:bool,base:float,margin:float,lp:float,ads:float}|null
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
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 0.95;
        }
        $adsPct = (float) ($row['ads_pct'] ?? 0);

        $pricer ??= StdPrcVsDilPricer::forChannel('topdawg');
        // Age / Dil / B / 0 Sold / CVR / Rev discounts, then clearance SKUs keep the discount price.
        $pricerRow = [
            'sku' => (string) ($row['sku'] ?? ''),
            'inv' => $inv,
            'dil' => (float) ($row['dil'] ?? 0),
            'std_price' => $std,
            'age_days' => $row['age_days'] ?? null,
            'cvr' => (float) ($row['cvr'] ?? 0),
            'reviews' => 0.0,
            'sold' => (float) ($row['td_l30'] ?? 0),
            'lp' => $lp,
            'ship' => 0.0,
            'margin' => $margin,
            'ads' => $adsPct > 0 ? $adsPct / 100 : 0.0,
        ];
        if (array_key_exists('clearance', $row)) {
            $pricerRow['clearance'] = $row['clearance'];
        }
        $effective = $pricer->priceFromRow($pricerRow);
        if ($effective === null || ! ($effective > 0)) {
            return null;
        }

        // No LMP cap and no Amazon floor. Std Prc is the maximum.
        $uncapped = AmazonDilGroiRule::capToStdWhenNoLmp($effective, 0.0, $std);

        if (! is_finite($uncapped) || $uncapped < 0.01) {
            return null;
        }

        return [
            'sprice' => round($uncapped, 2),
            'uncapped' => round($uncapped, 2),
            'amz_capped' => false,
            'base' => round($std, 2),
            'margin' => $margin,
            'lp' => $lp,
            'ads' => $adsPct,
        ];
    }

    /**
     * Same rows as /topdawg-pricing. Listed on TopDawg, INV > 0, child SKUs only.
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
            Log::warning('[TopDawgSprcDilAutoPush] age days lookup failed', ['error' => $e->getMessage()]);
        }

        $res = app(TopDawgPricingController::class)->getViewTopDawgTabularData(new Request());
        $payload = method_exists($res, 'getData') ? $res->getData(true) : [];
        $pageRows = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($pageRows)) {
            throw new \RuntimeException('TopDawg pricing data load failed');
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
            $price = (float) ($r['TD Price'] ?? 0);
            if (! ($inv > 0) || ! ($price > 0)) {
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
                'td_l30' => (float) ($r['TD L30'] ?? 0),
                'cvr' => 0.0,
                'lp' => (float) ($r['LP_productmaster'] ?? 0),
                'margin' => (float) ($r['percentage'] ?? 0),
                'age_days' => $ageDays->ageDays($sku, $inv, $agePushBySku, $ageLastSaleBySku),
                'standard_price' => (float) ($r['STANDARD_PRICE'] ?? 0),
                'sprice' => (float) ($r['SPRICE'] ?? 0),
                'pushed_value' => isset($r['SPRICE_PUSHED_VALUE']) && is_numeric($r['SPRICE_PUSHED_VALUE'])
                    ? (float) $r['SPRICE_PUSHED_VALUE']
                    : null,
                // Last successful push time. Kept even when a later push failed, so a SKU that is still in
                // TopDawg review is never re-submitted early.
                'pushed_at' => $r['SPRICE_PUSHED_AT'] ?? ($status === 'pushed' ? $statusAt : null),
                'push_error_at' => in_array($status, ['failed', 'error'], true) ? $statusAt : null,
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
     * Save S PRC the way the page does (topdawg_data_views.value: sprice + SPRICE_STATUS + PUSH_STATUS).
     * SGPFT / SROI are recomputed from sprice when the page loads.
     *
     * @param  array{sprice:float,uncapped?:float,amz_capped?:bool}  $computed
     */
    protected function saveSprice(string $sku, array $computed, string $status = 'applied'): void
    {
        $view = $this->findView($sku);
        $existing = $this->decodeValue($view->value);

        $sprice = round((float) $computed['sprice'], 2);
        $now = now()->toDateTimeString();

        $existing['sprice'] = $sprice;
        $existing['SPRICE'] = $sprice;
        $existing['AMZ_CAPPED'] = (bool) ($computed['amz_capped'] ?? false);
        $existing['SPRICE_UNCAPPED'] = round((float) ($computed['uncapped'] ?? $sprice), 2);
        $existing['SPRICE_STATUS'] = $status;
        $existing['SPRICE_STATUS_UPDATED_AT'] = $now;
        if ($status === 'pushed') {
            $existing['PUSH_STATUS'] = 'pushed';
            $existing['PUSH_STATUS_UPDATED_AT'] = $now;
            $existing['SPRICE_PUSHED_VALUE'] = $sprice;
        } else {
            $existing['PUSH_STATUS'] = null;
        }

        $view->value = $existing;
        $view->save();
    }

    /**
     * Push one SKU through TopDawgApiService::pushPrice (same call as the old page push), then stamp the result.
     * A 200 means TopDawg queued it for review; TD Price follows after approval.
     *
     * @param  array{sprice:float}  $computed
     * @param  array<string, mixed>  $stats
     * @param  callable(string): void|null  $logger
     */
    protected function pushOne(string $sku, array $computed, array &$stats, ?callable $logger): void
    {
        $price = round((float) $computed['sprice'], 2);
        $result = $this->pushPrice($sku, $price);

        if (! empty($result['ok'])) {
            $stats['pushed']++;
            $this->savePushMeta($sku, 'pushed', $price);
            try {
                // Stamps the saved S PRC and writes it into TD Price (kept for the review window).
                ChannelLivePriceSync::confirmAfterPush('topdawg', $sku, $price);
            } catch (Throwable $e) {
                Log::warning('[TopDawgSprcDilAutoPush] confirmAfterPush failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            }
            // Live pull (same as Amazon): inside the review window it keeps the pushed price in TD Price.
            $this->pullLive($sku, $price);

            return;
        }

        $stats['push_failed']++;
        $resp = $result['response'] ?? null;
        $err = is_array($resp)
            ? (string) ($resp['message'] ?? ($resp['error'] ?? json_encode($resp)))
            : (string) ($resp ?? 'TopDawg push failed');
        $stats['errors'][] = $sku.': '.$err;
        $this->log($logger, 'Push failed '.$sku.': '.$err);
        Log::error('TopDawg Sprc Dil push failed', ['sku' => $sku, 'price' => $price, 'error' => $err]);
        $this->savePushMeta($sku, 'failed', null);
    }

    /**
     * Live TD Price from SupplierProduct/list. The pull also writes the live site cost into the Price column.
     *
     * @return array{success?:bool,price?:?float}|null
     */
    protected function pullLive(string $sku, float $expected): ?array
    {
        try {
            return app(ChannelPushedPricePullService::class)
                ->pullSkus('topdawg', [$sku], [strtoupper(trim($sku)) => $expected])[0] ?? null;
        } catch (Throwable $e) {
            Log::warning('[TopDawgSprcDilAutoPush] live price pull failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Channel Master TopDawg Ads% (same source Amazon uses for its SNROI). */
    protected function channelAdsPercent(): float
    {
        try {
            return (float) (ChannelMasterCalculatedData::query()->where('channel', 'TopDawg')->value('ads_percentage')
                ?? ChannelMasterCalculatedData::query()->where('channel', 'like', 'TopDawg%')->value('ads_percentage')
                ?? 0);
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    protected function apiConfigured(): bool
    {
        try {
            return app(TopDawgApiService::class)->isConfigured();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array{ok?:bool,status?:int,response?:mixed} */
    protected function pushPrice(string $sku, float $price): array
    {
        try {
            return app(TopDawgApiService::class)->pushPrice($sku, $price);
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 0, 'response' => $e->getMessage()];
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
            $existing['PUSH_STATUS'] = $status;
            $existing['SPRICE_STATUS_UPDATED_AT'] = $now;
            $existing['PUSH_STATUS_UPDATED_AT'] = $now;
            if ($status === 'pushed' && $price !== null && $price > 0) {
                $existing['SPRICE_PUSHED_VALUE'] = $price;
                $existing['SPRICE_PUSHED_AT'] = $now;
                $existing['SPRICE_PUSHED_BY'] = 'Server (Sprc Dil auto push)';
            }
            $view->value = $existing;
            $view->save();
        } catch (Throwable $e) {
            Log::error('[TopDawgSprcDilAutoPush] status save failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function findView(string $sku): TopDawgDataView
    {
        $key = strtoupper(trim($sku));

        return TopDawgDataView::whereRaw('UPPER(TRIM(sku)) = ?', [$key])->first()
            ?? new TopDawgDataView(['sku' => trim($sku)]);
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

