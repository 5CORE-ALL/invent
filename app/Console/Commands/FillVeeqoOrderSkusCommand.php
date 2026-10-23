<?php

namespace App\Console\Commands;

use App\Services\Veeqo\VeeqoOrderSkuResolver;
use Illuminate\Console\Command;

class FillVeeqoOrderSkusCommand extends Command
{
    protected $signature = 'veeqo:fill-order-skus {--limit=500 : Max Veeqo lookups per run}';

    protected $description = 'Look up SKUs in the Veeqo API for chargeback orders no local order table has';

    public function handle(VeeqoOrderSkuResolver $resolver): int
    {
        $missing = $resolver->missingChargebackOrders();
        $this->line(count($missing).' chargeback orders without a SKU.');
        $result = $resolver->resolve($missing, max(1, (int) $this->option('limit')), fn (string $l) => $this->line($l));
        $this->info('Checked '.$result['checked'].' in Veeqo, found SKUs for '.$result['found'].', skipped '.$result['skipped'].' already checked.');
        if ($result['error']) {
            $this->warn($result['error']);
        }

        return self::SUCCESS;
    }
}
