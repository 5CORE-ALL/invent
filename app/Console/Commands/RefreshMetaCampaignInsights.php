<?php

namespace App\Console\Commands;

use App\Models\MetaAdAccount;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Re-pulls the last N days of CAMPAIGN daily insights from the Meta API and
 * overwrites the stored numbers.
 *
 * `meta-ads:sync` only fetches *yesterday* each night, but Meta keeps revising
 * a day's impressions / clicks / spend for up to ~28 days (late attribution,
 * invalid-traffic removal). Without this refresh every day stays frozen at its
 * first-seen value and /facebook-all-ads-sheet drifts away from Ads Manager.
 *
 * One account-level request (level=campaign, time_increment=1) covers every
 * campaign, so this is a handful of API calls instead of one job per campaign.
 */
class RefreshMetaCampaignInsights extends Command
{
    protected $signature = 'meta-ads:refresh-insights
                            {--days=30 : How many days back (ending yesterday) to re-pull}
                            {--ad-account= : Meta ad account id (default: services.meta.sheet_ad_account_id)}';

    protected $description = 'Re-pull the last N days of campaign insights from Meta (corrects late data revisions)';

    public function handle(): int
    {
        $acct = trim((string) ($this->option('ad-account') ?: config('services.meta.sheet_ad_account_id')));
        if ($acct === '') {
            $this->error('No ad account given and services.meta.sheet_ad_account_id is empty.');

            return 1;
        }
        if (! str_starts_with($acct, 'act_')) {
            $acct = 'act_' . $acct;
        }

        $account = MetaAdAccount::where('meta_id', $acct)->first();
        if (! $account) {
            $this->error("Ad account {$acct} is not in meta_ad_accounts.");

            return 1;
        }

        $days  = max(1, min(90, (int) $this->option('days')));
        $until = Carbon::yesterday()->toDateString();
        $since = Carbon::yesterday()->subDays($days - 1)->toDateString();
        $this->info("Refreshing campaign insights for {$acct}: {$since} to {$until}");

        $baseUrl = config('services.meta.base_url', 'https://graph.facebook.com/v21.0');
        $token   = config('services.meta.access_token');

        // Meta campaign id => local meta_campaigns ids (the table can hold copies).
        $localIds = [];
        foreach (DB::table('meta_campaigns')->where('ad_account_id', $account->id)->orderByDesc('synced_at')->get(['id', 'meta_id']) as $c) {
            $localIds[(string) $c->meta_id][] = (int) $c->id;
        }

        $updated = 0;
        $created = 0;
        $skipped = 0;
        $after   = null;
        $pages   = 0;
        $emptyHash = md5(json_encode([]));

        do {
            $params = [
                'access_token'   => $token,
                'level'          => 'campaign',
                'time_increment' => 1,
                'time_range'     => json_encode(['since' => $since, 'until' => $until]),
                'fields'         => 'campaign_id,date_start,impressions,clicks,reach,spend,ctr,cpc,cpm,cpp,frequency',
                'limit'          => 500,
            ];
            if ($after) {
                $params['after'] = $after;
            }

            $response = Http::timeout(120)->get("{$baseUrl}/{$acct}/insights", $params);
            if (! $response->successful()) {
                $msg = $response->json('error.message') ?? $response->body();
                Log::error('meta-ads:refresh-insights failed', ['body' => $response->body()]);
                $this->error("Meta API error: {$msg}");

                return 1;
            }

            foreach ($response->json('data') ?? [] as $row) {
                $ids = $localIds[(string) ($row['campaign_id'] ?? '')] ?? null;
                if (! $ids || empty($row['date_start'])) {
                    $skipped++; // campaign not stored locally (e.g. archived & never synced)
                    continue;
                }

                $values = [
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'clicks'      => (int) ($row['clicks'] ?? 0),
                    'reach'       => (int) ($row['reach'] ?? 0),
                    'spend'       => (float) ($row['spend'] ?? 0),
                    'ctr'         => (float) ($row['ctr'] ?? 0),
                    'cpc'         => (float) ($row['cpc'] ?? 0),
                    'cpm'         => (float) ($row['cpm'] ?? 0),
                    'cpp'         => (float) ($row['cpp'] ?? 0),
                    'frequency'   => (float) ($row['frequency'] ?? 0),
                    'synced_at'   => now(),
                    'updated_at'  => now(),
                ];

                $n = DB::table('meta_insights_daily')
                    ->where('entity_type', 'campaign')
                    ->whereIn('entity_id', $ids)
                    ->where('date_start', $row['date_start'])
                    ->update($values);

                if ($n > 0) {
                    $updated += $n;
                    continue;
                }

                DB::table('meta_insights_daily')->insert($values + [
                    'user_id'        => null,
                    'entity_type'    => 'campaign',
                    'entity_id'      => $ids[0],
                    'date_start'     => $row['date_start'],
                    'breakdown_hash' => $emptyHash,
                    'created_at'     => now(),
                ]);
                $created++;
            }

            $after = $response->json('paging.next') ? $response->json('paging.cursors.after') : null;
        } while ($after && ++$pages < 50);

        $this->info("Done: {$updated} rows updated, {$created} created, {$skipped} skipped (campaign not stored).");
        Log::info('meta-ads:refresh-insights done', compact('since', 'until', 'updated', 'created', 'skipped'));

        return 0;
    }
}
