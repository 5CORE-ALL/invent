<?php

namespace App\Console\Commands;

use App\Services\DilVsSbidApplyService;
use App\Support\PmtCategoryBidRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EbayPmtRuleAutoPushCommand extends Command
{
    protected $signature = 'ebay:pmt-rule-auto-push
        {account=all : ebay1, ebay2, ebay3, or all}
        {--mismatch : Only ads whose stored C Bid differs from the PMT rule bid}';

    protected $description = 'eBay 1, 2 and 3: push the Ads Rule PMT bid (or pause the ad) while that account\'s PMT switch is on.';

    public function handle(DilVsSbidApplyService $apply): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $arg = strtolower(trim((string) $this->argument('account')));
        $accounts = $arg === 'all' ? PmtCategoryBidRule::ACCOUNTS : (in_array($arg, PmtCategoryBidRule::ACCOUNTS, true) ? [$arg] : []);
        if ($accounts === []) {
            $this->error('Unsupported account. Use ebay1, ebay2, ebay3, or all.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($accounts as $account) {
            $failed = $this->runAccount($apply, $account) === false || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function runAccount(DilVsSbidApplyService $apply, string $account): bool
    {
        $lock = Cache::lock('ebay-pmt-rule-auto-push-'.$account, 7200);
        if (! $lock->get()) {
            $this->warn($account.': PMT push already running, skip');

            return true;
        }

        try {
            $result = $apply->applyPmtChanged($account, (bool) $this->option('mismatch'));
        } catch (Throwable $e) {
            $this->error($account.': '.$e->getMessage());

            return false;
        } finally {
            $lock->release();
        }

        if (($result['error'] ?? '') === 'Ads Rule PMT is off') {
            $this->line($account.': PMT switch is off, nothing pushed');

            return true;
        }
        if (! empty($result['error'])) {
            $this->error($account.': '.$result['error']);

            return false;
        }
        if (! empty($result['stopped'])) {
            $this->warn($account.': PMT switch was turned off during the run, remaining pushes stopped');
        }

        $this->info(sprintf(
            '%s PMT: pushed %d, unchanged %d, failed %d, skipped %d',
            $account,
            (int) ($result['success'] ?? 0),
            (int) ($result['unchanged'] ?? 0),
            (int) ($result['failed'] ?? 0),
            (int) ($result['skipped'] ?? 0)
        ));

        // Why pushes failed: the most common reasons (the result rows are not stored anywhere else).
        $reasons = [];
        foreach ($result['results'] ?? [] as $row) {
            if (! in_array(strtolower((string) ($row['status'] ?? '')), ['failed', 'error'], true)) {
                continue;
            }
            $why = (string) ($row['reason'] ?? $row['error'] ?? $row['message'] ?? 'unknown');
            $why = mb_substr(preg_replace('/\d{6,}/', 'N', $why) ?? $why, 0, 200);
            $reasons[$why] = ($reasons[$why] ?? 0) + 1;
        }
        arsort($reasons);
        foreach (array_slice($reasons, 0, 8, true) as $why => $n) {
            $this->line(sprintf('  failed x%d: %s', $n, $why));
        }

        return true;
    }
}
