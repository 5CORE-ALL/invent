<?php

namespace App\Console\Commands;

use App\Jobs\ImportTikTokOrderToShopify;
use App\Models\TiktokOrder;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\TikTokOrderSyncService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * mm-tiktok had 129,770 waiting ImportTikTokOrderToShopify jobs for 530 orders: the unique lock
 * (15 min) expired while jobs waited days, so every sync cycle queued the same order again.
 * The worker then spent days on copies and new orders never reached the front.
 *
 * Keeps ONE waiting job per order (and one of each repeat-safe housekeeping job), deletes the rest,
 * and takes the unique lock for the kept import jobs so the next sync cycle does not queue them again.
 * Never touches a job a worker has reserved. Dry run unless --apply is given.
 */
class DedupeTikTokImportQueue extends Command
{
    protected $signature = 'tiktok:dedupe-import-queue
        {--apply : Delete the duplicates (without it only counts them)}
        {--queue= : Queue to clean (default: the TikTok 1 order queue)}';

    protected $description = 'Remove duplicate waiting TikTok 1 Shopify-import jobs (keeps one per order)';

    /** Housekeeping jobs where identical waiting copies do the same work, so one is enough. */
    private const REPEAT_SAFE = [
        'App\Jobs\SyncTikTokAddressJob',
        'App\Jobs\WarmTikTokLiveListingsCache',
        'App\Jobs\RunMarketplaceInventorySyncJob',
        'App\Jobs\SyncMarketplaceOrdersJob',
    ];

    private const IMPORT_JOB = 'App\Jobs\ImportTikTokOrderToShopify';

    public function handle(): int
    {
        $queue = trim((string) $this->option('queue')) ?: MarketplaceManagerRegistry::queueFor('tiktok');
        $apply = (bool) $this->option('apply');

        $this->info("Reading waiting jobs on {$queue} ...");
        $jobs = $this->readJobs($queue);
        $this->info('Waiting jobs read: '.count($jobs));

        $rowIds = [];
        foreach ($jobs as $job) {
            if ($job['row'] !== null) {
                $rowIds[$job['row']] = true;
            }
        }
        $orderByRow = [];
        foreach (array_chunk(array_keys($rowIds), 1000) as $chunk) {
            foreach (TiktokOrder::query()->whereIn('id', $chunk)->pluck('order_id', 'id') as $id => $orderId) {
                $orderByRow[(int) $id] = (string) $orderId;
            }
        }

        $plan = self::plan($jobs, $orderByRow);

        $this->table(['Job', 'Waiting', 'Keep', 'Delete'], array_map(
            static fn (string $class, array $c) => [$class, $c['total'], $c['total'] - $c['delete'], $c['delete']],
            array_keys($plan['byClass']),
            array_values($plan['byClass'])
        ));
        $this->info('Duplicates to delete: '.count($plan['delete']).' | import jobs kept: '.count($plan['keptImportRows']));

        if (! $apply) {
            $this->warn('Dry run. Nothing deleted. Run again with --apply.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach (array_chunk($plan['delete'], 1000) as $chunk) {
            $deleted += (int) DB::table('jobs')
                ->where('queue', $queue)
                ->whereIn('id', $chunk)
                ->whereNull('reserved_at')
                ->delete();
        }
        $this->info("Deleted {$deleted} duplicate job(s).");

        // The kept import job holds the unique lock again, so a sync cycle cannot queue the order a second time.
        $locked = 0;
        $lock = new UniqueLock(app('cache.store'));
        foreach ($plan['keptImportRows'] as $rowId) {
            try {
                if ($lock->acquire(new ImportTikTokOrderToShopify((int) $rowId))) {
                    $locked++;
                }
            } catch (\Throwable $e) {
                $this->warn("Could not lock row {$rowId}: ".$e->getMessage());
            }
        }
        $this->info("Unique lock taken for {$locked} kept import job(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<array{id:int, class:string, row:?int, hash:string, reserved:bool}>
     */
    private function readJobs(string $queue): array
    {
        $jobs = [];
        DB::table('jobs')
            ->where('queue', $queue)
            ->select('id', 'payload', 'reserved_at')
            ->orderBy('id')
            ->chunkById(2000, function ($rows) use (&$jobs) {
                foreach ($rows as $row) {
                    $payload = (string) $row->payload;
                    $class = '';
                    if (preg_match('/"displayName":"([^"]+)"/', $payload, $m) === 1) {
                        $class = str_replace('\\\\', '\\', $m[1]);
                    }
                    $rowId = null;
                    $hash = '';
                    if ($class === self::IMPORT_JOB) {
                        $rowId = TikTokOrderSyncService::importRowIdFromPayload($payload);
                    } elseif (in_array($class, self::REPEAT_SAFE, true)) {
                        $decoded = json_decode($payload, true);
                        $hash = md5((string) ($decoded['data']['command'] ?? $payload));
                    }
                    $jobs[] = [
                        'id' => (int) $row->id,
                        'class' => $class,
                        'row' => $rowId,
                        'hash' => $hash,
                        'reserved' => $row->reserved_at !== null,
                    ];
                }
            }, 'id');

        return $jobs;
    }

    /**
     * Which waiting jobs are duplicates. Pure function so it can be tested without a queue.
     *
     * @param  list<array{id:int, class:string, row:?int, hash:string, reserved:bool}>  $jobs
     * @param  array<int, string>  $orderByRow  tiktok_orders.id => TikTok order id
     * @return array{delete: list<int>, byClass: array<string, array{total:int, delete:int}>, keptImportRows: list<int>}
     */
    public static function plan(array $jobs, array $orderByRow): array
    {
        $groups = [];
        $byClass = [];
        foreach ($jobs as $job) {
            $class = $job['class'] !== '' ? $job['class'] : '(unknown)';
            $byClass[$class] ??= ['total' => 0, 'delete' => 0];
            $byClass[$class]['total']++;

            if ($job['class'] === self::IMPORT_JOB && $job['row'] !== null) {
                $key = 'import|'.($orderByRow[$job['row']] ?? 'row:'.$job['row']);
            } elseif (in_array($job['class'], self::REPEAT_SAFE, true)) {
                $key = $job['class'].'|'.$job['hash'];
            } else {
                continue;
            }
            $groups[$key][] = $job;
        }

        $delete = [];
        $keptImportRows = [];
        foreach ($groups as $copies) {
            usort($copies, static fn ($a, $b) => $a['id'] <=> $b['id']);
            $keep = null;
            foreach ($copies as $copy) {
                if ($copy['reserved']) {
                    $keep = $copy;
                    break;
                }
            }
            $keep ??= $copies[0];

            foreach ($copies as $copy) {
                if ($copy['id'] === $keep['id'] || $copy['reserved']) {
                    continue;
                }
                $delete[] = $copy['id'];
                $byClass[$copy['class']]['delete']++;
            }
            if ($keep['class'] === self::IMPORT_JOB && $keep['row'] !== null) {
                $keptImportRows[] = $keep['row'];
            }
        }

        return ['delete' => $delete, 'byClass' => $byClass, 'keptImportRows' => $keptImportRows];
    }
}
