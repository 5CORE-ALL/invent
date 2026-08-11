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
     *   --limit=       Max tracking numbers this run
     *   --only-open    Skip Delivered/Expired
     *   --stale=       Skip numbers checked within the last N minutes
     *   --catch-up     Fast bulk mode for large backlogs (17TRACK, low sleep, import channel trackings)
     *   --prefer-native Force USPS/UPS/FedEx instead of 17TRACK
     *   --repair-quota Clear statuses poisoned by prior quota errors
     */
    protected $signature = 'tracking:sync-status
        {--limit= : Max distinct tracking numbers to process this run}
        {--only-open : Skip numbers already Delivered/Expired}
        {--stale= : Skip numbers checked within the last N minutes (omit for auto ~2 passes/day)}
        {--carrier= : Optional carrier filter (USPS, UPS, FEDEX, GOFO, ...)}
        {--prefer-native : Prefer native carrier APIs over 17TRACK}
        {--catch-up : Fast backlog mode (import channel trackings, larger batches, less sleep)}
        {--repair-quota : Reset NotFound rows that only contain quota/rate-limit error text}';

    protected $description = 'Fetch live shipment status from the tracking provider into carrier_tracking_statuses (channel trackings; no Shopify API)';

    public function handle(ShipmentTrackingService $tracking): int
    {
        if (! $tracking->isConfigured()) {
            $this->error('Tracking provider not configured. Set USPS / UPS credentials or TRACKING_API_KEY in .env.');
            Log::warning('tracking:sync-status aborted — no tracking provider configured');

            return self::FAILURE;
        }

        if (! Schema::hasTable('carrier_tracking_statuses')) {
            $this->warn('carrier_tracking_statuses table missing — run migrations.');

            return self::FAILURE;
        }

        $catchUp = (bool) $this->option('catch-up');
        if ($catchUp || $this->option('repair-quota')) {
            // Always import channel trackings before a catch-up / repair pass.
            $imported = $this->importChannelTrackingNumbers();
            $this->info("Imported/updated {$imported} channel tracking number(s) into carrier_tracking_statuses.");
        } else {
            // Light import so new Temu/etc trackings enter the queue without full catch-up.
            $this->importChannelTrackingNumbers(800);
        }

        if ($this->option('repair-quota')) {
            $repaired = $this->repairQuotaPoisonedRows();
            $this->info("Repaired {$repaired} quota-poisoned row(s).");
        }

        $cfg = config('services.tracking');
        $batchSize = max(1, (int) ($cfg['batch_size'] ?? 40));
        $sleepMs = max(0, (int) ($cfg['sleep_ms'] ?? 400));
        if ($catchUp) {
            $batchSize = max($batchSize, 40);
            $sleepMs = min($sleepMs, 80);
        }
        $carrierFilter = strtoupper(trim((string) $this->option('carrier')));
        $preferNative = (bool) $this->option('prefer-native');
        $onlyOpen = (bool) $this->option('only-open');

        $staleMin = $this->resolveStaleMinutes($onlyOpen, $carrierFilter, $catchUp);
        $maxPerRun = $this->resolveMaxPerRun($tracking, $onlyOpen, $staleMin, $carrierFilter, $catchUp);

        $query = DB::table('carrier_tracking_statuses')
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
            DB::raw('MAX(carrier) as carrier'),
            DB::raw('MAX(CASE WHEN shipment_status IS NULL OR shipment_status = \'\' THEN 1 ELSE 0 END) as needs_status')
        )
            ->groupBy('tracking_number')
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
        $this->info("Syncing {$total} tracking number(s) [{$mode}, stale≥{$staleMin}m, catch-up=".($catchUp ? 'yes' : 'no').", USPS remaining this hour: {$uspsLeft}]...");

        $updated = 0;
        $checked = 0;
        $skipped = 0;
        $errors = 0;
        $quotaStops = 0;
        $chunks = $rows->chunk($batchSize);
        $chunkTotal = $chunks->count();
        $chunkIndex = 0;

        if ($carrierFilter === 'GOFO' || $rows->contains(fn ($r) => str_contains(strtolower((string) $r->carrier), 'gofo'))) {
            $this->comment('  GOFO uses one API call per tracking number — large batches can take several minutes.');
        }

        foreach ($chunks as $chunk) {
            $chunkIndex++;
            $shipments = $chunk->map(fn ($r) => [
                'number' => $r->tracking_number,
                'carrier' => $r->carrier,
            ])->all();

            $this->line(sprintf(
                '  Batch %d/%d (%d number%s)…',
                $chunkIndex,
                $chunkTotal,
                count($shipments),
                count($shipments) === 1 ? '' : 's'
            ));

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
            $batchUpdated = 0;
            $persistByStatus = [];
            $rotateNumbers = [];

            foreach ($chunk as $r) {
                $num = (string) $r->tracking_number;
                $res = $results[$num] ?? null;
                // 17TRACK sometimes keys with different casing
                if ($res === null) {
                    foreach ($results as $k => $v) {
                        if (strcasecmp((string) $k, $num) === 0) {
                            $res = $v;
                            break;
                        }
                    }
                }
                $checked++;

                if (! ShipmentTrackingService::isPersistableResult($res)) {
                    $skipped++;
                    if (($res['status'] ?? null) === ShipmentTrackingService::STATUS_RATE_LIMITED
                        || ! empty($res['transient'])) {
                        $batchQuota++;
                        $rotateAt = $staleMin > 60
                            ? now()->subMinutes($staleMin - 60)
                            : now();
                        $rotateNumbers[$num] = $rotateAt;
                    }
                    continue;
                }

                $status = (string) $res['status'];
                $detail = $res['detail'] ?? null;
                $persistByStatus[$status][] = [
                    'number' => $num,
                    'detail' => $detail,
                ];
                $batchUpdated++;
            }

            foreach ($persistByStatus as $status => $items) {
                // Group identical detail strings for fewer queries.
                $byDetail = [];
                foreach ($items as $item) {
                    $d = (string) ($item['detail'] ?? '');
                    $byDetail[$d][] = $item['number'];
                }
                foreach ($byDetail as $detail => $numbers) {
                    $affected = DB::table('carrier_tracking_statuses')
                        ->whereIn('tracking_number', $numbers)
                        ->update([
                            'shipment_status' => $status,
                            'shipment_status_detail' => $detail !== '' ? $detail : null,
                            'shipment_checked_at' => $now,
                            'updated_at' => $now,
                        ]);
                    $updated += $affected;

                    // Mirror onto legacy shopify_raw_orders rows that already have this tracking#
                    // (display-only cache; no Shopify API).
                    if (Schema::hasTable('shopify_raw_orders')) {
                        try {
                            DB::table('shopify_raw_orders')
                                ->whereIn('tracking_number', $numbers)
                                ->update([
                                    'shipment_status' => $status,
                                    'shipment_status_detail' => $detail !== '' ? $detail : null,
                                    'shipment_checked_at' => $now,
                                    'updated_at' => $now,
                                ]);
                        } catch (\Throwable) {
                            // ignore mirror failures
                        }
                    }
                }
            }

            foreach ($rotateNumbers as $num => $rotateAt) {
                DB::table('carrier_tracking_statuses')
                    ->where('tracking_number', $num)
                    ->update([
                        'shipment_checked_at' => $rotateAt,
                        'updated_at' => $now,
                    ]);
            }

            $this->line(sprintf(
                '    → checked %d, rows updated %d, skipped %d',
                count($shipments),
                $batchUpdated,
                max(0, count($shipments) - $batchUpdated)
            ));

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
            'catch_up' => $catchUp,
            'prefer_native' => $preferNative,
        ]);

        return self::SUCCESS;
    }

    /**
     * Pull tracking numbers from channel order tables into carrier_tracking_statuses.
     */
    protected function importChannelTrackingNumbers(?int $limitPerSource = null): int
    {
        if (! Schema::hasTable('carrier_tracking_statuses')) {
            return 0;
        }

        $sources = [
            ['temu_orders', 'carrier'],
            ['temu2_orders', 'carrier'],
            ['purchasing_power_sales', 'carrier'], // may fall back to NULL carrier
            ['doba_daily_data', 'carrier_name'],
            ['shopify_raw_orders', 'tracking_company'], // legacy cache rows only — no Shopify API
        ];

        $total = 0;
        $now = now();

        foreach ($sources as [$table, $carrierCol]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tracking_number')) {
                continue;
            }
            $hasCarrier = Schema::hasColumn($table, $carrierCol);
            try {
                $q = DB::table($table)
                    ->whereNotNull('tracking_number')
                    ->where('tracking_number', '!=', '')
                    ->select('tracking_number')
                    ->selectRaw($hasCarrier
                        ? "MAX({$carrierCol}) as carrier"
                        : 'NULL as carrier')
                    ->groupBy('tracking_number');
                if ($limitPerSource !== null) {
                    $q->limit(max(1, $limitPerSource));
                }
                $rows = $q->get();
            } catch (\Throwable $e) {
                Log::warning('tracking:sync-status import failed', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            foreach ($rows->chunk(200) as $chunk) {
                $payload = [];
                foreach ($chunk as $r) {
                    $tn = trim((string) ($r->tracking_number ?? ''));
                    if ($tn === '' || strlen($tn) > 128) {
                        continue;
                    }
                    $carrier = trim((string) ($r->carrier ?? ''));
                    $payload[] = [
                        'tracking_number' => $tn,
                        'carrier' => $carrier !== '' ? mb_substr($carrier, 0, 128) : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($payload === []) {
                    continue;
                }
                try {
                    DB::table('carrier_tracking_statuses')->upsert(
                        $payload,
                        ['tracking_number'],
                        ['carrier', 'updated_at']
                    );
                    $total += count($payload);
                } catch (\Throwable $e) {
                    Log::warning('tracking:sync-status upsert failed', ['error' => $e->getMessage()]);
                }
            }
        }

        return $total;
    }

    protected function repairQuotaPoisonedRows(): int
    {
        $n = 0;
        if (Schema::hasTable('carrier_tracking_statuses')) {
            $n += (int) DB::table('carrier_tracking_statuses')
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

        if (Schema::hasTable('shopify_raw_orders')) {
            $n += (int) DB::table('shopify_raw_orders')
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

        return $n;
    }

    protected function resolveStaleMinutes(bool $onlyOpen, string $carrierFilter, bool $catchUp): int
    {
        $raw = $this->option('stale');
        if ($raw !== null && $raw !== '') {
            return max(0, (int) $raw);
        }
        if ($catchUp) {
            return 0;
        }

        $backlog = $this->countOpenNeedingStatus($onlyOpen, $carrierFilter);
        if ($backlog >= 100) {
            return 20 * 60;
        }

        $passes = max(1, (int) config('services.tracking.target_passes_per_day', 2));

        return (int) max(60, (int) floor((24 * 60) / $passes));
    }

    protected function resolveMaxPerRun(
        ShipmentTrackingService $tracking,
        bool $onlyOpen,
        int $staleMin,
        string $carrierFilter,
        bool $catchUp
    ): int {
        $default = $catchUp ? 2000 : (int) (config('services.tracking.max_per_run') ?? 200);
        $configured = (int) ($this->option('limit') ?: $default);
        $configured = max(1, $configured);

        if ($this->option('limit') !== null && $this->option('limit') !== '') {
            return min($configured, $catchUp ? 5000 : 500);
        }

        if ($catchUp) {
            return min($configured, 5000);
        }

        $preferAggregator = (bool) config('services.tracking.prefer_aggregator', true);
        $has17 = $tracking->has17Track();
        $uspsLeft = max(0, $tracking->uspsRemainingThisHour());

        if ($preferAggregator && $has17) {
            return min($configured, max($uspsLeft, 80), 200);
        }

        return min($configured, $uspsLeft + 40, 200);
    }

    protected function countOpenNeedingStatus(bool $onlyOpen, string $carrierFilter): int
    {
        if (! Schema::hasTable('carrier_tracking_statuses')) {
            return 0;
        }

        $q = DB::table('carrier_tracking_statuses')
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
                $q->whereRaw("UPPER(TRIM(COALESCE(carrier, ''))) LIKE ?", ['UPS%'])
                    ->orWhere('tracking_number', 'like', '1Z%');
            });
        } elseif ($carrierFilter === 'USPS') {
            $query->whereRaw("UPPER(TRIM(COALESCE(carrier, ''))) LIKE ?", ['%USPS%']);
        } elseif ($carrierFilter === 'FEDEX') {
            $query->where(function ($q) {
                $q->whereRaw("UPPER(TRIM(COALESCE(carrier, ''))) LIKE ?", ['%FEDEX%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(carrier, ''))) LIKE ?", ['%FEDERAL EXPRESS%'])
                    ->orWhereRaw('tracking_number REGEXP ?', ['^[0-9]{12}$'])
                    ->orWhereRaw('tracking_number REGEXP ?', ['^96[0-9]{13}$']);
            });
        } else {
            $query->whereRaw("UPPER(TRIM(COALESCE(carrier, ''))) LIKE ?", ['%'.$carrierFilter.'%']);
        }
    }
}
