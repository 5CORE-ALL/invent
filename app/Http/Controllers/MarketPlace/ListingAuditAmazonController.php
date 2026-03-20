<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\AmazonDataView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ListingAuditAmazonController extends Controller
{
    public function listingAuditAmazon(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listingAuditAmazon', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage
        ]);
    }

    public function getViewListingAuditAmazonData(Request $request)
    {
        $productMasters = ProductMaster::all();
        $skus = $productMasters->pluck('sku')->unique()->toArray();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $amazonDataViewValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $amazonDataViewValues) {
            $childSku = strtoupper(trim($item->sku));
            $parent = $item->parent ?? '';
            $isParent = stripos($childSku, 'PARENT') !== false;

            $item->INV = $shopifyData[$childSku]->inv ?? 0;
            $item->L30 = $shopifyData[$childSku]->quantity ?? 0;
            $item->Parent = $parent;
            $item->is_parent = $isParent;

            // Fetch image from ShopifySku or ProductMaster->Values
            $values = is_array($item->Values) ? $item->Values : (is_string($item->Values) ? json_decode($item->Values, true) : []);
            $item->image_path = $shopifyData[$childSku]->image_src ?? ($values['image_path'] ?? null);

            // Attach AmazonDataView fields if available
            $item->Listed = false;
            $item->Live = false;
            $item->Category = false;
            $item->AttrFilled = false;
            $item->APlus = false;
            $item->Video = false;
            $item->Title = '';
            $item->Images = false;
            $item->Description = '';
            $item->BulletPoints = '';
            $item->InVariation = false;
            $item->NR = 'REQ';

            if ($childSku && isset($amazonDataViewValues[$childSku])) {
                $raw = $amazonDataViewValues[$childSku];
                $data = is_array($raw) ? $raw : (json_decode($raw, true) ?: []);
                $item->Listed = filter_var($data['Listed'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Live = filter_var($data['Live'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Category = filter_var($data['Category'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->AttrFilled = filter_var($data['AttrFilled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->APlus = filter_var($data['APlus'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Video = filter_var($data['Video'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Title = $data['Title'] ?? '';
                $item->Images = filter_var($data['Images'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Description = $data['Description'] ?? '';
                $item->BulletPoints = $data['BulletPoints'] ?? '';
                $item->NR = $data['NR'] ?? '';
                $item->InVariation = filter_var($data['InVariation'] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            return $item;
        })->values();

        return response()->json([
            'data' => $processedData,
            'status' => 200,
        ]);
    }

    public function saveAuditToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        if (!$sku || !$field) {
            return response()->json(['error' => 'SKU and field are required.'], 400);
        }

        $product = ProductMaster::where('sku', $sku)->first();
        if (!$product) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);
        $data = is_array($amazonDataView->value) ? $amazonDataView->value : (json_decode($amazonDataView->value, true) ?: []);
        $data[$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $amazonDataView->value = $data;
        $amazonDataView->save();

        return response()->json(['success' => true, 'data' => $amazonDataView]);
    }
}