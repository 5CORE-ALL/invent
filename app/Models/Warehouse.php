<?php

namespace App\Models;

use App\Models\Wms\Zone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'group', 'location', 'status'];

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class, 'warehouse_id');
    }
}
