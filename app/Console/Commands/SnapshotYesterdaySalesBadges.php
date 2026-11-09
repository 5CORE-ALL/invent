<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Models\BadgeDataHistory;
use App\Services\CronMonitor\CronExecutionContext;
use App\Support\Badges\YesterdayBadgeRegistry;
use Illuminate\Console\Command;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;

/**
 * Save today's "Y Sales" badge value for every server-rendered sales page in YesterdayBadgeRegistry, so each badge's
 * trend dot and history chart fill even on days nobody opens the page. Each page's own controller method is called and
 * the same view variable the badge prints is read (no duplicate sales math).
 */
class SnapshotYesterdaySalesBadges extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'app:snapshot-yesterday-sales-badges {--page= : Only this registry page key}';

    protected $description = 'Store daily Y Sales badge snapshot for every sales page (trend dots / history charts)';

    protected string $monitorJobName = 'Yesterday Sales Badge Snapshot';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeSnapshot($m),
            $this->monitorJobName
        );
    }

    protected function executeSnapshot(CronExecutionContext $monitor): int
    {
        $monitor->startFresh()->markLocalOnly();

        $only = trim((string) $this->option('page'));
        $targets = [];
        foreach (YesterdayBadgeRegistry::pages() as $page => $def) {
            if (empty($def['controller'])) {
                continue; // browser-computed badge: saved when the page is opened
            }
            if ($only !== '' && $only !== $page) {
                continue;
            }
            $targets[$page] = $def;
        }

        $monitor->setExpected(count($targets));
        $monitor->setFetched(count($targets));
        $saved = 0;
        $failed = 0;

        foreach ($targets as $page => $def) {
            try {
                $result = app($def['controller'])->{$def['method']}();
                $data = $result instanceof View ? $result->getData() : [];
                $value = $data[$def['key']] ?? null;
                if (! is_numeric($value)) {
                    throw new \RuntimeException('no numeric "' . $def['key'] . '" in view data');
                }
                BadgeDataHistory::recordPage(YesterdayBadgeRegistry::storageName($page), ['y_sales' => round((float) $value, 2)]);
                $saved++;
                $this->line(sprintf('  %-26s $%s', $page, number_format((float) $value, 2)));
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Yesterday sales badge snapshot failed for ' . $page . ': ' . $e->getMessage());
                $this->warn(sprintf('  %-26s FAILED: %s', $page, $e->getMessage()));
            }
        }

        $monitor->setProcessed($saved);
        $monitor->setUpdated($saved);
        $monitor->setFailed($failed);
        $this->info("Saved {$saved} page(s), {$failed} failed.");

        return $saved > 0 || $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
