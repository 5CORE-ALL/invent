<?php

namespace App\Console\Commands;

use App\Models\PricePushPending;
use App\Services\PricePushPendingService;
use Illuminate\Console\Command;

/**
 * Started in the background (detached) by POST /price-push-background: pushes the rows of that click at once (Amazon first),
 * then helps drain the shared FIFO queue. It runs outside the web request on purpose — under Apache mod_php a request cannot
 * be released before its work ends, so the page would not get its answer (and could not show progress) until the pushes ended.
 */
class RunExpressPricePush extends Command
{
    protected $signature = 'price-push:express {ids* : price_push_pendings row ids to push right now}';

    protected $description = 'Push the given queued price-push rows immediately (Amazon first), then drain the shared queue';

    public function handle(PricePushPendingService $svc): int
    {
        @ignore_user_abort(true);
        @set_time_limit(0);

        $ids = array_values(array_filter(array_map('intval', (array) $this->argument('ids'))));
        if ($ids) {
            $jobs = PricePushPending::whereIn('id', $ids)->get(['sku', 'marketplace'])
                ->map(fn ($r) => ['sku' => $r->sku, 'marketplace' => $r->marketplace])->all();
            [$tried, $ok] = $svc->runExpress($jobs);
            $this->info("Express: pushed {$tried} row(s), {$ok} succeeded.");
        }
        [$tried, $ok, $ran] = $svc->drainQueue();
        if ($ran && $tried > 0) {
            $this->info("Queue: pushed {$tried} queued row(s), {$ok} succeeded.");
        }

        return self::SUCCESS;
    }
}
