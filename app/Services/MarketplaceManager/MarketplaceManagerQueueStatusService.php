<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of marketplace-manager queue + per-channel background work for MM UI.
 */
final class MarketplaceManagerQueueStatusService
{
    private const QUEUE = MarketplaceManagerRegistry::QUEUE;

    /** @var array<string, string> */
    private const JOB_LABELS = [
        'RunMarketplaceInventorySyncJob' => 'Full inventory sync',
        'PushLinkedSkuInventoryFromShopify' => 'SKU inventory push',
        'SyncInventoryToReverbManager' => 'Scheduled Reverb inventory sync',
        'SyncInventoryToAliexpress' => 'Scheduled AliExpress inventory sync',
        'SyncInventoryToAlibaba' => 'Scheduled Alibaba inventory sync',
        'SyncInventoryToNewegg' => 'Scheduled Newegg inventory sync',
        'WarmReverbLiveListingsCache' => 'Warm live Reverb listings',
        'WarmAliexpressLiveListingsCache' => 'Warm live AliExpress listings',
        'WarmNeweggLiveListingsCache' => 'Warm live Newegg listings',
        'ImportReverbManagerOrderToShopify' => 'Import Reverb order → Shopify',
        'ImportAliexpressOrderToShopify' => 'Import AliExpress order → Shopify',
        'ImportAlibabaOrderToShopify' => 'Import Alibaba order → Shopify',
        'ImportNeweggOrderToShopify' => 'Import Newegg order → Shopify',
    ];

