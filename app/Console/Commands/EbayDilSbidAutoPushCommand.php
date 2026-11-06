<?php

namespace App\Console\Commands;

use App\Models\EbayMetric;
use App\Services\DilVsSbidApplyService;
use App\Services\EbayApiService;
use App\Support\DilVsSbidRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EbayDilSbidAutoPushCommand extends Command
{
    protected $signature = 'ebay:dil-sbid-auto-push
        {account=all : ebay1 or all (Dil vs SBid is removed on eBay 2 / 3)}
        {--mismatch : Only ads whose stored C Bid differs from S Bid}';

    protected $description = 'eBay 1: push Dil vs SBid when Dil, views, or CVR changed the bid.';

    public function handle(DilVsSbidApplyService $apply): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $accounts = $this->accounts((string) $this->argument('account'));
        if ($accounts === []) {
            $this->error('Unsupported account. Dil vs SBid only exists for ebay1 (use ebay1 or all).');

            return self::FAILURE;
        }

        try {
            foreach ($accounts as $account) {
                $this->runAccount($apply, $account);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    public static function lockName(string $account): string
    {
        return 'ebay-dil-sbid-auto-push-'.$account;
    }

    /**
     * @return list<string>
     */
    private function accounts(string $account): array
    {
        $account = strtolower(trim($account));
        if ($account === 'all') {
            return ['ebay1'];
        }
        if ($account === 'ebay1') {
            return [$account];
        }

        return [];
    }

    private function runAccount(DilVsSbidApplyService $apply, string $account): void
    {
        $lock = Cache::lock(self::lockName($account), 7200);
        if (! $lock->get()) {
            $this->warn($account.': already running — skip');

            return;
        }

        try {
            $this->pushAccount($apply, $account);
        } finally {
            $lock->release();
        }
    }

    private function pushAccount(DilVsSbidApplyService $apply, string $account): void
    {
        $mismatch = (bool) $this->option('mismatch');
        $result = $apply->applyChanged(DilVsSbidRule::KEY_EBAY1, 'ebay_campaign_ads', EbayMetric::class, EbayApiService::class, $mismatch);

        if (! empty($result['error']) && ($result['error'] ?? '') === 'Dil vs SBid is off') {
            $this->line($account.': switch is off, nothing pushed');

            return;
        }
        if (($result['error'] ?? '') === 'Ads Rule PMT is on') {
            $this->line($account.': Ads Rule PMT is on and owns the bids, Dil vs SBid not pushed');

            return;
        }
        if (! empty($result['error'])) {
            $this->error($account.': '.$result['error']);

            return;
        }

        if (! empty($result['stopped'])) {
            $this->warn($account.': switch was turned off during the run — remaining pushes stopped');
        }

        $this->info(sprintf(
            '%s: pushed %d, unchanged %d, failed %d, skipped %d',
            $account,
            (int) ($result['success'] ?? 0),
            (int) ($result['unchanged'] ?? 0),
            (int) ($result['failed'] ?? 0),
            (int) ($result['skipped'] ?? 0)
        ));
    }
}
