<?php

namespace App\Console\Commands;

use App\Services\CustomerCare\CarrierClaimIncentive;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CarrierClaimsSnapshotIncentive extends Command
{
    protected $signature = 'carrier-claims:snapshot-incentive
        {--department=Carrier : Issue department the Carrier Claims board is locked to}
        {--date= : Snapshot as of this date (YYYY-MM-DD, default: now)}';

    protected $description = 'Save the L30 claims-received amount as a history entry for the Carrier Claims Incentive badge (runs on the 1st of every month)';

    public function handle(): int
    {
        $department = trim((string) $this->option('department')) ?: 'Carrier';
        $asOf = $this->option('date') ? Carbon::parse((string) $this->option('date'))->endOfDay() : null;
        if ($asOf && $asOf->isFuture()) {
            $asOf = now();
        }

        $r = CarrierClaimIncentive::snapshot($department, $asOf);
        $this->info(sprintf(
            'Incentive snapshot %s (%s): %d claims received, $%s -> Rs %s',
            $r['date'],
            $department,
            $r['count'],
            number_format($r['amount'], 2),
            number_format($r['incentive'], 2)
        ));

        return self::SUCCESS;
    }
}
