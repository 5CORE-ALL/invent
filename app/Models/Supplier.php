<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'category_id', 'name', 'company', 'sku', 'parent', 'phone', 'city',
        'email', 'whatsapp', 'wechat', 'alibaba', 'others', 'address', 'bank_details'
    ];

    public function ratings()
    {
        return $this->hasMany(SupplierRating::class);
    }

}
