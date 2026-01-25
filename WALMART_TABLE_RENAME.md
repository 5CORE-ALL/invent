# Walmart Table Name Change

## Summary

Changed table name from `walmart_pricing_sales` to `walmart_pricing`

---

## What Changed

### 1. Model Updated ✅

**File:** `app/Models/WalmartPricingSales.php`

```php
// Before
protected $table = 'walmart_pricing_sales';

// After
protected $table = 'walmart_pricing';
```

### 2. Migration Created ✅

**File:** `database/migrations/2026_01_25_055702_rename_walmart_pricing_sales_to_walmart_pricing.php`

This migration will rename the table in the database.

---

## How to Apply

### Option 1: If Table Already Exists (Rename)

```bash
# Run the migration to rename the table
php artisan migrate

# This will rename:
# walmart_pricing_sales → walmart_pricing
```

**Output:**
```
Migrating: 2026_01_25_055702_rename_walmart_pricing_sales_to_walmart_pricing
Migrated:  2026_01_25_055702_rename_walmart_pricing_sales_to_walmart_pricing (X.XXms)
```

### Option 2: If Table Doesn't Exist (Fresh)

```bash
# Run all migrations
php artisan migrate

# This will create the walmart_pricing table
```

---

## Verification

### Check Table Exists

```sql
-- MySQL/MariaDB
SHOW TABLES LIKE 'walmart_pricing';

-- Should return: walmart_pricing
```

### Check Data

```sql
SELECT COUNT(*) FROM walmart_pricing;

SELECT * FROM walmart_pricing LIMIT 10;
```

### Using Laravel

```bash
php artisan tinker
```

```php
// Check table
\App\Models\WalmartPricingSales::count();

// Should work without errors
\App\Models\WalmartPricingSales::first();
```

---

## What This Affects

### ✅ Already Updated:
- `app/Models/WalmartPricingSales.php` - Model table name
- `database/migrations/*_rename_walmart_pricing_sales_to_walmart_pricing.php` - Migration created

### ✅ Automatically Works:
- `app/Console/Commands/FetchWalmartPricingSales.php` - Uses model (no change needed)
- All queries using `WalmartPricingSales` model - Uses new table name

### ⚠️ May Need Manual Check:
- Any raw SQL queries with hardcoded table name
- Any views or stored procedures referencing old table
- Any external scripts accessing the table

---

## Rollback (If Needed)

If you need to revert:

```bash
php artisan migrate:rollback --step=1
```

This will rename it back to `walmart_pricing_sales`

---

## Database Schema

The table structure remains the same, only the name changes:

```
walmart_pricing (formerly walmart_pricing_sales)
├── id
├── sku (unique)
├── item_id
├── item_name
├── current_price
├── buy_box_base_price
├── l30_orders
├── l30_qty
├── l30_revenue
├── l60_orders
├── l60_qty
├── l60_revenue
├── traffic
├── views
├── page_views
├── ... (50+ total columns)
├── created_at
└── updated_at
```

---

## Command Usage

No changes needed to your commands!

```bash
# Run normally
php artisan walmart:pricing-sales

# Data will be inserted into walmart_pricing table ✅
```

---

## Summary

**Before:**
- Table: `walmart_pricing_sales`
- Model: `WalmartPricingSales` → `walmart_pricing_sales`

**After:**
- Table: `walmart_pricing` ✅
- Model: `WalmartPricingSales` → `walmart_pricing` ✅

**Action Required:**
```bash
php artisan migrate
```

That's it! ✅
