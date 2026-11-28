<?php

namespace App\Services;

use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifyB2BDataView;
use App\Models\ShopifySku;
use App\Models\StoreListingPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shopify B2B (business5core.com) percentage-off promotion — SAVED IN THE APP ONLY, NOTHING IS PUSHED.
 *
 * The B2B store has no coupon API, so a promotion is a lower S PRC. This service never calls the store:
 *
 * Apply  : promo price = BASE × (1 − pct/100), saved as a hand-set S PRC (SPRICE + SPRICE_MANUAL) in
 *          shopifyb2b_data_view, so the Sprc Dil auto-push / blue-price catch-up skip the SKU. BASE is the live
 *          selling price before the promo; editing the % later recomputes from the same BASE.
 * Remove : puts BASE back as the saved S PRC, clears the PROMO_* keys and, if the SKU was not hand-set before, the
 *          manual flag so the rule jobs price it again.
 * Expire : an optional end date; `shopify-b2b:promo-expire` removes due promos (saved values only).
 *
 * Getting a price onto business5core.com stays a separate, manual step (the pricing page push).
 *
 * Keys kept on shopifyb2b_data_view.value: PROMO_PCT, PROMO_BASE, PROMO_PRICE, PROMO_WAS_MANUAL, PROMO_END (unix|null),
 * PROMO_APPLIED_AT (unix).
 */
class ShopifyB2bPromotionService
{
    public const MAX_SKUS = 30;

    private function takeHome(): float
    {
        $m = MarketplacePercentage::where('marketplace', 'ShopifyB2B')->first();

        return $m ? ((float) $m->percentage / 100) : 0.95;
    }

    /** @return array<string, mixed> */
    private function valueOf(?ShopifyB2BDataView $dv): array
    {
        if (! $dv) {
            return [];
        }

        return is_array($dv->value) ? $dv->value : (json_decode((string) $dv->value, true) ?: []);
    }

    /**
     * Metrics at a price, same maths as Shopifyb2bController::calculateAndSaveSprice (no ship, no ads).
     *
     * @return array<string, float>
     */
    private function metrics(float $price, float $lp, float $takeHome): array
    {
        $gross = ($price * $takeHome) - $lp;
        $sgpft = $price > 0 ? ($gross / $price) * 100 : 0.0;
        $roi = $lp > 0 ? ($gross / $lp) * 100 : 0.0;

        return ['SGPFT' => $sgpft, 'SNPFT' => $sgpft, 'SPFT' => $sgpft, 'SROI' => $roi, 'SNROI' => $roi];
    }

    private function lpFor(string $sku): float
    {
        $p = ProductMaster::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
        if (! $p) {
            return 0.0;
        }
        $values = is_array($p->Values) ? $p->Values : (json_decode((string) $p->Values, true) ?: []);

        return (float) ($values['lp'] ?? 0);
    }

