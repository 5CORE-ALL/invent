<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\ReverbController;
use App\Models\ReverbViewData;
use App\Support\AmazonDilGroiRule;
use App\Support\PushedListingPrice;
use App\Support\StdPrcVsDilPricer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;


class ReverbSprcDilAutoPushService
{
    public const LOCK_KEY = 'reverb-sprc-dil-auto-push-run';

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
        $pricer = StdPrcVsDilPricer::forChannel('reverb');

        $this->log($logger, 'Loaded Reverb Std prc vs dil (reverb_std_prc_vs_dil) → Age/Dil/B/0 Sold/CVR/Rev Disc + Min NPFT + LMP cap');

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
            $this->log($logger, 'Skipped: another Reverb Sprc Dil push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0");

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
                        // Live RV Price already equals the target. Keep the saved S PRC in step, no push.
                        if (! $dryRun && ! PushedListingPrice::same($row['sprice'] ?? 0, $sprice)) {
                            $this->saveSprice((string) $row['sku'], $computed, 'pushed');
                            $stats['applied']++;
                        }
                        $stats['skipped_unchanged']++;
                        continue;
                    }

                    if (! $pushAll) {
                        if (! empty($row['push_error_at']) && $this->withinCooldown((string) $row['push_error_at'], $cooldownMinutes)) {
                            // Reverb rejected the last push — retry after the cooldown, not every run.
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

                    $this->pushOne((string) $row['sku'], $computed, $stats, $logger);
                    if ($sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }
                } catch (Throwable $e) {
                    $stats['push_failed']++;
                    $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                    Log::error('[ReverbSprcDilAutoPush] exception', [
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
                ($dryRun || $skipPush) ? ' [no Reverb push]' : ''
            ));

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Page S PRC formula (INV > 0 child SKUs): Std rule + Min NPFT, then LMP cap, Std is the maximum.
     *
     * @param  array<string, mixed>  $row
     * @return array{sprice:float,lmp_capped:bool,base:float,margin:float,lp:float,ship:float,ads:float}|null
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
            $margin = 0.85;
        }
        $adsPct = (float) ($row['ads_pct'] ?? 0);
        $lmp = (float) ($row['lmp'] ?? 0);

        $pricer ??= StdPrcVsDilPricer::forChannel('reverb');
        // Age / Dil / B / 0 Sold / CVR / Rev discounts, then Min NPFT (clearance exempt).
        $pricerRow = [
            'sku' => (string) ($row['sku'] ?? ''),
            'inv' => $inv,
            'dil' => (float) ($row['dil'] ?? 0),
            'std_price' => $std,
            'age_days' => $row['age_days'] ?? null,
            'cvr' => (float) ($row['cvr'] ?? 0),
            'reviews' => 0.0,
            'sold' => (float) ($row['rv_l30'] ?? 0),
            'lp' => $lp,
            'ship' => $ship,
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

        // Only the LMP cap (no eBay / Amazon caps). Std Prc is the maximum.
        $capped = AmazonDilGroiRule::capSpriceToLmp($effective, $lmp, $lp, $ship, $margin, $std);
        $lmpCapped = $lmp > 0
            && ($effective - $capped) > 0.009
            && abs($capped - round($lmp, 2)) <= 0.02;

        if (! is_finite($capped) || $capped < 0.01) {
            return null;
        }

        return [
            'sprice' => round($capped, 2),
            'lmp_capped' => $lmpCapped,
            'base' => round($std, 2),
            'margin' => $margin,
            'lp' => $lp,
            'ship' => $ship,
            'ads' => $adsPct,
        ];
    }

    /**
     * Same rows as /reverb-pricing. Listed on Reverb, INV > 0, child SKUs only.
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
            Log::warning('[ReverbSprcDilAutoPush] age days lookup failed', ['error' => $e->getMessage()]);
        }

        $res = app(ReverbController::class)->getViewReverbTabularData(new Request());
        $payload = method_exists($res, 'getData') ? $res->getData(true) : [];
        $pageRows = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($pageRows)) {
            throw new \RuntimeException('Reverb pricing data load failed');
        }

        $pushedAtBySku = $this->pushedAtBySku();

        $out = [];
        $seen = [];
        foreach ($pageRows as $r) {
            if (! is_array($r) || ! empty($r['is_parent'])) {
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
            $price = (float) ($r['RV Price'] ?? 0);
            if (! ($inv > 0) || ! $this->isListed($r, $price)) {
                continue;
            }
            $seen[$key] = true;

            $rvL30 = (float) ($r['RV L30'] ?? 0);
            $status = strtolower((string) ($r['SPRICE_STATUS'] ?? ''));

            $out[] = [
                'sku' => $sku,
                'price' => $price,
                'inv' => $inv,
                // Same as the page Dil: raw (L30 / INV) × 100, not rounded before slab match.
                'dil' => ((float) ($r['L30'] ?? 0) / $inv) * 100,
                'rv_l30' => $rvL30,
                'cvr' => (float) ($r['CVR'] ?? 0),
                'lp' => (float) ($r['LP_productmaster'] ?? 0),
                'ship' => (float) ($r['Ship_productmaster'] ?? 0),
                'margin' => (float) ($r['percentage'] ?? 0),
                'ads_pct' => (float) ($r['ads_pct'] ?? 0),
                'lmp' => (float) ($r['lmp_price'] ?? 0),
                'age_days' => $ageDays->ageDays($sku, $inv, $agePushBySku, $ageLastSaleBySku),
                'standard_price' => (float) ($r['STANDARD_PRICE'] ?? 0),
                'sprice' => (float) ($r['SPRICE'] ?? 0),
                'pushed_value' => isset($r['SPRICE_PUSHED_VALUE']) && is_numeric($r['SPRICE_PUSHED_VALUE'])
                    ? (float) $r['SPRICE_PUSHED_VALUE']
                    : null,
                'pushed_at' => $pushedAtBySku[$key] ?? null,
                'push_error_at' => $status === 'error' ? ($r['SPRICE_STATUS_UPDATED_AT'] ?? null) : null,
            ];

            if ($limit !== null && $limit > 0 && count($out) >= $limit) {
                break;
            }
        }

        usort($out, static fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

        return $out;
    }

    /**
     * Live Reverb listing: RV Price > 0, or an active/live listing state. Ended/draft/sold listings are skipped.
     *
     * @param  array<string, mixed>  $row
     */
    protected function isListed(array $row, float $price): bool
    {
        $state = strtolower(trim((string) ($row['listing_state'] ?? '')));
        if ($state !== '' && ! in_array($state, ['live', 'active'], true)) {
            return false;
        }

        return $price > 0;
    }

    /** @var array<string, string>|null */
    private ?array $pushedAtCache = null;

    /** Last successful push time from reverb_view_data.values (cooldown). @return array<string, string> */
    private function pushedAtBySku(): array
    {
        if ($this->pushedAtCache !== null) {
            return $this->pushedAtCache;
        }
        $map = [];
        try {
            foreach (ReverbViewData::query()->get(['sku', 'values']) as $view) {
                $val = $this->decodeValue($view->values);
                $at = $val['SPRICE_PUSHED_AT'] ?? null;
                if (is_string($at) && $at !== '') {
                    $map[strtoupper(trim((string) $view->sku))] = $at;
                }
            }
        } catch (Throwable $e) {
            Log::warning('[ReverbSprcDilAutoPush] pushed-at load failed', ['error' => $e->getMessage()]);
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
     * Save S PRC + the same SGPFT / SPFT / SROI / SNROI the page save writes.
     *
     * @param  array{sprice:float,margin:float,lp:float,ship:float,ads:float}  $computed
     */
    protected function saveSprice(string $sku, array $computed, string $status = 'saved'): void
    {
        $view = $this->findView($sku);
        $existing = $this->decodeValue($view->values);

        $sprice = round((float) $computed['sprice'], 2);
        $lp = (float) $computed['lp'];
        $profit = ($sprice * (float) $computed['margin']) - $lp - (float) $computed['ship'];
        $gpft = $sprice > 0 ? round(($profit / $sprice) * 100, 0) : 0;
        $adsPct = (float) $computed['ads'];

        $existing['SPRICE'] = $sprice;
        $existing['SGPFT'] = $gpft;
        $existing['SPFT'] = round($gpft - $adsPct, 0).'%';
        $existing['SROI'] = ($lp > 0 ? round(($profit / $lp) * 100, 0) : 0).'%';
        $existing['SNROI'] = ($lp > 0 && $sprice > 0)
            ? round((($profit - ($sprice * ($adsPct / 100))) / $lp) * 100, 0)
            : 0;
        $existing['SPRICE_STATUS'] = $status;
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
        if ($status === 'pushed') {
            $existing['SPRICE_PUSHED_VALUE'] = $sprice;
        }

        $view->values = $existing;
        $view->save();
    }

    /**
     * Push one SKU through ReverbApiService (same call as the old page push), then stamp the result.
     *
     * @param  array{sprice:float}  $computed
     * @param  array<string, mixed>  $stats
     * @param  callable(string): void|null  $logger
     */
    protected function pushOne(string $sku, array $computed, array &$stats, ?callable $logger): void
    {
        $price = round((float) $computed['sprice'], 2);
        $result = $this->pushPrice($sku, $price);

        if (! empty($result['success'])) {
            $stats['pushed']++;
            $this->savePushMeta($sku, 'pushed', $price);
            // RV Price column / metrics follow the live listing right away (next run sees "unchanged").
            try {
                ChannelLivePriceSync::writeLive('reverb', $sku, $price);
            } catch (Throwable $e) {
                Log::warning('[ReverbSprcDilAutoPush] live price sync failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            }

            return;
        }

        $stats['push_failed']++;
        $err = (string) ($result['message'] ?? 'Reverb push failed');
        $stats['errors'][] = $sku.': '.$err;
        $this->log($logger, 'Push failed '.$sku.': '.$err);
        Log::error('Reverb Sprc Dil push failed', ['sku' => $sku, 'price' => $price, 'error' => $err]);
        $this->savePushMeta($sku, 'error', null);
    }

    /** @return array{success?:bool,message?:string} */
    protected function pushPrice(string $sku, float $price): array
    {
        try {
            return (new ReverbApiService())->updatePrice($sku, $price);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Status, time and pushed value (same keys as the page push wrote). */
    protected function savePushMeta(string $sku, string $status, ?float $price): void
    {
        try {
            $view = $this->findView($sku);
            $existing = $this->decodeValue($view->values);
            $now = now()->toDateTimeString();
            $existing['SPRICE_STATUS'] = $status;
            $existing['SPRICE_STATUS_UPDATED_AT'] = $now;
            if ($status === 'pushed' && $price !== null && $price > 0) {
                $existing['SPRICE_PUSHED_VALUE'] = $price;
                $existing['SPRICE_PUSHED_AT'] = $now;
                $existing['SPRICE_PUSHED_BY'] = 'Server (Sprc Dil auto push)';
                unset($existing['SPRICE_PUSHED_BY_ID']);
                $hist = isset($existing['SPRICE_PUSH_HISTORY']) && is_array($existing['SPRICE_PUSH_HISTORY'])
                    ? $existing['SPRICE_PUSH_HISTORY']
                    : [];
                array_unshift($hist, [
                    'price' => $price,
                    'by' => 'Server (Sprc Dil auto push)',
                    'at' => $now,
                    'marketplace' => 'reverb',
                ]);
                $existing['SPRICE_PUSH_HISTORY'] = array_slice($hist, 0, 30);
            }
            $view->values = $existing;
            $view->save();
        } catch (Throwable $e) {
            Log::error('[ReverbSprcDilAutoPush] status save failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function findView(string $sku): ReverbViewData
    {
        $key = strtoupper(trim($sku));

        return ReverbViewData::whereRaw('UPPER(TRIM(sku)) = ?', [$key])->first()
            ?? new ReverbViewData(['sku' => trim($sku)]);
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
