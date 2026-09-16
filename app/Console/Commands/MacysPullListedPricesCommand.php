<?php

namespace App\Console\Commands;

use App\Services\Support\MacysDelayedPricePullStore;
use Illuminate\Console\Command;

class MacysPullListedPricesCommand extends Command
{
    protected $signature = 'macys:pull-listed-prices
        {--due : Run only if a post-push pull is due}
        {--force : Run the full MCM pull now}';

    protected $description = 'Full Macy listed-price pull (OF21). After a push this is scheduled for 10 minutes later.';

    public function handle(): int
    {
        if (! $this->option('force') && $this->option('due') && ! MacysDelayedPricePullStore::due()) {
            return self::SUCCESS;
        }

        if ($this->option('due') && ! $this->option('force')) {
            $taken = MacysDelayedPricePullStore::takeDue();
            if ($taken === null) {
                return self::SUCCESS;
            }
            $this->info('Due post-push pull: '.count($taken['skus'] ?? []).' pushed SKU(s), full MCM OF21.');
        } else {
            MacysDelayedPricePullStore::clear();
            $this->info('Full Macy listed-price pull now.');
        }

        return (int) $this->call('app:fetch-macy-products', [
            '--macy-mcm-only' => true,
            '--write-live' => true,
        ]);
    }
}
