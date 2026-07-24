<?php

namespace App\Console\Commands;

use App\Services\TemuRecommendedPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchTemuRecommendedPrices extends Command
{
    protected $signature = 'temu:fetch-recommended-prices
                            {--type=1 : recommendedPriceType (Temu API int, default 1)}
                            {--batch=20 : Goods IDs per API request}';

    protected $description = 'Fetch Temu recommended supply prices (temu.local.goods.recommendedprice.query) and upsert into temu_r_pricing';

    public function handle(TemuRecommendedPriceService $service): int
    {
        $type = (int) $this->option('type');
        $batch = max(1, (int) $this->option('batch'));

        $this->info("Fetching Temu recommended prices (type={$type}, batch={$batch})...");
        Log::info('temu:fetch-recommended-prices started', compact('type', 'batch'));

        $stats = $service->fetchAndInsertAll($type, $batch);

        $this->info("✅ Done. Goods: {$stats['total_goods']}, Upserted SKUs: {$stats['upserted']}, Batches OK: {$stats['batches_ok']}, Fail: {$stats['batches_fail']}");
        Log::info('temu:fetch-recommended-prices finished', $stats);

        return $stats['batches_fail'] > 0 && $stats['upserted'] === 0 ? 1 : 0;
    }
}
