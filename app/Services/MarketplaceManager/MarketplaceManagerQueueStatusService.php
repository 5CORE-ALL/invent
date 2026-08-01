<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of per-marketplace queue + background work for MM UI.
 */
final class MarketplaceManagerQueueStatusService
{
    /** @var array<string, string> */
    private const JOB_LABELS = [
        'RunMarketplaceInventorySyncJob' => 'Full inventory sync (Shopify → marketplace)',
        'PushLinkedSkuInventoryFromShopify' => 'SKU inventory push (Shopify → marketplace)',
        'SyncInventoryToReverbManager' => 'Full inventory sync (Shopify → Reverb)',
        'SyncInventoryToAliexpress' => 'Full inventory sync (Shopify → AliExpress)',
        'SyncInventoryToAlibaba' => 'Full inventory sync (Shopify → Alibaba)',
        'SyncInventoryToNewegg' => 'Full inventory sync (Shopify → Newegg)',
        'SyncInventoryToShein' => 'Full inventory sync (Shopify → Shein)',
        'SyncInventoryToFaire' => 'Full inventory sync (Shopify → Faire)',
        'SyncInventoryToTopDawg' => 'Full inventory sync (Shopify → TopDawg)',
        'SyncInventoryToTemu' => 'Full inventory sync (Shopify → Temu)',
        'SyncInventoryToAmazon' => 'Full inventory sync (Shopify → Amazon)',
        'SyncInventoryToEbay2' => 'Full inventory sync (Shopify → eBay 2)',
        'SyncInventoryToEbay3' => 'Full inventory sync (Shopify → eBay 3)',
        'WarmReverbLiveListingsCache' => 'Warm live Reverb listings cache',
        'WarmAliexpressLiveListingsCache' => 'Warm live AliExpress listings cache',
        'WarmAlibabaLiveListingsCache' => 'Warm live Alibaba listings cache',
        'WarmNeweggLiveListingsCache' => 'Warm live Newegg listings cache',
        'WarmFaireLiveListingsCache' => 'Warm live Faire listings cache',
        'WarmTopDawgLiveListingsCache' => 'Warm live TopDawg listings cache',
        'WarmTemuLiveListingsCache' => 'Warm live Temu listings cache',
        'WarmAmazonLiveListingsCache' => 'Warm live Amazon listings cache',
        'WarmSheinLiveListingsCache' => 'Warm live Shein listings cache',
        'WarmEbay2LiveListingsCache' => 'Warm live eBay 2 listings cache',
        'WarmEbay3LiveListingsCache' => 'Warm live eBay 3 listings cache',
        'ImportReverbManagerOrderToShopify' => 'Import order → Shopify',
        'ImportAliexpressOrderToShopify' => 'Import order → Shopify',
        'ImportAlibabaOrderToShopify' => 'Import order → Shopify',
        'ImportNeweggOrderToShopify' => 'Import order → Shopify',
        'ImportSheinOrderToShopify' => 'Import order → Shopify',
        'ImportFaireOrderToShopify' => 'Import order → Shopify',
        'ImportTopDawgOrderToShopify' => 'Import order → Shopify',
        'ImportTemuOrderToShopify' => 'Import order → Shopify',
        'ImportAmazonOrderToShopify' => 'Import order → Shopify',
        'ImportEbay2OrderToShopify' => 'Import order → Shopify',
        'ImportEbay3OrderToShopify' => 'Import order → Shopify',
        'SyncMarketplaceOrdersJob' => 'Fetch orders from marketplace',
    ];

