<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WayfairPricingPrice extends Model
{
    use HasFactory;

    protected $table = 'wayfair_pricing_prices';

    protected $fillable = [
        'sku',
        'price',
        'wayfair_stock',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'wayfair_stock'  => 'integer',
    ];

    /**
     * wayfair_pricing_prices.id is not AUTO_INCREMENT in this database.
     */
    public static function upsertBySku(string $sku, array $attrs = []): self
    {
        $sku = trim($sku);
        $row = static::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first();

        if (! $row) {
            $row = new static();
            $row->id = ((int) static::query()->max('id')) + 1;
            $row->sku = $sku;
            $row->price = 0;
            $row->wayfair_stock = 0;
        }

        foreach ($attrs as $key => $value) {
            if ($key === 'id') {
                continue;
            }
            $row->{$key} = $value;
        }

        $row->save();

        return $row;
    }
}
