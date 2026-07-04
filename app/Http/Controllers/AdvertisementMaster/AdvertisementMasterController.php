<?php

namespace App\Http\Controllers\AdvertisementMaster;

use App\Http\Controllers\AmazonAdsController;
use App\Http\Controllers\Campaigns\Ebay2CampaignAdsController;
use App\Http\Controllers\Campaigns\Ebay3CampaignAdsController;
use App\Http\Controllers\Campaigns\EbayCampaignAdsController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MarketPlace\ShopifyAdsMasterController;
use App\Http\Controllers\Sales\AmazonSalesController;
use App\Models\AmazonOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdvertisementMasterController extends Controller
{
    public function index(Request $request)
    {
        return view('advertisement-master.advertisement_master', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
        ]);
    }

    public function data(
        AmazonAdsController $amazonAds,
        EbayCampaignAdsController $ebayCampaignAds,
        Ebay2CampaignAdsController $ebay2CampaignAds,
        Ebay3CampaignAdsController $ebay3CampaignAds,
        ShopifyAdsMasterController $shopifyAdsMaster
    ) {
        $amazonNetSales = $this->amazonNetSales();
        $ebayNetSales = EbayCampaignAdsController::advertisementMasterNetSales();
        $ebay2NetSales = Ebay2CampaignAdsController::advertisementMasterNetSales();
        $ebay3NetSales = Ebay3CampaignAdsController::advertisementMasterNetSales();
        $shopifyNetSales = ShopifyAdsMasterController::advertisementMasterNetSales();

        $rows = array_merge(
            $amazonAds->getAdvertisementMasterChannelRows(),
            $ebayCampaignAds->getAdvertisementMasterChannelRows(),
            $ebay2CampaignAds->getAdvertisementMasterChannelRows(),
            $ebay3CampaignAds->getAdvertisementMasterChannelRows(),
            $shopifyAdsMaster->getAdvertisementMasterChannelRows()
        );

        $this->applyTcosToRows($rows, [
            'amazon' => $amazonNetSales,
            'ebay'   => $ebayNetSales,
            'ebay2'  => $ebay2NetSales,
            'ebay3'  => $ebay3NetSales,
            'shopify' => $shopifyNetSales,
        ]);

        $totalNetSales = round(
            $amazonNetSales + $ebayNetSales + $ebay2NetSales + $ebay3NetSales + $shopifyNetSales,
            2
        );

        return response()->json([
            'status' => 200,
            'message' => 'Advertisement Master data fetched successfully',
            'data' => $rows,
            'amazon_net_sales' => $amazonNetSales,
            'ebay_net_sales' => $ebayNetSales,
            'ebay2_net_sales' => $ebay2NetSales,
            'ebay3_net_sales' => $ebay3NetSales,
            'shopify_net_sales' => $shopifyNetSales,
            'total_net_sales' => $totalNetSales,
        ]);
    }

    /**
     * Amazon L30 store sales (Pacific rolling window) — same source as Channel Master
     * and the Amazon daily sales page. Used for the S Sales badge and TCOS.
     */
    private function amazonNetSales(): float
    {
        try {
            $yesterdayPacific = Carbon::yesterday('America/Los_Angeles');
            $endToday = $yesterdayPacific->copy()->endOfDay();
            $startAmazonWindow = $yesterdayPacific
                ->copy()
                ->subDays(AmazonSalesController::DAILY_SALES_WINDOW_DAYS - 1)
                ->startOfDay();

            return AmazonOrder::badgeTotalSalesByOrderDate($startAmazonWindow, $endToday);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master Amazon net sales lookup failed: ' . $e->getMessage());

            return 0.0;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, float>  $netSalesByMarketplace
     */
    private function applyTcosToRows(array &$rows, array $netSalesByMarketplace): void
    {
        foreach ($rows as &$row) {
            $marketplace = (string) ($row['marketplace'] ?? 'amazon');
            $netSales = (float) ($netSalesByMarketplace[$marketplace] ?? 0);
            $spend = (float) ($row['spend'] ?? 0);
            $row['tcos'] = $netSales > 0
                ? round(($spend / $netSales) * 100, 0)
                : ($spend > 0 ? 100 : 0);

            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->applyTcosToRows($row['_children'], $netSalesByMarketplace);
            }
        }
        unset($row);
    }
}
