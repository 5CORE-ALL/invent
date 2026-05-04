# Copy TeamLogger Integration to New Project

This guide explains how to copy the TeamLogger API integration to a new Laravel project.

## 📋 Prerequisites

- Laravel 8.x or higher
- PHP 7.4 or higher
- Composer installed

## 🚀 Quick Copy

### Step 1: Copy Files

Copy these two files to your new project:

```bash
# Copy the Service class
cp app/Services/TeamLoggerService.php /path/to/new-project/app/Services/

# Copy the Command class
cp app/Console/Commands/FetchTeamLoggerData.php /path/to/new-project/app/Console/Commands/
```

### Step 2: Create Services Directory (if needed)

```bash
# In your new project
mkdir -p app/Services
```

### Step 3: Update .env (Optional)

Add to your new project's `.env` file:

```env
TEAM_LOGGER_API_TOKEN=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Note**: The token is already embedded in the service, so this step is optional.

### Step 4: Clear Caches

```bash
cd /path/to/new-project
php artisan cache:clear
php artisan config:clear
composer dump-autoload
```

### Step 5: Test

```bash
php artisan teamlogger:fetch --month="May 2026"
```

## 📦 What Gets Copied

### 1. TeamLoggerService.php
**Location**: `app/Services/TeamLoggerService.php`

**Features**:
- Fetch data by month
- Fetch data by date range
- Fetch data for specific employee
- Caching support
- Email mapping
- Raw API response option

### 2. FetchTeamLoggerData.php
**Location**: `app/Console/Commands/FetchTeamLoggerData.php`

**Features**:
- CLI command interface
- Multiple output formats (table, JSON)
- Date range options
- Employee filtering
- Cache control

## 🔧 Configuration Options

### Option 1: Use Environment Variable

Add to `.env`:
```env
TEAM_LOGGER_API_TOKEN=your-token-here
```

Update `TeamLoggerService.php` constructor:
```php
public function __construct()
{
    $this->bearerToken = env('TEAM_LOGGER_API_TOKEN');
}
```

### Option 2: Use Config File

Create `config/teamlogger.php`:
```php
<?php

return [
    'api_token' => env('TEAM_LOGGER_API_TOKEN', 'default-token-here'),
    'api_url' => env('TEAM_LOGGER_API_URL', 'https://api2.teamlogger.com/api/employee_summary_report'),
    'timeout' => 30,
    'connect_timeout' => 5,
];
```

Update `TeamLoggerService.php`:
```php
public function __construct()
{
    $this->bearerToken = config('teamlogger.api_token');
    $this->apiUrl = config('teamlogger.api_url');
}
```

### Option 3: Keep Default (Simplest)

No changes needed. The token is already embedded in the service class.

## 📝 Customization Guide

### Change Email Mappings

Edit `TeamLoggerService.php` around line 205:

```php
private function processApiResponse($data)
{
    // ... existing code ...
    
    // Add your custom mappings here
    if ($emailKey === 'old-email@example.com') {
        $emailKey = 'new-email@example.com';
    }
    
    // ... rest of the code ...
}
```

### Change API Timeout

Edit `TeamLoggerService.php` around line 150:

```php
CURLOPT_TIMEOUT => 60, // Change from 30 to 60 seconds
CURLOPT_CONNECTTIMEOUT => 10, // Change from 5 to 10 seconds
```

### Add Logging

The service already logs to Laravel's default log. To customize:

```php
// Change log level
Log::debug('TeamLogger: ...'); // Instead of Log::info()

// Add context
Log::info('TeamLogger API call', [
    'start_date' => $startDate,
    'end_date' => $endDate,
    'employee_count' => count($data)
]);
```

## 🎯 Usage After Copy

### Command Line
```bash
# Fetch current month
php artisan teamlogger:fetch

# Fetch specific month
php artisan teamlogger:fetch --month="May 2026"

# Fetch date range
php artisan teamlogger:fetch --start-date=2026-05-01 --end-date=2026-05-31

# Get JSON output
php artisan teamlogger:fetch --month="May 2026" --json

# Fetch specific employee
php artisan teamlogger:fetch --month="May 2026" --email="employee@example.com"
```

### In Code
```php
use App\Services\TeamLoggerService;

$service = new TeamLoggerService();
$data = $service->fetchByMonth('May 2026');
```

## 🔍 Verification Checklist

After copying, verify:

- [ ] Command appears in `php artisan list`
- [ ] Service file exists in `app/Services/TeamLoggerService.php`
- [ ] Command file exists in `app/Console/Commands/FetchTeamLoggerData.php`
- [ ] No syntax errors: `composer dump-autoload`
- [ ] Command runs: `php artisan teamlogger:fetch --month="May 2026"`
- [ ] Data is returned (check logs if empty)

## 🐛 Troubleshooting

### "Command not found"

```bash
php artisan cache:clear
php artisan config:clear
composer dump-autoload
php artisan list | grep teamlogger
```

### "Class not found"

```bash
composer dump-autoload -o
```

### "Permission denied" on Linux/Mac

```bash
chmod +x artisan
chmod -R 775 storage bootstrap/cache
```

### No data returned

1. Check Laravel logs: `tail -f storage/logs/laravel.log`
2. Test with `--raw --json` flag
3. Verify bearer token is correct
4. Check if employees logged time for requested period

## 📚 Additional Documentation

- `TEAMLOGGER_USAGE.md` - Comprehensive usage guide
- `EXAMPLE_TEAMLOGGER_USAGE.php` - 16 code examples

## 🎓 Quick Start Guide for New Project

1. **Copy files** (2 files total)
2. **Run** `composer dump-autoload`
3. **Test** `php artisan teamlogger:fetch`
4. **Done!** ✅

## 🔐 Security Notes

- The bearer token is a JWT token that encodes your API key
- Store sensitive tokens in `.env` file
- Don't commit `.env` to version control
- Rotate tokens periodically if required by your security policy

## 📊 Performance Tips

1. **Use caching**: Default behavior caches within same request
2. **Batch fetches**: Fetch month once, filter in code rather than multiple API calls
3. **Queue large operations**: Use jobs for bulk data processing
4. **Monitor API usage**: Check logs for API call frequency

## 🚀 Advanced: Publish as Package

To create a reusable Composer package:

1. Create package structure
2. Add `composer.json` with autoloading
3. Publish to Packagist or private repo
4. Install with `composer require your-vendor/teamlogger-laravel`

## 💡 Integration Tips

### With Existing User Model
```php
// In User model
public function getTeamLoggerHours($month)
{
    $service = new \App\Services\TeamLoggerService();
    return $service->fetchForEmployee($this->email, ...);
}
```

### With API Routes
```php
// routes/api.php
Route::get('/teamlogger/{month}', function($month) {
    $service = new \App\Services\TeamLoggerService();
    return $service->fetchByMonth($month);
});
```

### With Dashboard
```php
// In your dashboard controller
public function index()
{
    $service = new TeamLoggerService();
    $currentMonth = Carbon::now()->format('F Y');
    $data = $service->fetchByMonth($currentMonth);
    
    return view('dashboard', [
        'teamlogger_data' => $data
    ]);
}
```

## ✅ Complete!

Your TeamLogger integration is now ready to use in the new project!
