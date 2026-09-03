<?php

namespace App\Console\Commands;

use App\Models\PayrollMonth;
use App\Services\PayrollService;
use Illuminate\Console\Command;

class PayrollSyncFinalHours extends Command
{
    protected $signature = 'payroll:sync-final-hours
                            {--month= : Payroll month label, e.g. "August 2026"}';

    protected $description = 'Copy Final Hour (18d TeamLogger + remaining New Logger) into Hours LM';

    public function handle(PayrollService $payroll): int
    {
        $label = trim((string) $this->option('month'));
        $month = $label !== ''
            ? PayrollMonth::where('month_label', $label)->first()
            : PayrollMonth::where('month_label', $payroll->defaultMonthLabel())->first()
                ?? PayrollMonth::orderByDesc('id')->first();

        if (! $month) {
            $this->error($label !== '' ? "Payroll month \"{$label}\" not found." : 'No payroll month found.');

            return self::FAILURE;
        }

        $this->info("Syncing Final Hour → Hours LM for {$month->month_label}...");
        $stats = $payroll->syncFinalHoursToHoursLm($month, 'Final Hour sync');

        if (! empty($stats['locked'])) {
            $this->error('Payroll month is locked. Unlock it first.');

            return self::FAILURE;
        }

        $this->line("Updated: {$stats['updated']}");
        $this->line("Unchanged: {$stats['unchanged']}");
        $this->line("Skipped (no data): {$stats['skipped_no_data']}");

        return self::SUCCESS;
    }
}
