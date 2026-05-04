# LQS (Listing Quality Score) Implementation Summary

## Overview
This document summarizes the complete implementation of the LQS (Listing Quality Score) feature across the dashboard system.

## Components Implemented

### 1. Database Structure

#### `lqs_history` Table
- **Purpose**: Stores daily snapshots of LQS metrics
- **Columns**:
  - `id` - Primary key
  - `date` - Unique date (indexed)
  - `total_inv` - Total inventory count
  - `total_ov` - Total OV L30 (Order Views Last 30 days)
  - `avg_dil` - Average DIL percentage
  - `avg_lqs` - Average Listing Quality Score
  - `created_at`, `updated_at` - Timestamps

### 2. Daily Data Calculation

#### Command: `channel:calculate-data`
- **Location**: `app/Console/Commands/CalculateChannelMasterData.php`
- **Schedule**: Runs daily at 1:00 AM IST
- **Function**: Calculates and stores LQS metrics daily

**LQS Calculation Logic**:
```php
- Queries ProductMaster, ShopifySku, and JungleScoutProductData tables
- Joins data using normalized SKU matching
- Calculates:
  * Total INV: Sum of all inventory
  * Total OV: Sum of all order views (L30)
  * Avg DIL%: Weighted average of (INV/OV)*100
  * Avg LQS: Average of listing_quality_score from JungleScout data
- Stores daily snapshot in lqs_history table
```

**Manual Execution**:
```bash
php artisan channel:calculate-data --force
```

### 3. Backend API

#### Controller: `LqsMasterController`
- **Location**: `app/Http/Controllers/MarketPlace/LqsMasterController.php`

**Endpoints**:
1. **GET /lqs-data** - Main LQS Data page view
2. **GET /lqs/data** - Fetch LQS tabulator data
3. **GET /lqs/badge-chart-data** - Historical trend data for charts
   - Parameters:
     - `metric`: avg_lqs, total_inv, total_ov, avg_dil
     - `days`: 7, 30, 60, 90

**Key Methods**:
- `lqsDataView()` - Returns the LQS Data page
- `getLqsData()` - Fetches and formats LQS data for tabulator
- `badgeChartData()` - Returns historical trend data
- `saveDailySnapshot()` - Saves daily metrics to lqs_history
- `calculateCurrentMetric()` - Calculates real-time metrics

### 4. Frontend Views

#### A. LQS Data Page
- **Route**: `/lqs-data`
- **View**: `resources/views/market-places/lqs_master_tabulator_view.blade.php`
- **Features**:
  - Tabulator table showing Parent, SKU, Image, INV, OV L30, DIL%, LQS
  - Filters: Row type, INV, LQS score, DIL, SKU search
  - Summary badges: Total INV, Total OV L30, Avg DIL, Avg LQS (all clickable)
  - Historical trend charts (Chart.js with datalabels)
  - Export functionality

