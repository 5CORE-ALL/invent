<?php

namespace App\Services;

use App\Models\AmazonDatasheet;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\MacyDataView;
use App\Models\MacyProduct;
use App\Models\MacysPriceData;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\AmazonDilGroiRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wipe stale SPRICE, then write Sprc Dil (A Price floor). Page does not need to stay open.
 * Dil match when Dil is in a slab. Out of box + MC L30 = 0 uses min Target GROI.
 * If that price is below A Price, SPRICE = A Price.
 */
class MacysRuleSpriceApplyService
{
    /**
     * @param  list<string>|null  $onlySkus
     * @param  callable(string): void|null  $logger
     * @return array<string, mixed>
     */
    public function run(bool $dryRun = false, ?int $limit = null, ?array $onlySkus = null, ?callable $logger = null): array
    {
        $dilRules = $this->loadDilGroiRules();
        $margin = MarketplacePercentage::takeHomeForPromoChannel('macys');
        if (! ($margin > 0)) {
            $margin = 0.80;
        }

        $this->log($logger, 'Loaded Dil slabs='.count($dilRules).' margin='.$margin);

        $stats = [
            'candidates' => 0,
            'applied' => 0,
            'cleared' => 0,
            'skipped_unchanged' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $lock = Cache::lock('macys-rule-sprice-apply', 10800);
        if (! $lock->get()) {
            $this->log($logger, 'Skipped: another Macys S PRC apply is already running');
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
                            $live = (float) ($row['mc_price'] ?? 0);
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
                                $this->saveSprice($row['sku'], $next);
                            }
                            $applied++;
                            if ($next > 0) {
                                $stats['applied']++;
                            } else {
                                $stats['cleared']++;
                            }
                        } catch (Throwable $e) {
                            $stats['errors'][] = ($row['sku'] ?? '').': '.$e->getMessage();
                            Log::warning('[MacysRuleSpriceApply] sku failed', [
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
            'Done. candidates=%d applied=%d cleared=%d unchanged=%d skipped=%d',
            $stats['candidates'],
            $stats['applied'],
            $stats['cleared'],
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

        $lp = (float) ($row['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }

        $dil = (float) ($row['dil'] ?? 0);
        $sold = (float) ($row['mc_l30'] ?? 0);
        $rule = AmazonDilGroiRule::match($dil, $dilRules);
        $groi = null;
        if ($rule !== null) {
            $groi = (float) $rule['groi'];
        } elseif ($sold <= 0) {
            $groi = AmazonDilGroiRule::minTarget($dilRules);
        }
        if ($groi === null) {
            return null;
        }

        $ship = (float) ($row['ship'] ?? 0);
        $raw = round(($lp * (1 + $groi / 100) + $ship) / $margin, 2);
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

        $macyBySku = MacyProduct::query()
            ->whereIn('sku', $lookup)
            ->get(['sku', 'm_l30'])
            ->keyBy(static fn ($r) => strtoupper(trim((string) $r->sku)));

        $sheetBySku = MacysPriceData::query()
            ->whereIn('sku', $lookup)
            ->get(['sku', 'price'])
            ->keyBy(static fn ($r) => strtoupper(trim((string) $r->sku)));

        $amzBySku = AmazonDatasheet::query()
            ->whereIn('sku', $lookup)
            ->get(['sku', 'price'])
            ->keyBy(static fn ($r) => strtoupper(trim((string) $r->sku)));

        $savedBySku = [];
        foreach (MacyDataView::query()->whereIn('sku', $lookup)->get(['sku', 'value']) as $view) {
            $val = $this->decodeValue($view->value);
            $savedBySku[strtoupper(trim((string) $view->sku))] = is_numeric($val['SPRICE'] ?? null)
                ? (float) $val['SPRICE']
                : 0.0;
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
            $ship = isset($values['ship']) ? (float) $values['ship'] : (float) ($master->ship ?? 0);

            $out[] = [
                'sku' => $sku,
                'inv' => $inv,
                'dil' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0.0,
                'mc_l30' => (float) (($macyBySku[$sku]->m_l30 ?? 0)),
                'mc_price' => isset($sheetBySku[$sku]) ? (float) ($sheetBySku[$sku]->price ?? 0) : 0.0,
                'lp' => $lp,
                'ship' => $ship,
                'amz' => isset($amzBySku[$sku]) ? (float) ($amzBySku[$sku]->price ?? 0) : 0.0,
                'saved_sprice' => $savedBySku[$sku] ?? 0.0,
            ];
        }

        return $out;
    }

    protected function saveSprice(string $sku, float $sprice): void
    {
        $sku = strtoupper(trim($sku));
        $view = MacyDataView::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->first()
            ?? new MacyDataView(['sku' => $sku]);

        $existing = $this->decodeValue($view->value);
        if (! $view->exists) {
            $view->sku = $sku;
        }

        $existing['SPRICE'] = $sprice > 0 ? round($sprice, 2) : 0;
        $existing['sprice'] = $existing['SPRICE'];
        $existing['has_custom_sprice'] = $sprice > 0;
        $existing['SPRICE_STATUS'] = $sprice > 0 ? 'applied' : 'cleared';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();

        $view->value = $existing;
        $view->save();
    }

    /** @return list<array{key:string,label:string,min:float,max:float,groi:float}> */
    protected function loadDilGroiRules(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', 'macys_dil_vs_groi')->first();
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
