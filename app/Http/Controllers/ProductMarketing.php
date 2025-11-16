<?php

namespace App\Http\Controllers;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;

class ProductMarketing extends Controller
{
    public function product_master()
    {
        return view('product_market.product_market');
    }

    public function product_market_details()
    {
        $productData = ProductMaster::whereNull('deleted_at')
            ->orderBy('id', 'asc')
            ->get();

        $skus = $productData
            ->pluck('sku')
            ->filter(fn($sku) => stripos($sku, 'PARENT') === false)
            ->unique()
            ->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)
            ->get()
            ->keyBy(fn($item) => trim(strtoupper($item->sku)));

        // Prepare the final flat array
        $data = [];

        foreach ($productData as $product) {
            $sku = strtoupper(trim($product->sku));
            $shopify = $shopifyData[$sku] ?? null;

            $inv = $shopify->inv ?? 0;
            $quantity = $shopify->quantity ?? 0;

            $data[] = [
                'Parent' => $product->parent ?? '-',
                'SKU' => $sku,
                'Shopify_INV' => $inv,
                'OVL3' => $shopify->quantity ?? 0,

                'Dil' => $inv > 0 ? round(($quantity / $inv) * 100) : 0,
            ];
        }


        return response()->json($data);
    }
}
