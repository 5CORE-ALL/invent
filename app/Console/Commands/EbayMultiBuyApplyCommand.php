<?php

namespace App\Console\Commands;

use App\Services\Ebay1VolumePricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Push the saved Multi Buy discounts (Buy 2 / Buy 3 / Buy 4 %) to every listing of one eBay account.
 * Started from the Multi Buy modal ("Save and Apply"); can also be run by hand.
 */
class EbayMultiBuyApplyCommand extends Command
{
    public const ACCOUNTS = ['ebay1', 'ebay2', 'ebay3'];

    protected $signature = 'ebay:multi-buy-apply {account=ebay1 : ebay1, ebay2 or ebay3}';

    protected $description = 'eBay 1 / 2 / 3: push the Multi Buy discounts (Buy 2 / 3 / 4) to all listings as volume pricing promotions.';

    public static function resultKey(string $account): string
    {
        return $account.'_multi_buy_last_run';
    }

    public static function lockKey(string $account): string
    {
        return $account.'_multi_buy_apply_lock';
    }

    public static function ruleKey(string $account): string
    {
        return $account.'_multi_buy';
    }

    public function handle(): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $account = strtolower(trim((string) $this->argument('account')));
        if (! in_array($account, self::ACCOUNTS, true)) {
            $this->error('Unsupported account. Use ebay1, ebay2 or ebay3.');

            return self::FAILURE;
        }

        $lock = Cache::lock(self::lockKey($account), 3 * 3600);
        if (! $lock->get()) {
            $this->warn('A Multi Buy apply is already running for '.$account.'.');

            return self::SUCCESS;
        }

        try {
            $service = new Ebay1VolumePricingService(null, $account);
            $row = DB::table('ebay_sbid_rules')->where('key', self::ruleKey($account))->first();
            $stored = $row ? (json_decode((string) $row->rule, true) ?: []) : [];
            $discounts = [
                'buy2' => $stored['buy2'] ?? 1,
                'buy3' => $stored['buy3'] ?? 2,
                'buy4' => $stored['buy4'] ?? 3,
            ];
            $this->info(sprintf('%s Multi Buy: Buy 2 %s%%, Buy 3 %s%%, Buy 4 %s%%', $account, $discounts['buy2'], $discounts['buy3'], $discounts['buy4']));
            Cache::put(self::resultKey($account), ['status' => 'running', 'message' => 'Applying to eBay...', 'at' => now()->toDateTimeString()], 7 * 86400);

            $result = $service->applyMultiBuy($discounts, fn (string $m) => $this->line($m));
        } catch (Throwable $e) {
            Cache::put(self::resultKey($account), ['status' => 'failed', 'message' => $e->getMessage(), 'at' => now()->toDateTimeString()], 7 * 86400);
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        Cache::put(self::resultKey($account), [
            'status' => $result['status'],
            'message' => $result['message'],
            'listings' => $result['listings'],
            'errors' => array_slice($result['errors'], 0, 5),
            'at' => now()->toDateTimeString(),
        ], 7 * 86400);

        $this->info($result['message']);
        foreach (array_slice($result['errors'], 0, 15) as $err) {
            $this->warn('  · '.$err);
        }

        return $result['status'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
