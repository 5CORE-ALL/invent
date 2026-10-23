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
        $r = $sync->run(fn (string $l) => $this->line($l));
        $this->info("SKUs with a figure: {$r['rows']}, updated {$r['updated']}, unchanged {$r['unchanged']}, not in Shipping Master {$r['missing']}, failed {$r['failed']}.");

        return $r['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
