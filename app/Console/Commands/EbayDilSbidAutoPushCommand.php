<?php

namespace App\Console\Commands;

use App\Models\Ebay2Metric;
use App\Models\EbayMetric;
use App\Services\DilVsSbidApplyService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Support\DilVsSbidRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EbayDilSbidAutoPushCommand extends Command
{
    protected $signature = 'ebay:dil-sbid-auto-push
        {account=all : ebay1, ebay2, or all}';

    protected $description = 'eBay 1 and 2: push Dil vs SBid only where Dil or CVR changed the bid.';

    public function handle(DilVsSbidApplyService $apply): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $accounts = $this->accounts((string) $this->argument('account'));
        if ($accounts === []) {
            $this->error('Unsupported account. Use ebay1, ebay2, or all.');

            return self::FAILURE;
        }

        $lock = Cache::lock('ebay-dil-sbid-auto-push', 7200);
        if (! $lock->get()) {
            $this->warn('Already running — skip');

            return self::SUCCESS;
        }

        try {
            foreach ($accounts as $account) {
                $this->runAccount($apply, $account);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function accounts(string $account): array
    {
        $account = strtolower(trim($account));
        if ($account === 'all') {
            return ['ebay1', 'ebay2'];
        }
        if (in_array($account, ['ebay1', 'ebay2'], true)) {
            return [$account];
        }

        return [];
    }

    private function runAccount(DilVsSbidApplyService $apply, string $account): void
    {
        $result = $account === 'ebay2'
            ? $apply->applyChanged(DilVsSbidRule::KEY_EBAY2, 'ebay2_campaign_ads', Ebay2Metric::class, Ebay2ApiService::class)
            : $apply->applyChanged(DilVsSbidRule::KEY_EBAY1, 'ebay_campaign_ads', EbayMetric::class, EbayApiService::class);

        if (! empty($result['error']) && ($result['error'] ?? '') === 'Dil vs SBid is off') {
            $this->line($account.': switch is off, nothing pushed');

            return;
        }
        if (! empty($result['error'])) {
            $this->error($account.': '.$result['error']);

            return;
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
