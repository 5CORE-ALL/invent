<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ChannelTabulatorColumnSetting extends Model
{
    protected $table = 'channel_tabulator_column_settings';

    protected $fillable = [
        'channel_name',
        'visibility',
        'column_order',
    ];

    protected $casts = [
        'visibility' => 'array',
        'column_order' => 'array',
    ];

    protected static function booted(): void
    {
        // Schema often has id WITHOUT AUTO_INCREMENT — assign next id on insert.
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
