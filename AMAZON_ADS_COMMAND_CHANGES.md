# Amazon Ads Command Files - Tracking & Reporting Updates

## Overview
Enhanced the Amazon Ads console command files to track and report campaigns that fail to update, matching the improvements made to the web controller methods.

## Files Modified

### 1. AutoUpdateAmazonBgtKw.php
**Location:** `app/Console/Commands/AutoUpdateAmazonBgtKw.php`

**What Changed:**
- Added detailed tracking of skipped campaigns with specific reasons
- Enhanced reporting to show exactly which campaigns failed and why
- Displays comprehensive summary after execution

**New Features:**
- Tracks campaigns skipped due to:
  - Missing or empty `campaign_id`
  - Invalid SBGT (zero, negative, null)
  - SBGT exceeding bid cap
- Shows skip report even when all campaigns fail validation
- Displays final summary with total submitted, processed, and skipped counts

## Command Output Example

### Before (Old Output):
```
Starting Amazon bgts auto-update...
✓ Database connection OK
Found 10 campaigns to process.
No valid campaigns found (all have empty campaign_id or invalid budget).
```

### After (New Output):
```
Starting Amazon bgts auto-update...
✓ Database connection OK
Found 10 campaigns to process.
No valid campaigns found (all have empty campaign_id or invalid budget).

========================================
SKIPPED CAMPAIGNS REPORT
========================================
Total Submitted: 10
Total Processed: 0
Total Skipped: 10

Campaign: Summer Sale KW
  - Campaign ID: (empty)
  - SBGT: 25
  - Reason: Missing or empty campaign_id
---
Campaign: Winter Products
  - Campaign ID: 123456789
  - SBGT: 0
  - Reason: Invalid SBGT (must be positive number > 0)
---
Campaign: Spring Collection
  - Campaign ID: 987654321
  - SBGT: 75
  - Reason: SBGT $75 exceeds Bid Cap $50
---
========================================
```

### Successful Update with Some Skips:
```
========================================
FINAL UPDATE SUMMARY
========================================
Total Submitted: 10
Total Processed: 7
Total Skipped: 3

SKIPPED CAMPAIGNS:
  - Summer Sale KW: Missing or empty campaign_id
  - Winter Products: Invalid SBGT (must be positive number > 0)
  - Spring Collection: SBGT $75 exceeds Bid Cap $50
========================================
```

## Skip Reasons

### 1. Missing or empty campaign_id
**Cause:** Campaign ID is null, empty string, or just whitespace
**Fix:** Ensure campaign data includes valid campaign_id from Amazon

### 2. Invalid SBGT (must be positive number > 0)
**Cause:** SBGT value is:
- null
- 0 or negative
- Not set
**Fix:** Check SBGT calculation logic in `amazonAcosKwControlData()` method

### 3. SBGT exceeds Bid Cap
**Cause:** Calculated SBGT is higher than the configured bid cap for that SKU
**Fix:** Either:
- Increase the bid cap in `amazon_bid_caps` table
- Review SBGT calculation if it seems too high

## How to Use

### Run Command Normally:
```bash
php artisan amazon:auto-update-amz-bgt-kw
```

### Dry Run (Test Mode):
```bash
php artisan amazon:auto-update-amz-bgt-kw --dry-run
```

### Check Logs:
The command also logs to Laravel's log file:
```bash
tail -f storage/logs/laravel.log | grep "amazon:auto-update-amz-bgt-kw"
```

## Other Command Files to Update

The following command files follow a similar pattern and should receive the same updates:

### Budget Update Commands:
- ✅ `AutoUpdateAmazonBgtKw.php` (UPDATED)
- `AutoUpdateAmazonBgtPt.php` - Product Targeting budgets
- `AutoUpdateAmazonBgtHl.php` - Headline budgets
- `UpdateAmazonFbaKwBudgetCronCommand.php` - FBA KW budgets
- `UpdateAmazonFbaPtBudgetCronCommand.php` - FBA PT budgets

### Bid Update Commands:
- `AutoUpdateAmazonKwBids.php` - Already has skip/fail tracking (good!)
- `AutoUpdateAmazonPtBids.php` - Product Targeting bids
- `AutoUpdateAmazonHlBids.php` - Headline bids
- `AutoUpdateAmazonFbaUnderKwBids.php` - FBA Under-utilized KW bids
- `AutoUpdateAmazonFbaOverKwBids.php` - FBA Over-utilized KW bids
- `AutoUpdateAmazonFbaUnderPtBids.php` - FBA Under-utilized PT bids
- `AutoUpdateAmazonFbaOverPtBids.php` - FBA Over-utilized PT bids

## Benefits

### 1. Transparency
- Know exactly which campaigns were updated and which weren't
- Understand why specific campaigns failed

### 2. Debugging
- Quickly identify data quality issues
- Find campaigns with missing IDs or invalid values

### 3. Monitoring
- Track success rate over time
- Set up alerts for high skip rates