    private function findListing(string $sku): ?StoreListingPrice
    {
        $exact = StoreListingPrice::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
            ->where('store_product_id', '>', 0)
            ->orderByDesc('is_variant')
            ->first();
        if ($exact) {
            return $exact;
        }
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm === '') {
            return null;
        }
        foreach (StoreListingPrice::query()
            ->where('store_product_id', '>', 0)
            ->whereRaw('REPLACE(REPLACE(UPPER(TRIM(sku)), "-", " "), "_", " ") LIKE ?', ['%'.$norm.'%'])
            ->limit(50)->get() as $row) {
            if (ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku) === $norm) {
                return $row;
            }
        }

        return null;
    }

    private function livePrice(?StoreListingPrice $l): float
    {
        if (! $l) {
            return 0.0;
        }
        foreach ([$l->selling_price, $l->special_price, $l->price] as $p) {
            if ($p !== null && (float) $p > 0) {
                return round((float) $p, 2);
            }
        }

        return 0.0;
    }

    /**
     * Dry run: what pct% would do to each SKU. Nothing is saved.
     *
     * @param  list<string>  $skus
     * @return list<array<string, mixed>>
     */
    public function plan(array $skus, float $pct, bool $allowBelowCost): array
    {
        $takeHome = $this->takeHome();
        $pm = [];
        foreach (ProductMaster::query()->whereIn(DB::raw('UPPER(TRIM(sku))'), array_map('strtoupper', $skus))->get() as $p) {
            $pm[strtoupper(trim((string) $p->sku))] = $p;
        }
        $inv = ShopifySku::mapByProductSkus(array_values(array_map(fn ($p) => (string) $p->sku, $pm)));

        $rows = [];
        foreach ($skus as $input) {
            $row = [
                'sku' => $input, 'status' => 'skip', 'reason' => null, 'live' => null, 'base' => null,
                'new_price' => null, 'cost' => null, 'margin' => null, 'margin_pct' => null, 'inv' => null,
                'in_promo' => false, 'promo_pct' => null,
            ];
            $p = $pm[strtoupper($input)] ?? null;
            if (! $p) {
                $rows[] = ['reason' => 'SKU not in Product Master'] + $row;

                continue;
            }
            $sku = (string) $p->sku;
            $row['sku'] = $sku;
            $values = is_array($p->Values) ? $p->Values : (json_decode((string) $p->Values, true) ?: []);
            $lp = (float) ($values['lp'] ?? 0);
            $listing = $this->findListing($sku);
            $live = $this->livePrice($listing);
            $stock = (float) (optional($inv->get($sku))->inv ?? 0);
            $dv = ShopifyB2BDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
            $val = $this->valueOf($dv);
            $inPromo = isset($val['PROMO_PCT'], $val['PROMO_BASE']);
            $base = $inPromo ? (float) $val['PROMO_BASE'] : $live;
            $new = round($base * (1 - $pct / 100), 2);
            $margin = $lp > 0 ? round($new * $takeHome - $lp, 2) : null;

            $row['live'] = $live ?: null;
            $row['base'] = $base ?: null;
            $row['new_price'] = $base > 0 ? $new : null;
            $row['cost'] = $lp > 0 ? round($lp, 2) : null;
            $row['margin'] = $margin;
            $row['margin_pct'] = ($margin !== null && $new > 0) ? round($margin / $new * 100, 1) : null;
            $row['inv'] = (int) $stock;
            $row['in_promo'] = $inPromo;
            $row['promo_pct'] = $inPromo ? (float) $val['PROMO_PCT'] : null;

            if (! $listing) {
                $row['reason'] = 'SKU not found on business5core.com';
            } elseif (! ($base > 0)) {
                $row['reason'] = 'No live price';
            } elseif (! ($lp > 0)) {
                $row['reason'] = 'No LP (cost) in Product Master';
            } elseif (! ($stock > 0)) {
                $row['reason'] = 'Out of stock';
            } elseif (! ($new >= 0.01)) {
                $row['reason'] = 'Price too low';
            } elseif ($margin < 0 && ! $allowBelowCost) {
                $row['reason'] = 'Below cost ('.number_format($margin, 2).' per unit)';
            } else {
                $row['status'] = 'ready';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Save the promo price for the ready SKUs. Nothing is sent to the store.
     *
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, applied: int, failed: int, results: list<array<string, mixed>>}
     */
    public function apply(array $skus, float $pct, ?int $endTs, bool $allowBelowCost, ?string $user = null): array
    {
        $takeHome = $this->takeHome();
        $results = [];
        $applied = 0;

        foreach ($this->plan($skus, $pct, $allowBelowCost) as $r) {
            if ($r['status'] !== 'ready') {
                $results[] = ['sku' => $r['sku'], 'status' => 'skipped', 'message' => $r['reason']];

                continue;
            }
            $sku = $r['sku'];
            $dv = ShopifyB2BDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                ?? new ShopifyB2BDataView(['sku' => $sku]);
            $before = $this->valueOf($dv);
            $inPromo = isset($before['PROMO_PCT'], $before['PROMO_BASE']);
            $new = (float) $r['new_price'];

            $dv->value = array_merge($before, [
                'SPRICE' => $new,
                'calc_price' => $new,
                'SPRICE_MANUAL' => true,
                'PROMO_PCT' => $pct,
                'PROMO_BASE' => (float) $r['base'],
                'PROMO_PRICE' => $new,
                'PROMO_WAS_MANUAL' => $inPromo ? (bool) ($before['PROMO_WAS_MANUAL'] ?? false) : ! empty($before['SPRICE_MANUAL']),
                'PROMO_END' => $endTs,
                'PROMO_APPLIED_AT' => time(),
            ], $this->metrics($new, (float) $r['cost'], $takeHome));
            $dv->save();

            $applied++;
            $results[] = [
                'sku' => $sku, 'status' => 'ok', 'price' => $new, 'base' => (float) $r['base'],
                'message' => 'Saved '.number_format($new, 2).' (original '.number_format((float) $r['base'], 2).')',
            ];
            Log::info('Promotions B2B saved (not pushed)', ['sku' => $sku, 'pct' => $pct, 'base' => $r['base'], 'price' => $new, 'end' => $endTs, 'user' => $user]);
        }

        return [
            'success' => $applied > 0,
            'message' => $applied.' SKU(s) saved at '.rtrim(rtrim(number_format($pct, 2), '0'), '.').'% off. Not pushed to Shopify / business5core.com.',
            'applied' => $applied,
            'failed' => 0,
            'results' => $results,
        ];
    }

    /**
     * Promotions currently saved (any SKU with PROMO_PCT).
     *
     * @return list<array<string, mixed>>
     */
    public function active(): array
    {
        $out = [];
        foreach (ShopifyB2BDataView::query()->where('value', 'like', '%PROMO_PCT%')->get() as $dv) {
            $v = $this->valueOf($dv);
            if (! isset($v['PROMO_PCT'], $v['PROMO_BASE'])) {
                continue;
            }
            $live = $this->livePrice($this->findListing((string) $dv->sku));
            $promoPrice = (float) ($v['PROMO_PRICE'] ?? 0);
            $out[] = [
                'sku' => (string) $dv->sku,
                'pct' => (float) $v['PROMO_PCT'],
                'base' => (float) $v['PROMO_BASE'],
                'promo_price' => $promoPrice,
                'live' => $live ?: null,
                'on_store' => $live > 0 && abs($live - $promoPrice) <= 0.011,
                'end' => isset($v['PROMO_END']) ? (int) $v['PROMO_END'] : null,
                'applied_at' => (int) ($v['PROMO_APPLIED_AT'] ?? 0),
            ];
        }
        usort($out, fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

        return $out;
    }

    /**
     * End the promotion: the original price becomes the saved S PRC again. Nothing is sent to the store.
     *
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, removed: int, failed: int, results: list<array<string, mixed>>}
     */
    public function remove(array $skus, ?string $user = null): array
    {
        $takeHome = $this->takeHome();
        $results = [];
        $removed = 0;

        foreach ($skus as $input) {
            $dv = ShopifyB2BDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($input))])->first();
            $v = $this->valueOf($dv);
            if (! $dv || ! isset($v['PROMO_PCT'], $v['PROMO_BASE'])) {
                $results[] = ['sku' => $input, 'status' => 'skipped', 'message' => 'No promotion on this SKU'];

                continue;
            }
            $sku = (string) $dv->sku;
            $base = round((float) $v['PROMO_BASE'], 2);
            $promoPrice = round((float) ($v['PROMO_PRICE'] ?? 0), 2);
            $live = $this->livePrice($this->findListing($sku));
            $stillOnStore = $live > 0 && abs($live - $promoPrice) <= 0.011;

            $after = $v;
            foreach (['PROMO_PCT', 'PROMO_BASE', 'PROMO_PRICE', 'PROMO_WAS_MANUAL', 'PROMO_END', 'PROMO_APPLIED_AT'] as $k) {
                unset($after[$k]);
            }
            if (empty($v['PROMO_WAS_MANUAL'])) {
                unset($after['SPRICE_MANUAL']);   // rule jobs price this SKU again
            }
            $dv->value = array_merge($after, ['SPRICE' => $base, 'calc_price' => $base], $this->metrics($base, $this->lpFor($sku), $takeHome));
            $dv->save();

            $removed++;
            $results[] = [
                'sku' => $sku, 'status' => 'ok',
                'message' => 'Saved price back to '.number_format($base, 2).($stillOnStore ? ' (the store still shows '.number_format($live, 2).' until you push)' : ''),
            ];
            Log::info('Promotions B2B removed (not pushed)', ['sku' => $sku, 'base' => $base, 'store_shows_promo' => $stillOnStore, 'user' => $user]);
        }

        return [
            'success' => $removed > 0,
            'message' => $removed.' promotion(s) removed. Nothing was pushed to Shopify / business5core.com.',
            'removed' => $removed,
            'failed' => 0,
            'results' => $results,
        ];
    }

    /**
     * Remove every promotion whose end time has passed (saved values only).
     *
     * @return array{removed: int, failed: int}
     */
    public function expireDue(?int $now = null): array
    {
        $now ??= time();
        $due = [];
        foreach ($this->active() as $a) {
            if ($a['end'] !== null && $a['end'] > 0 && $a['end'] <= $now) {
                $due[] = $a['sku'];
            }
        }
        if ($due === []) {
            return ['removed' => 0, 'failed' => 0];
        }
        $res = $this->remove($due, 'scheduler');

        return ['removed' => $res['removed'], 'failed' => 0];
    }
}
