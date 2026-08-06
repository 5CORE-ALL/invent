<?php

namespace App\Console\Commands;

use App\Http\Controllers\Channels\SalesOrderFulfillmentController;
use App\Models\SalesOrderFulfillmentDailySummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Capture Sales Order Fulfillment summary-bar counts once per Pacific day (00:00 PST/PDT).
 * Always stores a row for that day — even when totals are unchanged vs the previous day.
 */
class SnapshotSalesOrderFulfillmentDaily extends Command
{
    protected $signature = 'sof:snapshot-daily
        {--date= : Pacific Y-m-d to store (default: previous Pacific day)}
        {--catch-up : Only create the row if that Pacific day is still missing}
        {--backfill=0 : Also ensure the last N Pacific days each have a row}';

    protected $description = 'Save SOF summary history for a Pacific calendar day (always records, even if no change)';

    public function handle(SalesOrderFulfillmentController $controller): int
    {
        $dateOpt = trim((string) $this->option('date'));
        $catchUp = (bool) $this->option('catch-up');
        $backfill = max(0, (int) $this->option('backfill'));
        $pacific = now('America/Los_Angeles');

        if ($dateOpt !== '') {
            $snapshotDate = $dateOpt;
        } else {
            // Cron at 00:00 Pacific → store the day that just ended.
            $snapshotDate = $pacific->copy()->subDay()->toDateString();
        }

        try {
            if (! Schema::hasTable('sales_order_fulfillment_daily_data')) {
                $this->error('sales_order_fulfillment_daily_data table missing — run migrations.');

                return self::FAILURE;
            }

            // Always include target day; --backfill=N also ensures N older Pacific days exist.
            $dates = [$snapshotDate];
            if ($backfill > 0) {
                $anchor = Carbon\Carbon::parse($snapshotDate, 'America/Los_Angeles')->startOfDay();
                for ($i = 1; $i <= $backfill; $i++) {
                    $dates[] = $anchor->copy()->subDays($i)->toDateString();
                }
            }
            $dates = array_values(array_unique($dates));
            sort($dates);

            $saved = 0;
            $skipped = 0;
            foreach ($dates as $date) {
                $before = SalesOrderFulfillmentDailySummary::query()
                    ->whereDate('snapshot_date', $date)
                    ->exists();

                $row = $controller->saveDailySnapshot($date, $catchUp);
                $same = ! empty(($row->summary_data ?? [])['same_as_previous_day']);

                if ($catchUp && $before) {
                    $skipped++;
                    $this->line("  {$date}: already present (catch-up skip)");
                    continue;
                }

                $saved++;
                $this->info("  {$date}: saved id {$row->id}".($same ? ' [unchanged vs prior day — recorded]' : ''));
            }

            $this->info("SOF daily snapshot done. saved={$saved}, catch_up_skipped={$skipped}");
            Log::info('sof:snapshot-daily completed', [
                'dates' => $dates,
                'saved' => $saved,
                'catch_up_skipped' => $skipped,
                'catch_up' => $catchUp,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('SOF daily snapshot failed: '.$e->getMessage());
            Log::error('sof:snapshot-daily failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
