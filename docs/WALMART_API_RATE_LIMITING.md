# Walmart API Rate Limiting Guide

## Problem Summary

Walmart API enforces strict rate limits across different API endpoint groups. When these limits are exceeded, you receive:

```
REQUEST_THRESHOLD_VIOLATED.GMP_GATEWAY_API
```

## Rate Limits (Approximate)

| API Group           | Limit                | Window    |
|---------------------|----------------------|-----------|
| Pricing Insights    | ~100-200 requests   | per minute|
| Listing Quality     | ~100-200 requests   | per minute|
| Inventory API       | ~100-200 requests   | per minute|
| Orders API          | ~150-200 requests   | per minute|
| Feed Submissions    | ~10 uploads         | per hour  |
| Feed Status Checks  | Higher (200+)       | per minute|

**Note:** Exact limits vary by seller account tier and API group.

## Solution Implemented

### 1. WalmartRateLimiter Service

Created: `app/Services/WalmartRateLimiter.php`

Features:
- ✅ Tracks requests per API group using Laravel Cache
- ✅ Automatic throttling between requests (500-600ms)
- ✅ Exponential backoff on retries
- ✅ Rate limit detection and auto-wait
- ✅ Statistics tracking

Usage example:
```php
use App\Services\WalmartRateLimiter;

$rateLimiter = new WalmartRateLimiter();

// Execute with automatic retry and rate limiting
$response = $rateLimiter->executeWithRetry(function() {
    return Http::get('https://api.walmart.com/...');
}, 'pricing', 3); // API group, max retries
```

### 2. Updated Commands

All Walmart API commands now use the rate limiter:

- ✅ `walmart:pricing-sales` - Pricing insights + Listing quality
- ✅ `walmart:fetch-inventory` - Inventory data
- ✅ `walmart:daily` - Daily order data

### 3. Throttling Strategy

**Request Delays (between consecutive calls):**
- Pricing API: 600ms
- Listing Quality: 600ms
- Inventory API: 500ms
- Orders API: 400ms

**Retry Logic:**
- Detects `REQUEST_THRESHOLD_VIOLATED` error
- Exponential backoff: 30s → 60s → 90s
- Max 3 retries per request

## Recommended Cron Schedule

### Option A: Conservative (Recommended for ~400 SKUs)

```php
// Pricing & Listing Quality - Every 3 hours (8x per day)
$schedule->command('walmart:pricing-sales')
    ->cron('0 */3 * * *')  // 00:00, 03:00, 06:00, 09:00, 12:00, 15:00, 18:00, 21:00
    ->timezone('America/Los_Angeles')
    ->name('walmart-pricing-sales')
    ->withoutOverlapping();

// Inventory - Every 4 hours (6x per day)
$schedule->command('walmart:fetch-inventory')
    ->cron('30 */4 * * *')  // 00:30, 04:30, 08:30, 12:30, 16:30, 20:30
    ->timezone('America/Los_Angeles')
    ->name('walmart-inventory')
    ->withoutOverlapping();

// Orders - Already scheduled (daily at 01:20)
$schedule->command('walmart:daily --days=60')
    ->dailyAt('01:20')
    ->timezone('Asia/Kolkata')
    ->name('walmart-daily')
    ->withoutOverlapping();
```

### Option B: Moderate (For active sellers)

```php
// Pricing & Listing Quality - Every 2 hours
$schedule->command('walmart:pricing-sales')
    ->cron('0 */2 * * *')
    ->timezone('America/Los_Angeles')
    ->name('walmart-pricing-sales')
    ->withoutOverlapping();

// Inventory - Every 3 hours
$schedule->command('walmart:fetch-inventory')
    ->cron('30 */3 * * *')
    ->timezone('America/Los_Angeles')
    ->name('walmart-inventory')
    ->withoutOverlapping();
```

### Option C: Aggressive (For high-volume sellers - use with caution)

```php
// Pricing & Listing Quality - Every hour
$schedule->command('walmart:pricing-sales')
    ->hourly()
    ->timezone('America/Los_Angeles')
    ->name('walmart-pricing-sales')
    ->withoutOverlapping();

// Inventory - Every 2 hours
$schedule->command('walmart:fetch-inventory')
    ->cron('0 */2 * * *')
    ->timezone('America/Los_Angeles')
    ->name('walmart-inventory')
    ->withoutOverlapping();
```

