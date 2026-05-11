# PLS Pricing Page - Setup Complete (Enhanced with Product Master)

## Overview
A comprehensive pricing and inventory management page for PLS products, following the same structure as `/macys-pricing`. The page combines data from `product_master` (base), `pls_products` (pricing & sales), and `shopify_catalog_variants` (inventory).

## What Was Created

### 1. Controller Methods
**File:** `app/Http/Controllers/MarketPlace/PlsController.php`

**Methods Added:**
- `pricingView()` - Displays the PLS pricing page
- `pricingDataJson()` - Returns combined pricing, sales, and inventory data

**Data Sources (Following Macy's Pattern):**
1. **Base:** `product_master` table - All products (excluding PARENT items)
2. **Sales:** `pls_products` table - Price, L30, L60 sales data
3. **Inventory:** `shopify_catalog_variants` table - Inventory quantities
4. **Costs:** `product_master.Values` JSON - LP (Landed Price), Ship costs

### 2. Blade View
**File:** `resources/views/market-places/pls_pricing_view.blade.php`

**Features:**
- Tabulator table with vertical column headers (space-efficient)
- Multiple filters:
  - Inventory (All, 0, More than 0)
  - Sales (All, 0 Sales, Has Sales based on L30)
  - GPFT% ranges (Negative, 0-10%, 10-20%, 20-30%, 30-50%, 50%+)
  - ROI% ranges (<40%, 40-100%, 100-200%, 200%+)
  - DIL% ranges (Red <16.7%, Yellow 16.7-25%, Green 25-50%, Pink 50%+)
- SKU search with real-time filtering
- Column visibility toggle
- CSV export
- Real-time summary badges

## Data Structure (Enhanced Like Macy's)

Each row in the table contains:

| Column | Source | Description | Calculation |
|--------|--------|-------------|-------------|
| **Parent** | product_master | Parent SKU (hidden by default) | - |
| **SKU** | product_master | Product SKU (frozen column) | - |
| **Title** | product_master | Product name | - |
| **Price** | pls_products | Current selling price | - |
| **LP** | product_master.Values | Landed Price (cost) | Parsed from Values JSON |
| **Ship** | product_master.Values | Shipping cost | Parsed from Values JSON |
| **Inv** | shopify_catalog_variants | Total inventory | Sum of all variants |
| **L30** | pls_products | Sales quantity last 30 days | - |
| **L60** | pls_products | Sales quantity last 60 days | - |
| **Sales L30** | Calculated | Revenue from L30 sales | Price × L30 |
| **GPFT** | Calculated | Gross Profit per unit | Price - LP - Ship |
| **GPFT%** | Calculated | Gross Profit Percentage | (GPFT / Price) × 100 |
| **Total PFT** | Calculated | Total Profit from L30 | GPFT × L30 |
| **ROI%** | Calculated | Return on Investment | ((Price - LP - Ship) / LP) × 100 |
| **DIL%** | Calculated | Days of Inventory Left | (L30 / Inventory) × 100 |

## Calculations (Enhanced)

### GPFT (Gross Profit) - **Now includes Ship cost**
```
GPFT = Price - LP - Ship
```

### GPFT% (Gross Profit Percentage)
```
GPFT% = (GPFT / Price) × 100
```

### ROI% (Return on Investment) - **Now includes Ship cost**
```
ROI% = ((Price - LP - Ship) / LP) × 100
```

### Sales L30 (Revenue)
```
Sales L30 = Price × L30
```

### Total PFT L30 (Total Profit)
```
Total PFT L30 = GPFT × L30
```

### DIL% (Days of Inventory Left %)
```
DIL% = (L30 / Inventory) × 100
```

**DIL% Interpretation:**
- **Red (<16.7%)**: Low turnover - inventory lasts >6 months
- **Yellow (16.7-25%)**: Moderate - inventory lasts 4-6 months  
- **Green (25-50%)**: Good - inventory lasts 2-4 months
- **Pink (50%+)**: Excellent - inventory lasts <2 months

## Color Coding

### GPFT% Colors:
- **Red** (#dc3545): Negative
- **Yellow** (#ffc107): 0-10%
- **Blue** (#17a2b8): 10-30%
- **Green** (#28a745): 30%+

### ROI% Colors:
- **Red** (#dc3545): Negative
- **Yellow** (#ffc107): 0-40%
- **Blue** (#17a2b8): 40-100%
- **Green** (#28a745): 100%+

### DIL% Colors (same as Macy's):
- **Red** (#dc3545): <16.7%
- **Yellow** (#ffc107): 16.7-25%
- **Green** (#28a745): 25-50%
- **Pink** (#ff69b4): 50%+

### Inventory Colors:
- **Red** (#dc3545): 0 inventory
- **Green** (#28a745): >0 inventory

### Sales Colors:
- **Gray** (#6c757d): 0 sales
- **Blue** (#0d6efd): Has sales

## How to Access

### Direct URL:
```
http://your-domain/pls-pricing
```

### From Sidebar:
1. Navigate to sidebar
2. Click on "PLS" section
3. Click on "PLS Pricing"

## Features

### Filters
- **Inventory Filter:** View all products, only zero inventory, or only products with inventory
- **Sales Filter:** View all products, only products with no sales (L30), or only products with sales
- **GPFT% Filter:** Filter by profit margin ranges
- **ROI% Filter:** Filter by return on investment ranges

### Summary Metrics
Displays real-time calculations of:
- Total number of products (filtered)
- Total inventory across all products
- Total L30 and L60 sales quantities
- Average price per product
- Average GPFT% across products
- Average ROI% across products
- Count of products with/without inventory
- Count of products with/without sales

### Export
- Export visible columns to CSV
- Filename format: `pls_pricing_YYYY-MM-DD.csv`

### Column Management
- Toggle individual column visibility
- "Show All" button to display all columns

### Interactive Badges
Click on summary badges to quickly filter:
- "0 Inv" - Shows only products with 0 inventory
- "> 0 Inv" - Shows only products with inventory
- "0 Sold" - Shows only products with no L30 sales
- "> 0 Sold" - Shows only products with L30 sales

## Data Update

To ensure the page shows current data, run these commands:

```bash
# 1. Sync Shopify catalog (products, variants, prices, inventory)
php artisan shopify-pls:sync

# 2. Fetch sales transactions
php artisan app:fetch-pls-sales-data --days=90

# 3. Calculate L30/L60 metrics and update pls_products
php artisan app:fetch-pls-data
```

## Notes

- Default filter shows products with inventory (More than 0)
- Table is sorted by L30 sales (descending) by default
- All products from catalog are included, even those without sales
- Products without sales will show L30=0 and L60=0
- GPFT and ROI calculations require both Price and LP to be set
- Inventory is summed across all variants of the same SKU

## Reference Pages

This page was modeled after: `/macys-pricing`
- Similar Tabulator structure
- Vertical column headers for space efficiency
- Multiple filter options
- Real-time summary calculations
- CSV export functionality

## Technical Details

### Performance
- Uses Tabulator 6.3.1 for efficient rendering
- Pagination set to 100 rows per page
- Filters applied client-side for instant results
- Summary calculations update automatically

### Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Requires JavaScript enabled
- Bootstrap 5 for UI components
