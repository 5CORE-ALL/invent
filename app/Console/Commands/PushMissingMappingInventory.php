<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Services\MarketplaceManager\MarketplaceMismatchInventoryPass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Daily push of every /map-issues mismatch SKU to its marketplace, then one retry for SKUs
 * still off. Runs in-process (not queued) so a dead mm-* queue worker cannot stop it.
 */
class PushMissingMappingInventory extends Command
{
    public const CHANNELS = [
        'tiktok2', 'purchasingpower', 'tiktok', 'newegg', 'aliexpress', 'amazon',
        'ebay3', 'shein', 'ebay1', 'topdawg', 'ebay2', 'reverb', 'b5cb2b',
        'macy', 'bestbuy', 'temu', 'temu2', 'temu3', 'pls', 'wayfair', 'faire', 'doba', 'alibaba',
    ];

    protected $signature = 'inventory:push-missing-mapping
        {--channel=* : Only these channels (e.g. --channel=tiktok2 --channel=newegg)}
        {--chunk=25 : SKUs per push call}
        {--dry-run : List the SKUs that would be pushed}
        {--status : Show the last automatic run per channel and exit}';

    protected $description = 'Push Shopify qty for every Missing Mapping (/map-issues) SKU, then retry the ones still mismatched';

    public const LAST_RUN_CACHE_PREFIX = 'mm_push_missing_mapping_last:';

    public function handle(MarketplaceMismatchInventoryPass $pass, MarketplaceListingQtyMatchService $match): int
    {
        @set_time_limit(0);
        // Full-catalog channels (TopDawg, eBay) exceed the CLI default; a fatal here used to kill every later channel.
        @ini_set('memory_limit', '2048M');

        if ($this->option('status')) {
            return $this->printStatus();
        }

        $only = array_map(static fn ($c) => strtolower(trim((string) $c)), (array) $this->option('channel'));
        $channels = $only !== [] ? array_values(array_intersect(self::CHANNELS, $only)) : self::CHANNELS;
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $totals = ['skus' => 0, 'fixed' => 0, 'still' => 0];
        $summary = [];

        foreach ($channels as $channel) {
            try {
                $row = $this->pushChannel($pass, $match, $channel, $chunk, $dryRun);
            } catch (\Throwable $e) {
                $row = ['channel' => $channel, 'skus' => 0, 'fixed' => 0, 'still' => [], 'note' => 'Error: '.$e->getMessage()];
                Log::error('inventory:push-missing-mapping channel failed', ['channel' => $channel, 'error' => $e->getMessage()]);
            }
            $summary[] = $row;
            if (! $dryRun) {
                Cache::put(self::LAST_RUN_CACHE_PREFIX.$channel, [
                    'at' => now()->toDateTimeString(),
                    'skus' => $row['skus'],
                    'fixed' => $row['fixed'],
                    'still' => count($row['still']),
                    'still_sample' => array_slice($row['still'], 0, 10),
                    'note' => $row['note'],
                ], now()->addDays(14));
            }
            $totals['skus'] += $row['skus'];
            $totals['fixed'] += $row['fixed'];
            $totals['still'] += count($row['still']);

            $line = sprintf('%-16s %4d mismatched, %4d fixed, %4d still off', $channel, $row['skus'], $row['fixed'], count($row['still']));
            if ($row['note'] !== '') {
                $line .= ' — '.$row['note'];
            }
            $this->line($line);
            if ($row['still'] !== []) {
                $this->line('    still off: '.implode(', ', array_slice($row['still'], 0, 30)).(count($row['still']) > 30 ? ' …' : ''));
            }
        }

        $this->info(sprintf('Done: %d mismatched SKU(s), %d fixed, %d still off.', $totals['skus'], $totals['fixed'], $totals['still']));
        Log::info('inventory:push-missing-mapping finished', ['totals' => $totals, 'channels' => $summary, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }

    /**
     * @return array{channel: string, skus: int, fixed: int, still: list<string>, note: string}
     */
    private function pushChannel(
        MarketplaceMismatchInventoryPass $pass,
        MarketplaceListingQtyMatchService $match,
        string $channel,
        int $chunk,
        bool $dryRun
    ): array {
        $row = ['channel' => $channel, 'skus' => 0, 'fixed' => 0, 'still' => [], 'note' => ''];
        if (! MarketplaceMismatchInventoryPass::syncEnabled($channel)) {
            $row['note'] = 'skipped: inventory and price sync are both off in this channel\'s settings';

            return $row;
        }

        Cache::forget(MarketplaceListingQtyMatchService::CACHE_PREFIX.$channel);
        $skus = $pass->pageMismatchSkus($channel);
        $row['skus'] = count($skus);
        if ($skus === []) {
            return $row;
        }
        if ($dryRun) {
            $row['still'] = $skus;
            $row['note'] = 'dry run';

            return $row;
        }

        $errors = [];
        $this->pushInChunks($pass, $channel, $skus, $chunk, $errors);
        $still = $match->stillMismatched($channel, $skus);

        if ($still !== []) {
            sleep(5);
            $this->pushInChunks($pass, $channel, $still, $chunk, $errors);
            $still = $match->stillMismatched($channel, $still);
        }

        $row['still'] = array_values($still);
        $row['fixed'] = $row['skus'] - count($still);
        if ($still !== [] && $errors !== []) {
            $row['note'] = 'push errors: '.mb_substr(implode(' | ', array_slice(array_unique($errors), 0, 3)), 0, 500);
        }

        return $row;
    }

    /**
     * @param  list<string>  $skus
     * @param  list<string>  $errors
     */
    private function pushInChunks(MarketplaceMismatchInventoryPass $pass, string $channel, array $skus, int $chunk, array &$errors): void
    {
        foreach (array_chunk($skus, $chunk) as $batch) {
            try {
                $result = $pass->pushSkus($channel, $batch);
                if (! empty($result['rate_limited'])) {
                    sleep(30);
                }
                if ((int) ($result['failed'] ?? 0) > 0 && trim((string) ($result['message'] ?? '')) !== '') {
                    $errors[] = trim((string) $result['message']);
                }
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                Log::warning('inventory:push-missing-mapping batch failed', [
                    'channel' => $channel,
                    'skus' => $batch,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function printStatus(): int
    {
        $rows = [];
        foreach (self::CHANNELS as $channel) {
            $last = Cache::get(self::LAST_RUN_CACHE_PREFIX.$channel);
            $rows[] = is_array($last)
                ? [$channel, $last['at'], $last['skus'], $last['fixed'], $last['still'], mb_substr((string) $last['note'], 0, 90)]
                : [$channel, 'never (or not since this version)', '', '', '', MarketplaceMismatchInventoryPass::syncEnabled($channel) ? '' : 'sync off in channel settings'];
        }
        $this->table(['channel', 'last run', 'mismatched', 'fixed', 'still off', 'note'], $rows);

        return self::SUCCESS;
    }
}
