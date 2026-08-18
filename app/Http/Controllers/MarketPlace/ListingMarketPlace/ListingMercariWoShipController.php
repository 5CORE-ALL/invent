<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;



use App\Http\Controllers\Controller;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\MercariWoShipListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ListingMercariWoShipController extends Controller
{
    use HandlesListingPublishActions;

    public function listingMercariWoShip(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('mercariwoship_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingMercariWoShip', [
            'mercariWoShipPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingMercariWoShipData(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('mercariwoship'),
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
        $status = MercariWoShipListingStatus::where('sku', $sku)->first();

        $existing = $status ? $status->value : [];

        // Only update the fields that are present in the request
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        MercariWoShipListingStatus::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('mercariwoship');
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

        $allowedHeaders = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($header as $h) {
            if (!in_array($h, $allowedHeaders)) {
                return response()->json([
                    'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders)
                ], 422);
            }
        }

        $processedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($rows as $row) {
            if (count($row) < 1) {
                continue; // skip empty
            }

            // Pad row with empty strings if it has fewer columns than header
            $headerCount = count($header);
            $rowCount = count($row);
            if ($rowCount < $headerCount) {
                $row = array_pad($row, $headerCount, '');
            }

            // Trim row if it has more columns than header
            if ($rowCount > $headerCount) {
                $row = array_slice($row, 0, $headerCount);
            }

            try {
                $rowData = array_combine($header, $row);
                $sku = trim($rowData['sku'] ?? '');

                if (!$sku) {
                    $skippedCount++;
                    continue;
                }

                // Only import SKUs that exist in product_masters
                if (!ProductMaster::where('sku', $sku)->exists()) {
                    $skippedCount++;
                    continue;
                }

                $status = MercariWoShipListingStatus::where('sku', $sku)->first();
                $existing = $status ? $status->value : [];

                $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
                foreach ($fields as $field) {
                    if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                        $existing[$field] = $rowData[$field];
                    }
                }

                MercariWoShipListingStatus::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => $existing]
                );

                $processedCount++;
            } catch (\Exception $e) {
                $errorCount++;
                continue;
            }
        }

        return response()->json([
            'success' => 'CSV imported successfully',
            'processed' => $processedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount
        ]);
    }


    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_status.csv"',
        ];

        $columns = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all SKUs from product master
            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $status = MercariWoShipListingStatus::where('sku', $sku)->first();

                $row = [
                    'sku'         => $sku,
                    'nr_req'      => $status->value['nr_req'] ?? '',
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