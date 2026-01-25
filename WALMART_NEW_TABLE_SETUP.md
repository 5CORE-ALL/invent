# Walmart Pricing - New Table Setup

## Created: Fresh `walmart_pricing` Table

A brand new `walmart_pricing` table with all 40+ columns required for Walmart data.

---

## 📋 Table Schema

### Table: `walmart_pricing`

**50+ Columns:**

**Product Info:**
- `id` - Primary key
- `sku` - Unique SKU identifier (unique index)
- `item_id` - Walmart item ID
- `item_name` - Product name (500 chars)

**Pricing (10 columns):**
- `current_price`
- `buy_box_base_price`
- `buy_box_total_price`
- `buy_box_win_rate`
- `competitor_price`
- `comparison_price`
- `price_differential`
- `price_competitive_score`
- `price_competitive` (boolean)

**Repricer (5 columns):**
- `repricer_strategy_type`
- `repricer_strategy_name`
- `repricer_status`
- `repricer_min_price`
- `repricer_max_price`

**Sales & Inventory (4 columns):**
- `gmv30` - Gross Merchandise Value L30
- `inventory_count`
- `fulfillment`
- `sales_rank`

**Order Metrics (6 columns):**
- `l30_orders`, `l30_qty`, `l30_revenue`
- `l60_orders`, `l60_qty`, `l60_revenue`

**Traffic & Views (4 columns):**
- `traffic` - VERY_LOW to VERY_HIGH
- `views` - Numeric level 1-5
- `page_views` - Actual view count
- `in_demand` (boolean)

**Promotional (4 columns):**
- `promo_status`
- `promo_details` (JSON)
- `reduced_referral_status`
- `walmart_funded_status`

**Timestamps:**
- `created_at`
- `updated_at`

**Indexes:**
- SKU (unique)
- item_id
- updated_at
- l30_qty
- current_price

---

## 🚀 Setup Instructions

### Step 1: Drop Old Table (If Exists)

**⚠️ Warning:** This will delete existing data in `walmart_pricing` table!

```bash
# Option A: Using Laravel
php artisan tinker
```
```php
Schema::dropIfExists('walmart_pricing');
exit;
```

**OR**

```bash
# Option B: Direct SQL (if you have mysql command)
/Applications/XAMPP/bin/mysql -u root invent -e "DROP TABLE IF EXISTS walmart_pricing;"
```

### Step 2: Run Migration

```bash
php artisan migrate
```

**Expected Output:**
```
Migrating: 2026_01_25_060000_create_walmart_pricing_table
Migrated:  2026_01_25_060000_create_walmart_pricing_table (XX.XXms)
```

### Step 3: Verify Table Created

```bash
php artisan tinker
```

```php
// Check table exists and is empty
\App\Models\WalmartPricingSales::count(); // Should return 0

// Check table name
(new \App\Models\WalmartPricingSales)->getTable(); // Should return 'walmart_pricing'

exit;
```

---

## ✅ Test Data Insert

Now test the command:

```bash
php artisan walmart:pricing-sales
```

**Expected Output:**
```
Step 2/4: Fetching pricing insights (saving as we go)...
  Page 0: 25 items (Total: 25, Remaining: 99)
  Page 1: 25 items (Total: 50, Remaining: 98)
  → Saved batch: 50 SKUs (Total saved: 50) ✅

  Page 2: 25 items (Total: 25, Remaining: 97)
  Page 3: 25 items (Total: 50, Remaining: 96)
  → Saved batch: 50 SKUs (Total saved: 100) ✅
  
  ... continues successfully ...
```

### Verify Data Inserted

```bash
php artisan tinker
```

```php
// Check count
\App\Models\WalmartPricingSales::count(); // Should be > 0

// Check latest records
\App\Models\WalmartPricingSales::latest()->take(5)->get(['sku', 'item_name', 'current_price', 'l30_qty']);

// Check specific SKU
\App\Models\WalmartPricingSales::where('sku', 'YOUR-SKU')->first();
```

---

## 🔍 Troubleshooting

### If migration fails with "Table already exists"

```bash
php artisan tinker
```
```php
// Drop the old table
Schema::dropIfExists('walmart_pricing');
exit;
```

Then run migration again:
```bash
php artisan migrate
```

### If data still not inserting

Check logs:
```bash
tail -f storage/logs/laravel.log | grep -i "walmart\|upsert"
```

Should NOT see:
- ❌ "Column not found"
- ❌ "Unknown column"

Should see:
- ✅ Successful inserts
- ✅ No SQL errors

---

## 📁 Files Created/Modified

**Created:**
- ✅ `database/migrations/2026_01_25_060000_create_walmart_pricing_table.php`
- ✅ `WALMART_NEW_TABLE_SETUP.md` (this file)

**Modified:**
- ✅ `app/Models/WalmartPricingSales.php` - Points to `walmart_pricing`

**Deleted:**
- ✅ Old rename migration (not needed)

---

## Summary

**New Table:** `walmart_pricing` (fresh, with all 50+ columns)  
**Model:** `WalmartPricingSales` → `walmart_pricing`  
**Ready for:** Incremental saves during fetch  

**Next Steps:**
1. Drop old `walmart_pricing` table (if exists)
2. Run `php artisan migrate`
3. Run `php artisan walmart:pricing-sales`
4. Data will insert successfully! ✅
