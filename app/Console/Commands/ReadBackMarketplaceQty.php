<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Services\MarketplaceManager\MarketplaceMismatchInventoryPass;
use App\Services\MarketplaceManager\MarketplaceQtyReadBack;
use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sweep every linked SKU of a channel and store the qty the marketplace really has.
 *
 * The push-time read-back only covers SKUs already flagged as mismatched. A SKU the app
 * believes is matched (because the push target was written locally) is never re-read, so
 * Wayfair / TopDawg / PLS showed "0 mismatched" while the marketplace disagreed. This sweep
 * walks the whole linked list in slices (rotating cursor per channel) and records the live
 * qty; the 15-minute mismatch pass and the hourly push then fix whatever surfaces.
 */
class ReadBackMarketplaceQty extends Command
{
    protected $signature = 'mm:readback-marketplace-qty
        {--channel=* : Only these channels (default: every channel with a marketplace read)}
        {--all : Ignore the rotating cursor and read every linked SKU in one run}
        {--status : Show the last sweep per channel and exit}';

    protected $description = 'Read the live marketplace qty for linked SKUs (rotating slice) so hidden mismatches surface on /map-issues';

    public const LAST_RUN_CACHE_PREFIX = 'mm_readback_sweep_last:';

    private const CURSOR_CACHE_PREFIX = 'mm_readback_sweep_cursor:';

    public function handle(MarketplaceMismatchInventoryPass $pass, MarketplaceListingQtyMatchService $match, MarketplaceQtyReadBack $readBack): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '2048M');

        if ($this->option('status')) {
            return $this->printStatus();
        }

        $only = array_map(static fn ($c) => strtolower(trim((string) $c)), (array) $this->option('channel'));
        $channels = $only !== []
            ? array_values(array_intersect(MarketplaceQtyReadBack::SUPPORTED, $only))
            : MarketplaceQtyReadBack::SUPPORTED;
        $all = (bool) $this->option('all');

        foreach ($channels as $channel) {
            try {
                $row = $this->sweepChannel($pass, $match, $readBack, $channel, $all);
            } catch (\Throwable $e) {
                $row = ['channel' => $channel, 'linked' => 0, 'requested' => 0, 'read' => 0, 'mismatched' => [], 'cursor' => 0, 'note' => 'Error: '.$e->getMessage()];
                Log::error('mm:readback-marketplace-qty channel failed', ['channel' => $channel, 'error' => $e->getMessage()]);
            }
            Cache::put(self::LAST_RUN_CACHE_PREFIX.$channel, [
                'at' => now()->toDateTimeString(),
                'linked' => $row['linked'],
                'requested' => $row['requested'],
                'read' => $row['read'],
                'mismatched' => count($row['mismatched']),
                'mismatched_sample' => array_slice($row['mismatched'], 0, 10),
                'cursor' => $row['cursor'],
                'note' => $row['note'],
            ], now()->addDays(14));

            $line = sprintf(
                '%-12s linked %4d, asked %4d, marketplace answered %4d, now mismatched %3d, next slice starts at %d',
                $channel,
                $row['linked'],
                $row['requested'],
                $row['read'],
                count($row['mismatched']),
                $row['cursor']
            );
            if ($row['note'] !== '') {
                $line .= ' — '.$row['note'];
            }
            $this->line($line);
            if ($row['mismatched'] !== []) {
                $this->line('    mismatched: '.implode(', ', array_slice($row['mismatched'], 0, 30)).(count($row['mismatched']) > 30 ? ' …' : ''));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{channel: string, linked: int, requested: int, read: int, mismatched: list<string>, cursor: int, note: string}
     */
    private function sweepChannel(
        MarketplaceMismatchInventoryPass $pass,
        MarketplaceListingQtyMatchService $match,
        MarketplaceQtyReadBack $readBack,
        string $channel,
        bool $all
    ): array {
        $row = ['channel' => $channel, 'linked' => 0, 'requested' => 0, 'read' => 0, 'mismatched' => [], 'cursor' => 0, 'note' => ''];

        $linked = $pass->linkedSkus($channel);
        $total = count($linked);
        $row['linked'] = $total;
        if ($total === 0) {
            $row['note'] = 'no linked SKUs';

            return $row;
        }

        $cursorKey = self::CURSOR_CACHE_PREFIX.$channel;
        $slice = MarketplaceQtyReadBack::sweepSliceSize($channel);
        $offset = $all ? 0 : (int) Cache::get($cursorKey, 0);
        if ($offset < 0 || $offset >= $total) {
            $offset = 0;
        }
        $take = $all ? $total : min($slice, $total);
        $skus = array_slice($linked, $offset, $take);
        $next = ($offset + count($skus) >= $total) ? 0 : $offset + count($skus);
        $row['requested'] = count($skus);

        $read = $readBack->record($channel, $skus);
        $row['read'] = count($read);
        if ($read === []) {
            $row['note'] = 'marketplace returned no qty for this slice (API off, not listed, or read unsupported for these SKUs)';
            Cache::put($cursorKey, $next, now()->addDays(7));
            $row['cursor'] = $next;

            return $row;
        }

        $row['mismatched'] = array_values($match->stillMismatched($channel, array_keys($read)));
        MappingChannelCounts::markChannelStale($channel);

        Cache::put($cursorKey, $next, now()->addDays(7));
        $row['cursor'] = $next;
        if ($next === 0 && $total > count($skus)) {
            $row['note'] = 'full cycle complete';
        }

        return $row;
    }

    private function printStatus(): int
    {
        $rows = [];
        foreach (MarketplaceQtyReadBack::SUPPORTED as $channel) {
            $last = Cache::get(self::LAST_RUN_CACHE_PREFIX.$channel);
            $rows[] = is_array($last)
                ? [$channel, $last['at'], $last['linked'], $last['requested'], $last['read'], $last['mismatched'], $last['cursor'], mb_substr((string) $last['note'], 0, 80)]
                : [$channel, 'never (or not since this version)', '', '', '', '', (int) Cache::get(self::CURSOR_CACHE_PREFIX.$channel, 0), ''];
        }
        $this->table(['channel', 'last sweep', 'linked', 'asked', 'answered', 'mismatched', 'next slice', 'note'], $rows);

        return self::SUCCESS;
    }
}
