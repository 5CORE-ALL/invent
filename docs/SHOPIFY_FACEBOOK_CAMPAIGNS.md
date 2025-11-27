# Shopify Facebook Campaign Data Fetcher

## Overview
This feature fetches Facebook paid campaign data from Shopify and stores sales and order information for 7, 30, and 60-day periods in the database.

## Database Table
**Table Name:** `shopify_facebook_campaigns`

### Columns:
- `id` - Primary key
- `campaign_id` - Facebook campaign ID (extracted from UTM parameters)
- `campaign_name` - Campaign name
- `date_range` - Period: '7_days', '30_days', or '60_days'
- `start_date` - Start date of the period
- `end_date` - End date of the period
- `sales` - Total sales amount (decimal)
- `orders` - Total number of orders (integer)
- `sessions` - Total sessions (integer)
- `conversion_rate` - Conversion rate percentage (decimal)
- `ad_spend` - Total ad spend (decimal)
- `roas` - Return on ad spend (decimal)
- `referring_channel` - Always 'facebook'
- `traffic_type` - Always 'paid'
- `country` - Country code (default: 'IN')
- `created_at` - Timestamp
- `updated_at` - Timestamp

## Components Created

### 1. Migration
**File:** `database/migrations/2025_11_27_035447_create_shopify_facebook_campaigns_table.php`
- Creates the database table structure

### 2. Model
**File:** `app/Models/ShopifyFacebookCampaign.php`
- Eloquent model for database interactions

### 3. Service
**File:** `app/Services/ShopifyMarketingService.php`
- Handles API calls to Shopify
- Fetches orders with Facebook UTM parameters
- Aggregates campaign data by date ranges
- Extracts campaign IDs and names from UTM parameters

### 4. Job
**File:** `app/Jobs/FetchShopifyFacebookCampaignsJob.php`
- Processes data fetching in the background
- Supports fetching data for specific date ranges or all ranges
- Stores/updates campaign data in the database

### 5. Artisan Command
**File:** `app/Console/Commands/FetchShopifyFacebookCampaigns.php`
- Manual command to trigger data fetching

## Usage

### Manual Execution

#### Fetch all date ranges (7, 30, and 60 days):
```bash
php artisan shopify:fetch-facebook-campaigns
```

#### Fetch specific date range:
```bash
# For 7 days
php artisan shopify:fetch-facebook-campaigns --range=7_days

# For 30 days
php artisan shopify:fetch-facebook-campaigns --range=30_days

# For 60 days
php artisan shopify:fetch-facebook-campaigns --range=60_days
```

### Scheduled Execution

Add to `app/Console/Kernel.php` in the `schedule()` method:

```php
// Fetch daily at midnight
$schedule->command('shopify:fetch-facebook-campaigns')->daily();

// Or fetch every 6 hours
$schedule->command('shopify:fetch-facebook-campaigns')->everySixHours();
```

### Programmatic Usage

```php
use App\Jobs\FetchShopifyFacebookCampaignsJob;

// Dispatch to queue
FetchShopifyFacebookCampaignsJob::dispatch('all');

// Run immediately (synchronous)
FetchShopifyFacebookCampaignsJob::dispatchSync('30_days');
```

## How It Works

1. **Data Fetching:**
   - Connects to Shopify API using credentials from `.env` file
   - Fetches orders within the specified date range
   - Filters orders that came from Facebook (via UTM parameters)

2. **Campaign Identification:**
   - Extracts campaign ID from `utm_id` or `campaign_id` parameters
   - Extracts campaign name from `utm_campaign` parameter
   - Falls back to `fbclid` for Facebook-specific tracking

3. **Data Aggregation:**
   - Groups orders by campaign
   - Calculates total sales and order count
   - Computes conversion rates and ROAS (if applicable)

4. **Storage:**
   - Uses `updateOrCreate` to avoid duplicates
   - Updates existing records if data changes
   - Stores separate records for each date range

## Environment Variables

Required in `.env` file:
```
SHOPIFY_STORE_URL=5-core.myshopify.com
SHOPIFY_ACCESS_TOKEN=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
```

## Query Examples

### Get all campaigns for last 30 days:
```php
$campaigns = ShopifyFacebookCampaign::where('date_range', '30_days')
    ->orderBy('sales', 'desc')
    ->get();
```

### Get total sales by date range:
```php
$totals = ShopifyFacebookCampaign::selectRaw('date_range, SUM(sales) as total_sales, SUM(orders) as total_orders')
    ->groupBy('date_range')
    ->get();
```

### Get specific campaign across all date ranges:
```php
$campaign = ShopifyFacebookCampaign::where('campaign_name', 'Summer Sale 2025')
    ->get();
```

## Notes

- The job runs asynchronously by default (requires queue worker)
- Data is fetched from Shopify's order API, filtered by Facebook UTM parameters
- Campaign identification relies on proper UTM tagging in Facebook ads
- The system handles duplicate prevention via indexed columns
- Logs are stored in Laravel's log files for debugging

## Troubleshooting

### Check logs:
```bash
tail -f storage/logs/laravel.log
```

### Test API connection:
```bash
php artisan tinker
>>> $service = new App\Services\ShopifyMarketingService();
>>> $data = $service->fetchOrdersWithUtmData('7_days');
>>> dd($data);
```

### Verify queue is running:
```bash
php artisan queue:work
```
