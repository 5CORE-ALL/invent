<?php

namespace App\Services;

use App\Models\AmazonDataView;
use App\Models\AmazonDatasheet;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\DobaDataView;
use App\Models\DobaMetric;
use App\Models\FBMarketplaceDataView;
use App\Models\FbMarketplacePriceSoldData;
use App\Models\MarketplacePercentage;
use App\Models\NeweggDataView;
use App\Models\NeweggMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\Temu2DataView;
use App\Models\Temu2Metric;
use App\Models\Temu3DataView;
use App\Models\Temu3ApiOrder;
use App\Models\Temu3Pricing;
use App\Models\TemuDataView;
use App\Models\TemuMetric;
use App\Models\WalmartDataView;
use App\Models\WalmartMetrics;
use App\Models\WayfairDataView;
use App\Models\WayfairPricingPrice;
use App\Support\AmazonDilGroiRule;
use App\Support\EbayRuleSprice;
use App\Support\ProductMasterTemuShip;
use App\Support\PushedListingPrice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Page-less Sprc Dil → S PRC for Dil tabulator pages that do not have
 * their own nightly save cron (eBay / Amazon / Shopify B2C / Macys / PP / Best Buy do).
 *
 * Same cell math as ebay-sprc-dil: listing Dil, Target NROI (Ads%=0 → GROI), 0-sold min (except
 * Temu 2/3). Temu 1 0 Sold uses temu_orders L30 (same as
 * /temu1-data), not temu_metrics.quantity_purchased_l30. Dil stays Shopify
 * OV L30. CVR overlay where the page uses it, ship excluded on
 * Wayfair / FB, Newegg Amz floor, LMP cap at SGROI ≥ 20%.
 * Level-only CVR overlay when views > 0.
 */
class DilRuleSpriceApplyService
{
    public const LMP_SGROI_MIN = AmazonDilGroiRule::LMP_SGROI_MIN;

    public const CHANNELS = [
        'newegg',
        'temu',
        'temu2',
        'temu3',
        'doba',
        'wayfair',
        'fb_marketplace',
        'walmart',
    ];

    /** Channels the shared push runner can actually send. FB and Doba are save-only (no auto push). */
    public const PUSH_CHANNELS = [
        'newegg',
        'temu',
        'temu2',
        'temu3',
        'wayfair',
        'walmart',
    ];

    /**
     * Channels that run once a day only (the scheduled `--daily` call at 12:00 AM PT) or from the
     * page's Push button. Every other cron / "all" run skips them.
     */
    public const DAILY_ONLY_CHANNELS = ['wayfair'];

    private readonly string $channel;

    /** LMP cap (reduce % by Dil band) saved in the Sprc Dil modal; null = default 2%. */
    private ?array $lmpCap = null;

    /** Diff rule (gt_pct / points) saved in the Sprc Dil modal; null = default 20% / +10. */
    private ?array $diffAdj = null;

    /**
     * Ageing Rule bands saved in the Sprc Dil modal (age days → Target NROI points). Newegg only; null = off.
     *
     * @var list<array{min:float,max:float,points:float}>|null
     */
    private ?array $ageAdj = null;

    /** Clearance Target NROI% saved in this channel's Sprc Dil modal (last row). Null until the store is loaded. */
    private ?float $clearanceNroi = null;

    /**
     * Channels whose clearance SKUs (Inv days clearance = YES) are priced only from the Clearance Target NROI%, in Std prc vs dil and
     * Sprc Dil alike. Every other channel here still gives clearance SKUs no S PRC until its page is moved over.
     */
    public const CLEARANCE_NROI_CHANNELS = ['wayfair', 'temu3'];

    /** Channels whose Sprc Dil modal has the Ageing Rule (age band points on the Target NROI). */
    public const AGE_ADJ_CHANNELS = ['newegg', 'temu', 'temu3', 'doba'];

    private bool $applyStdPrcVsDil = false;

    public function __construct(string $channel = 'newegg')
    {
        $this->channel = self::normalizeChannel($channel);
    }

    public static function for(string $channel): self
    {
        return new self($channel);
    }

