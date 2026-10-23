<?php

namespace App\Console\Commands;

use App\Services\Veeqo\VeeqoBillingSync;
use Illuminate\Console\Command;

class SyncVeeqoBillingCommand extends Command
{
    protected $signature = 'veeqo:sync-billing
        {--backfill : Pull every order created in the last --days days (resumes where it stopped)}
        {--days=365 : How far back the backfill goes}
        {--fresh : Start the backfill again from page 1}
        {--max-pages= : Stop after this many pages (backfill only)}';

    protected $description = 'Copy Veeqo shipment label charges into veeqo_billing_shipments (Veeqo Billing page)';

    public function handle(VeeqoBillingSync $sync): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $log = fn (string $line) => $this->line($line);

        if ($this->option('backfill')) {
            $max = $this->option('max-pages');
            $result = $sync->backfill(
                max(1, (int) $this->option('days')),
                ! $this->option('fresh'),
                is_numeric($max) ? (int) $max : null,
                $log
            );
        } else {
            $result = $sync->incremental(null, $log);
        }

        $this->{$result['done'] ? 'info' : 'warn'}($result['message']);

        return $result['done'] ? self::SUCCESS : self::FAILURE;
    }
}
