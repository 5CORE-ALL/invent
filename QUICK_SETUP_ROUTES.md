# Quick Setup - Add These Routes to web.php

## Step 1: Add Controller Import

Add this line near the top of `routes/web.php` with other controller imports:

```php
use App\Http\Controllers\AmazonAds\AmazonAdsPushLogController;
```

## Step 2: Add Routes

Add these routes in your `web.php` file (suggest adding after Amazon Ads related routes):

```php
// Amazon Ads Push Logs - Failed Campaigns Tracker
Route::prefix('amazon-ads/push-logs')->name('amazon-ads.push-logs.')->middleware(['auth'])->group(function () {
    Route::get('/', [AmazonAdsPushLogController::class, 'index'])->name('index');
    Route::get('/data', [AmazonAdsPushLogController::class, 'getData'])->name('data');
    Route::get('/stats', [AmazonAdsPushLogController::class, 'getStats'])->name('stats');
    Route::get('/export', [AmazonAdsPushLogController::class, 'export'])->name('export');
    Route::post('/cleanup', [AmazonAdsPushLogController::class, 'cleanup'])->name('cleanup');
});
```

## Step 3: Run Migration

```bash
php artisan migrate
```

## Step 4: Access the Page

Navigate to: `http://your-domain.com/amazon-ads/push-logs`

## Quick Test

After setup, test the page:

```bash
# 1. Migrate
php artisan migrate

# 2. Clear cache
php artisan config:clear
php artisan route:clear

# 3. Check routes
php artisan route:list | grep "push-logs"
```

You should see:
```
GET|HEAD  amazon-ads/push-logs ............ amazon-ads.push-logs.index
GET|HEAD  amazon-ads/push-logs/data ....... amazon-ads.push-logs.data
GET|HEAD  amazon-ads/push-logs/stats ...... amazon-ads.push-logs.stats
GET|HEAD  amazon-ads/push-logs/export ..... amazon-ads.push-logs.export
POST      amazon-ads/push-logs/cleanup .... amazon-ads.push-logs.cleanup
```

## Optional: Add to Navigation Menu

Add this to your sidebar navigation file:

```html
<li class="menu-item">
    <a href="{{ route('amazon-ads.push-logs.index') }}" class="menu-link">
        <i class="menu-icon fa fa-exclamation-triangle text-danger"></i>
        <span class="menu-text">Failed Campaigns</span>
        <span class="badge badge-danger ml-auto">New</span>
    </a>
</li>
```

Or under Amazon Ads submenu:

```html
<li class="menu-item">
    <a href="#" class="menu-link menu-toggle">
        <i class="menu-icon fa fa-amazon"></i>
        <span class="menu-text">Amazon Ads</span>
    </a>
    <ul class="menu-submenu">
        <!-- Your existing Amazon Ads menu items -->
        
        <li class="menu-item">
            <a href="{{ route('amazon-ads.push-logs.index') }}" class="menu-link">
                <i class="menu-icon fa fa-exclamation-triangle text-danger"></i>
                <span class="menu-text">Failed Campaigns Log</span>
            </a>
        </li>
    </ul>
</li>
```

## That's It!

After these steps, you'll have:
- ✅ Database table created
- ✅ Routes registered
- ✅ Page accessible at `/amazon-ads/push-logs`
- ✅ Ready to start logging failed campaigns

Next step: Integrate logging into your existing push methods (see main guide).
