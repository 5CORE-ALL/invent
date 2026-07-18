# Cron Monitor — Self-Healing Framework

Production monitoring for Laravel scheduled artisan commands. Extends the existing Cron Monitor dashboard without replacing auto-Kernel coverage or Task Manager alerts.

## Architecture

```
Kernel schedule
    ├─ AutoMonitorScheduledCommand (CommandStarting/Finished)
    │     → exit-code health for ~all scheduled artisan commands
    └─ MonitoredCommand / MonitorsCronExecution (opt-in rich mode)
          → metrics, checkpoints, intelligent retry, self-healing

CronMonitorService
    ├─ FailureClassifier
    ├─ IntelligentRetryService (+ backoff)
    ├─ CheckpointService
    ├─ DuplicateLockService (Cache::lock)
    ├─ SelfHealingService (DB reconnect, queue watchdog, pluggable)
    ├─ StuckJobDetector / CronWatchdogService
    ├─ HistoricalAnalysisService
    ├─ AlertGroupingService → Task Manager / mail / WhatsApp
    └─ ManualActionService (dashboard actions)
```

## Folder structure

```
app/
  Console/Commands/
    MonitoredCommand.php
    Concerns/MonitorsCronExecution.php
    CronMonitor*.php
  Services/CronMonitor/
    Healers/
  Http/Controllers/CronMonitor/
  Jobs/CronMonitor/
  Models/CronExecution*.php, CronAlertBatch.php, CronMonitorAlert.php
  Repositories/CronExecutionLogRepository.php
config/cron-monitor.php
resources/views/cron-monitor/
database/migrations/*cron*
docs/cron-monitor.md
```

## Database

| Table | Purpose |
|-------|---------|
| `cron_execution_logs` | Per-run metrics + root cause, recovery, checkpoint, retries |
| `cron_execution_failures` | Per-record failures with category / recoverable |
| `cron_execution_checkpoints` | Resume cursors per job |
| `cron_monitor_alerts` | Individual alerts |
| `cron_alert_batches` | Grouped smart alerts |

Migrations:

1. `2026_07_18_020000_create_cron_monitoring_tables.php`
2. `2026_07_18_220000_upgrade_cron_monitor_self_healing.php`

## Config highlights (`config/cron-monitor.php`)

- `retry.max_attempts`, `retry.retry_delay` (1→30s, 2→120s, 3→300s)
- `retry.recoverable_http` / categories
- `locks.enabled`, `locks.ttl_seconds`
- `stuck.multiplier`, `stuck.min_expected_seconds`
- `self_healing.db_reconnect`, `self_healing.queue_watchdog`
- `alerts.group_window_minutes` (default 15)
- `health_score` weights (started/api/fetched/updated/validation/retry/runtime/historical)
- `notifications.channels` = `taskmanager,database,mail,whatsapp`

## Integrate a new Artisan command

```php
use App\Console\Commands\MonitoredCommand;
use App\Services\CronMonitor\CronExecutionContext;

class AmazonBidSync extends MonitoredCommand
{
    protected $signature = 'amazon:bid-sync';
    protected string $monitorJobName = 'Amazon Bid Sync';

    protected function executeJob(CronExecutionContext $m): int
    {
        $offset = $m->resumeOffset();
        $m->markApiConnected();
        $m->setExpected(20000);

        // ... fetch ...
        $m->setFetched(20000);

        for ($i = $offset; $i < 20000; $i++) {
            try {
                // update product $i
                $m->incrementProcessed()->incrementUpdated();
            } catch (\Throwable $e) {
                $m->classifyAndRecord($e, sku: (string) $i, marketplace: 'amazon');
            }

            if ($i % 100 === 0) {
                $m->checkpoint(['index' => $i], $i);
            }
        }

        return self::SUCCESS;
    }
}
```

Monitoring (lock, retries, heal, checkpoints, dashboard, alerts) is automatic.

Register marketplace token refresh by implementing `HealerInterface` and calling:

