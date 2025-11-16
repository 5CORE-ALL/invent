<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PricingAnalysisController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function pricingAnalysis(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('product-master.pricingAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewPricingAnalysisData(Request $request)
    {
        // Get request parameters
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 25);
        $dilFilter = $request->input('dil_filter', 'all');
        $dataType = $request->input('data_type', 'all');
        $searchTerm = $request->input('search', '');
        $parentFilter = $request->input('parent', '');
        $skuFilter = $request->input('sku', '');
        $distinctOnly = $request->input('distinct_only', false);

        // If "all" is selected, set perPage to a large number
        if ($perPage === 'all') {
            $perPage = 1000000;
        } else {
            $perPage = (int) $perPage;
        }

        // Cache key includes all filter parameters
        $cacheKey = 'pricing_analysis_data_' . md5(serialize([
            'dil_filter' => $dilFilter,
            'data_type' => $dataType,
            'search' => $searchTerm,
            'parent' => $parentFilter,
            'sku' => $skuFilter
        ]));

        // Get cached data or process it
        $processedData = Cache::remember($cacheKey, 3600, function () use ($searchTerm) {
            return $this->processPricingData($searchTerm);
        });

        // Apply filters
        $filteredData = $this->applyFilters($processedData, $dilFilter, $dataType, $parentFilter, $skuFilter);

        // For distinct values request
        if ($distinctOnly) {
            return response()->json([
                'distinct_values' => $this->getDistinctValues($processedData),
                'status' => 200
            ]);
        }

        // Paginate results
        $total = count($filteredData);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedData = array_slice($filteredData, $offset, $perPage);

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $paginatedData,
            'distinct_values' => $this->getDistinctValues($processedData),
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages
            ],
            'status' => 200
        ]);
    }

    protected function getDistinctValues($data)
    {
        $parents = [];
        $skus = [];

        foreach ($data as $item) {
            if (!empty($item->Parent)) {
                $parents[$item->Parent] = true;
            }
            if (!empty($item->SKU)) {
                $skus[$item->SKU] = true;
            }
        }

        return [
            'parents' => array_keys($parents),
            'skus' => array_keys($skus)
        ];
    }

    protected function processPricingData($searchTerm = '')
    {
        // Fetch all data sources
        $response = $this->apiController->fetchDataFromProductMasterGoogleSheet();
        $amz = $this->apiController->fetchDataFromAmazonGoogleSheet();
        $ebay = $this->apiController->fetchEbayListingData();
        $shopifyb2c = $this->apiController->fetchShopifyB2CListingData();
        $macy = $this->apiController->fetchMacyListingData();
        $neweegb2c = $this->apiController->fetchDataFromNeweggB2CMasterGoogleSheet();

        // Check if the main response is successful
        if ($response->getStatusCode() !== 200) {
            return [];
        }

        $mainData = $response->getData();

        // Configuration for different data sources
        $dataSources = [
            'amz' => [
                'source' => $amz->getData()->data ?? [],
                'match_field' => '(Child) sku',
                'price_field' => 'AMZ',
                'output_field' => 'amz_price',
                'additional_fields' => [
                    [
                        'source_field' => 'PFT %',
                        'output_field' => 'amz_pft'
                    ],
                    [
                        'source_field' => 'Roi',
                        'output_field' => 'amz_roi'
                    ],
                    [
                        'source_field' => 'AMZ LINK BL',
                        'output_field' => 'amz_buy_link'
                    ],
                    [
                        'source_field' => 'A L30',
                        'output_field' => 'amz_l30'
                    ],
                    [
                        'source_field' => 'A Dil%',
                        'output_field' => 'amz_dil'
                    ]
                ]
            ],
            'ebay' => [
                'source' => $ebay->getData()->data ?? [],
                'match_field' => '(Child) sku',
                'price_field' => 'eBay Price',
                'output_field' => 'ebay_price',
                'additional_fields' => [
                    [
                        'source_field' => 'PFT %',
                        'output_field' => 'ebay_pft'
                    ],
                    [
                        'source_field' => 'ROI%',
                        'output_field' => 'ebay_roi'
                    ],
                    [
                        'source_field' => 'B Link',
                        'output_field' => 'ebay_buy_link'
                    ],
                    [
                        'source_field' => 'E L30',
                        'output_field' => 'ebay_l30'
                    ],
                    [
                        'source_field' => 'E Dil%',
                        'output_field' => 'ebay_dil'
                    ]
                ]
            ],
            'shopifyb2c' => [
                'source' => $shopifyb2c->getData()->data ?? [],
                'match_field' => '(Child) sku',
                'price_field' => 'Price',
                'output_field' => 'shopifyb2c_price',
                'additional_fields' => [
                    [
                        'source_field' => 'PFT %',
                        'output_field' => 'shopifyb2c_pft'
                    ],
                    [
                        'source_field' => 'ROI%',
                        'output_field' => 'shopiyb2c_roi'
                    ],
                    [
                        'source_field' => 'SH L30',
                        'output_field' => 'shopiyb2c_l30'
                    ],
                    [
                        'source_field' => 'SH Dil%',
                        'output_field' => 'shopiyb2c_dil'
                    ],
                    [
                        'source_field' => 'B Link',
                        'output_field' => 'shopiyb2c_buy_link'
                    ]
                ]
            ],
            'macy' => [
                'source' => $macy->getData()->data ?? [],
                'match_field' => '(Child) sku',
                'price_field' => 'Price',
                'output_field' => 'macy_price',
                'additional_fields' => [
                    [
                        'source_field' => 'PFT%',
                        'output_field' => 'macy_pft'
                    ],
                    [
                        'source_field' => 'ROI%',
                        'output_field' => 'macy_roi'
                    ],
                    [
                        'source_field' => 'M L30',
                        'output_field' => 'macy_l30'
                    ],
                    [
                        'source_field' => 'M Dil%',
                        'output_field' => 'macy_dil'
                    ],
                    [
                        'source_field' => 'Blink',
                        'output_field' => 'macy_buy_link'
                    ]
                ]
            ],
            'neweegb2c' => [
                'source' => $neweegb2c->getData()->data ?? [],
                'match_field' => '(Child) sku',
                'price_field' => 'Newegg Price',
                'output_field' => 'neweegb2c_price',
                'additional_fields' => [
                    [
                        'source_field' => 'PFT%',
                        'output_field' => 'neweegb2c_pft'
                    ],
                    [
                        'source_field' => 'ROI',
                        'output_field' => 'neweegb2c_roi'
                    ],
                    [
                        'source_field' => 'N L30',
                        'output_field' => 'neweegb2c_l30'
                    ],
                    [
                        'source_field' => 'N Dil%',
                        'output_field' => 'neweegb2c_dil'
                    ],
                    [
                        'source_field' => 'Buyer Link',
                        'output_field' => 'neweegb2c_buy_link'
                    ]
                ]
            ]
        ];

        // Process data sources
        $sourceLookups = [];
        foreach ($dataSources as $sourceName => $sourceConfig) {
            $sourceLookups[$sourceName] = $this->createSkuLookup(
                $sourceConfig['source'],
                $sourceConfig['match_field'],
                $sourceConfig['price_field'],
                $sourceConfig['additional_fields'] ?? []
            );
        }

        // Get non-PARENT SKUs
        $skus = collect($mainData->data)
            ->filter(function ($item) {
                $childSku = $item->{'SKU'} ?? '';
                return !empty($childSku) && stripos($childSku, 'PARENT') === false;
            })
            ->pluck('SKU')
            ->unique()
            ->toArray();

        // Fetch Shopify inventory data
        $shopifyData = ShopifySku::whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        // Filter and process data
        $processedData = [];
        foreach ($mainData->data as $item) {
            $childSku = $item->{'SKU'} ?? '';
            if (empty(trim($childSku))) {
                continue;
            }

            // Apply search filter
            if (!empty($searchTerm)) {
                $matchFound = false;
                foreach ($item as $key => $value) {
                    if (is_scalar($value) && stripos($value, $searchTerm) !== false) {
                        $matchFound = true;
                        break;
                    }
                }
                if (!$matchFound) {
                    continue;
                }
            }

            // Add is_parent flag
            $isParent = !empty($childSku) && stripos($childSku, 'PARENT') !== false;

            // Update inventory data for non-PARENT SKUs
            if (!$isParent) {
                if ($shopifyData->has($childSku)) {
                    $item->INV = $shopifyData[$childSku]->inv;
                    $item->L30 = $shopifyData[$childSku]->quantity;
                } else {
                    $item->INV = 0;
                    $item->L30 = 0;
                }
            }

            // Calculate Dil%
            $inv = $item->INV ?? 0;
            $l30 = $item->L30 ?? 0;
            $item->{'Dil%'} = ($inv > 0) ? ($l30 / $inv) : 0;

            // Add prices and additional fields from sources
            foreach ($dataSources as $sourceName => $sourceConfig) {
                $sourceData = $this->findDataBySku(
                    $childSku,
                    $sourceLookups[$sourceName]
                );

                $item->{$sourceConfig['output_field']} = $sourceData['price'] ?? null;

                // Add additional fields if they exist
                if (isset($sourceConfig['additional_fields'])) {
                    foreach ($sourceConfig['additional_fields'] as $field) {
                        $item->{$field['output_field']} = $sourceData[$field['source_field']] ?? null;
                    }
                }
            }

            $item->is_parent = $isParent;
            $processedData[] = $item;
        }

        return $processedData;
    }

    protected function applyFilters($data, $dilFilter, $dataType, $parentFilter, $skuFilter)
    {
        return array_filter($data, function ($item) use ($dilFilter, $dataType, $parentFilter, $skuFilter) {
            // Apply Dil% filter
            if ($dilFilter !== 'all') {
                $dilPercent = ($item->{'Dil%'} ?? 0) * 100;
                switch ($dilFilter) {
                    case 'red':
                        if ($dilPercent >= 16.66)
                            return false;
                        break;
                    case 'yellow':
                        if ($dilPercent < 16.66 || $dilPercent >= 25)
                            return false;
                        break;
                    case 'green':
                        if ($dilPercent < 25 || $dilPercent >= 50)
                            return false;
                        break;
                    case 'pink':
                        if ($dilPercent < 50)
                            return false;
                        break;
                }
            }

            // Apply data type filter
            if ($dataType !== 'all') {
                $sku = $item->{'SKU'} ?? '';
                $isParentSku = stripos($sku, 'PARENT') !== false;

                if ($dataType === 'parent') {
                    if (!$isParentSku)
                        return false;
                } else if ($dataType === 'sku') {
                    if ($isParentSku)
                        return false;
                }
            }

            // Apply parent filter
            if ($parentFilter && $item->Parent !== $parentFilter) {
                return false;
            }

            // Apply SKU filter
            if ($skuFilter && $item->SKU !== $skuFilter) {
                return false;
            }

            return true;
        });
    }

    protected function createSkuLookup($sourceData, $matchField, $priceField, $additionalFields = [])
    {
        $lookup = [];

        foreach ($sourceData as $item) {
            $sku = $item->{$matchField} ?? '';

            if (!empty($sku)) {
                $entry = [
                    'price' => isset($item->{$priceField}) && is_numeric($item->{$priceField})
                        ? (float) $item->{$priceField}
                        : null
                ];

                // Add additional fields exactly as they are
                foreach ($additionalFields as $field) {
                    $sourceField = $field['source_field'];
                    $entry[$sourceField] = $item->{$sourceField} ?? null;
                }

                $lookup[$sku] = $entry;
            }
        }

        return $lookup;
    }


    protected function findDataBySku($sku, $sourceLookup)
    {
        // If SKU exists in lookup, return its data
        if (isset($sourceLookup[$sku])) {
            return $sourceLookup[$sku];
        }

        // Otherwise return a default structure with all possible fields set to null
        $defaultStructure = ['price' => null];

        if (!empty($sourceLookup)) {
            $firstItem = reset($sourceLookup);
            foreach ($firstItem as $key => $value) {
                if ($key !== 'price') {
                    $defaultStructure[$key] = null;
                }
            }
        }

        return $defaultStructure;
    }

}