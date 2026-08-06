<?php

namespace App\Console\Commands;

use App\Models\PayrollMonth;
use App\Services\PayrollFxRateService;
use App\Services\PayrollService;
use Illuminate\Console\Command;

/**
 * Pull INR→USD / INR→CNY rates for the current payroll month (1st of each month).
 */
class FetchPayrollFxRates extends Command
{
    protected $signature = 'payroll:fetch-fx-rates {--force : Re-fetch even if rates already stored}';

    protected $description = 'Fetch Current INR Rate (USD + CNY) for the current payroll month sheet';

    public function handle(PayrollFxRateService $fx, PayrollService $payroll): int
    {
        $label = $payroll->defaultMonthLabel();
        $month = PayrollMonth::firstOrCreate(
            ['month_label' => $label],
            [
                'status' => 'draft',
                'payslip_format' => 'standard',
            ]
        );

        $before = $month->fx_rates_fetched_at;
        $month = $fx->ensureRatesForMonth($month, (bool) $this->option('force'));

        if ($month->inr_usd_rate || $month->inr_cny_rate) {
            $this->info(sprintf(
                '%s — USD rate: %s | CNY rate: %s%s',
                $month->month_label,
                $month->inr_usd_rate ?? '—',
                $month->inr_cny_rate ?? '—',
                $before ? '' : ' (fetched)'
            ));

            return self::SUCCESS;
        }

        $this->warn('No FX rates stored for '.$month->month_label);

        return self::FAILURE;
    }
}