```php
app(\App\Services\CronMonitor\SelfHealingService::class)
    ->register(new AmazonTokenRefreshHealer());
```

## Chunked updates (large bid / budget / DB syncs)

Default chunk size is **50**. After every chunk: commit (DB path), checkpoint, sync dashboard meta (`chunk_progress`).

```bash
php artisan amazon:auto-update-over-kw-bids --dry-run --chunk=25
php artisan amazon:auto-update-under-kw-bids --chunk=50
```

Env / config:

- `CRON_MONITOR_CHUNK_SIZE=50`
- `CRON_MONITOR_CHECKPOINT_EVERY=1`
- `CRON_MONITOR_CHUNK_DB_TX=true` (per-chunk `DB::transaction` for `processQueryById`)
- `CRON_MONITOR_DASHBOARD_JOBS_PER_PAGE=50`

### API id→value maps

```php
use App\Console\Commands\Concerns\PushesAmazonAdsUpdatesInChunks;

$stats = $this->pushAmazonAdsIdMapInChunks(
    $monitor,
    $idToBidMap,
    fn (array $ids, array $bids) => $controller->updateAutoCampaignKeywordsBid($ids, $bids)
);
```

### DB updates (`chunkById`)

```php
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;

$this->processQueryInChunks(
    $monitor,
    ProductMaster::query()->orderBy('id'),
    function ($rows, int $chunkIndex, $lastId) {
        // mutate $rows — same business rules as before
        return ['updated' => $rows->count(), 'failed' => 0, 'processed' => $rows->count()];
    }
);
```

Resume uses checkpoint `last_id` (DB) or offset (API maps). Failed chunks are listed in `meta.failed_chunks` for failed-only retry.

Dashboard/show display: Total/Completed/Failed/Current chunks, Resume Point, Avg Chunk Time, ETA, Retry Count, Memory.

### Skip chunking when
- Utility/control-plane commands (`cron-monitor:*`, queue watchdog, logout, token generators)
- Tiny one-shot auth/test commands
- Filter phases that require full in-memory parent/child aggregation (chunk the **write/API** phase instead)

## Dashboard

URL: `/cron-monitor`

Job status table is **paginated** (batched last-success query — no N+1).

Actions per run: Retry Job, Resume, Retry Failed Records, Cancel, Unlock, Download Log.

## Artisan helpers

```bash
php artisan cron-monitor:jobs
php artisan cron-monitor:watchdog
php artisan cron-monitor:demo --checkpoint --fail-rate=10
php artisan cron-monitor:demo --simulate-timeout
php artisan cron-monitor:unlock "amazon:auto-update-over-kw-bids"
php artisan cron-monitor:cancel {id}
php artisan cron-monitor:retry "Cron Monitor Demo" --dry-run
php artisan cron-monitor:cleanup
```

## Deploy

1. `php artisan migrate`
2. `php artisan config:clear`
3. Ensure queue worker is running (grouped alerts + retry jobs)
4. Optional `.env`:
   - `CRON_MONITOR_CHANNELS=taskmanager,database`
   - `CRON_MONITOR_MAIL_TO=...`
   - `CRON_MONITOR_MAX_RETRY=3`
5. Open `/cron-monitor`

## Kernel jobs to upgrade for rich self-healing

Auto-monitoring already covers exit-code health for all Kernel artisan commands (`php artisan cron-monitor:jobs`).

Prioritize upgrading these to `MonitoredCommand` + checkpoints for resume / per-record retry:

- `amazon:auto-update-over-kw-bids` / under / pt / hl (+ FBA variants)
- `amazon:auto-update-amz-bgt-kw` / pt / hl
- `ebay:auto-update-over-bids` / under / ebay2 / ebay3 utilized
- `sync:amazon-prices`, `sync:tiktok-sheet-data`, `sync:walmart-metrics-data`
- `app:fetch-amazon-orders`, `amazon:sync-products`
- `channel:calculate-data`
- `stock:update-mapping-daily`
- Google Ads / Meta ads sync commands

Run `php artisan cron-monitor:jobs` for the full discovered list.
