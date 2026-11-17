<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Explains any difference between the MIP count on /forecast.analysis and the rows on /mfrg-in-progress.
 * Usage: php artisan mip:compare-forecast
 */
class CompareMipForecast extends Command
{
    protected $signature = 'mip:compare-forecast';

    protected $description = 'List SKUs counted as MIP by Forecast Analysis but missing from /mfrg-in-progress (with the reason)';

    public function handle(): int
    {
        $norm = function ($sku) {
            $sku = strtoupper(trim((string) $sku));

            return trim(preg_replace('/\s+/u', ' ', $sku));
        };

        // product_master as Forecast sees it (soft-deleted excluded); duplicates show up as extra Forecast rows.
        $pm = DB::table('product_master')->whereNull('deleted_at')->pluck('sku');
        $pmCount = [];
        foreach ($pm as $s) {
            $k = $norm($s);
            if ($k !== '') {
                $pmCount[$k] = ($pmCount[$k] ?? 0) + 1;
            }
        }

        // Forecast: latest mfrg_progress row per SKU; counted when not ready-to-ship and qty > 0.
        $latest = DB::table('mfrg_progress')->whereNull('deleted_at')
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->get(['id', 'sku', 'qty', 'ready_to_ship'])
            ->groupBy(fn ($r) => $norm($r->sku))
            ->map(fn ($g) => $g->first());

        $forecastRows = 0;
        $forecastSkus = [];
        foreach ($latest as $k => $r) {
            $rts = $r->ready_to_ship ?? 'No';
            if (($rts === 'No' || $rts === '') && (float) $r->qty > 0 && isset($pmCount[$k])) {
                $forecastSkus[$k] = $pmCount[$k];
                $forecastRows += $pmCount[$k];
            }
        }

        // MIP page: every mfrg_progress row with qty > 0 and RTS No/empty/null whose SKU is in product_master.
        $pmAny = DB::table('product_master')->pluck('sku')->map($norm)->flip();
        $mipRows = DB::table('mfrg_progress')->whereNull('deleted_at')->where('qty', '>', 0)
            ->where(function ($q) {
                $q->where('ready_to_ship', 'No')->orWhere('ready_to_ship', '')->orWhereNull('ready_to_ship');
            })->get(['id', 'sku', 'qty', 'ready_to_ship']);
        $mipSkus = [];
        foreach ($mipRows as $r) {
            $k = $norm($r->sku);
            if ($k !== '' && isset($pmAny[$k])) {
                $mipSkus[$k] = ($mipSkus[$k] ?? 0) + 1;
            }
        }

        $this->info('Forecast MIP>0 rows : '.$forecastRows.' ('.count($forecastSkus).' distinct SKUs)');
        $this->info('MIP page rows      : '.array_sum($mipSkus).' ('.count($mipSkus).' distinct SKUs)');

        $dupes = array_filter($forecastSkus, fn ($n) => $n > 1);
        if ($dupes) {
            $this->warn('SKUs duplicated in product_master (Forecast shows them more than once): '.implode(', ', array_keys($dupes)));
        }
        $missing = array_diff_key($forecastSkus, $mipSkus);
        foreach ($missing as $k => $_) {
            $this->line('In Forecast but NOT on MIP page: '.$k);
        }
        $multi = array_filter($mipSkus, fn ($n) => $n > 1);
        if ($multi) {
            $this->warn('SKUs with several MIP rows (MIP page lists each; Forecast counts one): '.implode(', ', array_keys($multi)));
        }

        return self::SUCCESS;
    }
}
