# TeamLogger API Integration - Usage Guide

This project includes a reusable TeamLogger API integration with a Service class and Artisan command.

## 📁 Files Created

1. **Service Class**: `app/Services/TeamLoggerService.php`
   - Reusable service for fetching TeamLogger data
   - Can be used in controllers, jobs, or anywhere in your Laravel app

2. **Artisan Command**: `app/Console/Commands/FetchTeamLoggerData.php`
   - CLI command for fetching TeamLogger data
   - Easy to use and test

## 🚀 Quick Start

### Fetch Current Month Data
```bash
php artisan teamlogger:fetch
```

### Fetch Specific Month
```bash
php artisan teamlogger:fetch --month="May 2026"
php artisan teamlogger:fetch --month="January 2025"
```

### Fetch by Date Range
```bash
php artisan teamlogger:fetch --start-date=2026-05-01 --end-date=2026-05-31
```

### Get JSON Output
```bash
php artisan teamlogger:fetch --month="May 2026" --json
```

## 📋 All Command Options

### Basic Usage
```bash
# Fetch current month (default)
php artisan teamlogger:fetch

# Fetch specific month
php artisan teamlogger:fetch --month="May 2026"

# Fetch date range
php artisan teamlogger:fetch --start-date=2026-05-01 --end-date=2026-05-31
```

### Filtering
```bash
# Fetch specific employee
php artisan teamlogger:fetch --month="May 2026" --email="employee@example.com"
```

### Output Formats
```bash
# Get JSON output
php artisan teamlogger:fetch --month="May 2026" --json

# Get raw API response
php artisan teamlogger:fetch --month="May 2026" --raw --json

# Pipe to jq for formatting
php artisan teamlogger:fetch --month="May 2026" --json | jq .
```

### Caching
```bash
# Disable caching (force fresh API call)
php artisan teamlogger:fetch --month="May 2026" --no-cache
```

## 💻 Using in Your Code

### Method 1: In a Controller

```php
<?php

namespace App\Http\Controllers;

use App\Services\TeamLoggerService;
use Illuminate\Http\Request;

class EmployeeHoursController extends Controller
{
    protected $teamLoggerService;

    public function __construct(TeamLoggerService $teamLoggerService)
    {
        $this->teamLoggerService = $teamLoggerService;
    }

    public function getMonthlyHours($month)
    {
        // Fetch by month (e.g., "May 2026")
        $data = $this->teamLoggerService->fetchByMonth($month);
        
        return response()->json($data);
    }

    public function getEmployeeHours(Request $request)
    {
        $email = $request->input('email');
        $startDate = $request->input('start_date'); // Y-m-d format
        $endDate = $request->input('end_date');     // Y-m-d format
        
        $data = $this->teamLoggerService->fetchForEmployee($email, $startDate, $endDate);
        
        return response()->json([
            'email' => $email,
            'hours' => $data
        ]);
    }

    public function getDateRangeHours($startDate, $endDate)
    {
        $data = $this->teamLoggerService->fetchByDateRange($startDate, $endDate);
        
        return response()->json($data);
    }
}
```

### Method 2: Direct Usage

```php
use App\Services\TeamLoggerService;

$service = new TeamLoggerService();

// Fetch data for May 2026
$data = $service->fetchByMonth('May 2026');

// Access employee data
foreach ($data as $email => $hours) {
    echo "Email: {$email}\n";
    echo "Productive Hours: {$hours['hours']}\n";
    echo "Total Hours: {$hours['total_hours']}\n";
    echo "Idle Hours: {$hours['idle_hours']}\n\n";
}

// Get specific employee
$employeeData = $data['employee@example.com'] ?? ['hours' => 0];
echo "Hours: " . $employeeData['hours'];
```

### Method 3: In a Job/Queue

```php
<?php

namespace App\Jobs;

use App\Services\TeamLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessTeamLoggerDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $month;

    public function __construct($month)
    {
        $this->month = $month;
    }

    public function handle(TeamLoggerService $teamLoggerService)
    {
        $data = $teamLoggerService->fetchByMonth($this->month);
        
        // Process the data
        foreach ($data as $email => $hours) {
            // Update database, send notifications, etc.
            \Log::info("Employee {$email} worked {$hours['hours']} hours");
        }
    }
}
```

