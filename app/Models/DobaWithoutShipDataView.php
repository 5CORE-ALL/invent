<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-SKU SPRICE storage for the "Doba without ship" (pickup / prepaid label)
 * page. Kept in its own table so its SPRICE / SPFT / SROI / S_SELF_PICK /
 * PUSH_STATUS values do not collide with the regular Doba (with ship) page,
 * which uses {@see DobaDataView}.
 */
class DobaWithoutShipDataView extends Model
{
    protected $table = 'doba_withoutship_data_view';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}