    /** @var array<string, class-string> */
    private const LINK_MAP_SERVICES = [
        'reverb' => ReverbLinkMapSyncService::class,
        'aliexpress' => AliexpressLinkMapSyncService::class,
        'alibaba' => AlibabaLinkMapSyncService::class,
        'newegg' => NeweggLinkMapSyncService::class,
    ];

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?string $marketplace = null): array
    {
        $slug = $marketplace !== null ? strtolower(trim($marketplace)) : null;
        $queueRows = $this->queueRows();
        $classified = $this->classifyRows($queueRows, $slug);

        return [
            'queue' => self::QUEUE,
            'worker' => $this->workerStatus($classified),
            'counts' => [
                'waiting' => $classified['waiting'],
                'running' => $classified['running'],
                'delayed' => $classified['delayed'],
                'failed_recent' => $this->failedCount(),
            ],
            'tasks' => $classified['tasks'],
            'link_map' => $slug ? $this->linkMapProgress($slug) : null,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<object>
     */
    private function queueRows(): array
    {
        if (! Schema::hasTable('jobs')) {
            return [];
        }

        return DB::table('jobs')
            ->where('queue', self::QUEUE)
            ->orderBy('id')
            ->get(['id', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])
            ->all();
    }

    /**
     * @param  list<object>  $rows
     * @return array{waiting: int, running: int, delayed: int, tasks: list<array<string, mixed>>}
     */
    private function classifyRows(array $rows, ?string $slug): array
    {
        $now = now()->getTimestamp();
        $waiting = 0;
        $running = 0;
        $delayed = 0;
        $tasks = [];
        $taskCounts = [];

        foreach ($rows as $row) {
            $payload = (string) ($row->payload ?? '');
            $shortName = $this->shortJobName($payload);
            $isRunning = $row->reserved_at !== null;
            $isDelayed = ! $isRunning && strtotime((string) $row->available_at) > $now;

            if ($isRunning) {
                $running++;
            } elseif ($isDelayed) {
                $delayed++;
            } else {
                $waiting++;
            }

            if ($slug !== null && ! $this->jobRelatesToMarketplace($shortName, $payload, $slug)) {
                continue;
            }

            $status = $isRunning ? 'running' : ($isDelayed ? 'delayed' : 'waiting');
            $key = $shortName.'|'.$status;
            if (! isset($taskCounts[$key])) {
                $taskCounts[$key] = [
                    'job' => $shortName,
                    'label' => self::JOB_LABELS[$shortName] ?? $shortName,
                    'status' => $status,
                    'count' => 0,
                ];
            }
            $taskCounts[$key]['count']++;
        }

        foreach ($taskCounts as $task) {
            $tasks[] = $task;
        }

        usort($tasks, static function (array $a, array $b): int {
            $order = ['running' => 0, 'waiting' => 1, 'delayed' => 2];
            $cmp = ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9);

            return $cmp !== 0 ? $cmp : strcmp($a['label'], $b['label']);
        });

        return [
            'waiting' => $waiting,
            'running' => $running,
            'delayed' => $delayed,
            'tasks' => array_slice($tasks, 0, 12),
        ];
    }

    /**
     * @param  array{waiting: int, running: int, delayed: int, tasks: list<array<string, mixed>>}  $classified
     * @return array{state: string, message: string, log_age_seconds: ?int}
     */
    private function workerStatus(array $classified): array
    {
        $logPath = storage_path('logs/marketplace-manager-worker.log');
        $logAge = is_file($logPath) ? max(0, time() - (int) filemtime($logPath)) : null;
        $pending = $classified['waiting'] + $classified['delayed'];
        $running = $classified['running'];

        if ($running > 0) {
            $msg = $running === 1
                ? 'Worker is processing 1 job.'
                : "Worker is processing {$running} job(s).";

            if ($pending > 0) {
                $msg .= " {$pending} more waiting.";
            }

            return ['state' => 'running', 'message' => $msg, 'log_age_seconds' => $logAge];
        }

        if ($pending > 0) {
            if ($logAge !== null && $logAge > 900) {
                return [
                    'state' => 'stalled',
                    'message' => "{$pending} job(s) waiting but worker log is stale (".round($logAge / 60)."m). Worker may be stopped.",
                    'log_age_seconds' => $logAge,
                ];
            }

            return [
                'state' => 'backlogged',
                'message' => "{$pending} job(s) waiting in queue.",
                'log_age_seconds' => $logAge,
            ];
        }

        if ($logAge !== null && $logAge < 600) {
            return ['state' => 'idle', 'message' => 'Queue idle. Worker recently active.', 'log_age_seconds' => $logAge];
        }

        return ['state' => 'idle', 'message' => 'Queue idle.', 'log_age_seconds' => $logAge];
    }

    private function failedCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')
            ->where('queue', self::QUEUE)
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

        if (! $running && $done && ! $error) {
            return null;
        }

        return [
            'running' => $running,
            'done' => $done,
            'error' => $error,
            'page' => (int) ($progress['page'] ?? 0),
            'total_page' => isset($progress['total_page']) ? (int) $progress['total_page'] : null,
            'total_upserted' => (int) ($progress['total_upserted'] ?? 0),
            'message' => (string) ($progress['message'] ?? ''),
        ];
    }

    private function shortJobName(string $payload): string
    {
        if (preg_match('/"displayName":"(?:App\\\\\\\\Jobs\\\\\\\\)?([^"\\\\]+)"/', $payload, $m)) {
            return $m[1];
        }

        return 'UnknownJob';
    }

    private function jobRelatesToMarketplace(string $shortName, string $payload, string $slug): bool
    {
        if (str_contains(strtolower($payload), '"'.$slug.'"') || str_contains(strtolower($payload), ';s:'.strlen($slug).':"'.$slug.'"')) {
            return true;
        }

        $globalJobs = [
            'SyncInventoryToAliexpress',
            'SyncInventoryToAlibaba',
            'SyncInventoryToNewegg',
            'SyncInventoryToReverbManager',
            'PushLinkedSkuInventoryFromShopify',
        ];

        if ($slug === 'reverb' && in_array($shortName, ['WarmReverbLiveListingsCache', 'ImportReverbManagerOrderToShopify', 'SyncInventoryToReverbManager'], true)) {
            return true;
        }
        if ($slug === 'aliexpress' && in_array($shortName, ['WarmAliexpressLiveListingsCache', 'ImportAliexpressOrderToShopify', 'SyncInventoryToAliexpress'], true)) {
            return true;
        }
        if ($slug === 'alibaba' && in_array($shortName, ['ImportAlibabaOrderToShopify', 'SyncInventoryToAlibaba'], true)) {
            return true;
        }
        if ($slug === 'newegg' && in_array($shortName, ['WarmNeweggLiveListingsCache', 'ImportNeweggOrderToShopify', 'SyncInventoryToNewegg'], true)) {
            return true;
        }

        if (in_array($shortName, $globalJobs, true)) {
            return $slug === 'reverb';
        }

        return false;
    }
}
