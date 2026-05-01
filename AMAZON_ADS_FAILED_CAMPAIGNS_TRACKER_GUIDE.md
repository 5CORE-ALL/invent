# Amazon Ads Failed Campaigns Tracker - Complete Guide

## Overview
A complete system to track, view, and analyze all failed Amazon Ads campaign updates (bids and budgets) from both web interface and command-line jobs.

## ✅ What Was Created

### 1. Database Migration
**File:** `database/migrations/2026_05_02_040000_create_amazon_ads_push_logs_table.php`

Creates `amazon_ads_push_logs` table to store all push attempts with:
- Campaign details (ID, name)
- Push type (SP/SB bid/budget)
- Status (success, skipped, failed)
- Failure reason
- Request/response data
- Timestamps and user tracking

### 2. Eloquent Model
**File:** `app/Models/AmazonAdsPushLog.php`

Provides methods to:
- Log individual push attempts
- Log batch push results
- Get statistics by date range
- Query by type, status, date
- Human-readable status/type names

### 3. Controller
**File:** `app/Http/Controllers/AmazonAds/AmazonAdsPushLogController.php`

Endpoints:
- `index()` - Display main page
- `getData()` - Get filtered logs for table
- `getStats()` - Get statistics
- `export()` - Export to CSV
- `cleanup()` - Delete old logs

### 4. Web Interface
**File:** `resources/views/amazon-ads/push-logs/index.blade.php`

Features:
- Real-time statistics dashboard
- Advanced filtering (type, status, date, source)
- Interactive data table with Tabulator
- Export to CSV
- Detailed view modal
- Top failure reasons

## 🚀 Installation Steps

### Step 1: Run Migration
```bash
php artisan migrate
```

This creates the `amazon_ads_push_logs` table.

### Step 2: Add Routes

Add to `routes/web.php`:

```php
// Amazon Ads Push Logs
Route::prefix('amazon-ads/push-logs')->name('amazon-ads.push-logs.')->group(function () {
    Route::get('/', [App\Http\Controllers\AmazonAds\AmazonAdsPushLogController::class, 'index'])
        ->name('index');
    Route::get('/data', [App\Http\Controllers\AmazonAds\AmazonAdsPushLogController::class, 'getData'])
        ->name('data');
    Route::get('/stats', [App\Http\Controllers\AmazonAds\AmazonAdsPushLogController::class, 'getStats'])
        ->name('stats');
    Route::get('/export', [App\Http\Controllers\AmazonAds\AmazonAdsPushLogController::class, 'export'])
        ->name('export');
    Route::post('/cleanup', [App\Http\Controllers\AmazonAds\AmazonAdsPushLogController::class, 'cleanup'])
        ->name('cleanup');
});
```

### Step 3: Add Menu Link

Add to your navigation menu (e.g., `resources/views/layouts/shared/left-sidebar.blade.php`):

```html
<li class="menu-item">
    <a href="{{ route('amazon-ads.push-logs.index') }}" class="menu-link">
        <i class="menu-icon fa fa-exclamation-triangle"></i>
        <span class="menu-text">Failed Campaigns</span>
    </a>
</li>
```

## 📝 Integration with Existing Code

### A. Update Controller Push Methods

Modify your `AmazonAdsController.php` push methods to log results:

```php
use App\Models\AmazonAdsPushLog;

public function pushSpSbids(Request $request): JsonResponse
{
    // ... existing code ...
    
    // After processing, log the results
    foreach ($skipped_rows as $skipped) {
        AmazonAdsPushLog::logPush([
            'push_type' => 'sp_sbid',
            'campaign_id' => $skipped['campaign_id'],
            'campaign_name' => $skipped['campaign_name'],
            'value' => $skipped['bid'],
            'status' => 'skipped',
            'reason' => $skipped['reason'],
            'source' => 'web',
        ]);
    }
    
    // Log successful ones too (optional)
    foreach ($successful_campaigns as $campaign) {
        AmazonAdsPushLog::logPush([
            'push_type' => 'sp_sbid',
            'campaign_id' => $campaign['campaign_id'],
            'campaign_name' => $campaign['campaign_name'],
            'value' => $campaign['bid'],
            'status' => 'success',
            'source' => 'web',
        ]);
    }
    
    return response()->json($payload);
}
```

### B. Update Command Files

Modify your command files to log results:

```php
use App\Models\AmazonAdsPushLog;

public function handle()
{
    // ... existing code ...
    
    // After processing, log skipped campaigns
    foreach ($skippedCampaigns as $skipped) {
        AmazonAdsPushLog::logPush([
            'push_type' => 'sp_sbgt',  // or appropriate type
            'campaign_id' => $skipped['campaign_id'],
            'campaign_name' => $skipped['campaign_name'],
            'value' => $skipped['sbgt'],
            'status' => 'skipped',
            'reason' => $skipped['reason'],
            'source' => 'command',
        ]);
    }
    
    return 0;
}
```

### C. Batch Logging (More Efficient)

For large batches, use batch logging:

```php
// Collect all results
$results = [];
foreach ($skippedCampaigns as $skipped) {
    $results[] = [
        'campaign_id' => $skipped['campaign_id'],
        'campaign_name' => $skipped['campaign_name'],
        'value' => $skipped['bid'] ?? $skipped['sbgt'],
        'status' => 'skipped',
        'reason' => $skipped['reason'],
    ];
}

// Log in one go
AmazonAdsPushLog::logBatch('sp_sbid', $results, 'command');
```

