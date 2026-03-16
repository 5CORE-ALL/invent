<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Walmart API Rate Limiter
 * 
 * Manages rate limits across different Walmart API endpoints
 * 
 * Typical Walmart Limits (approximate per seller account):
 * - Pricing Insights: ~100-200 requests/minute
 * - Listing Quality: ~100-200 requests/minute  
 * - Inventory Feeds: ~10 uploads/hour
 * - Orders API: ~200 requests/minute
 */
class WalmartRateLimiter
{
    /**
     * API endpoint groups with their limits (requests per minute)
     */
    protected const RATE_LIMITS = [
        'pricing' => ['limit' => 100, 'window' => 60],      // 100 requests per minute
        'listing' => ['limit' => 100, 'window' => 60],      // 100 requests per minute
        'inventory' => ['limit' => 10, 'window' => 3600],   // 10 feeds per hour
        'orders' => ['limit' => 150, 'window' => 60],       // 150 requests per minute
        'feeds' => ['limit' => 10, 'window' => 3600],       // 10 feeds per hour
        'default' => ['limit' => 80, 'window' => 60],       // Conservative default
    ];

    /**
     * Recommended sleep between requests (milliseconds)
     */
    protected const REQUEST_DELAYS = [
        'pricing' => 600,    // 600ms between pricing calls
        'listing' => 600,    // 600ms between listing calls
        'inventory' => 500,  // 500ms between inventory calls
        'orders' => 400,     // 400ms between order calls
        'feeds' => 0,        // No delay for feeds (rate limited by window)
        'default' => 500,    // 500ms default
    ];

    /**
     * Check if we can make a request to the specified API group
     * 
     * @param string $apiGroup API group name (pricing, listing, etc.)
     * @return bool
     */
    public function canMakeRequest(string $apiGroup): bool
    {
        $config = self::RATE_LIMITS[$apiGroup] ?? self::RATE_LIMITS['default'];
        $cacheKey = "walmart_rate_limit:{$apiGroup}";
        
        $requestCount = Cache::get($cacheKey, 0);
        
        return $requestCount < $config['limit'];
    }

    /**
     * Record a request to the specified API group
     * 
     * @param string $apiGroup API group name
     * @return void
     */
    public function recordRequest(string $apiGroup): void
    {
        $config = self::RATE_LIMITS[$apiGroup] ?? self::RATE_LIMITS['default'];
        $cacheKey = "walmart_rate_limit:{$apiGroup}";
        
        $requestCount = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $requestCount + 1, $config['window']);
    }

    /**
     * Wait if necessary before making a request
     * Returns the number of seconds waited
     * 
     * @param string $apiGroup API group name
     * @return int Seconds waited
     */
    public function waitIfNeeded(string $apiGroup): int
    {
        if (!$this->canMakeRequest($apiGroup)) {
            $config = self::RATE_LIMITS[$apiGroup] ?? self::RATE_LIMITS['default'];
            $waitTime = $config['window'];
            
            Log::warning("Walmart rate limit reached for {$apiGroup}. Waiting {$waitTime} seconds...");
            sleep($waitTime);
            
            // Clear the counter after waiting
            Cache::forget("walmart_rate_limit:{$apiGroup}");
            
            return $waitTime;
        }
        
        return 0;
    }

    /**
     * Sleep between requests (microseconds)
     * 
     * @param string $apiGroup API group name
     * @return void
     */
    public function throttle(string $apiGroup = 'default'): void
    {
        $delayMs = self::REQUEST_DELAYS[$apiGroup] ?? self::REQUEST_DELAYS['default'];
        usleep($delayMs * 1000); // Convert ms to microseconds
    }

    /**
     * Get remaining requests for an API group
     * 
     * @param string $apiGroup API group name
     * @return int
     */
    public function getRemainingRequests(string $apiGroup): int
    {
        $config = self::RATE_LIMITS[$apiGroup] ?? self::RATE_LIMITS['default'];
        $cacheKey = "walmart_rate_limit:{$apiGroup}";
        
        $requestCount = Cache::get($cacheKey, 0);
        
        return max(0, $config['limit'] - $requestCount);
    }

    /**
     * Execute with retry logic and exponential backoff
     * 
     * @param callable $callback The API call to execute
     * @param string $apiGroup API group name
     * @param int $maxRetries Maximum number of retries
     * @return mixed
     * @throws \Exception
     */
    public function executeWithRetry(callable $callback, string $apiGroup = 'default', int $maxRetries = 3)
    {
        $attempt = 0;
        $lastException = null;
        $consecutiveRateLimits = 0;

        while ($attempt < $maxRetries) {
            try {
                // Wait if we're at the rate limit
                $this->waitIfNeeded($apiGroup);
                
                // Throttle between requests
                if ($attempt > 0) {
                    // Exponential backoff: 2^attempt * 30 seconds
                    $backoffTime = pow(2, $attempt) * 30;
                    Log::info("Retry attempt {$attempt} for {$apiGroup}. Waiting {$backoffTime}s...");
                    sleep($backoffTime);
                } else {
                    $this->throttle($apiGroup);
                }

                // Record the request
                $this->recordRequest($apiGroup);

                // Execute the callback
                $result = $callback();

                // Success! Return the result
                return $result;

            } catch (\Exception $e) {
                $lastException = $e;
                $errorMessage = $e->getMessage();

                // Check if it's a rate limit error
                if (strpos($errorMessage, 'REQUEST_THRESHOLD_VIOLATED') !== false || 
                    strpos($errorMessage, '429') !== false) {
                    
                    $consecutiveRateLimits++;
                    
                    Log::warning("Rate limit hit for {$apiGroup} on attempt {$attempt}. Retrying... (consecutive: {$consecutiveRateLimits})");
                    
                    // If hitting limit too many times, suggest waiting longer
                    if ($consecutiveRateLimits >= 2) {
                        Log::error("Walmart API for {$apiGroup} is persistently rate-limited. Consider waiting 1-2 hours before running again.");
                        throw new \Exception("Walmart {$apiGroup} API is heavily rate-limited. Please wait 1-2 hours and try again. (Consecutive failures: {$consecutiveRateLimits})");
                    }
                    
                    $attempt++;
                    
                    // Wait longer for rate limit errors (2-3 minutes)
                    $waitTime = 120 + ($attempt * 60);
                    Log::info("Waiting {$waitTime} seconds for Walmart rate limit to reset...");
                    sleep($waitTime);
                    continue;
                }

                // For other errors, rethrow immediately
                throw $e;
            }
        }

        // All retries exhausted
        throw new \Exception("Max retries ({$maxRetries}) exceeded for {$apiGroup}: " . $lastException->getMessage());
    }

    /**
     * Reset all rate limit counters (useful for testing)
     * 
     * @return void
     */
    public function reset(): void
    {
        foreach (array_keys(self::RATE_LIMITS) as $group) {
            Cache::forget("walmart_rate_limit:{$group}");
        }
    }

    /**
     * Get statistics for all API groups
     * 
     * @return array
     */
    public function getStats(): array
    {
        $stats = [];
        
        foreach (self::RATE_LIMITS as $group => $config) {
            $cacheKey = "walmart_rate_limit:{$group}";
            $used = Cache::get($cacheKey, 0);
            
            $stats[$group] = [
                'limit' => $config['limit'],
                'window' => $config['window'],
                'used' => $used,
                'remaining' => max(0, $config['limit'] - $used),
                'percentage' => $config['limit'] > 0 ? round(($used / $config['limit']) * 100, 2) : 0,
            ];
        }
        
        return $stats;
    }
}
