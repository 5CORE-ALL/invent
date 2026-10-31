<?php

namespace App\Services;

use App\Models\AmazonDataView;
use App\Support\AmazonDilPriceCoupon;
use App\Support\NegativeSnroiPushGuard;
use Carbon\Carbon;

/**
 * Push the Promotions "Amazon Dil % × Price Coupons" rules to Amazon.
 *
 * Amazon SP-API cannot create Seller Central Coupons (see AmazonCpnCouponCatalog), so — exactly like the
 * CVR vs CPN auto push — a coupon of N % is applied as a discounted listing price:
 *
 *     S PRC = base × (1 − N / 100)   →   Amazon Listings API (Sale / Business / Min via updateAmazonPriceUS)
 *
 * Shares PEF_CPN_PCT / SPRICE / PEF_CPN_PUSHED_AT with AmazonCvrCpnAutoPushService, so the
 * "1 coupon tier change per SKU per ET day" guard and the unchanged-price checks work across both.
 *
 * Safety rules:
 *  - Base price = STANDARD_PRICE, else the live price only when no coupon was ever applied by the app
 *    (never re-discount an already-discounted live price).
 *  - A SKU whose rule says 0 % is left alone unless the app had applied a coupon to it before; then its
 *    price goes back to base.
 *  - A discounting push is skipped for negative SNROI; restoring the price is never blocked.
 */
