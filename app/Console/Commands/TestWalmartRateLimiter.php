<?php

namespace App\Console\Commands;

use App\Services\WalmartRateLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestWalmartRateLimiter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walmart:test-rate-limiter {--reset : Reset counters before test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Walmart API rate limiter functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rateLimiter = new WalmartRateLimiter();

        if ($this->option('reset')) {
            $rateLimiter->reset();
            $this->info('✓ Rate limit counters reset');
        }

        $this->info('Testing Walmart Rate Limiter...');
        $this->newLine();

        // Test 1: Basic throttling
        $this->info('Test 1: Throttling between requests');
        $start = microtime(true);
        
        for ($i = 1; $i <= 5; $i++) {
            $rateLimiter->throttle('pricing');
            $this->line("  Request {$i} sent");
        }
        
        $elapsed = round((microtime(true) - $start) * 1000);
        $this->info("  ✓ 5 requests completed in {$elapsed}ms (expected ~3000ms for 600ms throttle)");
        $this->newLine();

        // Test 2: Request tracking
        $this->info('Test 2: Request tracking and limits');
        $rateLimiter->recordRequest('pricing');
        $rateLimiter->recordRequest('pricing');
        $rateLimiter->recordRequest('listing');
        
        $remaining = $rateLimiter->getRemainingRequests('pricing');
        $this->line("  Pricing API: {$remaining} requests remaining (out of 100)");
        
        $remaining = $rateLimiter->getRemainingRequests('listing');
        $this->line("  Listing API: {$remaining} requests remaining (out of 100)");
        $this->newLine();

        // Test 3: Statistics
        $this->info('Test 3: Rate limit statistics');
        $stats = $rateLimiter->getStats();
        
        $this->table(
            ['API Group', 'Limit', 'Used', 'Remaining', 'Usage %'],
            collect($stats)->map(function($stat, $group) {
                return [
                    $group,
                    $stat['limit'],
                    $stat['used'],
                    $stat['remaining'],
                    $stat['percentage'] . '%'
                ];
            })->toArray()
        );
        $this->newLine();

        // Test 4: executeWithRetry (simulated)
        $this->info('Test 4: Execute with retry (simulated success)');
        try {
            $result = $rateLimiter->executeWithRetry(function() {
                // Simulate API call
                usleep(100000); // 100ms
                return 'Success!';
            }, 'pricing', 2);
            
            $this->info("  ✓ Result: {$result}");
        } catch (\Exception $e) {
            $this->error("  ✗ Failed: " . $e->getMessage());
        }
        $this->newLine();

        // Test 5: Final stats
        $this->info('Final Statistics:');
        $stats = $rateLimiter->getStats();
        
        foreach (['pricing', 'listing', 'inventory', 'orders', 'feeds'] as $group) {
            if (isset($stats[$group]) && $stats[$group]['used'] > 0) {
                $stat = $stats[$group];
                $this->line(sprintf(
                    "  %s: %d/%d requests (%.1f%% used)",
                    ucfirst($group),
                    $stat['used'],
                    $stat['limit'],
                    $stat['percentage']
                ));
            }
        }
        
        $this->newLine();
        $this->info('✓ All tests completed!');
        $this->newLine();
        $this->comment('Note: Counters will reset automatically after the time window (60 seconds for most APIs)');
        $this->comment('To reset manually, run: php artisan walmart:test-rate-limiter --reset');

        return 0;
    }
}
