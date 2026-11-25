<?php

namespace App\Console\Commands;

use App\Support\Penalty;
use Illuminate\Console\Command;

class PenaltyCloseMonth extends Command
{
    protected $signature = 'penalty:close-month {month? : Y-m to close (default: every finished month still open)}';

    protected $description = 'On the 1st of the month: freeze the previous month\'s penalty points and price them with the slab rates';

    public function handle(): int
    {
        if (! Penalty::tablesReady()) {
            $this->warn('Penalty tables are not ready.');

            return self::SUCCESS;
        }

        if ($this->argument('month')) {
            $n = Penalty::closeMonth((string) $this->argument('month'));
            $this->info("Closed {$n} member(s) for {$this->argument('month')}.");

            return self::SUCCESS;
        }

        Penalty::ensureMonthsClosed(true);
        $this->info('Finished months are closed.');

        return self::SUCCESS;
    }
}
