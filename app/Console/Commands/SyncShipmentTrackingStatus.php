<?php

namespace App\Console\Commands;

use App\Services\ShipmentTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncShipmentTrackingStatus extends Command
{
    /**
     * tracking:sync-status
     *
     *   --limit=    Override max tracking numbers processed this run.
     *   --only-open Only refresh shipments that aren't yet Delivered/Expired (saves quota).
     *   --stale=    Skip numbers checked within the last N minutes (default 150 = 2.5h).
     */
    protected $signature = 'tracking:sync-status
        {--limit= : Max distinct tracking numbers to process this run}
        {--only-open : Skip numbers already Delivered/Expired}
        {--stale=150 : Skip numbers checked within the last N minutes}';

    protected $description = 'Fetch live shipment status from the tracking provider and update shopify_raw_orders';

    public function handle(ShipmentTrackingService $tracking): int
    {
        if (!$tracking->isConfigured()) {
            $this->error('Tracking provider not configured. Set TRACKING_API_KEY in .env.');
            Log::warning('tracking:sync-status aborted — TRACKING_API_KEY missing');
            return self::FAILURE;
        }

        $cfg       = config('services.tracking');
        $batchSize = max(1, (int) ($cfg['batch_size'] ?? 40));
        $maxPerRun = (int) ($this->option('limit') ?: ($cfg['max_per_run'] ?? 2000));
        $sleepMs   = max(0, (int) ($cfg['sleep_ms'] ?? 400));
        $staleMin  = max(0, (int) $this->option('stale'));

        // Distinct tracking numbers with a representative carrier.
        $query = DB::table('shopify_raw_orders')
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '');

        if ($this->option('only-open')) {
            $query->where(function ($q) {
                $q->whereNull('shipment_status')
                  ->orWhereNotIn('shipment_status', [
                      ShipmentTrackingService::STATUS_DELIVERED,
                      ShipmentTrackingService::STATUS_EXPIRED,
                  ]);
            });
        }

        if ($staleMin > 0) {
            $cutoff = now()->subMinutes($staleMin);
            $query->where(function ($q) use ($cutoff) {
                $q->whereNull('shipment_checked_at')
                  ->orWhere('shipment_checked_at', '<', $cutoff);
            });
        }

        $rows = $query->select('tracking_number', DB::raw('MAX(tracking_company) as carrier'))
            ->groupBy('tracking_number')
            ->orderByRaw('MAX(shipment_checked_at) IS NOT NULL, MAX(shipment_checked_at) ASC')
            ->limit($maxPerRun)
            ->get();

        $total = $rows->count();
        if ($total === 0) {
            $this->info('Nothing to sync.');
            return self::SUCCESS;
        }

        $this->info("Syncing shipment status for {$total} tracking number(s) in batches of {$batchSize}...");

        $updated = 0;
        $checked = 0;
        $errors  = 0;

        foreach ($rows->chunk($batchSize) as $chunk) {
            $shipments = $chunk->map(fn ($r) => [
                'number'  => $r->tracking_number,
                'carrier' => $r->carrier,
            ])->all();

            try {
                $results = $tracking->track($shipments);
            } catch (\Throwable $e) {
                $errors++;
                Log::error('tracking:sync-status batch failed', ['error' => $e->getMessage()]);
                $this->warn('  Batch failed: ' . $e->getMessage());
                continue;
            }

            $now = now();
            foreach ($chunk as $r) {
                $num = $r->tracking_number;
                $res = $results[$num] ?? null;

                $affected = DB::table('shopify_raw_orders')
                    ->where('tracking_number', $num)
                    ->update([
                        'shipment_status'        => $res['status'] ?? ShipmentTrackingService::STATUS_NOT_FOUND,
                        'shipment_status_detail' => $res['detail'] ?? null,
                        'shipment_checked_at'    => $now,
                        'updated_at'             => $now,
                    ]);

                $checked++;
                if ($res) {
                    $updated += $affected;
                }
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Tracking numbers checked', $checked],
            ['Rows updated', $updated],
            ['Batch errors', $errors],
        ]);

        Log::info('tracking:sync-status completed', [
            'checked' => $checked,
            'updated' => $updated,
            'errors'  => $errors,
        ]);

        return self::SUCCESS;
    }
}
