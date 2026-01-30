<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Sheets Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Google Sheets export settings for various features
    |
    */

    'verification_adjustment' => [
        // Set to true to use the same spreadsheet for all exports (updates existing data)
        // Set to false to create a new spreadsheet for each export
        'use_existing' => env('GOOGLE_SHEETS_USE_EXISTING', true),
        
        // Spreadsheet ID for verification adjustment exports
        // You can find this in the Google Sheets URL: 
        // https://docs.google.com/spreadsheets/d/{SPREADSHEET_ID}/edit
        // Leave null to create a new spreadsheet
        'spreadsheet_id' => env('GOOGLE_SHEETS_VERIFICATION_ADJUSTMENT_ID', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credentials Path
    |--------------------------------------------------------------------------
    |
    | Path to Google service account credentials JSON file
    | Default: storage/app/google-credentials.json
    |
    */
    'credentials_path' => env('GOOGLE_SHEETS_CREDENTIALS_PATH', storage_path('app/google-credentials.json')),
];
