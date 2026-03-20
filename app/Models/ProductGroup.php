<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_groups';

    protected $fillable = [
        'group_name',
        'description',
        'status',
    ];

    /**
     * Get all products that belong to this group
     */
    public function products()
    {
        return $this->hasMany(ProductMaster::class, 'group_id');
    }
}
