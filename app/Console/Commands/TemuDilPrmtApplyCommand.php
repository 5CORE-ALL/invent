<?php

namespace App\Console\Commands;

use App\Services\TemuDilPrmtApplyService;
use Illuminate\Console\Command;

/**
 * Saves Dil vs PRMT% on every SKU of Temu 1 / 2 / 3 (Promotions page "Save & Apply").
 * Temu prices come from Std Prc vs Dil, so nothing is pushed to Temu.
 */
class TemuDilPrmtApplyCommand extends Command
{
    protected $signature = 'temu:dil-prmt-apply
        {channel=all : temu, temu2, temu3 or all}
        {--dry-run : Compute only. Do NOT save PRMT%}';

    protected $description = 'Apply the shared Dil vs PRMT slabs to the saved PRMT% of every SKU on Temu 1/2/3 (no Temu push).';

    public function handle(TemuDilPrmtApplyService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $arg = strtolower(trim((string) $this->argument('channel')));
        $channels = $arg === 'all' || $arg === '' ? TemuDilPrmtApplyService::CHANNELS : [$arg];
        foreach ($channels as $c) {
            if (! in_array($c, TemuDilPrmtApplyService::CHANNELS, true)) {
                $this->error('channel must be one of: '.implode(', ', TemuDilPrmtApplyService::CHANNELS).' or all');

                return self::FAILURE;
            }
        }

        $failed = false;
        foreach ($channels as $channel) {
            $stats = $service->run($channel, (bool) $this->option('dry-run'), fn (string $msg) => $this->line($msg));
            foreach (array_slice($stats['errors'], 0, 10) as $err) {
                $this->warn('  · '.$err);
            }
            if ($stats['errors'] !== [] && $stats['saved'] === 0) {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
