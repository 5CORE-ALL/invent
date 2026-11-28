<?php

namespace App\Services;

use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\Shopifyb2cDataView;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Shopify B2C (main store, services.shopify): percentage-off as a real Shopify AUTOMATIC DISCOUNT
 * (Admin GraphQL discountAutomaticBasic*, needs write_discounts).
 *
 * The price of the product is NOT changed, so the price rules / auto-push are not affected. Shopify applies the % in
 * the cart / checkout to the listed variants between startsAt and endsAt. One discount holds many SKUs at one %;
 * "Update %" changes that % for all of its SKUs, "Deactivate" ends it.
 * Our discounts are recognised by the title prefix TITLE_PREFIX.
 */
class ShopifyB2cPromotionService
{
    public const TITLE_PREFIX = '5C Promo';

    public const MAX_SKUS = 100;

    private function creds(): array
    {
        $domain = preg_replace('#^https?://#', '', (string) (config('services.shopify.store_url') ?: config('services.shopify.domain')));
        $domain = rtrim($domain, '/');
        $token = (string) (config('services.shopify.access_token') ?: config('services.shopify.password'));
        $version = (string) (config('services.shopify.api_version') ?: '2025-01');
        if ($domain === '' || $token === '') {
            throw new RuntimeException('Shopify store credentials are not configured.');
        }

        return [$domain, $token, $version];
    }

    /**
     * @param  array<string, mixed>  $vars
     * @return array<string, mixed> GraphQL `data`
     */
    private function graphql(string $query, array $vars = []): array
    {
        [$domain, $token, $version] = $this->creds();
        $url = 'https://'.$domain.'/admin/api/'.$version.'/graphql.json';
        for ($try = 0; $try < 4; $try++) {
            $res = Http::withHeaders(['X-Shopify-Access-Token' => $token, 'Content-Type' => 'application/json'])
                ->timeout(40)->post($url, ['query' => $query, 'variables' => (object) $vars]);
            $json = $res->json();
            $throttled = $res->status() === 429 || strtoupper((string) data_get($json, 'errors.0.extensions.code')) === 'THROTTLED';
            if ($throttled) {
                sleep(2 + $try * 2);

                continue;
            }
            if (! $res->successful()) {
                throw new RuntimeException('Shopify HTTP '.$res->status().': '.mb_substr((string) $res->body(), 0, 200));
            }
            if (! empty($json['errors'])) {
                throw new RuntimeException('Shopify: '.mb_substr(json_encode($json['errors']), 0, 300));
            }

            return (array) ($json['data'] ?? []);
        }
        throw new RuntimeException('Shopify is rate limiting, try again in a minute.');
    }

    private function userErrors(array $payload): ?string
    {
        $errs = $payload['userErrors'] ?? [];
        if ($errs === []) {
            return null;
        }

        return collect($errs)->map(fn ($e) => ($e['message'] ?? 'error').(isset($e['field']) ? ' ('.implode('.', (array) $e['field']).')' : ''))->implode('; ');
    }

    private function takeHome(): float
    {
        $m = MarketplacePercentage::where('marketplace', 'ShopifyB2C')->first();

        return $m ? ((float) $m->percentage / 100) : 0.95;
    }

