# Amazon Orders Sync - With Items Feature

## Overview

The `--with-items` flag has been added to automatically fetch order items (line items/products) during the daily sync process. This gives you complete order data including all SKUs, quantities, and prices in a single command.

## What Are Order Items?

- **Orders**: The order header (order ID, date, total amount, customer info)
- **Order Items**: The individual products/line items within each order (SKU, ASIN, quantity, price, title)

Previously, these required separate API calls and commands. Now you can get both in one go!

## Usage

### Basic Usage

```bash
# Sync today's orders AND their items
php artisan app:fetch-amazon-orders --daily --with-items

# Sync yesterday's orders AND their items
php artisan app:fetch-amazon-orders --yesterday --with-items

# Sync last 7 days with items
php artisan app:fetch-amazon-orders --last-days=7 --with-items
```

### Auto-Sync with Items

```bash
# Auto-sync all pending/failed days with items
php artisan app:fetch-amazon-orders --auto-sync --with-items
```

### Re-sync with Items

```bash
# Re-sync a specific date with items
php artisan app:fetch-amazon-orders --resync-date=2026-01-15 --with-items

# Re-sync last 7 days with items
php artisan app:fetch-amazon-orders --resync-last-days=7 --with-items
```

## How It Works

### Without `--with-items` (Default)

```
1. Fetch orders from Amazon Orders API
2. Save orders to `amazon_orders` table
3. Done ✅

To get items later:
php artisan app:fetch-amazon-orders --fetch-missing-items
```

### With `--with-items`

```
1. Fetch orders from Amazon Orders API
2. Save orders to `amazon_orders` table
3. For each order:
   a. Fetch items from Amazon Order Items API
   b. Save items to `amazon_order_items` table immediately
4. Done ✅ (Complete data in one run!)
```

## Database Storage

### Orders Table: `amazon_orders`
- `id`, `amazon_order_id`, `order_date`, `status`, `total_amount`, `currency`, `raw_data`

### Order Items Table: `amazon_order_items`
- `id`, `amazon_order_id` (FK), `asin`, `sku`, `quantity`, `price`, `currency`, `title`, `raw_data`

### Sync Tracking: `amazon_daily_syncs`
- Now includes `items_fetched` column to track how many items were fetched

## Status Display

When you run `--status`, you'll see the Items column:

```
📊 Sync Status (Last 90 days):

+------------+--------------+--------+-------+-------+
| Date       | Status       | Orders | Items | Pages |
+------------+--------------+--------+-------+-------+
| 2026-01-15 | ✅ completed | 156    | 342   | 2     |
| 2026-01-14 | ✅ completed | 234    | 512   | 3     |
| 2026-01-13 | ⏸️ pending   | 0      | 0     | 0     |
+------------+--------------+--------+-------+-------+
```

- **Orders**: Number of orders fetched
- **Items**: Number of order items (line items) fetched

## Performance Considerations

### API Rate Limits

Amazon has separate rate limits for:
- **Orders API**: Get order headers
- **Order Items API**: Get items for each order (one API call per order)

**Example:**
- 100 orders = 1 Orders API call + 100 Order Items API calls

### Timing

The command automatically adds delays between item requests:
- 500ms delay between each order's item fetch
- Helps stay within Amazon's rate limits

### Estimated Time

Approximate sync time with `--with-items`:

| Orders | Without Items | With Items |
|--------|---------------|------------|
| 50     | ~30 seconds   | ~1 minute  |
| 100    | ~1 minute     | ~2 minutes |
| 500    | ~5 minutes    | ~10 minutes|

## When to Use `--with-items`

### ✅ Use `--with-items` When:

1. **You need complete order data** - SKUs, quantities, prices
2. **Initial sync** - Getting all historical data
3. **Daily comprehensive sync** - Want everything in one command
4. **You have API quota available** - Not hitting rate limits

### ❌ Don't Use `--with-items` When:

1. **Quick order count check** - Just need to know how many orders
2. **Limited API quota** - Save quota for order headers only
3. **You'll fetch items separately** - Using `--fetch-missing-items` later

## Examples

### Example 1: Complete Daily Sync

```bash
# Morning: Sync yesterday with complete data
php artisan app:fetch-amazon-orders --yesterday --with-items

# Result:
# ✅ Orders: 156
# ✅ Items: 342 (all SKUs, prices, quantities saved)
```

### Example 2: Backfill Historical Data

```bash
# Get last 30 days with complete data
php artisan app:fetch-amazon-orders --last-days=30 --with-items

# Result:
# ✅ 30 days of orders
# ✅ All order items for each order
# ✅ Complete product-level data
```

### Example 3: Selective Item Fetching

```bash
# Sync last 7 days (orders only - fast)
php artisan app:fetch-amazon-orders --last-days=7

# Then fetch items only for orders missing them
php artisan app:fetch-amazon-orders --fetch-missing-items

# Advantage: Can inspect orders first, decide which need items
```

### Example 4: Daily Automation

```bash
# Cron job: Complete daily sync with items
0 2 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --auto-sync --with-items
```

## Troubleshooting

### Rate Limit Errors

If you hit rate limits when using `--with-items`:

