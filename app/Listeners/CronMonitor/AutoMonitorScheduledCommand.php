<?php

namespace App\Listeners\CronMonitor;

use App\Services\CronMonitor\CronMonitorService;
use App\Services\CronMonitor\ScheduledJobRegistry;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automatically monitors every artisan command scheduled in Kernel.php.
 *
 * Uses CommandStarting/Finished (not ScheduledTask*) so runInBackground()
 * jobs are tracked until they actually complete.
 *
 * Commands that use MonitoredCommand reuse the same context and upgrade
 * to rich metrics (fetched/updated/failed).
 */
class AutoMonitorScheduledCommand
{
    public function __construct(
        protected CronMonitorService $monitor,
        protected ScheduledJobRegistry $registry,
    ) {}

    public function handleStarting(CommandStarting $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $command = $this->commandName($event->command);
        if (! $command || ! $this->registry->isScheduledCommand($command)) {
            return;
        }

        // Already started (e.g. nested) — leave alone
        if ($this->monitor->context()) {
            return;
        }

        try {
            $ctx = $this->monitor->start(
                $this->registry->jobNameFor($command),
                $command
            );
            $ctx->mergeMeta([
                'auto' => true,
                'mode' => 'schedule',
            ]);
        } catch (Throwable $e) {
            Log::warning('[CronMonitor] Auto start failed: ' . $e->getMessage(), [
                'command' => $command,
            ]);
        }
    }

    public function handleFinished(CommandFinished $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $ctx = $this->monitor->context();
        if (! $ctx) {
            return;
        }

        // Rich MonitoredCommand already finished and cleared context
        if (empty($ctx->meta['auto'])) {
            return;
        }

        $command = $this->commandName($event->command);
        if ($command && $ctx->command && $command !== $ctx->command) {
            return;
        }

        try {
            $exitCode = (int) $event->exitCode;
            $ctx->mergeMeta(['exit_code' => $exitCode]);

            if ($exitCode !== 0) {
                $this->monitor->finish(new \RuntimeException(
                    "Artisan command exited with code {$exitCode}."
                ));

                return;
            }

            $this->monitor->finishBasic();
        } catch (Throwable $e) {
            Log::warning('[CronMonitor] Auto finish failed: ' . $e->getMessage());
        }
    }

    protected function enabled(): bool
    {
        return config('cron-monitor.enabled', true)
            && config('cron-monitor.auto_monitor.enabled', true);
    }

    protected function commandName(?string $command): ?string
    {
        if (! $command) {
            return null;
        }

        $command = preg_replace('/^(php\s+)?artisan\s+/i', '', trim($command)) ?? $command;

        return explode(' ', trim($command))[0] ?: null;
    }
}
