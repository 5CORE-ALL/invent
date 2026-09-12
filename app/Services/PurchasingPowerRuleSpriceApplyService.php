<?php

namespace App\Services;

use App\Models\AmazonDatasheet;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\MacysPriceData;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\PurchasingPowerDataView;
use App\Models\PurchasingPowerProduct;
use App\Models\PurchasingPowerSale;
use App\Models\ShopifySku;
use App\Support\AmazonDilGroiRule;
use App\Support\MacysAmazonPriceCap;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Wipe stale SPRICE, write Sprc Dil (A Price floor), then push listed price via MCM PRI01.
 * Dil = (OV L30 / INV) × 100. Ship is excluded.
 * 0 Sold (PP L30 = 0) uses min Target GROI. Sold + out of box has no Dil SPRICE.
 * If that Dil / min-ROI price is below A Price, SPRICE = A Price.
 */
class PurchasingPowerRuleSpriceApplyService
{
    public function __construct(
        private readonly PurchasingPowerApiService $ppApi
    ) {}

    /**
     * @param  list<string>|null  $onlySkus
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    public function run(bool $dryRun = false, bool $push = true, ?int $limit = null, ?array $onlySkus = null, ?callable $logger = null): array
    {
        $dilRules = $this->loadDilGroiRules();
        $margin = MarketplacePercentage::takeHomeForPromoChannel('purchasing_power');
        if (! ($margin > 0)) {
            $margin = 0.65;
        }

        $this->log($logger, 'Loaded Dil slabs='.count($dilRules).' margin='.$margin.' push='.($push ? '1' : '0'));

        $stats = [
            'candidates' => 0,
            'applied' => 0,
            'cleared' => 0,
            'pushed' => 0,
            'push_failed' => 0,
            'skipped_unchanged' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $lock = Cache::lock('purchasing-power-rule-sprice-apply', 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another Purchasing Power S PRC apply is already running');
            $stats['errors'][] = 'lock: already running';

            return ['dry_run' => $dryRun, 'stats' => $stats];
        }

        try {
            $applied = 0;
            ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->when($onlySkus, function ($q) use ($onlySkus) {
                    $keys = $this->normalizeSkuList($onlySkus);
                    $q->where(function ($inner) use ($keys) {
                        foreach ($keys as $sku) {
                            $inner->orWhereRaw('UPPER(TRIM(sku)) = ?', [$sku]);
                        }
                    });
                })
                ->orderBy('id')
                ->chunkById(150, function ($rows) use (
                    $dilRules,
                    $margin,
                    $dryRun,
                    $push,
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
                            $computed = $this->computeTarget($row, $dilRules, $margin);
                            $saved = (float) ($row['saved_sprice'] ?? 0);
                            $live = (float) ($row['pp_price'] ?? 0);
                            if ($computed === null && $saved > 0 && $live > 0 && abs($saved - $live) < 0.005) {
                                $stats['skipped_unchanged']++;
                                continue;
                            }
                            $next = $computed !== null ? $computed['sprice'] : 0.0;
                            if (abs($saved - $next) < 0.005) {
                                $stats['skipped_unchanged']++;
                                continue;
                            }
                            if (! $dryRun) {
                                $this->saveSprice($row, $next, $margin);
                            }
                            $applied++;
                            if ($next > 0) {
                                $stats['applied']++;
                                if ($push && ! $dryRun && ($row['listed'] ?? false) && abs($next - $live) >= 0.005) {
                                    $ok = $this->pushPrice((string) $row['sku'], $next);
                                    if ($ok) {
                                        $stats['pushed']++;
                                        $this->markPushed((string) $row['sku'], $next);
                                    } else {
                                        $stats['push_failed']++;
                                    }
                                }
                            } else {
                                $stats['cleared']++;
                            }
                        } catch (Throwable $e) {
                            $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                            Log::warning('[PurchasingPowerRuleSpriceApply] sku failed', [
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
            'Done. candidates=%d applied=%d cleared=%d pushed=%d push_failed=%d unchanged=%d skipped=%d',
            $stats['candidates'],
            $stats['applied'],
            $stats['cleared'],
            $stats['pushed'],
            $stats['push_failed'],
            $stats['skipped_unchanged'],
            $stats['skipped']
        ));

        return ['dry_run' => $dryRun, 'stats' => $stats];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{key:string,label:string,min:float,max:float,groi:float}>  $dilRules
     * @return array{sprice: float}|null
     */
    public function computeTarget(array $row, array $dilRules, float $margin): ?array
    {
        $inv = (float) ($row['inv'] ?? 0);
        if (! ($inv > 0) || ! ($margin > 0)) {
            return null;
        }

        $dil = (float) ($row['dil'] ?? 0);
        $sold = (float) ($row['pp_l30'] ?? 0);
        $rule = AmazonDilGroiRule::match($dil, $dilRules);
        $groi = null;
        if ($sold <= 0) {
            $groi = AmazonDilGroiRule::minTarget($dilRules);
        } elseif ($rule !== null) {
            $groi = (float) $rule['groi'];
        }
        if ($groi === null) {
            return null;
        }

        $lp = (float) ($row['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }

        $raw = round(($lp * (1 + $groi / 100)) / $margin, 2);
        if (! is_finite($raw) || $raw < 0.01) {
            return null;
        }

        $amz = round((float) ($row['amz'] ?? 0), 2);
        $sprice = ($amz > 0 && $raw < $amz) ? $amz : $raw;

        return ['sprice' => round($sprice, 2)];
    }

    /**
     * @param  iterable<ProductMaster>  $rows
     * @return list<array<string, mixed>>
     */
    protected function hydrateChunk($rows): array
    {
        $skus = [];
        $lookup = [];
        $masters = [];
        foreach ($rows as $item) {
            $raw = trim((string) $item->sku);
            $sku = strtoupper($raw);
            if ($sku === '' || str_contains($sku, 'PARENT')) {
                continue;
            }
            $skus[] = $sku;
            $lookup[] = $raw;
            $lookup[] = $sku;
            $masters[$sku] = $item;
        }
        $skus = array_values(array_unique($skus));
        $lookup = array_values(array_unique(array_filter($lookup)));
        if ($skus === []) {
            return [];
        }

        $shopifyBySku = ShopifySku::query()
            ->whereIn('sku', $lookup)
            ->get(['sku', 'inv', 'quantity'])
            ->keyBy(static fn ($r) => strtoupper(trim((string) $r->sku)));

        $ppBySku = [];
        if (Schema::hasTable('purchasing_power_products')) {
            $ppBySku = PurchasingPowerProduct::query()
                ->whereIn('sku', $lookup)
                ->get(['sku', 'm_l30', 'price'])
                ->keyBy(static fn ($r) => strtoupper(trim((string) $r->sku)));
        }

        $sheetBySku = [];
        if (Schema::hasTable('macys_price_data')) {
            $sheetBySku = MacysPriceData::query()
                ->where(function ($q) use ($lookup) {
                    $q->whereIn('sku', $lookup)->orWhereIn('offer_sku', $lookup);
                })
                ->get(['sku', 'offer_sku', 'price'])
                ->keyBy(function ($item) {
                    $key = strtoupper(trim((string) ($item->offer_sku ?: $item->sku)));

                    return $key;
                });
        }

        $salesBySku = [];
        if (Schema::hasTable('purchasing_power_sales')) {
            $salesBySku = PurchasingPowerSale::query()
                ->whereNotIn('status', ['Canceled', 'canceled'])
                ->whereIn('offer_sku', $lookup)
                ->selectRaw('UPPER(TRIM(offer_sku)) as sku_upper, SUM(quantity) as total_qty')
                ->groupBy('sku_upper')
                ->pluck('total_qty', 'sku_upper')
                ->all();
        }

        $savedBySku = [];
        if (Schema::hasTable('purchasing_power_data_views')) {
            foreach (PurchasingPowerDataView::query()->whereIn('sku', $lookup)->get(['sku', 'value']) as $view) {
                $val = $this->decodeValue($view->value);
                $savedBySku[strtoupper(trim((string) $view->sku))] = is_numeric($val['SPRICE'] ?? null)
                    ? (float) $val['SPRICE']
                    : 0.0;
            }
        }

        $amzBySku = [];
        if (Schema::hasTable('amazon_datsheets')) {
            $amzBySku = AmazonDatasheet::query()
                ->whereIn('sku', $lookup)
                ->get(['sku', 'price'])
                ->keyBy(static fn ($r) => strtoupper(trim((string) $r->sku)));
        }

        $out = [];
        foreach ($skus as $sku) {
            $master = $masters[$sku] ?? null;
            $shopify = $shopifyBySku[$sku] ?? null;
            if (! $master) {
                continue;
            }
            $inv = (float) ($shopify->inv ?? 0);
            $ovL30 = (float) ($shopify->quantity ?? 0);
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

            $pp = $ppBySku[$sku] ?? null;
            $sheet = $sheetBySku[$sku] ?? null;
            $ppPrice = 0.0;
            if ($pp && is_numeric($pp->price) && (float) $pp->price > 0) {
                $ppPrice = round((float) $pp->price, 2);
            } elseif ($sheet && is_numeric($sheet->price) && (float) $sheet->price > 0) {
                $ppPrice = round((float) $sheet->price, 2);
            }

            $ppL30 = (float) ($salesBySku[$sku] ?? 0);
            if (! ($ppL30 > 0) && $pp) {
                $ppL30 = (float) ($pp->m_l30 ?? 0);
            }

            $out[] = [
                'sku' => $sku,
                'inv' => $inv,
                'dil' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0.0,
                'pp_l30' => $ppL30,
                'pp_price' => $ppPrice,
                'lp' => $lp,
                'amz' => isset($amzBySku[$sku]) ? (float) ($amzBySku[$sku]->price ?? 0) : 0.0,
                'listed' => $pp !== null,
                'saved_sprice' => $savedBySku[$sku] ?? 0.0,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function saveSprice(array $row, float $sprice, float $margin): void
    {
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
        if ($sku === '') {
            return;
        }

        $view = PurchasingPowerDataView::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->first()
            ?? new PurchasingPowerDataView(['sku' => $sku]);

        $existing = $this->decodeValue($view->value);
        if (! $view->exists) {
            $view->sku = $sku;
        }

        $lp = (float) ($row['lp'] ?? 0);
        $sgpft = ($sprice > 0) ? round((($sprice * $margin - $lp) / $sprice) * 100, 2) : 0.0;
        $sroi = ($lp > 0) ? round((($sprice * $margin - $lp) / $lp) * 100, 2) : 0.0;

        $existing['SPRICE'] = $sprice > 0 ? round($sprice, 2) : 0;
        $existing['sprice'] = $existing['SPRICE'];
        $existing['SGPFT'] = $sgpft;
        $existing['SPFT'] = $sgpft;
        $existing['SROI'] = $sroi;
        $existing['has_custom_sprice'] = $sprice > 0;
        $existing['SPRICE_STATUS'] = $sprice > 0 ? 'applied' : 'cleared';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();

        $view->value = $existing;
        $view->save();
    }

    protected function markPushed(string $sku, float $sprice): void
    {
        $sku = strtoupper(trim($sku));
        $view = PurchasingPowerDataView::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->first();
        if (! $view) {
            return;
        }
        $existing = $this->decodeValue($view->value);
        $existing['SPRICE_STATUS'] = 'pushed';
        $existing['SPRICE_PUSHED_AT'] = now()->toDateTimeString();
        $existing['SPRICE_PUSHED_VALUE'] = round($sprice, 2);
        $view->value = $existing;
        $view->save();
    }

    protected function pushPrice(string $sku, float $sprice): bool
    {
        $sprice = MacysAmazonPriceCap::capForSku($sku, $sprice);
        try {
            $result = $this->ppApi->updatePrice($sku, $sprice);
            if (($result['success'] ?? false) === true) {
                return true;
            }
            Log::warning('[PurchasingPowerRuleSpriceApply] price push failed', [
                'sku' => $sku,
                'sprice' => $sprice,
                'message' => $result['message'] ?? '',
            ]);

            return false;
        } catch (Throwable $e) {
            Log::warning('[PurchasingPowerRuleSpriceApply] price push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** @return list<array{key:string,label:string,min:float,max:float,groi:float}> */
    protected function loadDilGroiRules(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', 'purchasing_power_dil_vs_groi')->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        if (is_array($saved) && isset($saved['rules']) && is_array($saved['rules'])) {
            $saved = $saved['rules'];
        }
        $rules = AmazonDilGroiRule::normalizeList(is_array($saved) ? $saved : []);

        return $rules !== [] ? $rules : AmazonDilGroiRule::defaults();
    }

    /** @param  list<string>  $skus */
    protected function normalizeSkuList(array $skus): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($s) => strtoupper(trim((string) $s)),
            $skus
        ))));
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
