<?php

namespace App\Console\Commands;

use App\Http\Controllers\AmazonAdsController;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only health check for /amazon-ads/all: is the data behind it (daily report rows, the cached grid, the
 * scheduled jobs) actually fresh?  Run:  php artisan amazon-ads:freshness
 */
class AmazonAdsFreshness extends Command
{
    protected $signature = 'amazon-ads:freshness {--days=7 : How many recent days of daily rows to list}';

    protected $description = 'Check how fresh the data behind /amazon-ads/all is (report rows, cached grid, scheduler locks)';

    public function handle(): int
    {
        $tz = 'America/Los_Angeles';
        $today = Carbon::now($tz)->toDateString();
        $yesterday = Carbon::now($tz)->subDay()->toDateString();
        $this->info("Now (Pacific): ".Carbon::now($tz)->format('Y-m-d H:i')."   today={$today}   yesterday={$yesterday}");
        $problems = 0;

        // 1. Daily report rows (LT CVR / LT ACOS / L1 etc. are summed from these).
        foreach (['amazon_sp_campaign_reports' => 'SP', 'amazon_sb_campaign_reports' => 'SB (HL)', 'amazon_sd_campaign_reports' => 'SD'] as $table => $label) {
            $this->line('');
            if (! Schema::hasTable($table)) {
                $this->warn("{$label}: table {$table} missing");
                continue;
            }
            $this->info("{$label} daily rows ({$table})");
            $days = max(1, (int) $this->option('days'));
            $rows = DB::table($table)
                ->selectRaw('report_date_range AS d, COUNT(*) AS n, COUNT(DISTINCT campaign_id) AS campaigns, SUM(COALESCE(clicks,0)) AS clicks, MAX(updated_at) AS last_write')
                ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                ->where('report_date_range', '>=', Carbon::now($tz)->subDays($days)->toDateString())
                ->groupBy('report_date_range')
                ->orderByDesc('report_date_range')
                ->get();
            if ($rows->isEmpty()) {
                $this->error("  no daily rows in the last {$days} days");
                $problems++;
                continue;
            }
            $this->table(['day', 'rows', 'campaigns', 'clicks', 'last written'], $rows->map(fn ($r) => [$r->d, $r->n, $r->campaigns, (int) $r->clicks, $r->last_write])->all());
            if (! $rows->contains(fn ($r) => $r->d === $yesterday)) {
                $this->error("  MISSING yesterday ({$yesterday}) - the daily report job has not stored it, so LT CVR / LT ACOS are a day behind.");
                $problems++;
            }
            $l30 = DB::table($table)->where('report_date_range', 'L30')->selectRaw('COUNT(*) n, MAX(updated_at) u, MAX(endDate) e')->first();
            $this->line("  L30 summary rows: {$l30->n}, last written {$l30->u}, window ends ".($l30->e ?? 'n/a'));
        }

        // 2. Cached grid.
        $this->line('');
        $this->info('Cached grid');
        try {
            $this->line('  change version: '.AmazonAdsController::rawDataVersion());
            $stamp = Cache::get('amz_ads_warm_grid_at');
            if (is_array($stamp)) {
                $age = time() - (int) ($stamp['at'] ?? 0);
                $this->line("  default view last rebuilt {$age}s ago (version ".(($stamp['ver'] ?? null) === AmazonAdsController::rawDataVersion() ? 'current' : 'OLD').')');
                if ($age > 1800) {
                    $this->error('  warm-grid has not rebuilt for 30+ min (it should run every 10).');
                    $problems++;
                }
            } else {
                // Normal for ~10 min after `php artisan optimize:clear` (it empties the cache, including this stamp).
                $this->warn('  warm-grid has not recorded a rebuild on this cache. Expected right after optimize:clear; '
                    .'re-run in ~10 min. If it is still empty then, the scheduler is not running amazon-ads:warm-grid.');
                $problems++;
            }
            $hot = Cache::get('amz_ads_hot_views', []);
            foreach (is_array($hot) ? $hot : [] as $key => $view) {
                $entry = Cache::get($key);
                $age = is_array($entry) ? time() - (int) ($entry['at'] ?? 0) : null;
                $this->line('  view '.($view['source'] ?? '?').': '.($age === null ? 'not cached' : "built {$age}s ago"));
            }
        } catch (\Throwable $e) {
            $this->warn('  cache unreadable: '.$e->getMessage());
        }

        // 3. Scheduler overlap locks that would silently skip a job.
        $this->line('');
        $this->info('Scheduler locks (a held lock means that job is being skipped)');
        try {
            foreach (app(Schedule::class)->events() as $event) {
                $name = (string) ($event->description ?? '');
                if (! preg_match('/amazon-(sp|sb|sd)-campaign-reports|amazon-ads|amazon-pull-today|warm-grid|ads-sync/', $name)) {
                    continue;
                }
                if ($event->mutex->exists($event)) {
                    $this->error("  LOCKED: {$name}");
                    $problems++;
                }
            }
        } catch (\Throwable $e) {
            $this->warn('  could not read scheduler locks: '.$e->getMessage());
        }

        $this->line('');
        $problems === 0 ? $this->info('No freshness problems found.') : $this->error("{$problems} problem(s) found.");

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }
}
