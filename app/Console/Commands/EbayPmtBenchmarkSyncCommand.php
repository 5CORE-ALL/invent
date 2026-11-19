<?php

namespace App\Console\Commands;

use App\Services\PmtBenchmarkSync;
use App\Support\PmtCategoryBidRule;
use Illuminate\Console\Command;

class EbayPmtBenchmarkSyncCommand extends Command
{
    protected $signature = 'ebay:pmt-benchmark-sync {account=all : ebay1 | ebay2 | ebay3 | all} {--no-push : Save the ranges but do not start the eBay push}';

    protected $description = 'Set Ads Rule PMT Highest / Lowest of every category to its benchmark (Top / Bottom 10% promoted listings ad rate) and push when the switch is on.';

    public function handle(PmtBenchmarkSync $sync): int
    {
        $arg = strtolower((string) $this->argument('account'));
        $accounts = $arg === 'all' ? PmtCategoryBidRule::ACCOUNTS : [$arg];

        foreach ($accounts as $account) {
            $r = $sync->run($account, ! $this->option('no-push'));
            $this->line(sprintf(
                '%s: %s, %d of %d benchmarked categories changed%s%s%s',
                $account,
                $r['status'],
                $r['changed'],
                $r['categories'],
                $r['file'] ? ' ('.$r['file'].')' : '',
                $r['pushed'] ? ', push started' : '',
                $r['note'] ? ' - '.$r['note'] : ''
            ));
        }

        return self::SUCCESS;
    }
}
