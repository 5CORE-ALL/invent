<?php

namespace App\Console\Commands;

use App\Models\TemuMetric;
use App\Services\TemuRecommendedPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchTemuRecommendedPrices extends Command
{
    protected $signature = 'temu:fetch-recommended-prices
                            {--type=1 : recommendedPriceType (Temu API int, default 1)}
                            {--batch=20 : Goods IDs per API request}
                            {--probe : Probe request variants for one goods ID and exit}
                            {--goods-id= : Goods ID for --probe (default: first from temu_metrics)}
                            {--no-language : Omit language from request}
                            {--no-type : Omit recommendedPriceType from request}';

    protected $description = 'Fetch Temu recommended supply prices and update temu_metrics.recommended_base_price';

    public function handle(TemuRecommendedPriceService $service): int
    {
        if ($this->option('probe')) {
            return $this->runProbe($service);
        }

        $type = (int) $this->option('type');
        $batch = max(1, (int) $this->option('batch'));
        $extra = [];
        if ($this->option('no-language')) {
            $extra['omit_language'] = true;
        }
        if ($this->option('no-type')) {
            $extra['omit_type'] = true;
        }

        $this->info("Fetching Temu recommended prices (type={$type}, batch={$batch})...");
        Log::info('temu:fetch-recommended-prices started', compact('type', 'batch', 'extra'));

        // Quick single-goods smoke test so failures are visible immediately
        $sampleGoods = TemuMetric::whereNotNull('goods_id')->where('goods_id', '!=', '')->value('goods_id');
        if ($sampleGoods) {
            $smoke = $service->fetchBatch([(int) $sampleGoods], $type, $this->option('no-language') ? '' : 'en', $extra);
            if (! $smoke['ok']) {
                $this->error('API smoke test failed for goods '.$sampleGoods);
                $this->error('['.($smoke['error_code'] ?? '?').'] '.($smoke['error_msg'] ?? 'unknown'));
                $this->warn('Run with --probe to try alternate request shapes:');
                $this->line('  php artisan temu:fetch-recommended-prices --probe --goods-id='.$sampleGoods);
            } else {
                $this->info('Smoke test OK for goods '.$sampleGoods.' (sku rows in response: '.count($smoke['goodsList'][0]['skuList'] ?? []).')');
            }
        }

        $stats = $service->fetchAndInsertAll($type, $batch, $extra);

        $this->info("✅ Done. Goods: {$stats['total_goods']}, Upserted SKUs: {$stats['upserted']}, Batches OK: {$stats['batches_ok']}, Fail: {$stats['batches_fail']}, Empty-OK: {$stats['empty_ok_batches']}");
        if (! empty($stats['last_error'])) {
            $this->error('Last API error: '.$stats['last_error']);
        }
        Log::info('temu:fetch-recommended-prices finished', $stats);

        return $stats['batches_fail'] > 0 && $stats['upserted'] === 0 ? 1 : 0;
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
        }

        return $anyOk ? 0 : 1;
    }
}
