<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceDepartment extends Model
{
    protected $fillable = ['slug', 'name', 'sort_order'];

    public function tags(): HasMany
    {
        return $this->hasMany(ResourceTag::class, 'department_id');
    }
}
