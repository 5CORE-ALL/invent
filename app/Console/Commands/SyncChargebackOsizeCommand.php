<?php

namespace App\Console\Commands;

use App\Services\Veeqo\ChargebackOsizeSync;
use Illuminate\Console\Command;

class SyncChargebackOsizeCommand extends Command
{
    protected $signature = 'veeqo:chargeback-osize';

    protected $description = 'Fill Shipping Master O-Size Charge with each parent\'s 12-month chargeback per unit sold (applied to every SKU of the parent) (positive)';

    public function handle(ChargebackOsizeSync $sync): int
    {
        $lock = \Illuminate\Support\Facades\Cache::lock(ChargebackOsizeSync::LOCK, 600);
        if (! $lock->get()) {
            $this->warn('Skipped: another O-Size fill is already running.');

            return self::SUCCESS;
        }
        try {
            $r = $sync->run(fn (string $l) => $this->line($l));
        } catch (\Throwable $e) {
            $this->error('Failed: '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
        $this->info("Parents with a figure: {$r['rows']}, updated {$r['updated']}, unchanged {$r['unchanged']}, not in Shipping Master {$r['missing']}, failed {$r['failed']}.");

        return $r['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
