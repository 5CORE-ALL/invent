<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifyB2CDailyData;
use App\Models\ShopifySku;
use App\Models\Shopifyb2cDataView;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Page-less CVR Disc → S PRC. 0 Sold Dil/Min ROI is removed; Sprc Dil owns 0 Sold on the page.
 * Writes shopifyb2c_data_view SPRICE + PEF_CPN_PCT even if /shopify-b2c-pricing is closed.
 */
class ShopifyB2cRuleSpriceApplyService
{
    public function __construct(
        private readonly PefCvrCpnAutoApplyService $cvrCpnRules
    ) {}

    /**
     * @param  list<string>|null  $onlySkus
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    public function run(bool $dryRun = false, ?int $limit = null, ?array $onlySkus = null, ?callable $logger = null): array
    {
        $cvrRules = $this->loadCvrRules();
        $zeroRules = [];
        $zeroMinRoi = 0.0;
        $margin = MarketplacePercentage::takeHomeForPromoChannel('shopify_b2c');
        if (! ($margin > 0)) {
            $margin = 0.95;
        }

        $this->log($logger, 'Loaded CVR slabs='.count($cvrRules).' (0 Sold Dil rule removed)');

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
                    $cvrRules,
                    $zeroRules,
                    $zeroMinRoi,
                    $margin,
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
                            $computed = $this->computeTarget($row, $cvrRules, $zeroRules, $zeroMinRoi, $margin);
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
                'amz' => (float) ($amzPrices[$sku]->price ?? 0),
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
     * @return array{sprice:float,prmt:float,cpn:float}|null
     */
    protected function computeTarget(array $row, array $cvrRules, array $zeroRules, float $zeroMinRoi, float $margin): ?array
    {
        $inv = (float) ($row['inv'] ?? 0);
        $dil = (float) ($row['dil'] ?? 0);
        $cvr = (float) ($row['cvr'] ?? 0);
        $sold = (float) ($row['b2c_l30'] ?? 0);
        $zeroSold = $sold <= 0;
        $prmt = 0.0;
        $cpn = $inv > 0 ? $this->cvrCpnRules->cpnForCvr($cvr, $cvrRules) : 0.0;

        $sprice = 0.0;
        if ($zeroSold) {
            // 0 Sold Dil / Min ROI rule removed. Sprc Dil on /shopify-b2c-pricing owns 0 Sold.
            return null;
        } else {
            $std = (float) ($row['std'] ?? 0);
            if (! ($std > 0)) {
                return null;
            }
            $t = min(99.99, max(0, $prmt + $cpn));
            $sprice = $t > 0 ? round($std * (1 - $t / 100), 2) : round($std, 2);
        }

        $amz = (float) ($row['amz'] ?? 0);
        if ($sprice > 0 && $amz > 0 && $sprice < $amz) {
            $sprice = round($amz, 2);
        }

        if (! is_finite($sprice) || $sprice < 0.01) {
            return null;
        }

        return [
            'sprice' => $sprice,
            'prmt' => round($prmt, 2),
            'cpn' => round($cpn, 2),
        ];
    }

    /**
     * @param  array{saved_sprice:float,saved_prmt:?float,saved_cpn:?float}  $row
     * @param  array{sprice:float,prmt:float,cpn:float}  $computed
     */
    protected function isUnchanged(array $row, array $computed): bool
    {
        if (abs(((float) ($row['saved_sprice'] ?? 0)) - $computed['sprice']) >= 0.005) {
            return false;
        }
        if (abs(((float) ($row['saved_prmt'] ?? 0)) - $computed['prmt']) >= 0.005) {
            return false;
        }

        return abs(((float) ($row['saved_cpn'] ?? 0)) - $computed['cpn']) < 0.005;
    }

    /**
     * @param  array{sprice:float,prmt:float,cpn:float}  $computed
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
        $existing['AMZ_SUGG_APPLIED'] = false;
        $existing['SPRICE_STATUS'] = 'saved';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();

        $view->value = $existing;
        $view->save();
    }

    /** @return list<array{key:string,label:string,cpn:float}> */
    protected function loadCvrRules(): array
    {
        $defaults = [
            ['key' => '0.01-1', 'label' => '0.01–1%', 'cpn' => 9],
            ['key' => '1-1.5', 'label' => '1–1.5%', 'cpn' => 8],
            ['key' => '1.5-2', 'label' => '1.5–2%', 'cpn' => 7],
            ['key' => '2-3', 'label' => '2–3%', 'cpn' => 6],
            ['key' => '3-4', 'label' => '3–4%', 'cpn' => 5],
            ['key' => '4-5', 'label' => '4–5%', 'cpn' => 4],
            ['key' => '5-6', 'label' => '5–6%', 'cpn' => 3],
            ['key' => '6-6.5', 'label' => '6–6.5%', 'cpn' => 2],
            ['key' => '6.5-7', 'label' => '6.5–7%', 'cpn' => 1],
            ['key' => 'gt-7', 'label' => '> 7%', 'cpn' => 0],
        ];

        return $this->loadStoredRules('shopify_b2c_cvr_vs_cpn', $defaults, 'cpn');
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
