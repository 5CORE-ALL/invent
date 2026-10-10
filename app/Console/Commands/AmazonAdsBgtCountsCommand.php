<?php

namespace App\Console\Commands;

use App\Support\AmazonAdsBgtCountCalculator;
use App\Support\AmazonAdsBgtCountRunner;
use Illuminate\Console\Command;

class AmazonAdsBgtCountsCommand extends Command
{
    protected $signature = 'amazon-ads:bgt-counts';

    protected $description = 'Count campaigns in each BGT rule band and save the counts';

    public function handle(AmazonAdsBgtCountCalculator $calculator): int
    {
        $path = AmazonAdsBgtCountRunner::lockPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fh = fopen($path, 'c');
        if ($fh === false) {
            $this->error('Could not open the count lock.');

            return 1;
        }
        if (! flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            $this->info('BGT count is already running.');

            return 0;
        }

        try {
            do {
                $saved = $calculator->calculate();
                $campaigns = (int) ($saved['sum']['campaigns'] ?? 0);
                $this->info('Saved BGT counts for '.$campaigns.' campaigns.');
                $again = is_file(AmazonAdsBgtCountRunner::rerunPath());
                if ($again) {
                    @unlink(AmazonAdsBgtCountRunner::rerunPath());
                }
            } while ($again);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return 0;
    }
}
