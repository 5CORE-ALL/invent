<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'category_id', 'name', 'company', 'sku', 'parent', 'phone', 'city',
        'email', 'whatsapp', 'wechat', 'alibaba', 'others', 'address', 'bank_details',
        'approval_status',
    ];

    public function ratings()
    {
        return $this->hasMany(SupplierRating::class);
    }

    public function remarkHistories()
    {
        return $this->hasMany(SupplierRemarkHistory::class)->orderByDesc('id');
    }

    public function latestRemark()
    {
        return $this->hasOne(SupplierRemarkHistory::class)->latestOfMany();
    }

}
