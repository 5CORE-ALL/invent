<?php

namespace App\Http\Controllers;

use App\Models\EbayListingStatus;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;

class SkuMatchController extends Controller
{
    public function index()
    {
        $shopifySkus = EbayListingStatus::all();
        $productMasterSkus = ProductMaster::all();

        return view('sku_match', compact('shopifySkus', 'productMasterSkus'));
    }
    public function update(Request $request)
    {
        $request->validate([
            'ebay_listing_sku' => 'required|string',
            'product_master_sku' => 'required|string',
        ]);

        $ebayListing = EbayListingStatus::where('sku', $request->ebay_listing_sku)->first();
        if ($ebayListing) {
            $ebayListing->sku = $request->product_master_sku;
            $ebayListing->save();
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false]);
    }
}