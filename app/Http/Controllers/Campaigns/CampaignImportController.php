<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Http\Controllers\JungleScoutController;
use Illuminate\Http\Request;
use App\Jobs\ProcessCampaignCsvChunk;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSpCampaignReport;
use App\Models\Campaign;
use App\Models\JungleScoutProductData;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; // <-- Add this
use Illuminate\Support\Facades\Validator;

class CampaignImportController extends Controller
{

    public function updateField(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'    => 'required|integer',
            'field' => 'required|string|in:note,sbid,yes_sbid,move',
            'value' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $id = $request->id;
        $field = $request->field;
        $value = $request->value;

        $tables = [
            'amazon_sp_campaign_reports',
            'amazon_sd_campaign_reports',
            'amazon_sb_campaign_reports',
        ];

        foreach ($tables as $table) {
            $exists = DB::table($table)->where('id', $id)->exists();

            if ($exists) {
                if ($field === 'move') {
                    $current = DB::table($table)->where('id', $id)->first();
                    if ($current && !empty($current->sbid)) {
                        DB::table($table)->where('id', $id)->update([
                            'ysid' => ($current->sbid == 'done') ? '' : $current->sbid,
                            'sbid' => null,
                            'updated_at' => Carbon::now()
                        ]);

                        return response()->json([
                            'success' => true,
                            'message' => "sbid moved to ysid in table '$table'."
                        ]);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => "No sbid value to move for ID $id in table $table."
                        ], 400);
                    }
                } else {

                    DB::table($table)
                        ->where('id', $id)
                        ->update([
                            $field => $value,
                            'updated_at' => DB::raw('NOW()')
                        ]);

                    return response()->json([
                        'success' => true,
                        'message' => "Field '$field' updated in table '$table'."
                    ]);
                }
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'ID not found in any campaign table.'
        ], 404);
    }

    public function index()
    {
        $campaigns = DB::select("
    SELECT * FROM (
        SELECT 
            id,
            campaign_id,
            note,
            sbid,
            yes_sbid,
            profile_id,
            ad_type,
            report_date_range,
            campaignName,
            clicks,
            cost,
            impressions,
            startDate,
            endDate,
            sales,
            purchases,
            unitsSold,
            campaignBudgetAmount,
            campaignBudgetCurrencyCode,
            'SB' as source
        FROM amazon_sb_campaign_reports

        UNION ALL

        SELECT 
            id,
            campaign_id,
            note,
            sbid,
            yes_sbid,
            profile_id,
            ad_type,
            report_date_range,
            campaignName,
            clicks,
            cost,
            impressions,
            startDate,
            endDate,
            sales,
            purchases,
            unitsSold,
            NULL as campaignBudgetAmount,
            campaignBudgetCurrencyCode,
            'SD' as source
        FROM amazon_sd_campaign_reports

        UNION ALL

        SELECT 
            id,
            campaign_id,
            note,
            sbid,
            yes_sbid,
            profile_id,
            ad_type,
            report_date_range,
            campaignName,
            clicks,
            cost,
            impressions,
            startDate,
            endDate,
            sales30d as sales,
            purchases30d as purchases,
            unitsSoldClicks30d as unitsSold,
            campaignBudgetAmount,
            campaignBudgetCurrencyCode,
            'SP' as source
        FROM amazon_sp_campaign_reports
    ) AS campaign_id
    ORDER BY id DESC
");
        return view('campaign.campaign', compact('campaigns'));
    }
    public function getCampaigns(Request $request)
    {
        $draw = $request->input('draw');
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $search = $request->input('search.value');
        $order = $request->input('order');
        $columns = $request->input('columns');

        $startDateFilter = $request->input('start_date');
        $endDateFilter = $request->input('end_date');

        \DB::enableQueryLog();

        $subQuery = "
        SELECT 
    base.*,
    ce.parent AS parent,

    -- Clicks
    COALESCE(clicks_by_range.l7_clicks, 0) AS l7_clicks,
    COALESCE(clicks_by_range.l15_clicks, 0) AS l15_clicks,
    COALESCE(clicks_by_range.l30_clicks, 0) AS l30_clicks,
    COALESCE(clicks_by_range.l60_clicks, 0) AS l60_clicks,

    -- Spend
    COALESCE(spend_by_range.l1_spend, 0) AS l1_spend,
    COALESCE(spend_by_range.l7_spend, 0) AS l7_spend,
    COALESCE(spend_by_range.l15_spend, 0) AS l15_spend,
    COALESCE(spend_by_range.l30_spend, 0) AS l30_spend,
    COALESCE(spend_by_range.l60_spend, 0) AS l60_spend,

    -- Sales
    COALESCE(sales_by_range.l7_sales, 0) AS l7_sales,
    COALESCE(sales_by_range.l15_sales, 0) AS l15_sales,
    COALESCE(sales_by_range.l30_sales, 0) AS l30_sales,
    COALESCE(sales_by_range.l60_sales, 0) AS l60_sales,

    -- CPC
    COALESCE(cpc_by_range.l7_cpc, 0) AS l7_cpc,
    COALESCE(cpc_by_range.l15_cpc, 0) AS l15_cpc,
    COALESCE(cpc_by_range.l30_cpc, 0) AS l30_cpc,
    COALESCE(cpc_by_range.l60_cpc, 0) AS l60_cpc,

    -- Orders (Purchases)
    COALESCE(order_by_range.l7_orders, 0) AS l7_orders,
    COALESCE(order_by_range.l15_orders, 0) AS l15_orders,
    COALESCE(order_by_range.l30_orders, 0) AS l30_orders,
    COALESCE(order_by_range.l60_orders, 0) AS l60_orders,

    -- ACOS
    ROUND(COALESCE(spend_by_range.l7_spend, 0) / NULLIF(sales_by_range.l7_sales, 0) * 100, 2) AS l7_acos,
    ROUND(COALESCE(spend_by_range.l15_spend, 0) / NULLIF(sales_by_range.l15_sales, 0) * 100, 2) AS l15_acos,
    ROUND(COALESCE(spend_by_range.l30_spend, 0) / NULLIF(sales_by_range.l30_sales, 0) * 100, 2) AS l30_acos,
    ROUND(COALESCE(spend_by_range.l60_spend, 0) / NULLIF(sales_by_range.l60_sales, 0) * 100, 2) AS l60_acos

FROM (
    SELECT 
        id, campaign_id, note, sbid, yes_sbid, profile_id, ad_type,
        report_date_range, campaignName, clicks, cost, impressions,
        startDate, endDate, sales, purchases, unitsSold,
        campaignBudgetAmount, campaignBudgetCurrencyCode, 'SB' AS source,campaignStatus
    FROM amazon_sb_campaign_reports
    UNION ALL
    SELECT 
        id, campaign_id, note, sbid, yes_sbid, profile_id, ad_type,
        report_date_range, campaignName, clicks, cost, impressions,
        startDate, endDate, sales, purchases, unitsSold,
        NULL AS campaignBudgetAmount, campaignBudgetCurrencyCode, 'SD' AS source,campaignStatus
    FROM amazon_sd_campaign_reports
    UNION ALL
    SELECT 
        id, campaign_id, note, sbid, yes_sbid, profile_id, ad_type,
        report_date_range, campaignName, clicks, cost, impressions,
        startDate, endDate, sales30d AS sales, purchases30d AS purchases,
        unitsSoldClicks30d AS unitsSold, campaignBudgetAmount,
        campaignBudgetCurrencyCode, 'SP' AS source,campaignStatus
    FROM amazon_sp_campaign_reports
) AS base
LEFT JOIN campaign_entries ce
    ON TRIM(base.campaignName) = TRIM(ce.campaign_name)
-- Clicks
LEFT JOIN (
    SELECT campaign_id, profile_id, source,
        SUM(CASE WHEN report_date_range = 'L7' THEN clicks ELSE 0 END) AS l7_clicks,
        SUM(CASE WHEN report_date_range = 'L15' THEN clicks ELSE 0 END) AS l15_clicks,
        SUM(CASE WHEN report_date_range = 'L30' THEN clicks ELSE 0 END) AS l30_clicks,
        SUM(CASE WHEN report_date_range = 'L60' THEN clicks ELSE 0 END) AS l60_clicks
    FROM (
        SELECT campaign_id, profile_id, report_date_range, clicks, 'SB' AS source FROM amazon_sb_campaign_reports
        UNION ALL
        SELECT campaign_id, profile_id, report_date_range, clicks, 'SD' AS source FROM amazon_sd_campaign_reports
        UNION ALL
        SELECT campaign_id, profile_id, report_date_range, clicks, 'SP' AS source FROM amazon_sp_campaign_reports
    ) AS reports
    GROUP BY campaign_id, profile_id, source
) AS clicks_by_range
ON base.campaign_id = clicks_by_range.campaign_id AND base.profile_id = clicks_by_range.profile_id AND base.source = clicks_by_range.source

-- Spend
LEFT JOIN (
    SELECT campaign_id, profile_id, source,
        SUM(CASE WHEN report_date_range = 'L1' THEN cost ELSE 0 END) AS l1_spend,
        SUM(CASE WHEN report_date_range = 'L7' THEN cost ELSE 0 END) AS l7_spend,
        SUM(CASE WHEN report_date_range = 'L15' THEN cost ELSE 0 END) AS l15_spend,
        SUM(CASE WHEN report_date_range = 'L30' THEN cost ELSE 0 END) AS l30_spend,
        SUM(CASE WHEN report_date_range = 'L60' THEN cost ELSE 0 END) AS l60_spend
    FROM (
        SELECT campaign_id, profile_id, report_date_range, cost, 'SB' AS source FROM amazon_sb_campaign_reports
        UNION ALL
        SELECT campaign_id, profile_id, report_date_range, cost, 'SD' AS source FROM amazon_sd_campaign_reports
        UNION ALL
        SELECT campaign_id, profile_id, report_date_range, cost, 'SP' AS source FROM amazon_sp_campaign_reports
    ) AS spend_reports
    GROUP BY campaign_id, profile_id, source
) AS spend_by_range
ON base.campaign_id = spend_by_range.campaign_id AND base.profile_id = spend_by_range.profile_id AND base.source = spend_by_range.source

-- Sales
LEFT JOIN (
    SELECT campaign_id, profile_id, source,
        SUM(CASE WHEN report_date_range = 'L7' THEN sales ELSE 0 END) AS l7_sales,
        SUM(CASE WHEN report_date_range = 'L15' THEN sales ELSE 0 END) AS l15_sales,
        SUM(CASE WHEN report_date_range = 'L30' THEN sales ELSE 0 END) AS l30_sales,
        SUM(CASE WHEN report_date_range = 'L60' THEN sales ELSE 0 END) AS l60_sales
    FROM (
        SELECT campaign_id, profile_id, report_date_range, sales, 'SB' AS source FROM amazon_sb_campaign_reports
        UNION ALL
        SELECT campaign_id, profile_id, report_date_range, sales, 'SD' AS source FROM amazon_sd_campaign_reports
        UNION ALL
        SELECT campaign_id, profile_id, report_date_range, sales30d AS sales, 'SP' AS source FROM amazon_sp_campaign_reports
    ) AS sales_reports
    GROUP BY campaign_id, profile_id, source
) AS sales_by_range
ON base.campaign_id = sales_by_range.campaign_id AND base.profile_id = sales_by_range.profile_id AND base.source = sales_by_range.source

-- CPC
LEFT JOIN (
    SELECT campaign_id, profile_id, source,
        ROUND(SUM(CASE WHEN report_date_range = 'L7' THEN cost ELSE 0 END) / NULLIF(SUM(CASE WHEN report_date_range = 'L7' THEN clicks ELSE 0 END), 0), 2) AS l7_cpc,
        ROUND(SUM(CASE WHEN report_date_range = 'L15' THEN cost ELSE 0 END) / NULLIF(SUM(CASE WHEN report_date_range = 'L15' THEN clicks ELSE 0 END), 0), 2) AS l15_cpc,
        ROUND(SUM(CASE WHEN report_date_range = 'L30' THEN cost ELSE 0 END) / NULLIF(SUM(CASE WHEN report_date_range = 'L30' THEN clicks ELSE 0 END), 0), 2) AS l30_cpc,
        ROUND(SUM(CASE WHEN report_date_range = 'L60' THEN cost ELSE 0 END) / NULLIF(SUM(CASE WHEN report_date_range = 'L60' THEN clicks ELSE 0 END), 0), 2) AS l60_cpc
    FROM (
        SELECT campaign_id, profile_id, report_date_range, clicks, cost, 'SB' AS source FROM amazon_sb_campaign_reports
        UNION ALL
        SELECT campaign_id, profile_id, report_date_range, clicks, cost, 'SD' AS source FROM amazon_sd_campaign_reports
        UNION ALL
        SELECT campaign_id, profile_id, report_date_range, clicks, cost, 'SP' AS source FROM amazon_sp_campaign_reports
    ) AS cpc_reports
    GROUP BY campaign_id, profile_id, source
) AS cpc_by_range
ON base.campaign_id = cpc_by_range.campaign_id AND base.profile_id = cpc_by_range.profile_id AND base.source = cpc_by_range.source

-- Orders
LEFT JOIN (
    SELECT campaign_id, profile_id, source,
        SUM(CASE WHEN report_date_range = 'L7' THEN purchases ELSE 0 END) AS l7_orders,
        SUM(CASE WHEN report_date_range = 'L15' THEN purchases ELSE 0 END) AS l15_orders,
        SUM(CASE WHEN report_date_range = 'L30' THEN purchases ELSE 0 END) AS l30_orders,
        SUM(CASE WHEN report_date_range = 'L60' THEN purchases ELSE 0 END) AS l60_orders
    FROM (
        SELECT campaign_id, profile_id, report_date_range, purchases, 'SB' AS source FROM amazon_sb_campaign_reports
        UNION ALL
        SELECT campaign_id, profile_id, report_date_range, purchases, 'SD' AS source FROM amazon_sd_campaign_reports
        UNION ALL
        SELECT campaign_id, profile_id, report_date_range, purchases30d AS purchases, 'SP' AS source FROM amazon_sp_campaign_reports
    ) AS order_reports
    GROUP BY campaign_id, profile_id, source
) AS order_by_range
ON base.campaign_id = order_by_range.campaign_id AND base.profile_id = order_by_range.profile_id AND base.source = order_by_range.source
    ";

        $query = DB::table(DB::raw("($subQuery) as campaign_id"));

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('campaignName', 'LIKE', "%{$search}%")
                    ->orWhere('ad_type', 'LIKE', "%{$search}%")
                    ->orWhere('note', 'LIKE', "%{$search}%")
                    ->orWhere('sbid', 'LIKE', "%{$search}%")
                    ->orWhere('yes_sbid', 'LIKE', "%{$search}%")
                    ->orWhere('campaignStatus', 'LIKE', "%{$search}%")
                    ->orWhere('ce.parent', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($startDateFilter)) {
            $query->whereDate('startDate', '>=', $startDateFilter);
        }

        if (!empty($endDateFilter)) {
            $query->whereDate('endDate', '<=', $endDateFilter);
        }

        if (!empty($order)) {
            $columnIndex = $order[0]['column'];
            $columnName = $columns[$columnIndex]['data'];
            $direction = $order[0]['dir'];
            if (!empty($columnName)) {
                $query->orderBy($columnName, $direction);
            }
        } else {
            $query->orderBy('id', 'desc');
        }

        $filteredRecords = $query->count();
        $campaigns = $query->offset($start)->limit($length)->get();

        $totalRecords = DB::table(DB::raw("(
        SELECT id FROM amazon_sb_campaign_reports
        UNION ALL
        SELECT id FROM amazon_sd_campaign_reports
        UNION ALL
        SELECT id FROM amazon_sp_campaign_reports
    ) as total"))->count();

        $queries = \DB::getQueryLog();
        if (!empty($queries)) {
            $lastQuery = end($queries);
            \Log::info('Executed SQL Query: ', ['query' => $lastQuery['query'], 'bindings' => $lastQuery['bindings'], 'time' => $lastQuery['time']]);
        }

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $campaigns
        ]);
    }

    private function cleanNumber($value)
    {
        if ($value === null) return null;
        // Remove $ and commas, then convert to float if possible
        $clean = str_replace(['$', ','], '', trim($value));
        return is_numeric($clean) ? $clean : null;
    }

    public function upload(Request $request)
    {
        Log::info('Campaign CSV upload started.');

        if (!$request->hasFile('csv_file')) {
            Log::error('No file uploaded.');
            return back()->with('error', 'No file uploaded.');
        }

        $file = $request->file('csv_file');
        Log::info('File received for import.', ['filename' => $file->getClientOriginalName()]);

        $rowCount = 0;
        $errorCount = 0;

        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $header = fgetcsv($handle);

            // Normalize header to snake_case for DB columns
            $normalizedHeader = array_map(function ($h) {
                $h = preg_replace('/[^A-Za-z0-9 ]/', '', $h); // Remove special chars
                $h = strtolower(str_replace(' ', '_', $h));
                return $h;
            }, $header);

            Log::info('CSV header read.', ['header' => $normalizedHeader]);

            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rowCount++;
                if (count($data) != count($normalizedHeader)) {
                    $errorCount++;
                    Log::error('Row import failed: column count mismatch', [
                        'row' => $rowCount,
                        'data' => $data
                    ]);
                    continue;
                }
                try {
                    $rowData = array_combine($normalizedHeader, $data);

                    // Parse dates robustly
                    $startDate = $this->parseDate($rowData['start_date'] ?? null);
                    $endDate = $this->parseDate($rowData['end_date'] ?? null);

                    DB::table('campaigns')->insert([
                        'state' => $rowData['state'] ?? null,
                        'campaigns' => $rowData['campaigns'] ?? null,
                        'country' => $rowData['country'] ?? null,
                        'status' => $rowData['status'] ?? null,
                        'type' => $rowData['type'] ?? null,
                        'targeting' => $rowData['targeting'] ?? null,
                        'retailer' => $rowData['retailer'] ?? null,
                        'portfolio' => $rowData['portfolio'] ?? null,
                        'campaign_bidding_strategy' => $rowData['campaign_bidding_strategy'] ?? null,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'budget_converted' => $this->cleanNumber($rowData['budget_converted'] ?? null),
                        'budget' => $this->cleanNumber($rowData['budget'] ?? null),
                        'cost_type' => $rowData['cost_type'] ?? null,
                        'impressions' => $this->cleanNumber($rowData['impressions'] ?? null),
                        'top_of_search_impression_share' => $rowData['topofsearch_impression_share'] ?? null,
                        'top_of_search_bid_adjustment' => $this->cleanNumber($rowData['topofsearch_bid_adjustment'] ?? null),
                        'clicks' => $this->cleanNumber($rowData['clicks'] ?? null),
                        'ctr' => $this->cleanNumber($rowData['ctr'] ?? null),
                        'spend_converted' => $this->cleanNumber($rowData['spend_converted'] ?? null),
                        'spend' => $this->cleanNumber($rowData['spend'] ?? null),
                        'cpc_converted' => $this->cleanNumber($rowData['cpc_converted'] ?? null),
                        'cpc' => $this->cleanNumber($rowData['cpc'] ?? null),
                        'detail_page_views' => $this->cleanNumber($rowData['detail_page_views'] ?? null),
                        'orders' => $this->cleanNumber($rowData['orders'] ?? null),
                        'sales_converted' => $this->cleanNumber($rowData['sales_converted'] ?? null),
                        'sales' => $this->cleanNumber($rowData['sales'] ?? null),
                        'acos' => $this->cleanNumber($rowData['acos'] ?? null),
                        'roas' => $this->cleanNumber($rowData['roas'] ?? null),
                        'ntb_orders' => $this->cleanNumber($rowData['ntb_orders'] ?? null),
                        'percent_orders_ntb' => $this->cleanNumber($rowData['of_orders_ntb'] ?? null),
                        'ntb_sales_converted' => $this->cleanNumber($rowData['ntb_sales_converted'] ?? null),
                        'ntb_sales' => $this->cleanNumber($rowData['ntb_sales'] ?? null),
                        'percent_sales_ntb' => $this->cleanNumber($rowData['of_sales_ntb'] ?? null),
                        'long_term_sales_converted' => $this->cleanNumber($rowData['longterm_sales_converted'] ?? null),
                        'long_term_sales' => $this->cleanNumber($rowData['longterm_sales'] ?? null),
                        'long_term_roas' => $this->cleanNumber($rowData['longterm_roas'] ?? null),
                        'cumulative_reach' => $this->cleanNumber($rowData['cumulative_reach'] ?? null),
                        'household_reach' => $this->cleanNumber($rowData['household_reach'] ?? null),
                        'viewable_impressions' => $this->cleanNumber($rowData['viewable_impressions'] ?? null),
                        'cpm_converted' => $this->cleanNumber($rowData['cpm_converted'] ?? null),
                        'cpm' => $this->cleanNumber($rowData['cpm'] ?? null),
                        'vcpm_converted' => $this->cleanNumber($rowData['vcpm_converted'] ?? null),
                        'vcpm' => $this->cleanNumber($rowData['vcpm'] ?? null),
                        'video_first_quartile' => $this->cleanNumber($rowData['video_first_quartile'] ?? null),
                        'video_midpoint' => $this->cleanNumber($rowData['video_midpoint'] ?? null),
                        'video_third_quartile' => $this->cleanNumber($rowData['video_third_quartile'] ?? null),
                        'video_complete' => $this->cleanNumber($rowData['video_complete'] ?? null),
                        'video_unmute' => $this->cleanNumber($rowData['video_unmute'] ?? null),
                        'vtr' => $this->cleanNumber($rowData['vtr'] ?? null),
                        'vctr' => $this->cleanNumber($rowData['vctr'] ?? null),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    Log::info('Row imported', ['row' => $rowCount]);
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Row import failed', [
                        'row' => $rowCount,
                        'error' => $e->getMessage(),
                        'data' => $data
                    ]);
                }
            }
            fclose($handle);
        }

        Log::info('Campaign CSV upload finished.', [
            'total_rows' => $rowCount,
            'errors' => $errorCount
        ]);

        return back()->with('success', 'CSV imported successfully!');
    }

    private function parseDate($date)
    {
        if (!$date) return null;
        $date = trim($date);
        foreach (['m/d/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            $parsed = DateTime::createFromFormat($format, $date);
            if ($parsed) return $parsed->format('Y-m-d');
        }
        return null;
    }

    function getCampaignsData()
    {
        $subBase = DB::table('amazon_sb_campaign_reports')
            ->selectRaw("
                id, campaign_id, note, sbid, yes_sbid, profile_id, ad_type,
                report_date_range, campaignName, clicks, cost, impressions,
                startDate, endDate, sales, purchases, unitsSold,
                campaignBudgetAmount, campaignBudgetCurrencyCode, 'SB' AS source, campaignStatus
            ")
            ->unionAll(
                DB::table('amazon_sd_campaign_reports')
                    ->selectRaw("
                        id, campaign_id, note, sbid, yes_sbid, profile_id, ad_type,
                        report_date_range, campaignName, clicks, cost, impressions,
                        startDate, endDate, sales, purchases, unitsSold,
                        NULL AS campaignBudgetAmount, campaignBudgetCurrencyCode, 'SD' AS source, campaignStatus
                    ")
            )
            ->unionAll(
                DB::table('amazon_sp_campaign_reports')
                    ->selectRaw("
                        id, campaign_id, note, sbid, yes_sbid, profile_id, ad_type,
                        report_date_range, campaignName, clicks, cost, impressions,
                        startDate, endDate, sales30d AS sales, purchases30d AS purchases,
                        unitsSoldClicks30d AS unitsSold, campaignBudgetAmount,
                        campaignBudgetCurrencyCode, 'SP' AS source, campaignStatus
                    ")
            );

        $base = DB::table(DB::raw("({$subBase->toSql()}) as base"))
            ->mergeBindings($subBase)
            ->leftJoin('campaign_entries as ce', DB::raw('TRIM(base.campaignName)'), '=', DB::raw('TRIM(ce.campaign_name)'));

        // --- Clicks by range ---
        $clicksByRange = DB::table(DB::raw("
            (SELECT campaign_id, profile_id, report_date_range, clicks, 'SB' AS source FROM amazon_sb_campaign_reports
            UNION ALL
            SELECT campaign_id, profile_id, report_date_range, clicks, 'SD' AS source FROM amazon_sd_campaign_reports
            UNION ALL
            SELECT campaign_id, profile_id, report_date_range, clicks, 'SP' AS source FROM amazon_sp_campaign_reports) AS reports
        "))
            ->selectRaw("
            campaign_id, profile_id, source,
            SUM(CASE WHEN report_date_range = 'L7' THEN clicks ELSE 0 END) AS l7_clicks,
            SUM(CASE WHEN report_date_range = 'L15' THEN clicks ELSE 0 END) AS l15_clicks,
            SUM(CASE WHEN report_date_range = 'L30' THEN clicks ELSE 0 END) AS l30_clicks,
            SUM(CASE WHEN report_date_range = 'L60' THEN clicks ELSE 0 END) AS l60_clicks
        ")
            ->groupBy('campaign_id', 'profile_id', 'source');

        $base = $base->leftJoinSub($clicksByRange, 'clicks_by_range', function ($join) {
            $join->on('base.campaign_id', '=', 'clicks_by_range.campaign_id')
                ->on('base.profile_id', '=', 'clicks_by_range.profile_id')
                ->on('base.source', '=', 'clicks_by_range.source');
        });

        // --- Spend by range ---
        $spendByRange = DB::table(DB::raw("
            (SELECT campaign_id, profile_id, report_date_range, cost, 'SB' AS source FROM amazon_sb_campaign_reports
            UNION ALL
            SELECT campaign_id, profile_id, report_date_range, cost, 'SD' AS source FROM amazon_sd_campaign_reports
            UNION ALL
            SELECT campaign_id, profile_id, report_date_range, cost, 'SP' AS source FROM amazon_sp_campaign_reports) AS spend_reports
        "))
            ->selectRaw("
            campaign_id, profile_id, source,
            SUM(CASE WHEN report_date_range = 'L1' THEN cost ELSE 0 END) AS l1_spend,
            SUM(CASE WHEN report_date_range = 'L7' THEN cost ELSE 0 END) AS l7_spend,
            SUM(CASE WHEN report_date_range = 'L15' THEN cost ELSE 0 END) AS l15_spend,
            SUM(CASE WHEN report_date_range = 'L30' THEN cost ELSE 0 END) AS l30_spend,
            SUM(CASE WHEN report_date_range = 'L60' THEN cost ELSE 0 END) AS l60_spend
        ")
            ->groupBy('campaign_id', 'profile_id', 'source');

        $base = $base->leftJoinSub($spendByRange, 'spend_by_range', function ($join) {
            $join->on('base.campaign_id', '=', 'spend_by_range.campaign_id')
                ->on('base.profile_id', '=', 'spend_by_range.profile_id')
                ->on('base.source', '=', 'spend_by_range.source');
        });

        // --- Sales by range ---
        $salesByRange = DB::table(DB::raw("
            (SELECT campaign_id, profile_id, report_date_range, sales, 'SB' AS source FROM amazon_sb_campaign_reports
            UNION ALL
            SELECT campaign_id, profile_id, report_date_range, sales, 'SD' AS source FROM amazon_sd_campaign_reports
            UNION ALL
            SELECT campaign_id, profile_id, report_date_range, sales30d AS sales, 'SP' AS source FROM amazon_sp_campaign_reports) AS sales_reports
        "))
            ->selectRaw("
            campaign_id, profile_id, source,
            SUM(CASE WHEN report_date_range = 'L7' THEN sales ELSE 0 END) AS l7_sales,
            SUM(CASE WHEN report_date_range = 'L15' THEN sales ELSE 0 END) AS l15_sales,
            SUM(CASE WHEN report_date_range = 'L30' THEN sales ELSE 0 END) AS l30_sales,
            SUM(CASE WHEN report_date_range = 'L60' THEN sales ELSE 0 END) AS l60_sales
        ")
            ->groupBy('campaign_id', 'profile_id', 'source');

        $base = $base->leftJoinSub($salesByRange, 'sales_by_range', function ($join) {
            $join->on('base.campaign_id', '=', 'sales_by_range.campaign_id')
                ->on('base.profile_id', '=', 'sales_by_range.profile_id')
                ->on('base.source', '=', 'sales_by_range.source');
        });

        // --- CPC by range ---
        $cpcByRange = DB::table(DB::raw("
            (SELECT campaign_id, profile_id, report_date_range, clicks, cost, 'SB' AS source FROM amazon_sb_campaign_reports
            UNION ALL
            SELECT campaign_id, profile_id, report_date_range, clicks, cost, 'SD' AS source FROM amazon_sd_campaign_reports
            UNION ALL
            SELECT campaign_id, profile_id, report_date_range, clicks, cost, 'SP' AS source FROM amazon_sp_campaign_reports) AS cpc_reports
        "))
            ->selectRaw("
            campaign_id, profile_id, source,
            ROUND(SUM(CASE WHEN report_date_range = 'L7' THEN cost ELSE 0 END) / NULLIF(SUM(CASE WHEN report_date_range = 'L7' THEN clicks ELSE 0 END),0),2) AS l7_cpc,
            ROUND(SUM(CASE WHEN report_date_range = 'L15' THEN cost ELSE 0 END) / NULLIF(SUM(CASE WHEN report_date_range = 'L15' THEN clicks ELSE 0 END),0),2) AS l15_cpc,
            ROUND(SUM(CASE WHEN report_date_range = 'L30' THEN cost ELSE 0 END) / NULLIF(SUM(CASE WHEN report_date_range = 'L30' THEN clicks ELSE 0 END),0),2) AS l30_cpc,
            ROUND(SUM(CASE WHEN report_date_range = 'L60' THEN cost ELSE 0 END) / NULLIF(SUM(CASE WHEN report_date_range = 'L60' THEN clicks ELSE 0 END),0),2) AS l60_cpc
        ")
            ->groupBy('campaign_id', 'profile_id', 'source');

        $base = $base->leftJoinSub($cpcByRange, 'cpc_by_range', function ($join) {
            $join->on('base.campaign_id', '=', 'cpc_by_range.campaign_id')
                ->on('base.profile_id', '=', 'cpc_by_range.profile_id')
                ->on('base.source', '=', 'cpc_by_range.source');
        });

        // --- Orders by range ---
        $ordersByRange = DB::table(DB::raw("
            (SELECT campaign_id, profile_id, report_date_range, purchases, 'SB' AS source FROM amazon_sb_campaign_reports
            UNION ALL
            SELECT campaign_id, profile_id, report_date_range, purchases, 'SD' AS source FROM amazon_sd_campaign_reports
            UNION ALL
            SELECT campaign_id, profile_id, report_date_range, purchases30d AS purchases, 'SP' AS source FROM amazon_sp_campaign_reports) AS order_reports
        "))
            ->selectRaw("
            campaign_id, profile_id, source,
            SUM(CASE WHEN report_date_range = 'L7' THEN purchases ELSE 0 END) AS l7_orders,
            SUM(CASE WHEN report_date_range = 'L15' THEN purchases ELSE 0 END) AS l15_orders,
            SUM(CASE WHEN report_date_range = 'L30' THEN purchases ELSE 0 END) AS l30_orders,
            SUM(CASE WHEN report_date_range = 'L60' THEN purchases ELSE 0 END) AS l60_orders
        ")
            ->groupBy('campaign_id', 'profile_id', 'source');

        $base = $base->leftJoinSub($ordersByRange, 'order_by_range', function ($join) {
            $join->on('base.campaign_id', '=', 'order_by_range.campaign_id')
                ->on('base.profile_id', '=', 'order_by_range.profile_id')
                ->on('base.source', '=', 'order_by_range.source');
        });

        // --- Final select including ACOS ---
        $results = $base->selectRaw("
            base.*, ce.parent AS parent,
            COALESCE(clicks_by_range.l7_clicks,0) as l7_clicks,
            COALESCE(clicks_by_range.l15_clicks,0) as l15_clicks,
            COALESCE(clicks_by_range.l30_clicks,0) as l30_clicks,
            COALESCE(clicks_by_range.l60_clicks,0) as l60_clicks,
            COALESCE(spend_by_range.l1_spend,0) as l1_spend,
            COALESCE(spend_by_range.l7_spend,0) as l7_spend,
            COALESCE(spend_by_range.l15_spend,0) as l15_spend,
            COALESCE(spend_by_range.l30_spend,0) as l30_spend,
            COALESCE(spend_by_range.l60_spend,0) as l60_spend,
            COALESCE(sales_by_range.l7_sales,0) as l7_sales,
            COALESCE(sales_by_range.l15_sales,0) as l15_sales,
            COALESCE(sales_by_range.l30_sales,0) as l30_sales,
            COALESCE(sales_by_range.l60_sales,0) as l60_sales,
            COALESCE(cpc_by_range.l7_cpc,0) as l7_cpc,
            COALESCE(cpc_by_range.l15_cpc,0) as l15_cpc,
            COALESCE(cpc_by_range.l30_cpc,0) as l30_cpc,
            COALESCE(cpc_by_range.l60_cpc,0) as l60_cpc,
            COALESCE(order_by_range.l7_orders,0) as l7_orders,
            COALESCE(order_by_range.l15_orders,0) as l15_orders,
            COALESCE(order_by_range.l30_orders,0) as l30_orders,
            COALESCE(order_by_range.l60_orders,0) as l60_orders,
            ROUND(COALESCE(spend_by_range.l7_spend,0)/NULLIF(COALESCE(sales_by_range.l7_sales,0),0)*100,2) AS l7_acos,
            ROUND(COALESCE(spend_by_range.l15_spend,0)/NULLIF(COALESCE(sales_by_range.l15_sales,0),0)*100,2) AS l15_acos,
            ROUND(COALESCE(spend_by_range.l30_spend,0)/NULLIF(COALESCE(sales_by_range.l30_sales,0),0)*100,2) AS l30_acos,
            ROUND(COALESCE(spend_by_range.l60_spend,0)/NULLIF(COALESCE(sales_by_range.l60_sales,0),0)*100,2) AS l60_acos
        ")->get();

        return response()->json([
            'data' => $results,
        ]);
    }

    public function budgetUnderUtilised()
    {
        return view('campaign.under_utilised');
    }
    public function budgetOverUtilised()
    {
        return view('campaign.over_utilised');
    }

}
