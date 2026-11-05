<?php

namespace App\Services\Lqs;

use App\Models\LqsMarketplaceScore;
use App\Models\TemuMetric;
use App\Services\TemuApiService;
use Illuminate\Support\Facades\Schema;

/**
 * Pulls the live Temu listing (bg.local.goods.detail.query) and stores an API-based LQS (0-100)
 * in lqs_marketplace_scores (marketplace = 'temu'), replacing the manual sheet value.
 *
 * Temu groups variations under one goods_id, so one API call scores every SKU of that goods.
 */
class TemuLqsApiSyncService
{
    public const MARKETPLACE = 'temu';

    public const LOCK_KEY = 'lqs-sync-temu-lock';

    public const STATUS_KEY = 'lqs-sync-temu-status';

    public function __construct(
        private readonly TemuApiService $temu,
        private readonly TemuListingQualityScorer $scorer,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->temu->isConfigured();
    }

    /**
     * Fetch + score one goods without saving.
     *
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function assess(string $goodsId): array
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '' || ! ctype_digit($goodsId)) {
            throw new \RuntimeException('This SKU has no Temu goods ID (temu_metrics.goods_id).');
        }

        $resp = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $resp = $this->temu->callOpenApi('bg.local.goods.detail.query', ['goodsId' => (int) $goodsId], 45);
            if ($resp['success'] ?? false) {
                break;
            }
            if ($attempt < 3) {
                sleep($attempt); // 1s, 2s back-off
            }
        }

        if (! ($resp['success'] ?? false)) {
            $why = trim((string) ($resp['errorMsg'] ?? $resp['message'] ?? ''));
            throw new \RuntimeException('Temu goods detail failed for '.$goodsId.($why !== '' ? ': '.$why : '.'));
        }

        $goods = $resp['result'] ?? null;
        if (! is_array($goods) || trim((string) ($goods['goodsName'] ?? '')) === '') {
            throw new \RuntimeException('Temu returned no listing for goods '.$goodsId.'.');
        }

        return $this->scorer->score($goods);
    }

    /**
     * Score one goods and save the result for every given SKU.
     *
     * @param  list<string>  $skus
     * @return array{lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>, goods_id: string, saved: int}
     */
    public function syncGoods(string $goodsId, array $skus): array
    {
        $result = $this->assess($goodsId);

        $row = [
            'lqs' => $result['score'],
            'listing_id' => $goodsId,
        ];
        if (Schema::hasColumn('lqs_marketplace_scores', 'audit_findings')) {
            $row['audit_findings'] = $result['findings'];
            $row['audit_suggestions'] = $result['suggestions'];
        }

        $saved = 0;
        foreach ($skus as $sku) {
            $key = strtoupper(trim($sku));
            if ($key === '') {
                continue;
            }
            LqsMarketplaceScore::updateOrCreate(['marketplace' => self::MARKETPLACE, 'sku' => $key], $row);
            $saved++;
        }

        return [
            'lqs' => $result['score'],
            'findings' => $result['findings'],
            'suggestions' => $result['suggestions'],
            'checks' => $result['checks'],
            'goods_id' => $goodsId,
            'saved' => $saved,
        ];
    }

    /**
     * Score the goods behind one SKU and save it for that SKU and its sibling variations.
     *
     * @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>, listing_id: string}
     */
    public function syncSku(string $sku): array
    {
        $metric = $this->metricForSku($sku);
        $goodsId = trim((string) ($metric->goods_id ?? ''));
        if ($goodsId === '') {
            throw new \RuntimeException('SKU '.$sku.' has no Temu goods ID in temu_metrics.');
        }

        $siblings = TemuMetric::query()
            ->where('goods_id', $goodsId)
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->pluck('sku')
            ->all();
        $siblings[] = $sku;

        $res = $this->syncGoods($goodsId, array_values(array_unique($siblings)));

        return [
            'sku' => $sku,
            'lqs' => $res['lqs'],
            'findings' => $res['findings'],
            'suggestions' => $res['suggestions'],
            'checks' => $res['checks'],
            'listing_id' => $goodsId,
        ];
    }

    public function metricForSku(string $sku): ?TemuMetric
    {
        $key = strtoupper(str_replace("\u{00a0}", ' ', trim($sku)));
        if ($key === '' || ! Schema::hasTable('temu_metrics')) {
            return null;
        }

        return TemuMetric::query()
            // Bound U+00A0 string (not CHAR(160)): CHAR(160) is an invalid byte on a utf8mb4 connection.
            ->whereRaw('UPPER(TRIM(REPLACE(sku, ?, " "))) = ?', ["\u{00a0}", $key])
            ->whereNotNull('goods_id')->where('goods_id', '!=', '')
            ->orderByDesc('id')
            ->first();
    }
}