## 📊 Features & Usage

### View Failed Campaigns
Navigate to: `/amazon-ads/push-logs`

#### Statistics Dashboard
Shows:
- Total attempts
- Success count and rate
- Skipped count and rate
- Failed count and rate

#### Filters
- **Push Type**: SP Bid, SB Bid, SP Budget, SB Budget
- **Status**: Failed Only, Skipped Only, Success Only
- **Source**: Web, Command
- **Date Range**: From/To dates
- **Search**: Campaign ID, Campaign Name

#### Actions
- **Export to CSV**: Download filtered results
- **View Details**: See full request/response data
- **Refresh**: Reload data

### Top Failure Reasons
Automatically shows most common reasons for failures in the selected date range.

## 🔧 Maintenance

### Cleanup Old Logs

Run periodically to prevent table bloat:

```bash
curl -X POST http://your-domain/amazon-ads/push-logs/cleanup?days=90
```

Or create a scheduled task:

```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Delete logs older than 90 days, run monthly
    $schedule->call(function () {
        \App\Models\AmazonAdsPushLog::where('created_at', '<', now()->subDays(90))->delete();
    })->monthly();
}
```

### Database Indexes

The migration includes indexes for:
- `push_type`
- `campaign_id`
- `status`
- `created_at`
- Composite indexes for common queries

## 📈 Monitoring & Alerts

### Get Daily Stats

```php
use App\Models\AmazonAdsPushLog;

$stats = AmazonAdsPushLog::getStats(
    now()->subDays(1)->format('Y-m-d'),
    now()->format('Y-m-d')
);

// Returns:
// [
//     'total' => 100,
//     'success' => 85,
//     'skipped' => 10,
//     'failed' => 5,
//     'success_rate' => 85.00,
//     'skip_rate' => 10.00,
//     'fail_rate' => 5.00,
// ]
```

### Set Up Alerts

```php
// In a scheduled job or event listener
$stats = AmazonAdsPushLog::getStats(
    now()->startOfDay()->format('Y-m-d'),
    now()->format('Y-m-d')
);

if ($stats['skip_rate'] > 20) {
    // Send warning email/Slack notification
    \Mail::to('admin@example.com')->send(new HighSkipRateAlert($stats));
}

if ($stats['fail_rate'] > 10) {
    // Send critical alert
    \Log::error('High failure rate in Amazon Ads pushes', $stats);
}
```

### Common Queries

```php
// Get all PT bid failures from last week
$failures = AmazonAdsPushLog::ofType('sp_sbid')
    ->where('status', 'failed')
    ->where('campaign_name', 'LIKE', '% PT')
    ->recent(7)
    ->get();

// Get campaigns that failed multiple times
$repeatedFailures = AmazonAdsPushLog::failed()
    ->select('campaign_id', \DB::raw('COUNT(*) as failure_count'))
    ->groupBy('campaign_id')
    ->having('failure_count', '>', 3)
    ->orderByDesc('failure_count')
    ->get();

// Get failure reasons breakdown
$reasonsBreakdown = AmazonAdsPushLog::failed()
    ->recent(30)
    ->selectRaw('reason, COUNT(*) as count')
    ->groupBy('reason')
    ->orderByDesc('count')
    ->get();
```

## 🎯 Use Cases

### 1. Data Quality Monitoring
- Identify campaigns with consistently missing data
- Find patterns in failures (time, type, source)
- Track improvement after fixing data issues

### 2. Debugging
- See exact request/response for failed pushes
- Compare successful vs failed campaigns
- Identify API errors or timeout issues

### 3. Reporting
- Generate weekly/monthly failure reports
- Export data for analysis in Excel
- Present skip rate trends to stakeholders

### 4. Automated Retry
- Query failed campaigns from last hour
- Attempt to push again after fixing issues
- Track retry success rates

## 🚨 Troubleshooting

### Issue: No data showing
**Check:**
1. Migration ran successfully
2. Routes are added correctly
3. Controller namespace matches
4. Integration code is added to push methods

### Issue: Table not loading
**Check:**
1. Browser console for JavaScript errors
2. Network tab for failed API calls
3. Laravel logs for errors
4. Tabulator library is loading

### Issue: Export not working
**Check:**
1. PHP memory limit
2. Execution time limit
3. Large result sets (add more filters)

## 📚 API Endpoints

### GET `/amazon-ads/push-logs`
Display main interface

### GET `/amazon-ads/push-logs/data`
Get paginated log data
**Params:** `push_type`, `status`, `source`, `date_from`, `date_to`, `campaign_id`, `campaign_name`, `per_page`, `sort`, `dir`

### GET `/amazon-ads/push-logs/stats`
Get statistics
**Params:** `date_from`, `date_to`

### GET `/amazon-ads/push-logs/export`
Export to CSV
**Params:** Same as `/data` endpoint

### POST `/amazon-ads/push-logs/cleanup`
Delete old logs
**Params:** `days` (default: 90)

## ✅ Benefits

1. **Visibility**: See all failures in one place
2. **Accountability**: Track who pushed what and when
3. **Debugging**: Full request/response data available
4. **Trends**: Identify recurring issues
5. **Reporting**: Export data for analysis
6. **Automation**: Programmatic access to failure data

---

**Installation Time:** ~10 minutes
**Maintenance:** Low (automated cleanup recommended)
**Dependencies:** Tabulator.js (included in view)
