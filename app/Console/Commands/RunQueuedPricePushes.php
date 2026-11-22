<?php

namespace App\Console\Commands;

use App\Services\PricePushPendingService;
use Illuminate\Console\Command;

class RunQueuedPricePushes extends Command
{
    protected $signature = 'price-push:run-queued';

    protected $description = 'Every minute: drain the sequential price-push queue (one push at a time, first in first out) when no worker is running, so no push is abandoned';

    public function handle(PricePushPendingService $svc): int
    {
        [$tried, $ok, $ran] = $svc->drainQueue();
        if ($ran && $tried > 0) {
            $this->info("Queue worker took over: pushed {$tried} queued price(s), {$ok} succeeded.");
        }

        return self::SUCCESS;
    }
}
