<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ResolvesBulletPointIdentifier
{
    /**
     * Find a metrics row by seller SKU (with case variants), then by alternate ID columns (e.g. item_id, goods_id, product_id).
     *
     * @param  list<string>  $alternateIdColumns
     */
    protected function findMetricRowBySkuOrAlternateIds(string $table, string $identifier, array $alternateIdColumns = []): ?object
    {
        $id = trim($identifier);
        if ($id === '' || ! Schema::hasTable($table)) {
            return null;
        }

        $row = DB::table($table)
            ->where(function ($q) use ($id) {
                $q->where('sku', $id)
                    ->orWhere('sku', strtoupper($id))
                    ->orWhere('sku', strtolower($id));
            })
            ->first();

        if ($row) {
            return $row;
        }

        foreach ($alternateIdColumns as $col) {
            if (! Schema::hasColumn($table, $col)) {
                continue;
            }
            $found = DB::table($table)->where($col, $id)->first();
            if ($found) {
                return $found;
            }
        }

        return null;
    }
}
