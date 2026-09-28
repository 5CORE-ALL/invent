<?php

namespace App\Models\Concerns;

use App\Support\DesignationKey;
use Illuminate\Database\Eloquent\Builder;

trait MatchesDesignation
{
    public function scopeForDesignation(Builder $query, string $designation): Builder
    {
        $variants = DesignationKey::variants($designation);
        if ($variants === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('designation', $variants);
    }
}
