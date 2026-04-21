<?php

namespace App\Jobs;

use App\Services\SkuImageMarketplacePushProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated UI and primary flow use {@see SkuImageMarketplacePushProcessor} synchronously / via
 * {@see \App\Console\Commands\PushSkuImagesToReverbCommand}. This job remains for any legacy queue dispatch.
 */
class PushImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $imageMarketplaceMapId) {}

    public function handle(SkuImageMarketplacePushProcessor $processor): void
    {
        $processor->processMapById($this->imageMarketplaceMapId);
    }
}
