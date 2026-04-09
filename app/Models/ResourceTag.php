<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ResourceTag extends Model
{
    protected $fillable = ['slug', 'tag_name', 'department_id'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(ResourceDepartment::class, 'department_id');
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(ResourceMaster::class, 'resource_tag_map', 'resource_tag_id', 'resource_id')
            ->withTimestamps();
    }
}
