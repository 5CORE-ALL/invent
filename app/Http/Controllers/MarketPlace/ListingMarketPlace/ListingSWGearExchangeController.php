<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;



use App\Http\Controllers\Controller;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\SWGearExchangeListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ListingSWGearExchangeController extends Controller
{
    use HandlesListingPublishActions;

    public function listingSWGearExchange(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('swgearexchange_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingSWGearExchange', [
            'swGearExchangePercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingSWGearExchangeData(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('swgearexchange'),
        ]);
    }

    public function saveStatus(Request $request)
    {
        if ($response = $this->listingPublishResponse($request)) {
            return $response;
        }

        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|url',
            'seller_link' => 'nullable|url',
        ]);

        $sku = $validated['sku'];
        $status = SWGearExchangeListingStatus::where('sku', $sku)->first();

        $existing = $status ? $status->value : [];

        // Only update the fields that are present in the request
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        SWGearExchangeListingStatus::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('swgearexchange');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file));
        // $header = array_map('trim', $rows[0]); // first row = header
        $header = array_map(function ($h) {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)); // remove BOM if present
        }, $rows[0]);

        unset($rows[0]);

        $allowedHeaders = ['sku','listed', 'buyer_link', 'seller_link'];
        foreach ($header as $h) {
            if (!in_array($h, $allowedHeaders)) {
                return response()->json([
                    'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders)
                ], 422);
            }
        }

        foreach ($rows as $row) {
            if (count($row) < 1) {
                continue; // skip empty
            }

            $rowData = array_combine($header, $row);
            $sku = trim($rowData['sku'] ?? '');

            if (!$sku) {
                continue;
            }

            // Only import SKUs that exist in product_masters
            if (!ProductMaster::where('sku', $sku)->exists()) {
                continue;
            }

            $status = SWGearExchangeListingStatus::where('sku', $sku)->first();
            $existing = $status ? $status->value : [];

            $fields = ['listed', 'buyer_link', 'seller_link'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                    $existing[$field] = $rowData[$field];
                }
            }

            SWGearExchangeListingStatus::updateOrCreate(
                ['sku' => $sku],
                ['value' => $existing]
            );
        }

        return response()->json(['success' => 'CSV imported successfully']);
    }


    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_status.csv"',
        ];

        $columns = ['sku', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all SKUs from product master
            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $status = SWGearExchangeListingStatus::where('sku', $sku)->first();

                $row = [
                    'sku'         => $sku,
                    'listed'      => $status->value['listed'] ?? '',
                    'buyer_link'  => $status->value['buyer_link'] ?? '',
                    'seller_link' => $status->value['seller_link'] ?? '',
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}