### 4. Audit Trail
- Log output provides record of what was attempted
- Can trace issues back to specific runs

## Integration with Monitoring

### Add Alerting:
```php
// At end of command handle() method
$skipRate = count($skippedCampaigns) / count($campaigns) * 100;

if ($skipRate > 10) {
    // Send warning notification
    \Log::warning('High skip rate in amazon:auto-update-amz-bgt-kw', [
        'skip_rate' => $skipRate,
        'total' => count($campaigns),
        'skipped' => count($skippedCampaigns),
    ]);
}

if ($skipRate > 50) {
    // Send critical alert (email, Slack, etc.)
    \Notification::route('mail', 'admin@example.com')
        ->notify(new HighSkipRateAlert($skipRate, count($campaigns)));
}
```

### Track in Database:
```sql
CREATE TABLE amazon_ads_command_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    command_name VARCHAR(100) NOT NULL,
    total_submitted INT NOT NULL,
    total_processed INT NOT NULL,
    total_skipped INT NOT NULL,
    skipped_details JSON,
    run_duration_seconds INT,
    status VARCHAR(50),
    run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_command_name (command_name),
    INDEX idx_run_at (run_at)
);
```

## Testing

### Test Case 1: All Valid Data
**Setup:** Ensure all campaigns have valid campaign_id and SBGT > 0
**Expected:** total_skipped = 0, all campaigns processed

### Test Case 2: Missing Campaign IDs
**Setup:** Remove campaign_id from some campaigns
**Expected:** Those campaigns appear in skip report with reason "Missing or empty campaign_id"

### Test Case 3: Invalid SBGT
**Setup:** Set SBGT to 0 or negative for some campaigns
**Expected:** Those campaigns appear in skip report with reason "Invalid SBGT"

### Test Case 4: Bid Cap Exceeded
**Setup:** Set bid cap lower than SBGT for some campaigns
**Expected:** Those campaigns appear in skip report with reason "SBGT $X exceeds Bid Cap $Y"

### Test Case 5: Dry Run Mode
```bash
php artisan amazon:auto-update-amz-bgt-kw --dry-run
```
**Expected:** Shows what would be updated without actually calling Amazon API

## Troubleshooting

### Issue: All campaigns are being skipped

**Check:**
1. Database connectivity - are campaigns loading from DB?
2. Campaign ID format - are IDs being properly extracted?
3. SBGT calculation - is `amazonAcosKwControlData()` working correctly?
4. Bid caps - are they set too low?

**Debug:**
```bash
# Add this temporarily in amazonAcosKwControlData():
\Log::info('Campaign loaded', [
    'sku' => $sku,
    'campaign_id' => $matchedCampaignL30->campaign_id ?? 'NONE',
    'sbgt' => $row['sbgt'] ?? 'NOT SET',
]);
```

### Issue: Skip reasons are unclear

**Solution:** The skip report now shows exact values and reasons. Check:
- The campaign_id value (shows "(empty)" if blank)
- The SBGT value
- The specific reason text

### Issue: Need to export skip report

**Solution:** Redirect command output to file:
```bash
php artisan amazon:auto-update-amz-bgt-kw > budget_update_report.txt 2>&1
```

Or parse from logs:
```bash
grep "SKIPPED CAMPAIGNS" storage/logs/laravel.log -A 50
```

## Next Steps

1. **Apply to Other Commands:** Use the same pattern for all other Amazon Ads command files
2. **Add Database Logging:** Store run results in database for historical tracking
3. **Create Dashboard:** Build admin page to view command run history and skip rates
4. **Set Up Alerts:** Implement notifications for high skip rates or failures
5. **Automated Reports:** Generate daily summary emails of all command runs

## Example Implementation for Other Commands

To apply similar tracking to other command files, follow this pattern:

```php
// 1. Add tracking array
$skippedCampaigns = [];

// 2. Track skips in filter
$validCampaigns = collect($campaigns)->filter(function ($campaign, $index) use (&$skippedCampaigns) {
    if (empty($campaign->campaign_id)) {
        $skippedCampaigns[] = [
            'index' => $index,
            'campaign_id' => $campaign->campaign_id ?? '',
            'campaign_name' => $campaign->campaignName ?? 'Unknown',
            'value' => $campaign->bid ?? $campaign->sbgt ?? null,
            'reason' => 'Missing or empty campaign_id',
        ];
        return false;
    }
    // ... other validations
    return true;
});

// 3. Display report
$this->info("Total Submitted: " . count($campaigns));
$this->info("Total Processed: " . count($validCampaigns));
$this->warn("Total Skipped: " . count($skippedCampaigns));

if (!empty($skippedCampaigns)) {
    foreach ($skippedCampaigns as $skipped) {
        $this->warn("  - {$skipped['campaign_name']}: {$skipped['reason']}");
    }
}
```

---

**All command changes have been tested for syntax errors. No linter errors found.**
