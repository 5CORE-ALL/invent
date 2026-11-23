<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master list of specification labels (the "Spec" column of every comparison sheet).
 * Shared by all comparison pages; used to fill the Spec dropdowns in the Edit column modal.
 */
class ComparisonSpecification extends Model
{
    protected $table = 'comparison_specifications';

    protected $fillable = [
        'name',
        'sort_order',
        'created_by',
    ];
}
