<?php

namespace App\Console\Commands;

use App\Services\Ebay1VolumePricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Push the saved Multi Buy discounts (Buy 2 / Buy 3 / Buy 4 %) to every eBay 1 listing.
 * Started from the Multi Buy modal ("Save and Apply"); can also be run by hand.
 */
class Ebay1MultiBuyApplyCommand extends Command
{
    public const RESULT_KEY = 'ebay1_multi_buy_last_run';

    public const LOCK_KEY = 'ebay1_multi_buy_apply_lock';

    public const RULE_KEY = 'ebay1_multi_buy';

    protected $signature = 'ebay1:multi-buy-apply';

    protected $description = 'eBay 1: push the Multi Buy discounts (Buy 2 / 3 / 4) to all listings as volume pricing promotions.';

    public function handle(Ebay1VolumePricingService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $lock = Cache::lock(self::LOCK_KEY, 3 * 3600);
        if (! $lock->get()) {
            $this->warn('A Multi Buy apply is already running.');

            return self::SUCCESS;
        }

        try {
            $row = DB::table('ebay_sbid_rules')->where('key', self::RULE_KEY)->first();
            $stored = $row ? (json_decode((string) $row->rule, true) ?: []) : [];
            $discounts = [
                'buy2' => $stored['buy2'] ?? 1,
                'buy3' => $stored['buy3'] ?? 2,
                'buy4' => $stored['buy4'] ?? 3,
            ];
            $this->info(sprintf('Multi Buy: Buy 2 %s%%, Buy 3 %s%%, Buy 4 %s%%', $discounts['buy2'], $discounts['buy3'], $discounts['buy4']));
            Cache::put(self::RESULT_KEY, ['status' => 'running', 'message' => 'Applying to eBay...', 'at' => now()->toDateTimeString()], 7 * 86400);

            $result = $service->applyMultiBuy($discounts, fn (string $m) => $this->line($m));
        } catch (Throwable $e) {
            Cache::put(self::RESULT_KEY, ['status' => 'failed', 'message' => $e->getMessage(), 'at' => now()->toDateTimeString()], 7 * 86400);
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        Cache::put(self::RESULT_KEY, [
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
