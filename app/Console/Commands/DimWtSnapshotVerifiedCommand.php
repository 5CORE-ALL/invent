<?php

namespace App\Console\Commands;

use App\Services\DimWtVerifiedHistoryService;
use Illuminate\Console\Command;

class DimWtSnapshotVerifiedCommand extends Command
{
    protected $signature = 'dim-wt:snapshot-verified';

    protected $description = 'Save today\'s verified / not-verified SKU counts for the /dim-wt-master verified history graph.';

    public function handle(DimWtVerifiedHistoryService $service): int
    {
        $c = $service->record();
        if ($c === null) {
            $this->error('Could not save the verified snapshot (see the log).');

            return self::FAILURE;
        }
        $this->info("Verified {$c['verified']} | not verified {$c['not_verified']} | total {$c['total']}");

        return self::SUCCESS;
    }
}