    public static function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return match ($channel) {
            'fb', 'facebook', 'fbmarketplace' => 'fb_marketplace',
            'wf' => 'wayfair',
            'wm', 'walmart_wfs' => 'walmart',
            default => $channel,
        };
    }

    /**
     * @return list<string>
     */
    public static function channelsFromArg(string $arg): array
    {
        $arg = strtolower(trim($arg));
        if ($arg === '' || $arg === 'all') {
            return self::CHANNELS;
        }
        $ch = self::normalizeChannel($arg);
        if (! in_array($ch, self::CHANNELS, true)) {
            return [];
        }

        return [$ch];
    }

    public function channel(): string
    {
        return $this->channel;
    }

    /**
     * @param  list<string>|null  $onlySkus
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    public function run(bool $dryRun = false, ?int $limit = null, ?array $onlySkus = null, ?callable $logger = null): array
    {
        $this->applyStdPrcVsDil = \App\Support\SpriceActiveRule::usesStdPrc($this->channel);
        $store = $this->loadDilGroiStore();
        $dilRules = $store['rules'];
        $cvrAdj = $store['cvr_adj'];
        $margin = $this->takeHome();

        $this->log($logger, $this->channel.' S PRC rule='.($this->applyStdPrcVsDil ? 'std' : 'dil')
            .' Dil slabs='.count($dilRules).' margin='.$margin);

        $stats = [
            'channel' => $this->channel,
            'candidates' => 0,
            'applied' => 0,
            'skipped_unchanged' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
        $pushTasks = [];

        try {
            $rows = $this->hydrateAll($onlySkus);
            $stats['candidates'] = count($rows);
            $applied = 0;

            foreach ($rows as $row) {
                if ($limit !== null && $applied >= $limit) {
                    break;
                }
                try {
                    $computed = $this->computeTarget($row, $dilRules, $cvrAdj, $margin);
                    if ($computed === null) {
                        $stats['skipped']++;
                        continue;
                    }
                    $next = $computed['sprice'];
                    if ($this->shouldEnqueuePush($row, $next, $dryRun)) {
                        $pushTasks[] = ['sku' => $row['sku'], 'price' => $next];
                    }
                    $saved = (float) ($row['saved_sprice'] ?? 0);
                    if (abs($saved - $next) < 0.005) {
                        $stats['skipped_unchanged']++;
                        continue;
                    }
                    if (! $dryRun) {
                        $this->saveSprice($row['sku'], $next, $row, $margin);
                    }
                    $applied++;
                    $stats['applied']++;
                } catch (Throwable $e) {
                    $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                    Log::warning('[DilRuleSpriceApply] sku failed', [
                        'channel' => $this->channel,
                        'sku' => $row['sku'] ?? '',
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            $stats['errors'][] = $e->getMessage();
            Log::error('[DilRuleSpriceApply] hydrate/run failed', [
                'channel' => $this->channel,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
            $this->log($logger, $this->channel.' FAILED: '.$e->getMessage());

            return ['channel' => $this->channel, 'dry_run' => $dryRun, 'stats' => $stats, 'push_tasks' => []];
        }

        $this->log($logger, sprintf(
            '%s done. candidates=%d applied=%d unchanged=%d skipped=%d push=%d',
            $this->channel,
            $stats['candidates'],
            $stats['applied'],
            $stats['skipped_unchanged'],
            $stats['skipped'],
            count($pushTasks)
        ));

        return [
            'channel' => $this->channel,
            'dry_run' => $dryRun,
            'stats' => $stats,
            'push_tasks' => $pushTasks,
        ];
    }

    /**
     * Dil S PRC ≠ live listing price (listed, INV > 0).
     *
     * @return list<array{sku: string, price: float}>
     */
    public function collectPushTasks(?array $onlySkus = null): array
    {
        $this->applyStdPrcVsDil = \App\Support\SpriceActiveRule::usesStdPrc($this->channel);
        if (! in_array($this->channel, self::PUSH_CHANNELS, true)) {
            return [];
        }

        $store = $this->loadDilGroiStore();
        $margin = $this->takeHome();
        $out = [];
        foreach ($this->hydrateAll($onlySkus) as $row) {
            $computed = $this->computeTarget($row, $store['rules'], $store['cvr_adj'], $margin);
            if ($computed === null) {
                continue;
            }
            $next = $computed['sprice'];
            if (! $this->shouldEnqueuePush($row, $next, false)) {
                continue;
            }
            $out[] = [
                'sku' => $row['sku'],
                'price' => $next,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{key:string,label:string,min:float,max:float,groi:float}>  $dilRules
     * @param  array{down_lt:float,down_adj:float,up_gt:float,up_adj:float}|null  $cvrAdj
     * @return array{sprice: float, groi: float}|null
     */
    public function computeTarget(array $row, array $dilRules, ?array $cvrAdj, float $margin): ?array
    {
        if ((new \App\Support\StdPrcVsDilPricer([], $this->channel))->isClearanceRow($row)) {
            // Channels not moved over yet: no S PRC from any rule, nothing is saved or pushed.
            if (! in_array($this->channel, self::CLEARANCE_NROI_CHANNELS, true)) {
                return null;
            }
            // Only the Clearance Target NROI% prices it: (LP × (1 + NROI%) + Ship) ÷ take-home. No slab, Diff, LMP, Std or Amz rule.
            $lp = (float) ($row['lp'] ?? 0);
            if (! ($lp > 0) || ! ($margin > 0)) {
                return null;
            }
            $nroi = isset($row['clearance_nroi']) && is_numeric($row['clearance_nroi'])
                ? (float) $row['clearance_nroi']
                : (float) ($this->clearanceNroi ?? 0.0);
            $ship = ! empty($this->channelConfig()['exclude_ship']) ? 0.0 : (float) ($row['ship'] ?? 0);
            $price = $this->spriceFromGroi($lp, $ship, max(0.0, $nroi), $margin);
            if (! is_finite($price) || $price < 0.01) {
                return null;
            }

            return ['sprice' => round($price, 2), 'groi' => max(0.0, $nroi)];
        }
        if ($this->applyStdPrcVsDil) {
            if ($margin > 0 && ! is_numeric($row['margin'] ?? null)) {
                $row['margin'] = $margin;
            }
            // Temu 3: the SNROI Cap judges the same take-home % as the page's SNROI% column (TEMU_MARGIN).
            if ($this->channel === 'temu3') {
                $temuMargin = TemuShopifySalesService::temu3MarginDecimal();
                if ($temuMargin > 0 && $temuMargin <= 1) {
                    $row['margin'] = $temuMargin;
                }
            }
            $priced = \App\Support\StdPrcVsDilPricer::forChannel($this->channel)->priceFromRow($row);

            return $priced !== null ? ['sprice' => $priced] : null;
        }
        $cfg = $this->channelConfig();
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0) || ! ($margin > 0)) {
            return null;
        }
        $lp = (float) ($row['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }

        $sold = array_key_exists('temu_l30', $row)
            ? (float) $row['temu_l30']
            : (float) ($row['ov_l30'] ?? 0);
        $dil = (float) ($row['dil'] ?? 0);
        $groi = null;

        $isZeroSold = ! empty($cfg['zero_sold_min_groi']) && $sold <= 0;
        if ($isZeroSold) {
            $groi = AmazonDilGroiRule::minTarget($dilRules);
        } elseif (! empty($cfg['match_or_nearest'])) {
            $rule = AmazonDilGroiRule::matchOrNearest($dil, $dilRules);
            $groi = $rule !== null ? (float) $rule['groi'] : null;
        } else {
            $rule = AmazonDilGroiRule::match($dil, $dilRules);
            $groi = $rule !== null ? (float) $rule['groi'] : null;
        }
        if ($groi === null || ! is_finite($groi)) {
            return null;
        }

        if (! empty($cfg['cvr_adj'])) {
            $views = (float) ($row['views'] ?? 0);
            if (empty($cfg['cvr_adj_requires_views']) || $views > 0) {
                $cvr = (float) ($row['cvr'] ?? 0);
                $groi = AmazonDilGroiRule::adjustGroiForCvrArrow(
                    $groi,
                    $cvr,
                    (float) ($row['cvr_60'] ?? $row['cvr60'] ?? 0),
                    $cvrAdj
                );
            }
        }

        // Ageing Rule (Newegg): the SKU's age band adds its points to the Target NROI, after the CVR overlay (floored at 0).
        if ($this->ageAdj !== null && in_array($this->channel, self::AGE_ADJ_CHANNELS, true)) {
            $groi = AmazonDilGroiRule::adjustGroiForAge(
                (float) $groi,
                is_numeric($row['age_days'] ?? null) ? (float) $row['age_days'] : null,
                $this->ageAdj
            );
        }

        // Diff rule (same as eBay 1): Diff = (Std Prc − Price) / Price above the saved % adds the saved points to the Target (default 20% / +10). Temu 3 Price = full Temu Price.
        if ($this->channel === 'temu3') {
            $diffPct = EbayRuleSprice::stdPriceDiffPct(
                (float) ($row['std_price'] ?? $row['standard_price'] ?? $row['STANDARD_PRICE'] ?? 0),
                TemuShopifySalesService::computeFullTemuPrice((float) ($row['live'] ?? 0))
            );
            $groi += AmazonDilGroiRule::diffAdjPoints($diffPct, $this->diffAdj);
        } elseif (in_array($this->channel, ['wayfair', 'doba'], true)) {
            // Wayfair / Doba: same Diff rule against the channel price (the page's Std Diff column). Floor the target at 0 like the page.
            $diffPct = EbayRuleSprice::stdPriceDiffPct(
                (float) ($row['std_price'] ?? $row['standard_price'] ?? $row['STANDARD_PRICE'] ?? 0),
                (float) ($row['live'] ?? 0)
            );
            $groi = max(0.0, (float) $groi) + AmazonDilGroiRule::diffAdjPoints($diffPct, $this->diffAdj);
            if ($groi < 0) {
                $groi = 0.0;
            }
        }

        $ship = ! empty($cfg['exclude_ship']) ? 0.0 : (float) ($row['ship'] ?? 0);
        $raw = $this->spriceFromGroi($lp, $ship, (float) $groi, $margin);
        if (! is_finite($raw) || $raw < 0.01) {
            return null;
        }

        $lmp = (float) ($row['lmp'] ?? 0);
        $std = (float) ($row['std_price'] ?? $row['standard_price'] ?? $row['STANDARD_PRICE'] ?? 0);
        // Temu 3 uses the eBay / Amazon LMP rule: a price that reaches LMP lands on LMP − x%, x by the SKU's Dil band
        // (Sprc Dil modal, LMP cap table; default 2% = LMP × 0.98), same as the page.
        $lmpReducePct = $this->channel === 'temu3'
            ? AmazonDilGroiRule::lmpCapReducePct($dil, $this->lmpCap)
            : 0.0;
        $sprice = AmazonDilGroiRule::capSpriceToLmp($raw, $lmp, $lp, $ship, $margin, $std, $lmpReducePct);

        if (! empty($cfg['amz_floor'])) {
            $amz = (float) ($row['amz_price'] ?? 0);
            if ($amz > 0 && $sprice + 0.0001 < $amz) {
                $sprice = round($amz, 2);
            }
        }

        return [
            'sprice' => $sprice,
            'groi' => $groi,
        ];
    }

    /**
     * Temu 1–3: S PRC so on-page SGROI = ((S R × 0.95) − ship − LP) / LP equals target.
     * Other channels: (LP × (1 + GROI%/100) + ship) / take-home.
     */
    protected function spriceFromGroi(float $lp, float $ship, float $groi, float $margin): float
    {
        if (in_array($this->channel, ['temu', 'temu2', 'temu3'], true)) {
            return TemuShopifySalesService::spriceFromTargetSgroi($lp, $ship, $groi, 0.0);
        }

        $price = AmazonDilGroiRule::suggestedPrice($lp, $ship, $groi, 0.0, $margin);

        return $price !== null ? $price : round(($lp * (1 + $groi / 100) + $ship) / $margin, 2);
    }

    public function capToLmp(float $sprice, float $lmp, float $lp, float $ship, float $margin): float
    {
        return AmazonDilGroiRule::capSpriceToLmp($sprice, $lmp, $lp, $ship, $margin);
    }

    /**
     * @param  list<string>|null  $onlySkus
     * @return list<array<string, mixed>>
     */
    public function hydrateAll(?array $onlySkus = null): array
    {
        $only = $onlySkus !== null ? $this->normalizeSkuList($onlySkus) : [];
        $cfg = $this->channelConfig();
        $metricClass = $cfg['metric'];
        $viewClass = $cfg['view'];
        $priceCol = $cfg['price'];
        $l30Col = $cfg['l30'];
        $viewsCol = $cfg['views'];
        $groupCol = $cfg['group'];

        $table = (new $metricClass)->getTable();
        if (! Schema::hasTable($table)) {
            return [];
        }

        $query = $metricClass::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '');
        if (Schema::hasColumn($table, $priceCol)) {
            $query->where($priceCol, '>', 0);
        }
        if ($only !== []) {
            $query->where(function ($inner) use ($only) {
                foreach ($only as $sku) {
                    $inner->orWhereRaw('UPPER(TRIM(sku)) = ?', [$sku]);
                }
            });
        }
        if (Schema::hasColumn($table, 'id')) {
            $query->orderBy('id');
        } else {
            $query->orderBy('sku');
        }

        $metrics = $query->get();
        $bySku = [];
        foreach ($metrics as $metric) {
            $sku = strtoupper(trim((string) $metric->sku));
            if ($sku === '' || str_contains($sku, 'PARENT')) {
                continue;
            }
            if ($this->isInactiveListing($metric)) {
                continue;
            }
            $live = (float) ($metric->{$priceCol} ?? 0);
            if (! ($live > 0)) {
                continue;
            }
            if (isset($bySku[$sku])) {
                continue;
            }
            $bySku[$sku] = $metric;
        }
        $skus = array_keys($bySku);
        if ($skus === []) {
            return [];
        }

        $shopifyBySku = $this->keyBySku(
            ShopifySku::query()->whereIn('sku', $skus)->get(['sku', 'inv', 'quantity'])
        );
        $masters = $this->keyBySku(
            ProductMaster::query()->whereIn('sku', $skus)->get(['sku', 'parent', 'Values'])
        );
        $savedBySku = [];
        $pushedBySku = [];
        $lmpBySku = [];
        foreach ($viewClass::query()->whereIn('sku', $skus)->get(['sku', 'value']) as $view) {
            $val = is_array($view->value)
                ? $view->value
                : (json_decode((string) ($view->value ?? ''), true) ?: []);
            $key = strtoupper(trim((string) $view->sku));
            $sprice = $val['SPRICE'] ?? null;
            if (is_numeric($sprice) && (float) $sprice > 0) {
                $savedBySku[$key] = round((float) $sprice, 2);
            }
            $pushed = PushedListingPrice::fromValue(is_array($val) ? $val : []);
            if ($pushed !== null) {
                $pushedBySku[$key] = $pushed;
            }
            $lmp = $val['lmp_price'] ?? $val['LMP'] ?? null;
            if (is_numeric($lmp) && (float) $lmp > 0) {
                $lmpBySku[$key] = round((float) $lmp, 2);
            }
        }

        $amzBySku = [];
        if (! empty($cfg['amz_floor']) || ! empty($cfg['a_l30'])) {
            $amzBySku = $this->amazonBySku($skus);
        }
        $stdBySku = self::amazonStdBySku($skus);
        $l30Overlay = [];
        if ($this->channel === 'temu3') {
            $l30Overlay = $this->temu3L30BySku($skus);
        } elseif ($this->channel === 'temu' || $this->channel === 'temu2') {
            $l30Overlay = $this->temuOrdersL30BySku($skus, $this->channel === 'temu2');
        }

        // Ageing Rule: the Shopify push clock, read once for the run. Only when a Newegg band has points.
        $ageDaysBySku = [];
        if ($this->ageAdj !== null && in_array($this->channel, self::AGE_ADJ_CHANNELS, true) && collect($this->ageAdj)->contains(
            static fn ($b) => is_array($b) && is_numeric($b['points'] ?? null) && abs((float) $b['points']) > 0.00001
        )) {
            try {
                $ageCtl = app(\App\Http\Controllers\ProductMaster\InvDaysController::class);
                $ageSources = $ageCtl->ageDaySources();
                foreach ($skus as $ageSku) {
                    $ageInv = (float) (($shopifyBySku[$ageSku]->inv ?? 0));
                    $ageDaysBySku[$ageSku] = $ageCtl->ageDays($ageSku, $ageInv, $ageSources['push'] ?? [], $ageSources['last_sale'] ?? []);
                }
            } catch (Throwable $e) {
                Log::warning('[DilRuleSpriceApply] age days lookup failed', ['channel' => $this->channel, 'error' => $e->getMessage()]);
            }
        }

        $draft = [];
        foreach ($skus as $sku) {
            $metric = $bySku[$sku];
            $shopify = $shopifyBySku[$sku] ?? null;
            $master = $masters[$sku] ?? null;
            $inv = (float) ($shopify->inv ?? 0);
            $lpShip = $this->lpAndShip($master);
            $parent = strtoupper(trim((string) ($master->parent ?? '')));
            $parent = strtoupper(trim(preg_replace('/^PARENT\s+/i', '', $parent) ?? ''));

            $ov = 0.0;
            $al30 = 0.0;
            if ($this->channel === 'temu' || $this->channel === 'temu2') {
                // Dil = Shopify OV L30, same as /temu1-data. Do not use temu_metrics sales.
                $ov = (float) ($shopify->quantity ?? 0);
            } elseif ($this->channel === 'doba') {
                // Same Dil as /amazon-tabulator-view: Shopify OV L30 ÷ Shopify INV.
                // doba_metrics.inventory is D INV and is not the Dil denominator.
                $ov = (float) ($shopify->quantity ?? 0);
            } elseif (! empty($cfg['a_l30'])) {
                $ov = (float) ($amzBySku[$sku]['l30'] ?? 0);
            } elseif ($l30Overlay !== []) {
                $ov = (float) ($l30Overlay[$sku] ?? 0);
            } elseif ($l30Col !== null && isset($metric->{$l30Col})) {
                $ov = (float) $metric->{$l30Col};
            }

            $views = 0.0;
            if ($viewsCol !== null && isset($metric->{$viewsCol})) {
                $views = (float) $metric->{$viewsCol};
            }

            $groupVal = '';
            if ($groupCol !== null && $groupCol !== 'sku' && isset($metric->{$groupCol})) {
                $groupVal = trim((string) $metric->{$groupCol});
            }

            $draft[] = [
                'sku' => $sku,
                'item_id' => $groupCol === 'item_id' ? $groupVal : '',
                'goods_id' => $groupCol === 'goods_id' ? $groupVal : '',
                'parent' => $parent,
                'inv' => $inv,
                'ov_l30' => $ov,
                'temu_l30' => ($this->channel === 'temu' || $this->channel === 'temu2')
                    ? (float) ($l30Overlay[$sku] ?? 0)
                    : $ov,
                'al30' => $al30,
                'live' => round((float) ($metric->{$priceCol} ?? 0), 2),
                'lp' => $lpShip['lp'],
                'ship' => $lpShip['ship'],
                'views' => $views,
                'cvr' => $views > 0
                    ? round(((($this->channel === 'temu' || $this->channel === 'temu2')
                        ? (float) ($l30Overlay[$sku] ?? 0)
                        : $ov) / $views) * 100, 2)
                    : 0.0,
                'lmp' => $lmpBySku[$sku] ?? 0.0,
                'std_price' => $stdBySku[$sku] ?? 0.0,
                'amz_price' => (float) ($amzBySku[$sku]['price'] ?? 0),
                'saved_sprice' => $savedBySku[$sku] ?? 0.0,
                'pushed_sprice' => $pushedBySku[$sku] ?? 0.0,
                'age_days' => $ageDaysBySku[$sku] ?? null,
                'dil' => $inv > 0 ? round((((float) ($shopify->quantity ?? 0)) / $inv) * 100, 2) : 0.0,
            ];
        }

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function shouldEnqueuePush(array $row, float $next, bool $dryRun = false): bool
    {
        if (! in_array($this->channel, self::PUSH_CHANNELS, true)) {
            return false;
        }
        $live = (float) ($row['live'] ?? 0);
        if (! ($live > 0) || ! ($next > 0)) {
            return false;
        }

        return ! ChannelLivePriceSync::shouldSkipPushAndRepair($this->channel, $row, $next, $dryRun);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<string, object>
     */
    protected function keyBySku($rows)
    {
        return $rows->keyBy(static fn ($r) => strtoupper(trim((string) $r->sku)));
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array{price: float, l30: float}>
     */
    protected function amazonBySku(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('amazon_datsheets')) {
            return [];
        }
        $out = [];
        foreach (AmazonDatasheet::query()->whereIn('sku', $skus)->get(['sku', 'price', 'units_ordered_l30']) as $row) {
            $sku = strtoupper(trim((string) $row->sku));
            if ($sku === '' || isset($out[$sku])) {
                continue;
            }
            $out[$sku] = [
                'price' => round((float) ($row->price ?? 0), 2),
                'l30' => (float) ($row->units_ordered_l30 ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, float>
     */
    public static function amazonStdBySku(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('amazon_data_view')) {
            return [];
        }
        $out = [];
        foreach (AmazonDataView::query()->whereIn('sku', $skus)->get(['sku', 'value']) as $row) {
            $val = is_array($row->value)
                ? $row->value
                : (json_decode((string) ($row->value ?? ''), true) ?: []);
            $std = $val['STANDARD_PRICE'] ?? null;
            if (! is_numeric($std) || (float) $std <= 0) {
                continue;
            }
            $sku = strtoupper(trim((string) $row->sku));
            if ($sku === '' || isset($out[$sku])) {
                continue;
            }
            $out[$sku] = round((float) $std, 2);
        }

        return $out;
    }

    /**
     * /temu1-data and /temu2-decrease Temu L30: orders in the channel-master L30 window.
     * Never temu_metrics.quantity_purchased_l30.
     *
     * @param  list<string>  $skus
     * @return array<string, float>
     */
    protected function temuOrdersL30BySku(array $skus, bool $temu2): array
    {
        if ($skus === []) {
            return [];
        }
        try {
            [$start, $end] = TemuShopifySalesService::channelMasterL30Window();
            $wanted = [];
            $noSpace = [];
            foreach ($skus as $sku) {
                $n = TemuShopifySalesService::normalizeTemu3Sku($sku);
                $wanted[$n] = $sku;
                $compact = str_replace(' ', '', $n);
                if ($compact !== '') {
                    $noSpace[$compact] = $sku;
                }
            }
            $out = array_fill_keys($skus, 0.0);
            $rows = $temu2
                ? TemuShopifySalesService::getTemu2OrdersTableRows($start, $end)
                : TemuShopifySalesService::getOrdersTableRows($start, $end);
            foreach ($rows as $row) {
                $raw = trim((string) ($row['contribution_sku'] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $n = TemuShopifySalesService::normalizeTemu3Sku($raw);
                $key = $wanted[$n] ?? $noSpace[str_replace(' ', '', $n)] ?? '';
                if ($key === '') {
                    continue;
                }
                $out[$key] = ($out[$key] ?? 0.0) + (float) ($row['quantity_purchased'] ?? 0);
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('[DilRuleSpriceApply] temu orders L30 overlay failed', [
                'channel' => $this->channel,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, float>
     */
    protected function temu3L30BySku(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('temu3_api_orders')) {
            return [];
        }
        try {
            [$start, $end] = TemuShopifySalesService::temu3SheetL30Window();
            $wanted = array_fill_keys($skus, true);
            $out = [];
            // Temu 3 API orders (app:fetch-temu3-orders): listing SKU = display_sku, ext_code is the fallback.
            $skuExpr = "COALESCE(NULLIF(display_sku, ''), NULLIF(ext_code, ''))";
            foreach (
                Temu3ApiOrder::query()
                    ->whereBetween('parent_order_time', [$start, $end])
                    ->where(function ($q) {
                        $q->whereNull('order_status_text')
                            ->orWhereRaw('UPPER(order_status_text) NOT IN (?, ?)', ['CANCELED', 'CANCELLED']);
                    })
                    ->whereRaw("{$skuExpr} IS NOT NULL")
                    ->selectRaw("{$skuExpr} as contribution_sku, SUM(quantity) as qty")
                    ->groupBy('contribution_sku')
                    ->get() as $row
            ) {
                $raw = strtoupper(trim((string) $row->contribution_sku));
                $sku = TemuShopifySalesService::normalizeTemu3Sku($raw);
                $key = isset($wanted[$sku]) ? $sku : (isset($wanted[$raw]) ? $raw : '');
                if ($key === '') {
                    continue;
                }
                $out[$key] = ($out[$key] ?? 0.0) + (float) ($row->qty ?? 0);
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('[DilRuleSpriceApply] temu3 L30 overlay failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, float>
     */
    protected function listingDilByKey(array $rows): array
    {
        $sums = [];
        foreach ($rows as $row) {
            $key = $this->listingKey($row);
            if ($key === '') {
                continue;
            }
            if (! isset($sums[$key])) {
                $sums[$key] = ['inv' => 0.0, 'l30' => 0.0];
            }
            $sums[$key]['inv'] += (float) ($row['inv'] ?? 0);
            $sums[$key]['l30'] += (float) ($row['ov_l30'] ?? 0);
        }
        $out = [];
        foreach ($sums as $key => $sum) {
            $out[$key] = $sum['inv'] > 0 ? ($sum['l30'] / $sum['inv']) * 100 : 0.0;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function listingKey(array $row): string
    {
        $goodsId = trim((string) ($row['goods_id'] ?? ''));
        if ($goodsId !== '' && $goodsId !== '0') {
            return 'goods:'.$goodsId;
        }
        $itemId = trim((string) ($row['item_id'] ?? ''));
        if ($itemId !== '' && $itemId !== '0') {
            return 'item:'.$itemId;
        }
        $parent = strtoupper(trim((string) ($row['parent'] ?? '')));
        if ($parent !== '') {
            return 'parent:'.$parent;
        }
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));

        return $sku !== '' ? 'sku:'.$sku : '';
    }

    /**
     * @return array{lp: float, ship: float}
     */
    protected function lpAndShip(?ProductMaster $master): array
    {
        if (! $master) {
            return ['lp' => 0.0, 'ship' => 0.0];
        }
        $values = is_array($master->Values)
            ? $master->Values
            : (is_string($master->Values) ? json_decode($master->Values, true) : []);
        $lp = 0.0;
        foreach ((array) $values as $k => $v) {
            if (strtolower((string) $k) === 'lp') {
                $lp = (float) $v;
                break;
            }
        }
        if (! ($lp > 0) && isset($master->lp)) {
            $lp = (float) $master->lp;
        }
        if (in_array($this->channel, ['temu', 'temu2', 'temu3'], true)) {
            $ship = ProductMasterTemuShip::forPricing(is_array($values) ? $values : [], $master);
        } else {
            $ship = isset($values['ship']) ? (float) $values['ship'] : (float) ($master->ship ?? 0);
        }

        return ['lp' => $lp, 'ship' => $ship];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function saveSprice(string $sku, float $sprice, array $row, float $margin): void
    {
        $sku = strtoupper(trim($sku));
        $cfg = $this->channelConfig();
        $viewClass = $cfg['view'];
        $view = $viewClass::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->first()
            ?? new $viewClass(['sku' => $sku]);

        $existing = is_array($view->value) ? $view->value : [];
        if (! $view->exists) {
            $view->sku = $sku;
        }

        $sprice = round($sprice, 2);
        $lp = (float) ($row['lp'] ?? 0);
        $ship = ! empty($cfg['exclude_ship']) ? 0.0 : (float) ($row['ship'] ?? 0);
        $sgpft = $sprice > 0 ? round((($sprice * $margin - $ship - $lp) / $sprice) * 100, 2) : 0.0;
        $sgroi = $lp > 0 ? round((($sprice * $margin - $lp - $ship) / $lp) * 100, 2) : 0.0;
        if (in_array($this->channel, ['temu', 'temu2', 'temu3'], true) && $lp > 0) {
            $temuSgroi = TemuShopifySalesService::sgroiAtSprice($sprice, $lp, $ship, 0.0);
            if ($temuSgroi !== null) {
                $sgroi = round($temuSgroi, 2);
            }
        }

        unset($existing['SPRICE_CLEARED']);
        $existing['SPRICE'] = $sprice;
        $existing['sprice'] = $sprice;
        $existing['has_custom_sprice'] = true;
        $existing['SGPFT'] = $sgpft;
        $existing['SGROI'] = $sgroi;
        $existing['SROI'] = $sgroi;
        $existing['SPRICE_STATUS'] = 'applied';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();

        $view->value = $existing;
        $view->save();
    }

    /**
     * Copy another channel's Dil store once, only when this channel has no row yet.
     * Later saves stay on $channel and do not write $source.
     */
    public static function copyDilGroiStoreIfMissing(string $channel, string $source): void
    {
        $channel = self::normalizeChannel($channel);
        $source = self::normalizeChannel($source);
        if ($channel === '' || $source === '' || $channel === $source) {
            return;
        }

        $destKey = $channel.'_dil_vs_groi';
        if (ChannelTabulatorColumnSetting::query()->where('channel_name', $destKey)->exists()) {
            return;
        }

        $sourceRow = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', $source.'_dil_vs_groi')
            ->first();
        $visibility = is_array($sourceRow?->visibility) ? $sourceRow->visibility : null;
        if ($visibility === null || $visibility === []) {
            return;
        }

        ChannelTabulatorColumnSetting::query()->firstOrCreate(
            ['channel_name' => $destKey],
            [
                'visibility' => $visibility,
                'column_order' => $sourceRow->column_order,
            ]
        );
    }

    /**
     * @return array{rules:list<array{key:string,label:string,min:float,max:float,groi:float}>,cvr_adj:array{down_lt:float,down_adj:float,up_gt:float,up_adj:float}}
     */
    public function loadDilGroiStore(): array
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', $this->channel.'_dil_vs_groi')
            ->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($saved) ? $saved : null);
        $this->lmpCap = $unpacked['lmp_cap'] ?? null;
        $this->diffAdj = $unpacked['diff_adj'] ?? null;
        $this->ageAdj = in_array($this->channel, self::AGE_ADJ_CHANNELS, true) && is_array($unpacked['age_adj'] ?? null) && $unpacked['age_adj'] !== []
            ? $unpacked['age_adj']
            : null;
        $cn = $unpacked['clearance_nroi'] ?? null;
        $this->clearanceNroi = (is_numeric($cn) && (float) $cn >= 0) ? (float) $cn : 0.0;
        if ($unpacked['rules'] === []) {
            $unpacked['rules'] = AmazonDilGroiRule::defaultsForChannel($this->channel);
        } elseif (AmazonDilGroiRule::usesZeroToZero($this->channel)) {
            $unpacked['rules'] = AmazonDilGroiRule::ensureZeroToZero($unpacked['rules']);
        }

        return $unpacked;
    }

    /** @param  list<string>  $skus */
    protected function normalizeSkuList(array $skus): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($s) => strtoupper(trim((string) $s)),
            $skus
        ))));
    }

    protected function log(?callable $logger, string $msg): void
    {
        if ($logger) {
            $logger($msg);
        }
    }

    protected function takeHome(): float
    {
        $margin = MarketplacePercentage::takeHomeForPromoChannel($this->channel);
        if (! ($margin > 0) || $margin > 1) {
            $margin = $margin > 1 ? $margin / 100 : 0.80;
        }

        return $margin;
    }

    protected function isInactiveListing(object $metric): bool
    {
        $status = strtolower(trim((string) ($metric->listing_status ?? $metric->status ?? '')));
        if ($status === '') {
            return false;
        }

        return (bool) preg_match('/\b(ended|inactive|offline|delisted|disabled|unpublished)\b/', $status);
    }

    /**
     * @return array{
     *     metric: class-string,
     *     view: class-string,
     *     price: string,
     *     l30: string|null,
     *     views: string|null,
     *     group: string,
     *     exclude_ship: bool,
     *     zero_sold_min_groi: bool,
     *     match_or_nearest: bool,
     *     cvr_adj: bool,
     *     cvr_adj_requires_views: bool,
     *     amz_floor: bool,
     *     a_l30: bool,
     *     live_is_base: bool
     * }
     */
    public function channelConfig(): array
    {
        $base = [
            'l30' => 'l30',
            'views' => null,
            'group' => 'sku',
            'exclude_ship' => false,
            'zero_sold_min_groi' => true,
            'match_or_nearest' => false,
            'cvr_adj' => false,
            'cvr_adj_requires_views' => false,
            'amz_floor' => false,
            'a_l30' => false,
            'live_is_base' => false,
        ];

        $specific = match ($this->channel) {
            'newegg' => [
                'metric' => NeweggMetric::class,
                'view' => NeweggDataView::class,
                'price' => 'price',
                'amz_floor' => true,
            ],
            'temu' => [
                'metric' => TemuMetric::class,
                'view' => TemuDataView::class,
                'price' => 'base_price',
                'l30' => 'quantity_purchased_l30',
                'views' => 'product_impressions_l30',
                'group' => 'goods_id',
                'cvr_adj' => true,
                'live_is_base' => true,
            ],
            'temu2' => [
                'metric' => Temu2Metric::class,
                'view' => Temu2DataView::class,
                'price' => 'base_price',
                'l30' => 'quantity_purchased_l30',
                'views' => 'product_impressions_l30',
                'group' => 'goods_id',
                'zero_sold_min_groi' => false,
                'match_or_nearest' => true,
                'cvr_adj' => true,
                'live_is_base' => true,
            ],
            'temu3' => [
                'metric' => Temu3Pricing::class,
                'view' => Temu3DataView::class,
                'price' => 'base_price',
                'l30' => null,
                'group' => 'goods_id',
                'zero_sold_min_groi' => false,
                'match_or_nearest' => true,
                'live_is_base' => true,
            ],
            'doba' => [
                'metric' => DobaMetric::class,
                'view' => DobaDataView::class,
                'price' => 'anticipated_income',
                'l30' => 'quantity_l30',
                'views' => 'impressions',
                'group' => 'item_id',
            ],
            'wayfair' => [
                'metric' => WayfairPricingPrice::class,
                'view' => WayfairDataView::class,
                'price' => 'price',
                'l30' => null,
                'exclude_ship' => true,
                'a_l30' => true,
            ],
            'walmart' => [
                'metric' => WalmartMetrics::class,
                'view' => WalmartDataView::class,
                'price' => 'price',
                'l30' => 'l30',
            ],
            default => [
                'metric' => FbMarketplacePriceSoldData::class,
                'view' => FBMarketplaceDataView::class,
                'price' => 'price',
                'l30' => 'sold',
                'exclude_ship' => true,
            ],
        };

        return array_merge($base, $specific);
    }
}
