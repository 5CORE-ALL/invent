<?php

namespace App\Services;

use App\Models\AmazonSkuCompetitor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LmpaDataService
{
    /**
     * Amazon LMP for a SKU from amazon_sku_competitors (app LMP, not repricer).
     *
     * @return array{lowest_price: float|int, data: list<object>}
     */
    public function getLmpaData($sku)
    {
        if (! Schema::hasTable('amazon_sku_competitors')) {
            return [
                'lowest_price' => 0,
                'data' => [],
            ];
        }

        try {
            $rows = AmazonSkuCompetitor::getCompetitorsForSku((string) $sku)
                ->filter(fn ($row) => ! AmazonSkuCompetitor::isIgnored($row))
                ->values()
                ->take(50);

            $data = $rows->map(function ($row) {
                return (object) [
                    'price' => $row->price ?? 0,
                    'link' => $row->product_link ?? null,
                    'image' => $row->image ?? null,
                    'created_at' => null,
                ];
            })->all();

            $lowest = AmazonSkuCompetitor::lowestFromCollection($rows);

            return [
                'lowest_price' => $lowest ? (float) ($lowest->price ?? 0) : 0,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::warning('LmpaDataService: amazon_sku_competitors unavailable', [
                'sku' => $sku,
                'message' => $e->getMessage(),
            ]);

            return [
                'lowest_price' => 0,
                'data' => [],
            ];
        }
    }
}
