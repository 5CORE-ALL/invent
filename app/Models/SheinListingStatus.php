<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SheinListingStatus extends Model
{
    protected $table = 'shein_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];

    /**
     * Seller parent SKUs marked Listed (includes Seller Hub Pending with `--` stock).
     *
     * @return list<string>
     */
    public static function listedSellerSkus(): array
    {
        if (! Schema::hasTable('shein_listing_statuses')) {
            return [];
        }

        $out = [];
        foreach (static::query()->get(['sku', 'value']) as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $value = is_array($row->value) ? $row->value : [];
            if (strtolower(trim((string) ($value['listed'] ?? ''))) !== 'listed') {
                continue;
            }
            $out[$sku] = true;
        }

        return array_keys($out);
    }
}
