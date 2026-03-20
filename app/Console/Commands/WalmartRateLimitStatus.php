<?php

namespace App\Console\Commands;

use App\Services\WalmartRateLimiter;
use Illuminate\Console\Command;

class WalmartRateLimitStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walmart:rate-limit-status {--reset : Reset all rate limit counters}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check or reset Walmart API rate limit status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rateLimiter = new WalmartRateLimiter();

        if ($this->option('reset')) {
            $rateLimiter->reset();
            $this->info('✓ All Walmart API rate limit counters have been reset');
            $this->newLine();
        }

        $this->info('Walmart API Rate Limit Status');
        $this->info('============================');
        $this->newLine();

        $stats = $rateLimiter->getStats();

        // Show active API groups only
        $activeGroups = ['pricing', 'listing', 'inventory', 'orders', 'feeds'];
        
        $tableData = [];
        foreach ($activeGroups as $group) {
            if (isset($stats[$group])) {
                $stat = $stats[$group];
                
                // Color coding based on usage
                $status = '✓ OK';
                if ($stat['percentage'] > 80) {
                    $status = '⚠ HIGH';
                } elseif ($stat['percentage'] > 50) {
                    $status = '⚡ MODERATE';
                }
                
                $tableData[] = [
                    ucfirst($group),
                    $stat['limit'],
                    $stat['used'],
                    $stat['remaining'],
                    $stat['percentage'] . '%',
                    $status
                ];
            }
        }

        $this->table(
            ['API Group', 'Limit', 'Used', 'Remaining', 'Usage %', 'Status'],
            $tableData
        );

        $this->newLine();

        // Show warnings for high usage
        foreach ($activeGroups as $group) {
            if (isset($stats[$group]) && $stats[$group]['percentage'] > 80) {
                $this->warn("⚠ Warning: {$group} API is at {$stats[$group]['percentage']}% usage!");
                $this->line("  Consider waiting or reducing request frequency.");
            }
        }

        // Show recommendations
        $this->newLine();
        $this->comment('💡 Tips:');
        $this->line('  • Counters reset automatically after 60 seconds');
        $this->line('  • Use --reset to manually clear all counters');
        $this->line('  • Run before executing walmart commands to check status');
        $this->newLine();
        $this->comment('Usage:');
        $this->line('  php artisan walmart:rate-limit-status');
        $this->line('  php artisan walmart:rate-limit-status --reset');

        return 0;
    }
}
