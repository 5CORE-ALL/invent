<?php

namespace App\Console\Commands;

use App\Support\Marketplace\YesterdaySettleLedger as Ledger;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Prints the per-marketplace settle status (state, attempts, last error, next retry, verification).
 * Strictly read-only: it reads the ledger and the settle_* tables and writes nothing.
 */
class SettleReportCommand extends Command
{
    protected $signature = 'settle:report
        {--date= : Pacific date (Y-m-d). Defaults to Pacific yesterday}
        {--problems : Only show tasks that are not verified}
        {--json : Print JSON}';

    protected $description = 'Per-marketplace status of the yesterday settle (read-only)';

    public function handle(): int
    {
        $now = Carbon::now(Ledger::TZ);
        $date = (string) ($this->option('date') ?: Ledger::pacificYesterday($now));
        $status = Ledger::status($date, $now);

        if ($this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $v = $status['verification'];
        $this->info(sprintf(
            'Pacific %s — %s (ledger %s, verified %d/%d%s)',
            $date,
            strtoupper((string) ($status['level_label'] ?? ($status['settled'] ? 'Settled' : 'Not settled'))),
            $status['ledger_settled'] ? 'settled' : 'not settled',
            $v['verified'],
            $v['total'],
            $v['available'] ? '' : ', tracking tables missing'
        ));

        $rows = [];
        foreach ($status['tasks'] as $t) {
            if ($this->option('problems') && in_array($t['state'], ['verified', 'limited_by_design'], true)) {
                continue;
            }
            $problem = '';
            foreach ($t['verification']['checks'] ?? [] as $c) {
                $problem .= ($problem === '' ? '' : ' | ').$c['code'].': '.$c['message'];
            }
            $rows[] = [
                $t['marketplace'].' ('.$t['kind'].')',
                $t['key'],
                $t['state'],
                $t['attempts'].'/'.$t['max_attempts'],
                mb_substr((string) ($t['last_error'] ?? ''), 0, 60),
                $t['next_retry_at'] ? Carbon::parse($t['next_retry_at'])->format('H:i').' PT' : (string) ($t['next_retry_note'] ?? ''),
                mb_substr($problem, 0, 90),
            ];
        }
        $this->table(['Marketplace', 'Task', 'State', 'Attempts', 'Last error', 'Next retry', 'Verification'], $rows);

        return self::SUCCESS;
    }
}
