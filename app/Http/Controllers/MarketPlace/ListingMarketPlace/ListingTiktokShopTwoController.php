<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\TiktokTwoShopListingStatus;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingTiktokShopTwoController extends Controller
{
    public function listingTiktokShopTwo(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('tiktokshop2_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingTiktokShopTwo', [
            'tiktokShopPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingTiktokShopTwoData(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('tiktokshop2'),
        ]);
    }

    public function saveStatus(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|string',
            'seller_link' => 'nullable|string',
        ]);

        $sku = trim($validated['sku']);

        $status = TiktokTwoShopListingStatus::where('sku', $sku)
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($status) {
            $existing = is_array($status->value) ? $status->value : (json_decode($status->value, true) ?? []);
            if (empty($existing)) {
                $existing = [];
            }
        } else {
            $existing = [];
        }

        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field) && $request->input($field) !== null && $request->input($field) !== '') {
                $existing[$field] = $validated[$field];
            }
        }

        TiktokTwoShopListingStatus::where('sku', $sku)->delete();

        TiktokTwoShopListingStatus::create([
            'sku' => $sku,
            'value' => $existing,
        ]);

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('tiktokshop2');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file));
        $header = array_map(function ($h) {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
        }, $rows[0]);

        unset($rows[0]);

        $allowedHeaders = ['sku', 'listed', 'buyer_link', 'seller_link'];
        foreach ($header as $h) {
            if (! in_array($h, $allowedHeaders)) {
                return response()->json([
                    'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders),
                ], 422);
            }
        }

        foreach ($rows as $row) {
            if (count($row) < 1) {
                continue;
            }

            $rowData = array_combine($header, $row);
            $sku = trim($rowData['sku'] ?? '');

            if (! $sku) {
                continue;
            }

            if (! ProductMaster::where('sku', $sku)->exists()) {
                continue;
            }

            $status = TiktokTwoShopListingStatus::where('sku', $sku)
                ->orderBy('updated_at', 'desc')
                ->first();

            if ($status) {
                $existing = is_array($status->value) ? $status->value : (json_decode($status->value, true) ?? []);
                if (empty($existing)) {
                    $existing = [];
                }
            } else {
                $existing = [];
            }

            $fields = ['listed', 'buyer_link', 'seller_link'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                    $existing[$field] = $rowData[$field];
                }
            }

            TiktokTwoShopListingStatus::where('sku', $sku)->delete();

            TiktokTwoShopListingStatus::create([
                'sku' => $sku,
                'value' => $existing,
            ]);
        }

        return response()->json(['success' => 'CSV imported successfully']);
    }

    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_tiktokshop2_status.csv"',
        ];

        $columns = ['sku', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $status = TiktokTwoShopListingStatus::where('sku', $sku)->first();

                fputcsv($file, [
                    'sku' => $sku,
                    'listed' => $status->value['listed'] ?? '',
                    'buyer_link' => $status->value['buyer_link'] ?? '',
                    'seller_link' => $status->value['seller_link'] ?? '',
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
