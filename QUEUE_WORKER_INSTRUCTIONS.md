# Queue Worker Instructions - Shopify Price Push Background Retry

## Overview

The system now automatically retries failed Shopify B2C price pushes in the background using Laravel's queue system. When an Amazon price is updated successfully but the Shopify push fails, the job is automatically queued for retry with exponential backoff.

## Retry Configuration

- **Max Attempts**: 5 retries
- **Backoff Strategy**: Exponential (60s, 5min, 15min, 30min)
- **Timeout**: 120 seconds per attempt
- **Queue**: database (using `jobs` table)

## How It Works

1. User pushes Amazon price → Amazon API succeeds
2. Shopify push attempt → If it fails:
   - Status marked as "pending/retry" (⟳ yellow rotating icon)
   - Background job queued automatically
   - System retries up to 5 times with increasing delays
3. After successful retry:
   - Status updated to "pushed" (✓✓ green check)
   - Record saved in `shopifyb2c_data_view`
4. After all retries fail:
   - Status marked as "error" (✗ red X)
   - Manual intervention needed

## Running the Queue Worker

### For Development (XAMPP)

Open a new terminal and run:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/invent
php artisan queue:work --tries=5 --timeout=120
```

Keep this terminal running while using the application.

### For Production

Use a process supervisor like Supervisor to keep the queue worker running:

```bash
# Install Supervisor (on Ubuntu/Debian)
sudo apt-get install supervisor

# Create supervisor config
sudo nano /etc/supervisor/conf.d/laravel-queue-worker.conf
```

Add this configuration:

```ini
[program:laravel-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/artisan queue:work --tries=5 --timeout=120 --sleep=3 --max-jobs=1000
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-queue-worker:*
```

## Monitoring

### Check Queue Status

```bash
# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Retry specific job
php artisan queue:retry [job-id]

# Clear all failed jobs
php artisan queue:flush
```

### View Logs

Logs are stored in `storage/logs/laravel.log`:

```bash
tail -f storage/logs/laravel.log | grep "PushShopifyB2CPriceJob"
```

## Troubleshooting

### Queue Worker Not Running

If background retries aren't happening:

1. Check if queue worker is running:
   ```bash
   ps aux | grep "queue:work"
   ```

2. Start the queue worker:
   ```bash
   php artisan queue:work
   ```

### Jobs Stuck in Queue

Clear and restart:

```bash
# Clear all jobs
php artisan queue:clear

# Restart queue worker
php artisan queue:restart
```

### Check Jobs Table

```bash
# View pending jobs in MySQL
SELECT * FROM jobs ORDER BY created_at DESC LIMIT 10;

# View failed jobs
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;
```

## Status Icons in UI

- **✓✓ Green**: Successfully pushed to Shopify
- **⟳ Yellow**: Retrying in background (job queued)
- **✗ Red**: Failed after all retry attempts
- **— Gray**: Not pushed yet

## Environment Variables

Add to `.env` if needed:

```env
QUEUE_CONNECTION=database
QUEUE_FAILED_DRIVER=database-uuids
```

## Notes

- The queue worker must be running for background retries to work
- Without a queue worker, jobs will pile up in the `jobs` table
- Monitor the `failed_jobs` table for items that need manual intervention
- Rate limiting is handled automatically (Shopify API: 2 calls/sec)
