<?php

namespace App\Services\Lqs;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\LqsMarketplaceScore;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Pulls the live listing from eBay (Trading API GetItem) and stores an API-based LQS
 * in lqs_marketplace_scores (marketplace = 'ebay' | 'ebay2' | 'ebay3'), replacing the manual sheet value.
 *
 * Each eBay account (1, 2, 3) has its own API credentials and its own *_metrics table, so every method
 * takes the marketplace slug and resolves the matching API client + metrics model.
 */
class EbayLqsApiSyncService
{
    public const MARKETPLACE = 'ebay';

    /** slug => [api service, metrics model, metrics table, label] */
    private const CHANNELS = [
        'ebay' => [EbayApiService::class, EbayMetric::class, 'ebay_metrics', 'eBay'],
        'ebay2' => [Ebay2ApiService::class, Ebay2Metric::class, 'ebay_2_metrics', 'eBay 2'],
        'ebay3' => [EbayThreeApiService::class, Ebay3Metric::class, 'ebay_3_metrics', 'eBay 3'],
    ];

    public function __construct(
        private readonly EbayListingQualityScorer $scorer,
    ) {
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::CHANNELS);
    }

    public static function supports(string $slug): bool
    {
        return isset(self::CHANNELS[$slug]);
    }

    /** Cache keys shared by the artisan command, the page button and the progress poller (one set per account). */
    public static function lockKey(string $slug): string
    {
        return 'lqs-sync-'.$slug.'-lock';
    }

    public static function statusKey(string $slug): string
    {
        return 'lqs-sync-'.$slug.'-status';
    }

    public static function label(string $slug): string
    {
        return self::CHANNELS[$slug][3] ?? strtoupper($slug);
    }

    public static function metricsTable(string $slug): string
    {
        return self::channel($slug)[2];
    }

    /** @return class-string<Model> */
    public static function metricsModel(string $slug): string
    {
        return self::channel($slug)[1];
    }

    public function isConfigured(string $slug = self::MARKETPLACE): bool
    {
        return $this->api($slug)->isConfigured();
    }

    /**
     * Fetch + score one listing without saving (used to ground the AI audit).
     *
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function assess(string $itemId, string $slug = self::MARKETPLACE): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            throw new \RuntimeException('This SKU has no '.self::label($slug).' item ID ('.self::metricsTable($slug).'.item_id).');
        }

        $api = $this->api($slug);

        $resp = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $resp = $api->getItem($itemId);
            if ($resp !== null) {
                break;
            }
            if ($attempt < 3) {
                sleep($attempt); // 1s, 2s back-off
            }
        }

        if ($resp === null) {
            $why = trim((string) $api->lastGetItemError);
            throw new \RuntimeException(self::label($slug).' GetItem failed for item '.$itemId.($why !== '' ? ': '.$why : '.'));
        }

        $item = $resp['Item'] ?? null;
        if (! is_array($item)) {
            throw new \RuntimeException(self::label($slug).' returned no Item for '.$itemId.'.');
        }

        return $this->scorer->score($item);
    }

    /**
     * Score one SKU from the eBay API and save it.
     *
     * @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>, listing_id: string}
     */
    public function syncSku(string $sku, string $slug = self::MARKETPLACE): array
    {
        $metric = $this->metricForSku($sku, $slug);
        if (! $metric || trim((string) $metric->item_id) === '') {
            throw new \RuntimeException('SKU '.$sku.' has no '.self::label($slug).' item ID in '.self::metricsTable($slug).'.');
        }

        $result = $this->assess((string) $metric->item_id, $slug);

        $row = [
            'lqs' => $result['score'],
            'listing_id' => (string) $metric->item_id,
        ];
        if (Schema::hasColumn('lqs_marketplace_scores', 'audit_findings')) {
            $row['audit_findings'] = $result['findings'];
            $row['audit_suggestions'] = $result['suggestions'];
        }

        LqsMarketplaceScore::updateOrCreate(
            ['marketplace' => $slug, 'sku' => strtoupper(trim($sku))],
            $row
        );

        return [
            'sku' => $sku,
            'lqs' => $result['score'],
            'findings' => $result['findings'],
            'suggestions' => $result['suggestions'],
            'checks' => $result['checks'],
            'listing_id' => (string) $metric->item_id,
        ];
    }

    public function metricForSku(string $sku, string $slug = self::MARKETPLACE): ?Model
    {
        $key = strtoupper(str_replace("\u{00a0}", ' ', trim($sku)));
        if ($key === '' || ! Schema::hasTable(self::metricsTable($slug))) {
            return null;
        }

        $model = self::metricsModel($slug);

        return $model::query()
            // Bound U+00A0 string (not CHAR(160)): CHAR(160) is an invalid byte on a utf8mb4 connection.
            ->whereRaw('UPPER(TRIM(REPLACE(sku, ?, " "))) = ?', ["\u{00a0}", $key])
            ->whereNotNull('item_id')
            ->orderByDesc('id')
            ->first();
    }

    private function api(string $slug): object
    {
        return app(self::channel($slug)[0]);
    }

    /** @return array{0: class-string, 1: class-string<Model>, 2: string, 3: string} */
    private static function channel(string $slug): array
    {
        return self::CHANNELS[$slug] ?? throw new \InvalidArgumentException('Unsupported eBay LQS channel: '.$slug);
    }
}
