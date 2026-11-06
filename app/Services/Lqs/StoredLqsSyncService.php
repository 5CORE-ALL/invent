<?php

namespace App\Services\Lqs;

use App\Models\LqsMarketplaceScore;
use App\Models\ProductMaster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Base for the LQS services that score listings from data this app has already synced (no live API calls):
 * Newegg, PLS and Business 5 Core B2B. A subclass defines MARKETPLACE / LOCK_KEY / STATUS_KEY, how to find the
 * listed SKUs, and how to build each listing's scoring context; this class scores and stores it in
 * lqs_marketplace_scores.
 */
abstract class StoredLqsSyncService
{
    abstract protected function scorer(): StoredListingScorer;

    abstract public function isConfigured(): bool;

    /**
     * Scoring context per listed SKU (or only $wanted), keyed by normalized SKU. Each context needs 'sku' (as in
     * Product Master) and 'listing_id'.
     *
     * @param  array<string, string>|null  $wanted  normalized SKU => Product Master SKU, or null for every listed SKU
     * @return array<string, array<string, mixed>>
     */
    abstract protected function buildContexts(?array $wanted): array;

    /**
     * Normalized SKUs listed on the marketplace and present in Product Master (cheap: no content).
     *
     * @param  array<string, string>  $master  normalized SKU => Product Master SKU
     * @return list<string>
     */
    abstract protected function listedKeys(array $master): array;

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }

    /** @return array<string, string> normalized SKU => Product Master SKU (parents excluded) */
    protected function masterSkus(): array
    {
        $out = [];
        foreach (ProductMaster::query()
            ->select(['id', 'sku'])
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->get() as $pm) {
            $out[self::normalizeSku((string) $pm->sku)] ??= trim((string) $pm->sku);
        }

        return $out;
    }

    /**
     * Context for every listed SKU (or just $only), keyed by normalized SKU.
     *
     * @param  list<string>|null  $only
     * @return array<string, array<string, mixed>>
     */
    public function listedContexts(?array $only = null): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $master = $this->masterSkus();
        if ($only !== null) {
            $want = array_flip(array_map([self::class, 'normalizeSku'], $only));
            $master = array_intersect_key($master, $want);
        }

        return $master === [] ? [] : $this->buildContexts($master);
    }

    /** SKUs listed on the marketplace that exist in Product Master (the page's "of N" denominator). */
    public function catalogSkus(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return Cache::remember('lqs-'.static::MARKETPLACE.'-catalog-skus', 60, fn () => $this->listedKeys($this->masterSkus()));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function assess(array $context): array
    {
        return $this->scorer()->score($context);
    }

    /**
     * Score a listing and save it.
     *
     * @param  array<string, mixed>  $context
     * @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>}
     */
    public function saveListing(array $context): array
    {
        $key = self::normalizeSku((string) ($context['sku'] ?? ''));
        if ($key === '') {
            throw new \RuntimeException('Listing has no SKU.');
        }

        $result = $this->assess($context);
        $row = ['lqs' => $result['score']];
        if (($context['listing_id'] ?? '') !== '') {
            $row['listing_id'] = (string) $context['listing_id'];
        }
        $price = (float) ($result['facts']['price'] ?? 0);
        if ($price > 0) {
            $row['price'] = $price;
        }
        if (Schema::hasColumn('lqs_marketplace_scores', 'audit_findings')) {
            $row['audit_findings'] = $result['findings'];
            $row['audit_suggestions'] = $result['suggestions'];
        }

        LqsMarketplaceScore::updateOrCreate(['marketplace' => static::MARKETPLACE, 'sku' => $key], $row);

        return [
            'sku' => $key,
            'lqs' => $result['score'],
            'findings' => $result['findings'],
            'suggestions' => $result['suggestions'],
            'checks' => $result['checks'],
        ];
    }

    /** @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>} */
    public function syncSku(string $sku): array
    {
        $context = $this->listedContexts([$sku])[self::normalizeSku($sku)] ?? null;
        if ($context === null) {
            throw new \RuntimeException('SKU '.$sku.' is not a listed '.static::MARKETPLACE.' product (no synced listing found).');
        }

        return $this->saveListing($context);
    }
}
