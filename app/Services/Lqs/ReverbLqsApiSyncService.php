<?php

namespace App\Services\Lqs;

use App\Models\LqsMarketplaceScore;
use App\Models\ReverbProduct;
use App\Services\ReverbApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Scores Reverb listings from the live seller API (GET /my/listings, 100 per page) and stores the LQS (0-100)
 * in lqs_marketplace_scores (marketplace = 'reverb'). A full run is a handful of requests.
 */
class ReverbLqsApiSyncService
{
    public const MARKETPLACE = 'reverb';

    public const LOCK_KEY = 'lqs-sync-reverb-lock';

    public const STATUS_KEY = 'lqs-sync-reverb-status';

    public function __construct(private readonly ReverbListingQualityScorer $scorer)
    {
    }

    public function isConfigured(): bool
    {
        $token = ReverbApiService::getReverbBearerToken();

        return is_string($token) && $token !== '';
    }

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }

    /**
     * Every listing with a SKU, keyed by normalized SKU (a live listing wins over an ended duplicate).
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetchAllListings(?callable $onPage = null): array
    {
        $out = [];
        $url = $this->apiBase().'/my/listings?state=all&per_page=100';
        $page = 0;

        while ($url && $page < 300) {
            $page++;
            $data = $this->get($url);
            foreach ((array) ($data['listings'] ?? []) as $listing) {
                if (! is_array($listing)) {
                    continue;
                }
                $this->keep($out, $listing);
            }
            if ($onPage) {
                $onPage($page, count($out));
            }
            $next = $data['_links']['next']['href'] ?? null;
            $url = $next ? trim((string) $next) : null;
            if ($url) {
                usleep(300_000);
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function fetchListing(string $sku): ?array
    {
        $want = self::normalizeSku($sku);
        $data = $this->get($this->apiBase().'/my/listings?'.http_build_query(['sku' => trim($sku), 'state' => 'all', 'per_page' => 50]));
        $found = [];
        foreach ((array) ($data['listings'] ?? []) as $listing) {
            if (is_array($listing) && self::normalizeSku((string) ($listing['sku'] ?? '')) === $want) {
                $this->keep($found, $listing);
            }
        }

        return $found[$want] ?? null;
    }

    /**
     * @param  array<string, mixed>  $listing
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function assess(array $listing): array
    {
        return $this->scorer->score($listing);
    }

    /**
     * @param  array<string, mixed>  $listing
     * @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>}
     */
    public function saveListing(array $listing): array
    {
        $key = self::normalizeSku((string) ($listing['sku'] ?? ''));
        if ($key === '') {
            throw new \RuntimeException('Reverb listing has no SKU.');
        }

        $result = $this->assess($listing);
        $row = ['lqs' => $result['score'], 'listing_id' => $result['facts']['listing_id'] ?: null];
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

    /** @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>} */
    public function syncSku(string $sku): array
    {
        $listing = $this->fetchListing($sku);
        if ($listing === null) {
            throw new \RuntimeException('SKU '.$sku.' was not found among your Reverb listings.');
        }

        return $this->saveListing($listing);
    }

    /** SKUs this app lists on Reverb (the page's "of N" denominator). */
    public function catalogSkus(): array
    {
        if (! Schema::hasTable((new ReverbProduct)->getTable())) {
            return [];
        }

        return ReverbProduct::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->pluck('sku')
            ->map(fn ($s) => self::normalizeSku((string) $s))
            ->unique()
            ->values()
            ->all();
    }

    /** @param  array<string, array<string, mixed>>  $out */
    private function keep(array &$out, array $listing): void
    {
        $key = self::normalizeSku((string) ($listing['sku'] ?? ''));
        if ($key === '') {
            return;
        }
        $isLive = fn (array $l) => $this->scorer->facts($l)['state'] === 'live';
        if (! isset($out[$key]) || (! $isLive($out[$key]) && $isLive($listing))) {
            $out[$key] = $listing;
        }
    }

    private function apiBase(): string
    {
        return rtrim((string) config('services.reverb.api_url', 'https://api.reverb.com/api'), '/');
    }

    /** @return array<string, mixed> */
    private function get(string $url): array
    {
        $token = ReverbApiService::getReverbBearerToken();
        if (! $token) {
            throw new \RuntimeException('Reverb is not configured (REVERB_CLIENT_ID/REVERB_CLIENT_SECRET or REVERB_TOKEN).');
        }

        $last = null;
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                ])
                ->get(trim($url));
            $last = $response;
            if ($response->successful()) {
                return (array) ($response->json() ?? []);
            }
            if (! in_array($response->status(), [408, 425, 429, 500, 502, 503, 504], true)) {
                break;
            }
            sleep(max((int) $response->header('Retry-After'), min(60, 3 * (2 ** ($attempt - 1)))));
        }

        throw new \RuntimeException('Reverb listings failed: HTTP '.($last?->status() ?? '?').' '.mb_substr((string) $last?->body(), 0, 200));
    }
}
