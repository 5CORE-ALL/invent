<?php

namespace App\Services;

use App\Models\PayrollMonth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches and stores INR FX rates for a payroll month (once, on/after the 1st).
 * Rates are "INR per 1 foreign unit" so foreign = INR_total / rate.
 */
class PayrollFxRateService
{
    /**
     * Ensure the month has USD + CNY INR rates. Fetches from the internet on the
     * 1st of that month (or the latest available business day) when missing.
     */
    public function ensureRatesForMonth(PayrollMonth $month, bool $force = false): PayrollMonth
    {
        if (! $force && $month->fx_rates_fetched_at && $month->inr_usd_rate && $month->inr_cny_rate) {
            return $month;
        }

        $asOf = $this->rateDateForMonth($month);
        if (! $asOf) {
            return $month;
        }

        // Only auto-fetch once the month's 1st has arrived (or force).
        if (! $force && now()->startOfDay()->lt($asOf->copy()->startOfDay())) {
            return $month;
        }

        try {
            $usd = $this->fetchInrPerUnit('USD', $asOf);
            $cny = $this->fetchInrPerUnit('CNY', $asOf);

            if ($usd === null && $cny === null) {
                return $month;
            }

            $month->fill([
                'inr_usd_rate' => $usd ?? $month->inr_usd_rate,
                'inr_cny_rate' => $cny ?? $month->inr_cny_rate,
                'fx_rates_fetched_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Payroll FX rate fetch failed', [
                'month' => $month->month_label,
                'error' => $e->getMessage(),
            ]);
        }

        return $month->fresh() ?? $month;
    }

    /**
     * Calendar 1st of the payroll month (period_start or parsed month_label).
     */
    public function rateDateForMonth(PayrollMonth $month): ?Carbon
    {
        if ($month->period_start) {
            return $month->period_start->copy()->startOfMonth();
        }

        try {
            return Carbon::parse('1 '.$month->month_label)->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * INR amount for 1 unit of $currency on/near $date (walks back if holiday).
     */
    public function fetchInrPerUnit(string $currency, Carbon $date): ?float
    {
        $currency = strtoupper($currency);
        $cursor = $date->copy()->startOfDay();
        $min = $date->copy()->subDays(7);

        while ($cursor->gte($min)) {
            $rate = $this->fetchFrankfurter($currency, $cursor)
                ?? $this->fetchOpenErApi($currency);

            if ($rate !== null && $rate > 0) {
                return round($rate, 4);
            }

            $cursor->subDay();
        }

        return null;
    }

    protected function fetchFrankfurter(string $currency, Carbon $date): ?float
    {
        // Frankfurter: how many INR for 1 USD/CNY on that date.
        $url = sprintf(
            'https://api.frankfurter.app/%s?from=%s&to=INR',
            $date->format('Y-m-d'),
            $currency
        );

        $response = Http::timeout(8)->acceptJson()->get($url);
        if (! $response->successful()) {
            return null;
        }

        $rate = $response->json('rates.INR');

        return is_numeric($rate) ? (float) $rate : null;
    }

    /**
     * Fallback (latest rates only — used when historical frankfurter fails).
     */
    protected function fetchOpenErApi(string $currency): ?float
    {
        $url = 'https://open.er-api.com/v6/latest/'.$currency;
        $response = Http::timeout(8)->acceptJson()->get($url);
        if (! $response->successful() || ($response->json('result') !== 'success')) {
            return null;
        }

        $rate = $response->json('rates.INR');

        return is_numeric($rate) ? (float) $rate : null;
    }
}