    /**
     * Dry run. No Shopify write.
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
        $shop = ShopifySku::mapByProductSkus(array_values(array_map(fn ($p) => (string) $p->sku, $pm)));

        $rows = [];
        foreach ($skus as $input) {
            $row = [
                'sku' => $input, 'status' => 'skip', 'reason' => null, 'variant_id' => null, 'price' => null,
                'new_price' => null, 'cost' => null, 'margin' => null, 'inv' => null,
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
            $ship = (float) ($values['ship'] ?? 0);
            $s = $shop->get($sku);
            $variantId = $s ? preg_replace('/\D/', '', (string) $s->variant_id) : '';
            $price = $s ? (float) $s->price : 0.0;
            $inv = (int) ($s->inv ?? 0);
            // A SKU already in a page-visible promo is priced from its ORIGINAL price, so changing the % is not compounded.
            $dv = Shopifyb2cDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
            $dvVal = $dv ? (is_array($dv->value) ? $dv->value : (json_decode((string) $dv->value, true) ?: [])) : [];
            $inPromo = isset($dvVal['PROMO_PCT'], $dvVal['PROMO_BASE']);
            $row['live'] = $price ?: null;
            $row['in_promo'] = $inPromo;
            $row['promo_pct'] = $inPromo ? (float) $dvVal['PROMO_PCT'] : null;
            if ($inPromo) {
                $price = (float) $dvVal['PROMO_BASE'];
            }
            $new = round($price * (1 - $pct / 100), 2);
            $cost = $lp > 0 ? round($lp + $ship, 2) : null;
            $margin = $cost !== null ? round($new * $takeHome - $cost, 2) : null;

            $row['variant_id'] = $variantId ?: null;
            $row['price'] = $price ?: null;
            $row['new_price'] = $price > 0 ? $new : null;
            $row['cost'] = $cost;
            $row['margin'] = $margin;
            $row['inv'] = $inv;

            if ($variantId === '') {
                $row['reason'] = 'SKU not found on Shopify';
            } elseif (! ($price > 0)) {
                $row['reason'] = 'No live price';
            } elseif ($cost === null) {
                $row['reason'] = 'No LP (cost) in Product Master';
            } elseif ($inv < 1) {
                $row['reason'] = 'Out of stock';
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
     * Create one automatic discount for the ready SKUs.
     *
     * @param  list<array<string, mixed>>  $ready  plan rows with status ready
     * @return array{success: bool, message: string, discount_id?: string}
     */
    public function create(array $ready, float $pct, int $beginTs, ?int $endTs, string $title): array
    {
        $title = trim($title) === '' ? '' : trim($title);
        $title = self::TITLE_PREFIX.' '.($title !== '' ? $title : rtrim(rtrim(number_format($pct, 2), '0'), '.').'% '.date('md-Hi'));
        $input = [
            'title' => mb_substr($title, 0, 255),
            'startsAt' => gmdate('Y-m-d\TH:i:s\Z', $beginTs),
            'combinesWith' => ['orderDiscounts' => false, 'productDiscounts' => false, 'shippingDiscounts' => true],
            'customerGets' => [
                'value' => ['percentage' => round($pct / 100, 4)],
                'items' => ['products' => ['productVariantsToAdd' => array_values(array_map(
                    fn ($r) => 'gid://shopify/ProductVariant/'.$r['variant_id'], $ready
                ))]],
            ],
        ];
        if ($endTs) {
            $input['endsAt'] = gmdate('Y-m-d\TH:i:s\Z', $endTs);
        }
        try {
            $data = $this->graphql(
                'mutation($d: DiscountAutomaticBasicInput!) { discountAutomaticBasicCreate(automaticBasicDiscount: $d) {
                    automaticDiscountNode { id } userErrors { field message code } } }',
                ['d' => $input]
            );
        } catch (\Throwable $e) {
            Log::warning('Promotions Shopify B2C create failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
        $p = $data['discountAutomaticBasicCreate'] ?? [];
        if ($err = $this->userErrors($p)) {
            return ['success' => false, 'message' => $err];
        }
        $id = (string) data_get($p, 'automaticDiscountNode.id', '');

        return $id === '' ? ['success' => false, 'message' => 'Shopify did not return a discount id.']
            : ['success' => true, 'message' => 'Discount created.', 'discount_id' => $id];
    }

    /** Change the % of an existing discount (all its SKUs). */
    public function updatePercent(string $discountId, float $pct): array
    {
        try {
            $data = $this->graphql(
                'mutation($id: ID!, $d: DiscountAutomaticBasicInput!) { discountAutomaticBasicUpdate(id: $id, automaticBasicDiscount: $d) {
                    automaticDiscountNode { id } userErrors { field message code } } }',
                ['id' => $discountId, 'd' => ['customerGets' => ['value' => ['percentage' => round($pct / 100, 4)]]]]
            );
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        $err = $this->userErrors($data['discountAutomaticBasicUpdate'] ?? []);

        return $err ? ['success' => false, 'message' => $err] : ['success' => true, 'message' => 'Discount changed to '.$pct.'%.'];
    }

    /** End a discount now. */
    public function deactivate(string $discountId): array
    {
        try {
            $data = $this->graphql(
                'mutation($id: ID!) { discountAutomaticDeactivate(id: $id) { automaticDiscountNode { id } userErrors { field message code } } }',
                ['id' => $discountId]
            );
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        $err = $this->userErrors($data['discountAutomaticDeactivate'] ?? []);

        return $err ? ['success' => false, 'message' => $err] : ['success' => true, 'message' => 'Discount deactivated.'];
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Page-visible promotion: variant price lowered, old price in compare-at (storefront shows it crossed out).
    // Kept on shopifyb2c_data_view.value: PROMO_PCT, PROMO_BASE, PROMO_PRICE, PROMO_COMPARE_ORIG (nullable),
    // PROMO_WAS_MANUAL, PROMO_END, PROMO_APPLIED_AT. SPRICE_MANUAL is set so the Sprc/Std-vs-Dil auto-push skips the SKU.
    // ---------------------------------------------------------------------------------------------------------------

    private const PROMO_KEYS = ['PROMO_PCT', 'PROMO_BASE', 'PROMO_PRICE', 'PROMO_COMPARE_ORIG', 'PROMO_WAS_MANUAL', 'PROMO_END', 'PROMO_APPLIED_AT'];

    /** @return array{price: float, compare_at: ?float, product_id: string}|null */
    private function fetchVariant(string $variantId): ?array
    {
        $data = $this->graphql(
            'query($id: ID!) { productVariant(id: $id) { price compareAtPrice product { id } } }',
            ['id' => 'gid://shopify/ProductVariant/'.$variantId]
        );
        $v = $data['productVariant'] ?? null;
        if (! $v) {
            return null;
        }
        $cmp = $v['compareAtPrice'] ?? null;

        return [
            'price' => round((float) $v['price'], 2),
            'compare_at' => is_numeric($cmp) && (float) $cmp > 0 ? round((float) $cmp, 2) : null,
            'product_id' => (string) data_get($v, 'product.id', ''),
        ];
    }

    /**
     * Set price and compare-at of one variant and verify what Shopify stored.
     *
     * @return array{success: bool, message: string}
     */
    private function writeVariant(string $variantId, string $productId, float $price, ?float $compareAt): array
    {
        try {
            $data = $this->graphql(
                'mutation($pid: ID!, $v: [ProductVariantsBulkInput!]!) { productVariantsBulkUpdate(productId: $pid, variants: $v) {
                    productVariants { id price compareAtPrice } userErrors { field message } } }',
                ['pid' => $productId, 'v' => [[
                    'id' => 'gid://shopify/ProductVariant/'.$variantId,
                    'price' => number_format($price, 2, '.', ''),
                    'compareAtPrice' => $compareAt !== null ? number_format($compareAt, 2, '.', '') : null,
                ]]]
            );
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        $p = $data['productVariantsBulkUpdate'] ?? [];
        if ($err = $this->userErrors($p)) {
            return ['success' => false, 'message' => $err];
        }
        $stored = round((float) data_get($p, 'productVariants.0.price', 0), 2);
        if (abs($stored - round($price, 2)) > 0.005) {
            return ['success' => false, 'message' => 'Shopify stored '.number_format($stored, 2).' instead of '.number_format($price, 2)];
        }

        return ['success' => true, 'message' => 'ok'];
    }

    private function dvFor(string $sku): Shopifyb2cDataView
    {
        return Shopifyb2cDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first()
            ?? new Shopifyb2cDataView(['sku' => $sku]);
    }

    /** @return array<string, mixed> */
    private function dvValue(Shopifyb2cDataView $dv): array
    {
        return is_array($dv->value) ? $dv->value : (json_decode((string) $dv->value, true) ?: []);
    }

    /**
     * Apply pct% as a lowered price with the original price crossed out.
     *
     * @param  list<array<string, mixed>>  $ready  plan rows with status ready
     * @return array{success: bool, message: string, applied: int, failed: int, results: list<array<string, mixed>>}
     */
    public function applyPrice(array $ready, float $pct, ?int $endTs, ?string $user = null): array
    {
        @set_time_limit(0);
        $results = [];
        $applied = 0;
        $failed = 0;
        $takeHome = $this->takeHome();

        foreach ($ready as $r) {
            $sku = (string) $r['sku'];
            $variantId = (string) $r['variant_id'];
            $dv = $this->dvFor($sku);
            $before = $this->dvValue($dv);
            $inPromo = isset($before['PROMO_PCT'], $before['PROMO_BASE']);
            $base = (float) $r['price'];             // original price (promo base when already in a promo)
            $new = (float) $r['new_price'];

            try {
                $live = $this->fetchVariant($variantId);
            } catch (\Throwable $e) {
                $live = null;
            }
            if (! $live || $live['product_id'] === '') {
                $failed++;
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => 'Could not read the variant from Shopify'];

                continue;
            }
            $expectedLive = $inPromo ? (float) ($before['PROMO_PRICE'] ?? 0) : $base;
            if (abs($live['price'] - $expectedLive) > 0.011) {
                $failed++;
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => 'Shopify price is '.number_format($live['price'], 2).', the app has '.number_format($expectedLive, 2).' — nothing changed, refresh the price first'];

                continue;
            }

            $compareOrig = $inPromo && array_key_exists('PROMO_COMPARE_ORIG', $before) ? $before['PROMO_COMPARE_ORIG'] : $live['compare_at'];
            $after = array_merge($before, [
                'SPRICE' => $new,
                'calc_price' => $new,
                'SPRICE_MANUAL' => true,
                'PROMO_PCT' => $pct,
                'PROMO_BASE' => $base,
                'PROMO_PRICE' => $new,
                'PROMO_COMPARE_ORIG' => $compareOrig,
                'PROMO_WAS_MANUAL' => $inPromo ? (bool) ($before['PROMO_WAS_MANUAL'] ?? false) : ! empty($before['SPRICE_MANUAL']),
                'PROMO_END' => $endTs,
                'PROMO_APPLIED_AT' => time(),
            ]);
            $dv->value = $after;
            $dv->save();

            $w = $this->writeVariant($variantId, $live['product_id'], $new, $base);
            if (! $w['success']) {
                $dv->value = $before;
                $dv->save();
                $failed++;
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $w['message']];
                Log::warning('Promotions Shopify B2C price promo failed', ['sku' => $sku, 'error' => $w['message']]);

                continue;
            }
            \App\Services\ChannelLivePriceSync::writeLive('shopify_b2c', $sku, $new);
            $applied++;
            $results[] = ['sku' => $sku, 'status' => 'ok', 'price' => $new, 'base' => $base, 'message' => 'Price '.number_format($base, 2).' → '.number_format($new, 2).' (old price crossed out)'];
            Log::info('Promotions Shopify B2C price promo', ['sku' => $sku, 'pct' => $pct, 'base' => $base, 'price' => $new, 'end' => $endTs, 'user' => $user]);
            usleep(500_000);
        }

        $ended = $applied > 0 ? $this->endCartDiscountsFor(array_map(fn ($r) => (string) $r['sku'], $ready)) : [];

        return [
            'success' => $applied > 0,
            'message' => $applied.' SKU(s) at '.rtrim(rtrim(number_format($pct, 2), '0'), '.').'% off on the product page'.($failed ? ', '.$failed.' failed' : '')
                .($ended ? '. Ended the cart-only discount(s): '.implode(', ', $ended).' (so the % is not applied twice)' : '').'.',
            'applied' => $applied,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Deactivate our cart discounts whose SKUs are all part of this page promo (otherwise the % would apply twice).
     *
     * @param  list<string>  $skus
     * @return list<string> titles ended
     */
    private function endCartDiscountsFor(array $skus): array
    {
        $want = array_map('strtoupper', array_map('trim', $skus));
        $ended = [];
        try {
            foreach ($this->list() as $d) {
                if (! in_array($d['status'], ['ACTIVE', 'SCHEDULED'], true) || $d['skus'] === []) {
                    continue;
                }
                $mine = array_map('strtoupper', array_map('trim', $d['skus']));
                if (array_diff($mine, $want) === [] && $this->deactivate($d['discount_id'])['success']) {
                    $ended[] = $d['title'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Promotions Shopify B2C end cart discounts failed', ['error' => $e->getMessage()]);
        }

        return $ended;
    }

    /** @return list<array<string, mixed>> */
    public function activePrice(): array
    {
        $out = [];
        foreach (Shopifyb2cDataView::query()->where('value', 'like', '%PROMO_PCT%')->get() as $dv) {
            $v = $this->dvValue($dv);
            if (! isset($v['PROMO_PCT'], $v['PROMO_BASE'])) {
                continue;
            }
            $s = ShopifySku::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim((string) $dv->sku))])->first();
            $live = $s ? round((float) $s->price, 2) : null;
            $promoPrice = (float) ($v['PROMO_PRICE'] ?? 0);
            $out[] = [
                'sku' => (string) $dv->sku,
                'pct' => (float) $v['PROMO_PCT'],
                'base' => (float) $v['PROMO_BASE'],
                'promo_price' => $promoPrice,
                'live' => $live,
                'changed_outside' => $live !== null && $live > 0 && abs($live - $promoPrice) > 0.011,
                'end' => isset($v['PROMO_END']) ? (int) $v['PROMO_END'] : null,
            ];
        }
        usort($out, fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

        return $out;
    }

    /**
     * Put the original price (and original compare-at) back.
     *
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, removed: int, failed: int, results: list<array<string, mixed>>}
     */
    public function removePrice(array $skus, ?string $user = null): array
    {
        @set_time_limit(0);
        $results = [];
        $removed = 0;
        $failed = 0;
        foreach ($skus as $input) {
            $dv = Shopifyb2cDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($input))])->first();
            $v = $dv ? $this->dvValue($dv) : [];
            if (! $dv || ! isset($v['PROMO_PCT'], $v['PROMO_BASE'])) {
                $results[] = ['sku' => $input, 'status' => 'skipped', 'message' => 'No promotion on this SKU'];

                continue;
            }
            $sku = (string) $dv->sku;
            $base = round((float) $v['PROMO_BASE'], 2);
            $promoPrice = round((float) ($v['PROMO_PRICE'] ?? 0), 2);
            $orig = $v['PROMO_COMPARE_ORIG'] ?? null;
            $orig = is_numeric($orig) && (float) $orig > 0 ? (float) $orig : null;

            $s = ShopifySku::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
            $variantId = $s ? preg_replace('/\D/', '', (string) $s->variant_id) : '';
            $note = 'Original price '.number_format($base, 2).' restored';
            $after = $v;
            foreach (self::PROMO_KEYS as $k) {
                unset($after[$k]);
            }
            if (empty($v['PROMO_WAS_MANUAL'])) {
                unset($after['SPRICE_MANUAL']);
            }

            try {
                $live = $variantId !== '' ? $this->fetchVariant($variantId) : null;
            } catch (\Throwable $e) {
                $live = null;
            }
            if (! $live || $live['product_id'] === '') {
                $failed++;
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => 'Could not read the variant from Shopify'];

                continue;
            }
            if (abs($live['price'] - $promoPrice) > 0.011) {
                $note = 'Price was changed after the promo ('.number_format($live['price'], 2).'), left as it is';
            } else {
                $w = $this->writeVariant($variantId, $live['product_id'], $base, $orig);
                if (! $w['success']) {
                    $failed++;
                    $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $w['message']];

                    continue;
                }
                $after = array_merge($after, ['SPRICE' => $base, 'calc_price' => $base]);
                \App\Services\ChannelLivePriceSync::writeLive('shopify_b2c', $sku, $base);
                usleep(500_000);
            }
            $dv->value = $after;
            $dv->save();
            $removed++;
            $results[] = ['sku' => $sku, 'status' => 'ok', 'message' => $note];
            Log::info('Promotions Shopify B2C price promo removed', ['sku' => $sku, 'note' => $note, 'user' => $user]);
        }

        return [
            'success' => $removed > 0,
            'message' => $removed.' promotion(s) removed'.($failed ? ', '.$failed.' failed' : '').'.',
            'removed' => $removed,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /** @return array{removed: int, failed: int} */
    public function expireDue(?int $now = null): array
    {
        $now ??= time();
        $due = [];
        foreach ($this->activePrice() as $a) {
            if ($a['end'] !== null && $a['end'] > 0 && $a['end'] <= $now) {
                $due[] = $a['sku'];
            }
        }
        if ($due === []) {
            return ['removed' => 0, 'failed' => 0];
        }
        $r = $this->removePrice($due, 'scheduler');

        return ['removed' => $r['removed'], 'failed' => $r['failed']];
    }

    /**
     * Our discounts (title starts with TITLE_PREFIX), newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $data = $this->graphql(
            'query { automaticDiscountNodes(first: 100, sortKey: CREATED_AT, reverse: true) { nodes { id automaticDiscount {
                __typename
                ... on DiscountAutomaticBasic { title status startsAt endsAt
                    customerGets { value { __typename ... on DiscountPercentage { percentage } }
                        items { __typename ... on DiscountProducts { productVariants(first: 20) { nodes { sku } } } } } } } } } }'
        );
        $out = [];
        foreach ((array) data_get($data, 'automaticDiscountNodes.nodes', []) as $n) {
            $d = $n['automaticDiscount'] ?? [];
            if (($d['__typename'] ?? '') !== 'DiscountAutomaticBasic' || ! str_starts_with((string) ($d['title'] ?? ''), self::TITLE_PREFIX)) {
                continue;
            }
            $skus = collect(data_get($d, 'customerGets.items.productVariants.nodes', []))->pluck('sku')->filter()->values()->all();
            $out[] = [
                'discount_id' => (string) $n['id'],
                'title' => (string) $d['title'],
                'status' => (string) ($d['status'] ?? ''),
                'starts_at' => $d['startsAt'] ?? null,
                'ends_at' => $d['endsAt'] ?? null,
                'pct' => round((float) data_get($d, 'customerGets.value.percentage', 0) * 100, 2),
                'skus' => $skus,
            ];
        }

        return $out;
    }
}
