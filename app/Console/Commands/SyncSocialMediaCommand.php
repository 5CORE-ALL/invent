<?php

namespace App\Console\Commands;

use App\Jobs\SyncSocialMediaAccount;
use App\Models\SocialMediaAccount;
use App\SocialMedia\SocialMediaSyncService;
use Illuminate\Console\Command;

class SyncSocialMediaCommand extends Command
{
    protected $signature = 'social-media:sync {--account=}';

    protected $description = 'Queue incremental social media synchronization and refresh token health';

    public function handle(SocialMediaSyncService $sync): int
    {
        $expired = $sync->markExpiredTokens();
        $this->info('Token health updated for '.$expired.' account(s).');

        $accounts = SocialMediaAccount::query()
            ->where('sync_enabled', true)
            ->where('status', '!=', 'disconnected')
            ->when($this->option('account'), fn ($query) => $query->whereKey($this->option('account')))
            ->orderBy('id')
            ->get();

        $queued = 0;
        foreach ($accounts as $account) {
            if ($account->tokenStatus() === 'expired') {
                $this->warn($account->account_name.' token expired — reconnect before syncing.');
                continue;
            }
            SyncSocialMediaAccount::dispatch($account->id);
            $queued++;
        }

        $this->info('Queued '.$queued.' account sync job(s) on '.config('social_media.sync_queue').'.');

        return self::SUCCESS;
    }
}
