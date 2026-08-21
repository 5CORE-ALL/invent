<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TiktokTwoShopDataView extends Model
{
    use HasFactory;

    protected $table = 'tiktok_two_shop_data_views';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    protected static function booted(): void
    {
        // Some environments created id without AUTO_INCREMENT.
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
                // fall through and assign manually
            }

            $model->id = ((int) (DB::table($model->getTable())->max('id') ?? 0)) + 1;
        });
    }
}
