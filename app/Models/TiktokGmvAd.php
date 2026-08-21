<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TiktokGmvAd extends Model
{
    use HasFactory;

    protected $table = 'tiktok_gmv_ads';

    protected $fillable = [
        'sku',
        'report_range',
        'product_id',
        'ad_sold',
        'ad_sales',
        'spend',
        'budget',
        'status',
        'approval',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if ($model->getAttribute($model->getKeyName()) !== null) {
                return;
            }
            try {
                $col = DB::selectOne("SHOW COLUMNS FROM `{$model->getTable()}` WHERE Field = 'id'");
                $extra = strtolower((string) ($col->Extra ?? ''));
                if (str_contains($extra, 'auto_increment')) {
                    return;
                }
            } catch (\Throwable) {
            }
            $model->id = ((int) (DB::table($model->getTable())->max('id') ?? 0)) + 1;
        });
    }
}
