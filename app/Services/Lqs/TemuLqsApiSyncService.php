<?php

namespace App\Services\Lqs;

use App\Models\LqsMarketplaceScore;
use App\Models\Temu2Metric;
use App\Models\TemuMetric;
use App\Services\Temu2ApiService;
use App\Services\TemuApiService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Pulls the live Temu listing (bg.local.goods.detail.query) and stores an API-based LQS (0-100)
 * in lqs_marketplace_scores (marketplace = 'temu' | 'temu2'), replacing the manual sheet value.
 *
 * Each Temu account has its own API credentials and its own *_metrics table, so every method takes the
 * marketplace slug. Temu groups variations under one goods_id, so one API call scores every SKU of that goods.
 */
class TemuLqsApiSyncService
{
    public const MARKETPLACE = 'temu';

    /** slug => [api service, metrics model, metrics table, label] */
    private const CHANNELS = [
        'temu' => [TemuApiService::class, TemuMetric::class, 'temu_metrics', 'Temu'],
        'temu2' => [Temu2ApiService::class, Temu2Metric::class, 'temu2_metrics', 'Temu 2'],
    ];

    public function __construct(
        private readonly TemuListingQualityScorer $scorer,
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
     * Fetch + score one goods without saving.
     *
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function assess(string $goodsId, string $slug = self::MARKETPLACE): array
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '' || ! ctype_digit($goodsId)) {
            throw new \RuntimeException('This SKU has no '.self::label($slug).' goods ID ('.self::metricsTable($slug).'.goods_id).');
        }

        $api = $this->api($slug);

        $resp = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $resp = $api->callOpenApi('bg.local.goods.detail.query', ['goodsId' => (int) $goodsId], 45);
            if ($resp['success'] ?? false) {
                break;
            }
            if ($attempt < 3) {
                sleep($attempt); // 1s, 2s back-off
            }
        }

        if (! ($resp['success'] ?? false)) {
            $why = trim((string) ($resp['errorMsg'] ?? $resp['message'] ?? ''));
            throw new \RuntimeException(self::label($slug).' goods detail failed for '.$goodsId.($why !== '' ? ': '.$why : '.'));
        }

        $goods = $resp['result'] ?? null;
        if (! is_array($goods) || trim((string) ($goods['goodsName'] ?? '')) === '') {
            throw new \RuntimeException(self::label($slug).' returned no listing for goods '.$goodsId.'.');
        }

        return $this->scorer->score($goods);
    }

    /**
     * Score one goods and save the result for every given SKU.
     *
     * @param  list<string>  $skus
     * @return array{lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>, goods_id: string, saved: int}
     */
    public function syncGoods(string $goodsId, array $skus, string $slug = self::MARKETPLACE): array
    {
        $result = $this->assess($goodsId, $slug);

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
            LqsMarketplaceScore::updateOrCreate(['marketplace' => $slug, 'sku' => $key], $row);
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
    public function syncSku(string $sku, string $slug = self::MARKETPLACE): array
    {
        $metric = $this->metricForSku($sku, $slug);
        $goodsId = trim((string) ($metric->goods_id ?? ''));
        if ($goodsId === '') {
            throw new \RuntimeException('SKU '.$sku.' has no '.self::label($slug).' goods ID in '.self::metricsTable($slug).'.');
        }

        $model = self::metricsModel($slug);
        $siblings = $model::query()
            ->where('goods_id', $goodsId)
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->pluck('sku')
            ->all();
        $siblings[] = $sku;

        $res = $this->syncGoods($goodsId, array_values(array_unique($siblings)), $slug);

        return [
            'sku' => $sku,
            'lqs' => $res['lqs'],
            'findings' => $res['findings'],
            'suggestions' => $res['suggestions'],
            'checks' => $res['checks'],
            'listing_id' => $goodsId,
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
            ->whereNotNull('goods_id')->where('goods_id', '!=', '')
            ->orderByDesc('id')
            ->first();
    }

    private function api(string $slug): TemuApiService
    {
        return app(self::channel($slug)[0]);
    }

    /** @return array{0: class-string<TemuApiService>, 1: class-string<Model>, 2: string, 3: string} */
    private static function channel(string $slug): array
    {
        return self::CHANNELS[$slug] ?? throw new \InvalidArgumentException('Unsupported Temu LQS channel: '.$slug);
    }
}
