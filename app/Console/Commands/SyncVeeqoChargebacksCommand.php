<?php

namespace App\Console\Commands;

use App\Services\Veeqo\ChargebackOsizeSync;
use App\Services\Veeqo\VeeqoChargebackSync;
use Illuminate\Console\Command;

class SyncVeeqoChargebacksCommand extends Command
{
    protected $signature = 'veeqo:sync-chargebacks {--days=30 : How many days of Amazon Finances to scan (365 for the first load)}';

    protected $description = 'Copy Veeqo / Buy Shipping label chargebacks from Amazon Finances into veeqo_chargebacks';

    public function handle(VeeqoChargebackSync $sync): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $result = $sync->sync(max(1, (int) $this->option('days')), fn (string $line) => $this->line($line));
        // New chargebacks change the report's Avg Chargeback Total, so push it to Shipping Master O-Size now.
        if ($result['done']) {
            $this->line(ChargebackOsizeSync::summary(app(ChargebackOsizeSync::class)->runAfterNewData(fn (string $l) => $this->line($l))));
        }
        $this->{$result['done'] ? 'info' : 'warn'}($result['message']);

        return $result['done'] ? self::SUCCESS : self::FAILURE;
    }
}
