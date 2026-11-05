<?php

namespace App\Services\Lqs;

use App\Models\EbayMetric;
use App\Models\LqsMarketplaceScore;
use App\Services\EbayApiService;
use Illuminate\Support\Facades\Schema;

/**
 * Pulls the live listing from eBay (Trading API GetItem) and stores an API-based LQS
 * in lqs_marketplace_scores (marketplace = 'ebay'), replacing the manual sheet value.
 */
class EbayLqsApiSyncService
{
    public const MARKETPLACE = 'ebay';

    /** Cache keys shared by the artisan command, the page button and the progress poller. */
    public const LOCK_KEY = 'lqs-sync-ebay-lock';

    public const STATUS_KEY = 'lqs-sync-ebay-status';

    public function __construct(
        private readonly EbayApiService $ebay,
        private readonly EbayListingQualityScorer $scorer,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->ebay->isConfigured();
    }

    /**
     * Fetch + score one listing without saving (used to ground the AI audit).
     *
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function assess(string $itemId): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            throw new \RuntimeException('This SKU has no eBay item ID (ebay_metrics.item_id).');
        }

        $resp = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $resp = $this->ebay->getItem($itemId);
            if ($resp !== null) {
                break;
            }
            if ($attempt < 3) {
                sleep($attempt); // 1s, 2s back-off
            }
        }

        if ($resp === null) {
            $why = trim($this->ebay->lastGetItemError);
            throw new \RuntimeException('eBay GetItem failed for item '.$itemId.($why !== '' ? ': '.$why : '.'));
        }

        $item = $resp['Item'] ?? null;
        if (! is_array($item)) {
            throw new \RuntimeException('eBay returned no Item for '.$itemId.'.');
        }

        return $this->scorer->score($item);
    }

    /**
     * Score one SKU from the eBay API and save it.
     *
     * @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>, listing_id: string}
     */
    public function syncSku(string $sku): array
    {
        $metric = $this->metricForSku($sku);
        if (! $metric || trim((string) $metric->item_id) === '') {
            throw new \RuntimeException('SKU '.$sku.' has no eBay item ID in ebay_metrics.');
        }

        $result = $this->assess((string) $metric->item_id);

        $row = [
            'lqs' => $result['score'],
            'listing_id' => (string) $metric->item_id,
        ];
        if (Schema::hasColumn('lqs_marketplace_scores', 'audit_findings')) {
            $row['audit_findings'] = $result['findings'];
            $row['audit_suggestions'] = $result['suggestions'];
        }

        LqsMarketplaceScore::updateOrCreate(
            ['marketplace' => self::MARKETPLACE, 'sku' => strtoupper(trim($sku))],
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

    public function metricForSku(string $sku): ?EbayMetric
    {
        $key = strtoupper(str_replace("\u{00a0}", ' ', trim($sku)));
        if ($key === '' || ! Schema::hasTable('ebay_metrics')) {
            return null;
        }

        return EbayMetric::query()
            // Bound U+00A0 string (not CHAR(160)): CHAR(160) is an invalid byte on a utf8mb4 connection.
            ->whereRaw('UPPER(TRIM(REPLACE(sku, ?, " "))) = ?', ["\u{00a0}", $key])
            ->whereNotNull('item_id')
            ->orderByDesc('id')
            ->first();
    }
}
