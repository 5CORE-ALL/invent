<?php

namespace App\Console\Commands;

use App\Services\Wayfair\WayfairBaseCostPullService;
use Illuminate\Console\Command;

class WayfairPullBaseCost extends Command
{
    protected $signature = 'wayfair:pull-base-cost
                            {--file= : Use this already-downloaded Partner Home product export (.xlsx) instead of downloading one}
                            {--dry-run : Show what would change without saving}
                            {--show : List every changed SKU}
                            {--force : Skip the safety checks (large-change limit, upload in progress)}
                            {--scheduled : Run only when WAYFAIR_BASE_COST_PULL_ENABLED is on}';

    protected $description = 'Set the Wayfair Price column from Partner Home "Current Base Cost" (the export spreadsheet)';

    public function handle(WayfairBaseCostPullService $service): int
    {
        if ($this->option('scheduled') && ! config('wayfair_upload.base_cost_pull.enabled')) {
            $this->info('Wayfair base-cost pull is off (WAYFAIR_BASE_COST_PULL_ENABLED).');

            return self::SUCCESS;
        }

        $path = (string) $this->option('file');
        if ($path === '') {
            $this->info('Downloading the Partner Home product export...');
            $download = $service->download();
            if (! $download['success']) {
                $this->error($download['message']);

                return self::FAILURE;
            }
            $path = (string) $download['path'];
            $this->info('Saved '.$path);
        }

        try {
            $export = $service->readExport($path);
        } catch (\Throwable $e) {
            $this->error('Could not read the export: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('Export rows with a Current Base Cost: '.count($export));

        $dry = (bool) $this->option('dry-run');
        $stats = $service->apply($export, $dry, (bool) $this->option('force'));
        $stats['success'] ? $this->info($stats['message']) : $this->error($stats['message']);

        if (($dry || $this->option('show') || $stats['aborted']) && ! empty($stats['changes'])) {
            $changes = $stats['changes'];
            usort($changes, static fn ($a, $b) => abs($b['new'] - $b['old']) <=> abs($a['new'] - $a['old']));
            $this->table(
                ['SKU', 'Status', 'Stored price', 'Wayfair Base Cost', 'Diff'],
                array_map(static fn ($c) => [
                    $c['sku'],
                    $c['status'],
                    number_format($c['old'], 2),
                    number_format($c['new'], 2),
                    number_format($c['new'] - $c['old'], 2),
                ], $changes)
            );
        }

        return $stats['success'] || $stats['skipped_upload_in_flight'] ? self::SUCCESS : self::FAILURE;
    }
}
