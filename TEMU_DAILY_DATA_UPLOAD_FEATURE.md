# Temu Daily Data Upload Feature

## Overview
This feature allows users to upload daily Temu order data via Excel/CSV files with chunk-based processing to handle large datasets efficiently.

## Files Created/Modified

### 1. Database Migration
**File**: `database/migrations/2025_12_09_051559_create_temu_daily_data_table.php`
- Creates `temu_daily_data` table with all required fields
- Includes indexes on `order_id`, `contribution_sku`, and `purchase_date`

### 2. Model
**File**: `app/Models/TemuDailyData.php`
- Eloquent model for the `temu_daily_data` table
- Includes fillable fields and casts for date/numeric columns

### 3. Controller
**File**: `app/Http/Controllers/MarketPlace/TemuController.php`
- Added `uploadDailyDataChunk()` - Handles chunk-based upload
- Added `normalizeHeader()` - Maps Excel headers to database fields
- Added `parseDate()` - Handles multiple date formats
- Added `downloadDailyDataSample()` - Generates sample Excel template

### 4. Routes
**File**: `routes/web.php`
- `POST /temu/upload-daily-data-chunk` - Upload endpoint
- `GET /temu/download-daily-data-sample` - Sample download endpoint

### 5. View
**File**: `resources/views/market-places/temu.blade.php`
- Added "Upload Daily Data" button in header
- Added upload modal with:
  - File input
  - Progress bar
  - Sample template download link
- Added JavaScript for chunk upload handling

## Features

### Chunk-Based Upload
- Divides file processing into 5 chunks
- Shows real-time progress
- Prevents timeout on large files
- Each chunk processed in transaction

### Supported Columns
All columns from Temu daily data export:
- Order ID, Order Status, Fulfillment Mode
- Order Item details
- Product information
- Customer/Recipient details
- Shipping addresses
- Dates (Purchase, Shipping, Delivery)
- Pricing information
- Tracking details
- Settlement status

### File Format Support
- Excel (.xlsx, .xls)
- CSV (.csv)

### Error Handling
- File validation
- Database transaction rollback on errors
- User-friendly error messages
- Progress tracking

## Usage

### For End Users
1. Click "Upload Daily Data" button
2. Select Excel/CSV file or download sample template
3. Click "Start Upload"
4. Monitor progress
5. View success/error message

### Sample Data Structure
Download sample template from modal to see expected format with:
- All 37 columns properly formatted
- 3 example rows
- Proper date formats
- Styled headers

## Technical Details

### Chunk Processing
```php
// Chunk settings
const TOTAL_CHUNKS = 5;
- Each chunk: ~20% of total rows
- Sequential processing
- Temp file storage during upload
- Cleanup on completion
```

### Database Schema
```sql
- 37 columns total
- Nullable fields for flexibility
- Proper data types (varchar, int, decimal, timestamp)
- Indexes for performance
```

### Header Normalization
Handles variations in column names:
- Converts to snake_case
- Removes special characters
- Maps long names (e.g., "ship postal code (Must be shipped...)" → "ship_postal_code")

### Date Parsing
Supports multiple formats:
- Y-m-d H:i:s
- Y-m-d
- m/d/Y H:i:s
- m/d/Y
- d/m/Y H:i:s
- d/m/Y

## Future Enhancements
- [ ] Add data validation rules
- [ ] Add duplicate detection
- [ ] Add export of uploaded data
- [ ] Add data visualization/reporting
- [ ] Add bulk delete/update features
- [ ] Add filtering by date range
- [ ] Add SKU matching with product_masters
