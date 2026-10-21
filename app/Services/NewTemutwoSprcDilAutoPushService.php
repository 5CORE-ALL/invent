<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\NewTemutwoController;
use App\Http\Controllers\MarketPlace\TemuController;
use App\Services\Support\ChannelPushSpriceRunner;
use App\Services\Support\NewTemutwoSuggestedPriceStore;
use App\Support\AmazonDilGroiRule;
use App\Support\StdPrcVsDilPricer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * New Temu Two Std prc vs dil stack → S PRC (NT2_SPRICE) → Temu 2 base price API (page not required).
 *
 * Same steps as AmazonSprcDilAutoPushService, with New Temu Two as the source:
 *  S PRC = Std Prc × (1 − (Age Disc + Dil Disc + B Disc + 0 Sold Disc + CVR Disc + Rev Disc) / 100)
 *  Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 *  No Amazon / eBay cap (same as New Temu One).
 *  Then LMP cap when LMP is lower and SGROI at LMP ≥ 20%. Std Prc is the maximum.
 *  S PRC is the full Temu price. Temu takes a Base price, so the push sends the S Base Prc (same invert as the page).
 *  Skip when the live Base already equals the target Base. The 0.95 take-home on S R Price is the page's SGROI.
 *
 * Rows come from NewTemutwoController::dataJson(), the same rows /new-temu2 shows
 * (INV, OV L30, LP, Temu Ship, margin, LMP, Std Prc, live base_price, saved S PRC).
 * No SGROI-stop guard and no negative-SNROI guard, same as Amazon.
 */
