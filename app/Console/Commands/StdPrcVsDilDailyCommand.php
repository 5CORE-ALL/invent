<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Three times a day: Std prc vs dil → S PRC → marketplace push.
 * The analytics page does not need to be open. Page reload is unchanged.
 * Each child command keeps its own lock. A SKU whose live price already
 * matches is not queued, and a pending queue row for that SKU is updated
 * instead of duplicated.
 */
class StdPrcVsDilDailyCommand extends Command
{
    protected $signature = 'std-prc-vs-dil:apply
        {--dry-run : Calculate only. Do not save or push}';

    protected $description = 'Std prc vs dil for every pushable analytics page, then push prices that differ from the live listing.';

    public const LOCK_CACHE_KEY = 'std-prc-vs-dil-apply';

    public function handle(): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $lock = Cache::lock(self::LOCK_CACHE_KEY, 10800);
        if (! $lock->get()) {
            $this->warn('Already running — skip');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $anyFail = false;

        try {
            $save = $dry ? ['--dry-run' => true] : [];
            $saveAndPush = $dry ? ['--dry-run' => true] : ['--push' => true];
            $steps = [
                ['amazon:sprc-dil-auto-push', $save],
                ['ebay:rule-sprice-apply', ['channel' => 'all'] + $saveAndPush],
                ['dil:rule-sprice-apply', ['channel' => 'all'] + $saveAndPush],
                ['shopify-b2c:rule-sprice-apply', $saveAndPush],
                ['macys:rule-sprice-apply', $saveAndPush],
                ['purchasing-power:rule-sprice-apply', $save],
            ];

            foreach ($steps as [$command, $args]) {
                $this->info('→ '.$command);
                $code = Artisan::call($command, $args);
                $out = trim(Artisan::output());
                if ($out !== '') {
                    $this->line($out);
                }
                if ($code !== 0) {
                    $anyFail = true;
                    $this->error($command.' exited '.$code);
                }
            }
        } finally {
            $lock->release();
        }

        return $anyFail ? self::FAILURE : self::SUCCESS;
    }
}
