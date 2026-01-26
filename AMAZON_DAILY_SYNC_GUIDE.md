# Amazon Orders Daily Sync System

## Overview

The Amazon Orders sync has been completely redesigned to sync data **day-by-day** instead of by period ranges. This ensures:

1. **Fresh Data**: Get regularly updated data without re-downloading everything
2. **Automatic Resume**: If sync fails, it resumes from the exact point of failure
3. **Day-Level Tracking**: Each day's sync is tracked independently
4. **Real-time Storage**: Data is saved to the database as it's fetched (page by page)
5. **Smart Recovery**: Failed days can be retried without affecting completed days

## Key Features

### 1. Daily-Based Sync
- Each calendar day is synced independently
- Track sync status for each individual day
- Skip already-completed days automatically

### 2. Automatic Resume on Failure
- If API rate limit is hit, sync pauses and saves progress
- Next run resumes from the exact page where it stopped
- No data is lost or re-fetched unnecessarily

### 3. Real-Time Data Storage
- Orders are inserted into database immediately after each API page
- No waiting for entire sync to complete
- Data is available even if sync is interrupted

### 4. Smart Status Tracking
- **Pending**: Day hasn't been synced yet
- **In Progress**: Currently syncing
- **Completed**: All orders for this day have been synced
- **Failed**: Sync encountered an error (can be retried)

## Quick Start

### Initialize Tracking (First Time Setup)

```bash
# Initialize tracking for last 90 days
php artisan app:fetch-amazon-orders --initialize-days=90
```

This creates sync records for each day. You only need to do this once.

### Daily Sync Operations

#### Sync Today's Orders
```bash
php artisan app:fetch-amazon-orders --daily
```

#### Sync Yesterday's Orders
```bash
php artisan app:fetch-amazon-orders --yesterday
```

#### Sync Last 30 Days
```bash
php artisan app:fetch-amazon-orders --last-days=30
```

#### Sync Specific Date Range
```bash
php artisan app:fetch-amazon-orders --from=2026-01-01 --to=2026-01-15
```

### Auto-Sync Mode (Recommended for Scheduled Jobs)

Auto-sync automatically processes all pending or failed days:

```bash
php artisan app:fetch-amazon-orders --auto-sync
```

**This is the recommended command for daily cron jobs** because it:
- Automatically finds days that need syncing
- Processes them in chronological order
- Stops gracefully if rate limit is hit
- Continues from where it left off on next run

### View Sync Status

```bash
php artisan app:fetch-amazon-orders --status
```

This shows a table with:
- Date
- Status (✅ completed, ❌ failed, ⏳ in progress, ⏸️ pending)
- Orders fetched
- Pages fetched
- Last update time
- Error message (if any)

### Re-sync Operations

#### Re-sync a Specific Date
```bash
php artisan app:fetch-amazon-orders --resync-date=2026-01-15
```

This resets the day and syncs it from scratch.

#### Re-sync Last N Days
```bash
php artisan app:fetch-amazon-orders --resync-last-days=7
```

Useful for ensuring fresh data for recent days.

## How It Works

### Daily Sync Process

1. **Day Selection**: Command determines which day(s) to sync
2. **Record Check**: Checks if day already has a sync record
3. **Resume or Start**: 
   - If day is already completed: Skips it
   - If day is in progress or failed: Resumes from saved NextToken
   - If day is pending: Starts fresh sync
4. **Page-by-Page Fetch**:
   - Fetches 100 orders per page from Amazon API
   - Saves orders to database immediately
   - Updates sync progress after each page
   - Saves NextToken for resume capability
5. **Completion**: Marks day as completed when all pages are fetched

### Failure Handling

If sync fails (e.g., rate limit, network error):

1. **Current Progress Saved**: 
   - Orders already fetched are in database
   - NextToken is saved for resume
   - Error message is logged

2. **Next Run Automatically Resumes**:
   - Reads saved NextToken
   - Continues from next page
   - No duplicate data or wasted API calls

3. **Retry Strategy**:
   - Auto-sync will retry failed days
   - Exponential backoff for transient errors
   - Manual retry with `--resync-date` for persistent errors

## Database Schema

### `amazon_daily_syncs` Table

| Column | Type | Description |
|--------|------|-------------|
| `sync_date` | date | The day being synced (California/Pacific timezone) |
| `status` | enum | pending, in_progress, completed, failed |
| `started_at` | timestamp | When sync started |
| `completed_at` | timestamp | When sync completed |
| `last_page_at` | timestamp | Last API page fetch time |
| `next_token` | text | Amazon's pagination token (for resume) |
| `orders_fetched` | integer | Number of new orders inserted |
| `pages_fetched` | integer | Number of API pages processed |
| `error_message` | text | Error details if failed |
| `retry_count` | integer | Number of retry attempts |

## Recommended Cron Schedule

### For Daily Fresh Data

```bash
# Run at 2 AM every day (auto-syncs all pending/failed days)
0 2 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --auto-sync

# Run at 10 AM to catch today's new orders
0 10 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --daily

# Run at 6 PM for another update
0 18 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --daily
```

