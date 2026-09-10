<?php

namespace App\Services;

use App\Models\AliexpressDataView;
use App\Models\AliexpressMetric;
use App\Models\AmazonDatasheet;
use App\Models\BestbuyUsaProduct;
use App\Models\BestbuyUSADataView;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\DobaDataView;
use App\Models\DobaMetric;
use App\Models\FaireDataView;
use App\Models\FaireMetric;
use App\Models\FBMarketplaceDataView;
use App\Models\FbMarketplacePriceSoldData;
use App\Models\MarketplacePercentage;
use App\Models\NeweggDataView;
use App\Models\NeweggMetric;
use App\Models\PLSDataView;
use App\Models\PLSProduct;
use App\Models\ProductMaster;
use App\Models\ReverbDataView;
use App\Models\ReverbMetric;
use App\Models\SheinDataView;
use App\Models\SheinMetric;
use App\Models\SheinOrderMetric;
use App\Models\ShopifySku;
use App\Models\Temu2DataView;
use App\Models\Temu2Metric;
use App\Models\Temu3DataView;
use App\Models\Temu3Order;
use App\Models\Temu3Pricing;
use App\Models\TemuDataView;
use App\Models\TemuMetric;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Models\TiktokShopDataView;
use App\Models\TiktokTwoShopDataView;
use App\Models\TopDawgDataView;
use App\Models\TopDawgProduct;
use App\Models\WalmartDataView;
use App\Models\WalmartMetrics;
use App\Models\WayfairDataView;
use App\Models\WayfairPricingPrice;
use App\Support\AmazonDilGroiRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Page-less Sprc Dil → S PRC for Dil tabulator pages that do not have
 * their own nightly save cron (eBay / Amazon / Shopify B2C / Macys / PP do).
 *
 * Same cell math as ebay-sprc-dil: listing Dil, 0-sold min GROI (except
 * Temu 2/3), CVR overlay where the page uses it, ship excluded on
 * Wayfair / Faire / TopDawg / FB, Newegg Amz floor, LMP cap at SGROI ≥ 20%.
 */
class DilRuleSpriceApplyService
{
    public const LMP_SGROI_MIN = 20.0;

    public const CHANNELS = [
        'bestbuy',
        'aliexpress',
        'newegg',
        'temu',
        'temu2',
        'temu3',
        'reverb',
        'tiktok',
        'tiktok2',
        'doba',
        'faire',
        'shein',
        'wayfair',
        'topdawg',
        'fb_marketplace',
        'walmart',
        'pls',
    ];

    /** Channels the shared push runner can actually send. FB is save-only. */
    public const PUSH_CHANNELS = [
        'bestbuy',
        'aliexpress',
        'newegg',
        'temu',
        'temu2',
        'temu3',
        'reverb',
        'tiktok',
        'tiktok2',
        'doba',
        'faire',
        'shein',
        'wayfair',
        'topdawg',
        'walmart',
        'pls',
    ];

    private readonly string $channel;

    public function __construct(string $channel = 'bestbuy')
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
            'bb', 'best_buy', 'bestbuyusa' => 'bestbuy',
            'ae', 'ali' => 'aliexpress',
            'tt', 'tiktokshop' => 'tiktok',
            'tiktok_2', 'tiktoktwo' => 'tiktok2',
            'td', 'top_dawg' => 'topdawg',
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
        $store = $this->loadDilGroiStore();
        $dilRules = $store['rules'];
        $cvrAdj = $store['cvr_adj'];
        $margin = $this->takeHome();

