<?php

namespace App\Console\Commands;

use App\Models\MetaAdSet;
use App\Jobs\SyncMetaAdsJob;
use Illuminate\Console\Command;

class MetaAdsSyncAdsCommand extends Command
{
    protected $signature = 'meta-ads:sync-ads 
                            {--user-id= : User ID to sync for}
                            {--ad-account-id= : Specific ad account database ID}
                            {--adset-id= : Specific adset database ID}';

    protected $description = 'Sync Meta Ads for all ad sets';

    public function handle()
    {
        $userId = $this->option('user-id') ? (int) $this->option('user-id') : null;
        $adAccountId = $this->option('ad-account-id');
        $adsetId = $this->option('adset-id');

        $this->info('Starting Meta Ads sync...');

        $query = MetaAdSet::query();
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        if ($adAccountId) {
            $query->where('ad_account_id', $adAccountId);
        }
        
        if ($adsetId) {
            $query->where('id', $adsetId);
        }

        $adsets = $query->get();
        
        if ($adsets->isEmpty()) {
            $this->warn('No ad sets found to sync ads for.');
            return 1;
        }

        // Show which accounts are being processed
        $accountsUsed = $adsets->groupBy('ad_account_id')->map(function ($group) {
            $account = $group->first()->adAccount ?? null;
            return [
                'account_id' => $group->first()->ad_account_id,
                'account_name' => $account ? $account->name : 'Unknown',
                'account_meta_id' => $account ? $account->meta_id : 'N/A',
                'adsets_count' => $group->count(),
            ];
        });

        $this->info("Found {$adsets->count()} ad set(s) to process...");
        $this->line('');
        $this->info('Accounts being processed:');
        foreach ($accountsUsed as $info) {
            $this->line("  - Account: {$info['account_name']} (DB ID: {$info['account_id']}, Meta ID: {$info['account_meta_id']}) - {$info['adsets_count']} adsets");
        }
        $this->line('');

        $queued = 0;

        foreach ($adsets as $adset) {
            if (empty($adset->meta_id)) {
                $this->warn("Skipping adset '{$adset->name}' - no meta_id");
                continue;
            }

            SyncMetaAdsJob::dispatch($userId ?? $adset->user_id, $adset->meta_id);
            $queued++;
        }

        $this->info("✓ Queued {$queued} ad sync jobs");
        $this->line('Note: Jobs will be processed by queue worker. Run: php artisan queue:work');

        return 0;
    }
}

