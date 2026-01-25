
# Walmart Data Source Flow

## Where Does Data Come From?

---

## 📡 Data Source: **WALMART API** (Direct)

Your `walmart:pricing-sales` command fetches data **directly from Walmart Marketplace API**, NOT from apicentral.

---

## 🔄 Complete Data Flow

### Flow Diagram

```
┌─────────────────────────────────────────────────────┐
│  WALMART MARKETPLACE API (Walmart.com)              │
│  - Pricing Insights API                             │
│  - Listing Quality API                              │
│  - Orders API                                       │
└─────────────────────┬───────────────────────────────┘
                      │
                      │ Fetch via HTTP
                      ↓
┌─────────────────────────────────────────────────────┐
│  YOUR LARAVEL APP                                   │
│  Command: walmart:pricing-sales                     │
│  File: FetchWalmartPricingSales.php                 │
└─────────────────────┬───────────────────────────────┘
                      │
                      │ Process & Save
                      ↓
┌─────────────────────────────────────────────────────┐
│  DATABASE: invent                                   │
│  Table: walmart_pricing                             │
│  (50+ columns with all Walmart data)                │
└─────────────────────┬───────────────────────────────┘
                      │
                      │ Sync (Copy)
                      ↓
┌─────────────────────────────────────────────────────┐
│  DATABASE: apicentral                               │
│  Table: walmart_metrics                             │
│  (Summary: L30, L60, price, stock only)             │
└─────────────────────────────────────────────────────┘
```

---

## 📊 Data Sources in Detail

### 1. Pricing Data → **Walmart API**

**API Endpoint:**
```
POST https://marketplace.walmartapis.com/v3/price/getPricingInsights
```

**Fetches:**
- Current price
- Buy box prices
- Competitor prices
- Repricer settings
- GMV30
- Inventory count
- Traffic levels
- Sales rank

**Saved to:** `walmart_pricing` table

---

### 2. Listing Quality Data → **Walmart API**

**API Endpoint:**
```
POST https://marketplace.walmartapis.com/v3/insights/items/listingQuality/items
```

**Fetches:**
- Page views (actual view count)
- Quality scores
- Offer scores
- Content scores

**Saved to:** `walmart_pricing.page_views` column (updates existing records)

---

### 3. Order Counts → **Local Database** (walmart_daily_data)

**Source:** `walmart_daily_data` table (populated by `walmart:daily` command)

**Calculates:**
- L30 orders, quantity, revenue
- L60 orders, quantity, revenue

**Saved to:** `walmart_pricing` table (l30_*, l60_* columns)

---

### 4. Sync to apicentral → **Internal Copy**

After saving to `walmart_pricing`, a subset is copied to `apicentral.walmart_metrics`:

**Copied Fields:**
- `sku`
- `l30` (from l30_qty)
- `l30_amt` (from l30_revenue)
- `l60` (from l60_qty)
- `l60_amt` (from l60_revenue)
- `price` (from current_price)
- `stock` (from inventory_count)

**Purpose:** Sync database for external API access

---

## 🎯 Summary Table

| Data Type | Source | API/Database | Frequency |
|-----------|--------|--------------|-----------|
| **Pricing** | Walmart API | marketplace.walmartapis.com | Every 3 hours |
| **Listing Quality** | Walmart API | marketplace.walmartapis.com | Every 3 hours |
| **Orders L30/L60** | Local DB | walmart_daily_data | Calculated from daily |
| **Walmart Metrics Sync** | Local Copy | walmart_pricing → apicentral | After each fetch |

---

## 🔐 API Authentication

Your app uses:
- **Client ID:** `WALMART_CLIENT_ID` (from .env)
- **Client Secret:** `WALMART_CLIENT_SECRET` (from .env)
- **Auth Method:** OAuth 2.0 Client Credentials

**Token Flow:**
```
1. Request access token (15-20 min expiry)
2. Use token for API calls
3. Auto-refresh if expired
4. Rate limiter prevents hitting limits
```

---

## 📈 Command Execution Flow

```bash
php artisan walmart:pricing-sales
```

**Step-by-step:**

```
1. Get OAuth token from Walmart
   ↓
2. Calculate order counts from walmart_daily_data (local)
   ↓
3. Fetch pricing from Walmart API
   → Save every 50 SKUs to walmart_pricing ✅
   ↓
4. Fetch listing quality from Walmart API
   → Update walmart_pricing.page_views ✅
   ↓
5. Copy summary to apicentral.walmart_metrics ✅
   ↓
DONE - All data in walmart_pricing table
```

---

## 🗂️ Database Tables Used

### Primary Table: `walmart_pricing` (Main storage)

**Database:** `invent`  
**Purpose:** Store all Walmart pricing, sales, traffic data  
**Source:** Walmart API + calculated metrics  
**Columns:** 50+  

### Supporting Tables:

1. **`walmart_daily_data`** (Local)
   - Database: `invent`
   - Source: Walmart Orders API (populated by `walmart:daily` command)
   - Used for: L30/L60 order calculations

2. **`walmart_metrics`** (Sync copy)
   - Database: `apicentral`
   - Source: Copy from `walmart_pricing`
   - Used for: External API access

---

## ❓ FAQ

### Q: Is data from Walmart or apicentral?

**A: WALMART API** (direct from marketplace.walmartapis.com)

### Q: What is apicentral used for?

**A:** It's a sync copy (subset of data) for external API access. The source is still Walmart API.

### Q: Do I need apicentral connection?

**A:** Optional. The main data goes to `walmart_pricing`. The apicentral sync is just a bonus copy.

### Q: Can I disable apicentral sync?

**A:** Yes, comment out `updateWalmartMetrics()` in the command if not needed.

---

## ✅ Verification

Check data is from Walmart API:

```bash
php artisan walmart:pricing-sales
```

**You'll see:**
```
Access token received. ← OAuth from Walmart
Step 1/4: Calculating order counts... ← From local DB
Step 2/4: Fetching pricing insights... ← From WALMART API ✅
Step 3/4: Fetching listing quality... ← From WALMART API ✅
```

---

## Summary

**Data Source:** 🌐 **Walmart Marketplace API**  
**Primary Table:** 📊 `walmart_pricing` (all data)  
**Secondary Table:** 🔄 `apicentral.walmart_metrics` (summary copy)  
**Order Metrics:** 📈 Calculated from `walmart_daily_data`  

**Your data is fresh from Walmart API, not apicentral!** ✅
