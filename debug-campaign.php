<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$campaignName = "ACTIVE HOME 12 2-MIC KW";
echo "Searching for campaign: $campaignName\n\n";

// Search in all date ranges
$campaigns = DB::table('amazon_sp_campaign_reports')
    ->where('campaignName', 'LIKE', '%' . $campaignName . '%')
    ->orderBy('report_date_range', 'desc')
    ->get();

if ($campaigns->isEmpty()) {
    echo "❌ Campaign not found in ANY date range!\n";
    
    // Try searching with different variations
    echo "\nTrying partial matches:\n";
    $parts = explode(' ', $campaignName);
    foreach($parts as $part) {
        if(strlen($part) > 3) {
            $matches = DB::table('amazon_sp_campaign_reports')
                ->where('campaignName', 'LIKE', '%' . $part . '%')
                ->limit(5)
                ->pluck('campaignName');
            echo "  '$part' matches: " . $matches->implode(', ') . "\n";
        }
    }
    exit;
}

echo "✅ Campaign found in " . $campaigns->count() . " date ranges:\n";
foreach($campaigns as $c) {
    echo "  Date: {$c->report_date_range}\n";
    echo "    ID: {$c->campaign_id}\n";
    echo "    Status: {$c->campaignStatus}\n";
    echo "    Current bid: {$c->currentSpBidPrice}\n";
    echo "    SBID M: {$c->sbid_m}\n";
    echo "    Last SBID: {$c->last_sbid}\n";
    
    // Check L7 and L1 specifically
    if($c->report_date_range == 'L7' || $c->report_date_range == 'L1') {
        $budget = $c->campaignBudgetAmount ?? 0;
        $spend = $c->spend ?? 0;
        if($c->report_date_range == 'L7') {
            $ub7 = $budget > 0 ? ($spend / ($budget * 7)) * 100 : 0;
            echo "    UB7: " . round($ub7, 2) . "%\n";
        } else {
            $ub1 = $budget > 0 ? ($spend / $budget) * 100 : 0;
            echo "    UB1: " . round($ub1, 2) . "%\n";
        }
    }
    echo "\n";
}

// Check if it appears in job logs
echo "\nChecking job logs:\n";
$logs = ['amz-over-kw-bids-update.log', 'amz-under-kw-bids-update.log', 'amazon-pink-dil-kw-ads.log'];
foreach($logs as $log) {
    $path = __DIR__ . '/storage/logs/' . $log;
    if(file_exists($path)) {
        $cmd = "grep -c '" . $campaigns[0]->campaign_id . "' " . $path . " 2>/dev/null";
        $count = shell_exec($cmd);
        echo "  $log: " . ($count ? "✅ Found ($count times)" : "❌ Not found") . "\n";
    }
}
