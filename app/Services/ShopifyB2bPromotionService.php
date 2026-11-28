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
 * Shopify B2B (business5core.com) percentage-off promotion.
 *
 * The store has no coupon / discount API (GET /api lists listings, inventory, prices, orders only). A "promotion" is
 * therefore a lower special_price on the listing: the storefront shows the new price in red and the old one crossed out.
 *
 * Apply  : promo price = BASE × (1 − pct/100), saved as a HAND-SET S PRC (SPRICE_MANUAL) in shopifyb2b_data_view so the
 *          Sprc Dil auto-push / blue-price catch-up leave it alone, then pushed with StorePricePushService.
 *          BASE is the live selling price before the promo; editing the % later recomputes from the same BASE.
 * Remove : pushes BASE back (unless somebody changed the live price after the promo — then it is left as it is),
 *          clears the PROMO_* keys and, if the SKU was not hand-set before, the manual flag so the rule jobs resume.
 * Expire : an optional end date; `shopify-b2b:promo-expire` removes due promos.
 *
 * Keys kept on shopifyb2b_data_view.value: PROMO_PCT, PROMO_BASE, PROMO_PRICE, PROMO_WAS_MANUAL, PROMO_END (unix|null),
 * PROMO_APPLIED_AT (unix).
 */
class ShopifyB2bPromotionService
{
    /** Pushes are rate limited by the store, so a request is kept small. */
    public const MAX_SKUS = 30;

    public function __construct(protected StorePricePushService $pusher)
    {
    }

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
     * Cost and metrics at a price, same maths as Shopifyb2bController::calculateAndSaveSprice (no ship, no ads).
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
     * Dry run: what pct% would do to each SKU. Nothing is saved or pushed.
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
                'new_price' => null, 'reg' => null, 'cost' => null, 'margin' => null, 'margin_pct' => null, 'inv' => null,
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

            $row['reg'] = ($listing && (float) $listing->price > 0) ? round((float) $listing->price, 2) : null;
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
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, applied: int, failed: int, results: list<array<string, mixed>>}
     */
    public function apply(array $skus, float $pct, ?int $endTs, bool $allowBelowCost, ?string $user = null): array
    {
        @set_time_limit(0);
        $takeHome = $this->takeHome();
        $plan = $this->plan($skus, $pct, $allowBelowCost);
        $results = [];
        $applied = 0;
        $failed = 0;

        foreach ($plan as $r) {
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

            $after = array_merge($before, [
                'SPRICE' => $new,
                'calc_price' => $new,
                'SPRICE_MANUAL' => true,
                'PROMO_PCT' => $pct,
                'PROMO_BASE' => (float) $r['base'],
                'PROMO_PRICE' => $new,
                // The store's regular (crossed-out) price before the promo. During the promo it is set to BASE so the page shows
                // "new price, old price crossed out"; Remove puts this value back.
                'PROMO_REG_PRICE' => ($inPromo && isset($before['PROMO_REG_PRICE'])) ? (float) $before['PROMO_REG_PRICE'] : (float) ($r['reg'] ?? 0),
                'PROMO_WAS_MANUAL' => $inPromo ? (bool) ($before['PROMO_WAS_MANUAL'] ?? false) : ! empty($before['SPRICE_MANUAL']),
                'PROMO_END' => $endTs,
                'PROMO_APPLIED_AT' => time(),
            ], $this->metrics($new, (float) $r['cost'], $takeHome));
            $dv->value = $after;
            $dv->save();

            $push = $this->pusher->pushSprice($sku, $new, (float) $r['base']);
            if (! $push['success']) {
                $dv->value = $before;     // nothing changed on the store, put the saved values back
                $dv->save();
                $failed++;
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $push['message']];
                Log::warning('Promotions B2B apply failed', ['sku' => $sku, 'pct' => $pct, 'error' => $push['message']]);
            } else {
                $applied++;
                $results[] = [
                    'sku' => $sku, 'status' => 'ok', 'price' => $new, 'base' => (float) $r['base'],
                    'message' => 'Price '.number_format((float) $r['base'], 2).' → '.number_format($new, 2),
                ];
                Log::info('Promotions B2B apply', ['sku' => $sku, 'pct' => $pct, 'base' => $r['base'], 'price' => $new, 'end' => $endTs, 'user' => $user]);
            }
            usleep(1_000_000); // the store API is rate limited
        }

        return [
            'success' => $applied > 0,
            'message' => $applied.' SKU(s) at '.rtrim(rtrim(number_format($pct, 2), '0'), '.').'% off'.($failed ? ', '.$failed.' failed' : '').'.',
            'applied' => $applied,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Promotions currently on (any SKU with PROMO_PCT).
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
                'changed_outside' => $live > 0 && abs($live - $promoPrice) > 0.011,
                'end' => isset($v['PROMO_END']) ? (int) $v['PROMO_END'] : null,
                'applied_at' => (int) ($v['PROMO_APPLIED_AT'] ?? 0),
            ];
        }
        usort($out, fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

        return $out;
    }

    /**
     * Put the original price back for these SKUs.
     *
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, removed: int, failed: int, results: list<array<string, mixed>>}
     */
    public function remove(array $skus, ?string $user = null): array
    {
        @set_time_limit(0);
        $takeHome = $this->takeHome();
        $results = [];
        $removed = 0;
        $failed = 0;

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

            $note = 'Original price '.number_format($base, 2).' restored';
            $push = true;
            if ($live > 0 && abs($live - $promoPrice) > 0.011) {
                // Somebody changed the price after the promo; do not overwrite their price.
                $push = false;
                $note = 'Price was changed after the promo ('.number_format($live, 2).'), left as it is';
            }

            $after = $v;
            foreach (['PROMO_PCT', 'PROMO_BASE', 'PROMO_PRICE', 'PROMO_REG_PRICE', 'PROMO_WAS_MANUAL', 'PROMO_END', 'PROMO_APPLIED_AT'] as $k) {
                unset($after[$k]);
            }
            if (empty($v['PROMO_WAS_MANUAL'])) {
                unset($after['SPRICE_MANUAL']);   // rule jobs price this SKU again
            }
            if ($push) {
                $lp = $this->lpFor($sku);
                $after = array_merge($after, ['SPRICE' => $base, 'calc_price' => $base], $this->metrics($base, $lp, $takeHome));
                $regOrig = (float) ($v['PROMO_REG_PRICE'] ?? 0);
                $res = $this->pusher->pushSprice($sku, $base, $regOrig > 0 ? $regOrig : null);
                if (! $res['success']) {
                    $failed++;
                    $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $res['message']];
                    Log::warning('Promotions B2B remove failed', ['sku' => $sku, 'error' => $res['message']]);
                    usleep(1_000_000);

                    continue;
                }
                usleep(1_000_000);
            }
            $dv->value = $after;
            $dv->save();
            $removed++;
            $results[] = ['sku' => $sku, 'status' => 'ok', 'message' => $note];
            Log::info('Promotions B2B remove', ['sku' => $sku, 'base' => $base, 'pushed' => $push, 'user' => $user]);
        }

        return [
            'success' => $removed > 0,
            'message' => $removed.' promotion(s) removed'.($failed ? ', '.$failed.' failed' : '').'.',
            'removed' => $removed,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Remove every promotion whose end time has passed.
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

        return ['removed' => $res['removed'], 'failed' => $res['failed']];
    }
}
