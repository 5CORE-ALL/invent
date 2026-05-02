# Amazon Ads - Complete SP & SB Command Updates

## ✅ ALL FILES UPDATED

Applied detailed tracking and reporting to both Sponsored Products (SP) and Sponsored Brands (SB) command files.

### Updated Files:

#### Budget Commands:
1. ✅ **AutoUpdateAmazonBgtKw.php** - SP Keyword Budgets
2. ✅ **AutoUpdateAmazonBgtPt.php** - SP Product Targeting Budgets
3. ✅ **AutoUpdateAmazonBgtHl.php** - SB Headline Budgets

#### Bid Commands:
4. ✅ **AutoUpdateAmazonPtBids.php** - SP Product Targeting Bids

## 📋 Changes Applied to Each File

### Common Improvements:
1. **Detailed Skip Tracking** - Captures index, campaign ID, campaign name, value, and specific reason for each skip
2. **Enhanced Error Messages** - Clear, actionable reasons for why campaigns were skipped
3. **Summary Reports** - Shows total submitted, processed, and skipped counts

4. **Skip Report Display** - Lists first 10 skipped campaigns with full details

### Skip Reasons Tracked:

**All Commands:**
- Missing or empty campaign_id
- Invalid bid/SBGT (zero, negative, or null)
- Duplicate campaign_id (for bid commands)
- SBGT/Bid exceeds configured bid cap

## 📊 Output Examples

### Keyword Budget Update (KW):
```bash
php artisan amazon:auto-update-amz-bgt-kw

========================================
FINAL UPDATE SUMMARY (KW)
========================================
Total Submitted: 15
Total Processed: 12
Total Skipped: 3

SKIPPED CAMPAIGNS:
  - Summer Sale KW: Missing or empty campaign_id
  - Winter Products: Invalid SBGT (must be positive number > 0)
  - Spring Collection: SBGT $75 exceeds Bid Cap $50
========================================
```

### Product Targeting Budget Update (PT):
```bash
php artisan amazon:auto-update-amz-bgt-pt

========================================
FINAL UPDATE SUMMARY (PT)
========================================
Total Submitted: 20
Total Processed: 18
Total Skipped: 2

SKIPPED CAMPAIGNS:
  - Product A PT: Missing or empty campaign_id
  - Product B PT: Invalid SBGT (must be positive number > 0)
========================================
```

### Headline/Sponsored Brands Budget Update (HL/SB):
```bash
php artisan amazon:auto-update-amz-bgt-hl

========================================
FINAL UPDATE SUMMARY (HL/SB)
========================================
Total Submitted: 8
Total Processed: 7
Total Skipped: 1

SKIPPED CAMPAIGNS:
  - Brand Campaign HEAD: SBGT $100 exceeds Bid Cap $80
========================================
```

### Product Targeting Bids Update (PT BIDS):
```bash
php artisan amazon:auto-update-over-pt-bids

========================================
FINAL UPDATE SUMMARY (PT BIDS)
========================================
Total Submitted: 25
Total Processed: 22
Total Skipped: 3

SKIPPED CAMPAIGNS:
  - Product X PT: Invalid bid (must be positive number > 0)
  - Product Y PT: Missing or empty campaign_id
  - Product Z PT: Duplicate campaign_id (using first occurrence)
========================================
```

## 🧪 Testing Commands

### Test Each Command with Dry Run:
```bash
# Keyword budgets (SP)
php artisan amazon:auto-update-amz-bgt-kw --dry-run

# Product Targeting budgets (SP)
php artisan amazon:auto-update-amz-bgt-pt --dry-run

# Headline budgets (SB)
php artisan amazon:auto-update-amz-bgt-hl --dry-run

# Product Targeting bids (SP)
php artisan amazon:auto-update-over-pt-bids --dry-run
```

### Run Actual Updates:
```bash
# Remove --dry-run flag to apply changes
php artisan amazon:auto-update-amz-bgt-kw
php artisan amazon:auto-update-amz-bgt-pt
php artisan amazon:auto-update-amz-bgt-hl
php artisan amazon:auto-update-over-pt-bids
```

## 🎯 Benefits Across All Commands

