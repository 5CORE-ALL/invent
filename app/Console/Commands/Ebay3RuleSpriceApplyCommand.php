<?php

namespace App\Console\Commands;

use App\Services\Ebay3RuleSpriceApplyService;
use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class Ebay3RuleSpriceApplyCommand extends Command
{
    protected $signature = 'ebay3:rule-sprice-apply
        {--dry-run : Compute Dil S PRC but do not write ebay3_data_view}
        {--limit= : Max SKUs to save (for testing)}
        {--push : After save, queue S PRC → live eBay 3 price}';

    protected $description = 'eBay 3: apply Sprc Dil (listing Dil + CVR + LMP cap) and save S PRC. Page not required.';

    public const LOCK_CACHE_KEY = 'ebay3-rule-sprice-apply';

    public function handle(Ebay3RuleSpriceApplyService $service, ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $lock = Cache::lock(self::LOCK_CACHE_KEY, 7200);
        if (! $lock->get()) {
            $this->warn('Already running — skip');

            return self::SUCCESS;
        }

        try {
            return $this->runApply($service, $enqueue);
        } finally {
            $lock->release();
        }
    }

    private function runApply(Ebay3RuleSpriceApplyService $service, ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $push = (bool) $this->option('push');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;

        $this->info('eBay 3 Sprc Dil apply'.($dryRun ? ' [DRY RUN]' : '').($push ? ' + push' : ''));

        try {
            $summary = $service->run(
                dryRun: $dryRun,
                limit: $limit,
                onlySkus: null,
                logger: fn (string $msg) => $this->line($msg)
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->error($e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }

        $stats = $summary['stats'] ?? [];
        $candidates = (int) ($stats['candidates'] ?? 0);
        $applied = (int) ($stats['applied'] ?? 0);
        $unchanged = (int) ($stats['skipped_unchanged'] ?? 0);
        $skipped = (int) ($stats['skipped'] ?? 0);
        $failed = count($stats['errors'] ?? []);
        $pushTasks = $summary['push_tasks'] ?? [];

        foreach (array_slice($stats['errors'] ?? [], 0, 15) as $err) {
            $this->warn('  · '.$err);
        }

        $this->info(sprintf(
            'Saved. candidates=%d applied=%d unchanged=%d skipped=%d errors=%d mismatch=%d',
            $candidates,
            $applied,
            $unchanged,
            $skipped,
            $failed,
            count($pushTasks)
        ));

        if ($push && ! $dryRun && $failed === 0) {
            $res = $enqueue->enqueueChannel('ebay3');
            $this->info('Push: '.$res['message'].(($res['spawned'] ?? false) ? ' — worker started' : ''));
        }

        return ($failed > 0 && $applied === 0 && ! $dryRun)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
