<?php

namespace App\Services;

use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSkuDailyData;
use App\Models\ShopifySku;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CVR vs CPN → snap to Amazon coupon tiers (5% / 10%) → discount SPRICE → push Listings API.
 * Coupons: created in-app (amazon_cpn_coupons) with attribute "1 coupon per day only".
 * Skips unchanged prices; also skips a second coupon-tier change for the same SKU on the same ET day.
 */
class AmazonCvrCpnAutoPushService
{
    public function __construct(
        private readonly PefCvrCpnAutoApplyService $cvrCpnRules,
        private readonly AmazonCpnCouponCatalog $couponCatalog
    ) {}

    /**
     * @param  list<string>|null  $onlySkus
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
        bool $pushAll = false
    ): array {
        $coupons = $this->couponCatalog->ensureCoupons();
        $this->log($logger, 'Amazon coupons ready: '.implode(', ', array_map(
            static fn ($c) => $c['percent'].'%'.(! empty($c['one_per_day']) ? ' (1/day)' : ''),
            $coupons
        )));

        $rules = $this->cvrCpnRules->loadRules();
        $this->log($logger, 'Loaded '.count($rules).' CVR vs CPN rules (shared with pricing-errors-fix)');

        $stats = [
            'candidates' => 0,
            'applied' => 0,
            'pushed' => 0,
            'skipped_unchanged' => 0,
            'skipped_one_per_day' => 0,
            'skipped' => 0,
            'push_failed' => 0,
            'errors' => [],
            'coupons' => $coupons,
        ];

        try {
            $rows = $this->loadCandidates($limit, $onlySkus);
        } catch (Throwable $e) {
            $stats['errors'][] = 'load: '.$e->getMessage();
            $this->log($logger, '✗ Load failed: '.$e->getMessage());

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $lock = Cache::lock('amazon-cvr-cpn-auto-push-run', 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another CVR vs CPN push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s)");

        $api = ($dryRun || $skipPush) ? null : new AmazonSpApiService;
        $total = count($rows);
        $todayEt = Carbon::now('America/New_York')->toDateString();

        try {
        foreach ($rows as $i => $row) {
            try {
                $computed = $this->computeTarget($row, $rules);
                if ($computed === null) {
                    $stats['skipped']++;
                    continue;
                }

                if (AmazonSpApiService::listingPriceMatchesSprice($row['price'] ?? 0, $computed['sprice'])) {
                    $stats['skipped_unchanged']++;
                    continue;
                }

                if (! $pushAll && $this->isUnchanged($row, $computed)) {
                    $stats['skipped_unchanged']++;
                    continue;
                }

                // Attribute: 1 coupon per day only — block tier changes after first push today
                if ($this->blockedByOnePerDay($row, $computed, $todayEt)) {
                    $stats['skipped_one_per_day']++;
                    continue;
                }

                $this->saveSpriceAndCpn($row['sku'], $computed['sprice'], $computed['cpn'], $computed['base'], $computed['cvr']);
                $stats['applied']++;

                if ($dryRun || $skipPush) {
                    continue;
                }

                $plan = AmazonSpApiService::computeSaleBusinessMin((float) $computed['sprice']);
                $reason = $pushAll
                    ? 'Push All'
                    : AmazonSpApiService::offerMismatchReason(
                        $row['pushed_sale'] ?? null,
                        $row['pushed_business'] ?? null,
                        $row['pushed_min'] ?? null,
                        $plan['sale_price'],
                        $plan['business_price'],
                        $plan['min_price']
                    );
                AmazonSpApiService::logPushDelta(
                    $row['sku'],
                    $row['pushed_sale'] ?? null,
                    $row['pushed_business'] ?? null,
                    $row['pushed_min'] ?? null,
                    $plan['sale_price'],
                    $plan['business_price'],
                    $plan['min_price']
                );
                $ok = $this->pushSprice($api, $row['sku'], $row['seller_sku'], $computed['sprice'], $computed['cpn'], $reason);
                if ($ok) {
                    $stats['pushed']++;
                } else {
                    $stats['push_failed']++;
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            } catch (Throwable $e) {
                $stats['push_failed']++;
                $stats['errors'][] = $row['sku'].': '.$e->getMessage();
                Log::error('[AmazonCvrCpnAutoPush] exception', [
                    'sku' => $row['sku'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }

            if ($logger && (($i + 1) % 100 === 0 || ($i + 1) === $total)) {
                $this->log($logger, 'Progress '.($i + 1)."/{$total} (pushed {$stats['pushed']}, unchanged {$stats['skipped_unchanged']}, 1/day {$stats['skipped_one_per_day']})");
            }
        }

        $this->log($logger, sprintf(
            'Done: applied=%d pushed=%d unchanged=%d one_per_day=%d skipped=%d failed=%d%s',
            $stats['applied'],
            $stats['pushed'],
            $stats['skipped_unchanged'],
            $stats['skipped_one_per_day'],
            $stats['skipped'],
            $stats['push_failed'],
            ($dryRun || $skipPush) ? ' [no Amazon push]' : ''
        ));

        return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @param  list<string>|null  $onlySkus
     * @return list<array<string, mixed>>
     */
    protected function loadCandidates(?int $limit, ?array $onlySkus): array
    {
        $query = AmazonDatasheet::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('price', '>', 0)
            ->orderBy('sku');

        if ($onlySkus !== null && $onlySkus !== []) {
            $upper = array_values(array_unique(array_map(
                static fn ($s) => strtoupper(trim((string) $s)),
                $onlySkus
            )));
            $query->where(function ($q) use ($upper) {
                foreach ($upper as $sku) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$sku]);
                }
            });
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $sheets = $query->get([
            'sku',
            'asin',
            'price',
            'units_ordered_l30',
            'sessions_l30',
        ]);

        $skuKeys = [];
        foreach ($sheets as $m) {
            $skuKeys[] = strtoupper(trim((string) $m->sku));
        }
        $skuKeys = array_values(array_unique(array_filter($skuKeys)));

        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku($skuKeys);
        $views = AmazonDataView::query()
            ->whereIn('sku', $skuKeys)
            ->get()
            ->keyBy(static fn ($v) => strtoupper(trim((string) $v->sku)));

        $out = [];
        $seen = [];
        foreach ($sheets as $m) {
            $sellerSku = trim((string) $m->sku);
            $sku = strtoupper($sellerSku);
            if ($sku === '' || isset($seen[$sku])) {
                continue;
            }
            $seen[$sku] = true;

            $price = (float) $m->price;
            if ($price <= 0) {
                continue;
            }

            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            $shopify = $shopifyByNorm[$norm] ?? null;
            $inv = (float) ($shopify->inv ?? 0);

            $sess30 = (float) ($m->sessions_l30 ?? 0);
            $u30 = (float) ($m->units_ordered_l30 ?? 0);
            $cvr = $sess30 > 0 ? round(($u30 / $sess30) * 100, 2) : 0.0;

            $dv = $this->decodeValue($views[$sku]->value ?? null);
            $std = (float) ($dv['STANDARD_PRICE'] ?? 0);
            $lastOffer = AmazonSpApiService::lastPushedSaleBusinessMin($dv);
            $pushed = $lastOffer['sale'];
            $cpnPct = isset($dv['PEF_CPN_PCT']) && is_numeric($dv['PEF_CPN_PCT'])
                ? (float) $dv['PEF_CPN_PCT']
                : null;
            $cpnPushedAt = isset($dv['PEF_CPN_PUSHED_AT']) ? trim((string) $dv['PEF_CPN_PUSHED_AT']) : '';

            $out[] = [
                'sku' => $sku,
                'seller_sku' => $sellerSku,
                'asin' => trim((string) ($m->asin ?? '')),
                'price' => $price,
                'inv' => $inv,
                'cvr' => $cvr,
                'standard_price' => $std,
                'pushed_value' => $pushed,
                'pushed_sale' => $lastOffer['sale'],
                'pushed_business' => $lastOffer['business'],
                'pushed_min' => $lastOffer['min'],
                'cpn_pct' => $cpnPct,
                'cpn_pushed_at' => $cpnPushedAt,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{key:string,label:string,cpn:float}>  $rules
     * @return array{sprice:float,cpn:int,base:float,cvr:float}|null
     */
    protected function computeTarget(array $row, array $rules): ?array
    {
        $inv = (float) ($row['inv'] ?? 0);
        $cvr = (float) ($row['cvr'] ?? 0);
        $rawCpn = $inv > 0 ? $this->cvrCpnRules->cpnForCvr($cvr, $rules) : 0.0;
        $cpn = AmazonCpnCouponCatalog::snapToTier($rawCpn);

        $base = (float) ($row['standard_price'] ?? 0);
        if (! ($base > 0)) {
            $base = (float) ($row['price'] ?? 0);
        }
        if (! ($base > 0)) {
            return null;
        }

        $sprice = $cpn > 0
            ? round($base * (1 - ($cpn / 100)), 2)
            : round($base, 2);

        if (! is_finite($sprice) || $sprice < 0.01) {
            return null;
        }

        return [
            'sprice' => $sprice,
            'cpn' => $cpn,
            'base' => round($base, 2),
            'cvr' => $cvr,
        ];
    }

    /**
     * @param  array{sprice:float,cpn:int}  $computed
     */
    protected function isUnchanged(array $row, array $computed): bool
    {
        $target = round((float) $computed['sprice'], 2);
        $cpn = (int) $computed['cpn'];
        $lastCpn = $row['cpn_pct'] !== null ? (int) round((float) $row['cpn_pct']) : null;
        if ($lastCpn !== $cpn) {
            return false;
        }

        $plan = AmazonSpApiService::computeSaleBusinessMin($target);

        return AmazonSpApiService::offerMatchesLastPushed(
            $row['pushed_sale'] ?? null,
            $row['pushed_business'] ?? null,
            $row['pushed_min'] ?? null,
            $plan['sale_price'],
            $plan['business_price'],
            $plan['min_price']
        );
    }

    /**
     * Block changing coupon tier when a different tier was already pushed today (ET).
     *
     * @param  array{sprice:float,cpn:int}  $computed
     */
    protected function blockedByOnePerDay(array $row, array $computed, string $todayEt): bool
    {
        $newCpn = (int) $computed['cpn'];
        $lastCpn = $row['cpn_pct'] !== null ? (int) round((float) $row['cpn_pct']) : null;
        $pushedAt = trim((string) ($row['cpn_pushed_at'] ?? ''));
        if ($pushedAt === '' || $lastCpn === null) {
            return false;
        }

        try {
            $pushedDay = Carbon::parse($pushedAt)->timezone('America/New_York')->toDateString();
        } catch (Throwable $e) {
            return false;
        }

        // Same ET day and tier would change → blocked (1 coupon / day)
        return $pushedDay === $todayEt && $lastCpn !== $newCpn;
    }

    protected function saveSpriceAndCpn(string $sku, float $sprice, int $cpn, float $base, float $cvr): void
    {
        $view = AmazonDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first()
            ?? new AmazonDataView(['sku' => strtoupper(trim($sku))]);

        $existing = $this->decodeValue($view->value);
        if (! $view->exists) {
            $view->sku = strtoupper(trim($sku));
        }

        $existing['SPRICE'] = round($sprice, 2);
        $existing['PEF_CPN_PCT'] = $cpn;
        $existing['PEF_CPN_BASE'] = round($base, 2);
        $existing['PEF_CPN_CVR'] = round($cvr, 2);
        $existing['SPRICE_STATUS'] = 'saved';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();

        $view->value = $existing;
        $view->save();

        $this->syncCpnDailyHistory(strtoupper(trim($sku)), round($sprice, 2), $cpn);
    }

    /** PDT daily roll-on for CPN% history dots. */
    protected function syncCpnDailyHistory(string $sku, float $sprice, int $cpn): void
    {
        try {
            $today = Carbon::now('America/Los_Angeles')->toDateString();
            $daily = AmazonSkuDailyData::firstOrNew([
                'sku' => $sku,
                'record_date' => $today,
            ]);
            $payload = is_array($daily->daily_data)
                ? $daily->daily_data
                : (json_decode($daily->daily_data ?? '{}', true) ?: []);
            $payload['sprice'] = $sprice;
            $payload['cpn_pct'] = $cpn;
            $daily->daily_data = $payload;
            $daily->save();
        } catch (Throwable $e) {
            Log::warning('[AmazonCvrCpnAutoPush] daily history sync failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function pushSprice(AmazonSpApiService $api, string $statusSku, string $sellerSku, float $sprice, int $cpn, string $reason = 'price push'): bool
    {
        $price = round($sprice, 2);
        if ($price < 0.01 || $price > 999999.99) {
            $this->savePushMeta($statusSku, 'error', null, $cpn);

            return false;
        }

        $apiSku = $sellerSku !== '' ? $sellerSku : $statusSku;
        $matched = $api->matchingSaleAndMinFromSprice($price);
        $matched['push_reason'] = $reason;
        $result = $api->updateAmazonPriceUS($apiSku, $price, 3, $matched);

        if (isset($result['errors']) && ! empty($result['errors'])) {
            $err = (string) ($result['errors'][0]['message'] ?? 'Amazon push failed');
            $this->savePushMeta($statusSku, 'error', null, $cpn, $err);
            Log::error('Amazon push failed', [
                'sku' => $statusSku,
                'error' => $err,
            ]);

            return false;
        }

        $this->savePushMeta($statusSku, 'pushed', $price, $cpn);

        return true;
    }

    protected function savePushMeta(string $sku, string $status, ?float $pushedValue, int $cpn, ?string $error = null): void
    {
        try {
            $view = AmazonDataView::firstOrNew(['sku' => strtoupper(trim($sku))]);
            $existing = $this->decodeValue($view->value);
            $existing['SPRICE_STATUS'] = $status;
            $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            $existing['PEF_CPN_PCT'] = $cpn;
            if ($pushedValue !== null) {
                $existing['CPN_PUSHED_VALUE'] = round($pushedValue, 2);
                $existing['PEF_CPN_PUSHED_AT'] = now()->toDateTimeString();
                $existing = AmazonSpApiService::stampPushedSaleBusinessMin($existing, $pushedValue);
            } elseif ($status === 'error') {
                $existing = AmazonSpApiService::stampPushError($existing, $error ?? 'Amazon push failed');
            }
            if (! $view->exists) {
                $view->sku = strtoupper(trim($sku));
            }
            $view->value = $existing;
            $view->save();
        } catch (Throwable $e) {
            Log::error('[AmazonCvrCpnAutoPush] status save failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string, mixed> */
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
