<?php

namespace App\Services;

use App\Http\Controllers\Channels\ChannelMasterController;
use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifyB2CDailyData;
use App\Models\ShopifySku;
use App\Models\Shopifyb2cDataView;
use App\Support\AmazonDilGroiRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Page-less Sprc Dil → S PRC (same as /shopify-b2c-pricing / new-temuone).
 * Sprc Dil stays the Dil suggestion. S PRC uses A Price when that suggestion is below Amz, and caps to A Price when it is above.
 * Dil slabs are Target SNROI (Ads% = Shopify TCOS / page Ads badge).
 * Writes shopifyb2c_data_view SPRICE even if /shopify-b2c-pricing is closed.
 */
class ShopifyB2cRuleSpriceApplyService
{
    /**
     * @param  list<string>|null  $onlySkus
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    public function run(bool $dryRun = false, ?int $limit = null, ?array $onlySkus = null, ?callable $logger = null): array
    {
        $dilStore = $this->loadDilGroiStore();
        $dilRules = $dilStore['rules'];
        $cvrAdj = $dilStore['cvr_adj'];
        $zeroRules = [];
        $zeroMinRoi = 0.0;
        $margin = MarketplacePercentage::takeHomeForPromoChannel('shopify_b2c');
        if (! ($margin > 0)) {
            $margin = 0.95;
        }
        $adsPct = $this->channelAdsPercent();

        $this->log($logger, 'Loaded Dil slabs='.count($dilRules)
            .' ads%='.$adsPct.' target=SNROI');

        $stats = [
            'candidates' => 0,
            'applied' => 0,
            'skipped_unchanged' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $lock = Cache::lock('shopify-b2c-rule-sprice-apply', 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another Shopify B2C S PRC apply is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'stats' => $stats];
        }

        try {
            $applied = 0;
            ShopifySku::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->when($onlySkus, function ($q) use ($onlySkus) {
                    $keys = array_values(array_unique(array_filter(array_map(
                        static fn ($s) => strtoupper(trim((string) $s)),
                        $onlySkus
                    ))));
                    $q->where(function ($inner) use ($keys) {
                        foreach ($keys as $sku) {
                            $inner->orWhereRaw('UPPER(TRIM(sku)) = ?', [$sku]);
                        }
                    });
                })
                ->orderBy('id')
                ->chunkById(150, function ($rows) use (
                    $dilRules,
                    $cvrAdj,
                    $zeroRules,
                    $zeroMinRoi,
                    $margin,
                    $adsPct,
                    $dryRun,
                    $limit,
                    $logger,
                    &$stats,
                    &$applied
                ) {
                    if ($limit !== null && $applied >= $limit) {
                        return false;
                    }

                    $chunk = $this->hydrateChunk($rows);
                    $stats['candidates'] += count($chunk);

                    foreach ($chunk as $row) {
                        if ($limit !== null && $applied >= $limit) {
                            return false;
                        }
                        try {
                            $computed = $this->computeTarget($row, [], $zeroRules, $zeroMinRoi, $margin, $dilRules, $cvrAdj, $adsPct);
                            if ($computed === null) {
                                $stats['skipped']++;
                                continue;
                            }
                            if ($this->isUnchanged($row, $computed)) {
                                $stats['skipped_unchanged']++;
                                continue;
                            }
                            if (! $dryRun) {
                                $this->saveSpriceAndPromo($row['sku'], $computed);
                            }
                            $applied++;
                            $stats['applied']++;
                        } catch (Throwable $e) {
                            $stats['errors'][] = $row['sku'].': '.$e->getMessage();
                            Log::warning('[ShopifyB2cRuleSpriceApply] sku failed', [
                                'sku' => $row['sku'] ?? '',
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });
        } finally {
            $lock->release();
        }

        $this->log($logger, sprintf(
            'Done. candidates=%d applied=%d unchanged=%d skipped=%d',
            $stats['candidates'],
            $stats['applied'],
            $stats['skipped_unchanged'],
            $stats['skipped']
        ));

        if (! $dryRun && (int) ($stats['applied'] ?? 0) > 0) {
            Cache::forget(\App\Http\Controllers\MarketPlace\Shopifyb2cController::TABULAR_CACHE_KEY);
        }

        return ['dry_run' => $dryRun, 'stats' => $stats];
    }

    /**
     * @param  iterable<ShopifySku>  $rows
     * @return list<array<string, mixed>>
     */
    protected function hydrateChunk($rows): array
    {
        $skus = [];
        $lookupSkus = [];
        $shopifyBySku = [];
        foreach ($rows as $item) {
            $raw = trim((string) $item->sku);
            $sku = strtoupper($raw);
            if ($sku === '' || str_contains($sku, 'PARENT')) {
                continue;
            }
            $skus[] = $sku;
            $lookupSkus[] = $raw;
            $lookupSkus[] = $sku;
            $shopifyBySku[$sku] = $item;
        }
        $skus = array_values(array_unique($skus));
        $lookupSkus = array_values(array_unique(array_filter($lookupSkus)));
        if ($skus === []) {
            return [];
        }

        $masters = ProductMaster::query()
            ->whereIn('sku', $lookupSkus)
            ->get(['sku', 'Values', 'lp', 'ship'])
            ->keyBy(static fn ($r) => strtoupper(trim((string) $r->sku)));

        $amzPrices = AmazonDatasheet::query()
            ->whereIn('sku', $lookupSkus)
            ->get(['sku', 'price'])
            ->keyBy(static fn ($r) => strtoupper(trim((string) $r->sku)));

        $stdBySku = [];
        $savedBySku = [];
        foreach (AmazonDataView::query()->whereIn('sku', $lookupSkus)->get(['sku', 'value']) as $adv) {
            $val = $this->decodeValue($adv->value);
            $std = $val['STANDARD_PRICE'] ?? null;
            $key = strtoupper(trim((string) $adv->sku));
            if (is_numeric($std) && (float) $std > 0) {
                $stdBySku[$key] = round((float) $std, 2);
            }
        }
        foreach (Shopifyb2cDataView::query()->whereIn('sku', $lookupSkus)->get(['sku', 'value']) as $view) {
            $val = $this->decodeValue($view->value);
            $savedBySku[strtoupper(trim((string) $view->sku))] = $val;
        }

        $soldBySku = ShopifyB2CDailyData::query()
            ->whereIn('sku', $lookupSkus)
            ->where('period', 'l30')
            ->where('financial_status', '!=', 'refunded')
            ->selectRaw('UPPER(TRIM(sku)) as sku_key, SUM(quantity) as total_quantity')
            ->groupByRaw('UPPER(TRIM(sku))')
            ->pluck('total_quantity', 'sku_key')
            ->all();

        $out = [];
        foreach ($skus as $sku) {
            $shopify = $shopifyBySku[$sku] ?? null;
            $master = $masters[$sku] ?? null;
            if (! $shopify || ! $master) {
                continue;
            }
            $inv = (float) ($shopify->inv ?? 0);
            if ($inv <= 0) {
                continue;
            }
            $values = is_array($master->Values)
                ? $master->Values
                : (is_string($master->Values) ? json_decode($master->Values, true) : []);
            $lp = (float) ($values['lp'] ?? ($master->lp ?? 0));
            $ship = (float) ($values['ship'] ?? ($master->ship ?? 0));
            $ovL30 = (float) ($shopify->quantity ?? 0);
            $b2cL30 = (float) ($soldBySku[$sku] ?? 0);
            $views = (float) ($shopify->views ?? 0);
            $saved = $savedBySku[$sku] ?? [];

            $out[] = [
                'sku' => $sku,
                'inv' => $inv,
                'ov_l30' => $ovL30,
                'b2c_l30' => $b2cL30,
                'views' => $views,
                'dil' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0.0,
                'cvr' => $views > 0 ? round(($b2cL30 / $views) * 100, 2) : 0.0,
                'lp' => $lp,
                'ship' => $ship,
                'std' => $stdBySku[$sku] ?? 0.0,
                'amz' => isset($amzPrices[$sku]) ? (float) ($amzPrices[$sku]->price ?? 0) : 0.0,
                'amz_sugg' => ! empty($saved['AMZ_SUGG_APPLIED']),
                'saved_sprice' => is_numeric($saved['SPRICE'] ?? null) ? (float) $saved['SPRICE'] : 0.0,
                'saved_prmt' => is_numeric($saved['PEF_PRMT_PCT'] ?? null) ? (float) $saved['PEF_PRMT_PCT'] : null,
                'saved_cpn' => is_numeric($saved['PEF_CPN_PCT'] ?? null) ? (float) $saved['PEF_CPN_PCT'] : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{key:string,label:string,cpn:float}>  $cvrRules
     * @param  array{red:float,green:float,pink:float}  $zeroRules
     * @param  list<array{key:string,label:string,min:float,max:float,groi:float}>  $dilRules
     * @param  array{down_lt:float,down_adj:float,up_gt:float,up_adj:float}|null  $cvrAdj
     * @return array{sprice:float,prmt:float,cpn:float,amz_sugg:bool}|null
     */
    protected function computeTarget(array $row, array $cvrRules, array $zeroRules, float $zeroMinRoi, float $margin, array $dilRules = [], ?array $cvrAdj = null, float $adsPct = 0.0): ?array
    {
        $dil = (float) ($row['dil'] ?? 0);
        $cvr = (float) ($row['cvr'] ?? 0);
        $sold = (float) ($row['b2c_l30'] ?? 0);
        $zeroSold = $sold <= 0;
        $prmt = 0.0;
        $cpn = 0.0;
        $amz = (float) ($row['amz'] ?? 0);
        $amzSugg = ! empty($row['amz_sugg']) && $amz > 0;

        $sprice = 0.0;
        if ($amzSugg) {
            $sprice = round($amz, 2);
        } else {
            $lp = (float) ($row['lp'] ?? 0);
            $ship = (float) ($row['ship'] ?? 0);
            $target = $zeroSold
                ? AmazonDilGroiRule::minTarget($dilRules)
                : AmazonDilGroiRule::groiForDil($dil, $dilRules);
            if ($target !== null && $lp > 0 && $margin > 0) {
                $target = AmazonDilGroiRule::adjustGroiForCvrArrow(
                    $target,
                    $cvr,
                    (float) ($row['cvr_60'] ?? 0),
                    $cvrAdj
                );
                $raw = AmazonDilGroiRule::suggestedPrice($lp, $ship, $target, $adsPct, $margin);
                $sprice = ($raw !== null && $raw >= 0.01) ? round($raw, 2) : 0.0;
            } elseif (! $zeroSold) {
                $std = (float) ($row['std'] ?? 0);
                if (! ($std > 0)) {
                    return null;
                }
                $sprice = round($std, 2);
            }

            if ($sprice > 0 && $amz > 0) {
                $sprice = round($amz, 2);
            }
        }

        if (! is_finite($sprice) || $sprice < 0.01) {
            return null;
        }

        return [
            'sprice' => $sprice,
            'prmt' => round($prmt, 2),
            'cpn' => round($cpn, 2),
            'amz_sugg' => $amzSugg,
        ];
    }

    /** Shopify TCOS / Ads% — same source as the /shopify-b2c-pricing Ads badge. */
    public function channelAdsPercent(): float
    {
        try {
            $snap = Cache::remember('shopify_direct_l30_snapshot_v1', 90, function () {
                return app(ChannelMasterController::class)->getShopifyDirectL30Snapshot();
            });

            return max(0.0, (float) ($snap['tcos_pct'] ?? 0));
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    /**
     * @param  array{saved_sprice:float,saved_prmt:?float,saved_cpn:?float,amz_sugg?:bool}  $row
     * @param  array{sprice:float,prmt:float,cpn:float,amz_sugg?:bool}  $computed
     */
    protected function isUnchanged(array $row, array $computed): bool
    {
        if (abs(((float) ($row['saved_sprice'] ?? 0)) - $computed['sprice']) >= 0.005) {
            return false;
        }
        if (abs(((float) ($row['saved_prmt'] ?? 0)) - $computed['prmt']) >= 0.005) {
            return false;
        }
        if (! empty($row['amz_sugg']) !== ! empty($computed['amz_sugg'])) {
            return false;
        }

        return abs(((float) ($row['saved_cpn'] ?? 0)) - $computed['cpn']) < 0.005;
    }

    /**
     * @param  array{sprice:float,prmt:float,cpn:float,amz_sugg?:bool}  $computed
     */
    protected function saveSpriceAndPromo(string $sku, array $computed): void
    {
        $view = Shopifyb2cDataView::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
            ->first()
            ?? new Shopifyb2cDataView(['sku' => strtoupper(trim($sku))]);

        $existing = $this->decodeValue($view->value);
        if (! $view->exists) {
            $view->sku = strtoupper(trim($sku));
        }

        $existing['SPRICE'] = round($computed['sprice'], 2);
        $existing['PEF_PRMT_PCT'] = round($computed['prmt'], 2);
        $existing['PEF_CPN_PCT'] = round($computed['cpn'], 2);
        $existing['AMZ_SUGG_APPLIED'] = ! empty($computed['amz_sugg']);
        $existing['SPRICE_STATUS'] = 'saved';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();

        $view->value = $existing;
        $view->save();
    }

    /**
     * @return array{rules:list<array{key:string,label:string,min:float,max:float,groi:float}>,cvr_adj:array{down_lt:float,down_adj:float,up_gt:float,up_adj:float}}
     */
    protected function loadDilGroiStore(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', 'shopify_b2c_dil_vs_groi')->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($saved) ? $saved : null);
        if ($unpacked['rules'] === []) {
            $unpacked['rules'] = AmazonDilGroiRule::defaultsForChannel('shopify_b2c');
        } elseif (AmazonDilGroiRule::usesZeroToZero('shopify_b2c')) {
            $unpacked['rules'] = AmazonDilGroiRule::ensureZeroToZero($unpacked['rules']);
        }

        return $unpacked;
    }

    /** @return list<array{key:string,label:string,min:float,max:float,groi:float}> */
    protected function loadDilGroiRules(): array
    {
        return $this->loadDilGroiStore()['rules'];
    }

    /** @return array{red:float,green:float,pink:float} */
    protected function loadZeroSoldRules(): array
    {
        $defaults = ChannelPromoPricingController::sharedZeroSoldPrcDefaults();
        $rules = $this->loadStoredRules('shopify_b2c_zero_sold_prc', $defaults, 'groi');
        $out = ['red' => 50.0, 'green' => 60.0, 'pink' => 70.0];
        foreach ($rules as $rule) {
            $k = (string) ($rule['key'] ?? '');
            if (isset($out[$k]) && is_numeric($rule['groi'] ?? null)) {
                $out[$k] = (float) $rule['groi'];
            }
        }

        return $out;
    }

    /**
     * @param  list<array{key:string,label:string}>  $defaults
     * @return list<array<string, mixed>>
     */
    protected function loadStoredRules(string $store, array $defaults, string $valueKey): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', $store)->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        $byKey = [];
        if (is_array($saved)) {
            foreach ($saved as $item) {
                if (is_array($item) && ($item['key'] ?? '') !== '') {
                    $byKey[(string) $item['key']] = $item;
                }
            }
        }

        $rules = [];
        foreach ($defaults as $def) {
            $k = $def['key'];
            $raw = $byKey[$k][$valueKey] ?? ($valueKey === 'cpn' ? ($byKey[$k]['disc'] ?? null) : null);
            $val = is_numeric($raw) ? (float) $raw : (float) $def[$valueKey];
            $rules[] = [
                'key' => $k,
                'label' => $def['label'],
                $valueKey => $val < 0 ? 0.0 : $val,
            ];
        }

        return $rules;
    }

    /** @param  array{red:float,green:float,pink:float}  $zeroRules */
    protected function zeroSoldGroi(float $dil, array $zeroRules, float $minRoi = 0.0): ?float
    {
        $minGroi = null;
        foreach (['red', 'green', 'pink'] as $key) {
            if (! isset($zeroRules[$key]) || ! is_numeric($zeroRules[$key])) {
                continue;
            }
            $g = (float) $zeroRules[$key];
            if ($minGroi === null || $g < $minGroi) {
                $minGroi = $g;
            }
        }
        $target = $minGroi;
        if ($minRoi > 0 && ($target === null || $minRoi > $target)) {
            $target = $minRoi;
        }

        return $target;
    }

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

    protected function log(?callable $logger, string $msg): void
    {
        if ($logger) {
            $logger($msg);
        }
    }
}
