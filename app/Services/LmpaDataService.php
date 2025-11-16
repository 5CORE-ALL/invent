<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LmpaDataService
{
    /**
     * Get LMPA data for a given SKU
     *
     * @param string $sku
     * @return array
     */
    public function getLmpaData($sku)
    {
        $rows = DB::connection('repricer')
            ->table('lmpa_data')
            ->select('price', 'link', 'image', DB::raw('NULL as created_at'))
            ->where('sku', $sku)
            ->where('price', '>', 0)
            ->orderBy('price', 'asc')
            ->limit(50)
            ->get();

        $lowestPrice = $rows->isNotEmpty() ? $rows->first()->price : 0;

        return [
            'lowest_price' => $lowestPrice,
            'data' => $rows->toArray()
        ];
    }
}