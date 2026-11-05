<?php

namespace App\Services\Lqs;

use App\Models\DobaMetric;
use App\Models\LqsMarketplaceScore;
use App\Services\DobaApiService;
use Illuminate\Support\Facades\Schema;

/**
 * Scores Doba listings from the live goods/detail catalog (100 products per call) and stores the LQS (0-100)
 * in lqs_marketplace_scores (marketplace = 'doba'). One product (SPU) carries all its SKUs, so a full run is
 * only a handful of requests.
 */
class DobaLqsApiSyncService
{
    public const MARKETPLACE = 'doba';

    public const LOCK_KEY = 'lqs-sync-doba-lock';

    public const STATUS_KEY = 'lqs-sync-doba-status';

    public function __construct(
        private readonly DobaApiService $api,
        private readonly DobaListingQualityScorer $scorer,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->api->isConfigured();
    }

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }

    /**
     * Walk the whole catalog; $each(product) is called per SPU. Returns the number of SPUs seen.
     *
     * @param  callable(array<string, mixed>): void  $each
     */
    public function eachProduct(callable $each, ?callable $onPage = null): int
    {
        $seen = 0;
        $page = 1;

        do {
            $rows = $this->page($page, 100);
            foreach ($rows as $product) {
                if (is_array($product)) {
                    $each($product);
                    $seen++;
                }
            }
            if ($onPage) {
                $onPage($page, $seen);
            }
            $page++;
            usleep(300_000);
        } while (count($rows) === 100 && $page <= 200);

        return $seen;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $sku
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function assess(array $product, array $sku): array
    {
        return $this->scorer->score($product, $sku);
    }

    /**
     * Score a SKU and save it.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $sku
     * @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>}
     */
    public function saveSku(array $product, array $sku): array
    {
        $result = $this->assess($product, $sku);
        $key = self::normalizeSku((string) ($sku['skuCode'] ?? ''));
        if ($key === '') {
            throw new \RuntimeException('Doba SKU has no skuCode.');
        }

        $row = ['lqs' => $result['score'], 'listing_id' => $result['facts']['item_no'] ?: null];
        if (Schema::hasColumn('lqs_marketplace_scores', 'audit_findings')) {
            $row['audit_findings'] = $result['findings'];
            $row['audit_suggestions'] = $result['suggestions'];
        }

        LqsMarketplaceScore::updateOrCreate(['marketplace' => self::MARKETPLACE, 'sku' => $key], $row);

        return [
            'sku' => $key,
            'lqs' => $result['score'],
            'findings' => $result['findings'],
            'suggestions' => $result['suggestions'],
            'checks' => $result['checks'],
        ];
    }

    /**
     * Live product + SKU for one SKU code.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|null
     */
    public function fetchSku(string $skuCode): ?array
    {
        $want = self::normalizeSku($skuCode);
        $metric = DobaMetric::query()
            ->whereRaw('UPPER(TRIM(REPLACE(sku, ?, " "))) = ?', ["\u{00a0}", $want])
            ->orderByDesc('id')
            ->first();

        $filters = [];
        if ($metric && trim((string) $metric->goods_id) !== '') {
            $filters[] = ['spuId' => trim((string) $metric->goods_id)];
        }
        if ($metric && trim((string) $metric->item_id) !== '') {
            $filters[] = ['itemNo' => trim((string) $metric->item_id)];
        }
        if ($filters === []) {
            throw new \RuntimeException('SKU '.$skuCode.' is not in doba_metrics yet. Run the Doba metrics sync first.');
        }

        foreach ($filters as $extra) {
            foreach ($this->page(1, 50, $extra) as $product) {
                foreach ((array) ($product['skus'] ?? []) as $sku) {
                    if (is_array($sku) && self::normalizeSku((string) ($sku['skuCode'] ?? '')) === $want) {
                        return [$product, $sku];
                    }
                }
            }
        }

        return null;
    }

    /** @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>} */
    public function syncSku(string $skuCode): array
    {
        $found = $this->fetchSku($skuCode);
        if ($found === null) {
            throw new \RuntimeException('SKU '.$skuCode.' was not found in the live Doba catalog.');
        }

        return $this->saveSku($found[0], $found[1]);
    }

    /** SKUs this app lists on Doba (the page's "of N" denominator). */
    public function catalogSkus(): array
    {
        if (! Schema::hasTable((new DobaMetric)->getTable())) {
            return [];
        }

        return DobaMetric::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->pluck('sku')
            ->map(fn ($s) => self::normalizeSku((string) $s))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * One goods/detail page, with retries; an API error throws instead of looking like "no more pages".
     *
     * @param  array<string, mixed>  $extra
     * @return list<array<string, mixed>>
     */
    private function page(int $page, int $size, array $extra = []): array
    {
        $last = null;
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $response = $this->api->goodsDetailRaw($page, $size, $extra);
            $rows = $response['businessData']['data']['dsGoodsDetailResultVOS'] ?? null;
            if (is_array($rows)) {
                return $rows;
            }
            // A filtered lookup with no match can come back without the list; treat only that as empty.
            if ($extra !== [] && (string) ($response['responseCode'] ?? '') === '000000') {
                return [];
            }
            $last = $response;
            sleep(2 * $attempt);
        }

        $msg = (string) ($last['responseMessage'] ?? $last['message'] ?? $last['errors'] ?? 'unexpected response');
        throw new \RuntimeException('Doba goods/detail failed (page '.$page.'): '.mb_substr($msg, 0, 200));
    }
}
