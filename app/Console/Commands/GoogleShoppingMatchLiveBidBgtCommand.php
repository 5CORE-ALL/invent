<?php

namespace App\Console\Commands;

use App\Services\GoogleShoppingLiveMatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GoogleShoppingMatchLiveBidBgtCommand extends Command
{
    protected $signature = 'google-shopping:match-live-bid-bgt
        {--campaign-id= : Only this campaign ID}
        {--retry-failed : Also push campaigns whose last push did not stick (otherwise they wait 3 hours)}
        {--dry-run : Pull and report the mismatches, push nothing}';

    protected $description = 'Google Shopping: pull live bid/budget, push SBID/SBGT to every ENABLED campaign that differs, pull again to confirm. No page needed.';

    public function handle(GoogleShoppingLiveMatchService $match): int
    {
        $lock = Cache::lock('google-shopping:match-live-bid-bgt', 1800);
        if (! $lock->get()) {
            $this->warn('Another Google Shopping live match is already running.');

            return self::SUCCESS;
        }

        try {
            @ini_set('memory_limit', '512M');
            $campaign = $this->option('campaign-id');
            $stats = $match->run(
                (bool) $this->option('dry-run'),
                $campaign !== null && $campaign !== '' ? (string) $campaign : null,
                (bool) $this->option('retry-failed')
            );
        } finally {
            $lock->release();
        }

        $this->info('Enabled shopping campaigns checked: '.$stats['checked']);
        $this->info('SBID differs from live: '.$stats['bid_mismatch'].' | SBGT differs from live: '.$stats['bgt_mismatch']);
        if (! $this->option('dry-run')) {
            $this->info('After push: SBID matched '.$stats['bid_pushed'].', still different '.$stats['bid_left']
                .' | SBGT matched '.$stats['bgt_pushed'].', still different '.$stats['bgt_left']);
        }
        if ($stats['waiting'] > 0) {
            $this->info('Waiting before the next try (last push did not stick): '.$stats['waiting'].' — use --retry-failed to push them now.');
        }
        foreach ($stats['push_errors'] as $err) {
            $this->warn($err);
        }
        if ($stats['error'] !== null) {
            $this->error($stats['error']);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
