<?php

namespace App\Console\Commands;

use App\Models\PricePushPending;
use App\Services\PricePushPendingService;
use Illuminate\Console\Command;

class RetryPendingPricePushes extends Command
{
    protected $signature = 'price-push:retry-pending';

    protected $description = 'Hourly: re-push channel prices from the pricing modals that are still pending, then e-mail software5@5core.com if any is still not pushed';

    public function handle(PricePushPendingService $svc): int
    {
        if (! $svc->available()) {
            $this->warn('price_push_pendings table missing — run php artisan migrate');

            return self::SUCCESS;
        }

        [$tried, $ok] = $svc->retryAll();
        $this->info("Retried {$tried} pending push(es), {$ok} succeeded.");

        $alerted = $svc->alertDigestIfDue();
        if ($alerted > 0) {
            $this->warn("Alert e-mail sent for {$alerted} outstanding push(es).");
        }

        PricePushPending::where('status', 'pushed')->where('pushed_at', '<', now()->subDays(30))->delete();

        return self::SUCCESS;
    }
}
