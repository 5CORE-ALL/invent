# Amazon Orders Sync - Changes Summary

## What Changed?

The Amazon Orders sync command has been completely redesigned to sync data **day-by-day** instead of by large period ranges. This solves all the issues you mentioned:

### Problems Fixed

1. ✅ **No more period column-wise sync** - Now syncs one day at a time
2. ✅ **Data stored during runtime** - Orders saved to DB immediately after each API page
3. ✅ **Resume from failure point** - Never starts from beginning after failure
4. ✅ **Fresh data regularly** - Easy to re-sync individual days without affecting others

## New Files Created

### 1. Model: `app/Models/AmazonDailySync.php`
- Tracks sync status for each individual day
- Fields: sync_date, status, orders_fetched, pages_fetched, next_token, error_message, etc.
- Statuses: pending, in_progress, completed, failed

### 2. Migration: `database/migrations/2026_01_27_015828_create_amazon_daily_syncs_table.php`
- Creates `amazon_daily_syncs` table
- Already migrated successfully

### 3. Documentation: `AMAZON_DAILY_SYNC_GUIDE.md`
- Complete guide on how to use the new system
- Examples, best practices, troubleshooting

## Modified Files

### 1. `app/Console/Commands/FetchAmazonOrders.php`
- **Old approach**: Synced large date ranges using cursors
- **New approach**: Syncs one day at a time with individual tracking

**New Command Options:**
- `--auto-sync` - Automatically sync all pending/failed days (recommended for cron)
- `--resync-date=2026-01-15` - Re-sync a specific date
- `--resync-last-days=7` - Re-sync last N days
- `--initialize-days=90` - Initialize tracking for N days
- `--status` - Show sync status table

**Kept Options:**
- `--daily` - Sync today
- `--yesterday` - Sync yesterday
- `--last-days=30` - Sync last N days
- `--from/--to` - Sync date range
- `--fetch-missing-items` - Fetch missing order items
- `--fix-zero-prices` - Fix items with $0 price

**Removed Options:**
- `--reset-cursor` - No longer needed (replaced with `--resync-date`)
- `--new-only` - No longer needed (use `--auto-sync` instead)
- `--limit` - Removed (not compatible with day-based sync)

## How It Works Now

### Day-by-Day Sync Process

```
Day 1 (2026-01-01)
  └─ Fetch Page 1 → Save to DB → Update progress
  └─ Fetch Page 2 → Save to DB → Update progress
  └─ Fetch Page 3 → Save to DB → Mark as COMPLETED
  
Day 2 (2026-01-02)
  └─ Fetch Page 1 → Save to DB → Update progress
  └─ RATE LIMIT HIT! → Mark as FAILED → Save NextToken
  
NEXT RUN:
  └─ Skip Day 1 (already completed)
  └─ Resume Day 2 from Page 2 using saved NextToken
     └─ Fetch Page 2 → Save to DB → Update progress
     └─ Fetch Page 3 → Save to DB → Mark as COMPLETED
```

### Real-Time Data Storage

```
OLD WAY:
- Fetch all pages into memory (risk of data loss)
- Save everything at the end
- If crash: lose all progress

NEW WAY:
- Fetch Page 1 → Save 100 orders to DB immediately
- Fetch Page 2 → Save 100 orders to DB immediately
- Fetch Page 3 → Save 100 orders to DB immediately
- If crash: Already have 200 orders, just resume from Page 3
```

### Automatic Resume

```
amazon_daily_syncs table:

| sync_date  | status      | next_token | orders | pages | error_message |
|------------|-------------|------------|--------|-------|---------------|
| 2026-01-01 | completed   | NULL       | 245    | 3     | NULL          |
| 2026-01-02 | failed      | eyJtYXJ... | 156    | 2     | Rate limit... |
| 2026-01-03 | pending     | NULL       | 0      | 0     | NULL          |

When you run --auto-sync:
1. Skips 2026-01-01 (already completed)
2. Resumes 2026-01-02 from page 3 (using saved next_token)
3. Then syncs 2026-01-03 from beginning
```

## Quick Start Guide

### Step 1: Initialize Tracking (One-Time Setup)

```bash
php artisan app:fetch-amazon-orders --initialize-days=90
```

This creates a sync record for each of the last 90 days.

### Step 2: Sync All Pending Days

```bash
php artisan app:fetch-amazon-orders --auto-sync
```

This will sync all pending days. If it hits rate limit, just run again later.

### Step 3: Check Status

```bash
php artisan app:fetch-amazon-orders --status
```

You'll see a table showing status of each day.

### Step 4: Set Up Daily Cron Job

```bash
# Add to crontab
0 2 * * * cd /Applications/XAMPP/xamppfiles/htdocs/invent && php artisan app:fetch-amazon-orders --auto-sync
```

## Common Usage Patterns

