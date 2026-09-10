<?php

namespace App\Console\Commands;

use App\Services\EbayRuleSpriceApplyService;
use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EbayRuleSpriceApplyCommand extends Command
{
    protected $signature = 'ebay:rule-sprice-apply
        {channel=all : ebay1, ebay2, ebay3, or all}
        {--dry-run : Compute Dil S PRC but do not write data_view}
        {--limit= : Max SKUs to save per channel (for testing)}
        {--push : After save, queue S PRC → live listing price}';

    protected $description = 'eBay 1/2/3: apply Sprc Dil (listing Dil + CVR + LMP cap) and save S PRC. Page not required.';

    public const LOCK_CACHE_KEY = 'ebay-rule-sprice-apply';

    public function handle(ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $channels = EbayRuleSpriceApplyService::channelsFromArg((string) $this->argument('channel'));
        if ($channels === []) {
            $this->error('Unsupported channel. Use ebay1, ebay2, ebay3, or all.');

            return self::FAILURE;
        }

        $lock = Cache::lock(self::LOCK_CACHE_KEY, 10800);
        if (! $lock->get()) {
            $this->warn('Already running — skip');

            return self::SUCCESS;
        }

        try {
            return $this->runChannels($channels, $enqueue);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<string>  $channels
     */
    private function runChannels(array $channels, ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $push = (bool) $this->option('push');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;

        $this->info('eBay Sprc Dil apply ['.implode(', ', $channels).']'.($dryRun ? ' [DRY RUN]' : '').($push ? ' + push' : ''));

        $anyFail = false;
        $anyApplied = false;

        foreach ($channels as $channel) {
            try {
                $summary = EbayRuleSpriceApplyService::for($channel)->run(
                    dryRun: $dryRun,
                    limit: $limit,
                    onlySkus: null,
                    logger: fn (string $msg) => $this->line($msg)
                );
            } catch (Throwable $e) {
                $anyFail = true;
                $this->error($channel.': '.$e->getMessage());
                $this->error($e->getFile().':'.$e->getLine());
                continue;
            }

            $stats = $summary['stats'] ?? [];
            $applied = (int) ($stats['applied'] ?? 0);
            $failed = count($stats['errors'] ?? []);
            if ($applied > 0) {
                $anyApplied = true;
            }
            if ($failed > 0) {
                $anyFail = true;
            }

            foreach (array_slice($stats['errors'] ?? [], 0, 15) as $err) {
                $this->warn('  · '.$err);
            }

            $this->info(sprintf(
                '%s saved. candidates=%d applied=%d unchanged=%d skipped=%d errors=%d mismatch=%d',
                strtoupper($channel),
                (int) ($stats['candidates'] ?? 0),
                $applied,
                (int) ($stats['skipped_unchanged'] ?? 0),
                (int) ($stats['skipped'] ?? 0),
                $failed,
                count($summary['push_tasks'] ?? [])
            ));
        }

        if ($push && ! $dryRun) {
            foreach ($channels as $channel) {
                $res = $enqueue->enqueueChannel($channel);
                $this->info(strtoupper($channel).' push: '.$res['message'].(($res['spawned'] ?? false) ? ' — worker started' : ''));
            }
        }

        return ($anyFail && ! $anyApplied && ! $dryRun)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
