<?php

namespace App\Console\Commands;

use App\Services\DilVsSbidApplyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EbayPmtRuleAutoPushCommand extends Command
{
    protected $signature = 'ebay:pmt-rule-auto-push
        {--mismatch : Only ads whose stored C Bid differs from the PMT rule bid}';

    protected $description = 'eBay 1: push the Ads Rule PMT bid (or pause the ad) while the PMT switch is on.';

    public function handle(DilVsSbidApplyService $apply): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $lock = Cache::lock('ebay-pmt-rule-auto-push', 7200);
        if (! $lock->get()) {
            $this->warn('ebay1: PMT push already running — skip');

            return self::SUCCESS;
        }

        try {
            $result = $apply->applyPmtChanged((bool) $this->option('mismatch'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        if (($result['error'] ?? '') === 'Ads Rule PMT is off') {
            $this->line('ebay1: PMT switch is off, nothing pushed');

            return self::SUCCESS;
        }
        if (! empty($result['error'])) {
            $this->error('ebay1: '.$result['error']);

            return self::FAILURE;
        }
        if (! empty($result['stopped'])) {
            $this->warn('ebay1: PMT switch was turned off during the run — remaining pushes stopped');
        }

        $this->info(sprintf(
            'ebay1 PMT: pushed %d, unchanged %d, failed %d, skipped %d',
            (int) ($result['success'] ?? 0),
            (int) ($result['unchanged'] ?? 0),
            (int) ($result['failed'] ?? 0),
            (int) ($result['skipped'] ?? 0)
        ));

        return self::SUCCESS;
    }
}