## Budget Calculator

For **~400 SKUs** with pagination:

### Pricing Insights API
- Page size: ~50 items
- Total pages: ~8 pages
- With rate limiter: 8 requests × 600ms = ~5 seconds
- **Budget:** 8 requests per run

### Listing Quality API
- Page size: 50 items
- Total pages: ~8 pages
- With rate limiter: 8 requests × 600ms = ~5 seconds
- **Budget:** 8 requests per run

### Inventory API
- Cursor-based pagination
- Estimated: ~5-10 pages
- With rate limiter: 10 requests × 500ms = ~5 seconds
- **Budget:** 10 requests per run

### Total per full sync:
- **~26 requests total**
- **~15-20 seconds execution time**
- **Safe to run every 2-3 hours**

## Best Practices

### ✅ DO:
1. **Separate read operations from write operations**
   - Don't submit feeds immediately after fetching data
   - Space them out by at least 30-60 minutes

2. **Use the rate limiter for ALL API calls**
   ```php
   $response = $rateLimiter->executeWithRetry(
       fn() => Http::get($url),
       'pricing',
       3
   );
   ```

3. **Monitor rate limit stats**
   ```php
   $stats = $rateLimiter->getStats();
   Log::info('Rate limit stats', $stats);
   ```

4. **Use `withoutOverlapping()` in schedule**
   - Prevents concurrent runs if previous job is still running

5. **Check response headers** (if available)
   ```php
   $remaining = $response->header('X-RateLimit-Remaining');
   $reset = $response->header('X-RateLimit-Reset');
   ```

### ❌ DON'T:
1. **Don't call multiple API groups back-to-back**
   - Bad: pricing → listing → inventory → feed (all at once)
   - Good: pricing+listing at 00:00, inventory at 00:30

2. **Don't skip throttling**
   - Always use `$rateLimiter->throttle()` between requests

3. **Don't ignore 429 errors**
   - The rate limiter handles them automatically

4. **Don't schedule too frequently**
   - Hourly runs can exhaust daily quotas
   - Every 2-4 hours is safer

## Monitoring Rate Limits

### Check current usage:
```bash
php artisan tinker
```
```php
use App\Services\WalmartRateLimiter;
$limiter = new WalmartRateLimiter();
print_r($limiter->getStats());
```

### Reset counters (for testing):
```php
$limiter->reset();
```

### View logs:
```bash
tail -f storage/logs/laravel.log | grep -i "walmart\|rate"
```

## Troubleshooting

### Error: REQUEST_THRESHOLD_VIOLATED

**Cause:** Exceeded rate limit for specific API group

**Solution:**
1. Wait 60-120 seconds
2. The rate limiter will auto-retry
3. If persistent, reduce cron frequency

### Error: 429 Too Many Requests

**Cause:** General rate limit across all APIs

**Solution:**
1. Increase delay between requests (edit `WalmartRateLimiter::REQUEST_DELAYS`)
2. Reduce cron frequency
3. Check if multiple jobs running simultaneously

### Feed submission fails after data fetch

**Cause:** Multiple API groups hit in quick succession

**Solution:**
1. Separate feed submission to its own cron
2. Run feeds 30-60 min after data fetch
3. Limit feed submissions to 2-4 times per day

## Performance Optimization

### For Large Catalogs (1000+ SKUs)

1. **Increase page size** (if API supports):
   ```php
   'limit' => 100  // Instead of 50
   ```

2. **Use bulk endpoints** where available

3. **Cache frequently accessed data**:
   ```php
   Cache::remember('walmart_pricing', 3600, fn() => ...);
   ```

4. **Process in background jobs**:
   ```php
   dispatch(new FetchWalmartPricingJob())->onQueue('walmart');
   ```

## Summary

✅ Rate limiter implemented  
✅ Automatic throttling (500-600ms)  
✅ Exponential backoff retries  
✅ Separate API group tracking  
✅ Conservative scheduling recommended  

**Recommended for ~400 SKUs:**
- Pricing/Listing: Every 3 hours
- Inventory: Every 4 hours
- Orders: Daily

This ensures you stay well within limits while keeping data fresh.
