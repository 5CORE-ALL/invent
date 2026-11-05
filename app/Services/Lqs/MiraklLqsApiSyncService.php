<?php

namespace App\Services\Lqs;

use App\Models\BestbuyUsaProduct;
use App\Models\MacyProduct;
use App\Models\PurchasingPowerProduct;
use App\Models\LqsMarketplaceScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Scores Mirakl-based marketplaces (Best Buy, Macy's, Purchasing Power) from the live seller offer feed (OF21) and stores
 * the LQS (0-100) in lqs_marketplace_scores.
 *
 * OF21 returns up to 100 offers per call, so a whole catalog is a handful of requests (no per-SKU calls).
 */
class MiraklLqsApiSyncService
{
    /** slug => [services.php config key, metrics table (bullets/images this app pushed), catalog model, label] */
    private const CHANNELS = [
        'bestbuy' => ['bestbuy', 'bestbuy_metrics', BestbuyUsaProduct::class, 'Best Buy'],
        'macy' => ['macy', 'macy_metrics', MacyProduct::class, "Macy's"],
        'purchasingpower' => ['purchasingpower', 'purchasing_power_metrics', PurchasingPowerProduct::class, 'Purchasing Power'],
    ];

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::CHANNELS);
    }

    public static function supports(string $slug): bool
    {
        return isset(self::CHANNELS[$slug]);
    }

    public static function label(string $slug): string
    {
        return self::CHANNELS[$slug][3] ?? $slug;
    }

    public static function lockKey(string $slug): string
    {
        return 'lqs-sync-'.$slug.'-lock';
    }

    public static function statusKey(string $slug): string
    {
        return 'lqs-sync-'.$slug.'-status';
    }

    public function __construct(
        private readonly MiraklListingQualityScorer $scorer,
    ) {
    }

    public function isConfigured(string $slug): bool
    {
        return $this->apiKey($slug) !== '' && $this->baseUrl($slug) !== '';
    }

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }

    /**
     * Every offer on the shop, keyed by normalized shop_sku (an active offer wins over an inactive duplicate).
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetchAllOffers(string $slug, ?callable $onPage = null): array
    {
        $offers = [];
        $offset = 0;
        $max = 100;
        $total = null;

        do {
            $body = $this->getOffers($slug, ['max' => $max, 'offset' => $offset]);
            $page = (array) ($body['offers'] ?? []);
            $total ??= (int) ($body['total_count'] ?? 0);

            foreach ($page as $offer) {
                if (! is_array($offer)) {
                    continue;
                }
                $key = self::normalizeSku((string) ($offer['shop_sku'] ?? ''));
                if ($key === '') {
                    continue;
                }
                if (! isset($offers[$key]) || (! ($offers[$key]['active'] ?? false) && ($offer['active'] ?? false))) {
                    $offers[$key] = $offer;
                }
            }

            if ($onPage) {
                $onPage(count($offers), $total);
            }
            $offset += $max;
            sleep(1); // Mirakl shops (Macy's especially) answer 429 when paged quickly
        } while (count($page) === $max && ($total === 0 || $offset < $total));

        return $offers;
    }

    /** @return array<string, mixed>|null */
    public function fetchOffer(string $sku, string $slug): ?array
    {
        $want = self::normalizeSku($sku);
        $body = $this->getOffers($slug, ['sku' => trim($sku), 'max' => 10]);
        foreach ((array) ($body['offers'] ?? []) as $offer) {
            if (is_array($offer) && self::normalizeSku((string) ($offer['shop_sku'] ?? '')) === $want) {
                return $offer;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function assess(array $offer, string $slug): array
    {
        return $this->scorer->score($offer, $this->localContent((string) ($offer['shop_sku'] ?? ''), $slug), self::label($slug));
    }

    /**
     * Score an offer and save it for its SKU.
     *
     * @param  array<string, mixed>  $offer
     * @return array{lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>}
     */
    public function saveOffer(array $offer, string $slug): array
    {
        $result = $this->assess($offer, $slug);

        $row = [
            'lqs' => $result['score'],
            'listing_id' => (string) ($offer['product_sku'] ?? $offer['offer_id'] ?? ''),
        ];
        if (isset($offer['price']) && is_numeric($offer['price'])) {
            $row['price'] = (float) $offer['price'];
        }
        if (Schema::hasColumn('lqs_marketplace_scores', 'audit_findings')) {
            $row['audit_findings'] = $result['findings'];
            $row['audit_suggestions'] = $result['suggestions'];
        }

        LqsMarketplaceScore::updateOrCreate(
            ['marketplace' => $slug, 'sku' => self::normalizeSku((string) $offer['shop_sku'])],
            $row
        );

        return [
            'lqs' => $result['score'],
            'findings' => $result['findings'],
            'suggestions' => $result['suggestions'],
            'checks' => $result['checks'],
        ];
    }

    /** @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>} */
    public function syncSku(string $sku, string $slug): array
    {
        $offer = $this->fetchOffer($sku, $slug);
        if ($offer === null) {
            throw new \RuntimeException('SKU '.$sku.' has no offer on '.self::label($slug).' (OF21).');
        }

        return ['sku' => $sku] + $this->saveOffer($offer, $slug);
    }

    /**
     * Bullets and images this app last pushed to the marketplace (OF21 does not return them).
     *
     * @return array{bullets: list<string>, images: list<string>}
     */
    public function localContent(string $sku, string $slug): array
    {
        $out = ['bullets' => [], 'images' => []];
        $table = self::CHANNELS[$slug][1] ?? null;
        if (! $table || trim($sku) === '' || ! Schema::hasTable($table)) {
            return $out;
        }

        $row = DB::table($table)
            ->whereRaw('UPPER(TRIM(REPLACE(sku, ?, " "))) = ?', ["\u{00a0}", self::normalizeSku($sku)])
            ->orderByDesc('id')
            ->first();
        if (! $row) {
            return $out;
        }

        $out['bullets'] = array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n/', (string) ($row->bullet_points ?? '')) ?: []
        ), fn ($l) => $l !== ''));

        $raw = (string) ($row->image_urls ?? '');
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $out['images'] = array_values(array_filter(array_map(fn ($u) => is_string($u) ? trim($u) : '', $decoded)));
        } elseif (trim($raw) !== '') {
            $out['images'] = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $raw) ?: [])));
        }

        return $out;
    }

    /** SKUs this app lists on the marketplace (the page's "of N" denominator). */
    public function catalogSkus(string $slug): array
    {
        $model = self::CHANNELS[$slug][2] ?? null;
        if (! $model || ! Schema::hasTable((new $model)->getTable())) {
            return [];
        }

        return $model::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->pluck('sku')
            ->map(fn ($s) => self::normalizeSku((string) $s))
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function getOffers(string $slug, array $params): array
    {
        $shopId = $this->config($slug, 'shop_id');
        if ($shopId !== null && $shopId !== '') {
            $params['shop_id'] = (int) $shopId;
        }

        $last = null;
        for ($attempt = 1; $attempt <= 7; $attempt++) {
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => $this->apiKey($slug), 'Accept' => 'application/json'])
                ->timeout(60)
                ->get($this->baseUrl($slug).'/api/offers', $params);
            $last = $response;
            if ($response->successful()) {
                return (array) ($response->json() ?? []);
            }
            if ($response->status() !== 429 && $response->status() < 500) {
                break;
            }
            // 429/5xx: honour Retry-After when sent, otherwise back off 5, 10, 20, 40, 60s.
            $retryAfter = (int) $response->header('Retry-After');
            sleep(max($retryAfter, min(60, 5 * (2 ** ($attempt - 1)))));
        }

        throw new \RuntimeException(self::label($slug).' offers (OF21) failed: HTTP '.($last?->status() ?? '?').' '.mb_substr((string) $last?->body(), 0, 200));
    }

    private function config(string $slug, string $key): mixed
    {
        return config('services.'.(self::CHANNELS[$slug][0] ?? $slug).'.'.$key);
    }

    private function apiKey(string $slug): string
    {
        $key = trim((string) $this->config($slug, 'mcm_api_key'));

        return trim(preg_replace('/^(authorization:\s*|bearer\s+)/i', '', $key) ?? $key);
    }

    private function baseUrl(string $slug): string
    {
        return rtrim((string) $this->config($slug, 'mcm_base_url'), '/');
    }
}
