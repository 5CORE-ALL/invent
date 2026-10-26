<?php

namespace App\Console\Commands;

use App\Services\Veeqo\ChargebackOsizeSync;
use Illuminate\Console\Command;

class SyncChargebackOsizeCommand extends Command
{
    protected $signature = 'veeqo:chargeback-osize';

    protected $description = 'Fill Shipping Master O-Size Charge with each SKU\'s 12-month chargeback per unit sold (positive)';

    public function handle(ChargebackOsizeSync $sync): int
    {
        $r = $sync->runAfterNewData(fn (string $l) => $this->line($l));
        if ($r === null) {
            $this->warn('Skipped: another O-Size fill is already running, or it failed (see log).');

            return self::SUCCESS;
        }
        $this->info("SKUs with a figure: {$r['rows']}, updated {$r['updated']}, unchanged {$r['unchanged']}, not in Shipping Master {$r['missing']}, failed {$r['failed']}.");

        return $r['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
