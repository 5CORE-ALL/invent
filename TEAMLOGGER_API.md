# Team Logger API Integration

## Overview
This Laravel command integrates with the Team Logger API to fetch various data including users, projects, tasks, worklog, and summaries.

## API Key
The API key is stored in the command file: `6242af8a6be246c491702abb82bf9d60`

## Command Usage

### Basic Syntax
```bash
php artisan teamlogger:fetch {endpoint} [options]
```

### Available Endpoints

#### 1. Fetch Users
```bash
php artisan teamlogger:fetch users
```
Returns all users from Team Logger.

#### 2. Fetch Projects
```bash
php artisan teamlogger:fetch projects
```
Returns all projects from Team Logger.

#### 3. Fetch Tasks
```bash
php artisan teamlogger:fetch tasks
```
Returns all tasks.

Filter by project:
```bash
php artisan teamlogger:fetch tasks --project=123
```

#### 4. Fetch Worklog
```bash
php artisan teamlogger:fetch worklog
```
Returns worklog for today (default).

Filter by date:
```bash
php artisan teamlogger:fetch worklog --date=2026-05-01
```

Filter by user:
```bash
php artisan teamlogger:fetch worklog --user=456
```

Filter by project:
```bash
php artisan teamlogger:fetch worklog --project=123
```

Combine filters:
```bash
php artisan teamlogger:fetch worklog --date=2026-05-01 --user=456 --project=123
```

#### 5. Fetch Summary
```bash
php artisan teamlogger:fetch summary
```
Returns summary data for today (default).

Filter by date:
```bash
php artisan teamlogger:fetch summary --date=2026-05-01
```

## Options

| Option | Description | Example |
|--------|-------------|---------|
| `--date` | Filter by date (YYYY-MM-DD) | `--date=2026-05-01` |
| `--user` | Filter by user ID | `--user=456` |
| `--project` | Filter by project ID | `--project=123` |

## Output

### Console Display
- Results are displayed in a formatted table (if applicable)
- Maximum 20 rows shown in console
- JSON format for non-tabular data

### File Storage
All fetched data is automatically saved to:
```
storage/app/teamlogger_{endpoint}_{timestamp}.json
```

Example:
```
storage/app/teamlogger_users_2026-05-05_020630.json
```

## API Endpoints (Base URL)
```
https://api.teamlogger.com
```

## Authentication
The command uses Bearer token authentication with the provided API key.

## Error Handling
- All errors are logged to Laravel's log file
- Console displays error messages
- Returns appropriate exit codes (0 for success, 1 for failure)

## Examples

### Get all users
```bash
php artisan teamlogger:fetch users
```

### Get today's worklog
```bash
php artisan teamlogger:fetch worklog
```

### Get worklog for specific date and user
```bash
php artisan teamlogger:fetch worklog --date=2026-05-01 --user=10
```

### Get all projects
```bash
php artisan teamlogger:fetch projects
```

### Get summary for yesterday
```bash
php artisan teamlogger:fetch summary --date=2026-05-04
```

## Scheduling (Optional)

To schedule automatic data fetching, add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Fetch worklog daily at 6 PM
    $schedule->command('teamlogger:fetch worklog')
             ->dailyAt('18:00');
    
    // Fetch summary daily at 11 PM
    $schedule->command('teamlogger:fetch summary')
             ->dailyAt('23:00');
}
```

## Notes
- The Team Logger API base URL is: `https://api.teamlogger.com`
- All requests use JSON format
- Data is cached in `storage/app/` directory
- Check Laravel logs for detailed error information

## Troubleshooting

### API Key Issues
If you get authentication errors, verify the API key in:
```
app/Console/Commands/FetchTeamLoggerData.php
```

### Network Issues
Ensure your server can make HTTPS requests to `api.teamlogger.com`

### Permission Issues
Ensure Laravel has write permissions to:
```
storage/app/
storage/logs/
```
