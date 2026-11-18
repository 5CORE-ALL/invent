<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\WayfairStatusReconciler;
use Illuminate\Console\Command;

class ReconcileWayfairStatusCommand extends Command
{
    protected $signature = 'wayfair:reconcile-status
        {--dry-run : Only report what would change}
        {--close-older-than=0 : Also mark open/accepted POs older than N days (with no carrier answer) as closed}';

    protected $description = 'Move Wayfair POs stuck on open/accepted to delivered/shipped/closed from the carrier tracking status (no Wayfair API call)';

    public function handle(WayfairStatusReconciler $reconciler): int
    {
        $dry = (bool) $this->option('dry-run');
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
}