#### B. Main Dashboard (Index Page)
- **Route**: `/` (index)
- **View**: `resources/views/index.blade.php`
- **LQS Badge Added**: 
  - "Avg LQS" badge in Summary Statistics section
  - Clickable to show historical trend chart
  - Purple badge (#6f42c1) with white text

#### C. All Marketplace Master Page
- **Route**: `/all-marketplace-master`
- **View**: `resources/views/channels/all-marketplace-master.blade.php`
- **LQS Badge Added**:
  - "Avg LQS" badge in summary section
  - Historical trend chart modal
  - Same styling as other pages

#### D. Resources Master Dashboard
- **Route**: `/dashboard`
- **View**: `resources/views/resources-master/dashboard.blade.php`
- **LQS Card Added**:
  - Stat card showing "Avg LQS"
  - Clickable to show trend chart
  - Consistent with other dashboard cards

### 5. Chart Implementation

**Technology Stack**:
- Chart.js 4.4.0
- chartjs-plugin-datalabels (for value labels on charts)

**Chart Features**:
- Line chart with filled area
- Time range selector (7, 30, 60, 90 days)
- Data labels showing values on each point
- Responsive design
- Loading and no-data states
- Purple color scheme matching badge

**Modal Structure**:
```html
<div class="modal fade" id="lqsBadgeChartModal">
  - Header with metric title and time range selector
  - Canvas for Chart.js rendering
  - Loading spinner
  - No-data message
</div>
```

## Data Flow

```
1. Daily Schedule (1 AM IST)
   ↓
2. channel:calculate-data command runs
   ↓
3. Queries ProductMaster + ShopifySku + JungleScoutProductData
   ↓
4. Calculates metrics (Total INV, OV, Avg DIL, Avg LQS)
   ↓
5. Stores in lqs_history table
   ↓
6. Dashboard pages fetch data via /lqs/badge-chart-data
   ↓
7. Displays current value in badge
   ↓
8. Click badge → Opens modal with historical chart
```

## Testing

### Recent Test Results (2026-05-03 03:59:41)
```
✓ Command execution: SUCCESS
✓ Channels processed: 29
✓ Execution time: 45.92 seconds
✓ LQS Data Calculated:
  - Total INV: 2,307
  - Total OV: 258
  - Avg DIL: 2107.84%
  - Avg LQS: 7.05
```

### Manual Testing Commands
```bash
# Run calculation manually
php artisan channel:calculate-data --force

# Check LQS history data
php artisan tinker
>>> DB::table('lqs_history')->orderBy('date', 'desc')->first();

# Check if scheduled
php artisan schedule:list | grep channel
```

## Routes Summary

```php
// LQS Data Page Routes
Route::get('/lqs-data', [LqsMasterController::class, 'lqsDataView'])
    ->name('lqs.data.view');

Route::get('/lqs/data', [LqsMasterController::class, 'getLqsData'])
    ->name('lqs.data');

Route::get('/lqs/badge-chart-data', [LqsMasterController::class, 'badgeChartData'])
    ->name('lqs.badge.chart');
```

## Models

### LqsHistory Model
- **Location**: `app/Models/LqsHistory.php`
- **Table**: `lqs_history`
- **Fillable**: date, total_inv, total_ov, avg_dil, avg_lqs
- **Casts**: date → date, metrics → decimal

### Related Models Used
- `ProductMaster` - Main product/SKU data
- `ShopifySku` - Inventory and OV data
- `JungleScoutProductData` - Listing quality scores

## Configuration

### Cron Schedule (app/Console/Kernel.php)
```php
$schedule->command('channel:calculate-data')
    ->dailyAt('01:00')
    ->timezone('Asia/Kolkata')
    ->description('Calculate channel master and LQS data daily');
```

### Cache
- Summary data cached for 24 hours
- Key: `channel_master_summary_data`

## Features Summary

✅ Daily automated LQS calculation
✅ Historical data storage
✅ Multiple dashboard integrations
✅ Interactive trend charts
✅ Real-time metric calculation fallback
✅ Data export functionality
✅ Responsive design
✅ Loading states and error handling

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Chart.js 4.4.0 compatible
- Bootstrap 5 modals
- Responsive (mobile-friendly)

## Future Enhancements (Optional)

1. Add email alerts when LQS drops below threshold
2. Add LQS breakdown by category or brand
3. Add comparison view (this week vs last week)
4. Add forecasting based on historical trends
5. Add drill-down from badge to detailed SKU view

## Maintenance

### Regular Tasks
- Monitor daily command execution logs
- Verify data integrity in lqs_history table
- Review LQS trends monthly
- Archive old historical data (optional, after 1+ years)

### Troubleshooting

**Command not running?**
```bash
# Check cron status
php artisan schedule:list

# Check last run
php artisan schedule:work
```

**No data showing?**
```bash
# Run calculation manually
php artisan channel:calculate-data --force

# Check if data exists
php artisan tinker
>>> LqsHistory::latest()->first();
```

**Charts not loading?**
- Check browser console for JavaScript errors
- Verify Chart.js and datalabels plugin are loaded
- Check network tab for API endpoint responses

---

**Implementation Date**: 2026-05-03
**Last Updated**: 2026-05-03
**Status**: ✅ Complete and operational
