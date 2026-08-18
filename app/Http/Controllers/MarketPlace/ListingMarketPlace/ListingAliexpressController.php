<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AliexpressDataView;
use App\Models\AliexpressListingStatus;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\Marketplace\AliexpressListingCounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingAliexpressController extends Controller
{
    use HandlesListingPublishActions;

    public function listingAliexpress(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('aliexpress_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingAliexpress', [
            'aliexpressPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingAliexpressData(Request $request)
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->filter()->values()->all();

        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // Links only — NRL/REQ + Listed are automated (same pattern as /listing-ebaytwo)
        $statusData = AliexpressListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->mapWithKeys(function ($row) {
                return [strtolower(trim((string) $row->sku)) => $row];
            });

        // NRL column — same source as aliexpress-pricing (AliexpressDataView.value.NRL)
        $nrValues = AliexpressDataView::whereIn('sku', $skus)
            ->get(['sku', 'value'])
            ->mapWithKeys(function ($row) {
                return [strtoupper(trim((string) $row->sku)) => $row->value];
            });

        // Listed = real aliexpress_metric.product_id OR sku in aliexpress_pricing_prices
        $metricsByNorm = AliexpressListingCounts::metricsByNormalizedSku();
        $pricingByNorm = AliexpressListingCounts::pricingSkusByNormalizedSku();

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $statusData, $nrValues, $metricsByNorm, $pricingByNorm) {
            $childSku = (string) $item->sku;
            $skuLower = strtolower(trim($childSku));
            $skuUpper = strtoupper(trim($childSku));

            $item->INV = $shopifyData[$childSku]->inv ?? 0;
            $item->L30 = $shopifyData[$childSku]->quantity ?? 0;

            $item->buyer_link = null;
            $item->seller_link = null;
            if (isset($statusData[$skuLower])) {
                $statusValue = $statusData[$skuLower]->value;
                $status = is_array($statusValue)
                    ? $statusValue
                    : (json_decode($statusValue, true) ?? []);
                $item->buyer_link = $status['buyer_link'] ?? null;
                $item->seller_link = $status['seller_link'] ?? null;
            }

            $item->nr_req = AliexpressListingCounts::nrReqFromDataView(
                $nrValues->has($skuUpper) ? $nrValues->get($skuUpper) : null
            );

            $resolved = AliexpressListingCounts::resolveListed($childSku, $metricsByNorm, $pricingByNorm);
            $item->ae_product_id = $resolved['product_id'] !== '' ? $resolved['product_id'] : null;
            $item->listed = $resolved['listed'] ? 'Listed' : 'Pending';

            return $item;
        })->values();

        return response()->json([
            'status' => 200,
            'data' => $processedData,
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

        $sku = trim($validated['sku']);
        $status = AliexpressListingStatus::where('sku', $sku)
            ->orderBy('updated_at', 'desc')
            ->first();

        $existing = [];
        if ($status && $status->value) {
            $existing = is_array($status->value)
                ? $status->value
                : (json_decode($status->value, true) ?? []);
        }

        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        AliexpressListingStatus::where('sku', $sku)->delete();
        AliexpressListingStatus::create([
            'sku' => $sku,
            'value' => $existing,
        ]);

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        $counts = AliexpressListingCounts::counts();

        return [
            'REQ' => $counts['REQ'],
            'NRL' => $counts['NRL'],
            'Listed' => $counts['Listed'],
            'Pending' => $counts['MissingL'],
        ];
    }

    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => [
                    'required',
                    'file',
                    function ($attribute, $value, $fail) {
                        $extension = strtolower($value->getClientOriginalExtension());
                        if (! in_array($extension, ['csv', 'txt'], true)) {
                            $fail('The file must be a CSV or TXT file.');
                        }
                    },
                ],
            ]);

            $file = $request->file('file');
            $fileContent = file($file->getRealPath());
            $firstLine = $fileContent[0] ?? '';
            $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ',';

            $rows = array_map(function ($line) use ($delimiter) {
                return str_getcsv($line, $delimiter);
            }, $fileContent);

            $header = array_map(function ($h) {
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
            }, $rows[0] ?? []);
            unset($rows[0]);

            $allowedHeaders = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];
            foreach ($header as $h) {
                if (! in_array($h, $allowedHeaders, true)) {
                    return response()->json([
                        'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders),
                    ], 422);
                }
            }

            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($rows as $row) {
                if (count($row) < 1) {
                    $skippedCount++;
                    continue;
                }

                $headerCount = count($header);
                $rowCount = count($row);
                if ($rowCount < $headerCount) {
                    $row = array_pad($row, $headerCount, '');
                }
                if ($rowCount > $headerCount) {
                    $row = array_slice($row, 0, $headerCount);
                }

                $rowData = array_combine($header, $row);
                $sku = trim($rowData['sku'] ?? '');
                if ($sku === '') {
                    $skippedCount++;
                    continue;
                }

                try {
                    if (! ProductMaster::where('sku', $sku)->whereNull('deleted_at')->exists()) {
                        $skippedCount++;
                        continue;
                    }

                    $status = AliexpressListingStatus::where('sku', $sku)
                        ->orderBy('updated_at', 'desc')
                        ->first();
                    $existing = [];
                    if ($status && $status->value) {
                        $existing = is_array($status->value)
                            ? $status->value
                            : (json_decode($status->value, true) ?? []);
                    }

                    foreach (['nr_req', 'listed', 'buyer_link', 'seller_link'] as $field) {
                        if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                            $existing[$field] = trim($rowData[$field]);
                        }
                    }

                    AliexpressListingStatus::where('sku', $sku)->delete();
                    AliexpressListingStatus::create([
                        'sku' => $sku,
                        'value' => $existing,
                    ]);
                    $processedCount++;
                } catch (\Exception $rowError) {
                    $errorCount++;
                }
            }

            $message = 'CSV imported successfully';
            if ($errorCount > 0) {
                $message .= " (Processed: $processedCount, Skipped: $skippedCount, Errors: $errorCount)";
            }

            return response()->json([
                'success' => $message,
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_aliexpress_' . date('Y-m-d') . '.csv"',
        ];

        $columns = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link', 'ae_product_id'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $productMasters = ProductMaster::whereNull('deleted_at')->get(['sku']);
            $skus = $productMasters->pluck('sku')->unique()->filter()->values()->all();

            $statusData = AliexpressListingStatus::whereIn('sku', $skus)
                ->orderBy('updated_at', 'desc')
                ->get()
                ->mapWithKeys(fn ($row) => [strtolower(trim((string) $row->sku)) => $row]);

            $nrValues = AliexpressDataView::whereIn('sku', $skus)
                ->get(['sku', 'value'])
                ->mapWithKeys(fn ($row) => [strtoupper(trim((string) $row->sku)) => $row->value]);

            $metricsByNorm = AliexpressListingCounts::metricsByNormalizedSku();
            $pricingByNorm = AliexpressListingCounts::pricingSkusByNormalizedSku();

            foreach ($productMasters as $product) {
                $sku = (string) $product->sku;
                $skuLower = strtolower(trim($sku));
                $skuUpper = strtoupper(trim($sku));

                $status = $statusData->get($skuLower);
                $value = [];
                if ($status && $status->value) {
                    $value = is_array($status->value)
                        ? $status->value
                        : (json_decode($status->value, true) ?? []);
                }

                $nrReq = AliexpressListingCounts::nrReqFromDataView(
                    $nrValues->has($skuUpper) ? $nrValues->get($skuUpper) : null
                );
                $resolved = AliexpressListingCounts::resolveListed($sku, $metricsByNorm, $pricingByNorm);

                fputcsv($file, [
                    'sku' => $sku,
                    'nr_req' => $nrReq,
                    'listed' => $resolved['listed'] ? 'Listed' : 'Pending',
                    'buyer_link' => $value['buyer_link'] ?? '',
                    'seller_link' => $value['seller_link'] ?? '',
                    'ae_product_id' => $resolved['product_id'],
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    public function downloadSample()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="aliexpress_listing_import_sample.csv"',
        ];

        $columns = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, [
                'EXAMPLE-SKU-001',
                'REQ',
                'Listed',
                'https://www.aliexpress.com/item/1005001234567890.html',
                'https://gsp.aliexpress.com/m_apps/product-publish/publish?productId=1005001234567890',
            ]);
            fputcsv($file, [
                'EXAMPLE-SKU-002',
                'NR',
                'Pending',
                '',
                '',
            ]);
            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