    /** @var array<string, class-string> */
    private const LINK_MAP_SERVICES = [
        'reverb' => ReverbLinkMapSyncService::class,
        'aliexpress' => AliexpressLinkMapSyncService::class,
        'alibaba' => AlibabaLinkMapSyncService::class,
        'newegg' => NeweggLinkMapSyncService::class,
        'shein' => SheinLinkMapSyncService::class,
        'topdawg' => TopDawgLinkMapSyncService::class,
        'temu' => TemuLinkMapSyncService::class,
        'amazon' => AmazonLinkMapSyncService::class,
        'ebay2' => Ebay2LinkMapSyncService::class,
        'ebay3' => Ebay3LinkMapSyncService::class,
        'faire' => FaireLinkMapSyncService::class,
    ];

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?string $marketplace = null): array
    {
        $slug = $marketplace !== null ? strtolower(trim($marketplace)) : null;
        $queue = $slug ? MarketplaceManagerRegistry::queueFor($slug) : MarketplaceManagerRegistry::QUEUE;
        // Only this marketplace's dedicated queue — do not mix legacy shared failures into the UI.
        $queues = [$queue];

        $queueRows = $this->queueRows($queues);
        $classified = $this->classifyRows($queueRows);

        $label = $slug ? (MarketplaceManagerRegistry::find($slug)['label'] ?? $slug) : 'Marketplace';

        return [
            'queue' => $queue,
            'marketplace' => $slug,
            'marketplace_label' => $label,
            'worker' => $this->workerStatus($classified, $queue, $label),
            'counts' => [
                'waiting' => $classified['waiting'],
                'running' => $classified['running'],
                'delayed' => $classified['delayed'],
                'failed_recent' => $this->failedCount($queues),
            ],
            'now_running' => $classified['now_running'],
            'ready' => $classified['ready'],
            'delayed_jobs' => $classified['delayed_jobs'],
            'tasks' => $classified['tasks'],
            'link_map' => $slug ? $this->linkMapProgress($slug) : null,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<string>  $queues
     * @return list<object>
     */
    private function queueRows(array $queues): array
    {
        if (! Schema::hasTable('jobs') || $queues === []) {
            return [];
        }

        return DB::table('jobs')
            ->whereIn('queue', $queues)
            ->orderBy('id')
            ->get(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])
            ->all();
    }

    /**
     * @param  list<object>  $rows
     * @return array{
     *   waiting: int,
     *   running: int,
     *   delayed: int,
     *   now_running: list<array<string, mixed>>,
     *   ready: list<array<string, mixed>>,
     *   delayed_jobs: list<array<string, mixed>>,
     *   tasks: list<array<string, mixed>>
     * }
     */
    private function classifyRows(array $rows): array
    {
        $now = now()->getTimestamp();
        $waiting = 0;
        $running = 0;
        $delayed = 0;
        $nowRunning = [];
        $ready = [];
        $delayedJobs = [];
        $taskCounts = [];

        foreach ($rows as $row) {
            $payload = (string) ($row->payload ?? '');
            $shortName = $this->shortJobName($payload);
            $label = self::JOB_LABELS[$shortName] ?? $this->humanizeJob($shortName);
            $isRunning = $row->reserved_at !== null;
            $availableTs = is_numeric($row->available_at)
                ? (int) $row->available_at
                : (int) strtotime((string) $row->available_at);
            $isDelayed = ! $isRunning && $availableTs > $now;
            $delaySeconds = $isDelayed ? max(0, $availableTs - $now) : 0;

            $item = [
                'id' => (int) $row->id,
                'job' => $shortName,
                'label' => $label,
                'attempts' => (int) ($row->attempts ?? 0),
                'delay_seconds' => $delaySeconds,
                'delay_human' => $this->humanDelay($delaySeconds),
            ];

            if ($isRunning) {
                $running++;
                $status = 'running';
                if (count($nowRunning) < 5) {
                    $nowRunning[] = $item;
                }
            } elseif ($isDelayed) {
                $delayed++;
                $status = 'delayed';
                if (count($delayedJobs) < 8) {
                    $delayedJobs[] = $item;
                }
            } else {
                $waiting++;
                $status = 'waiting';
                if (count($ready) < 8) {
                    $ready[] = $item;
                }
            }

            $key = $shortName.'|'.$status;
            if (! isset($taskCounts[$key])) {
                $taskCounts[$key] = [
                    'job' => $shortName,
                    'label' => $label,
                    'status' => $status,
                    'count' => 0,
                ];
            }
            $taskCounts[$key]['count']++;
        }

        $tasks = array_values($taskCounts);
        usort($tasks, static function (array $a, array $b): int {
            $order = ['running' => 0, 'waiting' => 1, 'delayed' => 2];
            $cmp = ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9);

            return $cmp !== 0 ? $cmp : strcmp($a['label'], $b['label']);
        });

        return [
            'waiting' => $waiting,
            'running' => $running,
            'delayed' => $delayed,
            'now_running' => $nowRunning,
            'ready' => $ready,
            'delayed_jobs' => $delayedJobs,
            'tasks' => array_slice($tasks, 0, 12),
        ];
    }

    /**
     * @param  array{waiting: int, running: int, delayed: int, now_running: list<array<string, mixed>>}  $classified
     * @return array{state: string, message: string, log_age_seconds: ?int}
     */
    private function workerStatus(array $classified, string $primaryQueue, string $label): array
    {
        $logPath = storage_path('logs/mm-worker-'.$primaryQueue.'.log');
        if (! is_file($logPath)) {
            $logPath = storage_path('logs/marketplace-manager-worker.log');
        }
        $logAge = is_file($logPath) ? max(0, time() - (int) filemtime($logPath)) : null;
        $pending = $classified['waiting'] + $classified['delayed'];
        $running = $classified['running'];

        if ($running > 0) {
            $current = $classified['now_running'][0]['label'] ?? 'a job';
            $msg = "Now running: {$current}";
            if ($classified['waiting'] > 0) {
                $msg .= ". {$classified['waiting']} more ready next.";
            }
            if ($classified['delayed'] > 0) {
                $msg .= " {$classified['delayed']} delayed (retry later).";
            }

            return ['state' => 'running', 'message' => $msg, 'log_age_seconds' => $logAge];
        }

        if ($pending > 0) {
            // Delayed-only = worker is healthy, jobs are waiting on retry backoff (not stalled).
            if ($classified['delayed'] > 0 && $classified['waiting'] === 0 && $running === 0) {
                return [
                    'state' => 'backlogged',
                    'message' => "{$classified['delayed']} {$label} job(s) delayed for retry (worker idle until then).",
                    'log_age_seconds' => $logAge,
                ];
            }

            if ($logAge !== null && $logAge > 900) {
                return [
                    'state' => 'stalled',
                    'message' => "{$pending} {$label} job(s) waiting but worker looks stalled. Auto-restart should recover within a few minutes.",
                    'log_age_seconds' => $logAge,
                ];
            }

            if ($classified['waiting'] > 0 && $classified['delayed'] > 0) {
                $msg = "{$classified['waiting']} ready + {$classified['delayed']} delayed on {$label}.";
            } elseif ($classified['delayed'] > 0) {
                $msg = "{$classified['delayed']} delayed job(s) on {$label} (will retry soon).";
            } else {
                $msg = "{$classified['waiting']} job(s) ready on {$label} — worker should pick them up now.";
            }

            return [
                'state' => 'backlogged',
                'message' => $msg,
                'log_age_seconds' => $logAge,
            ];
        }

        return [
            'state' => 'idle',
            'message' => "{$label} queue idle — no inventory/order jobs right now.",
            'log_age_seconds' => $logAge,
        ];
    }

    /**
     * @param  list<string>  $queues
     */
    private function failedCount(array $queues): int
    {
        if (! Schema::hasTable('failed_jobs') || $queues === []) {
            return 0;
        }

        return (int) DB::table('failed_jobs')
            ->whereIn('queue', $queues)
            ->where('failed_at', '>=', now()->subDay())
            ->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function linkMapProgress(string $slug): ?array
    {
        $serviceClass = self::LINK_MAP_SERVICES[$slug] ?? null;
        if ($serviceClass === null) {
            return null;
        }

        $progress = app($serviceClass)->getProgress();
        if ($progress === []) {
            return null;
        }

        $running = (bool) ($progress['running'] ?? false);
        $done = (bool) ($progress['done'] ?? false);
        $error = (bool) ($progress['error'] ?? false);
        $page = (int) ($progress['page'] ?? 0);

        // Only show when a link-map sync is actually active (or failed mid-run).
        if (! $running && ! $error) {
            return null;
        }
        if (! $running && $done) {
            return null;
        }
        if (! $running && $page < 1) {
            return null;
        }

        return [
            'running' => $running,
            'done' => $done,
            'error' => $error,
            'page' => $page,
            'total_page' => isset($progress['total_page']) ? (int) $progress['total_page'] : null,
            'total_upserted' => (int) ($progress['total_upserted'] ?? 0),
            'message' => (string) ($progress['message'] ?? ''),
        ];
    }

    private function shortJobName(string $payload): string
    {
        if (preg_match('/"displayName":"([^"]+)"/', $payload, $m)) {
            $name = str_replace('\\\\', '\\', $m[1]);

            return basename(str_replace('\\', '/', $name));
        }

        return 'UnknownJob';
    }

    private function humanizeJob(string $shortName): string
    {
        return trim(preg_replace('/([a-z])([A-Z])/', '$1 $2', $shortName) ?? $shortName);
    }

    private function humanDelay(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'now';
        }
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return (int) ceil($seconds / 60).'m';
        }

        return (int) ceil($seconds / 3600).'h';
    }
}