class NewTemutwoSprcDilAutoPushService
{
    public const LOCK_KEY = 'newtemutwo-sprc-dil-auto-push-run';

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
        int $cooldownMinutes = 600
    ): array {
        $pricer = StdPrcVsDilPricer::forChannel('newtemutwo');

        $this->log($logger, 'Loaded New Temu Two Std prc vs dil (newtemutwo_std_prc_vs_dil) → Age/Dil/B/0 Sold/CVR/Rev Disc + LMP cap');

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

        $lock = Cache::lock(self::LOCK_KEY, 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another New Temu Two Sprc Dil push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0 listed on Temu 2");

        $canPush = ! $dryRun && ! $skipPush;
        if ($canPush && NewTemutwoController::autoPushPaused()) {
            // Pause button on /new-temu2: keep saving S PRC, but push nothing to Temu.
            $stats['errors'][] = 'auto-push paused';
            $this->log($logger, '⏸ Auto-push is paused on /new-temu2 — saving S PRC only, no push');
            $canPush = false;
        }
        if ($canPush && ! ChannelPushSpriceRunner::livePushAllowed()) {
            $stats['errors'][] = 'live push disabled in local (CHANNEL_PUSH_SPRICE_ALLOW_LOCAL)';
            $this->log($logger, '✗ Live push is disabled on this environment — saving S PRC only, no push');
            $canPush = false;
        }

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
                    $base = (float) $computed['base'];
                    $sku = (string) $row['sku'];

                    $savedSame = $this->same($row['sprice'] ?? 0, $sprice);

                    if ($this->same($row['live_base'] ?? 0, $base)) {
                        // Live Base already equals the target. Keep the saved S PRC in step, no push.
                        if (! $dryRun && ! $savedSame) {
                            $this->saveSprice($sku, $computed, $row);
                            $stats['applied']++;
                        }
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    if (! $pushAll) {
                        if (($row['push_status'] ?? '') === 'error'
                            && $this->withinCooldown((string) ($row['push_error_at'] ?? ''), $cooldownMinutes)) {
                            // Temu rejected the last push — retry after the cooldown, not every run.
                            $stats['skipped']++;
                            continue;
                        }
                        if ($this->same($row['pushed_base'] ?? 0, $base)
                            && $this->withinCooldown((string) ($row['pushed_at'] ?? ''), $cooldownMinutes)) {
                            // Already pushed this Base. Temu applies it slowly, so wait before pushing it again.
                            if (! $dryRun && ! $savedSame) {
                                $this->saveSprice($sku, $computed, $row);
                                $stats['applied']++;
                            }
                            $stats['skipped_unchanged']++;
                            continue;
                        }
                    }

                    if (! $dryRun && ! $savedSame) {
                        $this->saveSprice($sku, $computed, $row);
                    }
                    $stats['applied']++;

                    if (! $canPush) {
                        continue;
                    }

                    $error = $this->pushBase($sku, $base);
                    if ($error === null) {
                        $stats['pushed']++;
                        NewTemutwoSuggestedPriceStore::markPushed($sku, $base, (float) $computed['full']);
                    } else {
                        $stats['push_failed']++;
                        $stats['errors'][] = $sku.': '.$error;
                        $this->log($logger, 'Push failed '.$sku.': '.$error);
                        Log::error('New Temu Two Sprc Dil push failed', ['sku' => $sku, 'base' => $base, 'error' => $error]);
                        NewTemutwoSuggestedPriceStore::markPushError($sku);
                    }
                    if ($sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }
                } catch (Throwable $e) {
                    $stats['push_failed']++;
                    $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                    Log::error('[NewTemutwoSprcDilAutoPush] exception', [
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
                ($dryRun || $skipPush) ? ' [no Temu push]' : ''
            ));

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Page S PRC formula (INV > 0 child SKUs): Std rule, then LMP cap, Std is the maximum.
     *
     * @param  array<string, mixed>  $row  Loaded candidate (see loadCandidates)
     * @return array{sprice:float,uncapped:float,lmp_capped:bool,labels:list<string>,base:float,full:float}|null
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
            $margin = TemuShopifySalesService::DECREASE_TAKEHOME;
        }
        $lmp = (float) ($row['lmp'] ?? 0);
        $adsPct = (float) ($row['ads_percent'] ?? 0);

        $pricer ??= StdPrcVsDilPricer::forChannel('newtemutwo');
        // Age / Dil / B / 0 Sold / CVR / Rev discounts, then clearance SKUs keep the discount price.
        $pricerRow = [
            'sku' => (string) ($row['sku'] ?? ''),
            'inv' => $inv,
            'dil' => (float) ($row['dil'] ?? 0),
            'std_price' => $std,
            'age_days' => $row['age_days'] ?? null,
            'cvr' => (float) ($row['cvr'] ?? 0),
            'cvr_60' => (float) ($row['cvr_60'] ?? 0),
            'reviews' => 0.0,
            // Same 0 Sold test as the page: OV L30 (field L30).
            'sold' => (float) ($row['ov_l30'] ?? 0),
            'lp' => $lp,
            'ship' => $ship,
            'margin' => $margin,
            // The pricer reads Ads as a fraction (2.2% → 0.022).
            'ads' => $adsPct > 0 ? $adsPct / 100 : 0.0,
            // LMP Disc applies only when this SKU has an LMP. Prefer the landed price the page shows.
            'lmp' => (float) (($row['lmp_raw'] ?? 0) > 0 ? $row['lmp_raw'] : ($row['lmp'] ?? 0)),
        ];
        if (array_key_exists('clearance', $row)) {
            $pricerRow['clearance'] = $row['clearance'];
        }
        // S PRC = Promo Price, and it must equal the page's "Promo Base Prc" column
        // (Price After Discount − Ship Disc), so the column and the push always agree.
        //   12% $               = Temu Price × 12%, where Temu Price = full price from the live base
        //   Disc $              = Std × Sum disc % (the pricer gives Std × (1 − Sum disc %))
        //   Price After Discount = Std − 12% $ − Disc $
        //   Ship Disc           = $2.99 when Price After Discount ≤ $30, else $0
        //   Promo Base Prc      = Price After Discount − Ship Disc
        $discounted = $pricer->priceFromRow($pricerRow);
        if ($discounted === null || ! ($discounted > 0)) {
            return null;
        }
        $liveBase = (float) ($row['live_base'] ?? 0);
        $temuPrice = $liveBase > 0 ? TemuShopifySalesService::computeFullTemuPrice($liveBase) : 0.0;
        $twelvePct = round($temuPrice * 0.12, 2);
        $discAmt = max(0.0, round($std - $discounted, 2));
        $priceAfterDisc = round($std - $twelvePct - $discAmt, 2);
        $shipDisc = $priceAfterDisc <= 30 ? 2.99 : 0.0;
        $effective = round($priceAfterDisc - $shipDisc, 2);
        if (! ($effective > 0)) {
            return null;
        }

        // No Amazon / eBay cap (same as New Temu One). Only the LMP cap below applies.
        $labels = [];
        $afterRefs = round($effective, 2);

        $capped = $this->capToLmp($afterRefs, $lmp, $lp, $ship, $std);
        $lmpCapped = $lmp > 0
            && ($afterRefs - $capped) > 0.009
            && abs($capped - round($lmp, 2)) <= 0.02;
        if ($lmpCapped) {
            $labels = [];
        }

        if (! is_finite($capped) || $capped < 0.01) {
            return null;
        }
        $sprice = round($capped, 2);
        // S PRC is already the Temu base (Promo Base Prc), so it is pushed as-is — no further 0.88 / −$2.99 step.
        $base = $sprice;
        $promoBase = $sprice;
        if (! ($base > 0)) {
            return null;
        }

        return [
            'sprice' => $sprice,
            'uncapped' => round($effective, 2),
            'lmp_capped' => $lmpCapped,
            'labels' => $labels,
            'base' => round($base, 2),
            'promo_base' => round($promoBase, 2),
            'full' => round(TemuShopifySalesService::computeFullTemuPrice($base), 2),
        ];
    }

    /**
     * Amazon LMP cap with the Temu SGROI (S R Price × 0.95, same as the page):
     *  S PRC at/above LMP and SGROI at LMP ≥ 20% → S PRC = LMP. Otherwise keep S PRC.
     *  Std Prc is always the maximum. Never raises S PRC.
     */
    public function capToLmp(float $sprice, float $lmp, float $lp, float $ship, float $std): float
    {
        $s = round($sprice, 2);
        if (! ($s > 0)) {
            return $s;
        }
        if ($lmp > 0 && ($s + 0.0001) >= $lmp) {
            $sgroiAtLmp = TemuShopifySalesService::newTemuoneSgroiAtSprice($lmp, $lp, $ship);
            if ($sgroiAtLmp === null || $sgroiAtLmp >= AmazonDilGroiRule::LMP_SGROI_MIN) {
                $s = round($lmp, 2);
            }
        }

        return AmazonDilGroiRule::capToStdWhenNoLmp($s, $lmp, $std);
    }

    /**
     * Same rows as /new-temu2. Listed on Temu 2 (live Base > 0), INV > 0, child SKUs only.
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
            Log::warning('[NewTemutwoSprcDilAutoPush] age days lookup failed', ['error' => $e->getMessage()]);
        }

        $res = app(NewTemutwoController::class)->dataJson();
        $pageRows = method_exists($res, 'getData') ? $res->getData(true) : [];
        if (! is_array($pageRows) || ($pageRows !== [] && isset($pageRows['error']))) {
            throw new \RuntimeException('New Temu Two data load failed');
        }
        $adsPercent = 0.0;
        try {
            $adsPercent = (float) app(NewTemutwoController::class)->channelAdsPercent();
        } catch (Throwable $e) {
            Log::warning('[NewTemutwoSprcDilAutoPush] ads% lookup failed', ['error' => $e->getMessage()]);
        }

        $out = [];
        $seen = [];
        foreach ($pageRows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $sku = trim((string) ($r['sku'] ?? $r['(Child) sku'] ?? ''));
            $key = strtoupper($sku);
            if ($sku === '' || isset($seen[$key]) || str_contains($key, 'PARENT')) {
                continue;
            }
            if ($want !== null && ! isset($want[$key])) {
                continue;
            }
            $inv = (float) ($r['INV'] ?? $r['inventory'] ?? 0);
            $liveBase = (float) ($r['base_price'] ?? 0);
            if (! ($inv > 0) || ! ($liveBase > 0)) {
                continue;
            }
            $seen[$key] = true;

            $out[] = [
                'sku' => $sku,
                'inv' => $inv,
                'live_base' => $liveBase,
                // Page Dil: OV L30 ÷ INV.
                'dil' => (float) ($r['Dil%'] ?? 0),
                'ov_l30' => (float) ($r['L30'] ?? 0),
                'cvr' => (float) ($r['cvr_percent'] ?? 0),
                'cvr_60' => (float) ($r['cvr_60'] ?? 0),
                'lp' => (float) ($r['lp'] ?? 0),
                'ship' => (float) ($r['temu_ship'] ?? 0),
                'margin' => (float) ($r['percentage'] ?? 0),
                'ads_percent' => $adsPercent,
                'lmp' => (float) ($r['lmp_raw'] ?? 0),
                'age_days' => $ageDays->ageDays($sku, $inv, $agePushBySku, $ageLastSaleBySku),
                'standard_price' => (float) ($r['STANDARD_PRICE'] ?? 0),
                'sprice' => (float) ($r['sprice'] ?? 0),
                'pushed_base' => isset($r['nto_pushed_base']) && is_numeric($r['nto_pushed_base'])
                    ? (float) $r['nto_pushed_base']
                    : null,
                'pushed_at' => $r['nto_pushed_at'] ?? null,
                'push_status' => $r['nto_push_status'] ?? null,
                'push_error_at' => $r['nto_push_error_at'] ?? null,
            ];

            if ($limit !== null && $limit > 0 && count($out) >= $limit) {
                break;
            }
        }

        usort($out, static fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

        return $out;
    }

    protected function same(mixed $a, mixed $b): bool
    {
        $x = is_numeric($a) ? round((float) $a, 2) : 0.0;
        $y = is_numeric($b) ? round((float) $b, 2) : 0.0;

        return $x > 0 && $y > 0 && abs($x - $y) < 0.005;
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
     * Save S PRC (NT2_SPRICE) so /new-temu2 shows the same dollar the server will push.
     *
     * @param  array{sprice:float,uncapped:float,lmp_capped:bool}  $computed
     * @param  array<string, mixed>  $row
     */
    protected function saveSprice(string $sku, array $computed, array $row): void
    {
        try {
            $store = new NewTemutwoSuggestedPriceStore();
            $store->loadForSkus([$sku]);
            $store->writeExactSprice(
                $sku,
                (float) $computed['sprice'],
                (float) ($row['lp'] ?? 0),
                (float) ($row['ship'] ?? 0),
                (float) $computed['uncapped'],
                ! empty($computed['lmp_capped']) ? ['LMP'] : array_values($computed['labels'] ?? []),
                ! empty($computed['lmp_capped'])
            );
            $store->flush();
        } catch (Throwable $e) {
            Log::error('[NewTemutwoSprcDilAutoPush] save S PRC failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Push one S Base Prc through the same endpoint as the page push (Temu 2 base price API).
     * No SGROI-stop guard. Returns null on success, else the error text.
     */
    protected function pushBase(string $sku, float $base): ?string
    {
        try {
            $req = Request::create('/temu2/push-price', 'POST', [
                'sku' => $sku,
                'price' => $base,
                'skip_local_base' => 1,
            ]);
            $req->headers->set('Accept', 'application/json');
            $res = app(TemuController::class)->pushTemu2Price($req, app(Temu2ApiService::class));
            $payload = method_exists($res, 'getData') ? $res->getData(true) : [];
            $status = method_exists($res, 'getStatusCode') ? $res->getStatusCode() : 200;
            if ($status >= 400 || (is_array($payload) && (
                (isset($payload['success']) && $payload['success'] === false)
                || ! empty($payload['errors'])
            ))) {
                return is_array($payload)
                    ? (string) (($payload['errors'][0]['message'] ?? null) ?: ($payload['message'] ?? 'Temu 2 push failed'))
                    : 'Temu 2 push failed';
            }

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /** @param  callable(string): void|null  $logger */
    protected function log(?callable $logger, string $msg): void
    {
        if ($logger) {
            $logger($msg);
        }
    }
}
