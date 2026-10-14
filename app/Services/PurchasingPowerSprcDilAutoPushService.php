<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\PurchasingPowerController;
use App\Models\PurchasingPowerDataView;
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
 * Purchasing Power Std prc vs dil stack → SPRICE → MCM price import (page not required).
 *
 * Same steps as AmazonSprcDilAutoPushService, with Purchasing Power as the source:
 *  S PRC = Std Prc × (1 − (Age Disc + Dil Disc + B Disc + 0 Sold Disc + CVR Disc + Rev Disc) / 100)
 *  Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 *  Then raised to Min NPFT % (saved in purchasing_power_std_prc_vs_dil, default 10). Clearance SKUs keep the discount price.
 *  Std Prc is the maximum, then the Amz rule: S PRC never sits below the Amazon price (A Price). Same rule
 *  the page labels "Amz" and MacysAmazonPriceCap applies to the push.
 *  Ship BB is part of profit (same as the page). Purchasing Power has no ads and no LMP, so neither is used.
 *  Never pushes a price whose SNROI is below 0 (NegativeSnroiPushGuard). The S PRC is still saved.
 *
 * Flow, same as Amazon:
 *  - Twice-daily run: skips a SKU whose last pushed price already equals the target.
 *  - Blue catch-up run (purchasing-power:push-blue-prices): SKUs whose PP Price ≠ S PRC are pushed again once the
 *    cooldown has passed. PP Price itself is refreshed by purchasing-power:sync (MCM OF21), so a pushed price shows
 *    in the Price column after the next sync, not right away.
 *
 * Rows come from PurchasingPowerController::getViewData(), the same rows /purchasing-power-pricing shows.
 */
class PurchasingPowerSprcDilAutoPushService
{
    public const LOCK_KEY = 'purchasing-power-sprc-dil-auto-push-run';

    public function __construct(private readonly PurchasingPowerApiService $ppApi) {}

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
        $pricer = StdPrcVsDilPricer::forChannel('purchasing_power');