### For Hourly Updates (Today Only)

```bash
# Every hour during business hours
0 9-18 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --daily
```

## Migration from Old System

If you were using the old cursor-based system:

1. **Initialize Daily Tracking**:
   ```bash
   php artisan app:fetch-amazon-orders --initialize-days=90
   ```

2. **Sync All Pending Days**:
   ```bash
   php artisan app:fetch-amazon-orders --auto-sync
   ```

3. **Update Cron Jobs**: Replace old commands with new auto-sync approach

4. **Old Data**: The old `amazon_order_cursors` table can remain for historical reference

## Advanced Options

### Control API Rate Limiting

```bash
# Increase delay between pages (default: 3 seconds)
php artisan app:fetch-amazon-orders --daily --delay=5

# Increase max retries for failed pages (default: 3)
php artisan app:fetch-amazon-orders --daily --max-retries=5
```

### Fetch Missing Items

For orders that were created but don't have line items:

```bash
php artisan app:fetch-amazon-orders --fetch-missing-items
```

### Fix Zero-Price Items

For items with $0 price (tries to fix from API or product_master):

```bash
php artisan app:fetch-amazon-orders --fix-zero-prices
```

## Troubleshooting

### "All days already completed"

If you see this message but want fresh data:

```bash
# Re-sync recent days
php artisan app:fetch-amazon-orders --resync-last-days=7
```

### Sync Stuck in "in_progress"

This can happen if process was killed:

```bash
# View status to find the date
php artisan app:fetch-amazon-orders --status

# Re-sync that specific date
php artisan app:fetch-amazon-orders --resync-date=2026-01-15
```

### Rate Limit Errors

The system handles these automatically:

1. Saves progress when rate limit hit
2. Marks day as "failed" with error message
3. Next run with `--auto-sync` will retry automatically

Wait a few hours and run:
```bash
php artisan app:fetch-amazon-orders --auto-sync
```

### Check What's Happening

```bash
# View detailed status
php artisan app:fetch-amazon-orders --status

# Check database directly
mysql> SELECT sync_date, status, orders_fetched, error_message 
       FROM amazon_daily_syncs 
       WHERE status != 'completed' 
       ORDER BY sync_date DESC;
```

## Best Practices

1. **Use Auto-Sync for Automation**
   - Set up `--auto-sync` in cron
   - Let it handle pending/failed days automatically

2. **Fresh Data Strategy**
   - Sync today multiple times per day with `--daily`
   - Re-sync yesterday once per day to catch late updates
   - Use `--auto-sync` to backfill any gaps

3. **Monitor Sync Status**
   - Periodically check `--status`
   - Set up alerts for failed days
   - Re-sync failed days when issue is resolved

4. **Handle Rate Limits**
   - Don't run multiple sync commands simultaneously
   - Use appropriate `--delay` values (3-5 seconds)
   - Spread out cron jobs throughout the day

5. **Initialize New Days Proactively**
   - Run `--initialize-days` monthly to track future dates
   - Or let auto-sync create records as needed

## Examples

### Complete Setup from Scratch

```bash
# Step 1: Initialize tracking for last 90 days
php artisan app:fetch-amazon-orders --initialize-days=90

# Step 2: Sync all pending days (this might take a while)
php artisan app:fetch-amazon-orders --auto-sync

# Step 3: Check status
php artisan app:fetch-amazon-orders --status

# Step 4: Set up daily cron job
# Add to crontab:
# 0 2 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --auto-sync
```

### Daily Maintenance

```bash
# Morning: Check status and sync pending days
php artisan app:fetch-amazon-orders --status
php artisan app:fetch-amazon-orders --auto-sync

# Throughout day: Update today's orders
php artisan app:fetch-amazon-orders --daily

# Evening: Final sync and re-sync yesterday
php artisan app:fetch-amazon-orders --daily
php artisan app:fetch-amazon-orders --resync-date=$(date -d "yesterday" +%Y-%m-%d)
```

### Backfill Missing Data

```bash
# Find missing date range
php artisan app:fetch-amazon-orders --status

# Sync specific range
php artisan app:fetch-amazon-orders --from=2025-12-01 --to=2025-12-31

# Or initialize and auto-sync
php artisan app:fetch-amazon-orders --initialize-days=365
php artisan app:fetch-amazon-orders --auto-sync
```

## Summary

The new daily-based sync system provides:

- ✅ **Reliability**: Never lose progress, always resume from failure point
- ✅ **Fresh Data**: Sync individual days without re-downloading everything
- ✅ **Efficiency**: Skip completed days, only fetch what's needed
- ✅ **Visibility**: Clear status tracking for each day
- ✅ **Automation**: Set it and forget it with `--auto-sync`

For most users, the recommended approach is:

1. Initialize once: `--initialize-days=90`
2. Daily cron: `--auto-sync` (handles all pending/failed days)
3. Intraday updates: `--daily` (for today's fresh orders)
4. Weekly check: `--status` (monitor sync health)
