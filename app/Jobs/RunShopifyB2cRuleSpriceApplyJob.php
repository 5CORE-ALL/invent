<?php

namespace App\Jobs;

use App\Services\ShopifyB2cRuleSpriceApplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunShopifyB2cRuleSpriceApplyJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    public int $tries = 1;

    public int $uniqueFor = 7200;

    public function uniqueId(): string
    {
        return 'shopify-b2c-rule-sprice-apply';
    }

    public function handle(ShopifyB2cRuleSpriceApplyService $service): void
    {
        $summary = $service->run();
        $stats = $summary['stats'] ?? [];
        Log::info('[ShopifyB2cRuleSpriceApply] job finished', [
            'applied' => $stats['applied'] ?? 0,
            'unchanged' => $stats['skipped_unchanged'] ?? 0,
            'skipped' => $stats['skipped'] ?? 0,
        ]);
    }
}
