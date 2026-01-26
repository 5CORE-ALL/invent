# Amazon Orders Sync - Quick Start

## ✅ What's Done

Your Amazon Orders sync command has been **completely redesigned** with these improvements:

1. **✅ Daily-based sync** - No more period columns, sync by date
2. **✅ Real-time storage** - Data saved during runtime (page by page)
3. **✅ Auto-resume** - Never starts from beginning after failure
4. **✅ Fresh data** - Re-sync any day independently

## 🚀 Get Started in 3 Steps

### Step 1: Initialize Tracking (One-Time Setup)

```bash
php artisan app:fetch-amazon-orders --initialize-days=90
```

This creates tracking for the last 90 days. **You only need to do this once.**

### Step 2: Sync Your Data

```bash
php artisan app:fetch-amazon-orders --auto-sync
```

This automatically syncs all pending/failed days. **Run this command daily in cron.**

### Step 3: Check Status

```bash
php artisan app:fetch-amazon-orders --status
```

See which days are completed, failed, or pending.

## 📅 Daily Usage

### For Fresh Data Throughout the Day

```bash
# Sync today's orders (run as many times as you want)
php artisan app:fetch-amazon-orders --daily

# Sync today's orders WITH items (complete data)
php artisan app:fetch-amazon-orders --daily --with-items
```

Safe to run every hour - only fetches new data for today.

> **💡 New Feature:** Use `--with-items` to automatically fetch order items (SKUs, quantities, prices) along with orders. See `AMAZON_WITH_ITEMS_FEATURE.md` for details.

### For Automated Daily Sync (Recommended)

```bash
# Add to crontab:
0 2 * * * cd /Applications/XAMPP/xamppfiles/htdocs/invent && php artisan app:fetch-amazon-orders --auto-sync
```

This runs at 2 AM every day and syncs all pending/failed days automatically.

## 🔧 Common Operations

### Re-sync Recent Days for Fresh Data

```bash
# Re-sync last 7 days
php artisan app:fetch-amazon-orders --resync-last-days=7
```

### Re-sync a Specific Date

```bash
# Re-sync January 15, 2026
php artisan app:fetch-amazon-orders --resync-date=2026-01-15
```

### Sync Specific Date Range

```bash
# Sync December 2025
php artisan app:fetch-amazon-orders --from=2025-12-01 --to=2025-12-31
```

### Check What's Happening

```bash
# View detailed status table
php artisan app:fetch-amazon-orders --status

# View all available options
php artisan app:fetch-amazon-orders --help
```

## ⚡ How It Works

### Real-Time Data Storage

```
Old Way: Fetch all → Store at end → If crash, lose everything
New Way: Fetch page 1 → Store immediately → Fetch page 2 → Store immediately → etc.
```

**Your data is safe even if the command crashes!**

### Auto-Resume on Failure

```
Day 1: ✅ Completed (245 orders)
Day 2: ❌ Failed at page 3 (Rate limit hit)
Day 3: ⏸️ Pending

Run --auto-sync again:
  → Skips Day 1 (already done)
  → Resumes Day 2 from page 3 (no duplicate data)
  → Then syncs Day 3
```

**Never waste API calls re-fetching data!**

## 📊 Example Output

When you run `--status`:

```
📊 Sync Status (Last 90 days):

+------------+--------------+--------+-------+-------------------+
| Date       | Status       | Orders | Pages | Last Update       |
+------------+--------------+--------+-------+-------------------+
| 2026-01-26 | ✅ completed | 156    | 2     | 5 minutes ago     |
| 2026-01-25 | ✅ completed | 234    | 3     | 1 hour ago        |
| 2026-01-24 | ✅ completed | 189    | 2     | 2 hours ago       |
| 2026-01-23 | ❌ failed    | 45     | 1     | 3 hours ago       |
| 2026-01-22 | ⏸️ pending   | 0      | 0     | -                 |
+------------+--------------+--------+-------+-------------------+

Summary:
  ✅ Completed: 3
  ❌ Failed: 1
  ⏸️ Pending: 1
```

## 🎯 Recommended Setup

### For Regular Fresh Data

Set up these cron jobs:

```bash
# Main sync - runs at 2 AM daily
0 2 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --auto-sync

# Update today's orders - runs every 2 hours during business hours
0 8-18/2 * * * cd /path/to/invent && php artisan app:fetch-amazon-orders --daily

# Weekly fresh data - re-sync last 7 days every Sunday
0 3 * * 0 cd /path/to/invent && php artisan app:fetch-amazon-orders --resync-last-days=7
```

### For Manual Operations

```bash
# Morning routine
php artisan app:fetch-amazon-orders --status           # Check status
php artisan app:fetch-amazon-orders --auto-sync        # Sync pending days

# Throughout the day
php artisan app:fetch-amazon-orders --daily            # Update today

# If you need specific data fresh
php artisan app:fetch-amazon-orders --resync-date=2026-01-15
```

## 🛠️ Troubleshooting

### Rate Limit Hit?

**No problem!** The system automatically saves progress.

```bash
# Just run again later (it will resume automatically)
php artisan app:fetch-amazon-orders --auto-sync
```

### Day Stuck in "in_progress"?

Process was killed. Just re-sync it:

```bash
php artisan app:fetch-amazon-orders --resync-date=2026-01-15
```

### Want to See Failed Days?

```bash
# View status and look for ❌
php artisan app:fetch-amazon-orders --status | grep "❌"
```

## 📚 More Information

For detailed documentation, see:
- **Complete Guide**: `AMAZON_DAILY_SYNC_GUIDE.md`
- **What Changed**: `AMAZON_SYNC_CHANGES.md`
- **Command Help**: `php artisan app:fetch-amazon-orders --help`

## ✨ Key Benefits

| Feature | Benefit |
|---------|---------|
| **Daily Sync** | Get fresh data for any specific day |
| **Auto-Resume** | Never lose progress on failures |
| **Real-Time Storage** | Data saved immediately, no waiting |
| **Smart Skip** | Automatically skips completed days |
| **Status Tracking** | See exactly what's happening |
| **Flexible** | Re-sync any day anytime |

## 🎉 You're All Set!

The system is ready to use. Start with:

```bash
# 1. Initialize (if not done)
php artisan app:fetch-amazon-orders --initialize-days=90

# 2. Sync all pending days
php artisan app:fetch-amazon-orders --auto-sync

# 3. Set up daily cron (recommended)
# Add this to your crontab:
0 2 * * * cd /Applications/XAMPP/xamppfiles/htdocs/invent && php artisan app:fetch-amazon-orders --auto-sync
```

**That's it!** Your Amazon orders will be synced automatically every day with fresh data.

## 🔥 Pro Tips

1. **Use --auto-sync for automation** - It handles everything automatically
2. **Run --daily multiple times** - Safe to run as often as you want for today's data
3. **Check --status regularly** - Keep an eye on sync health
4. **Re-sync recent days weekly** - Ensures you have the freshest data
5. **Never worry about failures** - System automatically resumes where it left off

---

**Questions?** Check the full guide in `AMAZON_DAILY_SYNC_GUIDE.md`
