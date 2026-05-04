<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TeamLoggerService;
use App\Models\TeamLoggerHours;
use Carbon\Carbon;

class FetchTeamLoggerData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teamlogger:fetch 
                            {--month= : Month in format "May 2026" or "January 2025"}
                            {--start-date= : Start date in format Y-m-d (e.g., 2026-05-01)}
                            {--end-date= : End date in format Y-m-d (e.g., 2026-05-31)}
                            {--email= : Specific employee email to fetch}
                            {--raw : Return raw API response}
                            {--json : Output as JSON}
                            {--no-cache : Disable caching}
                            {--save : Save fetched data to database}
                            {--fetch-months= : Fetch multiple months (e.g., "Jan 2026,Feb 2026,Mar 2026")}
                            {--fetch-jan-to-current : Fetch all months from January 2026 to current month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch employee data from TeamLogger API';

    /**
     * TeamLogger Service instance
     *
     * @var TeamLoggerService
     */
    protected $teamLoggerService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(TeamLoggerService $teamLoggerService)
    {
        parent::__construct();
        $this->teamLoggerService = $teamLoggerService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔄 Fetching TeamLogger data...');
        $this->newLine();

        try {
            $useCache = !$this->option('no-cache');
            $saveToDb = $this->option('save');
            
            // Check if fetching Jan to current month
            if ($this->option('fetch-jan-to-current')) {
                return $this->fetchJanToCurrentMonth($saveToDb);
            }
            
            // Check if fetching multiple months
            if ($this->option('fetch-months')) {
                return $this->fetchMultipleMonths($saveToDb);
            }

            $data = null;

            // Determine which fetch method to use
            if ($this->option('month')) {
                $data = $this->fetchByMonth($useCache);
                $month = $this->option('month');
            } elseif ($this->option('start-date') && $this->option('end-date')) {
                $data = $this->fetchByDateRange($useCache);
                $month = null;
            } else {
                // Default: fetch current month
                $currentMonth = Carbon::now()->format('F Y');
                $this->warn("No date range specified. Fetching current month: {$currentMonth}");
                $data = $this->teamLoggerService->fetchByMonth($currentMonth, $useCache);
                $month = $currentMonth;
            }

            if (empty($data)) {
                $this->error('❌ No data retrieved from TeamLogger API');
                return Command::FAILURE;
            }

            // Save to database if requested
            if ($saveToDb) {
                $this->saveToDatabase($data, $month ?? 'custom');
            }

            // Filter by specific email if provided
            if ($this->option('email')) {
                $email = strtolower(trim($this->option('email')));
                $data = [$email => $data[$email] ?? ['hours' => 0, 'total_hours' => 0, 'idle_hours' => 0]];
            }

            // Output results
            if ($this->option('json')) {
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $this->displayTable($data);
            }

            $this->newLine();
            $this->info("✅ Successfully fetched data for " . count($data) . " employee(s)");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Fetch data by month
     *
     * @param bool $useCache
     * @return array
     */
    private function fetchByMonth($useCache)
    {
        $month = $this->option('month');
        $this->info("📅 Fetching data for month: {$month}");
        
        if ($this->option('raw')) {
            $monthParts = explode(' ', $month);
            $year = (int) $monthParts[1];
            $monthNumber = (int) date('m', strtotime($monthParts[0] . ' 1'));
            $startDate = Carbon::create($year, $monthNumber, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $monthNumber)->endOfMonth()->format('Y-m-d');
            return $this->teamLoggerService->fetchRaw($startDate, $endDate);
        }

        return $this->teamLoggerService->fetchByMonth($month, $useCache);
    }

    /**
     * Fetch data by date range
     *
     * @param bool $useCache
     * @return array
     */
    private function fetchByDateRange($useCache)
    {
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        
        $this->info("📅 Fetching data from {$startDate} to {$endDate}");
        
        if ($this->option('raw')) {
            return $this->teamLoggerService->fetchRaw($startDate, $endDate);
        }

        return $this->teamLoggerService->fetchByDateRange($startDate, $endDate, $useCache);
    }

    /**
     * Display data in a table format
     *
     * @param array $data
     * @return void
     */
    private function displayTable($data)
    {
        $this->newLine();
        $this->info('📊 TeamLogger Data:');
        $this->newLine();

        $tableData = [];
        foreach ($data as $email => $hours) {
            $tableData[] = [
                'Email' => $email,
                'Productive Hours' => is_array($hours) ? ($hours['hours'] ?? $hours['active_hours'] ?? 0) : $hours,
                'Total Hours' => is_array($hours) ? ($hours['total_hours'] ?? 0) : 0,
                'Idle Hours' => is_array($hours) ? ($hours['idle_hours'] ?? 0) : 0,
            ];
        }

        $this->table(
            ['Email', 'Productive Hours', 'Total Hours', 'Idle Hours'],
            $tableData
        );
    }

    /**
     * Save data to database
     *
     * @param array $data
     * @param string $month
     * @return void
     */
    private function saveToDatabase($data, $month)
    {
        $this->info("💾 Saving data to database for {$month}...");
        
        $saved = 0;
        $updated = 0;
        
        // Parse month to get date range
        $monthParts = explode(' ', $month);
        if (count($monthParts) == 2) {
            $monthName = $monthParts[0];
            $year = (int) $monthParts[1];
            $monthNumber = (int) date('m', strtotime($monthName . ' 1'));
            $startDate = Carbon::create($year, $monthNumber, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $monthNumber)->endOfMonth()->format('Y-m-d');
        } else {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        foreach ($data as $email => $hours) {
            $record = TeamLoggerHours::updateOrCreate(
                [
                    'employee_email' => $email,
                    'month' => $month,
                ],
                [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'productive_hours' => $hours['hours'] ?? 0,
                    'total_hours' => $hours['total_hours'] ?? 0,
                    'idle_hours' => $hours['idle_hours'] ?? 0,
                    'active_hours' => $hours['active_hours'] ?? 0,
                    'fetched_at' => now(),
                ]
            );

            if ($record->wasRecentlyCreated) {
                $saved++;
            } else {
                $updated++;
            }
        }

        $this->info("✅ Saved: {$saved} new records, Updated: {$updated} existing records");
    }

    /**
     * Fetch data from January 2026 to current month
     *
     * @param bool $saveToDb
     * @return int
     */
    private function fetchJanToCurrentMonth($saveToDb)
    {
        $this->info("📅 Fetching data from January 2026 to current month...");
        $this->newLine();

        $startMonth = Carbon::create(2026, 1, 1);
        $currentMonth = Carbon::now();
        
        $months = [];
        $month = $startMonth->copy();
        
        while ($month->lte($currentMonth)) {
            $months[] = $month->format('F Y');
            $month->addMonth();
        }

        $this->info("Will fetch " . count($months) . " months: " . implode(', ', $months));
        $this->newLine();

        $totalEmployees = 0;
        $allData = [];

        foreach ($months as $monthStr) {
            $this->info("⏳ Fetching {$monthStr}...");
            
            try {
                $data = $this->teamLoggerService->fetchByMonth($monthStr, false);
                
                if (!empty($data)) {
                    $this->info("  ✅ Found " . count($data) . " employees");
                    $totalEmployees += count($data);
                    
                    if ($saveToDb) {
                        $this->saveToDatabase($data, $monthStr);
                    }
                    
                    $allData[$monthStr] = $data;
                } else {
                    $this->warn("  ⚠️ No data found");
                }
                
                // Small delay between API calls
                usleep(500000); // 0.5 seconds
                
            } catch (\Exception $e) {
                $this->error("  ❌ Error: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("🎉 Complete! Fetched data for {$totalEmployees} employee records across " . count($months) . " months");
        
        if ($saveToDb) {
            $this->info("💾 All data saved to database");
        }

        return Command::SUCCESS;
    }

    /**
     * Fetch multiple specific months
     *
     * @param bool $saveToDb
     * @return int
     */
    private function fetchMultipleMonths($saveToDb)
    {
        $monthsInput = $this->option('fetch-months');
        $months = array_map('trim', explode(',', $monthsInput));

        $this->info("📅 Fetching " . count($months) . " months...");
        $this->newLine();

        foreach ($months as $month) {
            $this->info("⏳ Fetching {$month}...");
            
            try {
                $data = $this->teamLoggerService->fetchByMonth($month, false);
                
                if (!empty($data)) {
                    $this->info("  ✅ Found " . count($data) . " employees");
                    
                    if ($saveToDb) {
                        $this->saveToDatabase($data, $month);
                    }
                } else {
                    $this->warn("  ⚠️ No data found");
                }
                
                usleep(500000); // 0.5 seconds delay
                
            } catch (\Exception $e) {
                $this->error("  ❌ Error: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("🎉 Complete!");

        return Command::SUCCESS;
    }
}
