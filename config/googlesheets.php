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

    /*
    |--------------------------------------------------------------------------
    | Ready To Ship — Packing list links (public CSV)
    |--------------------------------------------------------------------------
    |
    | Point csv_url at a Google Sheet CSV export so the Ready To Ship table can
    | show a "ready-to-ship" link per SKU. Recommended setup:
    | 1. Create a sheet with column A = SKU, column B = full URL (https://...).
    | 2. Share the file: "Anyone with the link" → Viewer (or publish to web).
    | 3. CSV export URL (replace SHEET_ID and gid):
    |    https://docs.google.com/spreadsheets/d/SHEET_ID/export?format=csv&gid=0
    |
    | sheet_edit_url is optional: opens the spreadsheet for editing from the column header.
    |
    | To push links from the app into the sheet, use the same spreadsheet and share it with
    | your Google service account (email inside google-credentials.json) as Editor.
    | spreadsheet_id defaults to the ID parsed from csv_url if not set.
    | sheet_tab is the tab name (default Sheet1).
    |
    */
    'ready_to_ship_packing_list' => [
        'csv_url' => env('READY_TO_SHIP_PACKING_LIST_CSV_URL', ''),
        'sheet_edit_url' => env('READY_TO_SHIP_PACKING_LIST_SHEET_URL', ''),
        'spreadsheet_id' => env('READY_TO_SHIP_PACKING_LIST_SPREADSHEET_ID', ''),
        'sheet_tab' => env('READY_TO_SHIP_PACKING_LIST_SHEET_TAB', 'Sheet1'),
        'cache_seconds' => (int) env('READY_TO_SHIP_PACKING_LIST_CACHE_SECONDS', 120),
    ],
];
