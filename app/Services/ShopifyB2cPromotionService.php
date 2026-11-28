<?php

namespace App\Services;

use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
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
