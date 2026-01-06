<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking Walmart Tables in Inventory Database ===\n\n";

// Check for Walmart-related tables
$tables = DB::select("SHOW TABLES LIKE '%walmart%'");

echo "Walmart tables found:\n";
foreach ($tables as $table) {
    $tableName = array_values((array)$table)[0];
    echo "- $tableName\n";
    
    // Get row count
    $count = DB::table($tableName)->count();
    echo "  Rows: $count\n";
    
    // Get sample row
    if ($count > 0) {
        $sample = DB::table($tableName)->first();
        echo "  Sample columns: " . implode(', ', array_keys((array)$sample)) . "\n";
    }
    echo "\n";
}

echo "\n=== Done ===\n";

