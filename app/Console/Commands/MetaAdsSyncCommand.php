<?php

namespace App\Console\Commands;

use App\Jobs\SyncMetaAdAccountsJob;
use App\Jobs\SyncMetaCampaignsJob;
use App\Jobs\SyncMetaAdSetsJob;
use App\Jobs\SyncMetaAdsJob;
use App\Jobs\SyncMetaInsightsDailyJob;
use App\Models\MetaSyncRun;
use App\Models\MetaAdAccount;
use App\Models\MetaCampaign;
use App\Models\MetaAdSet;
use App\Models\MetaAd;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MetaAdsSyncCommand extends Command
{
    protected $signature = 'meta-ads:sync 
                            {--user-id= : User ID to sync for}
                            {--ad-account= : Specific ad account Meta ID to sync}
                            {--from= : Start date for insights (YYYY-MM-DD)}
                            {--to= : End date for insights (YYYY-MM-DD)}
                            {--insights-only : Only sync insights, skip entities}';

    protected $description = 'Sync Meta Ads data (accounts, campaigns, adsets, ads, insights)';

    public function handle()
    {
        $userId = $this->option('user-id') ? (int) $this->option('user-id') : null;
        $adAccountMetaId = $this->option('ad-account');
        // Default to last 1 day for daily graph data
        $dateStart = $this->option('from') ?? Carbon::yesterday()->format('Y-m-d');
        $dateEnd = $this->option('to') ?? Carbon::yesterday()->format('Y-m-d');
        $insightsOnly = $this->option('insights-only');

        $this->info('Starting Meta Ads sync...');
        $this->line("User ID: " . ($userId ?? 'All'));
        $this->line("Ad Account: " . ($adAccountMetaId ?? 'All'));
        $this->line("Date Range: {$dateStart} to {$dateEnd}");

        // Create sync run record
        $syncRun = MetaSyncRun::create([
            'user_id' => $userId,
            'ad_account_meta_id' => $adAccountMetaId,
            'sync_type' => $insightsOnly ? 'insights_only' : 'full',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            if (!$insightsOnly) {
                // Step 1: Sync Ad Accounts
                $this->info('Syncing ad accounts...');
                SyncMetaAdAccountsJob::dispatch($userId);
                $this->line('✓ Ad accounts queued');

                // Step 2: Sync Campaigns (for each ad account)
                $this->info('Syncing campaigns...');
                if ($adAccountMetaId) {
                    SyncMetaCampaignsJob::dispatch($userId, $adAccountMetaId);
                } else {
                    // Get all ad accounts and sync campaigns for each
                    $adAccounts = MetaAdAccount::when($userId, fn($q) => $q->where('user_id', $userId))->get();
                    foreach ($adAccounts as $account) {
                        SyncMetaCampaignsJob::dispatch($userId, $account->meta_id);
                    }
                }
                $this->line('✓ Campaigns queued (' . ($adAccountMetaId ? 1 : $adAccounts->count()) . ' jobs)');

                // Step 3: Sync AdSets (for each campaign)
                // Note: We dispatch ad sets jobs based on existing campaigns in DB
                // If campaigns haven't been synced yet, run campaigns sync first, then run this command again
                $this->info('Syncing ad sets...');
                $campaigns = MetaCampaign::when($userId, fn($q) => $q->where('user_id', $userId))
                    ->when($adAccountMetaId, function ($q) use ($adAccountMetaId) {
                        $account = MetaAdAccount::where('meta_id', $adAccountMetaId)->first();
                        if ($account) {
                            $q->where('ad_account_id', $account->id);
                        }
                    })
                    ->get();

                if ($campaigns->isEmpty()) {
                    $this->warn('⚠ No campaigns found in database. Please process campaigns jobs first, then run sync again for ad sets.');
                } else {
                    foreach ($campaigns as $campaign) {
                        SyncMetaAdSetsJob::dispatch($userId, $campaign->meta_id);
                    }
                    $this->line('✓ Ad sets queued (' . $campaigns->count() . ' jobs)');
                }

                // Step 4: Sync Ads (for each ad set)
                $this->info('Syncing ads...');
                $adsets = MetaAdSet::when($userId, fn($q) => $q->where('user_id', $userId))
                    ->when($adAccountMetaId, function ($q) use ($adAccountMetaId) {
                        $account = MetaAdAccount::where('meta_id', $adAccountMetaId)->first();
                        if ($account) {
                            $q->where('ad_account_id', $account->id);
                        }
                    })
                    ->get();

                foreach ($adsets as $adset) {
                    SyncMetaAdsJob::dispatch($userId, $adset->meta_id);
                }
                $this->line('✓ Ads queued');
            }

            // Step 5: Sync Insights (for all entities)
            $this->info('Syncing insights...');
            
            // Sync campaign insights
            $campaigns = MetaCampaign::when($userId, fn($q) => $q->where('user_id', $userId))
                ->when($adAccountMetaId, function ($q) use ($adAccountMetaId) {
                    $account = MetaAdAccount::where('meta_id', $adAccountMetaId)->first();
                    if ($account) {
                        $q->where('ad_account_id', $account->id);
                    }
                })
                ->get();

            foreach ($campaigns as $campaign) {
                SyncMetaInsightsDailyJob::dispatch($userId, 'campaign', $campaign->meta_id, $dateStart, $dateEnd);
            }

            // Sync adset insights
            $adsets = MetaAdSet::when($userId, fn($q) => $q->where('user_id', $userId))
                ->when($adAccountMetaId, function ($q) use ($adAccountMetaId) {
                    $account = MetaAdAccount::where('meta_id', $adAccountMetaId)->first();
                    if ($account) {
                        $q->where('ad_account_id', $account->id);
                    }
                })
                ->get();

            foreach ($adsets as $adset) {
                SyncMetaInsightsDailyJob::dispatch($userId, 'adset', $adset->meta_id, $dateStart, $dateEnd);
            }

            // Sync ad insights
            $ads = MetaAd::when($userId, fn($q) => $q->where('user_id', $userId))
                ->when($adAccountMetaId, function ($q) use ($adAccountMetaId) {
                    $account = MetaAdAccount::where('meta_id', $adAccountMetaId)->first();
                    if ($account) {
                        $q->where('ad_account_id', $account->id);
                    }
                })
                ->get();

            foreach ($ads as $ad) {
                SyncMetaInsightsDailyJob::dispatch($userId, 'ad', $ad->meta_id, $dateStart, $dateEnd);
            }

            $this->line('✓ Insights queued');

            // Update sync run
            $syncRun->update([
                'status' => 'completed',
                'finished_at' => now(),
                'accounts_synced' => MetaAdAccount::when($userId, fn($q) => $q->where('user_id', $userId))->count(),
                'campaigns_synced' => MetaCampaign::when($userId, fn($q) => $q->where('user_id', $userId))->count(),
                'adsets_synced' => MetaAdSet::when($userId, fn($q) => $q->where('user_id', $userId))->count(),
                'ads_synced' => MetaAd::when($userId, fn($q) => $q->where('user_id', $userId))->count(),
            ]);

            $this->info('✓ Sync completed! Jobs have been queued.');
            $this->line('Note: Actual sync happens in background. Check queue worker logs for progress.');

        } catch (\Exception $e) {
            $syncRun->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_summary' => $e->getMessage(),
            ]);

            $this->error('✗ Sync failed: ' . $e->getMessage());
            Log::error('MetaAdsSyncCommand: Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }

        return 0;
    }
}
