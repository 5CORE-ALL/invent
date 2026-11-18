<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\WayfairStatusReconciler;
use App\Services\MarketplaceManager\WayfairTrackingSyncService;
use Illuminate\Console\Command;

class ReconcileWayfairStatusCommand extends Command
{
    protected $signature = 'wayfair:reconcile-status
        {--report : Read-only breakdown of why open/accepted rows cannot be moved on (no changes)}
        {--po=* : With --report, show detail for these PO numbers}
        {--fill-labels : First look up Wayfair label tracking for open/accepted POs that have none (calls Wayfair, up to ~10 minutes)}
        {--dry-run : Only report what would change}
        {--close-older-than=0 : Also mark open/accepted POs older than N days (with no carrier answer) as closed}';

    protected $description = 'Move Wayfair POs stuck on open/accepted to delivered/shipped/closed from the carrier tracking status (no Wayfair API call)';

    public function handle(WayfairStatusReconciler $reconciler): int
    {
        if ($this->option('report')) {
            return $this->printReport($reconciler);
        }

        $dry = (bool) $this->option('dry-run');
        if ($this->option('fill-labels') && ! $dry) {
            $sync = app(WayfairTrackingSyncService::class);
            $deadline = microtime(true) + 600;
            $checked = 0;
            $found = 0;
            for ($i = 0; $i < 12 && microtime(true) < $deadline; $i++) {
                $r = $sync->fillMissingLabelTracking(50, 120, $deadline);
                $checked += $r['checked'];
                $found += $r['found'];
                $this->line("  label lookup batch ".($i + 1).": checked {$r['checked']}, found {$r['found']}");
                if ($r['checked'] === 0) {
                    break;
                }
            }
            $this->info("Wayfair labels: checked {$checked} POs, tracking found for {$found}. Run tracking:sync-status next so the carrier status is fetched.");
        }

        $stats = $reconciler->reconcile(
            null,
            null,
            null,
            max(0, (int) $this->option('close-older-than')),
            $dry,
        );

        $this->info(($dry ? '[dry run] ' : '').sprintf(
            'Checked %d open/accepted rows: delivered %d, shipped %d, closed %d.',
            $stats['checked'],
            $stats['delivered'],
            $stats['shipped'],
            $stats['closed']
        ));
        foreach ($stats['samples'] as $status => $pos) {
            $this->line("  {$status}: ".implode(', ', $pos));
        }
        $this->line('The "Wayfair no longer lists it" rule runs inside wayfair:daily (php artisan wayfair:daily --days=60).');

        return self::SUCCESS;
    }

    protected function printReport(WayfairStatusReconciler $reconciler): int
    {
        $pos = [];
        foreach ((array) $this->option('po') as $value) {
            foreach (explode(',', (string) $value) as $po) {
                if (trim($po) !== '') {
                    $pos[] = trim($po);
                }
            }
        }
        $r = $reconciler->report($pos);
        $this->info("Open/accepted rows: {$r['rows']} ({$r['pos']} POs), PO dates {$r['oldest']} to {$r['newest']}");
        $this->line("  with a tracking number: {$r['with_tracking']}");
        $this->line("    carrier status not fetched yet: {$r['no_carrier_status']}");
        foreach ($r['by_carrier_status'] as $status => $count) {
            $this->line("    carrier status {$status}: {$count}");
        }
        $this->line("  NO tracking number saved: {$r['no_tracking']}");
        foreach ($r['detail'] as $d) {
            $this->line('  '.json_encode($d));
        }
        if ($pos !== [] && $r['detail'] === []) {
            $this->warn('None of those POs is currently open/accepted in wayfair_daily_data.');
        }

        return self::SUCCESS;
    }
}
