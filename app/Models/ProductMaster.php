<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductMaster extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_master';

    protected $fillable = [
        'parent',
        'sku',
        'group_id',
        'Values',
        'remark',
        'sales',
        'views',
        'deleted_by'
    ];

    public function setTemuShipAttribute($value)
    {
        $values = $this->Values ?? [];
        $values['ship'] = $value;
        $this->attributes['Values'] = json_encode($values);
    }

    public function getTemuShipAttribute()
    {
        return $this->Values['ship'] ?? null;
    }

    protected $casts = [
        'Values' => 'array',
        'sales' => 'array',
        'views' => 'array',
    ];
}
