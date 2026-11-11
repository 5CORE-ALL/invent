<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pull LIFETIME spend / sales / purchases / impressions / clicks for every
 * campaign straight from Meta (insights, level=campaign, date_preset=maximum)
 * — one paginated call per ad account, no per-campaign queue jobs — and store
 * them in facebook_campaign_lifetime_stats for the Lifetime Acos / Lifetime
 * Sales columns on /facebook-all-ads-sheet.
 *
 * Sales = Meta's `purchase` action value, purchases = `purchase` action count
 * (same definition SyncMetaInsightsDailyJob uses for meta_insights_daily).
 */
class SyncFacebookLifetimeStats extends Command
{
    protected $signature = 'facebook:sync-lifetime-stats
                            {--account= : Only this ad account Meta ID (e.g. act_123)}';

    protected $description = 'Sync lifetime (all-time) per-campaign totals from Meta for the Facebook all-ads sheet';

    public function handle(): int
    {
        if (! Schema::hasTable('facebook_campaign_lifetime_stats') || ! Schema::hasTable('meta_ad_accounts')) {
            $this->error('Missing table(s). Run: php artisan migrate');
            return 1;
        }

        $token = (string) config('services.meta.access_token');
        if ($token === '') {
            $this->error('META_ACCESS_TOKEN is not set.');
            return 1;
        }
        $version = (string) (config('services.meta.api_version') ?: 'v21.0');
        $base    = 'https://graph.facebook.com/' . $version;

        $accounts = DB::table('meta_ad_accounts')
            ->when($this->option('account'), fn ($q, $a) => $q->where('meta_id', $a))
            ->pluck('meta_id')
            ->filter()
            ->unique()
            ->values();

        if ($accounts->isEmpty()) {
            $this->warn('No ad accounts found in meta_ad_accounts.');
            return 0;
        }

        $saved = 0;
        $failedAccounts = 0;
        $now = now();

        foreach ($accounts as $acct) {
            $url = "{$base}/{$acct}/insights";
            $params = [
                'access_token' => $token,
                'level'        => 'campaign',
                'date_preset'  => 'maximum',
                'fields'       => 'campaign_id,spend,impressions,clicks,actions,action_values,date_start,date_stop',
                'limit'        => 500,
            ];
            $count = 0;
            $pages = 0;

            do {
                $resp = Http::timeout(120)->get($url, $params);
                if (! $resp->ok()) {
                    $msg = data_get($resp->json(), 'error.message', 'HTTP ' . $resp->status());
                    $this->error("{$acct}: {$msg}");
                    Log::error('facebook:sync-lifetime-stats failed', ['account' => $acct, 'error' => $msg]);
                    $failedAccounts++;
                    break;
                }
                $body = $resp->json();

                foreach (($body['data'] ?? []) as $row) {
                    $cid = (string) ($row['campaign_id'] ?? '');
                    if ($cid === '') {
                        continue;
                    }
                    $sales = 0.0;
                    foreach (($row['action_values'] ?? []) as $a) {
                        if (($a['action_type'] ?? '') === 'purchase') {
                            $sales = (float) ($a['value'] ?? 0);
                        }
                    }
                    $sold = 0;
                    foreach (($row['actions'] ?? []) as $a) {
                        if (($a['action_type'] ?? '') === 'purchase') {
                            $sold = (int) ($a['value'] ?? 0);
                        }
                    }

                    DB::table('facebook_campaign_lifetime_stats')->updateOrInsert(
                        ['campaign_id' => $cid],
                        [
                            'ad_account_id' => $acct,
                            'spend'         => (float) ($row['spend'] ?? 0),
                            'sales'         => $sales,
                            'sold'          => $sold,
                            'impr'          => (int) ($row['impressions'] ?? 0),
                            'clk'           => (int) ($row['clicks'] ?? 0),
                            'first_day'     => $row['date_start'] ?? null,
                            'last_day'      => $row['date_stop'] ?? null,
                            'synced_at'     => $now,
                            'updated_at'    => $now,
                            'created_at'    => $now,
                        ]
                    );
                    $count++;
                    $saved++;
                }

                // Follow Meta's cursor to the next page (keeps the same params).
                $next = $body['paging']['cursors']['after'] ?? null;
                $hasNext = ! empty($body['paging']['next']) && $next;
                if ($hasNext) {
                    $params['after'] = $next;
                }
                $pages++;
            } while ($hasNext && $pages < 200);

            $this->line("{$acct}: {$count} campaigns");
        }

        $this->info("Done — {$saved} campaign lifetime rows saved" . ($failedAccounts ? ", {$failedAccounts} account(s) failed" : '') . '.');

        return $failedAccounts > 0 && $saved === 0 ? 1 : 0;
    }
}
