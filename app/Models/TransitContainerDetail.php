<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ✅ Correct import

class TransitContainerDetail extends Model
{
    use HasFactory, SoftDeletes; // ✅ Add trait here

    protected $fillable = [
        'tab_name',
        'supplier_name',
        'company_name',
        'parent',
        'our_sku',
        'photos',
        'specification',
        'package_size',
        'product_size_link',
        'status',
        'changes',
        'rec_qty',
        'no_of_units',
        'total_ctn',
        'rate',
        'unit',
        'cbm',
        'order_link',
        'comparison_link',
        'auth_user',
    ];

    protected $dates = ['deleted_at']; 
}
