<?php

namespace App\Console\Commands;

use App\Services\ReverbApiService;
use App\Services\Support\ReverbBumpRuleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ApplyReverbBumpRule extends Command
{
    protected $signature = 'reverb:apply-bump-rule
        {--dry-run : Only print what would change (no S Bump% saved, nothing pushed, nothing logged)}
        {--no-push : Fill S Bump% only, do not push to Reverb}
        {--scheduled : Called by the scheduler — does nothing unless auto-run is enabled on /reverb-bump}
        {--limit= : Only process the first N listings}';

    protected $description = 'Reverb Bump % checklist: Sold > 0 → 2%, Sold = 0 → 5% (override), skip low stock / high volume / premium, push to Reverb and log';

    public function handle(): int
    {
        @set_time_limit(0);

        $settings = ReverbBumpRuleService::settings();
        if ($this->option('scheduled') && ! $settings['auto_enabled']) {
            $this->info('Auto-run is off (enable it from Sold vs Bump… on /reverb-bump). Nothing to do.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('reverb_bump_change_log')) {
            $this->error('reverb_bump_change_log table missing — run php artisan migrate.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $push = ! $this->option('no-push');
        if ($push && ! $dry && ! ReverbApiService::getReverbBearerToken()) {
            $this->error('Reverb API token not configured.');

            return self::FAILURE;
        }

        $plan = ReverbBumpRuleService::plan();
        if ($this->option('limit')) {
            $plan = array_slice($plan, 0, (int) $this->option('limit'));
        }

        $fill = count(array_filter($plan, fn ($r) => $r['eval']['status'] === 'fill'));
        $skip = count($plan) - $fill;
        $this->info('Listings: '.count($plan)." · to set: {$fill} · skipped by exceptions: {$skip}");

        if ($dry) {
            foreach (array_slice($plan, 0, 40) as $r) {
                $e = $r['eval'];
                $this->line(sprintf(
                    '  %-28s sold %-3d inv %-6s %s',
                    mb_substr($r['sku'], 0, 28), $r['sold'], rtrim(rtrim(number_format($r['inv'], 2, '.', ''), '0'), '.'),
                    $e['status'] === 'fill'
                        ? (($r['old_s_bump'] ?: '—').' → '.ReverbBumpRuleService::formatBump($e['bump']))
                        : ('skip: '.$e['reason'])
                ));
            }
            $this->info('Dry run — nothing saved.');

            return self::SUCCESS;
        }

        $runId = ReverbBumpRuleService::newRunId();
        $source = $this->option('scheduled') ? 'auto' : 'manual';

        $res = ReverbBumpRuleService::applyFill($plan, $runId, $source);
        $this->info("S Bump% saved for {$res['filled']} row(s), {$res['skipped']} skipped.");

        if ($push) {
            $out = ReverbBumpRuleService::pushPlan($plan, $runId, $source, function (int $i, string $sku, bool $ok, ?string $msg) {
                if ($i % 50 === 0 || ! $ok) {
                    $this->line("  #{$i} {$sku}: ".($ok ? 'ok' : 'FAILED '.$msg));
                }
            });
            $this->info("Pushed to Reverb: {$out['ok']} ok, {$out['failed']} failed.");

            return $out['failed'] === 0 ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }
}
