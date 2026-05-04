<?php

/**
 * TeamLogger Service - Example Usage
 * 
 * This file demonstrates various ways to use the TeamLoggerService
 * in your Laravel application.
 */

use App\Services\TeamLoggerService;
use Carbon\Carbon;

// ============================================================================
// Example 1: Basic Usage - Fetch Current Month
// ============================================================================

$service = new TeamLoggerService();
$currentMonth = Carbon::now()->format('F Y'); // e.g., "May 2026"
$data = $service->fetchByMonth($currentMonth);

echo "Current Month Data:\n";
print_r($data);

// ============================================================================
// Example 2: Fetch Specific Month
// ============================================================================

$data = $service->fetchByMonth('May 2026');

foreach ($data as $email => $hours) {
    echo "Employee: {$email}\n";
    echo "  Productive Hours: {$hours['hours']}\n";
    echo "  Total Hours: {$hours['total_hours']}\n";
    echo "  Idle Hours: {$hours['idle_hours']}\n";
    echo "  Active Hours: {$hours['active_hours']}\n\n";
}

// ============================================================================
// Example 3: Fetch by Date Range
// ============================================================================

$startDate = '2026-05-01';
$endDate = '2026-05-31';
$data = $service->fetchByDateRange($startDate, $endDate);

echo "Date Range: {$startDate} to {$endDate}\n";
echo "Total Employees: " . count($data) . "\n";

// ============================================================================
// Example 4: Fetch Specific Employee
// ============================================================================

$email = 'employee@5core.com';
$employeeData = $service->fetchForEmployee($email, '2026-05-01', '2026-05-31');

echo "Employee: {$email}\n";
echo "Hours: {$employeeData['hours']}\n";

// ============================================================================
// Example 5: Calculate Total Hours for All Employees
// ============================================================================

$data = $service->fetchByMonth('May 2026');
$totalHours = 0;

foreach ($data as $email => $hours) {
    $totalHours += $hours['hours'];
}

echo "Total Hours for May 2026: {$totalHours}\n";

// ============================================================================
// Example 6: Find Top 5 Employees by Hours
// ============================================================================

$data = $service->fetchByMonth('May 2026');

// Sort by hours descending
uasort($data, function($a, $b) {
    return $b['hours'] - $a['hours'];
});

$top5 = array_slice($data, 0, 5, true);

echo "Top 5 Employees:\n";
foreach ($top5 as $email => $hours) {
    echo "  {$email}: {$hours['hours']} hours\n";
}

// ============================================================================
// Example 7: Filter Employees with Low Hours
// ============================================================================

$data = $service->fetchByMonth('May 2026');
$threshold = 120; // hours

$lowHoursEmployees = array_filter($data, function($hours) use ($threshold) {
    return $hours['hours'] < $threshold;
});

echo "Employees with less than {$threshold} hours:\n";
foreach ($lowHoursEmployees as $email => $hours) {
    echo "  {$email}: {$hours['hours']} hours\n";
}

// ============================================================================
// Example 8: Using in a Controller
// ============================================================================

namespace App\Http\Controllers;

use App\Services\TeamLoggerService;
use Illuminate\Http\Request;

class EmployeeReportController extends Controller
{
    protected $teamLoggerService;

    public function __construct(TeamLoggerService $teamLoggerService)
    {
        $this->teamLoggerService = $teamLoggerService;
    }

    public function monthlyReport($month)
    {
        $data = $this->teamLoggerService->fetchByMonth($month);
        
        return view('reports.monthly', [
            'month' => $month,
            'employees' => $data
        ]);
    }

    public function employeeDetail($email)
    {
        $currentMonth = \Carbon\Carbon::now()->format('F Y');
        $data = $this->teamLoggerService->fetchByMonth($currentMonth);
        
        $employeeData = $data[strtolower($email)] ?? null;
        
        if (!$employeeData) {
            abort(404, 'Employee not found');
        }
        
        return view('reports.employee', [
            'email' => $email,
            'hours' => $employeeData
        ]);
    }

    public function apiMonthlyData($month)
    {
        $data = $this->teamLoggerService->fetchByMonth($month);
        
        return response()->json([
            'success' => true,
            'month' => $month,
            'data' => $data
        ]);
    }
}

