<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcImprovementReqBeforeItemPkg extends Model
{
    protected $table = 'qc_improvement_req_before_item_pkg';

    protected $fillable = [
        'product_master_id',
        'qc_improvement_req',
    ];

    public function productMaster(): BelongsTo
    {
        return $this->belongsTo(ProductMaster::class, 'product_master_id');
    }
}
