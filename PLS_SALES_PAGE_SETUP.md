# PLS Sales Page - Setup Complete

## Overview
A new page that displays the last 30 days of PLS sales data with filtering, sorting, and export capabilities.

## What Was Created

### 1. Controller Methods
**File:** `app/Http/Controllers/MarketPlace/PlsController.php`

**Methods Added:**
- `salesView()` - Displays the PLS sales view page
- `salesDataJson()` - Returns JSON data for the last 30 days of sales

### 2. Blade View
**File:** `resources/views/market-places/pls_sales_view.blade.php`

**Features:**
- Tabulator table with sorting and pagination
- Financial status filter (paid, pending, refunded, voided)
- Fulfillment status filter (fulfilled, unfulfilled, partial)
- SKU/Order search
- Column visibility toggle
- CSV export
- Real-time summary badges showing:
  - Total orders
  - Total quantity
  - Total revenue
  - Total discounts
  - Average price
  - Paid count
  - Fulfilled count

### 3. Routes
**File:** `routes/web.php`

**Routes Added:**
```php
Route::get('/pls-sales', [PlsController::class, 'salesView'])->name('pls.sales');
Route::get('/pls-sales-data-json', [PlsController::class, 'salesDataJson']);
```

### 4. Sidebar Menu
**File:** `resources/views/layouts/shared/left-sidebar.blade.php`

**Menu Item Added:**
- PLS Sales (30 Days) - under PLS section

## Data Source

The page displays data from the `pls_sales` table, showing only sales from the last 30 days.

## Columns Displayed

1. **Date** - Order date
2. **Order #** - Shopify order name (e.g., #2351)
3. **Order Number** - Internal order number (hidden by default)
4. **SKU** - Product SKU
5. **Product** - Product title
6. **Variant** - Variant title
7. **Qty** - Quantity ordered
8. **Price** - Unit price
9. **Total** - Total amount (qty × price)
10. **Discount** - Discount amount
11. **Tax** - Tax amount
12. **Financial** - Payment status (paid, pending, etc.)
13. **Fulfillment** - Fulfillment status (fulfilled, unfulfilled, etc.)
14. **Customer** - Customer name and email

## How to Access

### Direct URL:
```
http://your-domain/pls-sales
```

### From Sidebar:
1. Navigate to sidebar
2. Click on "PLS" section
3. Click on "PLS Sales (30 Days)"

## Features

### Filters
- **Financial Status:** Filter by payment status
- **Fulfillment Status:** Filter by fulfillment status
- **SKU/Order Search:** Real-time search by SKU or order number

### Summary Metrics
- Displays real-time calculations of:
  - Total unique orders
  - Total quantity sold
  - Total revenue
  - Total discounts
  - Average price per item
  - Number of paid orders
  - Number of fulfilled orders

### Export
- Export visible columns to CSV
- Filename format: `pls_sales_YYYY-MM-DD.csv`

### Column Management
- Toggle individual column visibility
- "Show All" button to display all columns

## Data Update

To ensure the page shows current data, run these commands:

```bash
# 1. Fetch sales data (run daily)
php artisan app:fetch-pls-sales-data --days=90

# 2. Calculate metrics (run after sales fetch)
php artisan app:fetch-pls-data
```

## Notes

- The page automatically filters to show only the last 30 days of sales
- Data updates in real-time when filters are applied
- Summary badges reflect filtered data
- The table supports sorting by any column
- Pagination is set to 100 rows per page by default

## Reference Page

This page was modeled after: `/purchasing-power-sales`
Similar structure and functionality adapted for PLS sales data.