        $this->log($logger, 'Loaded Purchasing Power Std prc vs dil (purchasing_power_std_prc_vs_dil) → Age/Dil/B/0 Sold/CVR/Rev Disc + Min NPFT + Amz rule');

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
            $this->log($logger, 'Skipped: another Purchasing Power Sprc Dil push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0 listed on Purchasing Power");

        $canPush = ! $dryRun && ! $skipPush;
        if ($canPush && ! $this->apiConfigured()) {
            $stats['errors'][] = 'Purchasing Power MCM API key/base URL is missing in .env';
            $this->log($logger, '✗ Purchasing Power MCM API is not configured — saving S PRC only, no push');
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

                    if (PushedListingPrice::same($row['price'] ?? 0, $sprice)) {
                        // Live PP Price already equals the target. Keep the saved S PRC in step, no push.
                        if (! $dryRun && (! PushedListingPrice::same($row['sprice'] ?? 0, $sprice) || ! empty($row['amz_capped']) !== ! empty($computed['amz_capped']))) {
                            $this->saveSprice($row, $computed, 'pushed');
                            $stats['applied']++;
                        }
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    if ($blueCatchUp) {
                        // Headless "Push on reload": SKUs whose PP Price ≠ S PRC. The twice-daily run skips a SKU
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
                        if ($this->withinCooldown((string) ($row['pushed_at'] ?? ''), $cooldownMinutes)) {
                            // Pushed recently. PP Price only follows after the next MCM sync, so give it time.
                            $stats['skipped_unchanged']++;
                            continue;
                        }
                    } elseif (! $pushAll && $this->isUnchanged($row, $computed)) {
                        // Last push already equals the target. The blue catch-up handles a Price that stayed different.
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

                    if (NegativeSnroiPushGuard::shouldSkip('purchasing_power', (string) $row['sku'], $sprice)) {
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
                    Log::error('[PurchasingPowerSprcDilAutoPush] exception', [
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
                ($dryRun || $skipPush) ? ' [no Purchasing Power push]' : ''
            ));

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Page S PRC formula (INV > 0 child SKUs): Std rule + Min NPFT, Std is the maximum, then the Amz rule
     * (never below A Price). No LMP cap and no ads on Purchasing Power.
     *
     * @param  array<string, mixed>  $row
     * @return array{sprice:float,uncapped:float,amz_capped:bool,base:float,margin:float,lp:float,ship:float}|null
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
            $margin = 0.65;
        }

        $pricer ??= StdPrcVsDilPricer::forChannel('purchasing_power');
        // Age / Dil / B / 0 Sold / CVR / Rev discounts, then Min NPFT (clearance exempt, Ship BB included).
        $pricerRow = [
            'sku' => (string) ($row['sku'] ?? ''),
            'inv' => $inv,
            'dil' => (float) ($row['dil'] ?? 0),
            'std_price' => $std,
            'age_days' => $row['age_days'] ?? null,
            'cvr' => 0.0,
            'reviews' => 0.0,
            // 0 Sold = PP L30 (MCM OR11 orders), same as the page.
            'pp_l30' => (float) ($row['pp_l30'] ?? 0),
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

        // Std Prc is the maximum.
        $uncapped = AmazonDilGroiRule::capToStdWhenNoLmp($effective, 0.0, $std);

        // Amz rule: S PRC is never below the Amazon price (A Price).
        $amz = round((float) ($row['amz'] ?? 0), 2);
        $amzCapped = $amz > 0 && $uncapped + 0.0001 < $amz;
        $final = $amzCapped ? $amz : $uncapped;

        // Min NPFT is on SNPFT (the S PRC's own NPFT), so it is the last word: it wins over the Std Prc
        // maximum and the Amz rule. Clearance SKUs are exempt (the pricer skips them).
        $floored = $pricer->enforceMinNpft($final, $pricerRow);
        $npftFloor = $floored > $final + 0.0001;
        if ($npftFloor) {
            $final = $floored;
            $amzCapped = $amz > 0 && abs($final - $amz) <= 0.005;
        }

        if (! is_finite($final) || $final < 0.01) {
            return null;
        }

        return [
            'sprice' => round($final, 2),
            'uncapped' => round($uncapped, 2),
            'amz_capped' => $amzCapped,
            'npft_floor' => $npftFloor,
            'base' => round($std, 2),
            'margin' => $margin,
            'lp' => $lp,
            'ship' => $ship,
        ];
    }

    /**
     * Same rows as /purchasing-power-pricing. Listed on Purchasing Power MCM, INV > 0, child SKUs only.
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
            Log::warning('[PurchasingPowerSprcDilAutoPush] age days lookup failed', ['error' => $e->getMessage()]);
        }

        $res = app(PurchasingPowerController::class)->getViewData(new Request());
        $payload = method_exists($res, 'getData') ? $res->getData(true) : [];
        $pageRows = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($pageRows)) {
            throw new \RuntimeException('Purchasing Power pricing data load failed');
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
            $price = (float) ($r['PP Price'] ?? 0);
            if (! ($inv > 0) || ! ($price > 0) || ! empty($r['is_pp_inactive']) || ! empty($r['is_missing_pp'])) {
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
                'pp_l30' => (float) ($r['PP L30'] ?? 0),
                'lp' => (float) ($r['LP_productmaster'] ?? 0),
                'ship' => (float) ($r['Ship_productmaster'] ?? 0),
                'margin' => (float) ($r['percentage'] ?? 0),
                'amz' => (float) ($r['A Price'] ?? 0),
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
     * Save S PRC the way the page does (purchasing_power_data_views.value): SPRICE + SGPFT / SROI + status.
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
        $existing['SPRICE_STATUS'] = $status;
        $existing['SPRICE_STATUS_UPDATED_AT'] = $now;
        if ($status === 'pushed') {
            $existing['SPRICE_PUSHED_VALUE'] = $sprice;
        }

        $view->value = $existing;
        $view->save();
    }

    /**
     * Push one SKU through PurchasingPowerApiService::updatePrice (MCM PRI01 import), then stamp the result.
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
        $err = (string) ($result['message'] ?? 'Purchasing Power push failed');
        $stats['errors'][] = $sku.': '.$err;
        $this->log($logger, 'Push failed '.$sku.': '.$err);
        Log::error('Purchasing Power Sprc Dil push failed', ['sku' => $sku, 'price' => $price, 'error' => $err]);
        $this->savePushMeta($sku, 'error', null);
    }

    protected function apiConfigured(): bool
    {
        try {
            return $this->ppApi->isConfigured();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array{success?:bool,message?:string} */
    protected function pushPrice(string $sku, float $price): array
    {
        try {
            return $this->ppApi->updatePrice($sku, $price);
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
            Log::error('[PurchasingPowerSprcDilAutoPush] status save failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** The page reads the view by the exact Product Master SKU, so a new row keeps that spelling. */
    protected function findView(string $sku): PurchasingPowerDataView
    {
        $key = strtoupper(trim($sku));

        return PurchasingPowerDataView::whereRaw('UPPER(TRIM(sku)) = ?', [$key])->first()
            ?? new PurchasingPowerDataView(['sku' => trim($sku)]);
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