// ============================================================================
// Example 9: Using in a Job (Queue)
// ============================================================================

namespace App\Jobs;

use App\Services\TeamLoggerService;
use App\Models\EmployeeHours;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncTeamLoggerDataJob implements ShouldQueue
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
        
        foreach ($data as $email => $hours) {
            EmployeeHours::updateOrCreate(
                [
                    'email' => $email,
                    'month' => $this->month
                ],
                [
                    'productive_hours' => $hours['hours'],
                    'total_hours' => $hours['total_hours'],
                    'idle_hours' => $hours['idle_hours']
                ]
            );
        }
        
        \Log::info("Synced TeamLogger data for {$this->month}: " . count($data) . " employees");
    }
}

// Dispatch the job
SyncTeamLoggerDataJob::dispatch('May 2026');

// ============================================================================
// Example 10: Using with Custom Bearer Token
// ============================================================================

$service = new TeamLoggerService();
$service->setBearerToken('your-custom-token-here');
$data = $service->fetchByMonth('May 2026');

// ============================================================================
// Example 11: Get Raw API Response
// ============================================================================

$service = new TeamLoggerService();
$rawResponse = $service->fetchRaw('2026-05-01', '2026-05-31');

if ($rawResponse['success']) {
    echo "Raw API Data:\n";
    print_r($rawResponse['data']);
} else {
    echo "Error: " . $rawResponse['error'] . "\n";
}

// ============================================================================
// Example 12: Clear Cache
// ============================================================================

$service = new TeamLoggerService();
$service->clearCache();
echo "Cache cleared!\n";

// ============================================================================
// Example 13: Building a Blade View
// ============================================================================

// In your controller:
public function showMonthlyReport()
{
    $service = new TeamLoggerService();
    $data = $service->fetchByMonth('May 2026');
    
    return view('teamlogger.report', compact('data'));
}

// In your blade view (resources/views/teamlogger/report.blade.php):
/*
<table class="table">
    <thead>
        <tr>
            <th>Employee</th>
            <th>Productive Hours</th>
            <th>Total Hours</th>
            <th>Idle Hours</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $email => $hours)
        <tr>
            <td>{{ $email }}</td>
            <td>{{ $hours['hours'] }}</td>
            <td>{{ $hours['total_hours'] }}</td>
            <td>{{ $hours['idle_hours'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
*/

// ============================================================================
// Example 14: Weekly Report
// ============================================================================

$service = new TeamLoggerService();
$startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
$endDate = Carbon::now()->endOfWeek()->format('Y-m-d');

$weeklyData = $service->fetchByDateRange($startDate, $endDate);

echo "Weekly Report ({$startDate} to {$endDate}):\n";
foreach ($weeklyData as $email => $hours) {
    echo "{$email}: {$hours['hours']} hours\n";
}

// ============================================================================
// Example 15: Export to CSV
// ============================================================================

$service = new TeamLoggerService();
$data = $service->fetchByMonth('May 2026');

$filename = 'teamlogger_may_2026.csv';
$file = fopen($filename, 'w');

// Write header
fputcsv($file, ['Email', 'Productive Hours', 'Total Hours', 'Idle Hours', 'Active Hours']);

// Write data
foreach ($data as $email => $hours) {
    fputcsv($file, [
        $email,
        $hours['hours'],
        $hours['total_hours'],
        $hours['idle_hours'],
        $hours['active_hours']
    ]);
}

fclose($file);
echo "Exported to {$filename}\n";

// ============================================================================
// Example 16: Compare Two Months
// ============================================================================

$service = new TeamLoggerService();
$may = $service->fetchByMonth('May 2026');
$april = $service->fetchByMonth('April 2026');

echo "Month-over-Month Comparison:\n";
foreach ($may as $email => $mayHours) {
    $aprilHours = $april[$email] ?? ['hours' => 0];
    $difference = $mayHours['hours'] - $aprilHours['hours'];
    $percentage = $aprilHours['hours'] > 0 
        ? round(($difference / $aprilHours['hours']) * 100, 1) 
        : 0;
    
    echo "{$email}:\n";
    echo "  May: {$mayHours['hours']} hours\n";
    echo "  April: {$aprilHours['hours']} hours\n";
    echo "  Change: {$difference} hours ({$percentage}%)\n\n";
}
