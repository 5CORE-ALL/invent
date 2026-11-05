<?php

namespace App\Services\Lqs;

use App\Models\BestbuyUsaProduct;
use App\Models\LqsMarketplaceScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Scores Best Buy listings from the live Mirakl MCM offer feed (OF21) and stores the LQS (0-100) in
 * lqs_marketplace_scores (marketplace = 'bestbuy').
 *
 * OF21 returns up to 100 offers per call, so the whole catalog is a handful of requests (no per-SKU calls).
 */
class BestBuyLqsApiSyncService
{
    public const MARKETPLACE = 'bestbuy';

    public const LOCK_KEY = 'lqs-sync-bestbuy-lock';

    public const STATUS_KEY = 'lqs-sync-bestbuy-status';

    public function __construct(
        private readonly BestBuyListingQualityScorer $scorer,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '' && $this->baseUrl() !== '';
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
    public function fetchAllOffers(?callable $onPage = null): array
    {
        $offers = [];
        $offset = 0;
        $max = 100;
        $total = null;

        do {
            $body = $this->getOffers(['max' => $max, 'offset' => $offset]);
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
            usleep(250_000);
        } while (count($page) === $max && ($total === 0 || $offset < $total));

        return $offers;
    }

    /** @return array<string, mixed>|null */
    public function fetchOffer(string $sku): ?array
    {
        $want = self::normalizeSku($sku);
        $body = $this->getOffers(['sku' => trim($sku), 'max' => 10]);
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
    public function assess(array $offer): array
    {
        return $this->scorer->score($offer, $this->localContent((string) ($offer['shop_sku'] ?? '')));
    }

    /**
     * Score an offer and save it for its SKU.
     *
     * @param  array<string, mixed>  $offer
     * @return array{lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>}
     */
    public function saveOffer(array $offer): array
    {
        $result = $this->assess($offer);

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
            ['marketplace' => self::MARKETPLACE, 'sku' => self::normalizeSku((string) $offer['shop_sku'])],
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
    public function syncSku(string $sku): array
    {
        $offer = $this->fetchOffer($sku);
        if ($offer === null) {
            throw new \RuntimeException('SKU '.$sku.' has no offer on Best Buy (OF21).');
        }

        return ['sku' => $sku] + $this->saveOffer($offer);
    }

    /**
     * Bullets and images this app last pushed to Best Buy (OF21 does not return them).
     *
     * @return array{bullets: list<string>, images: list<string>}
     */
    public function localContent(string $sku): array
    {
        $out = ['bullets' => [], 'images' => []];
        if (trim($sku) === '' || ! Schema::hasTable('bestbuy_metrics')) {
            return $out;
        }

        $row = DB::table('bestbuy_metrics')
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

    /** SKUs this app lists on Best Buy (the page's "of N" denominator). */
    public function catalogSkus(): array
    {
        if (! Schema::hasTable((new BestbuyUsaProduct)->getTable())) {
            return [];
        }

        return BestbuyUsaProduct::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->pluck('sku')
            ->map(fn ($s) => self::normalizeSku((string) $s))
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function getOffers(array $params): array
    {
        $shopId = config('services.bestbuy.shop_id');
        if ($shopId !== null && $shopId !== '') {
            $params['shop_id'] = (int) $shopId;
        }

        $last = null;
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => $this->apiKey(), 'Accept' => 'application/json'])
                ->timeout(60)
                ->get($this->baseUrl().'/api/offers', $params);
            $last = $response;
            if ($response->successful()) {
                return (array) ($response->json() ?? []);
            }
            if ($response->status() !== 429 && $response->status() < 500) {
                break;
            }
            sleep(2 * $attempt);
        }

        throw new \RuntimeException('Best Buy offers (OF21) failed: HTTP '.($last?->status() ?? '?').' '.mb_substr((string) $last?->body(), 0, 200));
    }

    private function apiKey(): string
    {
        $key = trim((string) config('services.bestbuy.mcm_api_key', ''));

        return trim(preg_replace('/^(authorization:\s*|bearer\s+)/i', '', $key) ?? $key);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.bestbuy.mcm_base_url', ''), '/');
    }
}