### 1. Transparency
- Know exactly which campaigns in each type (KW/PT/HL/SB) were updated
- Understand why specific campaigns failed
- Track success rates per campaign type

### 2. Data Quality
- Identify campaigns with missing IDs
- Find campaigns with invalid bid/budget values
- Detect campaigns exceeding bid caps
- Spot duplicate entries

### 3. Debugging
- Pinpoint issues by campaign type (SP vs SB)
- Quickly see patterns in failures
- Compare skip rates across different campaign types

### 4. Reporting
- Export skip reports for each command type
- Track historical performance per campaign type
- Set up alerts for high skip rates

## 📈 Monitoring Strategy

### Track These Metrics:

**Per Command Type:**
- Skip Rate % for KW campaigns
- Skip Rate % for PT campaigns
- Skip Rate % for HL/SB campaigns
- Skip Rate % for PT bids

**Overall:**
- Total campaigns updated daily (all types)
- Total campaigns skipped daily (all types)
- Most common skip reason per type
- Campaigns that consistently fail

### Alert Thresholds:

**Warning Level (Skip Rate > 10%):**
```php
if ($skipRate > 10 && $skipRate <= 50) {
    Log::warning("Elevated skip rate for {$commandType}", [
        'skip_rate' => $skipRate,
        'command' => $commandType,
    ]);
}
```

**Critical Level (Skip Rate > 50%):**
```php
if ($skipRate > 50) {
    Log::error("Critical skip rate for {$commandType}", [
        'skip_rate' => $skipRate,
        'command' => $commandType,
    ]);
    // Send email/Slack notification
}
```

## 🔍 Troubleshooting by Campaign Type

### SP Keyword Issues:
- Check campaign names don't contain " PT" or end with " PT."
- Verify SBGT values are calculated from ACOS correctly
- Ensure inventory > 0

### SP Product Targeting Issues:
- Check campaign names end with " PT" or " PT."
- Verify PT campaigns have valid targeting data
- Check UB2/UB1 utilization metrics

### SB Headline Issues:
- Check campaign names match SKU or SKU + " HEAD"
- Verify SB campaign data is syncing from Amazon
- Check SBGT calculation for SB campaigns

### General Issues:
- Database connection problems
- Missing campaign data in recent report windows
- Bid caps set too low
- Data quality issues in source tables

## 📝 Quick Reference

### Command Summary:
| Command | Type | Updates | Dry Run Flag |
|---------|------|---------|--------------|
| `amazon:auto-update-amz-bgt-kw` | SP Budget | KW Campaigns | `--dry-run` |
| `amazon:auto-update-amz-bgt-pt` | SP Budget | PT Campaigns | `--dry-run` |
| `amazon:auto-update-amz-bgt-hl` | SB Budget | HL Campaigns | `--dry-run` |
| `amazon:auto-update-over-pt-bids` | SP Bid | PT Campaigns | `--dry-run` |

### Common Skip Reasons by Type:

**All Types:**
- Missing campaign_id
- Invalid value (≤ 0)
- Exceeds bid cap

**Bid Commands Only:**
- Duplicate campaign_id

**Budget Commands Only:**
- Invalid SBGT tier (must match configured values)

## 🚀 Next Steps

### Immediate:
1. ✅ Test each command with `--dry-run`
2. ✅ Review skip reports for data quality issues
3. ✅ Fix any campaigns with missing IDs or invalid values

### Short Term:
4. Set up automated daily runs in scheduler
5. Create dashboard to visualize skip rates by type
6. Implement email alerts for high skip rates

### Long Term:
7. Add database logging for historical tracking
8. Build admin interface to review skip history
9. Automated retry system for commonly failed campaigns
10. Predictive alerts based on historical patterns

## ✅ Validation

All updated files have been checked:
- ✅ No syntax errors
- ✅ No linter errors
- ✅ Consistent error handling
- ✅ Clear, actionable error messages
- ✅ Comprehensive reporting

---

**Status:** Complete - All SP and SB command files updated with tracking and reporting
**Date:** Saturday, May 2, 2026
**Files Updated:** 4 command files (KW, PT, HL budgets + PT bids)
**No Linter Errors:** Confirmed