class AmazonDilPriceCouponPushService extends AmazonCvrCpnAutoPushService
{
    /**
     * Work out what a push would change. Makes no Amazon calls and writes nothing.
     *
     * @param  list<array{sku:string,seller_sku:string,l30:float,inv:float,price:float}>  $skuRows
     * @param  list<array{dmin:float,dmax:float,pmin:float,pmax:float,pct:int}>  $rules
     * @return array{tasks: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function plan(array $skuRows, array $rules): array
    {
        $todayEt = Carbon::now('America/New_York')->toDateString();

        $candidates = [];
        foreach ($this->loadCandidates(null, null) as $c) {
            $candidates[strtoupper(trim((string) $c['seller_sku']))] = $c;
        }

        // SKUs this feature itself discounted before (only those may be restored when the rule goes to 0 %).
        $ours = $this->loadOwnCouponMarkers(array_keys($candidates));

        $skipped = [
            'no_coupon' => 0,        // rule says 0 % and this feature has nothing to undo
            'already_lower' => 0,    // live price is already at/below the coupon price: a coupon never raises a price
            'unchanged' => 0,        // already at the target price / coupon
            'one_per_day' => 0,      // tier already changed today (ET)
            'no_base' => 0,          // coupon applied before but STANDARD_PRICE is missing
            'negative_snroi' => 0,
            'no_datasheet' => 0,     // no Amazon datasheet row to push against
            'invalid' => 0,
        ];
        $tasks = [];
        $byPct = [];
        $seen = [];

        foreach ($skuRows as $row) {
            $key = strtoupper(trim((string) ($row['seller_sku'] ?: $row['sku'])));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $pct = AmazonDilPriceCoupon::percentFor(
                (float) $row['l30'],
                (float) $row['inv'],
                (float) $row['price'],
                $rules
            );

            $c = $candidates[$key] ?? null;
            if ($c === null) {
                $skipped['no_datasheet']++;

                continue;
            }

            $prevPct = $c['cpn_pct'] !== null ? (int) round((float) $c['cpn_pct']) : 0;
            // 0 % only undoes a coupon THIS feature applied. Coupons set by CVR vs CPN (or anything else) are not ours to touch.
            if ($pct === 0 && ($ours[$key] ?? 0) <= 0) {
                $skipped['no_coupon']++;

                continue;
            }

            $live = (float) $c['price'];
            $std = (float) ($c['standard_price'] ?? 0);
            if ($std > 0) {
                $base = $std;
            } elseif ($prevPct > 0) {
                $skipped['no_base']++;

                continue;
            } else {
                $base = $live;
            }

            $sprice = $pct > 0 ? round($base * (1 - $pct / 100), 2) : round($base, 2);
            if (! is_finite($sprice) || $sprice < 0.01) {
                $skipped['invalid']++;

                continue;
            }

            $computed = ['sprice' => $sprice, 'cpn' => $pct, 'base' => round($base, 2), 'cvr' => (float) $c['cvr']];

            // A new coupon must lower the price. If the live price is already lower (repriced, or an
            // earlier discount), leave it; pushing would raise it.
            if ($pct > 0 && $sprice >= $live) {
                $skipped['already_lower']++;

                continue;
            }
            if (AmazonSpApiService::listingPriceMatchesSprice($live, $sprice) || $this->isUnchanged($c, $computed)) {
                $skipped['unchanged']++;

                continue;
            }
            if ($this->blockedByOnePerDay($c, $computed, $todayEt)) {
                $skipped['one_per_day']++;

                continue;
            }
            if ($sprice < $live && NegativeSnroiPushGuard::shouldSkip('amazon', (string) $c['sku'], $sprice)) {
                $skipped['negative_snroi']++;

                continue;
            }

            $tasks[] = [
                'sku' => (string) $c['sku'],
                'seller_sku' => (string) $c['seller_sku'],
                'pct' => $pct,
                'prev_pct' => $prevPct,
                'base' => round($base, 2),
                'std' => round($std, 2),
                'live' => round($live, 2),
                'sprice' => $sprice,
                'cvr' => (float) $c['cvr'],
                'status' => 'pending',
            ];
            $byPct[$pct] = ($byPct[$pct] ?? 0) + 1;
        }

        ksort($byPct);
        $sample = array_map(static fn (array $t) => [
            'sku' => $t['sku'], 'live' => $t['live'], 'to' => $t['sprice'], 'pct' => $t['pct'],
        ], array_slice($tasks, 0, 8));

        return [
            'tasks' => $tasks,
            'summary' => [
                'considered' => count($seen),
                'to_push' => count($tasks),
                'discounts' => count(array_filter($tasks, static fn ($t) => $t['pct'] > 0)),
                'restores' => count(array_filter($tasks, static fn ($t) => $t['pct'] === 0)),
                'by_pct' => (object) $byPct,
                'max_pct' => $byPct === [] ? 0 : max(array_keys($byPct)),
                'skipped' => $skipped,
                'sample' => $sample,
            ],
        ];
    }

    /**
     * Coupon % this feature last pushed, per SKU key (upper-case seller SKU). Only > 0 entries.
     *
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private function loadOwnCouponMarkers(array $keys): array
    {
        $out = [];
        foreach (array_chunk($keys, 500) as $chunk) {
            foreach (AmazonDataView::query()->whereIn('sku', $chunk)->get(['sku', 'value']) as $v) {
                $val = $this->decodeValue($v->value);
                $pct = (int) ($val['PROMO_DIL_CPN_PCT'] ?? 0);
                if ($pct > 0) {
                    $out[strtoupper(trim((string) $v->sku))] = $pct;
                }
            }
        }

        return $out;
    }

    private function stampOwnCoupon(string $sku, int $pct): void
    {
        try {
            $view = AmazonDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
            if (! $view) {
                return;
            }
            $val = $this->decodeValue($view->value);
            $val['PROMO_DIL_CPN_PCT'] = $pct; // 0 after a restore
            $val['PROMO_DIL_CPN_AT'] = now()->toDateTimeString();
            $view->value = $val;
            $view->save();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[AmazonDilPriceCouponPush] marker save failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Save S PRC / coupon % locally and push one SKU's price to Amazon.
     *
     * @param  array<string, mixed>  $task
     * @return array{ok: bool, error: ?string}
     */
    public function pushTask(AmazonSpApiService $api, array $task): array
    {
        $sku = (string) $task['sku'];
        $pct = (int) $task['pct'];
        $sprice = (float) $task['sprice'];

        $this->saveSpriceAndCpn($sku, $sprice, $pct, (float) $task['base'], (float) ($task['cvr'] ?? 0));
        $ok = $this->pushSprice(
            $api,
            $sku,
            (string) ($task['seller_sku'] ?: $sku),
            $sprice,
            $pct,
            'Promotions Dil % × Price coupon',
            (float) ($task['std'] ?? 0)
        );
        if ($ok) {
            $this->stampOwnCoupon($sku, $pct);

            return ['ok' => true, 'error' => null];
        }

        $view = AmazonDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
        $val = $view ? $this->decodeValue($view->value) : [];

        return ['ok' => false, 'error' => (string) ($val['AMAZON_PUSH_ERROR'] ?? 'Amazon push failed')];
    }
}
