# Competitor Sales Data Command

## Overview
This command fetches sales data from JungleScout API for competitor ASINs stored in the `amazon_sku_competitors` table.

## Command Details

**File**: `app/Console/Commands/ProcessCompetitorSalesData.php`

**Signature**: `app:process-competitor-sales-data`

**Description**: Fetch competitor ASIN sales data from JungleScout API and update amazon_sku_competitors table

## How It Works

### Step 1: Fetch Competitor ASINs
- Queries the `amazon_sku_competitors` table for all records with valid ASINs
- Retrieves: id, asin, sku, marketplace
- Logs the total count of competitors found

### Step 2: Process in Chunks
- Divides competitors into batches of 100 ASINs
- Prevents API overload and handles rate limiting
- Adds 1-second delay between chunks

### Step 3: Call JungleScout API
For each chunk:
- Extracts ASIN values
- Makes POST request to JungleScout Product Database API
- Uses US marketplace
- Handles API failures gracefully (logs error and continues to next chunk)

### Step 4: Update Database
For each product returned:
- Matches ASIN with competitor records
- Updates the following fields:
  - `monthly_revenue` - 30-day revenue estimate
  - `monthly_units_sold` - 30-day units sold estimate
  - `buy_box_owner` - Current buy box owner
  - `seller_type_js` - Seller type from JungleScout
  - `sales_data_updated_at` - Timestamp of update

### Step 5: Error Handling
- Logs all errors
- Sends email notification to admin on failure
- Continues processing even if individual chunks fail

## Usage

### Run Manually
```bash
php artisan app:process-competitor-sales-data
```

### Schedule in Kernel (Optional)
Add to `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    // Run daily at 2 AM
    $schedule->command('app:process-competitor-sales-data')->dailyAt('02:00');
    
    // Or run every 6 hours
    $schedule->command('app:process-competitor-sales-data')->everySixHours();
}
```

## Database Changes

### Migration
File: `database/migrations/2026_05_04_224221_add_sales_columns_to_amazon_sku_competitors.php`

Added columns to `amazon_sku_competitors`:
- `monthly_revenue` (decimal, nullable)
- `monthly_units_sold` (integer, nullable)
- `buy_box_owner` (string, nullable)
- `seller_type_js` (string, nullable)
- `sales_data_updated_at` (timestamp, nullable)

### Model Updates
File: `app/Models/AmazonSkuCompetitor.php`

Updated:
- Added new fields to `$fillable` array
- Added proper type casting in `$casts` array

## Output Example
```
Fetched 150 competitor ASINs from amazon_sku_competitors table
Processing chunk 1 of 2 (100 ASINs)
Received 98 products from JungleScout API
Processing chunk 2 of 2 (50 ASINs)
Received 47 products from JungleScout API
Processing completed successfully!
Processed: 145 unique ASINs
Updated: 145 competitor records
```

## Notes
- The command handles multiple competitor records with the same ASIN
- If JungleScout API doesn't return data for an ASIN, that record is skipped
- Sales data is based on JungleScout's 30-day estimates
- Email notifications are sent to the admin email configured in `services.admin.email`
