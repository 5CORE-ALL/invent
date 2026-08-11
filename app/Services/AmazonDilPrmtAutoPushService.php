<?php

namespace App\Services;

use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSkuDailyData;
use App\Models\ShopifySku;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dil vs PRMT → discount SPRICE → push Amazon Listings our_price.
 * Uses the same Dil→PRMT rules store as /pricing-errors-fix (pef_dil_vs_prmt).
 * Skips SKUs whose target price is unchanged vs last push / live listing.
 */
class AmazonDilPrmtAutoPushService
{
    public function __construct(
        private readonly PefDilPrmtAutoApplyService $dilPrmtRules
    ) {}

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
        ?callable $logger = null
    ): array {
        $rules = $this->dilPrmtRules->loadRules();
        $this->log($logger, 'Loaded '.count($rules).' Dil vs PRMT rules (shared with pricing-errors-fix)');

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

        $stats['candidates'] = count($rows);
        $this->log($logger, "Found {$stats['candidates']} candidate SKU(s)");

        $api = ($dryRun || $skipPush) ? null : new AmazonSpApiService;
        $total = count($rows);

        foreach ($rows as $i => $row) {
            try {
                $computed = $this->computeTarget($row, $rules);
                if ($computed === null) {
                    $stats['skipped']++;
                    continue;
                }

                if ($this->isUnchanged($row, $computed)) {
                    $stats['skipped_unchanged']++;
                    continue;
                }

                $this->saveSpriceAndPrmt($row['sku'], $computed['sprice'], $computed['prmt'], $computed['base']);
                $stats['applied']++;

                if ($dryRun || $skipPush) {
                    continue;
                }

                $ok = $this->pushSprice($api, $row['sku'], $row['seller_sku'], $computed['sprice']);
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
                Log::error('[AmazonDilPrmtAutoPush] exception', [
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
    }

    /**
     * @param  list<string>|null  $onlySkus
     * @return list<array{sku:string,seller_sku:string,asin:string,price:float,inv:float,l30:float,dil:float,standard_price:float,sprice:float,pushed_value:?float,prmt_pct:?float}>
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

        $sheets = $query->get(['sku', 'asin', 'price']);
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
            $l30 = (float) ($shopify->quantity ?? 0);
            $dil = $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0.0;

            $dv = $this->decodeValue($views[$sku]->value ?? null);
            $std = (float) ($dv['STANDARD_PRICE'] ?? 0);
            $sprice = (float) ($dv['SPRICE'] ?? 0);
            $pushed = isset($dv['SPRICE_PUSHED_VALUE']) && is_numeric($dv['SPRICE_PUSHED_VALUE'])
                ? (float) $dv['SPRICE_PUSHED_VALUE']
                : null;
            $prmtPct = isset($dv['PEF_PRMT_PCT']) && is_numeric($dv['PEF_PRMT_PCT'])
                ? (float) $dv['PEF_PRMT_PCT']
                : null;

            $out[] = [
                'sku' => $sku,
                'seller_sku' => $sellerSku,
                'asin' => trim((string) ($m->asin ?? '')),
                'price' => $price,
                'inv' => $inv,
                'l30' => $l30,
                'dil' => $dil,
                'standard_price' => $std,
                'sprice' => $sprice,
                'pushed_value' => $pushed,
                'prmt_pct' => $prmtPct,
            ];
        }

        return $out;
    }

    /**
     * @param  array{sku:string,price:float,inv:float,dil:float,standard_price:float}  $row
     * @param  list<array{key:string,label:string,prmt:float}>  $rules
     * @return array{sprice:float,prmt:float,base:float,dil:float}|null
     */
    protected function computeTarget(array $row, array $rules): ?array
    {
        $inv = (float) ($row['inv'] ?? 0);
        $dil = (float) ($row['dil'] ?? 0);
        // Match UI: INV = 0 → PRMT% = 0
        $prmt = $inv > 0 ? $this->dilPrmtRules->prmtForDil($dil, $rules) : 0.0;

        // Stable base: STANDARD_PRICE when set, else live Amazon listing price (never compound SPRICE)
        $base = (float) ($row['standard_price'] ?? 0);
        if (! ($base > 0)) {
            $base = (float) ($row['price'] ?? 0);
        }
        if (! ($base > 0)) {
            return null;
        }

        $sprice = $prmt > 0
            ? round($base * (1 - ($prmt / 100)), 2)
            : round($base, 2);

        if (! is_finite($sprice) || $sprice < 0.01) {
            return null;
        }

        return [
            'sprice' => $sprice,
            'prmt' => round($prmt, 2),
            'base' => round($base, 2),
            'dil' => $dil,
        ];
    }

    /**
     * @param  array{price:float,pushed_value:?float,prmt_pct:?float}  $row
     * @param  array{sprice:float,prmt:float}  $computed
     */
    protected function isUnchanged(array $row, array $computed): bool
    {
        $target = round((float) $computed['sprice'], 2);
        $prmt = round((float) $computed['prmt'], 2);
        $listing = round((float) ($row['price'] ?? 0), 2);
        $lastPushed = $row['pushed_value'] !== null ? round((float) $row['pushed_value'], 2) : null;
        $lastPrmt = $row['prmt_pct'] !== null ? round((float) $row['prmt_pct'], 2) : null;

        // Already pushed this exact price
        if ($lastPushed !== null && abs($lastPushed - $target) < 0.005) {
            return true;
        }

        // Live Amazon price already matches target and PRMT% unchanged
        if ($lastPrmt !== null && abs($lastPrmt - $prmt) < 0.005 && abs($listing - $target) < 0.005) {
            return true;
        }

        return false;
    }

    protected function saveSpriceAndPrmt(string $sku, float $sprice, float $prmt, float $base): void
    {
        $view = AmazonDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first()
            ?? new AmazonDataView(['sku' => strtoupper(trim($sku))]);

        $existing = $this->decodeValue($view->value);
        if (! $view->exists) {
            $view->sku = strtoupper(trim($sku));
        }

        $existing['SPRICE'] = round($sprice, 2);
        $existing['PEF_PRMT_PCT'] = round($prmt, 2);
        $existing['PEF_PRMT_BASE'] = round($base, 2);
        $existing['SPRICE_STATUS'] = 'saved';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();

        $view->value = $existing;
        $view->save();

        $this->syncPrmtDailyHistory(strtoupper(trim($sku)), round($sprice, 2), round($prmt, 2));
    }

    /** PDT daily roll-on for PRMT% history dots. */
    protected function syncPrmtDailyHistory(string $sku, float $sprice, float $prmt): void
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
            $payload['prmt_pct'] = $prmt;
            $daily->daily_data = $payload;
            $daily->save();
        } catch (Throwable $e) {
            Log::warning('[AmazonDilPrmtAutoPush] daily history sync failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function pushSprice(AmazonSpApiService $api, string $statusSku, string $sellerSku, float $sprice): bool
    {
        $price = round($sprice, 2);
        if ($price < 0.01 || $price > 999999.99) {
            $this->savePushMeta($statusSku, 'error', null);

            return false;
        }

        $apiSku = $sellerSku !== '' ? $sellerSku : $statusSku;
        $result = $api->updateAmazonPriceUS($apiSku, $price);

        if (isset($result['errors']) && ! empty($result['errors'])) {
            $this->savePushMeta($statusSku, 'error', null);
            Log::warning('[AmazonDilPrmtAutoPush] push failed', [
                'status_sku' => $statusSku,
                'amazon_api_sku' => $apiSku,
                'price' => $price,
                'errors' => $result['errors'],
            ]);

            return false;
        }

        $minFloor = $api->minSellerAllowedPriceFromOurPrice($price);
        try {
            $minResult = $api->updateCompetitivePriceConstraints($apiSku, $minFloor);
            if (isset($minResult['errors']) && ! empty($minResult['errors'])) {
                Log::warning('[AmazonDilPrmtAutoPush] min price update failed (non-blocking)', [
                    'status_sku' => $statusSku,
                    'errors' => $minResult['errors'],
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('[AmazonDilPrmtAutoPush] min price exception (non-blocking)', [
                'status_sku' => $statusSku,
                'error' => $e->getMessage(),
            ]);
        }

        $this->savePushMeta($statusSku, 'pushed', $price);

        return true;
    }

    protected function savePushMeta(string $sku, string $status, ?float $pushedValue): void
    {
        try {
            $view = AmazonDataView::firstOrNew(['sku' => strtoupper(trim($sku))]);
            $existing = $this->decodeValue($view->value);
            $existing['SPRICE_STATUS'] = $status;
            $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            if ($pushedValue !== null) {
                $existing['SPRICE_PUSHED_VALUE'] = round($pushedValue, 2);
                $existing['SPRICE_PUSHED_AT'] = now()->toDateTimeString();
            }
            if (! $view->exists) {
                $view->sku = strtoupper(trim($sku));
            }
            $view->value = $existing;
            $view->save();
        } catch (Throwable $e) {
            Log::error('[AmazonDilPrmtAutoPush] status save failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
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
