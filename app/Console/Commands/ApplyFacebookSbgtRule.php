<?php

namespace App\Console\Commands;

use App\Http\Controllers\FacebookAllAdsSheetController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Turn Facebook / Instagram campaigns on and off on Meta according to the
 * current Sbgt rule of /facebook-all-ads-sheet. Runs right after the rule is
 * saved (ApplyFacebookSbgtRuleJob) and can be run by hand.
 *
 *   • Rule says pause (Sbgt 0)      → campaign is paused on Meta if it isn't already.
 *   • Rule gives a budget (Sbgt > 0) → a campaign that is PAUSED on Meta is turned ON
 *     (whoever paused it). Archived / deleted campaigns are skipped.
 *
 * Every change is written to meta_action_logs. Budgets are not touched here (use Push Sbgt,
 * which also turns the campaign on).
 */
class ApplyFacebookSbgtRule extends Command
{
    protected $signature = 'facebook:apply-sbgt-rule
                            {--dry-run : Only list what would change}
                            {--user= : User id to record in meta_action_logs (default 0 = system)}';

    protected $description = 'Pause / re-activate Meta campaigns to match the current Sbgt rule of the Facebook all-ads sheet';

    public function handle(): int
    {
        if (! Schema::hasTable('meta_campaigns') || ! Schema::hasTable('meta_action_logs')) {
            $this->error('Missing tables meta_campaigns / meta_action_logs.');
            return 1;
        }
        $token = (string) config('services.meta.access_token');
        if ($token === '') {
            $this->error('META_ACCESS_TOKEN is not set.');
            return 1;
        }
        $dry    = (bool) $this->option('dry-run');
        $userId = (int) ($this->option('user') ?: 0);
        $base   = 'https://graph.facebook.com/' . (config('services.meta.api_version') ?: 'v21.0');

        @set_time_limit(0);

        $view = app(FacebookAllAdsSheetController::class)->getMergedView(null, null);
        $rows = is_array($view) ? ($view['data'] ?? []) : [];
        if (! $rows) {
            $this->warn('No sheet rows — nothing to do.');
            return 0;
        }

        // One decision per campaign id: 'pause' | 'activate-candidate'
        $want = [];
        foreach ($rows as $r) {
            $cid = trim((string) ($r['CAMPAIGN ID'] ?? ''));
            if (! preg_match('/^\d{6,}$/', $cid) || isset($want[$cid])) {
                continue;
            }
            $sbgt = $r['Sbgt'] ?? null;
            if (! empty($r['_pause'])) {
                $want[$cid] = 'pause';
            } elseif ($sbgt !== null && $sbgt !== '' && is_numeric($sbgt) && (float) $sbgt > 0) {
                $want[$cid] = 'activate';
            }
        }
        if (! $want) {
            $this->info('No campaign has a pause or budget decision.');
            return 0;
        }
        $cids = array_keys($want);

        // Current status on Meta (as last synced) — newest synced copy wins.
        $status = [];
        foreach (array_chunk($cids, 1000) as $chunk) {
            foreach (DB::table('meta_campaigns')->whereIn('meta_id', $chunk)->orderBy('synced_at')
                ->get(['meta_id', 'status', 'effective_status']) as $c) {
                $status[(string) $c->meta_id] = strtoupper((string) ($c->status ?: $c->effective_status));
            }
        }

        $stats = ['paused' => 0, 'activated' => 0, 'failed' => 0, 'unchanged' => 0];
        foreach ($want as $cid => $decision) {
            $st = $status[$cid] ?? '';
            if (in_array($st, ['ARCHIVED', 'DELETED'], true)) {
                $stats['unchanged']++;
                continue;
            }
            if ($decision === 'pause') {
                if ($st === 'PAUSED') {
                    $stats['unchanged']++;
                    continue;
                }
                $action = 'pause_campaign';
                $target = 'PAUSED';
            } else {
                if ($st !== 'PAUSED') {
                    $stats['unchanged']++;
                    continue;
                }
                $action = 'activate_campaign';
                $target = 'ACTIVE';
            }

            if ($dry) {
                $this->line(($target === 'PAUSED' ? 'would PAUSE    ' : 'would ACTIVATE ') . $cid);
                $stats[$target === 'PAUSED' ? 'paused' : 'activated']++;
                continue;
            }

            $outcome = $this->setStatus($base, $token, $cid, $target, $action, $userId);
            if ($outcome === true) {
                $stats[$target === 'PAUSED' ? 'paused' : 'activated']++;
            } else {
                $stats['failed']++;
                $this->warn("{$cid}: {$outcome}");
                // Expired / invalid token: every further call would fail the same way.
                if (str_contains((string) $outcome, 'access token') || str_contains((string) $outcome, 'OAuth')) {
                    $this->error('Meta token problem — stopping.');
                    break;
                }
            }
            usleep(150000);
        }

        $summary = ($dry ? '[dry-run] ' : '')
            . "paused {$stats['paused']}, activated {$stats['activated']}, failed {$stats['failed']}, unchanged {$stats['unchanged']}";
        $this->info($summary);
        Log::info('facebook:apply-sbgt-rule ' . $summary);

        return $stats['failed'] > 0 ? 1 : 0;
    }

    /** @return true|string  true on success, otherwise the error message */
    private function setStatus(string $base, string $token, string $cid, string $target, string $action, int $userId)
    {
        try {
            $resp = Http::asForm()->timeout(20)->post("{$base}/{$cid}", [
                'access_token' => $token,
                'status'       => $target,
            ]);
            $body = $resp->json() ?? [];
            $ok   = $resp->successful() && (($body['success'] ?? false) === true || isset($body['id']));
            $err  = $ok ? null : ($body['error']['message'] ?? ('HTTP ' . $resp->status()));

            DB::table('meta_action_logs')->insert([
                'user_id'            => $userId,
                'action_type'        => $action,
                'entity_type'        => 'campaign',
                'entity_meta_id'     => $cid,
                'status'             => $ok ? 'success' : 'failed',
                'request_payload'    => json_encode(['status' => $target, 'source' => 'sbgt_rule_apply']),
                'response_payload'   => json_encode($body),
                'error_message'      => $err,
                'meta_error_code'    => $ok ? null : (string) ($body['error']['code'] ?? ''),
                'meta_error_message' => $ok ? null : ($body['error']['error_user_msg'] ?? $body['error']['message'] ?? null),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            if ($ok) {
                DB::table('meta_campaigns')->where('meta_id', $cid)->update([
                    'status'           => $target,
                    'effective_status' => $target,
                    'updated_at'       => now(),
                ]);
                if ($target === 'ACTIVE') {
                    Cache::forget('faas_sbgt_paused:' . $cid);
                } else {
                    Cache::put('faas_sbgt_paused:' . $cid, 1, now()->addHours(12));
                }
                return true;
            }

            return (string) $err;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }
}
