<?php

namespace App\Services;

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;
use Google_Service_Sheets_Spreadsheet;
use Google_Service_Sheets_SpreadsheetProperties;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleSheetsService
{
    protected $client;
    protected $service;

    public function __construct()
    {
        $this->initializeClient();
    }

    /**
     * Initialize Google Client
     */
    protected function initializeClient()
    {
        try {
            $this->client = new Google_Client();
            $this->client->setApplicationName('Inventory Management System');
            $this->client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
            $this->client->setAccessType('offline');

            // Check if credentials file exists
            $credentialsPath = storage_path('app/google-credentials.json');
            
            if (!file_exists($credentialsPath)) {
                Log::warning('Google credentials file not found at: ' . $credentialsPath);
                throw new Exception('Google credentials file not found. Please add google-credentials.json to storage/app/');
            }

            $this->client->setAuthConfig($credentialsPath);
            $this->service = new Google_Service_Sheets($this->client);
            
        } catch (Exception $e) {
            Log::error('Failed to initialize Google Sheets client: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a new spreadsheet or update existing one
     * 
     * @param array $data Array of data rows to export
     * @param string $sheetTitle Title for the sheet
     * @param string|null $spreadsheetId Optional existing spreadsheet ID to update
     * @return array ['spreadsheetId' => string, 'spreadsheetUrl' => string]
     */
    public function exportToSheet(array $data, string $sheetTitle = 'Verification Data', ?string $spreadsheetId = null)
    {
        try {
            // If spreadsheet ID is provided, update existing sheet, otherwise create new one
            if ($spreadsheetId) {
                return $this->updateExistingSheet($spreadsheetId, $data, $sheetTitle);
            } else {
                return $this->createNewSheet($data, $sheetTitle);
            }
        } catch (Exception $e) {
            Log::error('Failed to export to Google Sheets: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a new spreadsheet
     * 
     * @param array $data Array of data rows
     * @param string $sheetTitle Title for the sheet
     * @return array ['spreadsheetId' => string, 'spreadsheetUrl' => string]
     */
    protected function createNewSheet(array $data, string $sheetTitle)
    {
        try {
            // Create new spreadsheet
            $spreadsheet = new Google_Service_Sheets_Spreadsheet([
                'properties' => [
                    'title' => $sheetTitle . ' - ' . now()->format('Y-m-d H:i:s')
                ]
            ]);

            $spreadsheet = $this->service->spreadsheets->create($spreadsheet);
            $spreadsheetId = $spreadsheet->spreadsheetId;

            Log::info('Created new Google Sheet', ['spreadsheetId' => $spreadsheetId]);

            // Write data to the new sheet
            $this->writeData($spreadsheetId, $data, 'Sheet1');

            // Format the sheet (optional)
            $this->formatSheet($spreadsheetId);

            return [
                'spreadsheetId' => $spreadsheetId,
                'spreadsheetUrl' => "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit"
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to create new sheet: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update existing spreadsheet
     * 
     * @param string $spreadsheetId Existing spreadsheet ID
     * @param array $data Array of data rows
     * @param string $sheetTitle Title for the sheet tab
     * @return array ['spreadsheetId' => string, 'spreadsheetUrl' => string]
     */
    protected function updateExistingSheet(string $spreadsheetId, array $data, string $sheetTitle)
    {
        try {
            // Clear existing data
            $range = 'A1:Z10000'; // Clear a large range
            $clear = new Google_Service_Sheets_ValueRange();
            $this->service->spreadsheets_values->clear($spreadsheetId, $range, $clear);

            Log::info('Cleared existing data from Google Sheet', ['spreadsheetId' => $spreadsheetId]);

            // Write new data
            $this->writeData($spreadsheetId, $data, $sheetTitle);

            return [
                'spreadsheetId' => $spreadsheetId,
                'spreadsheetUrl' => "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit"
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to update existing sheet: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Write data to spreadsheet
     * 
     * @param string $spreadsheetId Spreadsheet ID
     * @param array $data Array of data rows
     * @param string $sheetName Sheet name/tab
     */
    protected function writeData(string $spreadsheetId, array $data, string $sheetName = 'Sheet1')
    {
        if (empty($data)) {
            Log::warning('No data to write to sheet');
            return;
        }

        // Convert associative array to indexed array with headers
        $headers = array_keys($data[0]);
        $rows = [$headers]; // First row is headers

        foreach ($data as $item) {
            $rows[] = array_values($item);
        }

        $body = new Google_Service_Sheets_ValueRange([
            'values' => $rows
        ]);

        $params = [
            'valueInputOption' => 'RAW'
        ];

        $range = $sheetName . '!A1';
        
        $result = $this->service->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $body,
            $params
        );

        Log::info('Data written to Google Sheet', [
            'spreadsheetId' => $spreadsheetId,
            'updatedCells' => $result->getUpdatedCells(),
            'updatedRows' => $result->getUpdatedRows()
        ]);
    }

    /**
     * Format the sheet (add header styling, freeze rows, etc.)
     * 
     * @param string $spreadsheetId Spreadsheet ID
     */
    protected function formatSheet(string $spreadsheetId)
    {
        try {
            $requests = [
                // Freeze first row (header)
                [
                    'updateSheetProperties' => [
                        'properties' => [
                            'sheetId' => 0,
                            'gridProperties' => [
                                'frozenRowCount' => 1
                            ]
                        ],
                        'fields' => 'gridProperties.frozenRowCount'
                    ]
                ],
                // Bold header row
                [
                    'repeatCell' => [
                        'range' => [
                            'sheetId' => 0,
                            'startRowIndex' => 0,
                            'endRowIndex' => 1
                        ],
                        'cell' => [
                            'userEnteredFormat' => [
                                'textFormat' => [
                                    'bold' => true
                                ],
                                'backgroundColor' => [
                                    'red' => 0.9,
                                    'green' => 0.9,
                                    'blue' => 0.9
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(textFormat,backgroundColor)'
                    ]
                ],
                // Auto-resize columns
                [
                    'autoResizeDimensions' => [
                        'dimensions' => [
                            'sheetId' => 0,
                            'dimension' => 'COLUMNS',
                            'startIndex' => 0,
                            'endIndex' => 20
                        ]
                    ]
                ]
            ];

            $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => $requests
            ]);

            $this->service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
            
            Log::info('Sheet formatted successfully', ['spreadsheetId' => $spreadsheetId]);
            
        } catch (Exception $e) {
            Log::warning('Failed to format sheet (non-critical): ' . $e->getMessage());
            // Don't throw exception for formatting errors
        }
    }

    /**
     * Get existing spreadsheet ID from environment or config
     * 
     * @param string $key Configuration key
     * @return string|null
     */
    public function getStoredSpreadsheetId(string $key = 'verification_adjustment')
    {
        return config("googlesheets.{$key}.spreadsheet_id");
    }

    /**
     * Share spreadsheet with email address
     * 
     * @param string $spreadsheetId Spreadsheet ID
     * @param string $email Email address to share with
     * @param string $role Role (reader, writer, owner)
     */
    public function shareSpreadsheet(string $spreadsheetId, string $email, string $role = 'writer')
    {
        try {
            $driveService = new \Google_Service_Drive($this->client);
            
            $permission = new \Google_Service_Drive_Permission([
                'type' => 'user',
                'role' => $role,
                'emailAddress' => $email
            ]);

            $driveService->permissions->create($spreadsheetId, $permission, [
                'sendNotificationEmail' => false
            ]);

            Log::info('Spreadsheet shared', [
                'spreadsheetId' => $spreadsheetId,
                'email' => $email,
                'role' => $role
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to share spreadsheet: ' . $e->getMessage());
            throw $e;
        }
    }
}
