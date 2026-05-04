<?php

namespace App\Services;

use Carbon\Carbon;
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
    public function fetchByDateRange($startDate, $endDate, $useCache = true)
    {
        try {
            $cacheKey = "teamlogger_{$startDate}_{$endDate}";
            
            // Check cache
            if ($useCache && isset(self::$cache[$cacheKey])) {
                Log::info("TeamLogger: Using cached data for {$startDate} to {$endDate}");
                return self::$cache[$cacheKey];
            }

            // Convert dates to timestamps (milliseconds)
            $startTime = Carbon::parse($startDate)->setTime(12, 0, 0)->utc()->getTimestamp() * 1000;
            $endTime = Carbon::parse($endDate)->addDay()->setTime(11, 59, 59)->utc()->getTimestamp() * 1000;

            // Fetch from API
            $response = $this->callApi($startTime, $endTime);
            
            if (!$response['success']) {
                Log::error('TeamLogger API failed', ['error' => $response['error']]);
                return [];
            }

            // Process response
            $employeeDataMap = $this->processApiResponse($response['data']);
            
            // Cache the result
            self::$cache[$cacheKey] = $employeeDataMap;
            Log::info("TeamLogger: Cached " . count($employeeDataMap) . " employee records");

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
        
        // Handle special case mapping
        if ($emailKey === 'customercare@5core.com') {
            $emailKey = 'debhritiksha@gmail.com';
        }

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

            // Handle special case email mapping
            if ($emailKey === 'customercare@5core.com') {
                $emailKey = 'debhritiksha@gmail.com';
            }

            // Extract hours data from various possible fields
            $totalHours = 0;
            $rawTotalHours = 0;
            $idleHours = 0;

            if (!empty($rec['totalHours'])) {
                $rawTotalHours = floatval($rec['totalHours']);
                $idleHours = isset($rec['idleHours']) ? floatval($rec['idleHours']) : 0;
                $totalHours = $rawTotalHours - $idleHours;
            } elseif (!empty($rec['onComputerHours'])) {
                $totalHours = floatval($rec['onComputerHours']);
            } elseif (!empty($rec['workHours'])) {
                $totalHours = floatval($rec['workHours']);
            } elseif (!empty($rec['hours'])) {
                $totalHours = floatval($rec['hours']);
            }

            // Store processed data
            $employeeDataMap[$emailKey] = [
                'hours' => (int) round($totalHours),
                'total_hours' => round($rawTotalHours, 2),
                'idle_hours' => round($idleHours, 2),
                'active_hours' => round($totalHours, 2)
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
        $startTime = Carbon::parse($startDate)->setTime(12, 0, 0)->utc()->getTimestamp() * 1000;
        $endTime = Carbon::parse($endDate)->addDay()->setTime(11, 59, 59)->utc()->getTimestamp() * 1000;

        return $this->callApi($startTime, $endTime);
    }
}