        $this->log($logger, $this->channel.' loaded Dil slabs='.count($dilRules).' margin='.$margin);

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
                    if ($this->shouldEnqueuePush($row, $next)) {
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
            if (! $this->shouldEnqueuePush($row, $next)) {
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
        $cfg = $this->channelConfig();
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0) || ! ($margin > 0)) {
            return null;
        }
        $lp = (float) ($row['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }

        $sold = (float) ($row['ov_l30'] ?? 0);
        $dil = (float) ($row['dil'] ?? 0);
        $groi = null;

        if (! empty($cfg['zero_sold_min_groi']) && $sold <= 0) {
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
            $groi = AmazonDilGroiRule::adjustGroiForCvrLevel(
                $groi,
                (float) ($row['cvr'] ?? 0),
                $cvrAdj
            );
        }

        $ship = ! empty($cfg['exclude_ship']) ? 0.0 : (float) ($row['ship'] ?? 0);
        $raw = round(($lp * (1 + $groi / 100) + $ship) / $margin, 2);
        if (! is_finite($raw) || $raw < 0.01) {
            return null;
        }

        $sprice = $this->capToLmp($raw, (float) ($row['lmp'] ?? 0), $lp, $ship, $margin);

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

    public function capToLmp(float $sprice, float $lmp, float $lp, float $ship, float $margin): float
    {
        if (! ($lmp > 0) || ! ($sprice > 0) || $sprice + 0.0001 < $lmp) {
            return round($sprice, 2);
        }
        if (! ($lp > 0) || ! ($margin > 0)) {
            return round($sprice, 2);
        }
        $sgroiAtLmp = (($lmp * $margin - $lp - $ship) / $lp) * 100;
        if ($sgroiAtLmp < self::LMP_SGROI_MIN) {
            return round($sprice, 2);
        }

        return round($lmp, 2);
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
            $lmp = $val['lmp_price'] ?? $val['LMP'] ?? null;
            if (is_numeric($lmp) && (float) $lmp > 0) {
                $lmpBySku[$key] = round((float) $lmp, 2);
            }
        }

        $amzBySku = [];
        if (! empty($cfg['amz_floor']) || ! empty($cfg['a_l30'])) {
            $amzBySku = $this->amazonBySku($skus);
        }

        $l30Overlay = [];
        if ($this->channel === 'temu3') {
            $l30Overlay = $this->temu3L30BySku($skus);
        } elseif ($this->channel === 'shein') {
            $l30Overlay = $this->sheinL30BySku($skus);
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
            if (! empty($cfg['a_l30'])) {
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
                'live' => round((float) ($metric->{$priceCol} ?? 0), 2),
                'lp' => $lpShip['lp'],
                'ship' => $lpShip['ship'],
                'cvr' => $views > 0 ? round(($ov / $views) * 100, 2) : 0.0,
                'lmp' => $lmpBySku[$sku] ?? 0.0,
                'amz_price' => (float) ($amzBySku[$sku]['price'] ?? 0),
                'saved_sprice' => $savedBySku[$sku] ?? 0.0,
            ];
        }

        $dilByKey = $this->listingDilByKey($draft);
        foreach ($draft as $i => $row) {
            $key = $this->listingKey($row);
            $draft[$i]['dil'] = $dilByKey[$key] ?? (
                $row['inv'] > 0 ? round(($row['ov_l30'] / $row['inv']) * 100, 2) : 0.0
            );
        }

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function shouldEnqueuePush(array $row, float $next): bool
    {
        if (! in_array($this->channel, self::PUSH_CHANNELS, true)) {
            return false;
        }
        $live = (float) ($row['live'] ?? 0);
        if (! ($live > 0) || ! ($next > 0)) {
            return false;
        }
        $liveCompare = ! empty($this->channelConfig()['live_is_base'])
            ? round(TemuShopifySalesService::computeFullTemuPrice($live), 2)
            : $live;

        return abs($next - $liveCompare) >= 0.005;
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
    protected function temu3L30BySku(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('temu3_orders')) {
            return [];
        }
        try {
            [$start, $end] = TemuShopifySalesService::temu3SheetL30Window();
            $wanted = array_fill_keys($skus, true);
            $out = [];
            foreach (
                Temu3Order::query()
                    ->whereBetween('purchase_date', [$start, $end])
                    ->whereNotNull('contribution_sku')
                    ->where('contribution_sku', '!=', '')
                    ->selectRaw('contribution_sku, SUM(quantity_purchased) as qty')
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
     * @param  list<string>  $skus
     * @return array<string, float>
     */
    protected function sheinL30BySku(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('shein_order_metrics')) {
            return [];
        }
        try {
            $out = [];
            foreach (
                SheinOrderMetric::query()
                    ->whereIn('sku', $skus)
                    ->where('order_date', '>=', now()->subDays(30)->startOfDay())
                    ->selectRaw('sku, SUM(quantity) as qty')
                    ->groupBy('sku')
                    ->get() as $row
            ) {
                $sku = strtoupper(trim((string) $row->sku));
                if ($sku === '') {
                    continue;
                }
                $out[$sku] = (float) ($row->qty ?? 0);
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('[DilRuleSpriceApply] shein L30 overlay failed', ['error' => $e->getMessage()]);

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
        $ship = isset($values['ship']) ? (float) $values['ship'] : (float) ($master->ship ?? 0);

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

        unset($existing['SPRICE_CLEARED']);
        $existing['SPRICE'] = $sprice;
        $existing['sprice'] = $sprice;
        $existing['has_custom_sprice'] = true;
        $existing['SGPFT'] = $sgpft;
        $existing['SGROI'] = $sgroi;
        $existing['SPRICE_STATUS'] = 'applied';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();

        $view->value = $existing;
        $view->save();
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
        if ($unpacked['rules'] === []) {
            $unpacked['rules'] = AmazonDilGroiRule::defaults();
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
            'amz_floor' => false,
            'a_l30' => false,
            'live_is_base' => false,
        ];

        $specific = match ($this->channel) {
            'bestbuy' => [
                'metric' => BestbuyUsaProduct::class,
                'view' => BestbuyUSADataView::class,
                'price' => 'price',
                'l30' => 'm_l30',
            ],
            'aliexpress' => [
                'metric' => AliexpressMetric::class,
                'view' => AliexpressDataView::class,
                'price' => 'price',
                'views' => 'views',
            ],
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
            'reverb' => [
                'metric' => ReverbMetric::class,
                'view' => ReverbDataView::class,
                'price' => 'price',
                'cvr_adj' => true,
            ],
            'tiktok' => [
                'metric' => TikTokProduct::class,
                'view' => TiktokShopDataView::class,
                'price' => 'price',
                'l30' => 'sold',
                'views' => 'views',
                'cvr_adj' => true,
            ],
            'tiktok2' => [
                'metric' => TikTokProductTwo::class,
                'view' => TiktokTwoShopDataView::class,
                'price' => 'price',
                'l30' => 'sold',
                'views' => 'views',
                'cvr_adj' => true,
            ],
            'doba' => [
                'metric' => DobaMetric::class,
                'view' => DobaDataView::class,
                'price' => 'anticipated_income',
                'l30' => 'quantity_l30',
                'views' => 'impressions',
                'group' => 'item_id',
            ],
            'faire' => [
                'metric' => FaireMetric::class,
                'view' => FaireDataView::class,
                'price' => 'price',
                'exclude_ship' => true,
                'cvr_adj' => true,
            ],
            'shein' => [
                'metric' => SheinMetric::class,
                'view' => SheinDataView::class,
                'price' => 'price',
                'l30' => null,
            ],
            'wayfair' => [
                'metric' => WayfairPricingPrice::class,
                'view' => WayfairDataView::class,
                'price' => 'price',
                'l30' => null,
                'exclude_ship' => true,
                'a_l30' => true,
            ],
            'topdawg' => [
                'metric' => TopDawgProduct::class,
                'view' => TopDawgDataView::class,
                'price' => 'price',
                'l30' => 'r_l30',
                'views' => 'views',
                'exclude_ship' => true,
            ],
            'walmart' => [
                'metric' => WalmartMetrics::class,
                'view' => WalmartDataView::class,
                'price' => 'price',
                'l30' => 'l30',
            ],
            'pls' => [
                'metric' => PLSProduct::class,
                'view' => PLSDataView::class,
                'price' => 'price',
                'l30' => 'p_l30',
                'views' => 'views',
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