### Daily Operations (Recommended)

```bash
# Morning: Auto-sync any pending/failed days
php artisan app:fetch-amazon-orders --auto-sync

# Throughout the day: Update today's orders (run multiple times)
php artisan app:fetch-amazon-orders --daily

# Evening: Re-sync yesterday to catch late updates
php artisan app:fetch-amazon-orders --resync-date=$(date -d "yesterday" +%Y-%m-%d)
```

### Ensure Fresh Data

```bash
# Re-sync last 7 days to ensure latest data
php artisan app:fetch-amazon-orders --resync-last-days=7
```

### Backfill Missing Dates

```bash
# Sync specific date range
php artisan app:fetch-amazon-orders --from=2025-12-01 --to=2025-12-31
```

### Monitor Sync Health

```bash
# View status table
php artisan app:fetch-amazon-orders --status

# Check for failed days
php artisan app:fetch-amazon-orders --status | grep "❌"
```

## Database Changes

### New Table: `amazon_daily_syncs`

```sql
CREATE TABLE `amazon_daily_syncs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sync_date` date NOT NULL,
  `status` enum('pending','in_progress','completed','failed','skipped'),
  `started_at` timestamp NULL,
  `completed_at` timestamp NULL,
  `last_page_at` timestamp NULL,
  `next_token` text NULL,
  `orders_fetched` int DEFAULT 0,
  `pages_fetched` int DEFAULT 0,
  `error_message` text NULL,
  `retry_count` int DEFAULT 0,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sync_date` (`sync_date`),
  KEY `status` (`status`),
  KEY `sync_date_status` (`sync_date`,`status`)
);
```

### Existing Tables (Unchanged)

- `amazon_orders` - Still stores all orders (no changes)
- `amazon_order_items` - Still stores order line items (no changes)
- `amazon_order_cursors` - Can be kept for historical reference or deleted

## Benefits

### 1. Reliability
- Never lose progress on API failures
- Always resume from exact point of failure
- Data saved in real-time (no risk of data loss)

### 2. Efficiency
- Skip already-completed days automatically
- Only fetch what's needed
- No redundant API calls

### 3. Fresh Data
- Re-sync individual days without affecting others
- Easy to update recent days multiple times per day
- Flexible date-specific syncing

### 4. Visibility
- Clear status tracking for each day
- See exactly which days need attention
- Easy troubleshooting with status table

### 5. Automation
- `--auto-sync` handles everything automatically
- Perfect for cron jobs
- Self-recovering on transient errors

## Comparison: Old vs New

| Feature | Old System | New System |
|---------|-----------|------------|
| **Sync granularity** | Large date ranges | Individual days |
| **Data storage** | At end of sync | Real-time (per page) |
| **Resume capability** | By date range | By individual day + page |
| **Fresh data** | Re-sync entire range | Re-sync specific days |
| **Status tracking** | Generic cursors | Per-day with details |
| **Automation** | Manual date ranges | Auto-sync pending days |
| **Failure recovery** | Start from scratch | Continue from exact page |
| **Progress visibility** | Limited | Detailed status table |

## Migration Notes

### For Users of Old System

1. **Data Safety**: All existing data in `amazon_orders` table is safe
2. **Old Cursors**: The `amazon_order_cursors` table can remain or be archived
3. **New Tracking**: Initialize tracking with `--initialize-days` to start using new system
4. **Cron Jobs**: Update your scheduled tasks to use `--auto-sync`

### No Breaking Changes

The command still works with familiar options like `--daily`, `--yesterday`, etc. 
You can start using the new features gradually.

## Testing the New System

```bash
# 1. Initialize tracking for last 7 days
php artisan app:fetch-amazon-orders --initialize-days=7

# 2. Check status (should show 7 pending days)
php artisan app:fetch-amazon-orders --status

# 3. Sync today only (test single day sync)
php artisan app:fetch-amazon-orders --daily

# 4. Check status (today should be completed or in-progress)
php artisan app:fetch-amazon-orders --status

# 5. Sync all pending days
php artisan app:fetch-amazon-orders --auto-sync

# 6. Final status check (should see completed days)
php artisan app:fetch-amazon-orders --status
```

## Support

For detailed information, see:
- **User Guide**: `AMAZON_DAILY_SYNC_GUIDE.md`
- **Command Help**: `php artisan app:fetch-amazon-orders --help`
- **Status Check**: `php artisan app:fetch-amazon-orders --status`

## Summary

The new daily-based sync system gives you:

✅ **Reliable** - Never lose progress  
✅ **Efficient** - Only fetch what's needed  
✅ **Fresh** - Easy to update individual days  
✅ **Automated** - Set and forget with --auto-sync  
✅ **Visible** - Clear status tracking for all days  

**Recommended for daily use:**
```bash
php artisan app:fetch-amazon-orders --auto-sync
```
