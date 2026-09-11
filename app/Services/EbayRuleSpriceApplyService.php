<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbaySkuCompetitor;
use App\Models\EbayThreeDataView;
use App\Models\EbayTwoDataView;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\AmazonDilGroiRule;
use App\Support\Marketplace\EbayListingEnded;
use App\Support\PushedListingPrice;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Page-less Sprc Dil → S PRC for eBay 1 / 2 / 3 tabulator pages.
 * Listing Dil (Σ OV L30 ÷ Σ INV), nearest slab, CVR overlay, then Amazon LMP cap:
 * Dil below LMP stays Dil; Dil at/above LMP caps only when SGROI at LMP ≥ 20%.
 * INV > 0 only — same as ebayDilGroiMetaForRow.
 */
class EbayRuleSpriceApplyService
{
    public const LMP_SGROI_MIN = AmazonDilGroiRule::LMP_SGROI_MIN;

    public const CHANNELS = ['ebay1', 'ebay2', 'ebay3'];

    private readonly string $channel;

    public function __construct(string $channel = 'ebay3')
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
            'ebay', 'ebayone', 'ebay_1' => 'ebay1',
            'ebaytwo', 'ebay_2' => 'ebay2',
            'ebaythree', 'ebay_3' => 'ebay3',
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
                    $live = (float) ($row['live'] ?? 0);
                    if ($live > 0 && ! ChannelLivePriceSync::shouldSkipPushAndRepair($this->channel, $row, $next, $dryRun)) {
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
                    Log::warning('[EbayRuleSpriceApply] sku failed', [
                        'channel' => $this->channel,
                        'sku' => $row['sku'] ?? '',
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            $stats['errors'][] = $e->getMessage();
            Log::error('[EbayRuleSpriceApply] hydrate/run failed', [
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
     * Dil S PRC ≠ live eBay Price (listed, INV > 0, not ended).
     *
     * @return list<array{sku: string, price: float}>
     */
    public function collectPushTasks(?array $onlySkus = null): array
    {
        $store = $this->loadDilGroiStore();
        $margin = $this->takeHome();

        $out = [];
        foreach ($this->hydrateAll($onlySkus) as $row) {
            $computed = $this->computeTarget($row, $store['rules'], $store['cvr_adj'], $margin);
            if ($computed === null) {
                continue;
            }
            $live = (float) ($row['live'] ?? 0);
            $next = $computed['sprice'];
            if (! ($live > 0) || ChannelLivePriceSync::shouldSkipPushAndRepair($this->channel, $row, $next, false)) {
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
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0) || ! ($margin > 0)) {
            return null;
        }
        $lp = (float) ($row['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }

        $rule = AmazonDilGroiRule::matchOrNearest((float) ($row['dil'] ?? 0), $dilRules);
        if ($rule === null) {
            return null;
        }

        $groi = AmazonDilGroiRule::adjustGroiForCvrLevel(
            (float) $rule['groi'],
            (float) ($row['cvr'] ?? 0),
            $cvrAdj
        );
        $ship = (float) ($row['ship'] ?? 0);
        $raw = round(($lp * (1 + $groi / 100) + $ship) / $margin, 2);
        if (! is_finite($raw) || $raw < 0.01) {
            return null;
        }

        $sprice = AmazonDilGroiRule::capSpriceToLmp($raw, (float) ($row['lmp'] ?? 0), $lp, $ship, $margin);

        return [
            'sprice' => $sprice,
            'groi' => $groi,
        ];
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

        $metrics = $metricClass::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->where('ebay_price', '>', 0)
            ->when($only !== [], function ($q) use ($only) {
                $q->where(function ($inner) use ($only) {
                    foreach ($only as $sku) {
                        $inner->orWhereRaw('UPPER(TRIM(sku)) = ?', [$sku]);
                    }
                });
            })
            ->orderBy('id')
            ->get(EbayListingEnded::withStatusColumn($cfg['metric_table'], [
                'id', 'sku', 'item_id', 'ebay_price', 'ebay_l30', 'ebay_l60', 'views',
            ]));

        $bySku = [];
        foreach ($metrics as $metric) {
            $sku = strtoupper(trim((string) $metric->sku));
            if ($sku === '' || str_contains($sku, 'PARENT')) {
                continue;
            }
            if (EbayListingEnded::isEnded($metric->listing_status ?? null)) {
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
        foreach ($viewClass::query()->whereIn('sku', $skus)->get(['sku', 'value']) as $view) {
            $val = is_array($view->value)
                ? $view->value
                : (json_decode((string) ($view->value ?? ''), true) ?: []);
            $sprice = $val['SPRICE'] ?? null;
            $key = strtoupper(trim((string) $view->sku));
            if (is_numeric($sprice) && (float) $sprice > 0) {
                $savedBySku[$key] = round((float) $sprice, 2);
            }
            $pushed = PushedListingPrice::fromValue(is_array($val) ? $val : []);
            if ($pushed !== null) {
                $pushedBySku[$key] = $pushed;
            }
        }

        $lmpLookups = $this->lmpLookupForSkus($skus);
        $lmpDetails = $lmpLookups['details'];
        $lmpLowest = $lmpLookups['lowest'];

        $draft = [];
        foreach ($skus as $sku) {
            $metric = $bySku[$sku];
            $shopify = $shopifyBySku[$sku] ?? null;
            $master = $masters[$sku] ?? null;
            $inv = (float) ($shopify->inv ?? 0);
            $ov = (float) ($shopify->quantity ?? 0);
            if (! ($ov > 0)) {
                $ov = (float) ($metric->ebay_l30 ?? 0);
            }
            $lpShip = $this->lpAndShip($master);
            $parent = strtoupper(trim((string) ($master->parent ?? '')));
            $parent = preg_replace('/^PARENT\s+/i', '', $parent) ?? '';
            $parent = strtoupper(trim($parent));
            $itemId = trim((string) ($metric->item_id ?? ''));
            $views = (float) ($metric->views ?? 0);
            $ebayL30 = (float) ($metric->ebay_l30 ?? 0);
            $lmpRow = [];
            EbaySkuCompetitor::applyToRow($lmpRow, $sku, $lmpLowest, $lmpDetails);

            $draft[] = [
                'sku' => $sku,
                'item_id' => $itemId,
                'parent' => $parent,
                'inv' => $inv,
                'ov_l30' => $ov,
                'live' => round((float) $metric->ebay_price, 2),
                'lp' => $lpShip['lp'],
                'ship' => $lpShip['ship'],
                'cvr' => $views > 0 ? round(($ebayL30 / $views) * 100, 2) : 0.0,
                'lmp' => (float) ($lmpRow['lmp_price'] ?? 0),
                'saved_sprice' => $savedBySku[$sku] ?? 0.0,
                'pushed_sprice' => $pushedBySku[$sku] ?? 0.0,
            ];
        }

        $dilByKey = $this->listingDilByKey($draft);
        foreach ($draft as $i => $row) {
            $key = $this->listingKey($row, $draft);
            $draft[$i]['dil'] = $dilByKey[$key] ?? (
                $row['inv'] > 0 ? round(($row['ov_l30'] / $row['inv']) * 100, 2) : 0.0
            );
        }

        return $draft;
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
     * @return array{details: \Illuminate\Support\Collection, lowest: \Illuminate\Support\Collection}
     */
    protected function lmpLookupForSkus(array $skus): array
    {
        $keys = [];
        foreach ($skus as $sku) {
            foreach (EbaySkuCompetitor::resolveLookupKeys($sku) as $key) {
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }
        $keys = array_values(array_unique($keys));
        if ($keys === []) {
            $empty = collect();

            return ['details' => $empty, 'lowest' => $empty];
        }

        $lmpRecords = EbaySkuCompetitor::query()
            ->where('marketplace', 'ebay')
            ->where('total_price', '>', 0)
            ->whereIn('sku', $keys)
            ->orderBy('total_price')
            ->get()
            ->groupBy(static fn ($item) => EbaySkuCompetitor::normalizeSkuKey($item->sku));

        return [
            'details' => $lmpRecords,
            'lowest' => $lmpRecords->map(static function ($items) {
                $active = EbaySkuCompetitor::withoutIgnored($items);

                return $active->isNotEmpty() ? $active->first() : null;
            }),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, float>
     */
    protected function listingDilByKey(array $rows): array
    {
        $sums = [];
        foreach ($rows as $row) {
            $key = $this->listingKey($row, $rows);
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
     * @param  list<array<string, mixed>>  $all
     */
    protected function listingKey(array $row, array $all): string
    {
        $itemId = trim((string) ($row['item_id'] ?? ''));
        if ($itemId !== '' && $itemId !== '0') {
            return 'item:'.$itemId;
        }
        $parent = strtoupper(trim((string) ($row['parent'] ?? '')));
        if ($parent !== '') {
            foreach ($all as $other) {
                if (strtoupper(trim((string) ($other['parent'] ?? ''))) !== $parent) {
                    continue;
                }
                $otherItem = trim((string) ($other['item_id'] ?? ''));
                if ($otherItem !== '' && $otherItem !== '0') {
                    return 'item:'.$otherItem;
                }
            }

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
        $viewClass = $this->channelConfig()['view'];
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
        $ship = (float) ($row['ship'] ?? 0);
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

    /**
     * @return array{metric: class-string, view: class-string, metric_table: string}
     */
    protected function channelConfig(): array
    {
        return match ($this->channel) {
            'ebay2' => [
                'metric' => Ebay2Metric::class,
                'view' => EbayTwoDataView::class,
                'metric_table' => 'ebay_2_metrics',
            ],
            'ebay3' => [
                'metric' => Ebay3Metric::class,
                'view' => EbayThreeDataView::class,
                'metric_table' => 'ebay_3_metrics',
            ],
            default => [
                'metric' => EbayMetric::class,
                'view' => EbayDataView::class,
                'metric_table' => 'ebay_metrics',
            ],
        };
    }
}
