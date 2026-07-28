<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcContainer extends Model
{
    use HasFactory;

    protected $table = 'qc_containers';

    protected $fillable = [
        'transit_container_id',
        'arrived_container_id',
        'tab_name',
        'supplier_name',
        'company_name',
        'hsn_code',
        'our_sku',
        'parent',
        'no_of_units',
        'total_ctn',
        'rate',
        'unit',
        'status',
        'changes',
        'package_size',
        'product_size_link',
        'comparison_link',
        'order_link',
        'image_src',
        'photos',
        'specification',
        'created_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
