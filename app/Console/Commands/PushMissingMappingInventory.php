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
        'macy', 'bestbuy', 'temu', 'temu2', 'pls', 'wayfair', 'faire', 'doba', 'alibaba',
    ];

    protected $signature = 'inventory:push-missing-mapping
        {--channel=* : Only these channels (e.g. --channel=tiktok2 --channel=newegg)}
        {--chunk=25 : SKUs per push call}
        {--dry-run : List the SKUs that would be pushed}';

    protected $description = 'Push Shopify qty for every Missing Mapping (/map-issues) SKU, then retry the ones still mismatched';

    public function handle(MarketplaceMismatchInventoryPass $pass, MarketplaceListingQtyMatchService $match): int
    {
        @set_time_limit(0);
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

        $this->pushInChunks($pass, $channel, $skus, $chunk);
        $still = $match->stillMismatched($channel, $skus);

        if ($still !== []) {
            sleep(5);
            $this->pushInChunks($pass, $channel, $still, $chunk);
            $still = $match->stillMismatched($channel, $still);
        }

        $row['still'] = array_values($still);
        $row['fixed'] = $row['skus'] - count($still);

        return $row;
    }

    /**
     * @param  list<string>  $skus
     */
    private function pushInChunks(MarketplaceMismatchInventoryPass $pass, string $channel, array $skus, int $chunk): void
    {
        foreach (array_chunk($skus, $chunk) as $batch) {
            try {
                $result = $pass->pushSkus($channel, $batch);
                if (! empty($result['rate_limited'])) {
                    sleep(30);
                }
            } catch (\Throwable $e) {
                Log::warning('inventory:push-missing-mapping batch failed', [
                    'channel' => $channel,
                    'skus' => $batch,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
