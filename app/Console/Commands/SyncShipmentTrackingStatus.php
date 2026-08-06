<?php

namespace App\Console\Commands;

use App\Services\ShipmentTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncShipmentTrackingStatus extends Command
{
    /**
     * tracking:sync-status
     *
     *   --limit=       Max tracking numbers this run (default: paced for ~1–2 passes/day).
     *   --only-open    Only refresh shipments that aren't yet Delivered/Expired (default on via schedule).
     *   --stale=       Skip numbers checked within the last N minutes (default auto from target passes/day).
     *   --prefer-native Force USPS/UPS/FedEx instead of 17TRACK aggregator.
     *   --repair-quota Clear statuses poisoned by prior quota errors so they re-queue.
     */
    protected $signature = 'tracking:sync-status
        {--limit= : Max distinct tracking numbers to process this run}
        {--only-open : Skip numbers already Delivered/Expired}
        {--stale= : Skip numbers checked within the last N minutes (omit for auto ~2 passes/day)}
        {--carrier= : Optional carrier filter (USPS, UPS, FEDEX, GOFO, ...)}
        {--prefer-native : Prefer native carrier APIs over 17TRACK}
        {--repair-quota : Reset NotFound rows that only contain quota/rate-limit error text}';

    protected $description = 'Fetch live shipment status from the tracking provider and update shopify_raw_orders (open shipments only when scheduled)';

    public function handle(ShipmentTrackingService $tracking): int
    {
        if (! $tracking->isConfigured()) {
            $this->error('Tracking provider not configured. Set USPS / UPS credentials or TRACKING_API_KEY in .env.');
            Log::warning('tracking:sync-status aborted — no tracking provider configured');

            return self::FAILURE;
        }

        if (! Schema::hasTable('shopify_raw_orders')) {
            $this->warn('shopify_raw_orders table missing.');

            return self::SUCCESS;
        }

        if ($this->option('repair-quota')) {
            $repaired = $this->repairQuotaPoisonedRows();
            $this->info("Repaired {$repaired} quota-poisoned row(s).");
        }

        $cfg = config('services.tracking');
        $batchSize = max(1, (int) ($cfg['batch_size'] ?? 40));
        $sleepMs = max(0, (int) ($cfg['sleep_ms'] ?? 400));
        $carrierFilter = strtoupper(trim((string) $this->option('carrier')));
        $preferNative = (bool) $this->option('prefer-native');
        $onlyOpen = (bool) $this->option('only-open');

        $staleMin = $this->resolveStaleMinutes($onlyOpen, $carrierFilter);
        $maxPerRun = $this->resolveMaxPerRun($tracking, $onlyOpen, $staleMin, $carrierFilter);

        $query = DB::table('shopify_raw_orders')
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '');

        if ($carrierFilter !== '') {
            $this->applyCarrierFilter($query, $carrierFilter);
        }

        if ($onlyOpen) {
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

        $rows = $query->select(
                'tracking_number',
                DB::raw('MAX(tracking_company) as carrier'),
                DB::raw('MAX(CASE WHEN shipment_status IS NULL OR shipment_status = \'\' THEN 1 ELSE 0 END) as needs_status')
            )
            ->groupBy('tracking_number')
            // Backlog first (no status), then never-checked, then oldest — burn quota on catch-up.
            ->orderByRaw('needs_status DESC')
            ->orderByRaw('MAX(shipment_checked_at) IS NOT NULL, MAX(shipment_checked_at) ASC')
            ->limit($maxPerRun)
            ->get();

        $total = $rows->count();
        if ($total === 0) {
            $this->info('Nothing to sync.');

            return self::SUCCESS;
        }

        $mode = $preferNative ? 'native-first' : 'aggregator-first';
        $uspsLeft = $tracking->uspsRemainingThisHour();
        $this->info("Syncing {$total} tracking number(s) [{$mode}, stale≥{$staleMin}m, USPS remaining this hour: {$uspsLeft}]...");

        $updated = 0;
        $checked = 0;
        $skipped = 0;
        $errors = 0;
        $quotaStops = 0;

        foreach ($rows->chunk($batchSize) as $chunk) {
            $shipments = $chunk->map(fn ($r) => [
                'number' => $r->tracking_number,
                'carrier' => $r->carrier,
            ])->all();

            try {
                $results = $tracking->track($shipments, [
                    'prefer_native' => $preferNative,
                ]);
            } catch (\Throwable $e) {
                $errors++;
                Log::error('tracking:sync-status batch failed', ['error' => $e->getMessage()]);
                $this->warn('  Batch failed: '.$e->getMessage());
                continue;
            }

            $now = now();
            $batchQuota = 0;

            foreach ($chunk as $r) {
                $num = $r->tracking_number;
                $res = $results[$num] ?? null;
                $checked++;

                if (! ShipmentTrackingService::isPersistableResult($res)) {
                    $skipped++;
                    if (($res['status'] ?? null) === ShipmentTrackingService::STATUS_RATE_LIMITED
                        || ! empty($res['transient'])) {
                        $batchQuota++;
                        // Rotate past this number for ~1 hour so the rest of the queue advances.
                        // Status/detail stay untouched (no more "NotFound + Exceeded quota" poison).
                        $rotateAt = $staleMin > 60
                            ? now()->subMinutes($staleMin - 60)
                            : now();
                        DB::table('shopify_raw_orders')
                            ->where('tracking_number', $num)
                            ->update([
                                'shipment_checked_at' => $rotateAt,
                                'updated_at' => $now,
                            ]);
                    }
                    continue;
                }

                $affected = DB::table('shopify_raw_orders')
                    ->where('tracking_number', $num)
                    ->update([
                        'shipment_status' => $res['status'],
                        'shipment_status_detail' => $res['detail'] ?? null,
                        'shipment_checked_at' => $now,
                        'updated_at' => $now,
                    ]);

                $updated += $affected;
            }

            if ($batchQuota > 0 && $batchQuota >= max(1, (int) floor(count($chunk) * 0.5))) {
                $quotaStops++;
                $this->warn('  Provider quota/rate-limit hit — stopping further batches this run.');
                break;
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Tracking numbers attempted', $checked],
            ['Rows updated', $updated],
            ['Skipped (no persist / quota)', $skipped],
            ['Batch errors', $errors],
            ['Early stop (quota)', $quotaStops],
        ]);

        Log::info('tracking:sync-status completed', [
            'checked' => $checked,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'quota_stops' => $quotaStops,
            'stale_min' => $staleMin,
            'limit' => $maxPerRun,
            'prefer_native' => $preferNative,
        ]);

        return self::SUCCESS;
    }

    /**
     * Clear statuses that were incorrectly saved as NotFound because of API quota errors.
     */
    protected function repairQuotaPoisonedRows(): int
    {
        return (int) DB::table('shopify_raw_orders')
            ->where('shipment_status', ShipmentTrackingService::STATUS_NOT_FOUND)
            ->where(function ($q) {
                $q->where('shipment_status_detail', 'like', '%quota%')
                    ->orWhere('shipment_status_detail', 'like', '%rate limit%')
                    ->orWhere('shipment_status_detail', 'like', '%Too Many Requests%')
                    ->orWhere('shipment_status_detail', 'like', '%ran out%');
            })
            ->update([
                'shipment_status' => null,
                'shipment_status_detail' => null,
                'shipment_checked_at' => null,
                'updated_at' => now(),
            ]);
    }

    protected function resolveStaleMinutes(bool $onlyOpen, string $carrierFilter): int
    {
        $raw = $this->option('stale');
        if ($raw !== null && $raw !== '') {
            return max(0, (int) $raw);
        }

        $backlog = $this->countOpenNeedingStatus($onlyOpen, $carrierFilter);
        // Large 30-day backlog: avoid re-hitting the same numbers — burn full daily quota on unique catch-up.
        if ($backlog >= 100) {
            return 20 * 60; // 20 hours
        }

        // Steady state: target N passes/day → stale ≈ 24h / N (e.g. 2 → 720 minutes).
        $passes = max(1, (int) config('services.tracking.target_passes_per_day', 2));

        return (int) max(60, (int) floor((24 * 60) / $passes));
    }

    protected function resolveMaxPerRun(
        ShipmentTrackingService $tracking,
        bool $onlyOpen,
        int $staleMin,
        string $carrierFilter
    ): int {
        $configured = (int) ($this->option('limit') ?: (config('services.tracking.max_per_run') ?? 200));
        $configured = max(1, $configured);

        if ($this->option('limit') !== null && $this->option('limit') !== '') {
            return $configured;
        }

        $preferAggregator = (bool) config('services.tracking.prefer_aggregator', true);
        $has17 = $tracking->has17Track();

        // Use as much of this hour's allowance as possible (full day = 24 × hourly budget).
        $uspsLeft = max(0, $tracking->uspsRemainingThisHour());

        if ($preferAggregator && $has17) {
            return min($configured, max($uspsLeft, 80), 200);
        }

        // Native: spend remaining USPS budget this hour + room for UPS/FedEx in the same tick.
        return min($configured, $uspsLeft + 40, 200);
    }

    protected function countOpenNeedingStatus(bool $onlyOpen, string $carrierFilter): int
    {
        $q = DB::table('shopify_raw_orders')
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->where(function ($w) {
                $w->whereNull('shipment_status')
                    ->orWhere('shipment_status', '');
            });

        if ($onlyOpen) {
            $q->where(function ($w) {
                $w->whereNull('shipment_status')
                    ->orWhereNotIn('shipment_status', [
                        ShipmentTrackingService::STATUS_DELIVERED,
                        ShipmentTrackingService::STATUS_EXPIRED,
                    ]);
            });
        }
        if ($carrierFilter !== '') {
            $this->applyCarrierFilter($q, $carrierFilter);
        }

        return (int) $q->distinct()->count('tracking_number');
    }

    protected function applyCarrierFilter($query, string $carrierFilter): void
    {
        if ($carrierFilter === 'UPS') {
            $query->where(function ($q) {
                $q->whereRaw("UPPER(TRIM(COALESCE(tracking_company, ''))) LIKE ?", ['UPS%'])
                    ->orWhere('tracking_number', 'like', '1Z%');
            });
        } elseif ($carrierFilter === 'USPS') {
            $query->whereRaw("UPPER(TRIM(COALESCE(tracking_company, ''))) LIKE ?", ['%USPS%']);
        } elseif ($carrierFilter === 'FEDEX') {
            $query->where(function ($q) {
                $q->whereRaw("UPPER(TRIM(COALESCE(tracking_company, ''))) LIKE ?", ['%FEDEX%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(tracking_company, ''))) LIKE ?", ['%FEDERAL EXPRESS%'])
                    ->orWhereRaw('tracking_number REGEXP ?', ['^[0-9]{12}$'])
                    ->orWhereRaw('tracking_number REGEXP ?', ['^96[0-9]{13}$']);
            });
        } else {
            $query->whereRaw("UPPER(TRIM(COALESCE(tracking_company, ''))) LIKE ?", ['%'.$carrierFilter.'%']);
        }
    }
}
