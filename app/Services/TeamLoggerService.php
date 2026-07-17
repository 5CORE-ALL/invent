<?php

namespace App\Services;

use App\Models\TeamLoggerDailyHours;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TeamLoggerService
{
    /**
     * TeamLogger API Bearer Token
     */
    private $bearerToken;
    
    /**
     * TeamLogger API Endpoint
     */
    private $apiUrl = 'https://api2.teamlogger.com/api/employee_summary_report';
    
    /**
     * Static cache to avoid multiple API calls in same request
     */
    private static $cache = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Use JWT token from env or fallback to the decoded API key format
        $this->bearerToken = env('TEAM_LOGGER_API_TOKEN') 
            ?? 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vaGlwZXJyLmNvbSIsInN1YiI6IjYyNDJhZjhhNmJlMjQ2YzQ5MTcwMmFiYjgyYmY5ZDYwIiwiYXVkIjoic2VydmVyIn0.mRzusxn0Ws9yD7Qmxu9QcFCNiLOnoEXSjy90edAFK4U';
    }

    /**
     * Fetch TeamLogger data for a specific date range
     *
     * @param string $startDate Format: Y-m-d
     * @param string $endDate Format: Y-m-d
     * @param bool $useCache Whether to use caching
     * @return array Employee data indexed by email
     */
    /**
     * Minutes to keep the (relatively static) summary report cached across requests.
     */
    private const CACHE_TTL_MINUTES = 30;

    /**
     * TeamLogger org settings (Employee Summary):
     * - Timezone: GMT+0530 (Asia/Kolkata)
     * - Day Reset: 12:00 → 11:59 next day
     *
     * For inclusive dates D1..D2 that means [D1 12:00, (D2+1) 11:59:59.999] in IST.
     *
     * @return array{0:int,1:int}
     */
    protected function epochRangeForDates(string $startDate, string $endDate): array
    {
        $tz = 'Asia/Kolkata';
        $startTime = Carbon::parse($startDate.' 12:00:00', $tz)->utc()->getTimestamp() * 1000;
        $endTime = Carbon::parse($endDate, $tz)->addDay()->setTime(11, 59, 59, 999000)->utc()->getTimestamp() * 1000;

        return [$startTime, $endTime];
    }

    public function fetchByDateRange($startDate, $endDate, $useCache = true)
    {
        try {
            // v4: IST noon day-reset (matches TeamLogger UI Day Reset / GMT+0530).
            $cacheKey = "teamlogger_v4_{$startDate}_{$endDate}";

            // Layer 1: in-request static cache (avoids repeat work within a single request).
            if ($useCache && isset(self::$cache[$cacheKey])) {
                return self::$cache[$cacheKey];
            }

            // Layer 2: persistent cross-request cache. The external API call can take several
            // seconds (up to a 30s timeout), so caching the processed result keeps page loads fast.
            $loader = function () use ($startDate, $endDate) {
                [$startTime, $endTime] = $this->epochRangeForDates($startDate, $endDate);

                $response = $this->callApi($startTime, $endTime);

                if (!$response['success']) {
                    Log::error('TeamLogger API failed', ['error' => $response['error']]);
                    return null; // null => failure; don't cache so we retry next time
                }

                return $this->processApiResponse($response['data']);
            };

            if ($useCache) {
                $employeeDataMap = Cache::get($cacheKey);

                if (!is_array($employeeDataMap)) {
                    $employeeDataMap = $loader();

                    if (is_array($employeeDataMap)) {
                        Cache::put($cacheKey, $employeeDataMap, now()->addMinutes(self::CACHE_TTL_MINUTES));
                        Log::info("TeamLogger: Cached " . count($employeeDataMap) . " employee records for {$startDate} to {$endDate}");
                    } else {
                        $employeeDataMap = [];
                    }
                }
            } else {
                $employeeDataMap = $loader() ?? [];
            }

            self::$cache[$cacheKey] = $employeeDataMap;

            return $employeeDataMap;

        } catch (\Exception $e) {
            Log::error('Error fetching TeamLogger data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch TeamLogger data for a specific month
     *
     * @param string $month Format: "May 2026" or "January 2025"
     * @param bool $useCache Whether to use caching
     * @return array Employee data indexed by email
     */
    public function fetchByMonth($month, $useCache = true)
    {
        try {
            // Parse month string
            $monthParts = explode(' ', $month);
            if (count($monthParts) != 2) {
                Log::error('Invalid month format. Expected format: "May 2026"');
                return [];
            }

            $monthName = $monthParts[0];
            $year = (int) $monthParts[1];
            $monthNumber = (int) date('m', strtotime($monthName . ' 1'));

            // Build date range for the month
            $startDate = Carbon::create($year, $monthNumber, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $monthNumber)->endOfMonth()->format('Y-m-d');

            return $this->fetchByDateRange($startDate, $endDate, $useCache);

        } catch (\Exception $e) {
            Log::error('Error fetching TeamLogger data by month: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Drop cached summary data for a payroll / TeamLogger month label.
     */
    public function clearCacheForMonth(string $month): void
    {
        try {
            $monthParts = explode(' ', trim($month));
            if (count($monthParts) !== 2) {
                return;
            }

            $monthName = $monthParts[0];
            $year = (int) $monthParts[1];
            $monthNumber = (int) date('m', strtotime($monthName.' 1'));
            $startDate = Carbon::create($year, $monthNumber, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $monthNumber)->endOfMonth()->format('Y-m-d');

            foreach (['teamlogger', 'teamlogger_v2', 'teamlogger_v3', 'teamlogger_v4'] as $prefix) {
                Cache::forget("{$prefix}_{$startDate}_{$endDate}");
                unset(self::$cache["{$prefix}_{$startDate}_{$endDate}"]);
            }
        } catch (\Throwable $e) {
            Log::warning('TeamLogger cache clear failed for '.$month.': '.$e->getMessage());
        }
    }

    /**
     * Fetch TeamLogger data for a specific employee
     *
     * @param string $email Employee email
     * @param string $startDate Format: Y-m-d
     * @param string $endDate Format: Y-m-d
     * @return array Employee hours data
     */
    public function fetchForEmployee($email, $startDate, $endDate)
    {
        $allData = $this->fetchByDateRange($startDate, $endDate);
        $emailKey = strtolower(trim($email));

        return $allData[$emailKey] ?? ['hours' => 0, 'total_hours' => 0, 'idle_hours' => 0];
    }

    /**
     * Call TeamLogger API
     *
     * @param int $startTime Timestamp in milliseconds
     * @param int $endTime Timestamp in milliseconds
     * @return array Response with success status and data
     */
    private function callApi($startTime, $endTime)
    {
        $curl = curl_init();
        $url = "{$this->apiUrl}?startTime={$startTime}&endTime={$endTime}";

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->bearerToken}",
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        Log::info("TeamLogger API: HTTP={$httpCode}, ResponseLength=" . strlen((string)$response));

        if ($curlError || $httpCode !== 200 || !$response) {
            return [
                'success' => false,
                'error' => $curlError ?: "HTTP {$httpCode}",
                'data' => null
            ];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response',
                'data' => null
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'data' => $data
        ];
    }

    /**
     * Process API response and extract employee data
     *
     * @param array $data Raw API response data
     * @return array Processed employee data indexed by email
     */
    private function processApiResponse($data)
    {
        $employeeDataMap = [];

        foreach ($data as $rec) {
            // Extract email from various possible fields
            $email = $rec['email'] ?? $rec['userEmail'] ?? $rec['user_email'] ?? null;
            if (!$email || !is_string($email)) {
                continue;
            }

            $emailKey = strtolower(trim($email));

            // Hours for payroll / salary: productive only — never include idle.
            // Prefer totalHours - idleHours (TeamLogger "Including Idle" minus Idle).
            // Fall back to fields that are already active/productive.
            $rawTotalHours = isset($rec['totalHours']) ? (float) $rec['totalHours'] : 0.0;
            $idleHours = isset($rec['idleHours']) ? (float) $rec['idleHours'] : 0.0;
            $productiveHours = 0.0;

            if ($rawTotalHours > 0) {
                $productiveHours = max(0, $rawTotalHours - max(0, $idleHours));
            } elseif (! empty($rec['productiveHours'])) {
                $productiveHours = (float) $rec['productiveHours'];
            } elseif (! empty($rec['activeHours'])) {
                $productiveHours = (float) $rec['activeHours'];
            } elseif (! empty($rec['onComputerHours'])) {
                $productiveHours = (float) $rec['onComputerHours'];
            } elseif (! empty($rec['workHours'])) {
                $productiveHours = (float) $rec['workHours'];
            } elseif (! empty($rec['hours'])) {
                $productiveHours = (float) $rec['hours'];
            }

            // Productive hours rounded to nearest whole hour for payroll sheets.
            $employeeDataMap[$emailKey] = [
                'hours' => (int) round($productiveHours),
                'total_hours' => round($rawTotalHours, 2),
                'idle_hours' => round(max(0, $idleHours), 2),
                'active_hours' => round($productiveHours, 2),
            ];
        }

        return $employeeDataMap;
    }

    /**
     * Clear the static cache
     */
    public function clearCache()
    {
        self::$cache = [];
        Log::info('TeamLogger cache cleared');
    }

    /**
     * Set custom bearer token (useful for different environments)
     *
     * @param string $token
     */
    public function setBearerToken($token)
    {
        $this->bearerToken = $token;
    }

    /**
     * Get raw API response without processing
     *
     * @param string $startDate Format: Y-m-d
     * @param string $endDate Format: Y-m-d
     * @return array Raw API response
     */
    public function fetchRaw($startDate, $endDate)
    {
        [$startTime, $endTime] = $this->epochRangeForDates($startDate, $endDate);

        return $this->callApi($startTime, $endTime);
    }

    /**
     * Fetch hours for a single calendar day, indexed by employee email.
     *
     * The upstream `employee_summary_report` aggregates across whatever range we
     * pass, so by passing the same date for start and end we get exactly one
     * day's worth of data per employee.
     *
     * @param  string  $date  Y-m-d
     * @return array<string, array{hours:int,total_hours:float,idle_hours:float,active_hours:float}>
     */
    public function fetchByDay($date, $useCache = true)
    {
        $day = Carbon::parse($date)->format('Y-m-d');

        return $this->fetchByDateRange($day, $day, $useCache);
    }

    /**
     * Fetch and persist day-wise hours for every employee returned by the API
     * across an inclusive date range. One row per employee per day is written
     * to `team_logger_daily_hours` via updateOrCreate, so re-runs are safe.
     *
     * Returns a summary of how many rows were inserted vs updated and which
     * days could not be fetched.
     *
     * @param  string  $startDate  Y-m-d
     * @param  string  $endDate    Y-m-d (inclusive)
     * @return array{days:int, inserted:int, updated:int, failed_days:array<int,string>}
     */
    public function fetchAndStoreDayWise($startDate, $endDate, $useCache = false)
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw new \InvalidArgumentException('End date must be on or after start date.');
        }

        $inserted = 0;
        $updated = 0;
        $failedDays = [];
        $daysProcessed = 0;
        $now = now();

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dayStr = $day->format('Y-m-d');

            try {
                // Disable the persistent cache by default for daily ingestion so
                // we always reflect the latest upstream state in the DB.
                $perDay = $this->fetchByDateRange($dayStr, $dayStr, $useCache);
            } catch (\Throwable $e) {
                Log::error("TeamLogger day-wise fetch failed for {$dayStr}: ".$e->getMessage());
                $failedDays[] = $dayStr;
                continue;
            }

            if (!is_array($perDay) || empty($perDay)) {
                $failedDays[] = $dayStr;
                continue;
            }

            foreach ($perDay as $email => $hours) {
                $record = TeamLoggerDailyHours::updateOrCreate(
                    [
                        'employee_email' => $email,
                        'work_date' => $dayStr,
                    ],
                    [
                        'total_hours' => $hours['total_hours'] ?? 0,
                        'idle_hours' => $hours['idle_hours'] ?? 0,
                        'active_hours' => $hours['active_hours'] ?? 0,
                        'productive_hours' => $hours['hours'] ?? 0,
                        'fetched_at' => $now,
                    ]
                );

                $record->wasRecentlyCreated ? $inserted++ : $updated++;
            }

            $daysProcessed++;
        }

        return [
            'days' => $daysProcessed,
            'inserted' => $inserted,
            'updated' => $updated,
            'failed_days' => $failedDays,
        ];
    }
}
