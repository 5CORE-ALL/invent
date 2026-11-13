<?php

namespace App\Console\Commands;

use App\Support\Marketplace\Settle\SettleCatalog;
use App\Support\Marketplace\Settle\SettleVerifier;
use App\Support\Marketplace\YesterdaySettleLedger as Ledger;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Verifies (read-only) that the pulls recorded in the yesterday settle ledger really stored
 * complete data, mirrors the per-marketplace state into settle_task_runs and sends alerts.
 *
 * It never runs a marketplace pull and never writes to marketplace tables.
 */
class SettleVerifyCommand extends Command
{
    protected $signature = 'settle:verify
        {--date= : Pacific date (Y-m-d). Defaults to Pacific yesterday}
        {--only= : Comma-separated task keys to verify}
        {--force : Verify again even if the task is already verified for its current pull}
        {--no-alerts : Do not send alerts}
        {--accept-baseline= : Operator action: accept the CURRENT table size of these tasks (comma separated) as the new passing baseline, e.g. after a deliberate prune}
        {--json : Print the full result as JSON}';

    protected $description = 'Read-only verification of yesterday\'s marketplace pulls (records, duplicates, zeros, pagination) + alerts';

    public function handle(SettleVerifier $verifier): int
    {
        $now = Carbon::now(Ledger::TZ);
        $date = (string) ($this->option('date') ?: Ledger::pacificYesterday($now));
        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));

        $accept = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('accept-baseline')))));
        if ($accept !== []) {
            foreach ($accept as $task) {
                $r = $verifier->acceptBaseline($date, $task, $now);
                $r['ok'] ? $this->info($r['message']) : $this->error($r['message']);
            }

            return self::SUCCESS;
        }

        $out = $verifier->run($date, (bool) $this->option('force'), $only, ! $this->option('no-alerts'), $now);

        if ($this->option('json')) {
            $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if (! ($out['available'] ?? false)) {
            $this->warn((string) ($out['message'] ?? 'Tracking tables are not available.'));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '[%s] verified %d task(s) this run. %d/%d tasks verified overall; %d verification failure(s); %d alert(s) sent.',
            $date,
            count($out['results']),
            $out['summary']['verified'],
            $out['summary']['total'],
            count($out['summary']['verification_failed']),
            count($out['alerts'])
        ));
        foreach ($out['results'] as $key => $r) {
            $line = sprintf('  %-22s %-8s %s', $key, strtoupper($r['status']), SettleCatalog::marketplace($key));
            $r['status'] === 'fail' || $r['status'] === 'error' ? $this->warn($line) : $this->line($line);
            foreach ($r['checks'] as $c) {
                if ($c['status'] !== 'pass') {
                    $this->line('      '.$c['status'].' '.$c['code'].': '.$c['message']);
                }
            }
        }

        return self::SUCCESS;
    }
}
