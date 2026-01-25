# Walmart Simplified Architecture

## ✅ Changes Applied

**Removed apicentral sync** - All data stays in local `walmart_pricing` table only!

---

## 🔄 New Data Flow (Simplified)

```
┌─────────────────────────────────────┐
│  WALMART MARKETPLACE API            │
│  (marketplace.walmartapis.com)      │
└──────────────┬──────────────────────┘
               │
               │ Direct API Calls
               │ (OAuth authenticated)
               ↓
┌─────────────────────────────────────┐
│  LARAVEL COMMAND                    │
│  walmart:pricing-sales              │
└──────────────┬──────────────────────┘
               │
               │ Save Data
               ↓
┌─────────────────────────────────────┐
│  DATABASE: invent                   │
│  TABLE: walmart_pricing             │
│  (All Walmart data - 50+ columns)   │
└─────────────────────────────────────┘

NO apicentral sync ✅
Single source of truth ✅
```

---

## 📊 Single Table Architecture

### Table: `walmart_pricing`

**Database:** `invent`  
**Purpose:** **Single source for ALL Walmart data**  
**Columns:** 50+  

**Contains:**
- ✅ Pricing data (from Walmart API)
- ✅ Listing quality (from Walmart API)
- ✅ Order metrics (from walmart_daily_data)
- ✅ Traffic & views
- ✅ Promotional data
- ✅ Everything in one place!

---

## 🚀 Benefits of Simplified Architecture

| Before | After | Benefit |
|--------|-------|---------|
| 2 databases (invent + apicentral) | 1 database (invent) | ✅ Simpler |
| 2 tables to maintain | 1 table | ✅ Less complexity |
| Sync delay possible | No sync needed | ✅ Always current |
| Duplicate data | Single source | ✅ No inconsistency |
| Extra DB connection | One connection | ✅ Faster |

---

## 📋 Commands Updated

### 1. `walmart:pricing-sales`

**Before:**
```php
Save to walmart_pricing ✓
Sync to apicentral.walmart_metrics ✓
```

**After:**
```php
Save to walmart_pricing ✓
// No apicentral sync ✅
```

### 2. `walmart:fetch-inventory`

**Before:**
```php
Save to product_stock_mappings ✓
Sync to apicentral.walmart_metrics ✓
```

**After:**
```php
Save to product_stock_mappings ✓
// No apicentral sync ✅
```

---

## 🗂️ Complete Table List

### Walmart Data Storage (All in `invent` database):

1. **`walmart_pricing`** ⭐ **PRIMARY TABLE**
   - All pricing, sales, traffic data
   - Source: Walmart API
   - 50+ columns

2. **`walmart_daily_data`**
   - Daily order details
   - Source: Walmart Orders API
   - Used for: L30/L60 calculations

3. **`product_stock_mappings`**
   - Inventory levels
   - Source: Walmart Inventory API
   - Column: `inventory_walmart`

4. **`walmart_price_data`**
   - Manual price uploads
   - Source: Excel/CSV uploads
   - Used by: WalmartSheetUploadController

5. **`walmart_listing_views_data`**
   - Manual listing uploads
   - Source: Excel/CSV uploads

---

## 📈 How to Use

### Get All Walmart Data

```php
// Everything in one table!
$data = \App\Models\WalmartPricingSales::where('l30_qty', '>', 0)->get();

// No need to join with apicentral ✅
```

### Get Summary Statistics

```php
// Calculate directly from walmart_pricing
$summary = \App\Models\WalmartPricingSales::selectRaw('
    COUNT(*) as total_products,
    SUM(l30_qty) as total_l30_qty,
    SUM(l30_revenue) as total_l30_revenue,
    AVG(current_price) as avg_price
')->first();
```

### Get Specific SKU

```php
$sku = \App\Models\WalmartPricingSales::where('sku', 'YOUR-SKU')->first();

// Has everything:
// - Pricing
// - Sales
// - Traffic
// - Orders
// All in one record ✅
```

---

## 🔧 Command Execution

```bash
php artisan walmart:pricing-sales
```

**Output:**
```
Fetching Walmart Pricing & Sales Data (Incremental Save Mode)...

Step 1/4: Calculating order counts...
  ✓ Calculated order counts for 400 SKUs

Step 2/4: Fetching pricing insights (saving as we go)...
  → Saved batch: 50 SKUs (Total saved: 50)
  → Saved to: walmart_pricing ✅
  
Step 3/4: Fetching listing quality (saving as we go)...
  → Updated batch: 100 SKUs (Total updated: 100)
  → Updated: walmart_pricing.page_views ✅

✓ All data in walmart_pricing table
✓ No apicentral sync (disabled)
```

---

## 🎯 Data Sources Summary

| Data | API Source | Saved To | Frequency |
|------|-----------|----------|-----------|
| Pricing | Walmart API | `walmart_pricing` | Every 3 hours |
| Listing Quality | Walmart API | `walmart_pricing` | Every 3 hours |
| Orders L30/L60 | Local (walmart_daily_data) | `walmart_pricing` | Calculated |
| Inventory | Walmart API | `product_stock_mappings` | Every 4 hours |

**All data from Walmart API → Stored locally → No apicentral dependency** ✅

---

## ✅ Summary

**Simplified Architecture:**
- ✅ One database (`invent`)
- ✅ One primary table (`walmart_pricing`)
- ✅ No apicentral sync
- ✅ Faster execution
- ✅ Single source of truth
- ✅ Easier maintenance

**Commands Updated:**
- ✅ `walmart:pricing-sales` - No apicentral sync
- ✅ `walmart:fetch-inventory` - No apicentral sync

**Ready to use:** Just run `php artisan walmart:pricing-sales` and all data goes to `walmart_pricing` table! 🎉
