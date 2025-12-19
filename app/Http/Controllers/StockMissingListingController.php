<?php

namespace App\Http\Controllers;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\WalmartListingStatus;
use App\Models\AliexpressListingStatus;
use App\Models\ShopifyB2CListingStatus;
use App\Models\ReverbListingStatus;
use App\Models\MacysListingStatus;
use App\Models\WayfairListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockMissingListingController extends Controller
{
    /**
     * Display stock missing listing page.
     */
    public function index()
    {
        return view('stock-missing-listing.index');
    }

    /**
     * Compute listing status based on rl_nrl, listed, and live_inactive fields
     * Priority: NRL > Listed > Not Listed (for Pending) > Live > Inactive
     * Note: "Pending" on listing page = "Not Listed" on stock missing listing page
     */
    private function computeListingStatus($value) {
        if (!is_array($value)) {
            return null;
        }

        $rlNrl = $value['rl_nrl'] ?? null;
        // Support backward compatibility with nr_req
        if (!$rlNrl && isset($value['nr_req'])) {
            $rlNrl = ($value['nr_req'] === 'REQ') ? 'RL' : (($value['nr_req'] === 'NR') ? 'NRL' : null);
        }

        $listed = $value['listed'] ?? null;
        $liveInactive = $value['live_inactive'] ?? null;

        // Priority order: NRL has highest priority, then Listed, then Not Listed (for Pending), then Live/Inactive
        if ($rlNrl === 'NRL') {
            return 'NRL';
        } else if ($listed === 'Listed') {
            return 'Listed';
        } else if ($listed === 'Pending') {
            return 'Not Listed'; // "Pending" on listing page = "Not Listed" on stock missing listing page
        } else if ($liveInactive === 'Live') {
            return 'Live';
        } else if ($liveInactive === 'Inactive') {
            return 'Inactive';
        }

        return null;
    }

    /**
     * Get stock missing listing data
     * Returns Parent, SKU, INV from product_master and listing status from marketplace listing tables
     */
    public function getStockMissingListingData(Request $request) {
        try {
            // Get all products from product_master (exclude deleted)
            $productMasters = ProductMaster::whereNull('deleted_at')
                ->orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            // Normalize SKUs for lookup
            $masterSKUs = $productMasters
                ->pluck('sku')
                ->filter()
                ->unique()
                ->map(function($s) {
                    return strtolower(str_replace("\u{00a0}", ' ', $s));
                })
                ->values()
                ->toArray();

            // Get Shopify inventory (INV) - normalize SKU for lookup
            $shopifySkus = ShopifySku::all()->keyBy(function($item) {
                return strtolower(str_replace("\u{00a0}", ' ', $item->sku));
            });

            // Define marketplaces with their listing status models
            $marketplaces = [
                'walmart' => [
                    'listing'   => [WalmartListingStatus::class],
                    'use_computed_status' => true,
                ],
                'aliexpress' => [
                    'listing'   => [AliexpressListingStatus::class],
                    'use_computed_status' => true,
                ],
                'shopifyb2c' => [
                    'listing'   => [ShopifyB2CListingStatus::class],
                    'use_computed_status' => true,
                    'require_datasheet' => false,
                ],
                'reverb' => [
                    'listing'   => [ReverbListingStatus::class],
                    'use_computed_status' => true,
                ],
                'macy' => [
                    'listing'   => [MacysListingStatus::class],
                    'use_computed_status' => true,
                ],
                'wayfair' => [
                    'listing'   => [WayfairListingStatus::class],
                    'use_computed_status' => true,
                ],
            ];

            // Pre-fetch all marketplace listing data
            $marketplaceData = [];
            foreach ($marketplaces as $marketplaceName => $config) {
                $listingModels = $config['listing'];
                
                // Fetch all listings for these SKUs (get the most recent non-empty record for each SKU)
                $marketplaceData[$marketplaceName]['listings'] = collect();
                foreach ($listingModels as $modelClass) {
                    // Get all listings and normalize SKUs for matching
                    $allListings = $modelClass::orderBy('updated_at', 'desc')->get();
                    
                    $listings = $allListings
                        ->filter(function ($record) use ($masterSKUs) {
                            // Normalize SKU for comparison
                            $normalizedSku = strtolower(str_replace("\u{00a0}", ' ', $record->sku));
                            // Check if this SKU is in our master list
                            return in_array($normalizedSku, $masterSKUs);
                        })
                        ->filter(function ($record) {
                            // Filter out records with empty or null values
                            $value = is_array($record->value) ? $record->value : (json_decode($record->value, true) ?? []);
                            return !empty($value);
                        })
                        ->groupBy(function ($item) {
                            return strtolower(str_replace("\u{00a0}", ' ', $item->sku));
                        })
                        ->map(function ($group) {
                            // Take the first (most recent) record from each group
                            return $group->first();
                        });
                    
                    $marketplaceData[$marketplaceName]['listings'] = $marketplaceData[$marketplaceName]['listings']->merge($listings);
                }
            }

            // Build result array
            $result = [];
            foreach ($productMasters as $pm) {
                $sku = strtolower(str_replace("\u{00a0}", ' ', $pm->sku));
                
                $row = [];
                $row['parent'] = $pm->parent;
                $row['sku'] = $pm->sku;
                
                // Get INV from ShopifySku
                $shopifySku = $shopifySkus[$sku] ?? null;
                $row['inv'] = $shopifySku ? ($shopifySku->inv ?? 0) : 0;
                
                // Initialize listing status array
                $row['listing_status'] = [];

                // Get listing status for each marketplace
                foreach ($marketplaces as $marketplaceName => $config) {
                    $useComputedStatus = $config['use_computed_status'] ?? false;
                    $foundListing = $marketplaceData[$marketplaceName]['listings'][$sku] ?? null;

                    $status = "Not Listed";
                    if ($foundListing) {
                        $value = $foundListing->value;
                        if (is_string($value)) {
                            $value = json_decode($value, true);
                        }
                        $value = is_array($value) ? $value : [];

                        if ($useComputedStatus) {
                            // Use computed listing status
                            $computedStatus = $this->computeListingStatus($value);
                            if ($computedStatus) {
                                $status = $computedStatus;
                            } else {
                                // If computed status is null, check individual fields to determine status
                                // This handles cases where the listing exists but doesn't match the priority logic
                                $listed = $value['listed'] ?? null;
                                $rlNrl = $value['rl_nrl'] ?? null;
                                // Support backward compatibility with nr_req
                                if (!$rlNrl && isset($value['nr_req'])) {
                                    $rlNrl = ($value['nr_req'] === 'REQ') ? 'RL' : (($value['nr_req'] === 'NR') ? 'NRL' : null);
                                }
                                $liveInactive = $value['live_inactive'] ?? null;
                                
                                // Apply the same priority logic manually if computeListingStatus returned null
                                if ($rlNrl === 'NRL') {
                                    $status = 'NRL';
                                } else if ($listed === 'Listed') {
                                    $status = 'Listed';
                                } else if ($listed === 'Pending') {
                                    $status = 'Not Listed'; // "Pending" on listing page = "Not Listed" on stock missing listing page
                                } else if ($liveInactive === 'Live') {
                                    $status = 'Live';
                                } else if ($liveInactive === 'Inactive') {
                                    $status = 'Inactive';
                                } else {
                                    // If listing exists but no status fields match, default to Listed
                                    $status = "Listed";
                                }
                            }
                        } else {
                            // Use old logic for backward compatibility
                            $status = "Listed";
                            if (($value['nr_req'] ?? "") === "NR") {
                                $status = "NRL";
                            }
                        }
                    }

                    $row['listing_status'][$marketplaceName] = $status;
                }

                $result[] = (object) $row;
            }

            return response()->json([
                'message' => 'Data fetched successfully',
                'data'    => $result,
                'status'  => 200,
            ]);
        } catch (\Exception $e) {
            Log::error('Stock missing listing data error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error fetching data',
                'error' => $e->getMessage(),
                'data'    => [],
                'status'  => 500,
            ], 500);
        }
    }
}

