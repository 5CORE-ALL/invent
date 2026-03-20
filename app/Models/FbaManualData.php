<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class FbaManualData extends Model
{
    use HasFactory;

    protected $fillable = ['sku', 'data', 'pft_amt', 'sales_amt'];

    protected $casts = [
        'data' => 'array', // Cast data to array
    ];

    /**
     * Boot the model and add event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // ✅ Validate s_price before saving to database
        static::saving(function ($model) {
            if (isset($model->data['s_price'])) {
                $sPrice = floatval($model->data['s_price']);
                
                // If s_price is 0 or negative, remove it or set to null
                if ($sPrice <= 0) {
                    Log::warning("Invalid s_price blocked from saving for SKU: {$model->sku}", [
                        'invalid_s_price' => $model->data['s_price'],
                        'sku' => $model->sku
                    ]);
                    
                    // Remove the invalid s_price from data
                    $data = $model->data;
                    unset($data['s_price']);
                    $model->data = $data;
                }
            }
        });
    }
}
