<?php

namespace App\Console\Commands;

use App\Services\Veeqo\TemuChargebackSkuResolver;
use Illuminate\Console\Command;

class ResolveTemuChargebackSkusCommand extends Command
{
    protected $signature = 'temu:chargeback-skus
        {--channel=all : temu1, temu2, temu3 or all}
        {--limit=500 : Most Temu orders to look up per channel}
        {--probe : Check 3 orders and print the raw API responses}';

    protected $description = 'Find SKUs for Temu shipping label (DO…) chargebacks via the Temu order and shipment APIs';

    public function handle(TemuChargebackSkuResolver $resolver): int
    {
        $channel = (string) $this->option('channel');
        $channels = $channel === 'all' ? array_keys(TemuChargebackSkuResolver::CHANNELS) : [$channel];
        $probe = (bool) $this->option('probe');
        $failed = false;

        foreach ($channels as $ch) {
            if (! isset(TemuChargebackSkuResolver::CHANNELS[$ch])) {
                $this->error('Unknown channel '.$ch.' (use temu1, temu2, temu3 or all).');

                return self::FAILURE;
            }
            $name = TemuChargebackSkuResolver::CHANNELS[$ch]['name'];
            $r = $resolver->resolve($ch, $probe ? 3 : max(1, (int) $this->option('limit')), $probe, fn (string $l) => $this->line($l));
            $this->info($name.': '.$r['missing'].' label ids without SKU; checked '.$r['orders_checked'].' orders; matched '.$r['labels_found'].' label ids.');
            if ($r['error']) {
                $this->error($name.': '.$r['error']);
                $failed = $failed || $r['orders_checked'] === 0 && $r['missing'] > 0;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
