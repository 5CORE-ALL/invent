<?php

namespace App\Console\Commands;

use App\Models\TemuMetric;
use App\Services\TemuRecommendedPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchTemuRecommendedPrices extends Command
{
    protected $signature = 'temu:fetch-recommended-prices
                            {--type=10 : recommendedPriceType: 10=low traffic, 20=restricted traffic (Traffic Boost)}
                            {--batch=20 : Goods IDs per API request}
                            {--probe : Probe request variants for one goods ID and exit}
                            {--goods-id= : Goods ID for --probe (default: first from temu_metrics)}
                            {--both : Fetch type 10 then 20 (20 overwrites recommended_base_price when present)}';

    protected $description = 'Fetch Temu recommended supply prices into temu_metrics.recommended_base_price (type 10=low traffic, 20=restricted/Traffic Boost)';

    public function handle(TemuRecommendedPriceService $service): int
    {
        if ($this->option('probe')) {
            return $this->runProbe($service);
        }

        $types = $this->option('both')
            ? [10, 20]
            : [(int) $this->option('type')];

        // Guard: Temu uses 10/20 — type=1 causes 150010002
        foreach ($types as $t) {
            if (! in_array($t, [10, 20], true)) {
                $this->error("Invalid --type={$t}. Use 10 (low traffic) or 20 (restricted traffic / Traffic Boost).");

                return 1;
            }
        }

        $batch = max(1, (int) $this->option('batch'));
        $extra = ['omit_language' => true]; // language optional; omit to avoid locale issues

        $this->info('Fetching Temu recommended prices (types='.implode(',', $types).", batch={$batch}) → temu_metrics.recommended_base_price");
        Log::info('temu:fetch-recommended-prices started', ['types' => $types, 'batch' => $batch]);

        $sampleGoods = TemuMetric::whereNotNull('goods_id')->where('goods_id', '!=', '')->value('goods_id');
        if ($sampleGoods) {
            $smokeType = $types[0];
            $smoke = $service->fetchBatch([(int) $sampleGoods], $smokeType, '', $extra);
            if (! $smoke['ok']) {
                $this->error('API smoke test failed for goods '.$sampleGoods.' (type='.$smokeType.')');
                $this->error('['.($smoke['error_code'] ?? '?').'] '.($smoke['error_msg'] ?? 'unknown'));
                $this->warn('Run: php artisan temu:fetch-recommended-prices --probe --goods-id='.$sampleGoods);
            } else {
                $this->info('Smoke test OK for goods '.$sampleGoods.' (sku rows: '.count($smoke['goodsList'][0]['skuList'] ?? []).')');
            }
        }

        $totalUpserted = 0;
        $anyFail = false;
        foreach ($types as $type) {
            $label = $type === 20 ? 'restricted traffic / Traffic Boost' : 'low traffic';
            $this->info("— Fetching type={$type} ({$label})...");
            $stats = $service->fetchAndInsertAll($type, $batch, $extra);
            $totalUpserted += $stats['upserted'];
            if ($stats['batches_fail'] > 0) {
                $anyFail = true;
            }
            $this->info("  Upserted: {$stats['upserted']}, OK batches: {$stats['batches_ok']}, Fail: {$stats['batches_fail']}");
            if (! empty($stats['last_error'])) {
                $this->error('  Last error: '.$stats['last_error']);
            }
            Log::info('temu:fetch-recommended-prices type done', ['type' => $type] + $stats);
        }

        $this->info("✅ Done. Total SKU updates: {$totalUpserted}");

        return ($anyFail && $totalUpserted === 0) ? 1 : 0;
    }

    private function runProbe(TemuRecommendedPriceService $service): int
    {
        $goodsId = $this->option('goods-id')
            ?: TemuMetric::whereNotNull('goods_id')->where('goods_id', '!=', '')->value('goods_id');

        if (! $goodsId) {
            $this->error('No goods_id found in temu_metrics');

            return 1;
        }

        $this->info("Probing recommended-price API for goodsId={$goodsId}...");
        $rows = $service->probe($goodsId);

        $this->table(
            ['Variant', 'OK', 'Error code', 'Error msg', 'SKU count', 'Result keys'],
            array_map(static function ($r) {
                return [
                    $r['label'],
                    $r['ok'] ? 'yes' : 'no',
                    $r['error_code'] ?? '',
                    $r['error_msg'] ?? '',
                    $r['sku_count'],
                    implode(',', $r['result_keys'] ?? []),
                ];
            }, $rows)
        );

        $anyOk = collect($rows)->contains(fn ($r) => $r['ok']);
        if (! $anyOk) {
            $this->warn('All variants failed. Check Temu Partner API permissions for temu.local.goods.recommendedprice.query');
        } else {
            $this->info('Use a working type, e.g.: php artisan temu:fetch-recommended-prices --type=10');
            $this->info('Traffic Boost / restricted: php artisan temu:fetch-recommended-prices --type=20');
        }

        return $anyOk ? 0 : 1;
    }
}
