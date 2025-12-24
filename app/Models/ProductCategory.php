<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_categories';

    protected $fillable = [
        'category_name',
        'code',
        'description',
        'status',
    ];

    /**
     * Get all products that belong to this category
     */
    public function products()
    {
        return $this->hasMany(ProductMaster::class, 'category_id');
    }
}
