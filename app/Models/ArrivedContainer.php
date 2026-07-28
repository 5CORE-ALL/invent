<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArrivedContainer extends Model
{
    use HasFactory;

    protected $table = 'arrived_containers';

    protected $fillable = [
        'transit_container_id',
        'tab_name',
        'supplier_name',
        'company_name',
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
        'po_number',
        'inv_verify_cartons',
        'inv_verify_discrepancy',
        'cp_approved',
        'cp_approved_reason',
        'cp_approved_auto',
        'image_src',
        'photos',
        'specification',
        'created_by'
    ];

    protected $casts = [
        'cp_approved_auto' => 'boolean',
        'inv_verify_cartons' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
