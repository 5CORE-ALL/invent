<?php

namespace App\Services;

use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonProductReview;
use App\Models\AmazonSkuCompetitor;
use App\Models\AmazonSkuDailyData;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\AmazonDilGroiRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Amazon Analytics Sprc Dil stack → SPRICE → Listings API (page not required).
 *
 * Same as Push Prc on /amazon-tabulator-view:
 *  Dil in slab (INV > 0, including 0 Sold) → Sale = Dil→GROI (LP/Ship / 0.80)
 *    then CVR Down & < 7% → Target GROI −10; CVR Up & > 10% → Target GROI +10
 *  Else → Sale = Std × (1 − (CVR Disc + Rev Disc)/100)
 *  Then LMP cap when LMP is lower and SGROI at LMP ≥ 20%.
 *  Skip when live Price already equals the target. Price column updates on each push.
 */
class AmazonSprcDilAutoPushService
{
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
        bool $pushAll = false
    ): array {
        $dilRules = $this->loadDilGroiRules();
        $cvrRules = $this->loadCvrDiscRules();
        $review = $this->loadReviewDiscRules();

        $this->log($logger, 'Loaded Dil slabs='.count($dilRules)
            .' CVR Disc slabs='.count($cvrRules)
            .' Rev Disc slabs='.count($review['rules'])
            .' (amazon_dil_vs_groi / amazon_cvr_vs_disc / amazon_review_vs_disc)');

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

        $lock = Cache::lock('amazon-sprc-dil-auto-push-run', 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another Sprc Dil push is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        }

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s) with INV > 0");

        $api = ($dryRun || $skipPush) ? null : new AmazonSpApiService;
        $total = count($rows);

        try {
            foreach ($rows as $i => $row) {
                try {
                    $computed = $this->computeTarget(
                        $row,
                        $dilRules,
                        $cvrRules,
                        $review['rules'],
                        $review['max_reviews']
                    );
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

                    $this->saveSprice($row['sku'], $computed);
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
                    $ok = $this->pushSprice($api, $row['sku'], $row['seller_sku'], $computed['sprice'], $reason);
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
                    $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                    Log::error('[AmazonSprcDilAutoPush] exception', [
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
                ($dryRun || $skipPush) ? ' [no Amazon push]' : ''
            ));

            return ['dry_run' => $dryRun, 'skip_push' => $skipPush, 'stats' => $stats];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Page Push Prc / Sprc Dil formula (INV > 0 child SKUs).
     *
     * @param  array<string, mixed>  $row
     * @param  list<array{key:string,label:string,min:float,max:float,groi:float}>  $dilRules
     * @param  list<array{key:string,label:string,disc:float}>  $cvrRules
     * @param  list<array{key:string,min:int,max:int,disc:float}>  $reviewRules
     * @return array{sprice:float,dil:float,groi:?float,cvr_disc:float,review_disc:float,dil_groi:bool,lmp_capped:bool,base:float}|null
     */
    public function computeTarget(
        array $row,
        array $dilRules,
        array $cvrRules,
        array $reviewRules,
        int $reviewMax
    ): ?array {
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0)) {
            return null;
        }

        $dil = (float) ($row['dil'] ?? 0);
        $lp = (float) ($row['lp'] ?? 0);
        $ship = (float) ($row['ship'] ?? 0);
        $std = (float) ($row['standard_price'] ?? 0);
        $cvr = (float) ($row['cvr'] ?? 0);
        $reviews = (int) ($row['review_count'] ?? 0);
        $lmp = (float) ($row['lmp'] ?? 0);

        $dilRule = AmazonDilGroiRule::match($dil, $dilRules);
        $dilPrice = null;
        $groi = null;
        if ($dilRule !== null && $lp > 0) {
            $groi = (float) $dilRule['groi'];
            $aL30 = (float) ($row['a_l30'] ?? 0);
            $sess30 = (float) ($row['sess30'] ?? $row['sessions_l30'] ?? 0);
            $aL60 = (float) ($row['a_l60'] ?? $row['units_ordered_l60'] ?? 0);
            $sess60 = (float) ($row['sess60'] ?? $row['sessions_l60'] ?? 0);
            $cvrL30 = AmazonDilGroiRule::cvrL30($aL30, $sess30);
            $cvrL45 = AmazonDilGroiRule::cvrL45($aL30, $sess30, $aL60, $sess60);
            $groi = AmazonDilGroiRule::adjustGroiForCvr(
                $groi,
                $cvrL30,
                AmazonDilGroiRule::cvrTrend($cvrL30, $cvrL45)
            );
            $dilPrice = AmazonDilGroiRule::suggestedPrice($lp, $ship, $groi);
            if ($dilPrice !== null && ! ($dilPrice >= 0.01)) {
                $dilPrice = null;
            }
        }
        $dilGroi = $dilPrice !== null && $dilPrice > 0;

        $cvrDisc = $this->discForCvr($cvr, $cvrRules);
        $reviewDisc = $this->discForReviews($reviews, $reviewRules, $reviewMax);
        $totalDisc = round(min(99.99, max(0, $cvrDisc + $reviewDisc)), 2);

        $sale = null;
        if ($dilGroi) {
            $sale = $dilPrice;
        } elseif (! ($std > 0)) {
            return null;
        } elseif ($totalDisc > 0 && $totalDisc < 100) {
            $sale = round($std * (1 - ($totalDisc / 100)), 2);
            if (! ($sale >= 0.01) || $sale >= $std) {
                $sale = null;
            }
        }

        $effective = $sale !== null ? (float) $sale : $std;
        if (! ($effective > 0)) {
            return null;
        }

        $capped = $this->capSpriceToLmp($effective, $lmp, $lp, $ship);
        $lmpCapped = ($effective - $capped) > 0.009;
        $sprice = $capped;

        if (! is_finite($sprice) || $sprice < 0.01) {
            return null;
        }

        return [
            'sprice' => round($sprice, 2),
            'dil' => round($dil, 2),
            'groi' => $groi,
            'cvr_disc' => $dilGroi ? 0.0 : $cvrDisc,
            'review_disc' => $dilGroi ? 0.0 : $reviewDisc,
            'dil_groi' => $dilGroi,
            'lmp_capped' => $lmpCapped,
            'base' => $std > 0 ? round($std, 2) : round($sprice, 2),
        ];
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
            'units_ordered_l60',
            'sessions_l60',
        ]);

        $skuKeys = [];
        $sellerSkus = [];
        foreach ($sheets as $m) {
            $seller = trim((string) $m->sku);
            $sku = strtoupper($seller);
            if ($sku === '' || str_contains($sku, 'PARENT')) {
                continue;
            }
            $skuKeys[] = $sku;
            $sellerSkus[] = $seller;
        }
        $skuKeys = array_values(array_unique($skuKeys));
        $sellerSkus = array_values(array_unique(array_filter($sellerSkus)));

        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku($skuKeys);
        $views = AmazonDataView::query()
            ->whereIn('sku', $skuKeys)
            ->get()
            ->keyBy(static fn ($v) => strtoupper(trim((string) $v->sku)));

        $mastersBySku = [];
        $masterLookup = array_values(array_unique(array_merge($skuKeys, $sellerSkus)));
        foreach (array_chunk($masterLookup, 400) as $chunk) {
            foreach (ProductMaster::query()->whereIn('sku', $chunk)->get(['sku', 'Values']) as $pm) {
                $mastersBySku[strtoupper(trim((string) $pm->sku))] = $pm;
            }
        }

        $reviewsBySku = $this->loadReviewCounts($skuKeys);
        $lmpBySku = $this->loadLmpBySku($skuKeys, $sellerSkus);

        $out = [];
        $seen = [];
        foreach ($sheets as $m) {
            $sellerSku = trim((string) $m->sku);
            $sku = strtoupper($sellerSku);
            if ($sku === '' || isset($seen[$sku]) || str_contains($sku, 'PARENT')) {
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
            if (! ($inv > 0)) {
                continue;
            }

            $l30 = (float) ($shopify->quantity ?? 0);
            // Same as amzPefDil: raw (L30 / INV) × 100 — do not round before slab match.
            $dil = ($l30 / $inv) * 100;
            $sess30 = (float) ($m->sessions_l30 ?? 0);
            $aL30 = (float) ($m->units_ordered_l30 ?? 0);
            $sess60 = (float) ($m->sessions_l60 ?? 0);
            $aL60 = (float) ($m->units_ordered_l60 ?? 0);
            $cvr = $sess30 > 0 ? round(($aL30 / $sess30) * 100, 2) : 0.0;

            $master = $mastersBySku[$sku] ?? null;
            [$lp, $ship] = $this->lpShipFromMaster($master);

            $dv = $this->decodeValue($views[$sku]->value ?? null);
            $std = (float) ($dv['STANDARD_PRICE'] ?? 0);
            $lastOffer = AmazonSpApiService::lastPushedSaleBusinessMin($dv);

            $out[] = [
                'sku' => $sku,
                'seller_sku' => $sellerSku,
                'asin' => trim((string) ($m->asin ?? '')),
                'price' => $price,
                'inv' => $inv,
                'l30' => $l30,
                'dil' => $dil,
                'cvr' => $cvr,
                'a_l30' => $aL30,
                'sess30' => $sess30,
                'a_l60' => $aL60,
                'sess60' => $sess60,
                'lp' => $lp,
                'ship' => $ship,
                'lmp' => (float) ($lmpBySku[$sku] ?? 0),
                'review_count' => (int) ($reviewsBySku[$sku] ?? 0),
                'standard_price' => $std,
                'sprice' => (float) ($dv['SPRICE'] ?? 0),
                'pushed_value' => $lastOffer['sale'],
                'pushed_sale' => $lastOffer['sale'],
                'pushed_business' => $lastOffer['business'],
                'pushed_min' => $lastOffer['min'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $skuKeys
     * @return array<string, int>
     */
    protected function loadReviewCounts(array $skuKeys): array
    {
        if ($skuKeys === []) {
            return [];
        }

        $out = [];
        try {
            $rows = AmazonProductReview::query()
                ->where(function ($q) {
                    $q->where('channel', 'Amazon')->orWhereNull('channel')->orWhere('channel', '');
                })
                ->whereNotNull('sku')
                ->get(['sku', 'review_count']);
            foreach ($rows as $rr) {
                $k = strtoupper(trim(str_replace("\xc2\xa0", ' ', (string) $rr->sku)));
                if ($k === '') {
                    continue;
                }
                $out[$k] = (int) ($rr->review_count ?? 0);
            }
        } catch (Throwable $e) {
            Log::warning('[AmazonSprcDilAutoPush] reviews load failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * Lowest landed LMP, merged across Sku Link LMP siblings (same as the tabulator).
     *
     * @param  list<string>  $skuKeys
     * @param  list<string>  $sellerSkus
     * @return array<string, float>
     */
    protected function loadLmpBySku(array $skuKeys, array $sellerSkus): array
    {
        $out = [];
        if ($skuKeys === []) {
            return $out;
        }

        try {
            $lookups = AmazonSkuCompetitor::buildGroupedLookup('amazon');
            $details = $lookups['details'];
            $groups = app(LmpSkuGroupService::class);
            $groups->prepareForSkus(array_merge($skuKeys, $sellerSkus));

            foreach ($skuKeys as $sku) {
                $members = $groups->groupContaining($sku);
                if ($members === []) {
                    $members = [$sku];
                }
                $entries = collect();
                foreach ($members as $member) {
                    $key = AmazonSkuCompetitor::normalizeSkuKey((string) $member);
                    $chunk = $details->get($key);
                    if ($chunk) {
                        $entries = $entries->merge($chunk);
                    }
                }
                $entries = AmazonSkuCompetitor::applyIgnoreToSameAsins($entries);
                $entries = AmazonSkuCompetitor::dedupeByAsin($entries);
                $lowest = AmazonSkuCompetitor::lowestFromCollection($entries);
                $landed = $lowest ? AmazonSkuCompetitor::landedPrice($lowest) : null;
                $out[$sku] = ($landed !== null && $landed > 0) ? (float) $landed : 0.0;
            }
        } catch (Throwable $e) {
            Log::warning('[AmazonSprcDilAutoPush] LMP load failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /** @return array{0: float, 1: float} */
    protected function lpShipFromMaster(mixed $master): array
    {
        if ($master === null) {
            return [0.0, 0.0];
        }
        $values = is_array($master->Values ?? null)
            ? $master->Values
            : (is_string($master->Values ?? null) ? json_decode((string) $master->Values, true) : []);
        if (! is_array($values)) {
            $values = [];
        }
        $lp = 0.0;
        foreach ($values as $k => $v) {
            if (strtolower((string) $k) === 'lp' && is_numeric($v)) {
                $lp = (float) $v;
                break;
            }
        }
        if (! ($lp > 0) && isset($master->lp) && is_numeric($master->lp)) {
            $lp = (float) $master->lp;
        }
        $ship = isset($values['ship']) && is_numeric($values['ship'])
            ? (float) $values['ship']
            : ((isset($master->ship) && is_numeric($master->ship)) ? (float) $master->ship : 0.0);

        return [$lp, $ship];
    }

    /**
     * @param  list<array{key:string,label:string,disc:float}>  $rules
     */
    public function discForCvr(float $cvr, array $rules): float
    {
        $key = $this->cvrSlabKey($cvr);
        foreach ($rules as $rule) {
            if (($rule['key'] ?? '') === $key) {
                $n = (float) ($rule['disc'] ?? 0);

                return is_finite($n) && $n >= 0 ? round($n, 2) : 0.0;
            }
        }

        return 0.0;
    }

    /**
     * @param  list<array{key:string,min:int,max:int,disc:float}>  $rules
     */
    public function discForReviews(int $count, array $rules, int $maxReviews): float
    {
        $cap = $maxReviews > 0 ? $maxReviews : 4;
        if (! ($count > 0) || $count > $cap) {
            return 0.0;
        }
        foreach ($rules as $rule) {
            $min = (int) ($rule['min'] ?? 0);
            $max = (int) ($rule['max'] ?? 0);
            if ($count >= $min && $count <= $max) {
                $n = (float) ($rule['disc'] ?? 0);

                return is_finite($n) && $n > 0 ? round($n, 2) : 0.0;
            }
        }

        return 0.0;
    }

    public function capSpriceToLmp(float $sprice, float $lmp, float $lp, float $ship): float
    {
        $s = round($sprice, 2);
        if (! ($lmp > 0) || ! ($s > 0) || ($s + 0.0001) < $lmp) {
            return $s;
        }
        $sgroiAtLmp = null;
        if ($lp > 0) {
            $sgroiAtLmp = (($lmp * AmazonDilGroiRule::TAKE_HOME - $ship - $lp) / $lp) * 100;
        }
        if ($sgroiAtLmp !== null && $sgroiAtLmp < 20) {
            return $s;
        }

        return round($lmp, 2);
    }

    protected function cvrSlabKey(float $cvr): string
    {
        if (! is_finite($cvr) || $cvr <= 0) {
            return 'eq-0';
        }
        if ($cvr > 7) {
            return 'gt-7';
        }
        if ($cvr >= 6.5) {
            return '6.5-7';
        }
        if ($cvr >= 6) {
            return '6-6.5';
        }
        if ($cvr >= 5) {
            return '5-6';
        }
        if ($cvr >= 4) {
            return '4-5';
        }
        if ($cvr >= 3) {
            return '3-4';
        }
        if ($cvr >= 2) {
            return '2-3';
        }
        if ($cvr >= 1.5) {
            return '1.5-2';
        }
        if ($cvr >= 1) {
            return '1-1.5';
        }

        return '0.01-1';
    }

    /**
     * @param  array{sprice:float}  $computed
     */
    protected function isUnchanged(array $row, array $computed): bool
    {
        $plan = AmazonSpApiService::computeSaleBusinessMin((float) $computed['sprice']);

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
     * @param  array{sprice:float,groi:?float,cvr_disc:float,review_disc:float,dil_groi:bool,base:float}  $computed
     */
    protected function saveSprice(string $sku, array $computed): void
    {
        $view = AmazonDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first()
            ?? new AmazonDataView(['sku' => strtoupper(trim($sku))]);

        $existing = $this->decodeValue($view->value);
        if (! $view->exists) {
            $view->sku = strtoupper(trim($sku));
        }

        $existing['SPRICE'] = round((float) $computed['sprice'], 2);
        $existing['SPRICE_STATUS'] = 'saved';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
        $existing['SPRC_DIL_GROI'] = $computed['groi'];
        $existing['SPRC_DIL_OWNED'] = ! empty($computed['dil_groi']);

        $view->value = $existing;
        $view->save();

        $this->syncDailyHistory(strtoupper(trim($sku)), (float) $computed['sprice'], $computed['groi'] ?? null);
    }

    protected function syncDailyHistory(string $sku, float $sprice, ?float $groi): void
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
            if ($groi !== null) {
                $payload['sprc_dil_groi'] = $groi;
            }
            $daily->daily_data = $payload;
            $daily->save();
        } catch (Throwable $e) {
            Log::warning('[AmazonSprcDilAutoPush] daily history sync failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function pushSprice(AmazonSpApiService $api, string $statusSku, string $sellerSku, float $sprice, string $reason = 'Sprc Dil'): bool
    {
        $price = round($sprice, 2);
        if ($price < 0.01 || $price > 999999.99) {
            $this->savePushMeta($statusSku, 'error', null);

            return false;
        }

        $apiSku = $sellerSku !== '' ? $sellerSku : $statusSku;
        $matched = $api->matchingSaleAndMinFromSprice($price);
        $matched['push_reason'] = $reason;
        $result = $api->updateAmazonPriceUS($apiSku, $price, 3, $matched);

        if (isset($result['errors']) && ! empty($result['errors'])) {
            $err = (string) ($result['errors'][0]['message'] ?? 'Amazon push failed');
            $this->savePushMeta($statusSku, 'error', null, $err);
            Log::error('Amazon Sprc Dil push failed', [
                'sku' => $statusSku,
                'error' => $err,
            ]);

            return false;
        }

        $this->savePushMeta($statusSku, 'pushed', $price);
        try {
            app(AmazonPushedPricePullService::class)->confirmAfterPush($statusSku, $apiSku, $price);
        } catch (Throwable $e) {
            Log::warning('Amazon Sprc Dil: immediate Price pull after push failed', [
                'sku' => $statusSku,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    protected function savePushMeta(string $sku, string $status, ?float $pushedValue, ?string $error = null): void
    {
        try {
            $view = AmazonDataView::firstOrNew(['sku' => strtoupper(trim($sku))]);
            $existing = $this->decodeValue($view->value);
            $existing['SPRICE_STATUS'] = $status;
            $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            if ($pushedValue !== null) {
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
            Log::error('[AmazonSprcDilAutoPush] status save failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return list<array{key:string,label:string,min:float,max:float,groi:float}> */
    protected function loadDilGroiRules(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', 'amazon_dil_vs_groi')->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        if (is_array($saved) && isset($saved['rules']) && is_array($saved['rules'])) {
            $saved = $saved['rules'];
        }
        $rules = AmazonDilGroiRule::normalizeList(is_array($saved) ? $saved : []);

        return $rules !== [] ? $rules : AmazonDilGroiRule::defaults();
    }

    /** @return list<array{key:string,label:string,disc:float}> */
    protected function loadCvrDiscRules(): array
    {
        $defaults = [
            ['key' => '0.01-1', 'label' => '0.01–1%', 'disc' => 9],
            ['key' => '1-1.5', 'label' => '1–1.5%', 'disc' => 8],
            ['key' => '1.5-2', 'label' => '1.5–2%', 'disc' => 7],
            ['key' => '2-3', 'label' => '2–3%', 'disc' => 6],
            ['key' => '3-4', 'label' => '3–4%', 'disc' => 5],
            ['key' => '4-5', 'label' => '4–5%', 'disc' => 4],
            ['key' => '5-6', 'label' => '5–6%', 'disc' => 3],
            ['key' => '6-6.5', 'label' => '6–6.5%', 'disc' => 2],
            ['key' => '6.5-7', 'label' => '6.5–7%', 'disc' => 1],
            ['key' => 'gt-7', 'label' => '> 7%', 'disc' => 0],
        ];
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', 'amazon_cvr_vs_disc')->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        if (! is_array($saved) || $saved === []) {
            return $defaults;
        }
        $byKey = [];
        foreach ($saved as $item) {
            if (! is_array($item)) {
                continue;
            }
            $k = (string) ($item['key'] ?? '');
            if ($k === '') {
                continue;
            }
            $byKey[$k] = $item;
        }
        $rules = [];
        foreach ($defaults as $def) {
            $raw = $byKey[$def['key']]['disc'] ?? $byKey[$def['key']]['cpn'] ?? null;
            $disc = is_numeric($raw) ? (float) $raw : $def['disc'];
            $rules[] = [
                'key' => $def['key'],
                'label' => $def['label'],
                'disc' => $disc,
            ];
        }

        return $rules;
    }

    /**
     * @return array{max_reviews: int, rules: list<array{key:string,min:int,max:int,label:string,disc:float}>}
     */
    protected function loadReviewDiscRules(): array
    {
        $defaults = [
            ['key' => '1-2', 'min' => 1, 'max' => 2, 'label' => '1–2', 'disc' => 4.0],
            ['key' => '2-3', 'min' => 2, 'max' => 3, 'label' => '2–3', 'disc' => 4.0],
        ];
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', 'amazon_review_vs_disc')->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        if (! is_array($saved) || $saved === []) {
            return ['max_reviews' => 4, 'rules' => $defaults];
        }
        $max = isset($saved['max_reviews']) && is_numeric($saved['max_reviews'])
            ? (int) $saved['max_reviews']
            : 4;
        if ($max < 1) {
            $max = 4;
        }
        $items = isset($saved['rules']) && is_array($saved['rules']) ? $saved['rules'] : $saved;
        $rules = [];
        foreach ($items as $item) {
            if (! is_array($item) || (isset($item['max_reviews']) && ! isset($item['min']) && ! isset($item['key']))) {
                continue;
            }
            $min = isset($item['min']) && is_numeric($item['min']) ? (int) $item['min'] : null;
            $hi = isset($item['max']) && is_numeric($item['max']) ? (int) $item['max'] : null;
            if ($min === null || $hi === null) {
                $key = (string) ($item['key'] ?? '');
                if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $key, $m) === 1) {
                    $min = (int) $m[1];
                    $hi = (int) $m[2];
                }
            }
            if ($min === null || $hi === null) {
                continue;
            }
            if ($hi < $min) {
                [$min, $hi] = [$hi, $min];
            }
            $disc = isset($item['disc']) && is_numeric($item['disc']) ? round((float) $item['disc'], 2) : 0.0;
            $rules[] = [
                'key' => $min.'-'.$hi,
                'min' => $min,
                'max' => $hi,
                'label' => $min.'–'.$hi,
                'disc' => $disc < 0 ? 0.0 : $disc,
            ];
        }

        return [
            'max_reviews' => $max,
            'rules' => $rules !== [] ? $rules : $defaults,
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
