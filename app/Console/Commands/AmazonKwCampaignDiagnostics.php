<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnose KW campaign count mismatch between Amazon (192) and app (83).
 * Run: php artisan amazon:kw-campaign-diagnostics
 */
class AmazonKwCampaignDiagnostics extends Command
{
    protected $signature = 'amazon:kw-campaign-diagnostics';
    protected $description = 'Diagnose KW campaign count mismatch (Amazon vs app)';

    public function handle()
    {
        $this->info('=== Amazon KW Campaign Diagnostics ===');
        $this->newLine();

        // 1. Total KW campaigns in DB (matching app query logic)
        $kwUnique = (int) DB::table('amazon_sp_campaign_reports')
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->where('campaignName', 'NOT LIKE', '%FBA')
            ->where('campaignName', 'NOT LIKE', '%FBA.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->distinct()
            ->count('campaign_id');

        $this->info("1. KW campaigns in DB (L30, excl. PT/FBA, not ARCHIVED): {$kwUnique}");
        $this->newLine();

        // 2. By status
        $byStatus = DB::table('amazon_sp_campaign_reports')
            ->select('campaignStatus', DB::raw('COUNT(DISTINCT campaign_id) as cnt'))
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->where('campaignName', 'NOT LIKE', '%FBA')
            ->where('campaignName', 'NOT LIKE', '%FBA.')
            ->groupBy('campaignStatus')
            ->get();

        $this->info('2. KW campaigns by status (L30):');
        foreach ($byStatus as $row) {
            $this->line("   - {$row->campaignStatus}: {$row->cnt}");
        }
        $this->newLine();

        // 3. ENABLED only (Active filter)
        $enabledCount = DB::table('amazon_sp_campaign_reports')
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->where('campaignName', 'NOT LIKE', '%FBA')
            ->where('campaignName', 'NOT LIKE', '%FBA.')
            ->where('campaignStatus', 'ENABLED')
            ->distinct()
            ->count('campaign_id');

        $this->info("3. KW campaigns ENABLED only (Active): {$enabledCount}");
        $this->newLine();

        // 4. Date ranges in DB
        $ranges = DB::table('amazon_sp_campaign_reports')
            ->select('report_date_range', DB::raw('COUNT(DISTINCT campaign_id) as cnt'))
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->where('campaignName', 'NOT LIKE', '%FBA')
            ->where('campaignName', 'NOT LIKE', '%FBA.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->groupBy('report_date_range')
            ->orderBy('report_date_range')
            ->get();

        $this->info('4. KW campaigns by report_date_range:');
        foreach ($ranges as $row) {
            $this->line("   - {$row->report_date_range}: {$row->cnt}");
        }
        $this->newLine();

        // 5. Sample campaign names (first 10)
        $samples = DB::table('amazon_sp_campaign_reports')
            ->select('campaignName', 'campaign_id', 'campaignStatus')
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->where('campaignName', 'NOT LIKE', '%FBA')
            ->where('campaignName', 'NOT LIKE', '%FBA.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->groupBy('campaign_id', 'campaignName', 'campaignStatus')
            ->limit(10)
            ->get();

        $this->info('5. Sample KW campaign names:');
        foreach ($samples as $row) {
            $this->line("   - [{$row->campaignStatus}] {$row->campaignName} (id: {$row->campaign_id})");
        }
        $this->newLine();

        $this->warn('Expected: Amazon 192 KW campaigns. If DB count is lower, possible causes:');
        $this->line('  - L30 report excludes campaigns with no recent activity');
        $this->line('  - app:amazon-sp-campaign-reports runs daily at 06:00 IST - data may be stale');
        $this->line('  - Amazon UI may include ARCHIVED or all-status campaigns');
        $this->line('  - Profile/scope mismatch between API and Amazon Ads UI');

        return 0;
    }
}