```bash
# System automatically saves progress
# Just run again later - it will resume

php artisan app:fetch-amazon-orders --auto-sync --with-items
```

The sync will:
1. Resume from where it stopped
2. Skip already-fetched items
3. Continue with remaining orders

### Missing Items for Some Orders

If some orders don't have items after sync:

```bash
# Fetch items for orders that are missing them
php artisan app:fetch-amazon-orders --fetch-missing-items
```

This is safe to run anytime and only fetches missing items.

### Verify Items Were Fetched

```sql
-- Check if items exist for a specific order
SELECT oi.* FROM amazon_order_items oi
JOIN amazon_orders o ON oi.amazon_order_id = o.id
WHERE o.amazon_order_id = 'YOUR-AMAZON-ORDER-ID';

-- Count orders with/without items
SELECT 
  COUNT(*) as total_orders,
  COUNT(DISTINCT oi.amazon_order_id) as orders_with_items,
  COUNT(*) - COUNT(DISTINCT oi.amazon_order_id) as orders_without_items
FROM amazon_orders o
LEFT JOIN amazon_order_items oi ON o.id = oi.amazon_order_id;
```

## API Quota Management

### Strategy 1: Sync Without Items Daily, With Items Weekly

```bash
# Daily (fast, low API usage)
0 2 * * 1-6 cd /path/to/invent && php artisan app:fetch-amazon-orders --auto-sync

# Weekly (complete data)
0 3 * * 0 cd /path/to/invent && php artisan app:fetch-amazon-orders --resync-last-days=7 --with-items
```

### Strategy 2: Items Only for Recent Orders

```bash
# Sync all orders
php artisan app:fetch-amazon-orders --last-days=30

# Fetch items only for last 7 days
php artisan app:fetch-amazon-orders --resync-last-days=7 --with-items
```

### Strategy 3: Complete Daily Sync (If Quota Allows)

```bash
# Everything in one go
0 2 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --auto-sync --with-items
```

## Data Structure Example

### Order (without items)

```json
{
  "id": 123,
  "amazon_order_id": "111-2222222-3333333",
  "order_date": "2026-01-15",
  "status": "Shipped",
  "total_amount": 45.97,
  "currency": "USD"
}
```

### Order Items (with `--with-items`)

```json
[
  {
    "id": 1,
    "amazon_order_id": 123,
    "asin": "B08XYZ1234",
    "sku": "MY-SKU-001",
    "quantity": 2,
    "price": 12.99,
    "currency": "USD",
    "title": "Product Name Here"
  },
  {
    "id": 2,
    "amazon_order_id": 123,
    "asin": "B08ABC5678",
    "sku": "MY-SKU-002",
    "quantity": 1,
    "price": 19.99,
    "currency": "USD",
    "title": "Another Product"
  }
]
```

## Best Practices

### 1. Start with `--with-items` for Initial Setup

```bash
# Get complete historical data
php artisan app:fetch-amazon-orders --initialize-days=90
php artisan app:fetch-amazon-orders --auto-sync --with-items
```

### 2. Use for Daily Fresh Data

```bash
# Update today's orders multiple times with complete data
php artisan app:fetch-amazon-orders --daily --with-items
```

### 3. Monitor API Usage

Check Amazon's API usage dashboard regularly to ensure you're within quotas.

### 4. Batch Item Fetching for Old Orders

```bash
# If you have many orders without items
php artisan app:fetch-amazon-orders --fetch-missing-items
```

This is more efficient as it only targets orders missing items.

## Migration Guide

### If You Previously Synced Without Items

```bash
# Step 1: Check how many orders are missing items
php artisan app:fetch-amazon-orders --status

# Step 2: Fetch items for all existing orders
php artisan app:fetch-amazon-orders --fetch-missing-items

# Step 3: Use --with-items for future syncs
php artisan app:fetch-amazon-orders --daily --with-items
```

### Update Your Cron Jobs

**Old:**
```bash
0 2 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --auto-sync
```

**New:**
```bash
0 2 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --auto-sync --with-items
```

## Summary

### ✅ Benefits of `--with-items`

1. **Complete data in one command** - No need for separate steps
2. **Real-time item storage** - Items saved as orders are fetched
3. **Auto-resume on failure** - Picks up where it left off
4. **Progress tracking** - See items_fetched in status table
5. **Full product details** - SKU, ASIN, price, quantity, title

### 📊 Quick Reference

| Command | What It Does |
|---------|-------------|
| `--daily --with-items` | Today's orders + items |
| `--auto-sync --with-items` | All pending days + items |
| `--resync-last-days=7 --with-items` | Re-fetch last 7 days + items |
| `--fetch-missing-items` | Only fetch items for orders without items |

### 🎯 Recommended Setup

```bash
# Daily comprehensive sync (if API quota allows)
0 2 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --auto-sync --with-items

# Or: Daily orders, weekly items (if quota is limited)
0 2 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --auto-sync
0 3 * * 0 cd /path/to/invent && php artisan app:fetch-amazon-orders --resync-last-days=7 --with-items
```

---

**Ready to use?** Try it now:

```bash
php artisan app:fetch-amazon-orders --daily --with-items
```
