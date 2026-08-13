<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WayfairListingStatus extends Model
{
    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * wayfair_listing_statuses.id is not AUTO_INCREMENT in this database.
     */
    public static function upsertBySku(string $sku, array $value): self
    {
        $sku = trim($sku);
        $row = static::query()
            ->where('sku', $sku)
            ->orderByDesc('id')
            ->first();

        if (! $row) {
            $row = new static();
            $row->id = ((int) static::query()->max('id')) + 1;
            $row->sku = $sku;
        }

        $row->value = $value;
        $row->save();

        static::query()
            ->where('sku', $sku)
            ->where('id', '!=', $row->id)
            ->delete();

        return $row;
    }
}
