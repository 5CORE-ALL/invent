<?php

namespace App\Console\Commands;

use App\Http\Controllers\FacebookAllAdsSheetController;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Compares /facebook-all-ads-sheet with LIVE Meta API data, campaign by campaign:
 * name, delivery status and — over the same 30-day window the sheet uses —
 * spend, impressions and clicks. Read-only. Exit code 1 when anything differs,
 * and the differences are written to the log so a daily run shows drift early.
 *
 * With --fix it first re-pulls the campaign list + 30 days of insights (the same
 * steps the nightly schedule runs), then compares.
 */
class VerifyFacebookSheetMatchesMeta extends Command
{
    protected $signature = 'facebook:verify-meta-match
                            {--fix : Re-sync campaigns + 30 days of insights from Meta before comparing}
                            {--show=25 : Max mismatching campaigns to print}';

    protected $description = 'Check that the Facebook all-ads sheet matches live Meta API data (name, status, spend, impr, clicks)';

    public function handle(): int
    {
        $acct = trim((string) config('services.meta.sheet_ad_account_id'));
        if ($acct === '') {
            $this->error('services.meta.sheet_ad_account_id is empty.');

            return 1;
        }
        if (! str_starts_with($acct, 'act_')) {
            $acct = 'act_' . $acct;
        }

        if ($this->option('fix')) {
            $this->info('Re-syncing from Meta first...');
            $this->call('meta-ads:sync', ['--ad-account' => $acct, '--force' => true]);
            $this->call('meta-ads:refresh-insights', ['--days' => 30, '--ad-account' => $acct]);
            $this->call('facebook:sync-lifetime-stats');
            $this->call('facebook:sync-sheet-campaigns');
        }

        // Same window the sheet uses: 30 days ending at the newest synced day.
        $until = DB::table('meta_insights_daily')->where('entity_type', 'campaign')->max('date_start');
        if (! $until) {
            $this->error('No campaign insights stored yet.');

            return 1;
        }
        $since = Carbon::parse($until)->subDays(29)->toDateString();

        $baseUrl = config('services.meta.base_url', 'https://graph.facebook.com/v21.0');
        $token   = config('services.meta.access_token');

        // 1) live campaign names + status
        $live = [];
        $after = null;
        $pages = 0;
        do {
            $p = [
                'access_token'     => $token,
                'fields'           => 'id,name,effective_status',
                'limit'            => 500,
                'effective_status' => json_encode(['ACTIVE', 'PAUSED', 'ARCHIVED', 'IN_PROCESS', 'WITH_ISSUES', 'CAMPAIGN_PAUSED']),
            ];
            if ($after) {
                $p['after'] = $after;
            }
            $r = Http::timeout(120)->get("{$baseUrl}/{$acct}/campaigns", $p);
            if (! $r->successful()) {
                $this->error('Meta API error: ' . ($r->json('error.message') ?? $r->body()));

                return 1;
            }
            foreach ($r->json('data') ?? [] as $c) {
                $live[(string) $c['id']] = ['name' => trim((string) $c['name']), 'status' => strtoupper((string) $c['effective_status']), 's' => 0.0, 'i' => 0, 'k' => 0];
            }
            $after = $r->json('paging.next') ? $r->json('paging.cursors.after') : null;
        } while ($after && ++$pages < 50);

        // 2) live insights for the window
        $after = null;
        $pages = 0;
        do {
            $p = [
                'access_token' => $token,
                'level'        => 'campaign',
                'fields'       => 'campaign_id,spend,impressions,clicks',
                'time_range'   => json_encode(['since' => $since, 'until' => $until]),
                'limit'        => 500,
            ];
            if ($after) {
                $p['after'] = $after;
            }
            $r = Http::timeout(120)->get("{$baseUrl}/{$acct}/insights", $p);
            if (! $r->successful()) {
                $this->error('Meta API error: ' . ($r->json('error.message') ?? $r->body()));

                return 1;
            }
            foreach ($r->json('data') ?? [] as $d) {
                $id = (string) ($d['campaign_id'] ?? '');
                if (isset($live[$id])) {
                    $live[$id]['s'] += (float) ($d['spend'] ?? 0);
                    $live[$id]['i'] += (int) ($d['impressions'] ?? 0);
                    $live[$id]['k'] += (int) ($d['clicks'] ?? 0);
                }
            }
            $after = $r->json('paging.next') ? $r->json('paging.cursors.after') : null;
        } while ($after && ++$pages < 50);

        // 3) the sheet as the page shows it
        $merged = app(FacebookAllAdsSheetController::class)->getMergedView(null);
        $rows = $merged['data'] ?? [];
        $num = fn ($v) => (float) preg_replace('/[^0-9.\-]/', '', (string) $v);
        $statusWord = fn (string $s) => match ($s) {
            'ACTIVE' => 'active',
            'PAUSED', 'CAMPAIGN_PAUSED' => 'inactive',
            'ARCHIVED' => 'archived',
            default => null,
        };

        $checked = 0;
        $notInMeta = 0;
        $bad = [];
        foreach ($rows as $row) {
            $id = (string) ($row['CAMPAIGN ID'] ?? '');
            if (! preg_match('/^\d{6,}$/', $id)) {
                continue;
            }
            if (! isset($live[$id])) {
                $notInMeta++;
                continue;
            }
            $checked++;
            $m = $live[$id];
            $diff = [];
            if (trim((string) ($row['Campaign name'] ?? '')) !== $m['name']) {
                $diff[] = 'name: sheet "' . ($row['Campaign name'] ?? '') . '" vs meta "' . $m['name'] . '"';
            }
            $word = $statusWord($m['status']);
            if ($word !== null && strtolower(trim((string) ($row['Status'] ?? ''))) !== $word) {
                $diff[] = 'status: sheet "' . ($row['Status'] ?? '') . '" vs meta "' . $word . '"';
            }
            if (abs($num($row['SPEND'] ?? 0) - $m['s']) > 0.011) {
                $diff[] = 'spend: sheet ' . ($row['SPEND'] ?? '') . ' vs meta ' . round($m['s'], 2);
            }
            if ((int) $num($row['IMPR'] ?? 0) !== $m['i']) {
                $diff[] = 'impr: sheet ' . ($row['IMPR'] ?? '') . ' vs meta ' . $m['i'];
            }
            if ((int) $num($row['CLK'] ?? 0) !== $m['k']) {
                $diff[] = 'clk: sheet ' . ($row['CLK'] ?? '') . ' vs meta ' . $m['k'];
            }
            if ($diff) {
                $bad[$id] = $diff;
            }
        }

        $this->line("Window {$since} to {$until} | sheet rows: " . count($rows) . " | compared with Meta: {$checked} | not in Meta account: {$notInMeta}");
        $this->line('MISMATCHED CAMPAIGNS: ' . count($bad));
        $shown = 0;
        foreach ($bad as $id => $diff) {
            if (++$shown > (int) $this->option('show')) {
                break;
            }
            $this->line("  {$id}");
            foreach ($diff as $d) {
                $this->line("      {$d}");
            }
        }

        // 4) Lifetime columns (Lifetime Spend / Sales / Acos, LT Sold…) are read from
        // facebook_campaign_lifetime_stats — compare that table with Meta's
        // all-time numbers for the campaigns on the sheet.
        $ltBad = [];
        $ltChecked = 0;
        $sheetIds = [];
        foreach ($rows as $row) {
            $id = (string) ($row['CAMPAIGN ID'] ?? '');
            if (preg_match('/^\d{6,}$/', $id)) {
                $sheetIds[$id] = true;
            }
        }
        if ($sheetIds) {
            $metaLt = [];
            $after = null;
            $pages = 0;
            do {
                $p = [
                    'access_token' => $token,
                    'level'        => 'campaign',
                    'date_preset'  => 'maximum',
                    'fields'       => 'campaign_id,spend,impressions,clicks,actions,action_values',
                    'limit'        => 500,
                ];
                if ($after) {
                    $p['after'] = $after;
                }
                $r = Http::timeout(120)->get("{$baseUrl}/{$acct}/insights", $p);
                if (! $r->successful()) {
                    $this->error('Meta API error (lifetime): ' . ($r->json('error.message') ?? $r->body()));

                    return 1;
                }
                foreach ($r->json('data') ?? [] as $d) {
                    $sales = 0.0;
                    $sold = 0;
                    foreach ($d['action_values'] ?? [] as $a) {
                        if (($a['action_type'] ?? '') === 'purchase') {
                            $sales = (float) $a['value'];
                        }
                    }
                    foreach ($d['actions'] ?? [] as $a) {
                        if (($a['action_type'] ?? '') === 'purchase') {
                            $sold = (int) $a['value'];
                        }
                    }
                    $metaLt[(string) $d['campaign_id']] = [
                        'spend' => (float) ($d['spend'] ?? 0), 'sales' => $sales, 'sold' => $sold,
                        'impr' => (int) ($d['impressions'] ?? 0), 'clk' => (int) ($d['clicks'] ?? 0),
                    ];
                }
                $after = $r->json('paging.next') ? $r->json('paging.cursors.after') : null;
            } while ($after && ++$pages < 50);

            foreach (DB::table('facebook_campaign_lifetime_stats')->whereIn('campaign_id', array_keys($sheetIds))->get() as $s) {
                $m = $metaLt[(string) $s->campaign_id] ?? null;
                if (! $m) {
                    continue;
                }
                $ltChecked++;
                $diff = [];
                // Meta's all-time totals include today's still-running spend, so an
                // active campaign drifts a little between the lifetime sync and now.
                // Only flag differences bigger than 0.5% (min $1 / 5 units).
                $money = fn (float $a, float $b) => abs($a - $b) > max(1.0, 0.005 * max($a, $b));
                $count = fn (int $a, int $b) => abs($a - $b) > max(5, (int) ceil(0.005 * max($a, $b)));
                if ($money((float) $s->spend, $m['spend'])) $diff[] = "lifetime spend: sheet {$s->spend} vs meta {$m['spend']}";
                if ($money((float) $s->sales, $m['sales'])) $diff[] = "lifetime sales: sheet {$s->sales} vs meta {$m['sales']}";
                if ($count((int) $s->sold, $m['sold']))     $diff[] = "lifetime sold: sheet {$s->sold} vs meta {$m['sold']}";
                if ($count((int) $s->impr, $m['impr']))     $diff[] = "lifetime impr: sheet {$s->impr} vs meta {$m['impr']}";
                if ($count((int) $s->clk, $m['clk']))       $diff[] = "lifetime clk: sheet {$s->clk} vs meta {$m['clk']}";
                if ($diff) {
                    $ltBad[(string) $s->campaign_id] = $diff;
                }
            }
        }
        $this->line("Lifetime stats compared with Meta: {$ltChecked} | MISMATCHED: " . count($ltBad));
        $shown = 0;
        foreach ($ltBad as $id => $diff) {
            if (++$shown > (int) $this->option('show')) {
                break;
            }
            $this->line("  {$id}");
            foreach ($diff as $d) {
                $this->line("      {$d}");
            }
        }
        foreach ($ltBad as $id => $diff) {
            $bad[$id] = array_merge($bad[$id] ?? [], $diff);
        }

        if ($bad) {
            Log::warning('facebook:verify-meta-match mismatches', ['count' => count($bad), 'sample' => array_slice($bad, 0, 10, true)]);
            $this->warn('Sheet does NOT fully match Meta. Run again with --fix, or check the queue worker / logs.');

            return 1;
        }

        $this->info('OK: every compared campaign matches Meta exactly.');

        return 0;
    }
}
