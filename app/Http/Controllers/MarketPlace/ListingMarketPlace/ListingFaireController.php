<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\FaireDataView;
use App\Models\FaireListingStatus;
use App\Models\ProductMaster;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingFaireController extends Controller
{
    use HandlesListingPublishActions;

    protected function listingPublishChannel(): string
    {
        return 'faire';
    }

    public function listingFaire(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('faire_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingFaire', [
            'fairePercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingFaireData(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('faire'),
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
        $status = FaireListingStatus::where('sku', $sku)->first();
        $existing = $status ? ($status->value ?? []) : [];

        foreach (['nr_req', 'listed', 'buyer_link', 'seller_link'] as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        FaireListingStatus::updateOrCreate(
            ['sku' => $sku],
            ['value' => $existing]
        );

        if ($request->has('nr_req')) {
            $nrReq = strtoupper(trim((string) $validated['nr_req']));
            $isNrl = in_array($nrReq, ['NR', 'NRL'], true);
            $dataView = FaireDataView::firstOrNew(['sku' => $sku]);
            $value = is_array($dataView->value)
                ? $dataView->value
                : (json_decode((string) $dataView->value, true) ?: []);
            $value['NRL'] = $isNrl ? 'NRL' : 'REQ';
            $value['NR'] = $isNrl ? 'NR' : 'REQ';
            $dataView->value = $value;
            $dataView->save();
        }

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('faire');
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
            if (! in_array($h, $allowedHeaders, true)) {
                return response()->json([
                    'error' => "Invalid header '$h'. Allowed headers: ".implode(', ', $allowedHeaders),
                ], 422);
            }
        }

        foreach ($rows as $row) {
            if (count($row) < 1) {
                continue;
            }

            $rowData = array_combine($header, $row);
            $sku = trim($rowData['sku'] ?? '');
            if ($sku === '' || ! ProductMaster::where('sku', $sku)->whereNull('deleted_at')->exists()) {
                continue;
            }

            $status = FaireListingStatus::where('sku', $sku)->first();
            $existing = $status ? ($status->value ?? []) : [];
            foreach (['listed', 'buyer_link', 'seller_link'] as $field) {
                if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                    $existing[$field] = $rowData[$field];
                }
            }

            FaireListingStatus::updateOrCreate(
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
            'Content-Disposition' => 'attachment; filename="listing_faire_status.csv"',
        ];

        $columns = ['sku', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach (ProductMaster::query()->whereNull('deleted_at')->pluck('sku') as $sku) {
                $status = FaireListingStatus::where('sku', $sku)->first();
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
