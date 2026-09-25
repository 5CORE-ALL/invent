<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\InstagramShopSoldRaw;

class InstagramShopSoldController extends Controller
{
    public function index()
    {
        return view('market-places.instagram_shop_sold');
    }

    public function data()
    {
        $rows = InstagramShopSoldRaw::query()
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->get([
                'id',
                'sale_date',
                'order_name',
                'sku',
                'url',
                'product_title',
                'quantity',
                'sold_price',
                'gross_sales',
                'net_sales',
                'discounts',
                'returns',
                'sales_channel',
            ]);

        return response()->json($rows);
    }
}