### Method 4: In a Scheduled Task

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Fetch TeamLogger data daily at midnight
    $schedule->command('teamlogger:fetch')
             ->dailyAt('00:00');
    
    // Fetch and store previous day's data
    $schedule->call(function () {
        $service = new \App\Services\TeamLoggerService();
        $yesterday = \Carbon\Carbon::yesterday()->format('Y-m-d');
        $data = $service->fetchByDateRange($yesterday, $yesterday);
        
        // Store in database or process
        \Log::info('TeamLogger daily sync: ' . count($data) . ' employees');
    })->dailyAt('01:00');
}
```

## 📊 Response Format

The service returns an array indexed by employee email:

```php
[
    'employee1@example.com' => [
        'hours' => 160,              // Productive hours (integer, rounded)
        'total_hours' => 165.50,     // Total logged hours (float)
        'idle_hours' => 5.50,        // Idle time hours (float)
        'active_hours' => 160.00     // Active hours (float)
    ],
    'employee2@example.com' => [
        'hours' => 150,
        'total_hours' => 155.25,
        'idle_hours' => 5.25,
        'active_hours' => 150.00
    ]
]
```

### Field Descriptions

- **hours**: Productive hours (total_hours - idle_hours), rounded to integer
- **total_hours**: Total time logged in TeamLogger
- **idle_hours**: Time marked as idle/inactive
- **active_hours**: Same as productive hours but as float

## 🔧 Configuration

### Environment Variables

Add to your `.env` file:

```env
# TeamLogger API Token (JWT Bearer Token)
TEAM_LOGGER_API_TOKEN=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

If not set, the service will use the default token embedded in the service class.

### Changing the Token

```php
$service = new TeamLoggerService();
$service->setBearerToken('your-new-token-here');
$data = $service->fetchByMonth('May 2026');
```

### Clearing Cache

```php
$service = new TeamLoggerService();
$service->clearCache(); // Clears in-memory cache
```

## 📋 Special Features

### Email Mapping

The service automatically handles special email mappings:
- `customercare@5core.com` → `debhritiksha@gmail.com`

To add more mappings, edit the `processApiResponse` method in `TeamLoggerService.php` around line 205.

### Time Range Details

The service uses the following time ranges:
- **Start**: 12:00 PM (noon) UTC on start date
- **End**: 11:59 AM UTC on the day AFTER end date

This ensures full 24-hour coverage aligned with TeamLogger's tracking system.

### Caching

- **Static cache**: Results are cached within the same request/process
- **Cache key**: `teamlogger_{startDate}_{endDate}`
- **Use `--no-cache`** flag to bypass cache and force fresh API call

## 🧪 Testing Examples

```bash
# Test with current month
php artisan teamlogger:fetch

# Test with specific month
php artisan teamlogger:fetch --month="May 2026"

# Test with date range (one week)
php artisan teamlogger:fetch --start-date=2026-05-01 --end-date=2026-05-07

# Test with JSON output and jq formatting
php artisan teamlogger:fetch --month="May 2026" --json | jq .

# Test specific employee
php artisan teamlogger:fetch --month="May 2026" --email="president@5core.com"

# Get raw API response
php artisan teamlogger:fetch --month="May 2026" --raw --json > raw_response.json

# Force fresh data (no cache)
php artisan teamlogger:fetch --month="May 2026" --no-cache
```

## 🐛 Troubleshooting

### Command not found
```bash
php artisan cache:clear
php artisan config:clear
composer dump-autoload
```

### No data returned
Check the logs:
```bash
tail -f storage/logs/laravel.log | grep TeamLogger
```

### API timeout
Increase timeout in `TeamLoggerService.php`:
```php
CURLOPT_TIMEOUT => 60, // Change from 30 to 60 seconds
```

### Invalid month format error
Ensure month format is: `"MonthName YEAR"` (e.g., `"May 2026"`)

### Empty response
- Verify the bearer token is correct
- Check if employees have logged time for the requested period
- Use `--raw --json` to see the actual API response

## 📝 API Details

- **Endpoint**: `https://api2.teamlogger.com/api/employee_summary_report`
- **Method**: GET
- **Authentication**: Bearer Token (JWT)
- **Query Parameters**: `startTime` and `endTime` (in milliseconds)
- **Timeout**: 30 seconds
- **Connection Timeout**: 5 seconds

## 🚀 Best Practices

1. **Use dependency injection** in controllers for better testing
2. **Cache results** when fetching the same date range multiple times
3. **Handle empty results** gracefully in your code
4. **Log errors** to Laravel's log for debugging
5. **Use queued jobs** for large data fetches
6. **Schedule regular syncs** using Laravel's task scheduler

## 📦 Copy to Another Project

See `COPY_TO_NEW_PROJECT.md` for detailed instructions on copying this integration to another Laravel project.
